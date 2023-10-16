<?php

namespace App;

class Product
{
    const BASE_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';
    const TITLE_SELECTOR = '.product-name';
    const CAPACITY_SELECTOR = '.product-capacity';
    const PRICE_SELECTOR = '.text-lg';
    const AVAILABILITY_SELECTOR = '.text-sm.block.text-center';
    const SHIPPING_SELECTOR = '.text-sm.block.text-center';
    const IMAGE_SELECTOR = 'img';
    const COLOUR_SELECTOR = '[data-colour]';

    public function extractProductData($node)
    {
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
        $imageUrl = self::BASE_URL . '/' . ltrim($imageUrl, '/');
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

        return $colors;
    }

    private function convertCapacityToMB($capacity)
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
