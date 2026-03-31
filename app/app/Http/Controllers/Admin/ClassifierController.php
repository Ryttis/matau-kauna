<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifierValue;
use App\Support\ClassifierValueMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassifierController extends Controller
{
    public function index(): JsonResponse
    {
        $types = ClassifierValue::notArchived()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'sort_order']);

        return response()->json([
            'types' => $types->map(fn (ClassifierValue $v) => ClassifierValueMapper::toArray($v))->values()->all(),
        ]);
    }

    public function naturesForType(ClassifierValue $type): JsonResponse
    {
        if ($type->parent_id !== null) {
            return response()->json(['natures' => []], 404);
        }

        $natures = $type->children()->notArchived()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'typeName' => $type->name,
            'natures' => $natures->map(fn (ClassifierValue $v) => ClassifierValueMapper::toArray($v))->values()->all(),
        ]);
    }

    public function storeValue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:classifier_values,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = ClassifierValue::findOrFail($validated['parent_id']);
            if ($parent->parent_id !== null) {
                return response()->json(['message' => 'Galima susieti tik su problemos rūšimi, ne su pobūdžiu.'], 422);
            }
        }

        $value = ClassifierValue::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['value' => ClassifierValueMapper::toArray($value)]);
    }

    public function updateValue(Request $request, ClassifierValue $value): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }

        $value->update($validated);

        return response()->json(['value' => ClassifierValueMapper::toArray($value)]);
    }

    public function destroyValue(ClassifierValue $value): JsonResponse
    {
        $now = now();
        if ($value->parent_id === null) {
            $value->children()->update(['archived_at' => $now]);
        }
        $value->update(['archived_at' => $now]);

        return response()->json(['ok' => true]);
    }
}
