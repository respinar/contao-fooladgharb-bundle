<?php

declare(strict_types=1);

/*
 * This file is part of Contao Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti
 *
 * @license MIT
 */

namespace Respinar\ContaoFooladgharbBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsHook('storeFormData')]
class SendFormDataListener
{
    private const BASE_URL = 'https://my.fooladgharb.com/api';
    private const TARGET_TABLE = 'tl_fooladgharb_requests';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $contaoGeneralLogger,
        // Set FOOLADGHARB_CRM_KEY in .env.local (and rotate the old key!)
        #[Autowire('%env(FOOLADGHARB_CRM_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function __invoke(array $data, Form $form): array
    {
        // Only handle the RFQ form that stores into our table
        if (self::TARGET_TABLE !== $form->targetTable) {
            return $data;
        }

        // Multi-value form fields (checkbox groups, multi-selects) arrive as
        // arrays, but every target column is a plain string: flatten them so
        // no serialized-array garbage (e.g. "a:2:{...}") ends up in the record.
        $data = $this->flattenArrayValues($data);

        // Defaults match the DCA (string columns, default ''):
        // a CRM failure must never break the user's form submission
        $data['send_status'] = 0;
        $data['lead_id'] = '';
        $data['client_id'] = '';

        try {
            $token = $this->getAuthToken();

            if (null === $token) {
                $this->contaoGeneralLogger->error('Failed to get auth token.');

                return $data;
            }

            $response = $this->sendLead($this->buildLeadData($data), $token);

            if (null === $response) {
                $this->contaoGeneralLogger->error('Failed to send lead via API.');

                return $data;
            }

            $data['send_status'] = 1;
            $data['lead_id'] = (string) ($response['lead_id'] ?? '');
            $data['client_id'] = (string) ($response['client_id'] ?? '');

            if ('' === $data['lead_id']) {
                $this->contaoGeneralLogger->warning('Lead accepted by API but no lead_id in response.');
            } else {
                $this->contaoGeneralLogger->info('Lead sent successfully via API. lead_id='.$data['lead_id']);
            }
        } catch (\Throwable $e) {
            $this->contaoGeneralLogger->error('Unexpected error while sending lead: '.$e->getMessage());
        }

        return $data;
    }

    /**
     * Multi-value form fields (checkbox groups, multi-selects) submit arrays,
     * but every column of tl_fooladgharb_requests is a plain string column.
     */
    private function flattenArrayValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = implode(', ', array_map(
                    static fn ($item): string => \is_scalar($item) ? (string) $item : '',
                    $value,
                ));
            }
        }

        return $data;
    }

    /**
     * Maps the fields of tl_fooladgharb_requests to the CRM lead payload.
     */
    private function buildLeadData(array $data): array
    {
        $v = static fn (string $key): string => trim(
            \is_array($data[$key] ?? null) ? implode(', ', $data[$key]) : (string) ($data[$key] ?? '')
        );

        $lines = [];
        $add = static function (string $label, string $value) use (&$lines): void {
            if ('' !== $value) {
                $lines[] = $label.': '.$value;
            }
        };

        $add('استان', $v('province'));
        $add('محصول', $v('product'));
        $add('نوع', $v('type'));
        $add('کاربری', $v('usage'));
        $add('عایق', $v('insulation'));
        $add('متراژ', $v('area'));
        $add('نحوه تحویل', $v('delivery'));
        $add('تاریخ', trim($v('date').' '.$v('time')));
        $add('پیام', $v('message'));
        $add('صفحه', $v('url'));

        return [
            'lead_process_id' => '1',
            'source' => '15',
            'name' => '' !== $v('name') ? $v('name') : 'بدون نام',
            'mobile' => $v('phone'),
            'email' => $v('email'),
            'address' => $v('province'),
            // Assumption: the CRM accepts "description" (Perfex-style). Verify in the API docs.
            'description' => implode("\n", $lines),
        ];
    }

    private function getAuthToken(): ?string
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL.'/token', [
                'headers' => [
                    // Stock Perfex CRM authenticates /api/token via the
                    // "authtoken" header; keep "x-api-key" as well in case
                    // the deployment expects it.
                    'authtoken' => $this->apiKey,
                    'x-api-key' => $this->apiKey,
                ],
                'timeout' => 5,
                'max_duration' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                // Log the response body so the CRM's actual rejection reason
                // (invalid key, wrong auth scheme, WAF block page) is visible
                $this->contaoGeneralLogger->error(
                    \sprintf(
                        'Auth API returned HTTP %d: %s',
                        $response->getStatusCode(),
                        mb_substr($response->getContent(false), 0, 500),
                    ),
                );

                return null;
            }

            $json = $response->toArray(false);

            if (!empty($json['result']) && \is_string($json['result'])) {
                // Never log the token itself
                $this->contaoGeneralLogger->info('Auth token retrieved.');

                return $json['result'];
            }

            $this->contaoGeneralLogger->error('No "result" key found in auth API response.');

            return null;
        } catch (\Throwable $e) {
            $this->contaoGeneralLogger->error('Auth API error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Returns the decoded response only if the CRM really created the lead.
     */
    private function sendLead(array $leadData, string $authToken): ?array
    {
        try {
            $response = $this->httpClient->request('POST', self::BASE_URL.'/leads/v1/add', [
                'headers' => [
                    'authtoken' => $authToken,
                    'x-api-key' => $this->apiKey,
                ],
                'body' => $leadData, // form-encoded
                'timeout' => 10,
                'max_duration' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if (200 !== $statusCode) {
                $this->contaoGeneralLogger->error(sprintf('Lead API returned HTTP %d: %s', $statusCode, $content));

                return null;
            }

            $json = json_decode($content, true);

            if (!\is_array($json)) {
                $this->contaoGeneralLogger->error('Lead API returned a non-JSON body: '.mb_substr($content, 0, 500));

                return null;
            }

            // HTTP 200 does not mean success: check the "status" flag in the body
            if (empty($json['status'])) {
                $this->contaoGeneralLogger->error('Lead API rejected the lead: '.$content, [
                    'sent_fields' => array_keys($leadData),
                ]);

                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            $this->contaoGeneralLogger->error('Lead API error: '.$e->getMessage());

            return null;
        }
    }
}
