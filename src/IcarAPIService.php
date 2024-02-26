<?php

namespace IcarAPI;

use Exception;
use Generator;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use WP_Error;

defined('ABSPATH') or die;

class IcarAPIService
{
    private Client $client;

    private array $credentials;

    private LoggerInterface $logger;

    public function __construct(
        Client $client,
        array $credentials,
        LoggerInterface $logger,
    )
    {
        $this->client = $client;
        $this->credentials = $credentials;
        $this->logger = $logger;
    }

    public function getProducts(array $skus): array
    {        
        $products = [];

        $pool = new Pool($this->client, $this->productInfoRequests($skus), [
            'concurrency' => 10,
            'fulfilled' => function(Response $response, string $sku) use(&$products) {
                $product = $this->parseProductInfoResponse(
                    $response->getBody()->getContents()
                );
                if ($product instanceof WP_Error) {
                    $this->logger->error($product->get_error_message());
                } else {
                    $products[] = $product;
                    $this->logger->info("Downloaded {$sku}");
                }
            },
            'rejected' => function(Exception $e, string $sku) {
                $this->logger->error($e->getMessage());
            },
        ]);

        ($pool->promise())->wait();

        return $products;
    }

    private function productInfoRequests(array $skus): Generator
    {
        foreach ($skus as $sku) {
            $uri = 'http://test.icarteam.com/IcarAPI/icarapi.asmx';
            $headers = [
                'Content-Type' => 'text/xml',
            ];
            $body = $this->productInfoRequestBody($sku);

            yield $sku => new Request('POST', $uri, $headers, $body);
        }
    }

    private function productInfoRequestBody(string $sku): string
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>';
        $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $body .= '<soap:Body>';
        $body .= '<getProductInfo xmlns="http://icarapi.com/">';
        $body .= "<login>{$this->credentials['login']}</login>";
        $body .= "<password>{$this->credentials['password']}</password>";
        $body .= "<product>{$sku}</product>";
        $body .= '</getProductInfo>';
        $body .= '</soap:Body>';
        $body .= '</soap:Envelope>';

        return $body;
    }

    private function parseProductInfoResponse(string $xml): ProductDTO|WP_Error
    {
        $xml = simplexml_load_string($xml);

        $info = $xml->children('soap', true)
            ->Body->children('', true)
            ->getProductInfoResponse
            ->getProductInfoResult;
        $info = json_decode(json_encode((array) $info), true);

        if ($info['Error']['Code']) {
            return new WP_Error($info['Error']['Code'], $info['Error']['Name']);
        }

        if (! $info['IsFound']) {
            return new WP_Error(404, 'Not Found');
        }

        $sku = $info['Product'] ?: '';
        $description = $info['Description'] ?: '';
        $manufacturer = $info['Manufacturer']['Name'] ?: '';
        $globalCategory = $info['GlobalCategory']['Name'] ?: '';
        $category = $info['Category']['Name'] ?: '';
        $subcategory = $info['SubCategory']['Name'] ?: '';
        $prices = [];
        foreach ($info['Price'] as $name => $value) {
            $prices[$name] = $value ?: '';
        }

        return new ProductDTO(
            $sku, 
            $description, 
            $manufacturer, 
            $globalCategory, 
            $category, 
            $subcategory, 
            $prices
        );
    }
}