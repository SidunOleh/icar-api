<?php

namespace IcarAPI;

defined('ABSPATH') or die;

class Product
{
    public function __construct(
        private string $sku,
        private string $description,
        private string $manufacturer,
        private string $globalCategory,
        private string $category,
        private string $subcategory,
        private array $prices,
        private string $image
    )
    {
        
    }

    public function sku(): string 
    {
        return $this->sku;
    }

    public function description(): string 
    {
        return $this->description;
    }

    public function manufacturer(): string 
    {
        return $this->manufacturer;
    }

    public function globalCategory(): string 
    {
        return $this->globalCategory;
    }

    public function category(): string 
    {
        return $this->category;
    }

    public function subcategory(): string 
    {
        return $this->subcategory;
    }

    public function prices(): array 
    {
        return $this->prices;
    }

    public function image(): string 
    {
        return $this->image;
    }
}