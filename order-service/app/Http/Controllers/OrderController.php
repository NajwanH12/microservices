<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderConflictException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->checkout($request->validated()['user_id']);

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => (new OrderResource($order))->resolve($request),
            ], 201);
        } catch (OrderConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ServiceUnavailableException $exception) {
            return $this->serviceUnavailableResponse($exception);
        } catch (Throwable $exception) {
            return $this->serverErrorResponse($exception, 'membuat pesanan');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);

            return response()->json([
                'message' => 'Berhasil mengambil pesanan',
                'data' => (new OrderResource($order))->resolve(request()),
            ]);
        } catch (OrderNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            return $this->serverErrorResponse($exception, 'mengambil pesanan');
        }
    }

    public function indexForUser(ListOrdersRequest $request, int $userId): JsonResponse
    {
        try {
            $orders = $this->orderService->getUserOrders(
                $userId,
                $request->validated()['status'] ?? null,
            );

            return response()->json([
                'message' => 'Berhasil mengambil daftar pesanan',
                'data' => OrderResource::collection($orders)->resolve($request),
            ]);
        } catch (Throwable $exception) {
            return $this->serverErrorResponse($exception, 'mengambil daftar pesanan');
        }
    }

    public function pay(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->payOrder($id);

            return response()->json([
                'message' => 'Pesanan berhasil dibayar',
                'data' => (new OrderResource($order))->resolve(request()),
            ]);
        } catch (OrderNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (OrderConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            return $this->serverErrorResponse($exception, 'membayar pesanan');
        }
    }

    private function serviceUnavailableResponse(ServiceUnavailableException $exception): JsonResponse
    {
        Log::error('Gagal berkomunikasi dengan service lain', [
            'service' => $exception->service,
            'error' => $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
        ]);

        $message = $exception->service === 'product-service'
            ? 'Product service tidak dapat dihubungi, coba lagi nanti'
            : 'Cart service tidak dapat dihubungi, coba lagi nanti';

        $body = ['message' => $message];

        if (config('app.debug')) {
            $body['error'] = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();
        }

        return response()->json($body, 503);
    }

    private function serverErrorResponse(Throwable $exception, string $action): JsonResponse
    {
        Log::error("Gagal {$action}", [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        $body = ['message' => "Gagal {$action}"];

        if (config('app.debug')) {
            $body['error'] = $exception->getMessage();
            $body['location'] = $exception->getFile().':'.$exception->getLine();
        }

        return response()->json($body, 500);
    }
}
