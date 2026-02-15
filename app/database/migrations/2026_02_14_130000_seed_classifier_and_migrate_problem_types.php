<?php

use App\Models\ClassifierCategory;
use App\Models\ClassifierValue;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rusis = ClassifierCategory::create([
            'slug' => 'rusis',
            'name' => 'Rūšis',
            'sort_order' => 1,
        ]);

        $pobudis = ClassifierCategory::create([
            'slug' => 'pobudis',
            'name' => 'Pobūdis',
            'sort_order' => 2,
        ]);

        $categoryNames = Problem::query()->distinct()->pluck('category')->filter()
            ->merge(ProblemAutoAssignmentRule::query()->distinct()->pluck('category')->filter())
            ->unique()
            ->sort()
            ->values();

        $sortOrder = 0;
        foreach ($categoryNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            ClassifierValue::create([
                'classifier_category_id' => $rusis->id,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }

        $natureDefaults = ['Defektas', 'Tvarkymas', 'Apželdinimas', 'Valymas', 'Kita'];
        $sortOrder = 0;
        foreach ($natureDefaults as $name) {
            ClassifierValue::create([
                'classifier_category_id' => $pobudis->id,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }

        $typeByName = ClassifierValue::where('classifier_category_id', $rusis->id)
            ->get()
            ->keyBy('name');

        foreach (Problem::query()->whereNotNull('category')->get() as $problem) {
            $value = $typeByName->get(trim($problem->category));
            if ($value) {
                $problem->update(['problem_type_id' => $value->id]);
            }
        }
    }

    public function down(): void
    {
        DB::table('problems')->update(['problem_type_id' => null, 'problem_nature_id' => null]);
        ClassifierValue::query()->delete();
        ClassifierCategory::query()->delete();
    }
};
