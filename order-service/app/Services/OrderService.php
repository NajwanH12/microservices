<?php

namespace App\Services;

use App\Clients\CartServiceClient;
use App\Clients\ProductServiceClient;
use App\Exceptions\OrderConflictException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartServiceClient $cartServiceClient,
        private readonly ProductServiceClient $productServiceClient,
    ) {}

    public function checkout(int $userId): Order
    {
        $cart = $this->cartServiceClient->getCart($userId);
        $cartItems = $cart['items'];

        if ($cartItems === []) {
            throw new OrderConflictException('Keranjang kosong');
        }

        $items = [];
        $totalCents = 0;

        foreach ($cartItems as $cartItem) {
            if (! is_array($cartItem)) {
                throw new ServiceUnavailableException('cart-service', 'Respons cart service tidak valid');
            }

            $productId = filter_var($cartItem['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($cartItem['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1) {
                throw new ServiceUnavailableException('cart-service', 'Respons cart service tidak valid');
            }

            $product = $this->productServiceClient->getProduct($productId);

            if ($product === null) {
                throw new OrderConflictException("Produk dengan id {$productId} tidak ditemukan");
            }

            if (! isset($product['name'], $product['price'], $product['stock'])
                || ! is_string($product['name'])
                || trim($product['name']) === ''
                || ! is_numeric($product['stock'])) {
                throw new ServiceUnavailableException('product-service', 'Respons product service tidak valid');
            }

            $stock = filter_var($product['stock'], FILTER_VALIDATE_INT);
            if ($stock === false || $stock < 0) {
                throw new ServiceUnavailableException('product-service', 'Respons product service tidak valid');
            }

            $unitPriceCents = $this->priceToCents($product['price']);

            if ($stock < $quantity) {
                throw new OrderConflictException(
                    "Stok {$product['name']} tidak mencukupi: diminta {$quantity}, tersedia {$stock}",
                );
            }

            $maximumOrderCents = 999_999_999_999;

            if ($unitPriceCents > intdiv($maximumOrderCents, $quantity)) {
                throw new OrderConflictException('Total pesanan terlalu besar');
            }

            $subtotalCents = $unitPriceCents * $quantity;

            if ($totalCents > $maximumOrderCents - $subtotalCents) {
                throw new OrderConflictException('Total pesanan melebihi batas yang didukung');
            }

            $totalCents += $subtotalCents;
            $items[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'unit_price' => $this->centsToDecimal($unitPriceCents),
                'quantity' => $quantity,
                'subtotal' => $this->centsToDecimal($subtotalCents),
            ];
        }

        $order = DB::transaction(function () use ($userId, $items, $totalCents): Order {
            $order = Order::create([
                'user_id' => $userId,
                'status' => 'PENDING',
                'total_amount' => $this->centsToDecimal($totalCents),
            ]);

            $order->items()->createMany($items);

            return $order->load('items');
        });

        try {
            $this->cartServiceClient->clearCart($userId);
        } catch (Throwable $exception) {
            Log::warning('Pesanan berhasil dibuat tetapi keranjang gagal dikosongkan', [
                'order_id' => $order->id,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }

        return $order;
    }

    public function getOrder(int $orderId): Order
    {
        $order = Order::with('items')->find($orderId);

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getUserOrders(int $userId, ?string $status = null): Collection
    {
        return Order::with('items')
            ->where('user_id', $userId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function payOrder(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId): Order {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order === null) {
                throw new OrderNotFoundException($orderId);
            }

            if ($order->status !== 'PENDING') {
                throw new OrderConflictException(
                    "Pesanan berstatus {$order->status}, hanya pesanan PENDING yang dapat dibayar",
                );
            }

            $order->update(['status' => 'PAID']);

            return $order->load('items');
        });
    }

    private function priceToCents(mixed $price): int
    {
        if (! is_string($price) && ! is_int($price) && ! is_float($price)) {
            throw new ServiceUnavailableException('product-service', 'Respons product service tidak valid');
        }

        $price = (string) $price;

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $price)) {
            throw new ServiceUnavailableException('product-service', 'Respons product service tidak valid');
        }

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '0');

        if (strlen($whole) > 10) {
            throw new ServiceUnavailableException('product-service', 'Harga produk melebihi batas yang didukung');
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function centsToDecimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
