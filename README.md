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

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/ju-nu/amazon-collector.git
   cd amazon-collector
   ```

2. Install dependencies via Composer:

   ```bash
   composer install
   ```

3. Copy the `.env.example` file to `.env` and fill in your credentials:

   ```bash
   cp .env.example .env
   ```

   Edit the `.env` file and provide your Shopware API credentials and Rainforest API key.

## Usage

Run the script using the PHP CLI:

```bash
php main.php
```

## Configuration

- **Environment Variables**: All configuration is done via the `.env` file. Ensure you have the correct values set for your environment.

## License

This project is licensed under the MIT License.
