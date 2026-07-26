<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
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
        $profitByCurrency = Invoice::selectRaw(
            'invoices.currency,
            SUM(invoices.total) as sales,
            SUM((invoice_items.quantity - COALESCE(invoice_items.returned_quantity, 0))
                * CASE WHEN invoices.currency = "USD" THEN COALESCE(parts.purchase_price_usd, 0) ELSE parts.purchase_price END
            ) as cost'
        )
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('parts', 'invoice_items.part_id', '=', 'parts.id')
            ->groupBy('invoices.currency')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->currency => (float) $row->sales - (float) $row->cost]);

        $stats = [
            'low_stock_parts' => Part::whereIn('status', ['منخفض', 'غير متوفر'])->count(),
            'inventory_value' => (float) Part::selectRaw('SUM(quantity * purchase_price) as total')->value('total'),
            'payments_count' => Payment::count(),
            'sales_count' => Invoice::count(),
            'suppliers_count' => Supplier::count(),
            'purchases_count' => Purchase::count(),
            'total_debts_syp' => (float) Invoice::where('status', 'عليه دين')->where('currency', 'SYP')->sum('remaining'),
            'total_debts_usd' => (float) Invoice::where('status', 'عليه دين')->where('currency', 'USD')->sum('remaining'),
            'total_purchases_syp' => (float) Purchase::where('currency', 'SYP')->sum('total'),
            'total_purchases_usd' => (float) Purchase::where('currency', 'USD')->sum('total'),
            'supplier_debts_syp' => (float) Purchase::where('status', 'علينا دين')->where('currency', 'SYP')->sum('remaining'),
            'supplier_debts_usd' => (float) Purchase::where('status', 'علينا دين')->where('currency', 'USD')->sum('remaining'),
            'total_withdrawals_syp' => (float) Withdrawal::where('currency', 'SYP')->sum('amount'),
            'total_withdrawals_usd' => (float) Withdrawal::where('currency', 'USD')->sum('amount'),
            'warehouse_profit_syp' => $profitByCurrency['SYP'] ?? 0,
            'warehouse_profit_usd' => $profitByCurrency['USD'] ?? 0,
        ];

        return response()->json($stats);
    }

    public function debtors(): JsonResponse
    {
        $customers = Customer::where('status', 'مدين')
            ->withSum('invoices as total_debt', 'remaining')
            ->latest()
            ->get();

        return response()->json($customers);
    }

    public function creditors(): JsonResponse
    {
        $suppliers = Supplier::where('status', 'علينا')
            ->withSum('purchases as total_debt', 'remaining')
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
        $sales = Invoice::selectRaw(
            'DATE_FORMAT(invoices.sale_date, "%Y-%m") as month,
            COUNT(DISTINCT invoices.id) as invoices,
            SUM(invoices.total) as total_sales,
            SUM((invoice_items.quantity - COALESCE(invoice_items.returned_quantity, 0))
                * CASE WHEN invoices.currency = "USD" THEN COALESCE(parts.purchase_price_usd, 0) ELSE parts.purchase_price END
            ) as cost'
        )
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('parts', 'invoice_items.part_id', '=', 'parts.id')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($row) {
                $row->net_profit = $row->total_sales - $row->cost;
                return $row;
            });

        return response()->json($sales);
    }
}
