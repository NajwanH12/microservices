<?php

namespace Tests\Feature;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_and_increments_cart_items(): void
    {
        $payload = ['user_id' => 1, 'product_id' => 7, 'quantity' => 2];

        $this->postJson('/carts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.quantity', 2);

        $this->postJson('/carts', $payload)
            ->assertOk()
            ->assertJsonPath('data.quantity', 4);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_it_returns_cart_contents_and_total_quantity(): void
    {
        CartItem::create(['user_id' => 1, 'product_id' => 7, 'quantity' => 2]);
        CartItem::create(['user_id' => 1, 'product_id' => 8, 'quantity' => 3]);
        CartItem::create(['user_id' => 2, 'product_id' => 9, 'quantity' => 5]);

        $this->getJson('/carts/1')
            ->assertOk()
            ->assertJsonPath('data.user_id', 1)
            ->assertJsonPath('data.total_quantity', 5)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_it_returns_all_cart_items(): void
    {
        CartItem::create(['user_id' => 1, 'product_id' => 7, 'quantity' => 2]);
        CartItem::create(['user_id' => 2, 'product_id' => 8, 'quantity' => 3]);

        $this->getJson('/carts')
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil mengambil semua keranjang')
            ->assertJsonCount(2, 'data');
    }

    public function test_it_validates_requests_and_returns_json_404s(): void
    {
        $this->post('/carts', ['user_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->get('/carts/abc')
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->putJson('/carts/items/999', ['quantity' => 1])
            ->assertNotFound()
            ->assertJsonPath('message', 'Item keranjang dengan id 999 tidak ditemukan');
    }

    public function test_it_updates_removes_and_clears_cart_items(): void
    {
        $item = CartItem::create(['user_id' => 1, 'product_id' => 7, 'quantity' => 2]);
        CartItem::create(['user_id' => 1, 'product_id' => 8, 'quantity' => 3]);

        $this->putJson("/carts/items/{$item->id}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.quantity', 5);

        $this->deleteJson("/carts/items/{$item->id}")->assertOk();
        $this->deleteJson('/carts/1')
            ->assertOk()
            ->assertJsonPath('data.deleted_items', 1);

        $this->assertDatabaseCount('cart_items', 0);
    }
}
