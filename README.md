# Amazon ASIN Collector

This project collects Amazon ASINs based on search terms retrieved from Shopware categories and manages them using the Rainforest API.

## Features

- Connects to the Shopware 6 API to retrieve categories.
- Uses custom fields in Shopware categories to determine search terms.
- Interacts with the Rainforest API to manage collections and ASINs.
- Optimized for PHP 8.3.
- Organized code structure with PSR-4 autoloading.

## Requirements

- PHP 8.3 or higher
- Composer
- Shopware 6 API credentials
- Rainforest API key
- Shopware Category Custom Fields (see [Custom Fields Setup](#custom-fields-setup))

## Installation

1. **Clone the repository**:

   ```bash
   git clone https://github.com/ju-nu/amazon-collector.git
   cd amazon-collector
   ```

2. **Install dependencies via Composer**:

   ```bash
   composer install
   ```

3. **Copy the `.env.example` file to `.env` and fill in your credentials**:

   ```bash
   cp .env.example .env
   ```

   Edit the `.env` file and provide your Shopware API credentials and Rainforest API key.

## Custom Fields Setup

To ensure the script works correctly, you need to create the following custom fields in your Shopware categories:

1. **`junu_category_collection`** (Boolean)

   - **Description**: A flag to indicate whether the category should be processed by the script.
   - **Values**:
     - `true`: The category will be processed.
     - `false` or unset: The category will be skipped.

2. **`junu_category_collection_search`** (String)

   - **Description**: The search term to be used when querying Amazon via the Rainforest API. If not set, the category name will be used as the search term.
   - **Usage**: Provide a specific search term if you want to override the category name.

3. **`junu_category_collection_id`** (String)

   - **Description**: Stores the collection ID from the Rainforest API corresponding to the category.
   - **Note**: This field is automatically populated by the script when a new collection is created. You don't need to set this manually.

## Usage

Run the script using the PHP CLI:

```bash
php main.php
```

## Configuration

- **Environment Variables**: All configuration is done via the `.env` file. Ensure you have the correct values set for your environment.

## License

This project is licensed under the MIT License.