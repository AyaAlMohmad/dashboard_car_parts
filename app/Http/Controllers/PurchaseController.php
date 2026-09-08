<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\SupplierPayment;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier.supplierPayments', 'part']);
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('total', 'like', "%{$search}%")
                    ->orWhere('paid', 'like', "%{$search}%")
                    ->orWhere('remaining', 'like', "%{$search}%")
                    ->orWhere('quantity', 'like', "%{$search}%")
                    ->orWhere('unit_price', 'like', "%{$search}%")
                    ->orWhere('purchase_date', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    })
                    ->orWhereHas('part', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('part_number', 'like', "%{$search}%");
                    });
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
            'settle_credit' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['nullable', 'exists:parts,id', 'required_without:items.*.part_name'],
            'items.*.part_name' => ['nullable', 'string', 'max:255', 'required_without:items.*.part_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:1'],
        ]);

        $paidTotal = $validated['paid'] ?? 0;
        $currency = $validated['currency'] ?? 'SYP';
        $rate = 1.0;
        $grandTotal = 0;
        foreach ($validated['items'] as $item) {
            $grandTotal += $item['unit_price'] * $item['quantity'];
        }

        return DB::transaction(function () use ($validated, $paidTotal, $grandTotal, $currency, $rate) {
            $supplier = Supplier::lockForUpdate()->findOrFail($validated['supplier_id']);
            $defaultCategory = Category::firstOrCreate(['name' => 'غير مصنف']);

            // معرّف صف الدين القديم إن وجد (0 إن لم يُنشأ بعد)
            $oldDebtPartId = DB::table('parts')->where('part_number', 'OLD_DEBT')->value('id') ?? 0;

            // تسديد من الرصيد الزائد (لنا) للمورد بناءً على القيمة المدخلة
            $balanceField = $currency === 'USD' ? 'balance_usd' : 'balance_syp';
            $availableCredit = -min(0, (float) $supplier->{$balanceField});
            $settleCredit = min(
                (float) ($validated['settle_credit'] ?? 0),
                $availableCredit,
                max(0, $grandTotal - $paidTotal)
            );
            $creditUsed = round($settleCredit, 2);

            // دين قديم على المورد (موجب = علينا) بنفس عملة الفاتورة الجديدة فقط
            $oldDebt = max(0,
                (float) DB::table('purchases')
                    ->where('supplier_id', $supplier->id)
                    ->where('currency', $currency)
                    ->where('part_id', '!=', $oldDebtPartId)
                    ->sum('remaining')
                -
                (float) DB::table('supplier_payments')
                    ->where('supplier_id', $supplier->id)
                    ->where('currency', $currency)
                    ->sum('amount')
                -
                (float) DB::table('purchases')
                    ->where('supplier_id', $supplier->id)
                    ->where('currency', $currency)
                    ->where('part_id', $oldDebtPartId)
                    ->sum('paid')
            );
            $cashAfterItems = max(0, $paidTotal - ($grandTotal - $creditUsed));
            $oldDebtPaid = min($oldDebt, $cashAfterItems);
            $excess = $cashAfterItems - $oldDebtPaid;

            $firstPurchaseId = null;
            $remainingPaid = min($paidTotal + $creditUsed, $grandTotal);
            $remainingCredit = $creditUsed;
            $itemCount = count($validated['items']);

            foreach ($validated['items'] as $index => $item) {
                if (!empty($item['part_id'])) {
                    $part = Part::lockForUpdate()->findOrFail($item['part_id']);
                } else {
                    $isUsd = $currency === 'USD';
                    $part = Part::create([
                        'name' => $item['part_name'],
                        'part_number' => (string) Str::uuid(),
                        'category_id' => $defaultCategory->id,
                        'quantity' => 0,
                        'purchase_price' => $isUsd ? 0 : $item['unit_price'],
                        'sale_price' => $isUsd ? 0 : $item['unit_price'],
                        'purchase_price_usd' => $isUsd ? $item['unit_price'] : 0,
                        'sale_price_usd' => $isUsd ? $item['unit_price'] : 0,
                        'notes' => '',
                        'alert_threshold' => 5,
                        'image' => '',
                    ]);
                }

                $total = $item['unit_price'] * $item['quantity'];

                if ($paidTotal >= $grandTotal) {
                    $itemPaid = $total;
                    $remaining = 0.0;
                } elseif ($index === $itemCount - 1) {
                    $itemPaid = min($total, $remainingPaid);
                    $remaining = round($total - $itemPaid, 2);
                } else {
                    $itemPaid = ($grandTotal > 0) ? round($paidTotal * $total / $grandTotal, 2) : 0;
                    $itemPaid = min($itemPaid, $total, $remainingPaid);
                    $remaining = round($total - $itemPaid, 2);
                }

                $remainingPaid -= $itemPaid;

                $itemCredit = min($remainingCredit, $itemPaid);
                $remainingCredit -= $itemCredit;
                $itemCash = round($itemPaid - $itemCredit, 2);
                $remaining = round(max(0, $total - $itemCash - $itemCredit), 2);

                $purchase = Purchase::create([
                    'supplier_id' => $validated['supplier_id'],
                    'part_id' => $part->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $total,
                    'paid' => $itemCash,
                    'credit_used' => $itemCredit,
                    'remaining' => $remaining,
                    'status' => $remaining > 0 ? 'علينا دين' : 'مسدد',
                    'currency' => $currency,
                    'purchase_date' => $validated['purchase_date'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                if ($firstPurchaseId === null) {
                    $firstPurchaseId = $purchase->id;
                }

                $part->quantity += $item['quantity'];

                // تحديث سعر القطعة المسجل لآخر سعر شراء (إذا كان مختلفاً)
                $costField = $currency === 'USD' ? 'purchase_price_usd' : 'purchase_price';
                $part->{$costField} = round($item['unit_price'], 2);

                $part->save();
            }

            // صف عرض للدين القديم المسدد من الفاتورة الجديدة (لا يعدل فواتير قديمة)
            if ($oldDebt > 0) {
                $oldDebtPart = Part::firstOrCreate(
                    ['part_number' => 'OLD_DEBT'],
                    [
                        'name' => 'دين قديم',
                        'category_id' => $defaultCategory->id,
                        'quantity' => 0,
                        'purchase_price' => 0,
                        'sale_price' => 0,
                        'purchase_price_usd' => 0,
                        'sale_price_usd' => 0,
                        'notes' => '',
                        'alert_threshold' => 0,
                        'image' => '',
                        'supplier' => '',
                    ]
                );
                Purchase::create([
                    'supplier_id' => $validated['supplier_id'],
                    'part_id' => $oldDebtPart->id,
                    'quantity' => 1,
                    'unit_price' => $oldDebt,
                    'total' => $oldDebt,
                    'paid' => $oldDebtPaid,
                    'remaining' => $oldDebt - $oldDebtPaid,
                    'status' => ($oldDebt - $oldDebtPaid) > 0 ? 'علينا دين' : 'مسدد',
                    'currency' => $currency,
                    'purchase_date' => $validated['purchase_date'],
                    'notes' => 'دين قديم',
                ]);
            }

            // رصيد المورد يتحرك بصافي الفاتورة بعد خصم النقد والرصيد المستخدم
            $net = $grandTotal - $paidTotal - $creditUsed;
            if ($currency === 'USD') {
                $supplier->balance_usd += $net;
            } else {
                $supplier->balance_syp += $net;
            }
            $supplier->status = $supplier->balance_syp > 0 || $supplier->balance_usd > 0
                ? 'علينا'
                : ($supplier->balance_syp < 0 || $supplier->balance_usd < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            // تسجيل الزيادة كدفعة مورد (للعرض) بدون تعديل فواتير قديمة
            if ($excess > 0) {
                SupplierPayment::create([
                    'supplier_id' => $validated['supplier_id'],
                    'purchase_id' => $firstPurchaseId,
                    'amount' => $excess,
                    'currency' => $currency,
                    'payment_date' => $validated['purchase_date'],
                    'notes' => 'دفعة زائدة من فاتورة شراء',
                ]);
            }

            return response()->json(['message' => 'تم تسجيل المشتريات'], 201);
        });
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json($purchase->load(['supplier', 'part']));
    }

    public function returnItems(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_id' => ['required', 'exists:purchases,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated, $purchase) {
            $ids = collect($validated['items'])->pluck('purchase_id')->all();
            $start = $purchase->created_at->copy()->startOfSecond();
            $end = $purchase->created_at->copy()->endOfSecond();

            $purchases = Purchase::whereIn('id', $ids)
                ->where('supplier_id', $purchase->supplier_id)
                ->where('purchase_date', $purchase->purchase_date)
                ->whereBetween('created_at', [$start, $end])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($purchases->count() !== count($ids)) {
                return response()->json(['message' => 'بعض القطع غير موجودة في هذه العملية'], 422);
            }

            $returnTotal = 0;

            foreach ($validated['items'] as $item) {
                $p = $purchases->get($item['purchase_id']);
                $returnQty = $item['quantity'];
                $maxReturn = $p->quantity - $p->returned_quantity;

                if ($returnQty > $maxReturn) {
                    return response()->json(['message' => 'كمية الترجيع تتجاوز الكمية المتاحة لقطعة: ' . ($p->part?->name ?? '؟')], 422);
                }

                $p->returned_quantity += $returnQty;
                $p->total = round($p->total - ($returnQty * $p->unit_price), 2);
                $p->remaining = round(max(0, $p->total - $p->paid - $p->credit_used), 2);
                $p->status = $p->remaining > 0 ? 'علينا دين' : 'مسدد';
                $p->save();

                $part = Part::lockForUpdate()->find($p->part_id);
                if ($part && $part->part_number !== 'OLD_DEBT') {
                    $part->decrement('quantity', $returnQty);
                }

                $returnTotal += $returnQty * $p->unit_price;
            }

            $supplier = Supplier::lockForUpdate()->find($purchase->supplier_id);
            if ($supplier) {
                $balanceField = $purchase->currency === 'USD' ? 'balance_usd' : 'balance_syp';
                $supplier->{$balanceField} = round($supplier->{$balanceField} - $returnTotal, 2);
                $supplier->status = $supplier->balance_syp > 0 || $supplier->balance_usd > 0
                    ? 'علينا'
                    : ($supplier->balance_syp < 0 || $supplier->balance_usd < 0 ? 'لنا' : 'متوازن');
                $supplier->save();
            }

            return response()->json(['message' => 'تم الترجيع'], 201);
        });
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        return DB::transaction(function () use ($purchase) {
            // حذف سجل الشراء فقط دون إرجاع مخزون أو تعديل رصيد المورد
            $purchase->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }
}
