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

    private string $url;

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
        $this->url = 'http://test.icarteam.com/IcarAPI/icarapi.asmx';
    }

    public function iterateProducts(int $pageSize = 1000): Generator 
    {      
        $generator = new ProductsGenerator($this->client, $this->credentials, $pageSize);

        return $generator->start();
    }

    public function searchProducts(string $s): array
    {
        $headers = $this->headers();
        $body = $this->searchProductsBody($s);

        $response = $this->client->post($this->url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        $products = $this->parseSearchProductsResponse(
            $response->getBody()->getContents()
        );

        return $products;
    }

    private function searchProductsBody(string $s): string
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

        return $body;
    }

    private function parseSearchProductsResponse(string $xml): array
    {
        $xml = simplexml_load_string($xml);
        $result = $xml->children('soap', true)
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
            'fulfilled' => function ($response, $sku) {
                $this->getProductInfoFulfilled($response, $sku);
            },
            'rejected' => function ($e, $sku) {
                $this->getProductInfoRejected($e, $sku);
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
        foreach ($productIds as $productId) {
            $sku = (new WC_Product($productId))->get_sku();

            $body = $this->getProductInfoBody($sku);

            yield $sku => new Request(
                'POST', 
                $this->url, 
                $this->headers(), 
                $body
            );
        }
    }

    private function getProductInfoBody(string $sku): string
    {
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

        return $body;
    }

    private function getProductInfoFulfilled(Response $response, string $sku): void
    {
        try {
            $product = $this->parseGetProductInfoResponse(
                $response->getBody()->getContents()
            );
            $this->saver->saveProduct($product);
            $this->logger->info("Updated {$product->sku()}");
        } catch (Exception $e) {
            $this->logger->error($e->getMessage() . " {$sku}");
        }
    }

    private function getProductInfoRejected(Exception $e, string $sku): void
    {
        $this->logger->error($e->getMessage() . " {$sku}");
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
            throw new Exception('Can\'t parse getProductInfo response');
        }

        if ($result['Error']['Code'] != 0) {
            throw new Exception($result['Error']['Name'], $result['Error']['Code']);
        }

        if (! $result['Product']['IsFound']) {
            throw new Exception('Product not found');
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

    public function createInquery(array $data): bool
    {
        $headers = $this->headers();
        $body = $this->createInqueryBody($data);

        try {
            $this->client->post($this->url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            
            return false;
        }
    }

    private function createInqueryBody(array $data): string
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>';
        $body .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $body .= '<soap:Body>';
        $body .= '<CreateInquery xmlns="http://icarapi.com/" >';
        $body .= "<login>{$this->credentials['login']}</login>";
        $body .= "<password>{$this->credentials['password']}</password>";
        $body .= '<inq>';
        $body .= '<FromWebsite>' . get_site_url() . '</FromWebsite>';
        $body .= '<Company>' . ($data['company'] ?? '') . '</Company>';
        $body .= '<Country>' . ($data['country'] ?? '') . '</Country>';
        $body .= '<State>' . ($data['state'] ?? '') . '</State>';
        $body .= '<City>' . ($data['city'] ?? '') . '</City>';
        $body .= '<Code>' . ($data['zip'] ?? '') . '</Code>';
        $body .= '<Contact>' . ($data['name'] ?? '') . '</Contact>';
        $body .= '<Email>' . ($data['name'] ?? '') . '</Email>';
        $body .= '<Phone>' . ($data['phone'] ?? '') . '</Phone>';
        $body .= '<AboutUs>0</AboutUs>';
        $body .= '<SKU>' . ($data['product'] ?? '') . '</SKU>';
        $body .= '<Message>' . ($data['message'] ?? ''). '</Message>';
        $body .= '<Service>';
        $body .= '<New>' . (int) in_array('New', $data['service'] ?? []) . '</New>';
        $body .= '<Refurbished>' . (int) in_array('Refurbished', $data['service'] ?? []) . '</Refurbished>';
        $body .= '<Repair>' . (int) in_array('Repair', $data['service'] ?? []) . '</Repair>';
        $body .= '<Exchange>' . (int) in_array('Exchange', $data['service'] ?? []) . '</Exchange>';
        $body .= '<RFE>' . (int) in_array('RFE', $data['service'] ?? []) . '</RFE>';
        $body .= '</Service>';
        $body .= '</inq>';
        $body .= '</CreateInquery>';
        $body .= '</soap:Body>';
        $body .= '</soap:Envelope>';

        return $body;
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->credentials['secret']}",
            'Content-Type' => 'text/xml',
        ];
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