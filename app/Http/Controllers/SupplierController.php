<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $purchaseSums = DB::table('purchases')
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN remaining ELSE 0 END) as debt_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN remaining ELSE 0 END) as debt_usd'))
            ->groupBy('supplier_id');

        $paymentSums = DB::table('supplier_payments')
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as paid_usd'))
            ->groupBy('supplier_id');

        $query->leftJoinSub($purchaseSums, 'pur_sums', 'suppliers.id', '=', 'pur_sums.supplier_id')
            ->leftJoinSub($paymentSums, 'spay_sums', 'suppliers.id', '=', 'spay_sums.supplier_id')
            ->select('suppliers.*',
                DB::raw('COALESCE(pur_sums.debt_syp, 0) as debt_syp'),
                DB::raw('COALESCE(pur_sums.debt_usd, 0) as debt_usd'),
                DB::raw('COALESCE(spay_sums.paid_syp, 0) as paid_syp'),
                DB::raw('COALESCE(spay_sums.paid_usd, 0) as paid_usd'),
                DB::raw('COALESCE(pur_sums.debt_syp, 0) - COALESCE(spay_sums.paid_syp, 0) as balance_syp'),
                DB::raw('COALESCE(pur_sums.debt_usd, 0) - COALESCE(spay_sums.paid_usd, 0) as balance_usd'));

        return response()->json($query->orderBy('suppliers.created_at', 'desc')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'balance' => ['nullable', 'numeric'],
        ]);

        $validated['status'] = ($validated['balance'] ?? 0) > 0 ? 'علينا' : (($validated['balance'] ?? 0) < 0 ? 'لنا' : 'متوازن');

        $supplier = Supplier::create($validated);
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier->load('purchases', 'supplierPayments'));
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'balance' => ['nullable', 'numeric'],
        ]);

        if (isset($validated['balance'])) {
            $validated['status'] = $validated['balance'] > 0 ? 'علينا' : ($validated['balance'] < 0 ? 'لنا' : 'متوازن');
        }

        $supplier->update($validated);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
