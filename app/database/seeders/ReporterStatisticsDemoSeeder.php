<?php

namespace Database\Seeders;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ReporterStatisticsDemoSeeder extends Seeder
{
    private const REPORTERS = [
        [
            'name' => 'Greta',
            'surname' => 'Petrauskaitė',
            'email' => 'greta.petrauskaite+reporter@kaunas.lt',
            'password' => 'Admin123',
            'problems_count' => 4,
        ],
        [
            'name' => 'Dovydas',
            'surname' => 'Mikalauskas',
            'email' => 'dovydas.mikalauskas+reporter@kaunas.lt',
            'password' => 'Admin123',
            'problems_count' => 6,
        ],
    ];

    private const ISSUE_TEMPLATES = [
        'Šaligatvio plytelės išsikraipiusios, vaikštant kliūna vežimėlių ratukai.',
        'Pėsčiųjų perėjoje ženklas užstotas medžio šakomis, prastas matomumas.',
        'Parko take po lietaus nuolat telkšo vanduo ir sunku praeiti.',
        'Naktį neveikia keli šviestuvai, atkarpa visiškai tamsi.',
        'Dviračių tako danga įtrūkusi, vietomis susidarė pavojingi nelygumai.',
        'Gatvės šulinio dangtis kliba ir važiuojant girdisi smūgiai.',
        'Sankryžoje per trumpa žalio signalo fazė pėstiesiems piko metu.',
        'Prie stotelės pažeistas bortelis, sudėtinga įlipti su vežimėliu.',
        'Skvere perpildytos šiukšliadėžės, aplink kaupiasi šiukšlės.',
        'Po remonto liko nepažymėta kliūtis tako pakraštyje.',
        'Prie mokyklos perėjoje nutrintas horizontalus ženklinimas.',
        'Lietaus nuotekų grotelės užsikimšusios, po liūties patvinsta gatvė.',
        'Šviesoforo mygtukas pėstiesiems neveikia iš pirmo karto.',
        'Parke suoliukas sulūžęs, lentos atsikabinusios ir nesaugios.',
        'Šaligatvyje atsivėrė duobė ties įvažiavimu į kiemą.',
        'Dviračių pervažoje trūksta aiškaus ženklinimo automobilininkams.',
    ];

    public function run(): void
    {
        $reporterRole = UserRole::query()
            ->where('role', UserRole::ROLE_REPORTER)
            ->first();

        if (! $reporterRole) {
            $this->command?->warn('ReporterStatisticsDemoSeeder: nerasta ROLE_REPORTER, praleidžiama.');

            return;
        }

        $institutions = Institution::query()
            ->notArchived()
            ->where('is_active', true)
            ->get(['id']);

        $problemTypes = ClassifierValue::query()
            ->notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($institutions->isEmpty() || $problemTypes->isEmpty()) {
            $this->command?->warn('ReporterStatisticsDemoSeeder: trūksta aktyvių įstaigų arba problemų tipų, praleidžiama.');

            return;
        }

        $naturesByTypeId = ClassifierValue::query()
            ->notArchived()
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $shuffledTemplates = collect(self::ISSUE_TEMPLATES)->shuffle()->values();
        $templateOffset = 0;

        foreach (self::REPORTERS as $reporterData) {
            $reporter = $this->upsertReporter($reporterData, $reporterRole->id);
            $this->seedReporterProblems(
                $reporter,
                (int) $reporterData['problems_count'],
                $institutions,
                $problemTypes,
                $naturesByTypeId,
                $shuffledTemplates,
                $templateOffset
            );
            $templateOffset += (int) $reporterData['problems_count'];
        }
    }

    private function upsertReporter(array $data, string $roleId): User
    {
        /** @var User $user */
        $user = User::query()->firstOrNew(['email' => $data['email']]);
        $user->name = $data['name'];
        $user->surname = $data['surname'];
        $user->password = $data['password'];
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->terms_accepted_at = $user->terms_accepted_at ?? now();
        $user->reporter_report_terms_accepted_at = $user->reporter_report_terms_accepted_at ?? now();
        $user->reporter_wizard_completed_at = $user->reporter_wizard_completed_at ?? now();
        $user->save();
        $user->roles()->syncWithoutDetaching([$roleId]);

        return $user;
    }

    private function seedReporterProblems(
        User $reporter,
        int $count,
        Collection $institutions,
        Collection $problemTypes,
        Collection $naturesByTypeId,
        Collection $templates,
        int $offset
    ): void {
        Problem::query()->where('reported_by_user_id', $reporter->id)->delete();

        $statuses = [
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
            ProblemStatus::RETURNED_FOR_REVIEW->value,
            ProblemStatus::RESOLVED->value,
        ];

        for ($i = 0; $i < $count; $i++) {
            $template = $templates->get($offset + $i) ?? self::ISSUE_TEMPLATES[array_rand(self::ISSUE_TEMPLATES)];
            $problemType = $problemTypes->random();
            $nature = $this->pickNature($naturesByTypeId, (int) $problemType->id);
            $institution = $institutions->random();
            $status = $statuses[array_rand($statuses)];

            $daysAgo = random_int(2, 170);
            $createdAt = Carbon::now()->subDays($daysAgo)->addMinutes(random_int(0, 1400));
            $updatedAt = (clone $createdAt)->addDays(random_int(0, 12));
            $completionDeadline = null;
            if (random_int(0, 1) === 1) {
                $completionDeadline = (clone $createdAt)->addDays(random_int(5, 30))->toDateString();
            }
            if ($status === ProblemStatus::RESOLVED->value && $completionDeadline === null) {
                $completionDeadline = (clone $createdAt)->addDays(random_int(5, 20))->toDateString();
            }

            $problem = Problem::query()->create([
                'status' => $status,
                'category' => $problemType->name,
                'problem_type_id' => $problemType->id,
                'problem_nature_id' => $nature?->id,
                'address' => sprintf('Kaunas, Testo g. %d', random_int(1, 220)),
                'lat' => 54.89 + (random_int(-80, 80) / 1000),
                'lng' => 23.90 + (random_int(-80, 80) / 1000),
                'description' => $template,
                'institution_id' => $institution->id,
                'reported_by_user_id' => $reporter->id,
                'completion_deadline' => $completionDeadline,
                'status_updated_at' => $updatedAt,
                'is_published_publicly' => random_int(0, 1) === 1,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $problem->saveQuietly();
        }
    }

    private function pickNature(Collection $naturesByTypeId, int $typeId): ?ClassifierValue
    {
        $natures = $naturesByTypeId->get($typeId);
        if (! $natures || $natures->isEmpty()) {
            return null;
        }

        return $natures->random();
    }
}
