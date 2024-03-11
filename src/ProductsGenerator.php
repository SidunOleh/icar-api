<?php

namespace IcarAPI;

use Exception;
use Generator;
use GuzzleHttp\Client;

defined('ABSPATH') or die;

class ProductsGenerator
{
    private Client $client;

    private array $credentials;

    private int $pageSize;

    private int $page;

    private string $iteratorId;

    private bool $started;

    public function __construct(
        Client $client,
        array $credentials,
        int $pageSize = 1000
    )
    {
        $this->client = $client;
        $this->credentials = $credentials;
        $this->pageSize = $pageSize < 1 ? 1 : $pageSize;
        $this->page = 1;
        $this->iteratorId = '';
        $this->started = false;
    }

    public function start(): Generator
    {
        if ($this->started) {
            throw new Exception('Can\'t start generator twice.');
        } else {
            $this->started = true;
        }

        while (true) {
            if ($this->page == 1) {
                $result = $this->fullListInit();
            } else {
                $result = $this->fullListNextPage();
            }

            if ($result['Error']['Code']) {
                throw new Exception($result['Error']['Name']);
            }

            if (! $result['IteratorID']) {
                throw new Exception('No iterator was returned.');
            }

            $this->iteratorId = $result['IteratorID'];

            $products = [];
            if ($result['Qty'] == 0) {
                $products = [];
            } elseif ($result['Qty'] == 1) {
                $products[] = $result['Products']['ProductInfo'];
            } else {
                $products = $result['Products']['ProductInfo'];
            }

            foreach ($products as $product) {
                yield $this->product($product);
            }

            if ($result['QtyLeave'] == 0) {
                break;
            }

            $this->page++;
        }
    }

    private function fullListInit(): array
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>';
        $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $body .= '<soap:Body>';
        $body .= '<FullListInit xmlns="http://icarapi.com/">';
        $body .= "<login>{$this->credentials['login']}</login>";
        $body .= "<password>{$this->credentials['password']}</password>";
        $body .= "<productsOnPage>{$this->pageSize}</productsOnPage>";
        $body .= '</FullListInit>';
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
            ->FullListInitResponse
            ->FullListInitResult;
        $result = json_decode(json_encode((array) $result), true);

        return $result;
    }

    private function fullListNextPage(): array
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>';
        $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $body .= '<soap:Body>';
        $body .= '<FullListNextPage xmlns="http://icarapi.com/">';
        $body .= "<login>{$this->credentials['login']}</login>";
        $body .= "<password>{$this->credentials['password']}</password>";
        $body .= "<iteratorID>{$this->iteratorId}</iteratorID>";
        $body .= '</FullListNextPage>';
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
            ->FullListNextPageResponse
            ->FullListNextPageResult;
        $result = json_decode(json_encode((array) $result), true);

        return $result;
    }

    private function product(array $data): Product
    {
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
}