<?php
declare(strict_types=1);

namespace Shopware;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    private GuzzleClient $client;
    private string $accessToken;
    private int $tokenExpiresAt;
    private string $clientId;
    private string $clientSecret;
    private string $apiBaseUri;

    public function __construct()
    {
        $this->clientId = $_ENV['SHOPWARE_CLIENT_ID'];
        $this->clientSecret = $_ENV['SHOPWARE_CLIENT_SECRET'];
        $this->apiBaseUri = rtrim($_ENV['SHOPWARE_API_BASE_URI'], '/');

        $this->obtainAccessToken();

        // Create a handler stack and add middleware
        $stack = HandlerStack::create();
        $stack->push($this->addAuthorizationHeader());
        $stack->push($this->refreshTokenMiddleware());

        $this->client = new GuzzleClient([
            'base_uri' => $this->apiBaseUri,
            'timeout' => 180,
            'handler' => $stack,
        ]);
    }

    private function obtainAccessToken(): void
    {
        try {
            $response = (new GuzzleClient([
                'base_uri' => $this->apiBaseUri,
                'timeout' => 180,
            ]))->post('/api/oauth/token', [
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

            // Store the token expiration time
            $this->tokenExpiresAt = time() + $data['expires_in'] - 30; // Subtract 30 seconds as a buffer

        } catch (RequestException $e) {
            echo 'Failed to obtain access token: ' . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    private function isTokenExpired(): bool
    {
        return time() >= $this->tokenExpiresAt;
    }

    private function addAuthorizationHeader()
    {
        return Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader('Authorization', 'Bearer ' . $this->accessToken);
        });
    }

    private function refreshTokenMiddleware()
    {
        return Middleware::retry(function (
            $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?RequestException $exception = null
        ) {
            if ($retries >= 1) {
                return false;
            }

            if ($response && $response->getStatusCode() === 401) {
                echo 'Access token expired, refreshing token and retrying...' . PHP_EOL;
                $this->obtainAccessToken();
                return true;
            }

            return false;
        }, function () {
            return 0; // No delay between retries
        });
    }

    public function request(string $method, string $uri, array $options = [])
    {
        // Add Accept header
        $options['headers']['Accept'] = 'application/json';

        return $this->client->request($method, $uri, $options);
    }

    public function listCategories(): array
    {
        $categories = [];
        $page = 1;
        $limit = 500;

        do {
            try {
                $response = $this->request('GET', '/api/category', [
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
            $this->request('PATCH', "/api/category/{$categoryId}", [
                'headers' => [
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
