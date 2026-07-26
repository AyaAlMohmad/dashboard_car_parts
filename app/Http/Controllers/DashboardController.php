<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
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
            'customers_count' => Customer::count(),
            'suppliers_count' => Supplier::count(),
            'parts_count' => Part::count(),
            'parts_low_stock' => Part::whereIn('status', ['منخفض', 'غير متوفر'])->count(),
            'parts_quantity' => Part::sum('quantity'),
            'sales_total_syp' => (float) Invoice::where('currency', 'SYP')->sum('total'),
            'sales_total_usd' => (float) Invoice::where('currency', 'USD')->sum('total'),
            'sales_paid_syp' => (float) Invoice::where('currency', 'SYP')->sum('paid'),
            'sales_paid_usd' => (float) Invoice::where('currency', 'USD')->sum('paid'),
            'debts_syp' => (float) Invoice::where('status', 'عليه دين')->where('currency', 'SYP')->sum('remaining'),
            'debts_usd' => (float) Invoice::where('status', 'عليه دين')->where('currency', 'USD')->sum('remaining'),
            'purchases_total_syp' => (float) Purchase::where('currency', 'SYP')->sum('total'),
            'purchases_total_usd' => (float) Purchase::where('currency', 'USD')->sum('total'),
            'supplier_debts_syp' => (float) Purchase::where('status', 'علينا دين')->where('currency', 'SYP')->sum('remaining'),
            'supplier_debts_usd' => (float) Purchase::where('status', 'علينا دين')->where('currency', 'USD')->sum('remaining'),
            'total_withdrawals_syp' => (float) Withdrawal::where('currency', 'SYP')->sum('amount'),
            'total_withdrawals_usd' => (float) Withdrawal::where('currency', 'USD')->sum('amount'),
            'warehouse_profit_syp' => $profitByCurrency['SYP'] ?? 0,
            'warehouse_profit_usd' => $profitByCurrency['USD'] ?? 0,
        ];

        $recentSales = Invoice::with(['customer', 'items.part'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_sales' => $recentSales,
        ]);
    }
}
