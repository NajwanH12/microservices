<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CartServiceClient
{
    /**
     * @return array{user_id: int, items: array}
     */
    public function getCart(int $userId): array
    {
        try {
            $response = Http::baseUrl(config('services.cart.url'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get("/carts/{$userId}");
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableException(
                'cart-service',
                'Cart service tidak dapat dihubungi',
                $exception,
            );
        }

        if (! $response->successful()) {
            throw new ServiceUnavailableException(
                'cart-service',
                "Cart service merespons dengan HTTP {$response->status()}",
            );
        }

        $cart = $response->json('data');

        if (! is_array($cart) || ! isset($cart['items']) || ! is_array($cart['items'])) {
            throw new ServiceUnavailableException('cart-service', 'Respons cart service tidak valid');
        }

        return $cart;
    }

    public function clearCart(int $userId): void
    {
        try {
            $response = Http::baseUrl(config('services.cart.url'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->delete("/carts/{$userId}");
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableException(
                'cart-service',
                'Cart service tidak dapat dihubungi',
                $exception,
            );
        }

        if (! $response->successful()) {
            throw new ServiceUnavailableException(
                'cart-service',
                "Cart service merespons dengan HTTP {$response->status()}",
            );
        }

        if (! is_array($response->json('data')) || ! is_numeric($response->json('data.deleted_items'))) {
            throw new ServiceUnavailableException('cart-service', 'Respons cart service tidak valid');
        }
    }
}
