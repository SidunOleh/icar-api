<?php

namespace IcarAPI;

use Exception;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Wa72\SimpleLogger\FileLogger;
use WC_Product;

defined('ABSPATH') or die;

class IcarAPIService
{
    private Client $client;

    private array $credentials;

    private FileLogger $logger;

    private Saver $saver;

    public function __construct(
        Client $client,
        array $credentials,
        FileLogger $logger,
        Saver $saver   
    )
    {
        $this->client = $client;
        $this->credentials = $credentials;
        $this->logger = $logger;
        $this->saver = $saver;
    }

    public function iterateProducts(int $pageSize = 1000): Generator 
    {      
        $generator = new ProductsGenerator($this->client, $this->credentials, $pageSize);

        return $generator->start();
    }

    public function searchProducts(string $s): array
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>';
        $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $body .= '<soap:Body>';
        $body .= '<getQuickSearch xmlns="http://icarapi.com/">';
        $body .= "<login>{$this->credentials['login']}</login>";
        $body .= "<password>{$this->credentials['password']}</password>";
        $body .= "<part>{$s}</part>";
        $body .= '</getQuickSearch>';
        $body .= '</soap:Body>';
        $body .= '</soap:Envelope>';

        $headers = [
            'Authorization' => "Bearer {$this->credentials['secret']}",
            'Content-Type' => 'text/xml',
        ];

        $response = $this->client->post('http://test.icarteam.com/IcarAPI/icarapi.asmx', [
            'headers' => $headers,
            'body' => $body,
        ]);

        $xmlContent = simplexml_load_string($response->getBody()->getContents());
        $result = $xmlContent->children('soap', true)
            ->Body->children('', true)
            ->getQuickSearchResponse
            ->getQuickSearchResult;
        $result = json_decode(json_encode((array) $result), true);

        $items = [];
        if ($result['Qty'] == 0) {
            $items = [];
        } elseif ($result['Qty'] == 1) {
            $items[] = $result['Items']['QuickSearchItem'];
        } else {
            $items = $result['Items']['QuickSearchItem'];
        }

        $products = [];
        foreach ($items as $item) {
            $products[] = new Product(
                $item['Product'] ?: '',
                '',
                $item['Manufacturer'] ?: '',
                '',
                $item['Category'] ?: '',
                '',
                [],
                ''
            );
        }

        return $products;
    }

    public function updateProducts(): void
    {
        $pool = new Pool($this->client, $this->getProductInfoRequests(), [
            'concurrency' => 100,
            'fulfilled' => function ($response, $productId) {
                $this->getProductInfoFulfilled($response, $productId);
            },
            'rejected' => function ($e, $productId) {
                $this->getProductInfoRejected($e, $productId);
            },
        ]); 

        ($pool->promise())->wait();
    }

    private function getProductInfoRequests(): Generator
    {
        $productIds = get_posts([
            'post_type' => 'product',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);
        $headers = [
            'Authorization' => "Bearer {$this->credentials['secret']}",
            'Content-Type' => 'text/xml',
        ];
        foreach ($productIds as $productId) {
            $sku = (new WC_Product($productId))->get_sku();

            $body = '<?xml version="1.0" encoding="utf-8"?>';
            $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
            $body .= '<soap:Body>';
            $body .= '<getProductInfo xmlns="http://icarapi.com/" >';
            $body .= "<login>{$this->credentials['login']}</login>";
            $body .= "<password>{$this->credentials['password']}</password>";
            $body .= "<product>{$sku}</product>";
            $body .= '</getProductInfo>';
            $body .= '</soap:Body>';
            $body .= '</soap:Envelope>';

            yield $productId => new Request(
                'POST', 
                'http://test.icarteam.com/IcarAPI/icarapi.asmx', 
                $headers, 
                $body
            );
        }
    }

    private function getProductInfoFulfilled(Response $response, int $productId): void
    {
        try {
            $product = $this->parseGetProductInfoResponse($response->getBody()->getContents());
            $this->saver->saveProduct($product);
            $this->logger->info("Updated {$product->sku()}");
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function getProductInfoRejected(Exception $e, int $productId): void
    {
        $this->logger->error($e->getMessage());
    }

    private function parseGetProductInfoResponse(string $xml): Product
    {
        $xml = simplexml_load_string($xml);
        $result = $xml->children('soap', true)
            ->Body->children('', true)
            ->getProductInfoResponse
            ->getProductInfoResult;
        $result = json_decode(json_encode((array) $result), true);

        if (! $result) {
            return new Exception('Can\'t parse getProductInfo response.');
        }

        if ($result['Error']['Code'] != 0) {
            return new Exception($result['Error']['Name'], $result['Error']['Code']);
        }

        $data = $result['Product'];

        $sku = $data['SKU'] ?: '';
        $description = $data['Description'] ?: '';
        $manufacturer = $data['Manufacturer']['Name'] ?: '';
        $globalCategory = $data['GlobalCategory']['Name'] ?: '';
        $category = $data['Category']['Name'] ?: '';
        $subcategory = $data['SubCategory']['Name'] ?: '';
        $prices = [];
        foreach ($data['Price'] as $name => $value) {
            $prices[$name] = $value ?: '';
        }
        $image = $data['ImageMain'] ?: '';

        return new Product(
            $sku, 
            $description, 
            $manufacturer, 
            $globalCategory, 
            $category, 
            $subcategory, 
            $prices,
            $image
        );
    }

    public static function create(string $logpath = ''): self
    {
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

        $settings = get_option('icar_api_settings');
        $credentials['login'] = $settings['login'] ?? '';
        $credentials['password'] = $settings['password'] ?? '';
        $credentials['secret'] = $settings['secret'] ?? '';

        $logger = new FileLogger($logpath ?: ICAR_API_ROOT . '/logs/icar-api.log');
        $saver = new Saver;

        return new self($client, $credentials, $logger, $saver);
    }
}