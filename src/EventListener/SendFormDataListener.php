<?php

declare(strict_types=1);

namespace Respinar\ContaoFooladgharbBundle\EventListener;

use Psr\Log\LoggerInterface;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsHook('storeFormData')]
class SendFormDataListener
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $contaoGeneralLogger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $contaoGeneralLogger;
    }

    public function __invoke(array $data, Form $form): array
    {

        $api_key = '$2a$08$T6x8FV.j/mAE./sa4PxO0Ofin1ph21vfd';

        $token = $this->getAuthToken($api_key);

        if (!$token) {
            $this->logger->error('Failed to get auth token.');
            return $data;
        }

        $this->logger->info('Auth token retrieved successfully.');

        $leadData = [
            'lead_process_id'       => '1',
            'source'                => '15',
            'name'                  => $data['name'] ?? 'بدون نام',
            'mobile'                => $data['phone'] ?? '',
            'address'               => $data['address'] ?? '',
            'email'                 => $data['email'] ?? '',
            'custom_fields[leads][7]'  => $data['national_id'] ?? '',
            'custom_fields[leads][8]'  => $data['code'] ?? '',
            'custom_fields[leads][9]'  => $data['branch'] ?? '',
            'custom_fields[leads][10]' => $data['country'] ?? '',
        ];

        if (!empty($data['upload']) && file_exists($data['upload'])) {
            $leadData['file[]'] = new \SplFileInfo($data['upload']);
        }

        $response = $this->sendLead($leadData, $api_key, $token);
        
        if ($response) {
            $this->logger->info('Lead sent successfully via API.');
            $data['send_status'] = 1;
            $data['lead_id'] = $response['lead_id'];
            $data['client_id'] = $response['client_id'];
        } else {
            $this->logger->error('Failed to send lead via API.');
        }

        return $data;
    }

    private function getAuthToken(string $api_key): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://my.fooladgharb.com/api/token', [
                'headers' => [
                    'x-api-key' => $api_key ?? throw new \RuntimeException('CRM_API_KEY not set'),
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                if (isset($data['result'])) {
                    $this->logger->info('Auth token retrieved: ' . $data['result']);
                    return $data['result'];
                }
                $this->logger->error('No result key found in API response: ' . $response->getContent());
                return null;
            }

            $this->logger->error('Auth API returned non-200 status: ' . $response->getStatusCode());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Auth API error: ' . $e->getMessage());
            return null;
        }
    }

    private function sendLead(array $leadData, string $api_key, string $authToken): ?array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://my.fooladgharb.com/api/leads/v1/add', [
                'headers' => [
                    'authtoken' => $authToken,
                    'x-api-key' => $api_key ?? throw new \RuntimeException('CRM_API_KEY not set'),
                    // Add cookies if required (see below)
                    // 'Cookie' => 'csrf_cookie_name=28d1a96241d672ef34ad63005bc7eecc; sp_session=maibm2oij0qtlnnmosk2c92iko53l0pu',
                ],
                'body' => $leadData, // Use json_encode($leadData) if API expects JSON
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                $this->logger->info('Lead sent successfully: ' . $response->getContent());
                return $data;
            }

            $this->logger->error('Lead API returned non-200 status: ' . $response->getStatusCode() . ', response: ' . $response->getContent());
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Lead API error: ' . $e->getMessage());
            return null;
        }
    }
}
