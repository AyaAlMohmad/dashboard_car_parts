<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Profit;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function summary(): JsonResponse
    {
        $profitByCurrency = Profit::selectRaw(
            'currency,
            SUM(profit - returned_profit) as net_profit'
        )
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->currency => (float) $row->net_profit]);

        $oldDebtPartId = Part::where('part_number', 'OLD_DEBT')->value('id') ?? 0;

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

        $customerPaymentSums = DB::table('payments')
            ->select('customer_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as payments_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as payments_usd'))
            ->groupBy('customer_id');

        $customerDebts = Customer::query()
            ->leftJoinSub($invoiceSums, 'inv_sums', 'customers.id', '=', 'inv_sums.customer_id')
            ->leftJoinSub($salesSums, 'sale_sums', 'customers.id', '=', 'sale_sums.customer_id')
            ->leftJoinSub($customerPaymentSums, 'pay_sums', 'customers.id', '=', 'pay_sums.customer_id')
            ->select(
                DB::raw('SUM(GREATEST(0, COALESCE(inv_sums.total_syp, 0) + COALESCE(sale_sums.total_syp_sales, 0) - COALESCE(inv_sums.paid_syp, 0) - COALESCE(sale_sums.paid_syp_sales, 0) - COALESCE(pay_sums.payments_syp, 0))) as total_debts_syp'),
                DB::raw('SUM(GREATEST(0, COALESCE(inv_sums.total_usd, 0) - COALESCE(inv_sums.paid_usd, 0) - COALESCE(pay_sums.payments_usd, 0))) as total_debts_usd')
            )
            ->first();

        $purchaseSums = DB::table('purchases')
            ->where('part_id', '!=', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN total ELSE 0 END) as total_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN total ELSE 0 END) as total_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as paid_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN remaining ELSE 0 END) as remaining_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN remaining ELSE 0 END) as remaining_usd'))
            ->groupBy('supplier_id');

        $oldDebtPaidSums = DB::table('purchases')
            ->where('part_id', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as old_debt_paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as old_debt_paid_usd'))
            ->groupBy('supplier_id');

        $supplierPaymentSums = DB::table('supplier_payments')
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as payments_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as payments_usd'))
            ->groupBy('supplier_id');

        $supplierDebts = Supplier::query()
            ->leftJoinSub($purchaseSums, 'pur_sums', 'suppliers.id', '=', 'pur_sums.supplier_id')
            ->leftJoinSub($oldDebtPaidSums, 'old_pay_sums', 'suppliers.id', '=', 'old_pay_sums.supplier_id')
            ->leftJoinSub($supplierPaymentSums, 'pay_sums', 'suppliers.id', '=', 'pay_sums.supplier_id')
            ->select(
                DB::raw('SUM(GREATEST(0, COALESCE(pur_sums.remaining_syp, 0) - COALESCE(pay_sums.payments_syp, 0) - COALESCE(old_pay_sums.old_debt_paid_syp, 0))) as supplier_debts_syp'),
                DB::raw('SUM(GREATEST(0, COALESCE(pur_sums.remaining_usd, 0) - COALESCE(pay_sums.payments_usd, 0) - COALESCE(old_pay_sums.old_debt_paid_usd, 0))) as supplier_debts_usd')
            )
            ->first();

        $stats = [
            'low_stock_parts' => Part::whereIn('status', ['منخفض', 'غير متوفر'])->where('id', '!=', $oldDebtPartId)->count(),
            'inventory_value' => (float) Part::where('id', '!=', $oldDebtPartId)->selectRaw('SUM(quantity * purchase_price) as total')->value('total'),
            'inventory_value_syp' => (float) Part::where('id', '!=', $oldDebtPartId)->selectRaw('SUM(quantity * purchase_price) as total')->value('total'),
            'inventory_value_usd' => (float) Part::where('id', '!=', $oldDebtPartId)->selectRaw('SUM(quantity * purchase_price_usd) as total')->value('total'),
            'payments_count' => Payment::count(),
            'sales_count' => Invoice::count(),
            'suppliers_count' => Supplier::count(),
            'purchases_count' => Purchase::count(),
            'total_debts_syp' => (float) ($customerDebts->total_debts_syp ?? 0),
            'total_debts_usd' => (float) ($customerDebts->total_debts_usd ?? 0),
            'total_purchases_syp' => (float) Purchase::where('currency', 'SYP')->where('part_id', '!=', $oldDebtPartId)->sum('total'),
            'total_purchases_usd' => (float) Purchase::where('currency', 'USD')->where('part_id', '!=', $oldDebtPartId)->sum('total'),
            'supplier_debts_syp' => (float) ($supplierDebts->supplier_debts_syp ?? 0),
            'supplier_debts_usd' => (float) ($supplierDebts->supplier_debts_usd ?? 0),
            'total_withdrawals_syp' => (float) Withdrawal::where('currency', 'SYP')->sum('amount'),
            'total_withdrawals_usd' => (float) Withdrawal::where('currency', 'USD')->sum('amount'),
            'warehouse_profit_syp' => $profitByCurrency['SYP'] ?? 0,
            'warehouse_profit_usd' => $profitByCurrency['USD'] ?? 0,
        ];

        return response()->json($stats);
    }

    public function debtors(): JsonResponse
    {
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

        $customers = Customer::query()
            ->leftJoinSub($invoiceSums, 'inv_sums', 'customers.id', '=', 'inv_sums.customer_id')
            ->leftJoinSub($salesSums, 'sale_sums', 'customers.id', '=', 'sale_sums.customer_id')
            ->leftJoinSub($paymentSums, 'pay_sums', 'customers.id', '=', 'pay_sums.customer_id')
            ->select('customers.*',
                DB::raw('GREATEST(0, COALESCE(inv_sums.total_syp, 0) + COALESCE(sale_sums.total_syp_sales, 0) - COALESCE(inv_sums.paid_syp, 0) - COALESCE(sale_sums.paid_syp_sales, 0) - COALESCE(pay_sums.payments_syp, 0)) as total_debt_syp'),
                DB::raw('GREATEST(0, COALESCE(inv_sums.total_usd, 0) - COALESCE(inv_sums.paid_usd, 0) - COALESCE(pay_sums.payments_usd, 0)) as total_debt_usd'))
            ->havingRaw('total_debt_syp > 0 OR total_debt_usd > 0')
            ->latest()
            ->get();

        return response()->json($customers);
    }

    public function creditors(): JsonResponse
    {
        $oldDebtPartId = (int) (Part::where('part_number', 'OLD_DEBT')->value('id') ?? 0);

        $purchaseSums = DB::table('purchases')
            ->where('part_id', '!=', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN total ELSE 0 END) as total_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN total ELSE 0 END) as total_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as paid_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN remaining ELSE 0 END) as remaining_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN remaining ELSE 0 END) as remaining_usd'))
            ->groupBy('supplier_id');

        $oldDebtPaidSums = DB::table('purchases')
            ->where('part_id', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as old_debt_paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as old_debt_paid_usd'))
            ->groupBy('supplier_id');

        $paymentSums = DB::table('supplier_payments')
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as payments_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as payments_usd'))
            ->groupBy('supplier_id');

        $suppliers = Supplier::query()
            ->leftJoinSub($purchaseSums, 'pur_sums', 'suppliers.id', '=', 'pur_sums.supplier_id')
            ->leftJoinSub($oldDebtPaidSums, 'old_pay_sums', 'suppliers.id', '=', 'old_pay_sums.supplier_id')
            ->leftJoinSub($paymentSums, 'pay_sums', 'suppliers.id', '=', 'pay_sums.supplier_id')
            ->select('suppliers.*',
                DB::raw('GREATEST(0, COALESCE(pur_sums.remaining_syp, 0) - COALESCE(pay_sums.payments_syp, 0) - COALESCE(old_pay_sums.old_debt_paid_syp, 0)) as total_debt_syp'),
                DB::raw('GREATEST(0, COALESCE(pur_sums.remaining_usd, 0) - COALESCE(pay_sums.payments_usd, 0) - COALESCE(old_pay_sums.old_debt_paid_usd, 0)) as total_debt_usd'))
            ->havingRaw('total_debt_syp > 0 OR total_debt_usd > 0')
            ->latest()
            ->get();

        return response()->json($suppliers);
    }

    public function inventory(): JsonResponse
    {
        $parts = Part::with('category')
            ->orderBy('quantity', 'asc')
            ->get();

        return response()->json($parts);
    }

    public function salesReport(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'items.part']);

        if ($from = $request->input('from')) {
            $query->whereDate('sale_date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('sale_date', '<=', $to);
        }

        return response()->json($query->latest()->get());
    }

    public function monthlyProfit(): JsonResponse
    {
        $sales = Profit::selectRaw(
            'DATE_FORMAT(sale_date, "%Y-%m") as month,
            COUNT(DISTINCT invoice_id) as invoices,
            SUM(quantity * unit_price - returned_quantity * unit_price) as total_sales,
            SUM(CASE WHEN currency = "SYP" THEN quantity * unit_price - returned_quantity * unit_price ELSE 0 END) as total_sales_syp,
            SUM(CASE WHEN currency = "USD" THEN quantity * unit_price - returned_quantity * unit_price ELSE 0 END) as total_sales_usd,
            SUM(quantity * purchase_price - returned_quantity * purchase_price) as cost,
            SUM(CASE WHEN currency = "SYP" THEN quantity * purchase_price - returned_quantity * purchase_price ELSE 0 END) as cost_syp,
            SUM(CASE WHEN currency = "USD" THEN quantity * purchase_price - returned_quantity * purchase_price ELSE 0 END) as cost_usd'
        )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($row) {
                $row->total_sales = (float) $row->total_sales;
                $row->total_sales_syp = (float) $row->total_sales_syp;
                $row->total_sales_usd = (float) $row->total_sales_usd;
                $row->cost = (float) $row->cost;
                $row->cost_syp = (float) $row->cost_syp;
                $row->cost_usd = (float) $row->cost_usd;
                $row->net_profit = $row->total_sales - $row->cost;
                $row->net_profit_syp = $row->total_sales_syp - $row->cost_syp;
                $row->net_profit_usd = $row->total_sales_usd - $row->cost_usd;
                return $row;
            });

        return response()->json($sales);
    }
}
