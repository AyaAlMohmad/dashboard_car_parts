<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        // معرّف صف "دين قديم" الوهمي (لا يُحسب ضمن المشتريات الحقيقية)
        $oldDebtPartId = DB::table('parts')->where('part_number', 'OLD_DEBT')->value('id') ?? 0;

        // المشتريات الحقيقية فقط (بدون OLD_DEBT)
        $purchaseSums = DB::table('purchases')
            ->where('part_id', '!=', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN total ELSE 0 END) as total_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN total ELSE 0 END) as total_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as paid_usd'),
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN remaining ELSE 0 END) as remaining_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN remaining ELSE 0 END) as remaining_usd'))
            ->groupBy('supplier_id');

        // الدفعات المخصصة للديون القديمة (تُحسب كتسديدات)
        $oldDebtPaidSums = DB::table('purchases')
            ->where('part_id', $oldDebtPartId)
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN paid ELSE 0 END) as old_debt_paid_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN paid ELSE 0 END) as old_debt_paid_usd'))
            ->groupBy('supplier_id');

        // دفعات موردين منفصلة
        $paymentSums = DB::table('supplier_payments')
            ->select('supplier_id',
                DB::raw('SUM(CASE WHEN currency = "SYP" THEN amount ELSE 0 END) as payments_syp'),
                DB::raw('SUM(CASE WHEN currency = "USD" THEN amount ELSE 0 END) as payments_usd'))
            ->groupBy('supplier_id');

        $query->leftJoinSub($purchaseSums, 'pur_sums', 'suppliers.id', '=', 'pur_sums.supplier_id')
            ->leftJoinSub($oldDebtPaidSums, 'old_pay_sums', 'suppliers.id', '=', 'old_pay_sums.supplier_id')
            ->leftJoinSub($paymentSums, 'pay_sums', 'suppliers.id', '=', 'pay_sums.supplier_id')
            ->select('suppliers.*',
                DB::raw('COALESCE(pur_sums.total_syp, 0) as total_syp'),
                DB::raw('COALESCE(pur_sums.total_usd, 0) as total_usd'),
                // الديون = المتبقي الحقيقي ناقص الدفعات المنفصلة ناقص ما دُفع للدين القديم
                DB::raw('GREATEST(0, COALESCE(pur_sums.remaining_syp, 0) - COALESCE(pay_sums.payments_syp, 0) - COALESCE(old_pay_sums.old_debt_paid_syp, 0)) as debt_syp'),
                DB::raw('GREATEST(0, COALESCE(pur_sums.remaining_usd, 0) - COALESCE(pay_sums.payments_usd, 0) - COALESCE(old_pay_sums.old_debt_paid_usd, 0)) as debt_usd'),
                // المسدد = المدفوع من الفواتير + دفعات منفصلة + ما دُفع للدين القديم
                DB::raw('(COALESCE(pur_sums.paid_syp, 0) + COALESCE(pay_sums.payments_syp, 0) + COALESCE(old_pay_sums.old_debt_paid_syp, 0)) as paid_syp'),
                DB::raw('(COALESCE(pur_sums.paid_usd, 0) + COALESCE(pay_sums.payments_usd, 0) + COALESCE(old_pay_sums.old_debt_paid_usd, 0)) as paid_usd'),
                // الرصيد الصافي قد يكون سالباً (لنا)
                DB::raw('(COALESCE(pur_sums.remaining_syp, 0) - COALESCE(pay_sums.payments_syp, 0) - COALESCE(old_pay_sums.old_debt_paid_syp, 0)) as balance_syp'),
                DB::raw('(COALESCE(pur_sums.remaining_usd, 0) - COALESCE(pay_sums.payments_usd, 0) - COALESCE(old_pay_sums.old_debt_paid_usd, 0)) as balance_usd'));

        return response()->json($query->orderBy('suppliers.created_at', 'desc')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'balance' => ['nullable', 'numeric'],
        ]);

        $validated['status'] = ($validated['balance'] ?? 0) > 0 ? 'علينا' : (($validated['balance'] ?? 0) < 0 ? 'لنا' : 'متوازن');

        $supplier = Supplier::create($validated);
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier->load('purchases', 'supplierPayments'));
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'balance' => ['nullable', 'numeric'],
        ]);

        if (isset($validated['balance'])) {
            $validated['status'] = $validated['balance'] > 0 ? 'علينا' : ($validated['balance'] < 0 ? 'لنا' : 'متوازن');
        }

        $supplier->update($validated);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
