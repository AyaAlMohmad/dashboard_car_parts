<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Withdrawal::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('person_name', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->latest('withdrawal_date')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'in:SYP,USD'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'withdrawal_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['currency'] = $validated['currency'] ?? 'SYP';

        $withdrawal = Withdrawal::create($validated);

        return response()->json($withdrawal, 201);
    }

    public function show(Withdrawal $withdrawal): JsonResponse
    {
        return response()->json($withdrawal);
    }

    public function destroy(Withdrawal $withdrawal): JsonResponse
    {
        $withdrawal->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
