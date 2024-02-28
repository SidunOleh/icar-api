<?php

namespace IcarAPI;

use Exception;
use WC_Product;
use WP_Error;

defined('ABSPATH') or die;

class Saver
{
    public function saveProduct(ProductDTO $dto): int
    {
        $productId = wp_insert_post([
            'ID' => $this->id($dto->sku()),
            'post_title' => $this->title($dto),
            'post_content' => $dto->description(),
            'post_excerpt' => json_encode($dto->prices()),
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (! $productId) {
            throw new Exception("Saving error \"{$dto->sku()}\"");
        }

        $tagIds = [];
        if (
            $dto->manufacturer() and 
            $tagId = $this->insertTag($dto->manufacturer())
        ) {
            $tagIds[] = $tagId;
        }   

        $catIds = $this->insertCategories(
            $dto->globalCategory(),
            $dto->category(),
            $dto->subcategory()
        );

        $product = new WC_Product($productId);
        $product->set_sku($dto->sku());
        $product->set_category_ids($catIds);
        $product->set_tag_ids($tagIds);
        $product->update_meta_data('product_main_image', $dto->image());
        $product->save();

        return $productId;
    }

    private function id(string $sku): int
    {
        return wc_get_product_id_by_sku($sku);
    }

    private function title(ProductDTO $dto): string
    {
        $title[] = $dto->manufacturer();
        $title[] = $dto->sku();
        $title[] = $dto->subcategory() ?: $dto->category() ?: $dto->globalCategory();
        
        return implode(' ', $title);
    }

    private function insertTag(string $name): int|false
    {
        global $wpdb;

        $tagId = $wpdb->get_var("SELECT `terms`.`term_id`
            FROM `{$wpdb->prefix}terms` AS `terms`
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS `taxs`
            ON `terms`.`term_id` = `taxs`.`term_id`
            WHERE `terms`.`name` = '{$name}'
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

    private function insertCategories(
        string $globalCategory, 
        string $category, 
        string $subcategory
    ): array
    {
        $catIds = [];

        if ($globalCategory) {
            $catIds['global_category'] = $this->insertCategory($globalCategory);
        }

        if ($category and $catIds['global_category']) {
            $catIds['category'] = $this->insertCategory($category, $catIds['global_category']);
        }
        
        if ($subcategory and $catIds['category']) {
            $catIds['subcategory'] = $this->insertCategory($subcategory, $catIds['category']);
        }

        return array_filter($catIds, fn($catId) => $catId);
    }

    private function insertCategory(string $name, int $parentId = 0): int|false
    {
        global $wpdb;

        $catId = $wpdb->get_var("SELECT `terms`.`term_id`
            FROM `{$wpdb->prefix}terms` AS `terms`
            INNER JOIN `{$wpdb->prefix}term_taxonomy` AS `taxs`
            ON `terms`.`term_id` = `taxs`.`term_id`
            WHERE `terms`.`name` = '{$name}'
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