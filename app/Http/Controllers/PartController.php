<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Part::with('category');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('part_number', 'like', "%{$search}%");
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'unique:parts,part_number'],
            'category_id' => ['required', 'exists:categories,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['required_without:purchase_price_usd', 'numeric', 'gt:0'],
            'purchase_price_usd' => ['required_without:purchase_price', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'alert_threshold' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated) {
            $lira = $validated['purchase_price'] ?? 0;
            $usd = $validated['purchase_price_usd'] ?? 0;
            $validated['sale_price'] = ($lira > 0) ? $lira : (($usd > 0) ? $usd : 1);
            $part = Part::create($validated);

            return response()->json($part->load('category'), 201);
        });
    }

    public function show(Part $part): JsonResponse
    {
        return response()->json($part->load('category', 'sales'));
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'part_number' => ['sometimes', 'nullable', 'string', 'unique:parts,part_number,' . $part->id],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'purchase_price' => ['sometimes', 'required_without:purchase_price_usd', 'numeric', 'gt:0'],
            'purchase_price_usd' => ['sometimes', 'required_without:purchase_price', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'alert_threshold' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $part) {
            if (isset($validated['purchase_price']) || isset($validated['purchase_price_usd'])) {
                $lira = $validated['purchase_price'] ?? $part->purchase_price;
                $usd = $validated['purchase_price_usd'] ?? $part->purchase_price_usd;
                $validated['sale_price'] = ($lira > 0) ? $lira : (($usd > 0) ? $usd : 1);
            }
            $part->update($validated);

            return response()->json($part->load('category'));
        });
    }


    public function destroy(Part $part): JsonResponse
    {
        $part->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function categories(): JsonResponse
    {
        return response()->json(Category::withCount('parts')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category->update($validated);
        return response()->json($category);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        $category->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
