<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditProduct;
use App\Models\CreditTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CreditAdminController extends Controller
{
    public function products(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'code' => ['required', 'string', 'max:60', 'unique:credit_products,code'],
                'name' => ['required', 'string', 'max:160'],
                'credits' => ['required', 'integer', 'min:1'],
                'price' => ['required', 'numeric', 'min:0'],
            ]);
            $p = CreditProduct::create($request->all());

            return response()->json($p, 201);
        }

        return response()->json(CreditProduct::orderBy('sort_order')->get());
    }

    public function updateProduct(Request $request, CreditProduct $product, AuditService $audit)
    {
        $product->update($request->all());
        $audit->log('admin.credit_product.updated', $request->user(), $product);

        return response()->json($product->fresh());
    }

    public function transactions(Request $request)
    {
        return response()->json(CreditTransaction::with('user')->latest('id')->paginate(25));
    }
}
