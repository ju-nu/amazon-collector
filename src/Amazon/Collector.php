<?php
declare(strict_types=1);

namespace Amazon;

use Shopware\Client as ShopwareClient;
use Rainforest\Client as RainforestClient;

class Collector
{
    private RainforestClient $rainforestClient;
    private ShopwareClient $shopwareClient;

    public function __construct()
    {
        $this->shopwareClient = new ShopwareClient();
        $this->rainforestClient = new RainforestClient();
    }

    public function processCategories(): void
    {
        $categories = $this->shopwareClient->listCategories();

        foreach ($categories as $category) {
            $customFields = $category['customFields'] ?? [];
            $junuCollectionFlag = $customFields['junu_category_collection'] ?? false;

            // Check if junu_category_collection is true
            if (!$junuCollectionFlag) {
                continue;
            }

            $categoryId = $category['id'];
            $collectionId = $customFields['junu_category_collection_id'] ?? null;
            $searchTerm = $customFields['junu_category_collection_search'] ?? $category['name'] ?? null;

            if (empty($searchTerm)) {
                echo "No search term for category ID {$categoryId}, skipping..." . PHP_EOL;
                continue;
            }

            // Check if the collection exists or create it
            $collectionName = $searchTerm;
            $collectionCreated = false;

            if (empty($collectionId)) {
                $collectionCreated = $this->checkOrCreateCollection($collectionName, $collectionId);

                // If we created a new collection, update junu_category_collection_id
                if ($collectionCreated) {
                    // Update junu_category_collection_id in Shopware
                    $customFields['junu_category_collection_id'] = $collectionId;
                    $this->shopwareClient->updateCategoryCustomField($categoryId, $customFields);
                }
            } else {
                // Collection ID exists, but ensure it exists in Rainforest API
                $this->checkOrCreateCollection($collectionName, $collectionId);
            }

            // Get existing ASINs in the collection
            $existingAsins = $this->listAsinsInCollection($collectionId);
            echo "Existing ASINs in collection $collectionId: " . implode(', ', $existingAsins) . PHP_EOL;

            // Search for new ASINs on Amazon
            echo "Searching Amazon for: $searchTerm" . PHP_EOL;
            $result = $this->searchAmazon($searchTerm);
            $newAsins = [];

            if (isset($result['search_results'])) {
                foreach ($result['search_results'] as $item) {
                    $asin = $item['asin'] ?? null;

                    // Skip if ASIN is already in the collection
                    if ($asin && !in_array($asin, $existingAsins, true)) {
                        $newAsins[] = $asin;
                    } else {
                        echo "ASIN $asin already in collection, skipping..." . PHP_EOL;
                    }
                }

                // Add new ASINs to the collection in batches
                if (!empty($newAsins)) {
                    echo "Adding new ASINs to collection $collectionId..." . PHP_EOL;
                    $this->addAsinsToCollection($collectionId, $newAsins);
                } else {
                    echo "No new ASINs found for: $searchTerm" . PHP_EOL;
                }
            } else {
                echo "No search results for: $searchTerm" . PHP_EOL;
            }
        }
    }

