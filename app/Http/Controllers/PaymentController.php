<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('customer');

        if ($search = $request->input('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            $payment = Payment::create($validated);

            // تحديث رصيد العميل
            $customer = Customer::find($validated['customer_id']);
            $customer->balance += $validated['amount'];
            $customer->status = $customer->balance < 0 ? 'مدين' : 'متوان';
            $customer->save();

            // تحديث حالة المبيعات المفتوحة بالدفعة الجديدة
            $this->updateCustomerSalesStatus($customer, $validated['amount']);

            return response()->json($payment->load('customer'), 201);
        });
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load('customer'));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        return DB::transaction(function () use ($payment) {
            // إرجاع الرصيد
            $customer = Customer::find($payment->customer_id);
            $customer->balance -= $payment->amount;
            $customer->status = $customer->balance < 0 ? 'مدين' : 'متوان';
            $customer->save();

            // إعادة حساب توزيع المدفوعات على المبيعات
            $this->updateCustomerSalesStatus($customer);

            $payment->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }

    private function updateCustomerSalesStatus(Customer $customer, ?float $amount = null): void
    {
        // إذا لم يُمرر amount، نعيد الحساب من الصفر
        if ($amount === null) {
            $customer->sales()->update([
                'status' => 'عليه دين',
                'paid' => 0,
                'remaining' => DB::raw('`total`'),
            ]);
            $amount = $customer->payments()->sum('amount');
        }

        $sales = $customer->sales()
            ->where('status', 'عليه دين')
            ->orderBy('sale_date')
            ->get();

        $available = $amount;

        foreach ($sales as $sale) {
            if ($available <= 0) {
                break;
            }

            if ($available >= $sale->remaining) {
                $available -= $sale->remaining;
                $sale->update([
                    'paid' => $sale->total,
                    'remaining' => 0,
                    'status' => 'مسدد',
                ]);
            } else {
                $sale->update([
                    'paid' => $sale->paid + $available,
                    'remaining' => $sale->remaining - $available,
                ]);
                $available = 0;
            }
        }
    }
}
