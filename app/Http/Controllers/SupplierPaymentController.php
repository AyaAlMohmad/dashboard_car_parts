<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::with('supplier');
        if ($search = $request->input('search')) {
            $query->whereHas('supplier', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'currency' => ['nullable', 'in:SYP,USD'],
            'payment_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            $currency = $validated['currency'] = $validated['currency'] ?? 'SYP';
            $payment = SupplierPayment::create($validated);

            $supplier = Supplier::find($validated['supplier_id']);
            if ($currency === 'USD') {
                $supplier->balance_usd -= $validated['amount'];
            } else {
                $supplier->balance_syp -= $validated['amount'];
            }
            $supplier->status = $supplier->balance_syp > 0 || $supplier->balance_usd > 0
                ? 'علينا'
                : ($supplier->balance_syp < 0 || $supplier->balance_usd < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            return response()->json($payment->load('supplier'), 201);
        });
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        return response()->json($supplierPayment->load('supplier'));
    }

    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        return DB::transaction(function () use ($supplierPayment) {
            $supplier = Supplier::find($supplierPayment->supplier_id);
            if ($supplierPayment->currency === 'USD') {
                $supplier->balance_usd += $supplierPayment->amount;
            } else {
                $supplier->balance_syp += $supplierPayment->amount;
            }
            $supplier->status = $supplier->balance_syp > 0 || $supplier->balance_usd > 0
                ? 'علينا'
                : ($supplier->balance_syp < 0 || $supplier->balance_usd < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            $supplierPayment->delete();
            return response()->json(['message' => 'Deleted successfully']);
        });
    }
}
