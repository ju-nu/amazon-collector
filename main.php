<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php'; // Load Composer dependencies, including autoload

use Amazon\Collector;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize and process categories
$collector = new Collector();
$collector->processCategories();
