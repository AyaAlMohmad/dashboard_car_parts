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

        $invoiceSums = DB::table('invoices')
            ->select('customer_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN total ELSE 0 END) as total_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN total ELSE 0 END) as total_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as paid_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN remaining ELSE 0 END) as remaining_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN remaining ELSE 0 END) as remaining_usd'))
            ->groupBy('customer_id');

        $salesSums = DB::table('sales')
            ->select('customer_id',
                DB::raw('SUM(total) as total_syp_sales'),
                DB::raw('SUM(remaining) as remaining_syp_sales'),
                DB::raw('SUM(paid) as paid_syp_sales'))
            ->groupBy('customer_id');

        $paymentSums = DB::table('payments')
            ->select('customer_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as payments_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as payments_usd'))
            ->groupBy('customer_id');

        $query->leftJoinSub($invoiceSums, 'inv_sums', 'customers.id', '=', 'inv_sums.customer_id')
            ->leftJoinSub($salesSums, 'sale_sums', 'customers.id', '=', 'sale_sums.customer_id')
            ->leftJoinSub($paymentSums, 'pay_sums', 'customers.id', '=', 'pay_sums.customer_id')
            ->select('customers.*',
                DB::raw('COALESCE(inv_sums.paid_syp, 0) + COALESCE(sale_sums.paid_syp_sales, 0) + COALESCE(pay_sums.payments_syp, 0) as paid_syp'),
                DB::raw('COALESCE(inv_sums.paid_usd, 0) + COALESCE(pay_sums.payments_usd, 0) as paid_usd'),
                DB::raw('COALESCE(inv_sums.total_syp, 0) + COALESCE(sale_sums.total_syp_sales, 0) as owed_syp'),
                DB::raw('COALESCE(inv_sums.total_usd, 0) as owed_usd'),
                DB::raw('GREATEST(0, COALESCE(inv_sums.total_syp, 0) + COALESCE(sale_sums.total_syp_sales, 0) - COALESCE(inv_sums.paid_syp, 0) - COALESCE(sale_sums.paid_syp_sales, 0) - COALESCE(pay_sums.payments_syp, 0)) as debt_syp'),
                DB::raw('GREATEST(0, COALESCE(inv_sums.total_usd, 0) - COALESCE(inv_sums.paid_usd, 0) - COALESCE(pay_sums.payments_usd, 0)) as debt_usd'),
                DB::raw('GREATEST(0, COALESCE(inv_sums.paid_syp, 0) + COALESCE(sale_sums.paid_syp_sales, 0) + COALESCE(pay_sums.payments_syp, 0) - COALESCE(inv_sums.total_syp, 0) - COALESCE(sale_sums.total_syp_sales, 0)) as over_syp'),
                DB::raw('GREATEST(0, COALESCE(inv_sums.paid_usd, 0) + COALESCE(pay_sums.payments_usd, 0) - COALESCE(inv_sums.total_usd, 0)) as over_usd'));

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

        $validated['status'] = ($validated['balance'] ?? 0) < 0 ? 'مدين' : (($validated['balance'] ?? 0) > 0 ? 'دائن' : 'متوان');

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
            $validated['status'] = $validated['balance'] < 0 ? 'مدين' : ($validated['balance'] > 0 ? 'دائن' : 'متوان');
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
