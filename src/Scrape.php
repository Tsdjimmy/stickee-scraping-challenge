<?php

namespace App;

use App\ScrapeHelper;
use App\Product;

require 'vendor/autoload.php';

class Scrape
{
    const BASE_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';

    public function run(): array
    {
        $url = self::BASE_URL;
        $products = [];

        $page = 1;

        $productHelper = new Product();

        while (true) {
            $pageUrl = $url . '?page=' . $page;
            $html = ScrapeHelper::fetchDocument($pageUrl);

            if (!$html) {
                echo 'Error fetching page ' . $pageUrl . PHP_EOL;
                continue;
            }

            $crawler = $html;

            $crawler->filter('.product')->each(function ($node) use (&$products, $url, $productHelper) {
                $colors = $productHelper->extractProductData($node);

                // Deduplicates products based on title, color, and capacity
                foreach ($colors as $colorProduct) {
                    $isDuplicate = false;
                    foreach ($products as $existingProduct) {
                        if (
                            $existingProduct['title'] === $colorProduct['title']
                            && $existingProduct['colour'] === $colorProduct['colour']
                            && $existingProduct['capacityMB'] === $colorProduct['capacityMB']
                        ) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    if (!$isDuplicate) {
                        $products[] = $colorProduct;
                    }
                }
            });

            $nextPageLink = $crawler->filter('#pages .flex-wrap a.active');
            if ($nextPageLink->count() === 0) {
                break;
            }

            $page++;
        }

        // Extracts the date from shippingText and update shippingDate
        foreach ($products as &$product) {
            $shippingText = $product['shippingText'];
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $shippingText, $matches)) {
                $shippingDate = $matches[0];
            } else {
                if (preg_match('/(\d{1,2}(?:st|nd|rd|th)? [A-Za-z]+ \d{4})/', $shippingText, $matches)) {
                    $dateString = $matches[0];
                    $dateString = preg_replace('/(st|nd|rd|th)/', '', $dateString);
                    $shippingDate = date('Y-m-d', strtotime($dateString));
                } else {
                    $shippingDate = "";
                }
            }
            $product['shippingDate'] = $shippingDate;
        }

        return $products;
    }
}

$scrape = new Scrape();
$products = $scrape->run();

if (!empty($products)) {
    file_put_contents('output.json', json_encode($products, JSON_PRETTY_PRINT));
    echo 'Scraped data saved to output.json.' . PHP_EOL;
} else {
    echo 'No data scraped or an error occurred.' . PHP_EOL;
}
