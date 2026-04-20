<?php

namespace Database\Seeders;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\User;
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

        $problemsData = [
            [
                'status' => ProblemStatus::NEW->value,
                'address' => 'Karaliaus Mindaugo pr. 35, Kaunas',
                'lat' => 54.8915,
                'lng' => 23.9138,
                'category' => 'Eismas',
                'nature' => 'Šviesoforas',
                'institution_code' => 'KMS',
                'description' => 'Sankryžoje netiksliai veikia šviesoforo fazės ir susidaro spūstys piko metu.',
            ],
            [
                'status' => ProblemStatus::IN_REVIEW->value,
                'address' => 'Šiaurės pr. 12, Kaunas',
                'lat' => 54.9322,
                'lng' => 23.9507,
                'category' => 'Keliai',
                'nature' => 'Duobė',
                'institution_code' => 'KMS',
                'description' => 'Važiuojamojoje dalyje po žiemos atsivėrė gili duobė, kelianti pavojų eismui.',
            ],
            [
                'status' => ProblemStatus::IN_REVIEW->value,
                'address' => 'A. Juozapavičiaus pr. 67, Kaunas',
                'lat' => 54.8797,
                'lng' => 23.9306,
                'category' => 'Apšvietimas',
                'nature' => 'Neveikianti lempa',
                'institution_code' => 'KMS',
                'description' => 'Nakties metu neveikia trys gatvės šviestuvai iš eilės, prastas matomumas pėsčiųjų take.',
            ],
            [
                'status' => ProblemStatus::RETURNED_FOR_REVIEW->value,
                'address' => 'Partizanų g. 104, Kaunas',
                'lat' => 54.9203,
                'lng' => 23.9724,
                'category' => 'Infrastruktūra',
                'nature' => 'Komunikacijos',
                'institution_code' => 'KRATC',
                'description' => 'Po liūties šulinio dangtis pakilęs, reikalinga skubi techninė apžiūra.',
            ],
            [
                'status' => ProblemStatus::RESOLVED->value,
                'address' => 'Studentų g. 48, Kaunas',
                'lat' => 54.9049,
                'lng' => 23.9573,
                'category' => 'Parkai',
                'nature' => 'Valymas',
                'institution_code' => 'KRATC',
                'description' => 'Pažeisti suoliukai ir šiukšliadėžės sutvarkytos, paliekamas įrašas kaip išspręstas atvejis.',
            ],
        ];

        $reporter = User::where('email', 'aiste.zukaite@kaunas.lt')->first();
        $responsibleNames = ['Tomas Jankauskas', 'Rasa Vaitkevičiūtė', 'Lukas Bielskis'];
        $typeLookup = ClassifierValue::query()->whereNull('parent_id')->pluck('id', 'name');
        $institutionsByCode = Institution::query()->pluck('id', 'code');

        $comments = [
            'Užduotis priimta ir perduota atsakingam skyriui.',
            'Atlikta vietos apžiūra, renkama papildoma informacija.',
            'Vykdomi rangos darbai pagal suderintą grafiką.',
            'Darbai užbaigti, atlikta kokybės patikra.',
        ];

        foreach ($problemsData as $i => $data) {
            $daysAgo = 20 - ($i * 3);
            $createdAt = Carbon::now()->subDays($daysAgo);
            $statusUpdatedAt = $createdAt->copy()->addDays(min($daysAgo, 7));
            $problemTypeId = $typeLookup[$data['category']] ?? null;
            $problemNatureId = null;

            if ($problemTypeId) {
                $problemNatureId = ClassifierValue::query()
                    ->where('parent_id', $problemTypeId)
                    ->where('name', $data['nature'])
                    ->value('id');
            }

            $problem = Problem::create([
                'status' => $data['status'],
                'category' => $data['category'],
                'problem_type_id' => $problemTypeId,
                'problem_nature_id' => $problemNatureId,
                'address' => $data['address'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'description' => $data['description'],
                'institution_id' => $institutionsByCode[$data['institution_code']] ?? null,
                'reported_by_user_id' => $reporter?->id,
                'status_updated_at' => $statusUpdatedAt,
            ]);

            $problem->created_at = $createdAt;
            $problem->updated_at = $createdAt;
            $problem->saveQuietly();

            $historyCount = 2;
            for ($j = 0; $j < $historyCount; $j++) {
                $historyDate = $createdAt->copy()
                    ->addDays($j + 1)
                    ->addHours(9 + $j)
                    ->addMinutes(15);

                ProblemReviewHistory::create([
                    'problem_id' => $problem->id,
                    'status' => $data['status'],
                    'status_changed' => true,
                    'responsible' => $responsibleNames[array_rand($responsibleNames)],
                    'comment' => $comments[array_rand($comments)],
                    'visibility' => $j === 0 ? 'internal' : 'public',
                    'created_at' => $historyDate,
                    'updated_at' => $historyDate,
                ]);
            }
        }
    }
}
