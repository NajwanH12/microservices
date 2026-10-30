<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ProductServiceClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function getProduct(int $productId): ?array
    {
        try {
            $response = Http::baseUrl(config('services.product.url'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get("/products/{$productId}");
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableException(
                'product-service',
                'Product service tidak dapat dihubungi',
                $exception,
            );
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            throw new ServiceUnavailableException(
                'product-service',
                "Product service merespons dengan HTTP {$response->status()}",
            );
        }

        $product = $response->json('data');

        if (! is_array($product)) {
            throw new ServiceUnavailableException('product-service', 'Respons product service tidak valid');
        }

        return $product;
    }
}
