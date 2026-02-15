<?php

use App\Models\ClassifierCategory;
use App\Models\ClassifierValue;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const NATURES_BY_TYPE = [
        'Apšvietimas' => ['Neveikianti lempa', 'Sulūžęs stulpas', 'Trūksta apšvietimo', 'Kiti'],
        'Eismas' => ['Ženklai', 'Šviesoforas', 'Perėja', 'Kiti'],
        'Infrastruktūra' => ['Komunikacijos', 'Vandentiekis', 'Elektrinė', 'Kiti'],
        'Keliai' => ['Duobė', 'Danga', 'Bordiūras', 'Šaligatvis', 'Kiti'],
        'Parkai' => ['Žolė', 'Krūmai ir medžiai', 'Takas', 'Valymas', 'Kiti'],
        'Pėsčiųjų takai' => ['Danga', 'Trūksta', 'Kiti'],
    ];

    private const FALLBACK_NATURES = ['Defektas', 'Tvarkymas', 'Kita'];

    public function up(): void
    {
        $rusis = ClassifierCategory::where('slug', 'rusis')->first();
        $pobudis = ClassifierCategory::where('slug', 'pobudis')->first();
        if (! $rusis || ! $pobudis) {
            return;
        }

        ClassifierValue::where('classifier_category_id', $pobudis->id)
            ->whereNull('parent_id')
            ->delete();

        $types = ClassifierValue::where('classifier_category_id', $rusis->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($types as $type) {
            $typeName = trim($type->name);
            $names = self::NATURES_BY_TYPE[$typeName] ?? self::FALLBACK_NATURES;

            $existingNames = $type->children()->pluck('name')->map(fn ($n) => trim($n))->filter()->all();
            $sortOrder = (int) $type->children()->max('sort_order');

            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '' || in_array($name, $existingNames, true)) {
                    continue;
                }
                ClassifierValue::create([
                    'classifier_category_id' => $pobudis->id,
                    'parent_id' => $type->id,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => ++$sortOrder,
                ]);
                $existingNames[] = $name;
            }
        }
    }

    public function down(): void
    {
        $pobudis = ClassifierCategory::where('slug', 'pobudis')->first();
        if (! $pobudis) {
            return;
        }

        ClassifierValue::where('classifier_category_id', $pobudis->id)
            ->whereNotNull('parent_id')
            ->delete();
    }
};
