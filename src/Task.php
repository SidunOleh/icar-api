<?php

namespace IcarAPI;

use Exception;
use IcarAPI\IcarAPIService;
use IcarAPI\Saver;
use Wa72\SimpleLogger\FileLogger;

defined('ABSPATH') or die;

class Task
{       
    public function __invoke()
    {
        set_time_limit(DAY_IN_SECONDS);

        wp_suspend_cache_addition(true);

        $icarApi = IcarAPIService::create();
        $saver = new Saver;
        $logger = new FileLogger(ICAR_API_ROOT . '/logs/imports/' . date('Y-m-d H:i:s') . '.log');

        try {
            foreach ($icarApi->iterateProducts() as $product) {
                $saver->saveProduct($product);
                $logger->info("Imported \"{$product->sku()}\"");
                wp_cache_flush();
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
    }
}