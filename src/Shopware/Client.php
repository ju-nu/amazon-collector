<?php
declare(strict_types=1);

namespace Shopware;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class Client
{
    private GuzzleClient $client;
    private string $accessToken;
    private string $clientId;
    private string $clientSecret;
    private string $apiBaseUri;

    public function __construct()
    {
        $this->clientId = $_ENV['SHOPWARE_CLIENT_ID'];
        $this->clientSecret = $_ENV['SHOPWARE_CLIENT_SECRET'];
        $this->apiBaseUri = rtrim($_ENV['SHOPWARE_API_BASE_URI'], '/');

        $this->client = new GuzzleClient([
            'base_uri' => $this->apiBaseUri,
            'timeout' => 180,
        ]);

        $this->obtainAccessToken();
    }

    private function obtainAccessToken(): void
    {
        try {
            $response = $this->client->post('/api/oauth/token', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'];
        } catch (RequestException $e) {
            echo 'Failed to obtain access token: ' . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    public function listCategories(): array
    {
        $categories = [];
        $page = 1;
        $limit = 500;

        do {
            try {
                $response = $this->client->get('/api/category', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'limit' => $limit,
                        'page' => $page,
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $categoriesPage = $data['data'] ?? [];

                foreach ($categoriesPage as $category) {
                    $categories[] = $category;
                }

                $total = $data['meta']['total'] ?? count($categories);
                $page++;
            } catch (RequestException $e) {
                echo 'Failed to list categories: ' . $e->getMessage() . PHP_EOL;
                break;
            }
        } while (count($categories) < $total);

        return $categories;
    }

    public function updateCategoryCustomField(string $categoryId, array $customFields): void
    {
        try {
            $this->client->patch("/api/category/{$categoryId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'id' => $categoryId,
                    'customFields' => $customFields,
                ],
            ]);

            echo "Updated category ID {$categoryId} with custom fields." . PHP_EOL;
        } catch (RequestException $e) {
            echo 'Failed to update category: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
