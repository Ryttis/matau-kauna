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
        $sortOrder = 0;
        $typeIds = [];

        foreach (array_keys(self::NATURES_BY_TYPE) as $name) {
            $type = ClassifierValue::updateOrCreate(
                [
                    'parent_id' => null,
                    'name' => $name,
                ],
                [
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                    'archived_at' => null,
                ]
            );
            $typeIds[$type->name] = $type->id;
        }

        foreach ($typeIds as $typeName => $typeId) {
            $names = self::NATURES_BY_TYPE[$typeName] ?? self::FALLBACK_NATURES;
            $sortOrder = 0;

            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }

                ClassifierValue::updateOrCreate(
                    [
                        'parent_id' => $typeId,
                        'name' => $name,
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => $sortOrder++,
                        'archived_at' => null,
                    ]
                );
            }
        }
    }
}
