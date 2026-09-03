<?php

namespace App\Http\Controllers;

use App\Domain\Payments\CustomerPaymentProofPolicy;
use App\Domain\Payments\CustomerPaymentProofViewModelFactory;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\Payments\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerPaymentProofController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        OrderPayment $payment,
        CustomerPaymentProofPolicy $policy,
        CustomerPaymentProofViewModelFactory $viewModels,
        PaymentProofService $proofs,
    ): JsonResponse|RedirectResponse {
        $policy->authorize($request, $order, $payment);
        $data = $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $payment = $proofs->storeCheckoutUpload($payment, $data['payment_proof']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $viewModels->for($order->fresh(), $payment)]);
        }

        return back()->with('status', 'Payment proof submitted for verification.');
    }
}
