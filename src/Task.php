<?php

namespace IcarAPI;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use IcarAPI\IcarAPIService;
use IcarAPI\Saver;
use Wa72\SimpleLogger\FileLogger;

defined('ABSPATH') or die;

class Task
{
    public function __invoke()
    {
        set_time_limit(0);

        $icarApi = IcarAPIService::create();
        $saver = new Saver;
        $logger = new FileLogger(ICAR_API_ROOT . '/logs/imports/' . date('Y-m-d H:i:s') . '.log');

        try {
            foreach ($icarApi->getProducts() as $product) {
                $saver->saveProduct($product);
                $logger->info("Imported \"{$product->sku()}\"");
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
    }
}