<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Part;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'part']);
        if ($search = $request->input('search')) {
            $query->whereHas('supplier', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhereHas('part', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'part_id' => ['required', 'exists:parts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purchase_date' => ['required', 'date'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated) {
            $part = Part::lockForUpdate()->findOrFail($validated['part_id']);
            $total = $part->purchase_price * $validated['quantity'];
            $paid = $validated['paid'] ?? 0;
            $remaining = $total - $paid;

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'part_id' => $validated['part_id'],
                'quantity' => $validated['quantity'],
                'total' => $total,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $remaining > 0 ? 'علينا دين' : 'مسدد',
                'purchase_date' => $validated['purchase_date'],
            ]);

            $part->increment('quantity', $validated['quantity']);

            // تحديث رصيد المورد (علينا = نحنا مدينين = balance موجب)
            $supplier = Supplier::find($validated['supplier_id']);
            $supplier->balance += $remaining;
            $supplier->status = $supplier->balance > 0 ? 'علينا' : ($supplier->balance < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            return response()->json($purchase->load(['supplier', 'part']), 201);
        });
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json($purchase->load(['supplier', 'part']));
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        return DB::transaction(function () use ($purchase) {
            $part = Part::find($purchase->part_id);
            $part->decrement('quantity', $purchase->quantity);

            $supplier = Supplier::find($purchase->supplier_id);
            $supplier->balance -= $purchase->remaining;
            $supplier->status = $supplier->balance > 0 ? 'علينا' : ($supplier->balance < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            $purchase->delete();
            return response()->json(['message' => 'Deleted successfully']);
        });
    }
}
