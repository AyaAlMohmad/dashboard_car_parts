<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Category;
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
            'purchase_date' => ['required', 'date'],
            'currency' => ['nullable', 'in:SYP,USD'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['nullable', 'exists:parts,id', 'required_without:items.*.part_name'],
            'items.*.part_name' => ['nullable', 'string', 'max:255', 'required_without:items.*.part_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated) {
            $supplier = Supplier::lockForUpdate()->findOrFail($validated['supplier_id']);
            $defaultCategory = Category::firstOrCreate(['name' => 'غير مصنف']);
            $paidTotal = $validated['paid'] ?? 0;
            $grandTotal = 0;
            $resolvedItems = [];

            foreach ($validated['items'] as $item) {
                if (!empty($item['part_id'])) {
                    $part = Part::lockForUpdate()->findOrFail($item['part_id']);
                } else {
                    $part = Part::create([
                        'name' => $item['part_name'],
                        'part_number' => '',
                        'category_id' => $defaultCategory->id,
                        'quantity' => 0,
                        'purchase_price' => $item['unit_price'],
                        'sale_price' => $item['unit_price'],
                        'purchase_price_usd' => 0,
                        'sale_price_usd' => 0,
                        'notes' => '',
                        'alert_threshold' => 5,
                        'image' => '',
                    ]);
                }

                $total = $item['unit_price'] * $item['quantity'];
                $grandTotal += $total;
                $resolvedItems[] = ['part' => $part, 'total' => $total, 'item' => $item];
            }

            $totalRemaining = 0;
            $remainingPaid = $paidTotal;
            $itemCount = count($resolvedItems);

            foreach ($resolvedItems as $index => $ri) {
                $part = $ri['part'];
                $total = $ri['total'];
                $item = $ri['item'];

                if ($index === $itemCount - 1) {
                    $itemPaid = min($total, $remainingPaid);
                } else {
                    $itemPaid = ($grandTotal > 0) ? round($paidTotal * $total / $grandTotal, 2) : 0;
                    $itemPaid = min($itemPaid, $total, $remainingPaid);
                }
                $remaining = round($total - $itemPaid, 2);
                $remainingPaid -= $itemPaid;
                $totalRemaining += $remaining;

                Purchase::create([
                    'supplier_id' => $validated['supplier_id'],
                    'part_id' => $part->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $total,
                    'paid' => $itemPaid,
                    'remaining' => $remaining,
                    'status' => $remaining > 0 ? 'علينا دين' : 'مسدد',
                    'currency' => $validated['currency'] ?? 'SYP',
                    'purchase_date' => $validated['purchase_date'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                $part->quantity += $item['quantity'];
                $part->purchase_price = $item['unit_price'];
                $part->save();
            }

            $supplier->balance += $totalRemaining;
            $supplier->status = $supplier->balance > 0 ? 'علينا' : ($supplier->balance < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            return response()->json(['message' => 'تم تسجيل المشتريات'], 201);
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
