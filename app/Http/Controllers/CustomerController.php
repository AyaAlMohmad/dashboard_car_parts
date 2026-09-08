<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $query->select('customers.*',
            DB::raw('GREATEST(0, -customers.balance_syp) as debt_syp'),
            DB::raw('(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.customer_id = customers.id AND payments.currency = \'SYP\' AND (payments.notes IS NULL OR payments.notes NOT LIKE \'استرجاع%\')) + (SELECT COALESCE(SUM(paid),0) FROM invoices WHERE invoices.customer_id = customers.id AND invoices.currency = \'SYP\') as paid_syp'),
            DB::raw('GREATEST(0, customers.balance_syp) as over_syp'),
            DB::raw('GREATEST(0, -customers.balance_usd) as debt_usd'),
            DB::raw('(SELECT COALESCE(SUM(amount),0) FROM payments WHERE payments.customer_id = customers.id AND payments.currency = \'USD\' AND (payments.notes IS NULL OR payments.notes NOT LIKE \'استرجاع%\')) + (SELECT COALESCE(SUM(paid),0) FROM invoices WHERE invoices.customer_id = customers.id AND invoices.currency = \'USD\') as paid_usd'),
            DB::raw('GREATEST(0, customers.balance_usd) as over_usd'));

        return response()->json(
            $query->orderBy('customers.created_at', 'desc')->paginate(20)
        );
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

        $validated['status'] = ($validated['balance_syp'] ?? 0) < 0 ? 'مدين' : (($validated['balance_syp'] ?? 0) > 0 ? 'دائن' : 'متوان');

        $customer = Customer::create($validated);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load('sales', 'payments'));
    }

    public function update(Request $request, Customer $customer): JsonResponse
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
            $validated['status'] = ($validated['balance_syp'] < 0 || $customer->balance_usd < 0) ? 'مدين' : (($validated['balance_syp'] > 0 || $customer->balance_usd > 0) ? 'دائن' : 'متوان');
        }

        $customer->update($validated);

        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
