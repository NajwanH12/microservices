<?php

namespace App\Services;

use App\Exceptions\ProductServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductService
{
    public function getProduct(int $productId): ?array
    {
        try {
            $response = Http::baseUrl(config('services.product.url'))
                ->get("/products/{$productId}");

            if ($response->successful() && isset($response->json()['data'])) {
                return $response->json()['data'];
            }

            if ($response->serverError()) {
                throw new ProductServiceUnavailableException(
                    'Product service tidak dapat dihubungi, coba lagi nanti'
                );
            }

            return null;
        } catch (ProductServiceUnavailableException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            Log::error('Product service tidak dapat dihubungi', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            throw new ProductServiceUnavailableException(
                'Product service tidak dapat dihubungi, coba lagi nanti',
                0,
                $e
            );
        } catch (Throwable $e) {
            Log::error('Gagal mengambil data product', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}