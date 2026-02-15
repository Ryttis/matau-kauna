<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifierCategory;
use App\Models\ClassifierValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassifierController extends Controller
{
    public function index(): JsonResponse
    {
        $rusis = ClassifierCategory::where('slug', 'rusis')->first();
        $pobudis = ClassifierCategory::where('slug', 'pobudis')->first();
        if (! $rusis) {
            return response()->json(['types' => [], 'rusisCategoryId' => null, 'pobudisCategoryId' => null]);
        }

        $types = ClassifierValue::where('classifier_category_id', $rusis->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'sort_order']);

        return response()->json([
            'types' => $types->map(fn (ClassifierValue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_active' => $v->is_active,
                'sort_order' => $v->sort_order,
            ])->values()->all(),
            'rusisCategoryId' => $rusis->id,
            'pobudisCategoryId' => $pobudis?->id,
        ]);
    }

    public function naturesForType(ClassifierValue $type): JsonResponse
    {
        $rusis = ClassifierCategory::where('slug', 'rusis')->first();
        if (! $rusis || (int) $type->classifier_category_id !== (int) $rusis->id || $type->parent_id !== null) {
            return response()->json(['natures' => []], 404);
        }

        $natures = $type->children()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'typeName' => $type->name,
            'natures' => $natures->map(fn (ClassifierValue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_active' => $v->is_active,
                'sort_order' => $v->sort_order,
            ])->values()->all(),
        ]);
    }

    public function storeValue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classifier_category_id' => 'required_without:parent_id|nullable|integer|exists:classifier_categories,id',
            'parent_id' => 'nullable|integer|exists:classifier_values,id',
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $pobudis = ClassifierCategory::where('slug', 'pobudis')->first();
        $rusis = ClassifierCategory::where('slug', 'rusis')->first();

        if (! empty($validated['parent_id'])) {
            $parent = ClassifierValue::findOrFail($validated['parent_id']);
            if (! $pobudis || (int) $parent->classifier_category_id !== (int) $rusis->id) {
                return response()->json(['message' => 'Invalid parent.'], 422);
            }
            $validated['classifier_category_id'] = $pobudis->id;
        }

        $value = ClassifierValue::create([
            'classifier_category_id' => $validated['classifier_category_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['value' => [
            'id' => $value->id,
            'name' => $value->name,
            'is_active' => $value->is_active,
            'sort_order' => $value->sort_order,
        ]]);
    }

    public function updateValue(Request $request, ClassifierValue $value): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if (array_key_exists('name', $validated)) {
            $value->name = trim($validated['name']);
        }
        if (array_key_exists('is_active', $validated)) {
            $value->is_active = (bool) $validated['is_active'];
        }
        if (array_key_exists('sort_order', $validated)) {
            $value->sort_order = (int) $validated['sort_order'];
        }
        $value->save();

        return response()->json(['value' => [
            'id' => $value->id,
            'name' => $value->name,
            'is_active' => $value->is_active,
            'sort_order' => $value->sort_order,
        ]]);
    }
}
