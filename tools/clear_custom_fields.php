<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php'; // Adjust path as needed

use Shopware\Client as ShopwareClient;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Shopware API credentials
// Retrieve environment variables and then define constants
define('SHOPWARE_API_BASE_URI', $_ENV['SHOPWARE_API_BASE_URI'] ?? 'https://default-api-url.com');
define('SHOPWARE_CLIENT_ID', $_ENV['SHOPWARE_CLIENT_ID'] ?? 'default-client-id');
define('SHOPWARE_CLIENT_SECRET', $_ENV['SHOPWARE_CLIENT_SECRET'] ?? 'default-client-secret');


/**
 * Get Shopware API access token
 *
 * @return string
 */
function getAccessToken(): string
{
    $url = SHOPWARE_API_BASE_URI . '/api/oauth/token';
    $data = [
        'client_id' => SHOPWARE_CLIENT_ID,
        'client_secret' => SHOPWARE_CLIENT_SECRET,
        'grant_type' => 'client_credentials',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_POST, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['access_token'] ?? '';
}

/**
 * Fetch categories where customFields.junu_category_collection_id is not empty or null
 *
 * @param string $accessToken
 * @return array
 */
function fetchCategoriesWithNonEmptyCustomField(string $accessToken): array
{
    $url = SHOPWARE_API_BASE_URI . '/api/search/category';
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];
    $data = json_encode([
        'filter' => [
            // Check if customFields.junu_category_collection_id exists and is not empty or null
            [
                'type' => 'multi',
                'operator' => 'AND',
                'queries' => [
                    [
                        'type' => 'not',
                        'field' => 'customFields.junu_category_collection_id',
                        'value' => null,
                    ],
                    [
                        'type' => 'not',
                        'field' => 'customFields.junu_category_collection_id',
                        'value' => '',
                    ],
                ]
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_POST, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['data'] ?? [];
}

/**
 * Get the current value of customFields for a specific category
 *
 * @param string $categoryId
 * @param string $accessToken
 * @return mixed
 */
function getCategoryCustomFieldValue(string $categoryId, string $accessToken)
{
    $url = SHOPWARE_API_BASE_URI . '/api/category/' . $categoryId;
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['data']['customFields']['junu_category_collection_id'] ?? null;
}

/**
 * Set the customFields.junu_category_collection_id to an empty string for a specific category
 *
 * @param string $categoryId
 * @param string $accessToken
 * @return bool
 */
function clearCustomFieldForCategory(string $categoryId, string $accessToken): bool
{
    $url = SHOPWARE_API_BASE_URI . '/api/category/' . $categoryId;
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];
    $data = json_encode([
        'customFields' => [
            'junu_category_collection_id' => ''
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 204;
}

/**
 * Main function to find categories with non-empty customFields.junu_category_collection_id and set them to empty string
 */
function clearCustomFieldsForCategories()
{
    $accessToken = getAccessToken();

    if ($accessToken) {
        $categories = fetchCategoriesWithNonEmptyCustomField($accessToken);

        foreach ($categories as $category) {
            $categoryId = $category['id'];

            // Before clearing, log the current value
            $currentValue = getCategoryCustomFieldValue($categoryId, $accessToken);
            echo "Current value of customFields.junu_category_collection_id for category $categoryId: $currentValue\n";

            if (clearCustomFieldForCategory($categoryId, $accessToken)) {
                echo "Successfully set custom field to empty for category: $categoryId\n";
            } else {
                echo "Failed to set custom field for category: $categoryId\n";
            }

            // Verify if the custom field is now empty
            $newValue = getCategoryCustomFieldValue($categoryId, $accessToken);
            echo "New value of customFields.junu_category_collection_id for category $categoryId: $newValue\n";
        }
    } else {
        echo "Failed to retrieve access token.\n";
    }
}

// Run the main function
clearCustomFieldsForCategories();

