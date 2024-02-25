<?php

namespace IcarAPI;

use Exception;
use Generator;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

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
                if (! $product or $product['Error']['Code']) {
                    $this->logger->error($product['Error']['Name'] ?? '');
                } elseif(! $product['IsFound']) {
                    $this->logger->error("Not Found SKU: {$sku}");
                } else {
                    $products[] = $product;
                    $this->logger->info("Downloaded SKU: {$sku}");
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

    private function parseProductInfoResponse(string $xml): array
    {
        $xml = simplexml_load_string($xml);

        $productInfo = $xml->children('soap', true)
            ->Body
            ->children('', true)
            ->getProductInfoResponse
            ->getProductInfoResult;

        return json_decode(json_encode((array) $productInfo), true);
    }
}