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
use Throwable;

#[AsHook('storeFormData')]
class SendFormDataListener
{
    private const API_BASE_URL = 'https://my.fooladgharb.com/api';

    public function __construct(
        private readonly HttpClientInterface $httpClient,

        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,

        #[Autowire('%env(FOOLADGHARB_CRM_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function __invoke(array $data, Form $form): array
    {
        // Only process the intended Contao form.
        if ('tl_fooladgharb_requests' !== $form->targetTable) {
            return $data;
        }

        if ('' === $this->apiKey) {
            $this->logger->error('CRM: API key is not configured.');

            return $data;
        }

        try {
            // STEP 1: Get authentication token.
            $token = $this->getAuthToken();

            if (null === $token) {
                return $data;
            }

            // STEP 2: Prepare lead data.
            $leadData = [
                'lead_process_id' => '1',
                'source' => '15',

                // Standard CRM fields.
                'name' => $data['name'] ?? 'بدون نام',
                'mobile' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'address' => $data['location'] ?? '',

                // Customer message and RFQ details.
                'description' => implode("\n", array_filter([
                    'Company: '.($data['company'] ?? ''),
                    'Product: '.($data['product'] ?? ''),
                    'Type: '.($data['type'] ?? ''),
                    'Usage: '.($data['usage'] ?? ''),
                    'Insulation: '.($data['insulation'] ?? ''),
                    'Area: '.($data['area'] ?? ''),
                    'Delivery: '.($data['delivery'] ?? ''),
                    'Date: '.($data['date'] ?? ''),
                    'Time: '.($data['time'] ?? ''),
                    'Message: '.($data['message'] ?? ''),
                    'URL: '.($data['url'] ?? ''),
                ])),
            ];

            // STEP 3: Attach uploaded file if available.
            if (
                !empty($data['upload'])
                && is_string($data['upload'])
                && is_file($data['upload'])
                && is_readable($data['upload'])
            ) {
                $leadData['file[]'] = new \SplFileInfo($data['upload']);
            }

            // STEP 4: Submit lead.
            $response = $this->sendLead($leadData, $token);

            if (null === $response) {
                return $data;
            }

            // STEP 5: Store CRM response in Contao form data.
            $data['send_status'] = 1;
            $data['lead_id'] = (string) ($response['lead_id'] ?? '');
            $data['client_id'] = (string) ($response['client_id'] ?? '');

            if ('' === $data['lead_id'] || '' === $data['client_id']) {
                $this->logger->error('CRM: Successful response is missing required identifiers.', [
                    'lead_id_received' => '' !== $data['lead_id'],
                    'client_id_received' => '' !== $data['client_id'],
                ]);
            }
        } catch (Throwable $exception) {
            $this->logger->error('CRM: Unexpected error during submission.', [
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);
        }

        return $data;
    }

    private function getAuthToken(): ?string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::API_BASE_URL.'/token',
                [
                    'headers' => [
                        'X-API-KEY' => $this->apiKey,
                    ],
                    'timeout' => 30,
                ],
            );

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if (200 !== $statusCode || empty($responseData['status'])) {
                $this->logger->error('CRM TOKEN: Authentication request failed.', [
                    'http_status' => $statusCode,
                ]);

                return null;
            }

            $token = $responseData['result'] ?? null;

            if (!is_string($token) || '' === $token) {
                $this->logger->error('CRM TOKEN: Invalid token response.');

                return null;
            }

            return $token;
        } catch (Throwable $exception) {
            $this->logger->error('CRM TOKEN: Request exception.', [
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }

    private function sendLead(array $leadData, string $authToken): ?array
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                self::API_BASE_URL.'/leads/v1/add',
                [
                    'headers' => [
                        'authtoken' => $authToken,
                        'X-API-KEY' => $this->apiKey,
                    ],
                    'body' => $leadData,
                    'timeout' => 30,
                ],
            );

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if (200 !== $statusCode || empty($responseData['status'])) {
                $this->logger->error('CRM LEAD: Submission rejected.', [
                    'http_status' => $statusCode,
                    'crm_status' => $responseData['status'] ?? null,
                    'message' => $responseData['message'] ?? null,
                ]);

                return null;
            }

            /*
             * CRM response structure:
             *
             * {
             *     "status": true,
             *     "message": "...",
             *     "lead_id": "...",
             *     "client_id": "..."
             * }
             *
             * Both IDs are at the top level.
             */

            return [
                'lead_id' => $responseData['lead_id'] ?? '',
                'client_id' => $responseData['client_id'] ?? '',
            ];
        } catch (Throwable $exception) {
            $this->logger->error('CRM LEAD: Request exception.', [
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }
}
