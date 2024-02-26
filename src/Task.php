<?php

namespace IcarAPI;

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

        $handlers = HandlerStack::create();
        $handlers->push(Middleware::retry(function($retries, $request, $response = null) {
            if ($response and $response->getStatusCode() == 200) {
                return false;
            } else {
                return $retries < 3;
            }
        }, function($retries) {
            return $retries * 1000;
        }));
        $client = new Client([
            'handler' => $handlers,
        ]);

        $logger = new FileLogger(ICAR_API_ROOT . '/logs/imports/' . date('Y-m-d H:i:s') . '.log');

        $settings = get_option('icar_api_settings');
        $credentials['login'] = $settings['login'] ?? '';
        $credentials['password'] = $settings['password'] ?? '';
        $skus = [
            'USAMED-20BA2T', 
            'D2L055FT51A0S', 
            'M523CXR', 
            'MPF1136C', 
            'DR2-03BCP', 
            'A860-0326-T102', 
            'MSM4A23R',
            'CPCR-MR082GC', 
            'USAGED-44V22K', 
            'MDS-B-SPA-110',
            'MDS-B-SPA-110ss',
        ];
        
        $products = (new IcarAPIService(
            $client, 
            $credentials, 
            $logger
        ))->getProducts($skus);
        (new Saver($logger))->saveProducts($products);
    }
}