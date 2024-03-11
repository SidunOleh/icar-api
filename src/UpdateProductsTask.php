<?php

namespace IcarAPI;

use IcarAPI\IcarAPIService;

defined('ABSPATH') or die;

class UpdateProductsTask
{       
    public function __invoke(): void
    {
        set_time_limit(DAY_IN_SECONDS);
        wp_suspend_cache_addition(true);

        $logpath = ICAR_API_ROOT . '/logs/updates/' . date('Y-m-d H:i:s') . '.log';
        
        $icarApi = IcarAPIService::create($logpath);
        $icarApi->updateProducts();
    }
}