<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Resources\Travel\PaymentResource;
use App\Models\Payment;
use App\Services\Api\Travel\PaymentService;
use App\Services\Payments\PaymentGatewayManager;
use App\Traits\HttpResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    use HttpResponse;

    public function __construct(public PaymentService $paymentService, public PaymentGatewayManager $gateways) {}

    public function gateways(Request $request)
    {
        return $this->sendResponse(200, null, $this->gateways->available($request->query('country')));
    }

    /** The app polls this after the checkout page closes. */
    public function show(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        return $this->sendResponse(200, null, PaymentResource::make($this->paymentService->confirm($payment)->load('order')));
    }

    /**
     * Where the gateway sends the user back. Confirms with the gateway, then
     * hands over to the app's deep link (or JSON for API clients).
     */
    public function return(Request $request, Payment $payment)
    {
        try {
            $payment = $this->paymentService->confirm($payment);
        } catch (Throwable $e) {
            report($e);
        }

        if ($request->expectsJson()) {
            return $this->sendResponse(200, null, PaymentResource::make($payment->load('order')));
        }

        $url = config('payments.app_return_url');

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'payment' => $payment->id,
            'order' => $payment->order->reference,
            'status' => $payment->status->value,
        ]));
    }

    public function webhook(Request $request, string $gateway)
    {
        $payment = $this->paymentService->webhook($gateway, $request);

        return response()->json(['received' => (bool) $payment], $payment ? 200 : 202);
    }
}
