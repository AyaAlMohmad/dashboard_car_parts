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
            'currency' => ['nullable', 'in:SYP,USD'],
            'payment_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated) {
            $currency = $validated['currency'] = $validated['currency'] ?? 'SYP';
            $payment = Payment::create($validated);

            $amount = $validated['amount'];

            // تحديث رصيد العميل حسب العملة
            $customer = Customer::find($validated['customer_id']);
            if ($currency === 'USD') {
                $customer->balance_usd = round($customer->balance_usd + $amount, 2);
            } else {
                $customer->balance_syp = round($customer->balance_syp + $amount, 2);
            }
            $customer->status = $customer->balance_syp < 0 || $customer->balance_usd < 0
                ? 'مدين'
                : ($customer->balance_syp > 0 || $customer->balance_usd > 0 ? 'دائن' : 'متوان');
            $customer->save();

            // تحديث حالة الفواتير المفتوحة بالدفعة الجديدة
            $this->updateCustomerInvoicesStatus($customer, $currency, $amount);

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
            if ($payment->currency === 'USD') {
                $customer->balance_usd = round($customer->balance_usd - $payment->amount, 2);
            } else {
                $customer->balance_syp = round($customer->balance_syp - $payment->amount, 2);
            }
            $customer->status = $customer->balance_syp < 0 || $customer->balance_usd < 0
                ? 'مدين'
                : ($customer->balance_syp > 0 || $customer->balance_usd > 0 ? 'دائن' : 'متوان');
            $customer->save();

            // إعادة حساب توزيع المدفوعات على الفواتير
            $this->updateCustomerInvoicesStatus($customer, $payment->currency);

            $payment->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }

    private function updateCustomerInvoicesStatus(Customer $customer, ?string $currency = null, ?float $amount = null): void
    {
        // إعادة الحساب من الصفر لهذه العملة
        $customer->invoices()
            ->where('currency', $currency)
            ->update([
                'remaining' => DB::raw('GREATEST(0, `total` - `paid`)'),
                'status' => DB::raw('CASE WHEN `paid` >= `total` THEN "مسدد" ELSE "عليه دين" END'),
            ]);

        if ($currency === 'SYP') {
            $customer->sales()
                ->update([
                    'remaining' => DB::raw('GREATEST(0, `total` - `paid`)'),
                    'status' => DB::raw('CASE WHEN `paid` >= `total` THEN "مسدد" ELSE "عليه دين" END'),
                ]);
        }

        // عند الحذف نجمع كل دفعات هذه العملة ونعيد التوزيع
        if ($amount === null) {
            $amount = round($customer->payments()->where('currency', $currency)->get()->sum(fn ($p) => (float) $p->amount), 2);
        }

        $available = round($amount, 2);

        // تغطية الفواتير المفتوحة بهذه العملة
        $invoices = $customer->invoices()
            ->where('currency', $currency)
            ->where('status', 'عليه دين')
            ->orderBy('sale_date')
            ->get();

        foreach ($invoices as $invoice) {
            if ($available <= 0) {
                break;
            }

            $needed = round((float) $invoice->remaining, 2);

            if ($available >= $needed) {
                $available = round($available - $needed, 2);
                $invoice->update([
                    'remaining' => 0,
                    'status' => 'مسدد',
                ]);
            } else {
                $invoice->update([
                    'remaining' => round((float) $invoice->remaining - $available, 2),
                ]);
                $available = 0;
            }
        }

        // تغطية المبيعات المفتوحة بما تبقى من المبلغ (سوري فقط)
        if ($currency === 'SYP' && $available > 0) {
            $sales = $customer->sales()
                ->where('status', 'عليه دين')
                ->orderBy('sale_date')
                ->get();

            foreach ($sales as $sale) {
                if ($available <= 0) {
                    break;
                }

                $needed = round((float) $sale->remaining, 2);

                if ($available >= $needed) {
                    $available = round($available - $needed, 2);
                    $sale->update([
                        'remaining' => 0,
                        'status' => 'مسدد',
                    ]);
                } else {
                    $sale->update([
                        'remaining' => round((float) $sale->remaining - $available, 2),
                    ]);
                    $available = 0;
                }
            }
        }
    }
}
