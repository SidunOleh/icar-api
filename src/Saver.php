<?php

namespace IcarAPI;

use Exception;
use WC_Product;
use WP_Error;

defined('ABSPATH') or die;

class Saver
{
    public function saveProduct(Product $product): int
    {
        $productId = wp_insert_post([
            'ID' => $this->id($product->sku()),
            'post_title' => $this->title($product),
            'post_content' => $product->description(),
            'post_excerpt' => json_encode($product->prices()),
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (! $productId) {
            throw new Exception("Saving error \"{$product->sku()}\"");
        }

        $tagIds = [];
        if (
            $product->manufacturer() and 
            $tagId = $this->upsertTag($product->manufacturer())
        ) {
            $tagIds[] = $tagId;
        }   

        $catIds = $this->upsertCategories(
            $product->globalCategory(),
            $product->category(),
            $product->subcategory()
        );

        $WCProduct = new WC_Product($productId);
        $WCProduct->set_sku($product->sku());
        $WCProduct->set_category_ids($catIds);
        $WCProduct->set_tag_ids($tagIds);
        $WCProduct->update_meta_data('product_main_image', $product->image());
        $WCProduct->save();

        return $productId;
    }

    private function id(string $sku): int
    {
        return wc_get_product_id_by_sku($sku);
    }

    private function title(Product $product): string
    {
        $title[] = $product->manufacturer();
        $title[] = $product->sku();
        $title[] = $product->subcategory() ?: $product->category() ?: $product->globalCategory();
        
        return implode(' ', $title);
    }

    private function upsertTag(string $name): int|false
    {
        global $wpdb;

        $tagId = $wpdb->get_var("SELECT `terms`.`term_id`
            FROM `{$wpdb->prefix}terms` AS `terms`
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS `taxs`
            ON `terms`.`term_id` = `taxs`.`term_id`
            WHERE `terms`.`name` = '" . $wpdb->_real_escape($name) . "'
            AND `taxs`.`taxonomy` = 'product_tag'
        ");

        if ($tagId) {
            return $tagId;
        }

        $tag = wp_insert_term($name, 'product_tag');

        if ($tag instanceof WP_Error) {
            return false;
        }

        return $tag['term_id'];
    }

    private function upsertCategories(
        string $globalCategory, 
        string $category, 
        string $subcategory
    ): array
    {
        $catIds = [];

        if ($globalCategory) {
            $catIds['global_category'] = $this->upsertCategory($globalCategory);
        }

        if ($category and $catIds['global_category']) {
            $catIds['category'] = $this->upsertCategory($category, $catIds['global_category']);
        }
        
        if ($subcategory and $catIds['category']) {
            $catIds['subcategory'] = $this->upsertCategory($subcategory, $catIds['category']);
        }

        return array_filter($catIds, fn($catId) => $catId);
    }

    private function upsertCategory(string $name, int $parentId = 0): int|false
    {
        global $wpdb;

        $name = $wpdb->_real_escape($name);

        $catId = $wpdb->get_var("SELECT `terms`.`term_id`
            FROM `{$wpdb->prefix}terms` AS `terms`
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS `taxs`
            ON `terms`.`term_id` = `taxs`.`term_id`
            WHERE `terms`.`name` = '" . $wpdb->_real_escape($name) . "'
            AND `taxs`.`taxonomy` = 'product_cat'
            AND `taxs`.`parent` = {$parentId}
        ");

        if ($catId) {
            return $catId;
        }

        $cat = wp_insert_term($name, 'product_cat', [
            'parent' => $parentId,
        ]);

        if ($cat instanceof WP_Error) {
            return false;
        }

        return $cat['term_id'];
    }
}