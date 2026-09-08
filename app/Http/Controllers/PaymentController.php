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

            return response()->json($payment->load('customer'), 201);
        });
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load('customer'));
    }

    public function refund(Request $request): JsonResponse
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
            $amount = $validated['amount'];

            $customer = Customer::lockForUpdate()->find($validated['customer_id']);
            $availableCredit = $currency === 'USD' ? $customer->balance_usd : $customer->balance_syp;
            if ($amount > $availableCredit) {
                return response()->json(['error' => 'مبلغ الاسترجاع أكبر من رصيد العميل المتاح في هذه العملة'], 422);
            }

            $payment = Payment::create([
                'customer_id' => $validated['customer_id'],
                'amount' => $amount,
                'currency' => $currency,
                'payment_date' => $validated['payment_date'],
                'notes' => 'استرجاع مبلغ' . ($validated['notes'] ? ' - ' . $validated['notes'] : ''),
            ]);

            if ($currency === 'USD') {
                $customer->balance_usd = round($customer->balance_usd - $amount, 2);
            } else {
                $customer->balance_syp = round($customer->balance_syp - $amount, 2);
            }
            $customer->status = $customer->balance_syp < 0 || $customer->balance_usd < 0
                ? 'مدين'
                : ($customer->balance_syp > 0 || $customer->balance_usd > 0 ? 'دائن' : 'متوان');
            $customer->save();

            return response()->json($payment->load('customer'), 201);
        });
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

            $payment->delete();

            return response()->json(['message' => 'Deleted successfully']);
        });
    }

}
