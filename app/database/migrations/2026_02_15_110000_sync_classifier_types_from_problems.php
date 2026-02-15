<?php

use App\Models\ClassifierCategory;
use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $rusis = ClassifierCategory::where('slug', 'rusis')->first();
        if (! $rusis) {
            return;
        }

        $categoryNames = Problem::query()->distinct()->pluck('category')->filter()
            ->merge(ProblemAutoAssignmentRule::query()->distinct()->pluck('category')->filter())
            ->unique()
            ->sort()
            ->values();

        $existingNames = ClassifierValue::where('classifier_category_id', $rusis->id)
            ->whereNull('parent_id')
            ->pluck('name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->flip();

        $sortOrder = (int) ClassifierValue::where('classifier_category_id', $rusis->id)->max('sort_order');

        foreach ($categoryNames as $name) {
            $name = trim((string) $name);
            if ($name === '' || $existingNames->has($name)) {
                continue;
            }
            ClassifierValue::create([
                'classifier_category_id' => $rusis->id,
                'parent_id' => null,
                'name' => $name,
                'is_active' => true,
                'sort_order' => ++$sortOrder,
            ]);
            $existingNames->put($name, true);
        }

        $typeByName = ClassifierValue::where('classifier_category_id', $rusis->id)
            ->whereNull('parent_id')
            ->get()
            ->keyBy('name');

        foreach (Problem::query()->whereNotNull('category')->get() as $problem) {
            $value = $typeByName->get(trim($problem->category));
            if ($value && (int) $problem->problem_type_id !== (int) $value->id) {
                $problem->update(['problem_type_id' => $value->id]);
            }
        }
    }

    public function down(): void
    {
    }
};
