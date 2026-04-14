<?php

namespace Database\Seeders;

use App\Enums\ProblemStatus;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProblemSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::statement('TRUNCATE TABLE problems RESTART IDENTITY CASCADE');
        } catch (\Exception $e) {
            ProblemReviewHistory::query()->delete();
            Problem::query()->delete();
        }

        $statuses = ProblemStatus::values();

        $problemsData = [
            [
                'address' => 'Laisvės al. 1, Kaunas',
                'lat' => 54.8985,
                'lng' => 23.8863,
                'category' => 'Eismas',
                'description' => 'Neveikia šviesoforas sankryžoje',
            ],
            [
                'address' => 'Gedimino g. 5, Kaunas',
                'lat' => 54.8970,
                'lng' => 23.8850,
                'category' => 'Keliai',
                'description' => 'Didelė duobė važiuojamojoje dalyje',
            ],
            [
                'address' => 'Vilniaus g. 12, Kaunas',
                'lat' => 54.8990,
                'lng' => 23.8920,
                'category' => 'Apšvietimas',
                'description' => 'Nedega gatvės šviestuvas',
            ],
            [
                'address' => 'Kęstučio g. 25, Kaunas',
                'lat' => 54.9010,
                'lng' => 23.9080,
                'category' => 'Pėsčiųjų takai',
                'description' => 'Atskilę plytelės',
            ],
            [
                'address' => 'Vytauto pr. 45, Kaunas',
                'lat' => 54.8940,
                'lng' => 23.9180,
                'category' => 'Keliai',
                'description' => 'Po lietaus telkšo vanduo',
            ],
            [
                'address' => 'Donelaičio g. 8, Kaunas',
                'lat' => 54.8960,
                'lng' => 23.8810,
                'category' => 'Keliai',
                'description' => 'Iškilęs asfaltas kelio ruože',
            ],
            [
                'address' => 'Maironio g. 15, Kaunas',
                'lat' => 54.8975,
                'lng' => 23.8870,
                'category' => 'Infrastruktūra',
                'description' => 'Pažeistas tvoros elementas',
            ],
            [
                'address' => 'Rotušės a. 3, Kaunas',
                'lat' => 54.8972,
                'lng' => 23.8865,
                'category' => 'Parkai',
                'description' => 'Neveikia fontano sistema',
            ],
        ];

        $responsibleNames = [
            'Antanas Antanaitis',
            'Jonas Jonaitis',
            'Petras Petraitis',
        ];

        $comments = [
            'Problema perduota nagrinėjimui',
            'Reikia papildomų tyrimų',
            'Laukiama atsakymo iš atsakingos institucijos',
            'Problema išspręsta',
        ];

        foreach ($problemsData as $i => $data) {
            $daysAgo = rand(0, 30);
            $createdAt = Carbon::now()->subDays($daysAgo);
            $statusUpdatedAt = $createdAt->copy()->addDays(rand(0, min($daysAgo, 10)));

            $problem = Problem::create([
                'status' => $statuses[array_rand($statuses)],
                'category' => $data['category'],
                'address' => $data['address'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'description' => $data['description'],
                'status_updated_at' => $statusUpdatedAt,
            ]);

            $problem->created_at = $createdAt;
            $problem->updated_at = $createdAt;
            $problem->saveQuietly();

            $historyCount = rand(2, 4);
            for ($j = 0; $j < $historyCount; $j++) {
                $historyDate = $createdAt->copy()
                    ->addDays(rand(1, max(1, $daysAgo)))
                    ->addHours(rand(8, 18))
                    ->addMinutes(rand(0, 59));

                ProblemReviewHistory::create([
                    'problem_id' => $problem->id,
                    'status' => $statuses[array_rand($statuses)],
                    'status_changed' => true,
                    'responsible' => $responsibleNames[array_rand($responsibleNames)],
                    'comment' => $comments[array_rand($comments)],
                    'visibility' => rand(0, 1) === 1 ? 'public' : 'internal',
                    'created_at' => $historyDate,
                    'updated_at' => $historyDate,
                ]);
            }
        }
    }
}
