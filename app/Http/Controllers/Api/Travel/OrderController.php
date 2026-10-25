<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Travel\OrderRequest;
use App\Http\Requests\Api\Travel\PayRequest;
use App\Http\Resources\Travel\OrderResource;
use App\Http\Resources\Travel\PaymentResource;
use App\Models\Order;
use App\Services\Api\Travel\OrderService;
use App\Services\Api\Travel\PaymentService;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use HttpResponse;

    public function __construct(public OrderService $orderService, public PaymentService $paymentService) {}

    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with('items', 'payments')
            ->when($request->query('status'), fn ($q, $status) => $q->whereIn('status', explode(',', $status)))
            ->latest()
            ->paginate(20);

        return $this->sendResponse(200, null, OrderResource::collection($orders->items()), [
            'page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
        ]);
    }

    /** Re-prices every item with its provider, holds what it can, and waits for payment. */
    public function store(OrderRequest $request)
    {
        $order = $this->orderService->create($request->user(), $request->validated());

        return $this->sendResponse(201, __('Order created, waiting for payment.'), OrderResource::make($order->load('items', 'payments')));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return $this->sendResponse(200, null, OrderResource::make($order->load('items', 'payments')));
    }

    /** Starts a payment with the chosen gateway; the app opens `redirect_url`. */
    public function pay(PayRequest $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $payment = $this->paymentService->start($order, $request->validated('gateway'));

        return $this->sendResponse(201, null, PaymentResource::make($payment->load('order')));
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return $this->sendResponse(200, __('Order cancelled.'), OrderResource::make($this->orderService->cancel($order)));
    }
}
