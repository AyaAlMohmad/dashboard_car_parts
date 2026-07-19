<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function summary(): JsonResponse
    {
        $stats = [
            'low_stock_parts' => Part::whereIn('status', ['منخفض', 'غير متوفر'])->count(),
            'inventory_value' => (float) Part::selectRaw('SUM(quantity * purchase_price) as total')->value('total'),
            'debtors' => Invoice::where('status', 'عليه دين')->sum('paid'),
            'total_debts' => Invoice::where('status', 'عليه دين')->sum('remaining'),
            'payments_count' => Payment::count(),
            'sales_count' => Invoice::count(),
            'suppliers_count' => Supplier::count(),
            'purchases_count' => Purchase::count(),
            'total_purchases' => Purchase::sum('total'),
            'supplier_debts' => Purchase::where('status', 'علينا دين')->sum('remaining'),
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
            SUM(invoice_items.quantity * parts.purchase_price) as cost'
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
