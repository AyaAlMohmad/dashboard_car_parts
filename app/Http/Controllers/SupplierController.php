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

        $query->select('suppliers.*',
            DB::raw('suppliers.balance_syp as balance_syp'),
            DB::raw('suppliers.balance_usd as balance_usd'),
            DB::raw('GREATEST(0, suppliers.balance_syp) as debt_syp'),
            DB::raw('(SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_payments.supplier_id = suppliers.id AND supplier_payments.currency = \'SYP\') + (SELECT COALESCE(SUM(paid),0) FROM purchases WHERE purchases.supplier_id = suppliers.id AND purchases.currency = \'SYP\') as paid_syp'),
            DB::raw('GREATEST(0, -suppliers.balance_syp) as over_syp'),
            DB::raw('GREATEST(0, suppliers.balance_usd) as debt_usd'),
            DB::raw('(SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_payments.supplier_id = suppliers.id AND supplier_payments.currency = \'USD\') + (SELECT COALESCE(SUM(paid),0) FROM purchases WHERE purchases.supplier_id = suppliers.id AND purchases.currency = \'USD\') as paid_usd'),
            DB::raw('GREATEST(0, -suppliers.balance_usd) as over_usd'));

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

        $validated['balance_syp'] = $validated['balance'] ?? 0;
        $validated['balance'] = 0;

        $validated['status'] = ($validated['balance_syp'] ?? 0) > 0 ? 'علينا' : (($validated['balance_syp'] ?? 0) < 0 ? 'لنا' : 'متوازن');

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
            $validated['balance_syp'] = $validated['balance'];
            $validated['balance'] = 0;
            $validated['status'] = ($validated['balance_syp'] > 0 || $supplier->balance_usd > 0) ? 'علينا' : (($validated['balance_syp'] < 0 || $supplier->balance_usd < 0) ? 'لنا' : 'متوازن');
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
