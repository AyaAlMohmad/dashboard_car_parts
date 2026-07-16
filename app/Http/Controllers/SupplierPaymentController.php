<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::with('supplier');
        if ($search = $request->input('search')) {
            $query->whereHas('supplier', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            $payment = SupplierPayment::create($validated);

            $supplier = Supplier::find($validated['supplier_id']);
            $supplier->balance -= $validated['amount'];
            $supplier->status = $supplier->balance > 0 ? 'علينا' : ($supplier->balance < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            $this->updateSupplierPurchasesStatus($supplier, $validated['amount']);

            return response()->json($payment->load('supplier'), 201);
        });
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        return response()->json($supplierPayment->load('supplier'));
    }

    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        return DB::transaction(function () use ($supplierPayment) {
            $supplier = Supplier::find($supplierPayment->supplier_id);
            $supplier->balance += $supplierPayment->amount;
            $supplier->status = $supplier->balance > 0 ? 'علينا' : ($supplier->balance < 0 ? 'لنا' : 'متوازن');
            $supplier->save();

            // إعادة حساب توزيع المدفوعات على المشتريات
            $this->updateSupplierPurchasesStatus($supplier);

            $supplierPayment->delete();
            return response()->json(['message' => 'Deleted successfully']);
        });
    }

    private function updateSupplierPurchasesStatus(Supplier $supplier, ?float $amount = null): void
    {
        // إذا لم يُمرر amount، نعيد الحساب من الصفر
        if ($amount === null) {
            $supplier->purchases()->update([
                'status' => 'علينا دين',
                'paid' => 0,
                'remaining' => DB::raw('`total`'),
            ]);
            $amount = $supplier->supplierPayments()->sum('amount');
        }

        $purchases = $supplier->purchases()
            ->where('status', 'علينا دين')
            ->orderBy('purchase_date')
            ->get();

        $available = $amount;

        foreach ($purchases as $purchase) {
            if ($available <= 0) break;
            if ($available >= $purchase->remaining) {
                $available -= $purchase->remaining;
                $purchase->update([
                    'paid' => $purchase->total,
                    'remaining' => 0,
                    'status' => 'مسدد',
                ]);
            } else {
                $purchase->update([
                    'paid' => $purchase->paid + $available,
                    'remaining' => $purchase->remaining - $available,
                ]);
                $available = 0;
            }
        }
    }
}
