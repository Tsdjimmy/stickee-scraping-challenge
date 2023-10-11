<?php

namespace App;

require 'vendor/autoload.php';

class Scrape
{
    public function run(): array
    {
        $url = 'https://www.magpiehq.com/developer-challenge/smartphones';
        $products = [];

        $page = 1;
        $uniqueTitles = []; // This is to keep track of unique product titles

        while (true) {
            $pageUrl = $url . '?page=' . $page;
            $html = ScrapeHelper::fetchDocument($pageUrl);

            if (!$html) {
                echo 'Error fetching page ' . $pageUrl . PHP_EOL;
                continue;
            }

            $crawler = $html;

            $crawler->filter('.product')->each(function ($node) use (&$products, $url, &$uniqueTitles) {
                $title = $node->filter('.product-name')->text();

                // Checks if the title is already in the $uniqueTitles
                if (in_array($title, $uniqueTitles)) {
                    return;
                }

                $uniqueTitles[] = $title;

                $capacity = $node->filter('.product-capacity')->text();
                $priceText = $node->filter('.text-lg')->text();
                $price = (float) str_replace(['£', '£'], '', $priceText);
                $availabilityText = $node->filter('.text-sm.block.text-center')->first()->text();
                $availabilityText = trim(str_replace('Availability:', '', $availabilityText));
                $shippingText = $node->filter('.text-sm.block.text-center')->last()->text();
                $shippingText = trim(str_replace('Availability:', '', $shippingText));
                $colour = $node->filter('[data-colour]')->attr('data-colour');
                $imageUrl = $node->filter('img')->attr('src');
                $imageUrl = str_replace('..', '', $imageUrl);
                $imageUrl = $url . '/' . ltrim($imageUrl, '/');
                $isAvailable = stripos($availabilityText, 'in stock') !== false;
                $shippingDate = date('Y-m-d', strtotime(str_replace('Delivery from ', '', $shippingText)));

                $product = [
                    'title' => $title,
                    'price' => $price,
                    'imageUrl' => $imageUrl,
                    'capacityMB' => $this->convertCapacityToMB($capacity),
                    'colour' => $colour,
                    'availabilityText' => $availabilityText,
                    'isAvailable' => $isAvailable,
                    'shippingText' => $shippingText,
                    'shippingDate' => $shippingDate,
                ];
                $products[] = $product;
            });

            $nextPageLink = $crawler->filter('#pages .flex-wrap a.active');
            if ($nextPageLink->count() === 0) {
                break;
            }

            $page++;
        }

        return $products;
    }

    private function convertCapacityToMB($capacity): int
    {
        $unit = strtoupper(substr($capacity, -2));
        $value = (int) trim($capacity);
        if ($unit === 'GB') {
            return $value * 1000;
        } elseif ($unit === 'MB') {
            return $value;
        }

        return 0; // Set default to 0 if capacity cannot be determined
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
