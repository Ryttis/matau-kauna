<?php

namespace Database\Seeders;

use App\Enums\ProblemStatus;
use App\Models\Problem;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ProblemSeeder extends Seeder
{
    public function run(): void
    {
        try {
            \DB::statement('TRUNCATE TABLE problems RESTART IDENTITY CASCADE');
        } catch (\Exception $e) {
            Problem::query()->delete();
        }

        $statuses = ProblemStatus::values();

        $categories = [
            'Eismas',
            'Keliai',
            'Apšvietimas',
            'Infrastruktūra',
            'Pėsčiųjų takai',
            'Aplinka',
            'Viešoji tvarka',
            'Parkai',
        ];

        $addresses = [
            'Laisvės al. 1, Kaunas',
            'Gedimino g. 5, Kaunas',
            'Vilniaus g. 12, Kaunas',
            'Kęstučio g. 25, Kaunas',
            'Vytauto pr. 45, Kaunas',
            'Donelaičio g. 8, Kaunas',
            'Maironio g. 15, Kaunas',
            'Rotušės a. 3, Kaunas',
            'Nepriklausomybės a. 12, Kaunas',
            'K. Donelaičio g. 62, Kaunas',
        ];

        $titles = [
            'Sugedęs šviesoforas',
            'Duobė kelyje',
            'Neveikiantis apšvietimas',
            'Užtvindyta gatvė',
            'Sugedęs šaligatvis',
            'Nelegalus šiukšlynas',
            'Triukšmas naktį',
            'Sugedęs suoliukas',
            'Sulūžęs stulpas',
            'Pažeista žolė',
            'Neveikiantis fontanas',
            'Sugedęs tvoros elementas',
            'Užterštas vanduo',
            'Pažeistas asfaltas',
            'Neveikiantis šildymas',
        ];

        $descriptions = [
            'Neveikia šviesoforas sankryžoje',
            'Didelė duobė važiuojamojoje dalyje',
            'Nedega gatvės šviestuvas',
            'Po lietaus telkšo vanduo',
            'Atskilę plytelės',
            'Paliktos statybinės atliekos',
            'Triukšmingi renginiai po 23 val.',
            'Sulūžusi sėdimoji dalis',
            'Nulūžęs apšvietimo stulpas',
            'Išrausta žolė parke',
            'Neveikia fontano sistema',
            'Pažeistas tvoros elementas',
            'Užterštas vanduo baseine',
            'Iškilęs asfaltas kelio ruože',
            'Neveikia šildymo sistema',
        ];

        for ($i = 0; $i < 25; $i++) {
            $daysAgo = rand(0, 30);
            $createdAt = Carbon::now()->subDays($daysAgo);
            $statusUpdatedAt = $createdAt->copy()->addDays(rand(0, min($daysAgo, 10)));

            $problem = new Problem([
                'status' => $statuses[array_rand($statuses)],
                'title' => $titles[array_rand($titles)],
                'category' => $categories[array_rand($categories)],
                'address' => $addresses[array_rand($addresses)],
                'description' => $descriptions[array_rand($descriptions)],
                'status_updated_at' => $statusUpdatedAt,
            ]);

            $problem->created_at = $createdAt;
            $problem->updated_at = $createdAt;
            $problem->save();
        }
    }
}
