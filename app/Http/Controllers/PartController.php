<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'alert_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $part = Part::create($validated);

        return response()->json($part->load('category'), 201);
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
            'purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'alert_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $part->update($validated);

        return response()->json($part->load('category'));
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
