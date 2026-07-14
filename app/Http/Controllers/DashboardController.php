<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Part;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'customers_count' => Customer::count(),
            'suppliers_count' => Supplier::count(),
            'parts_count' => Part::count(),
            'parts_low_stock' => Part::whereIn('status', ['منخفض', 'غير متوفر'])->count(),
            'sales_total' => Sale::sum('total'),
            'sales_paid' => Sale::sum('paid'),
            'debts' => Sale::where('status', 'عليه دين')->sum('remaining'),
            'purchases_total' => Purchase::sum('total'),
            'supplier_debts' => Purchase::where('status', 'علينا دين')->sum('remaining'),
        ];

        $recentSales = Sale::with(['customer', 'part'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_sales' => $recentSales,
        ]);
    }
}
