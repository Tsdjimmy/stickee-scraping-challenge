<?php

use App\ScrapeHelper;

require 'vendor/autoload.php';

class Scrape
{
    const BASE_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';
    const TITLE_SELECTOR = '.product-name';
    const CAPACITY_SELECTOR = '.product-capacity';
    const PRICE_SELECTOR = '.text-lg';
    const AVAILABILITY_SELECTOR = '.text-sm.block.text-center';
    const SHIPPING_SELECTOR = '.text-sm.block.text-center';
    const IMAGE_SELECTOR = 'img';
    const COLOUR_SELECTOR = '[data-colour]';

    public function run(): array
    {
        $url = self::BASE_URL;
        $products = [];

        $page = 1;

        while (true) {
            $pageUrl = $url . '?page=' . $page;
            $html = ScrapeHelper::fetchDocument($pageUrl);

            if (!$html) {
                echo 'Error fetching page ' . $pageUrl . PHP_EOL;
                continue;
            }

            $crawler = $html;

            $crawler->filter('.product')->each(function ($node) use (&$products, $url) {
                $title = $node->filter(self::TITLE_SELECTOR)->text();
                $capacity = $node->filter(self::CAPACITY_SELECTOR)->text();
                $priceText = $node->filter(self::PRICE_SELECTOR)->text();
                $price = (float) str_replace(['£', '£'], '', $priceText);
                $availabilityText = $node->filter(self::AVAILABILITY_SELECTOR)->first()->text();
                $availabilityText = trim(str_replace('Availability:', '', $availabilityText));
                $shippingText = $node->filter(self::SHIPPING_SELECTOR)->last()->text();
                $shippingText = trim(str_replace('Availability:', '', $shippingText));
                $imageUrl = $node->filter(self::IMAGE_SELECTOR)->attr('src');
                $imageUrl = str_replace('..', '', $imageUrl);
                $imageUrl = $url . '/' . ltrim($imageUrl, '/');
                $isAvailable = stripos($availabilityText, 'in stock') !== false;
                $shippingDate = date('Y-m-d', strtotime(str_replace('Delivery from ', '', $shippingText)));

                // Fetch color variants
                $colorVariants = $node->filter(self::COLOUR_SELECTOR);
                $colors = $colorVariants->each(function ($colorNode) use ($title, $capacity, $price, $availabilityText, $shippingText, $imageUrl, $isAvailable, $shippingDate) {
                    $colour = $colorNode->attr('data-colour');
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
                    return $product;
                });

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
