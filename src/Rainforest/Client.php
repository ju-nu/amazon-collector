<?php
declare(strict_types=1);

namespace Rainforest;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

class Client
{
    protected GuzzleClient $client;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['COL_RAINFOREST_API_KEY'];

        $this->client = new GuzzleClient([
            'base_uri' => 'https://api.rainforestapi.com/',
            'timeout'  => 180,
        ]);
    }

    public function request(string $method, string $uri, array $options = [])
    {
        $options['query']['api_key'] = $this->apiKey;

        try {
            return $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                return $response; // Return the response for further handling
            } else {
                echo 'Rainforest API request failed: ' . $e->getMessage() . PHP_EOL;
                return null;
            }
        }
    }
}
