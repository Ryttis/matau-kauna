<?php

namespace Database\Seeders;

use App\Models\ClassifierValue;
use Illuminate\Database\Seeder;

class ClassifierSeeder extends Seeder
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

    public function run(): void
    {
        if (ClassifierValue::query()->exists()) {
            return;
        }

        $sortOrder = 0;
        foreach (array_keys(self::NATURES_BY_TYPE) as $name) {
            ClassifierValue::create([
                'parent_id' => null,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }

        $types = ClassifierValue::whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get();

        foreach ($types as $type) {
            $names = self::NATURES_BY_TYPE[$type->name] ?? self::FALLBACK_NATURES;
            $sortOrder = 0;
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                ClassifierValue::create([
                    'parent_id' => $type->id,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }
}
