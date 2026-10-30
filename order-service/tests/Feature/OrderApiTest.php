<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cart.url' => 'http://cart-service.test',
            'services.product.url' => 'http://product-service.test',
        ]);
    }

    public function test_it_reports_order_service_health(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'order-service',
            ]);
    }

    public function test_it_checks_out_a_cart_and_stores_product_snapshots(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'cart-service.test/carts/7' => Http::sequence()
                ->push([
                    'data' => [
                        'user_id' => 7,
                        'items' => [
                            ['product_id' => 1, 'quantity' => 2],
                            ['product_id' => 2, 'quantity' => 1],
                        ],
                    ],
                ])
                ->push(['data' => ['deleted_items' => 2]]),
            'product-service.test/products/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'name' => 'Keyboard Mekanikal',
                    'price' => '450000.00',
                    'stock' => 25,
                ],
            ]),
            'product-service.test/products/2' => Http::response([
                'data' => [
                    'id' => 2,
                    'name' => 'Mouse Wireless',
                    'price' => '150000.00',
                    'stock' => 10,
                ],
            ]),
        ]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertCreated()
            ->assertJsonPath('message', 'Pesanan berhasil dibuat')
            ->assertJsonPath('data.user_id', 7)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.total_amount', 1050000)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Keyboard Mekanikal')
            ->assertJsonPath('data.items.0.unit_price', 450000)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.subtotal', 900000)
            ->assertJsonMissingPath('data.items.0.order_id');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'user_id' => 7,
            'status' => 'PENDING',
            'total_amount' => '1050000.00',
        ]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'product_id' => 1,
            'product_name' => 'Keyboard Mekanikal',
            'unit_price' => '450000.00',
            'quantity' => 2,
            'subtotal' => '900000.00',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'http://cart-service.test/carts/7');
    }

    public function test_it_validates_checkout_and_status_filter_inputs(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/orders', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Data yang dikirim tidak valid',
                'errors' => ['user_id' => ['Field user_id wajib diisi']],
            ]);

        $this->getJson('/orders/user/7?status=SHIPPED')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Data yang dikirim tidak valid',
                'errors' => ['status' => ['Status harus PENDING atau PAID']],
            ]);

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_it_rejects_an_empty_cart_without_creating_an_order(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'cart-service.test/carts/7' => Http::response([
                'data' => ['user_id' => 7, 'items' => []],
            ]),
        ]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertConflict()
            ->assertJsonPath('message', 'Keranjang kosong');

        $this->assertDatabaseCount('orders', 0);
        Http::assertSentCount(1);
    }

    public function test_it_rejects_missing_products_and_insufficient_stock(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'cart-service.test/carts/7' => Http::sequence()
                ->push(['data' => ['items' => [['product_id' => 99, 'quantity' => 1]]]])
                ->push(['data' => ['items' => [['product_id' => 1, 'quantity' => 11]]]]),
            'product-service.test/products/99' => Http::response(['message' => 'Produk tidak ditemukan'], 404),
            'product-service.test/products/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'name' => 'Monitor 24 Inch',
                    'price' => '450000.00',
                    'stock' => 10,
                ],
            ]),
        ]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertConflict()
            ->assertJsonPath('message', 'Produk dengan id 99 tidak ditemukan');

        $this->postJson('/orders', ['user_id' => 7])
            ->assertConflict()
            ->assertJsonPath('message', 'Stok Monitor 24 Inch tidak mencukupi: diminta 11, tersedia 10');

        $this->assertDatabaseCount('orders', 0);
        Http::assertSentCount(4);
    }

    public function test_it_returns_service_unavailable_when_a_dependency_fails(): void
    {
        Http::preventStrayRequests();
        config(['app.debug' => false]);
        Http::fake([
            'cart-service.test/carts/7' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'Cart service tidak dapat dihubungi, coba lagi nanti',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_maps_cart_connection_failures_to_service_unavailable(): void
    {
        Http::preventStrayRequests();
        config(['app.debug' => false]);
        Http::fake([
            'cart-service.test/carts/7' => Http::failedConnection(),
        ]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'Cart service tidak dapat dihubungi, coba lagi nanti',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_keeps_a_created_order_when_clearing_the_cart_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'cart-service.test/carts/7' => Http::sequence()
                ->push(['data' => ['items' => [['product_id' => 1, 'quantity' => 1]]]])
                ->push(['message' => 'Unavailable'], 503),
            'product-service.test/products/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'name' => 'Keyboard Mekanikal',
                    'price' => '450000.00',
                    'stock' => 25,
                ],
            ]),
        ]);
        Log::spy();

        $this->postJson('/orders', ['user_id' => 7])
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 450000);

        $this->assertDatabaseCount('orders', 1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_rolls_back_the_order_when_an_item_cannot_be_saved(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'cart-service.test/carts/7' => Http::response([
                'data' => ['items' => [['product_id' => 1, 'quantity' => 1]]],
            ]),
            'product-service.test/products/1' => Http::response([
                'data' => [
                    'id' => 1,
                    'name' => 'Keyboard Mekanikal',
                    'price' => '450000.00',
                    'stock' => 25,
                ],
            ]),
        ]);
        OrderItem::creating(function (OrderItem $item): void {
            throw new RuntimeException('Simulated item persistence failure');
        });
        config(['app.debug' => false]);

        $this->postJson('/orders', ['user_id' => 7])
            ->assertInternalServerError()
            ->assertJsonMissingPath('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        Http::assertSentCount(2);
    }

    public function test_it_returns_order_details_and_lists_user_orders_by_status(): void
    {
        $olderOrder = Order::create([
            'user_id' => 7,
            'status' => 'PAID',
            'total_amount' => '300000.00',
        ]);
        $olderOrder->items()->create([
            'product_id' => 2,
            'product_name' => 'Mouse Wireless',
            'unit_price' => '150000.00',
            'quantity' => 2,
            'subtotal' => '300000.00',
        ]);
        $pendingOrder = Order::create([
            'user_id' => 7,
            'status' => 'PENDING',
            'total_amount' => '450000.00',
        ]);
        $pendingOrder->items()->create([
            'product_id' => 1,
            'product_name' => 'Keyboard Mekanikal',
            'unit_price' => '450000.00',
            'quantity' => 1,
            'subtotal' => '450000.00',
        ]);
        Order::create([
            'user_id' => 8,
            'status' => 'PENDING',
            'total_amount' => '150000.00',
        ]);

        $this->getJson("/orders/{$pendingOrder->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil mengambil pesanan')
            ->assertJsonPath('data.items.0.product_name', 'Keyboard Mekanikal')
            ->assertJsonMissingPath('data.items.0.order_id');

        $this->getJson('/orders/user/7?status=PENDING')
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil mengambil daftar pesanan')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingOrder->id)
            ->assertJsonPath('data.0.status', 'PENDING')
            ->assertJsonPath('data.0.items.0.subtotal', 450000);

        $this->getJson('/orders/user/7')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pendingOrder->id)
            ->assertJsonPath('data.1.id', $olderOrder->id);
    }

    public function test_it_returns_not_found_for_missing_or_non_numeric_order_ids(): void
    {
        $this->getJson('/orders/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pesanan dengan id 999 tidak ditemukan');

        $this->getJson('/orders/not-a-number')->assertNotFound();
        $this->patchJson('/orders/not-a-number/pay')->assertNotFound();
    }

    public function test_it_pays_pending_orders_and_rejects_duplicate_payments(): void
    {
        $order = Order::create([
            'user_id' => 7,
            'status' => 'PENDING',
            'total_amount' => '150000.00',
        ]);
        $order->items()->create([
            'product_id' => 2,
            'product_name' => 'Mouse Wireless',
            'unit_price' => '150000.00',
            'quantity' => 1,
            'subtotal' => '150000.00',
        ]);

        $this->patchJson("/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('message', 'Pesanan berhasil dibayar')
            ->assertJsonPath('data.status', 'PAID');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'PAID']);

        $this->patchJson("/orders/{$order->id}/pay")
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'Pesanan berstatus PAID, hanya pesanan PENDING yang dapat dibayar',
            );

        $this->patchJson('/orders/999/pay')->assertNotFound();
    }
}
