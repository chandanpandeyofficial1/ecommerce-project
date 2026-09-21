<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;

class CatalogTest extends ApiTestCase
{
    public function test_search_and_category_filter(): void
    {
        $this->customer();
        $rice = Category::factory()->create(['name' => 'Rice']);
        $dal = Category::factory()->create(['name' => 'Dal']);
        Product::factory()->create(['category_id' => $rice->id, 'name' => 'Basmati Rice']);
        Product::factory()->create(['category_id' => $dal->id, 'name' => 'Toor Dal']);

        $this->getJson('/api/products?search=basmati')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'Rice');

        $this->getJson('/api/products?category_id='.$dal->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Toor Dal');

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_bad_query_params_give_422(): void
    {
        $this->customer();
        $this->getJson('/api/products?search[]=a')->assertStatus(422);
        $this->getJson('/api/products?category_id[]=1')->assertStatus(422);
    }

    public function test_percent_in_search_is_literal(): void
    {
        $this->customer();
        Product::factory()->create(['name' => 'Plain Rice']);
        Product::factory()->create(['name' => '50% Off Pack']);

        $this->getJson('/api/products?search=%25')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products?search=0')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_hidden_product_is_excluded_from_list_and_404_on_show(): void
    {
        $this->customer();
        $visible = Product::factory()->create(['name' => 'Visible Rice']);
        $hidden = Product::factory()->create(['name' => 'Hidden Rice', 'is_active' => false]);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible Rice');

        $this->getJson('/api/products/'.$visible->id)->assertOk();
        $this->getJson('/api/products/'.$hidden->id)->assertStatus(404);
    }

    public function test_products_are_paginated_and_show_returns_image_url(): void
    {
        $this->customer();
        Product::factory()->count(12)->create();
        $p = Product::factory()->create(['image' => 'products/a.jpg']);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(10, 'data');
        $this->getJson('/api/products/'.$p->id)
            ->assertOk()->assertJsonPath('data.image_url', asset('storage/products/a.jpg'));
        $this->getJson('/api/products/9999')->assertStatus(404)->assertJson(['message' => 'Not found.']);
    }
}