    private function checkOrCreateCollection(string $collectionName, ?string &$collectionId): bool
    {
        $page = 1;
        $collectionFound = false;
        $collectionCreated = false;

        do {
            $response = $this->rainforestClient->request('GET', 'collections', [
                'query' => [
                    'page' => $page,
                ],
            ]);

            if ($response === null) {
                break;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $collections = $data['collections'] ?? [];

            foreach ($collections as $collection) {
                if ($collection['name'] === $collectionName) {
                    $collectionId = $collection['id'];
                    $collectionFound = true;
                    echo "Found existing collection: $collectionName with ID $collectionId" . PHP_EOL;
                    break 2;
                }
            }

            $totalPages = $data['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        if (!$collectionFound) {
            // Create the collection
            $body = [
                "name" => $collectionName,
                "enabled" => true,
                "schedule_type" => "weekly",
                "schedule_days_of_week" => strval(rand(0, 5)),
                "schedule_hours" => strval(rand(0, 5)),
                "priority" => "normal",
                "notification_webhook" => 'https://webhook.real-markt.de/import.php',
                "requests_type" => 'product',
                "notification_as_json" => true
            ];            

            $response = $this->rainforestClient->request('POST', 'collections', [
                'json' => $body
            ]);

            if ($response === null) {
                exit(1);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $collectionId = $data['collection']['id'] ?? null;

            if ($collectionId) {
                echo "Created new collection: $collectionName with ID $collectionId" . PHP_EOL;
                $collectionCreated = true;
            } else {
                echo "Failed to create collection: $collectionName" . PHP_EOL;
                exit(1);
            }
        }

        return $collectionCreated;
    }

    private function listAsinsInCollection(string $collectionId): array
    {
        $asins = [];
        $page = 1;

        do {
            $response = $this->rainforestClient->request('GET', sprintf('collections/%s/requests/%d', $collectionId, $page));

            if ($response === null) {
                break;
            }

            $statusCode = $response->getStatusCode();

            if ($statusCode === 500) {
                // Handle the specific case where the collection has no requests
                $body = json_decode($response->getBody()->getContents(), true);
                if (($body['request_info']['message'] ?? '') === 'Collection has no Requests') {
                    echo "Collection $collectionId is empty. Proceeding to add new ASINs." . PHP_EOL;
                    return [];
                } else {
                    echo 'Failed to list ASINs in collection: ' . ($body['request_info']['message'] ?? 'Unknown error') . PHP_EOL;
                    break;
                }
            } elseif ($statusCode !== 200) {
                echo 'Failed to list ASINs in collection: HTTP ' . $statusCode . PHP_EOL;
                break;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $requests = $data['requests'] ?? [];

            foreach ($requests as $request) {
                if ($request['type'] === 'product') {
                    $asins[] = $request['asin'];
                }
            }

            $totalPages = $data['requests_page_count'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $asins;
    }

    private function addAsinsToCollection(string $collectionId, array $asins): void
    {
        $asinChunks = array_chunk($asins, 1000);

        foreach ($asinChunks as $asinBatch) {
            $body = [
                "requests" => array_map(fn($asin) => [
                    'type' => 'product',
                    'amazon_domain' => $_ENV['COL_AMAZON_DOMAIN'],
                    'asin' => $asin,
                    'include_summarization_attributes' => 'true',
                    'include_a_plus_body' => 'true',
                    'language' => $_ENV['COL_LANGUAGE'],
                    'currency' => $_ENV['COL_CURRENCY'],
                    'customer_zipcode' => $_ENV['COL_CUSTOMER_ZIPCODE'],
                    'include_html' => 'false'
                ], $asinBatch)
            ];

            $response = $this->rainforestClient->request('PUT', sprintf('collections/%s', $collectionId), [
                'json' => $body
            ]);

            if ($response !== null) {
                echo "Added ASINs to collection $collectionId: " . implode(', ', $asinBatch) . PHP_EOL;
            }
        }
    }

    private function searchAmazon(string $searchTerm): ?array
    {
        $response = $this->rainforestClient->request('GET', 'request', [
            'query' => [
                'type' => 'search',
                'amazon_domain' => $_ENV['COL_AMAZON_DOMAIN'],
                'search_term' => $searchTerm,
                'exclude_sponsored' => 'true',
                'language' => $_ENV['COL_LANGUAGE'],
                'currency' => $_ENV['COL_CURRENCY'],
                'customer_zipcode' => $_ENV['COL_CUSTOMER_ZIPCODE'],
                'output' => 'json',
                'include_html' => 'false',
                'page' => '1',
                'sort_by' => 'featured'
            ]
        ]);

        if ($response === null) {
            return null;
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
