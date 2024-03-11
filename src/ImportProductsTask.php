<?php

namespace IcarAPI;

use Exception;
use Shuchkin\SimpleXLSX;

defined('ABSPATH') or die;

class ImportProductsTask
{       
    public function __invoke(string $filepath): void
    {
        set_time_limit(DAY_IN_SECONDS);
        wp_suspend_cache_addition(true);

        $xlsx = SimpleXLSX::parseFile($filepath);

        if (! $xlsx) {
            throw new Exception(__('Can\'t parse file.'));
        }

        $saver = new Saver;
        $headers = [];
        foreach ($xlsx->readRows() as $i => $row) {
            if ($i == 0) {
                $headers = $row;
                continue;
            }

            $row = array_combine($headers, $row);
            
            $prices = [
                'Refurbished' => (string) $row['Refurbished'] ?: '',
                'Exchange' => (string) $row['Exchange'] ?: '',
                'Core' => (string) $row['Core'] ?: '',
                'Repair' => (string) $row['Repair'] ?: '',
            ];
            $product = new Product(
                $row['Model'], 
                '', 
                $row['Manufacturer'], 
                '', 
                $row['Category'], 
                '', 
                $prices, 
                ''
            );

            $saver->saveProduct($product);
        }
    }
}