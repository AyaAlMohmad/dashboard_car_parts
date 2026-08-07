<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'part']);

        if ($search = $request->input('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhereHas('part', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'part_id' => ['required', 'exists:parts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'sale_date' => ['required', 'date'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {
            $part = Part::lockForUpdate()->findOrFail($validated['part_id']);

            if ($part->quantity < $validated['quantity']) {
                return response()->json([
                    'message' => 'الكمية المطلوبة غير متوفرة في المخزون',
                ], 422);
            }

            $total = $part->sale_price * $validated['quantity'];
            $paid = $validated['paid'] ?? 0;

            if ($paid > $total) {
                return response()->json([
                    'message' => 'المبلغ المدفوع أكبر من إجمالي الفاتورة.',
                ], 422);
            }

            $remaining = $total - $paid;

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'part_id' => $validated['part_id'],
                'quantity' => $validated['quantity'],
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $remaining > 0 ? 'عليه دين' : 'مسدد',
                'sale_date' => $validated['sale_date'],
            ]);

            $part->decrement('quantity', $validated['quantity']);

            // تحديث رصيد العميل
            $customer = Customer::find($validated['customer_id']);
            $customer->balance -= $remaining;
            $customer->status = $customer->balance < 0 ? 'مدين' : ($customer->balance > 0 ? 'دائن' : 'متوان');
            $customer->save();

            // تسجيل الدفعة في جدول التسديدات إذا كان هناك مبلغ مدفوع
            if ($paid > 0) {
                Payment::create([
                    'customer_id' => $validated['customer_id'],
                    'sale_id' => $sale->id,
                    'amount' => $paid,
                    'notes' => 'دفعة من عملية بيع #' . $sale->id,
                    'payment_date' => $validated['sale_date'],
                ]);
            }

            return response()->json($sale->load(['customer', 'part']), 201);
        });
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($sale->load(['customer', 'part']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        return DB::transaction(function () use ($sale) {
            // إرجاع الكمية للمخزون
            $part = Part::find($sale->part_id);
            $part->increment('quantity', $sale->quantity);

            // إرجاع الرصيد الكلي للعميل (المدفوع + المتبقي) لأن التسديد المرتبط سيُحذف تلقائياً
            $customer = Customer::find($sale->customer_id);
            $customer->balance += $sale->total;
            $customer->status = $customer->balance < 0 ? 'مدين' : ($customer->balance > 0 ? 'دائن' : 'متوان');
            $customer->save();

            $sale->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }
}
