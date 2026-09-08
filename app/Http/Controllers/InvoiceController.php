<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Profit;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'items.part']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('total', 'like', "%{$search}%")
                    ->orWhere('paid', 'like', "%{$search}%")
                    ->orWhere('remaining', 'like', "%{$search}%")
                    ->orWhere('sale_date', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.part', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('part_number', 'like', "%{$search}%");
                    });
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'exists:parts,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'sale_date' => ['required', 'date'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'settle_credit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'in:SYP,USD'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated) {
            $currency = $validated['currency'] ?? 'SYP';
            $exchangeRate = 1.0;

            // Validate stock and calculate totals
            $itemsData = [];
            $total = 0;
            foreach ($validated['items'] as $item) {
                $part = Part::lockForUpdate()->findOrFail($item['part_id']);

                if ($part->quantity < $item['quantity']) {
                    return response()->json([
                        'message' => 'الكمية المطلوبة غير متوفرة في المخزون: ' . $part->name,
                    ], 422);
                }

                if ($currency === 'USD' && (float) $part->purchase_price_usd <= 0) {
                    return response()->json([
                        'message' => 'لا يوجد سعر شراء بالدولار للقطعة: ' . $part->name,
                    ], 422);
                }

                if ($currency === 'SYP' && (float) $part->purchase_price <= 0) {
                    return response()->json([
                        'message' => 'لا يوجد سعر شراء بالليرة للقطعة: ' . $part->name,
                    ], 422);
                }

                $itemTotal = $item['unit_price'] * $item['quantity'];
                $total += $itemTotal;

                $itemsData[] = [
                    'part' => $part,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $itemTotal,
                ];
            }

            $total = round($total, 2);
            $cashPaid = round($validated['paid'] ?? 0, 2);

            $customer = Customer::lockForUpdate()->find($validated['customer_id']);

            // تسديد من الرصيد الزائد (دائن) للعميل بناءً على القيمة المدخلة
            $balanceField = $currency === 'USD' ? 'balance_usd' : 'balance_syp';
            $availableCredit = max(0, (float) $customer->{$balanceField});
            $settleCredit = min(
                (float) ($validated['settle_credit'] ?? 0),
                $availableCredit,
                max(0, $total - $cashPaid)
            );
            $creditUsed = round($settleCredit, 2);
            $paid = round($cashPaid, 2);
            $remaining = round(max(0, $total - $cashPaid - $creditUsed), 2);
            $debtPaid = 0;

            // Generate invoice number based on the highest number for today
            $date = now()->format('Ymd');
            $lastNumber = (int) DB::table('invoices')
                ->where('invoice_number', 'like', 'INV-' . $date . '-%')
                ->max(DB::raw('CAST(SUBSTRING(invoice_number, -4) AS UNSIGNED)'));
            $invoiceNumber = 'INV-' . $date . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'customer_id' => $validated['customer_id'],
                'invoice_number' => $invoiceNumber,
                'total' => $total,
                'paid' => $paid,
                'credit_used' => $creditUsed,
                'debt' => 0,
                'remaining' => $remaining,
                'status' => $remaining > 0 ? 'عليه دين' : 'مسدد',
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'notes' => $validated['notes'] ?? null,
                'sale_date' => $validated['sale_date'],
            ]);

            foreach ($itemsData as $itemData) {
                $invoiceItem = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'part_id' => $itemData['part']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total' => $itemData['total'],
                ]);

                $purchasePrice = $currency === 'USD' ? $itemData['part']->purchase_price_usd : $itemData['part']->purchase_price;
                Profit::create([
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $invoiceItem->id,
                    'customer_id' => $invoice->customer_id,
                    'part_id' => $itemData['part']->id,
                    'quantity' => $itemData['quantity'],
                    'returned_quantity' => 0,
                    'unit_price' => $itemData['unit_price'],
                    'purchase_price' => $purchasePrice,
                    'currency' => $currency,
                    'sale_date' => $invoice->sale_date,
                    'profit' => $itemData['quantity'] * ($itemData['unit_price'] - $purchasePrice),
                    'returned_profit' => 0,
                ]);

                $itemData['part']->decrement('quantity', $itemData['quantity']);
            }

            // Update customer balance by currency
            if ($currency === 'USD') {
                $customer->balance_usd = round($customer->balance_usd + $cashPaid - $total, 2);
            } else {
                $customer->balance_syp = round($customer->balance_syp + $cashPaid - $total, 2);
            }
            $customer->status = $customer->balance_syp < 0 || $customer->balance_usd < 0
                ? 'مدين'
                : ($customer->balance_syp > 0 || $customer->balance_usd > 0 ? 'دائن' : 'متوان');
            $customer->save();

            return response()->json($invoice->load(['customer', 'items.part']), 201);
        });
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load(['customer', 'items.part']));
    }

    public function returnItems(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['required', 'exists:invoice_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($validated, $invoice) {
            $returnTotal = 0;

            foreach ($validated['items'] as $returnItem) {
                $item = InvoiceItem::where('id', $returnItem['invoice_item_id'])
                    ->where('invoice_id', $invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $returnQty = $returnItem['quantity'];
                $maxReturn = $item->quantity - $item->returned_quantity;

                if ($returnQty > $maxReturn) {
                    return response()->json([
                        'message' => 'كمية الترجيع تتجاوز الكمية المتاحة لقطعة: ' . ($item->part?->name ?? '؟'),
                    ], 422);
                }

                $amount = $returnQty * $item->unit_price;
                $returnTotal += $amount;

                $item->returned_quantity += $returnQty;
                $item->save();

                $profit = Profit::where('invoice_item_id', $item->id)->first();
                if ($profit) {
                    $returnProfit = $returnQty * ($item->unit_price - $profit->purchase_price);
                    $profit->returned_quantity += $returnQty;
                    $profit->returned_profit += $returnProfit;
                    $profit->save();
                }

                $part = Part::lockForUpdate()->find($item->part_id);
                if ($part) {
                    $part->quantity += $returnQty;
                    $part->save();
                }
            }

            $invoice->total = round(max(0, $invoice->total - $returnTotal), 2);
            $invoice->remaining = round(max(0, $invoice->total - $invoice->paid - $invoice->credit_used), 2);
            $invoice->status = $invoice->remaining > 0 ? 'عليه دين' : 'مسدد';
            $invoice->return_reason = $validated['reason'] ?? null;
            $invoice->save();

            $customer = Customer::lockForUpdate()->find($invoice->customer_id);
            if ($customer) {
                if ($invoice->currency === 'USD') {
                    $customer->balance_usd += ($returnTotal * 1.0);
                } else {
                    $customer->balance_syp += ($returnTotal * 1.0);
                }
                $customer->status = $customer->balance_syp < 0 || $customer->balance_usd < 0
                    ? 'مدين'
                    : ($customer->balance_syp > 0 || $customer->balance_usd > 0 ? 'دائن' : 'متوان');
                $customer->save();
            }

            return response()->json($invoice->load(['customer', 'items.part']), 201);
        });
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        return DB::transaction(function () use ($invoice) {
            // حذف الفاتورة فقط بدون إرجاع مخزون أو رصيد
            // إرجاع القطع والمبلغ يتم فقط عند عملية الترجيع (returnItems)
            $invoice->items()->delete();
            $invoice->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'exchange_rate' => 1,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Exchange rate updates disabled']);
    }
}
