
<?php

declare(strict_types=1);

/*
 * This file is part of Fooladgharb Bundle.
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
use Throwable;

#[AsHook('storeFormData')]
class SendFormDataListener
{
    private const BASE_URL = 'https://my.fooladgharb.com/api';

    private const TARGET_TABLE = 'tl_fooladgharb_requests';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(FOOLADGHARB_CRM_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function __invoke(array $data, Form $form): array
    {
        if (self::TARGET_TABLE !== $form->targetTable) {
            return $data;
        }

        $data = $this->flattenArrayValues($data);

        $data['send_status'] = 0;
        $data['lead_id'] = '';
        $data['client_id'] = '';

        try {
            $this->logger->info('CRM API key configuration check', [
                'configured' => $this->apiKey !== '',
                'length' => strlen($this->apiKey),
            ]);

            $authToken = $this->getAuthToken();

            if (null === $authToken) {
                $this->logger->error('CRM authentication failed. No authtoken received.');

                return $data;
            }

            $result = $this->sendLead(
                $this->buildLeadData($data),
                $authToken,
            );

            if (null === $result) {
                return $data;
            }

            $data['send_status'] = 1;
            $data['lead_id'] = (string) ($result['lead_id'] ?? '');
            $data['client_id'] = (string) ($result['client_id'] ?? '');

            $this->logger->info('CRM lead submitted successfully.', [
                'lead_id' => $data['lead_id'],
                'client_id' => $data['client_id'],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('CRM API request failed.', [
                'message' => $e->getMessage(),
            ]);
        }

        return $data;
    }

    private function getAuthToken(): ?string
    {
        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/token',
            [
                'headers' => [
                    'X-API-KEY' => trim($this->apiKey),
                ],
            ],
        );

        $statusCode = $response->getStatusCode();
        $responseData = $response->toArray(false);

        if (200 !== $statusCode || empty($responseData['result'])) {
            $this->logger->error('CRM token request failed.', [
                'http_status' => $statusCode,
                'response' => $responseData,
            ]);

            return null;
        }

        return (string) $responseData['result'];
    }

    private function sendLead(array $leadData, string $authToken): ?array
    {
        $response = $this->httpClient->request(
            'POST',
            self::BASE_URL . '/leads/v1/add',
            [
                'headers' => [
                    'authtoken' => $authToken,
                    'X-API-KEY' => trim($this->apiKey),
                ],
                'body' => $leadData,
            ],
        );

        $statusCode = $response->getStatusCode();
        $responseData = $response->toArray(false);

        if (200 !== $statusCode || empty($responseData['status'])) {
            $this->logger->error('CRM lead submission failed.', [
                'http_status' => $statusCode,
                'response' => $responseData,
            ]);

            return null;
        }

        return $responseData['data'] ?? [];
    }

    private function buildLeadData(array $data): array
    {
        $description = [
            'شرکت: ' . ($data['company'] ?? ''),
            'محل پروژه: ' . ($data['location'] ?? ''),
            'محصول: ' . ($data['product'] ?? ''),
            'نوع محصول: ' . ($data['type'] ?? ''),
            'کاربری: ' . ($data['usage'] ?? ''),
            'نوع عایق: ' . ($data['insulation'] ?? ''),
            'متراژ: ' . ($data['area'] ?? ''),
            'نحوه تحویل: ' . ($data['delivery'] ?? ''),
            'تاریخ: ' . ($data['date'] ?? ''),
            'ساعت: ' . ($data['time'] ?? ''),
            'توضیحات: ' . ($data['message'] ?? ''),
            'آدرس صفحه: ' . ($data['url'] ?? ''),
        ];

        $description = array_filter(
            $description,
            static fn (string $value): bool => !str_ends_with($value, ': '),
        );

        return [
            'lead_process_id' => '1',
            'source' => '15',
            'name' => !empty($data['name']) ? $data['name'] : 'بدون نام',
            'mobile' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'address' => $data['location'] ?? '',
            'description' => implode("\n", $description),
        ];
    }

    private function flattenArrayValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = implode(', ', array_filter($value));
            }
        }

        return $data;
    }
}
