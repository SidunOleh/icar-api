<?php

namespace IcarAPI;

use Exception;
use WC_Product;
use Psr\Log\LoggerInterface;

defined('ABSPATH') or die;

class Saver
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function saveProducts(array $products): array
    {
        $productIds = [];
        foreach ($products as $product) {
            try {
                $productIds[] = $this->saveProduct($product);
                $this->logger->info("Saved SKU: {$product['Product']}");
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return $productIds;
    }

    public function saveProduct(array $product): int
    {
        $productId = wp_insert_post([
            'ID' => wc_get_product_id_by_sku($product['Product']),
            'post_title' => $this->title($product),
            'post_content' => (string) $product['Description'],
            'post_excerpt' => json_encode($product['Price']),
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (! $productId) {
            throw new Exception("Saving error SKU: {$product['Product']}");
        }

        $catIds = $this->insertCategories([
            'GlobalCategory' => $product['GlobalCategory'], 
            'Category' => $product['Category'], 
            'SubCategory' => $product['SubCategory'],
        ]);

        $wcProduct = new WC_Product($productId);
        $wcProduct->set_sku($product['Product']);
        $wcProduct->set_category_ids($catIds);
        $wcProduct->save();

        return $productId;
    }

    private function title(array $product): string
    {
        $title[] = $product['Manufacturer']['Name'];
        $title[] = $product['Product'];
        $title[] = $product['SubCategory']['Name'] ?: $product['Category']['Name'] ?: $product['GlobalCategory']['Name'];
        
        return implode(' ', $title);
    }

    private function insertCategories(array $cats): array
    {
        $catIds = [];

        if ($cats['GlobalCategory']['Name']) {
            $catIds['global'] = $this->insertCategory($cats['GlobalCategory']['Name']);
        }

        if ($cats['Category']['Name']) {
            $catIds['cat'] = $this->insertCategory($cats['Category']['Name'], $catIds['global'] ?? 0);
        }
        
        if ($cats['SubCategory']['Name']) {
            $catIds['sub'] = $this->insertCategory($cats['SubCategory']['Name'], $catIds['cat'] ?? 0);
        }

        return $catIds;
    }

    private function insertCategory(string $catName, int $parentId = 0): int
    {
        global $wpdb;

        $catId = $wpdb->get_var("SELECT `terms`.`term_id`
            FROM `{$wpdb->prefix}terms` AS `terms`
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS `taxs`
            ON `terms`.`term_id` = `taxs`.`term_id`
            WHERE `terms`.`name` = '{$catName}'
            AND `taxs`.`parent` = {$parentId}
        ");

        if ($catId) {
            return $catId;
        }

        $cat = wp_insert_term($catName, 'product_cat', [
            'parent' => $parentId,
        ]);

        return $cat['term_id'];
    }
}