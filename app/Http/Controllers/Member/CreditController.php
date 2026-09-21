<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CreditSpendRequest;
use App\Http\Resources\CreditWalletResource;
use App\Models\CreditProduct;
use App\Services\CreditService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function wallet(Request $request, CreditService $credits)
    {
        $wallet = $credits->wallet($request->user())->load('transactions');

        return response()->json(CreditWalletResource::make($wallet));
    }

    public function products(Request $request)
    {
        return response()->json(CreditProduct::active()->get());
    }

    public function buy(CheckoutRequest $request, PaymentService $payments)
    {
        $request->validate(['credit_product' => ['required', 'string', 'exists:credit_products,code']]);
        try {
            $result = $payments->checkout($request->user(), [
                'gateway' => $request->string('gateway'),
                'credit_product' => $request->string('credit_product'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['payment_id' => $result['payment']->id, 'gateway' => $result['gateway']], 201);
    }

    public function spend(CreditSpendRequest $request, CreditService $credits)
    {
        try {
            $txn = $credits->spend($request->user(), (int) $request->input('amount'), (string) $request->input('description', 'Spend'), [
                'feature' => $request->input('feature'),
                ...(array) $request->input('meta', []),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($txn, 201);
    }
}
