<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class StatisticsReportExport implements FromArray, WithColumnWidths
{
    public function __construct(
        protected array $analytics,
        protected string $period,
        protected ?string $institution,
        protected ?string $category,
        protected ?string $institutionAssignmentLabel = null,
    ) {}

    public function array(): array
    {
        $a = $this->analytics;
        $periodLabel = $this->periodLabel();

        $rows = [
            ['Statistikos ataskaita'],
            ['Laikotarpis:', $periodLabel],
            ['Įstaiga:', $this->institution === 'all' || ! $this->institution ? 'Visos' : $this->institution],
            ['Įstaigos priskyrimas:', $this->institutionAssignmentLabel ?? 'Visi'],
            ['Kategorija:', $this->category === 'all' || ! $this->category ? 'Visos' : $this->category],
            [],
            ['Rodiklis', 'Reikšmė'],
            ['Unikalūs pranešėjai', ($a['activeUsers']['current'] ?? 0).' / '.($a['activeUsers']['total'] ?? 0)],
            ['Gauti pranešimai', $a['receivedReports'] ?? 0],
            ['Vidutinis sprendimo laikas', ($a['averageResolutionTime'] ?? 0).' d.'],
            ['Išnagrinėtos problemos', ($a['resolvedProblems'] ?? 0).'%'],
            ['Laiku išnagrinėtos', ($a['resolvedOnTime'] ?? 0).'%'],
            ['Pagerėjimas', (($a['improvement'] ?? 0) >= 0 ? '+' : '').($a['improvement'] ?? 0).'%'],
        ];

        $rows[] = [];
        $rows[] = ['Probleminės kategorijos', 'Skaičius'];
        foreach ($a['problematicCategories'] ?? [] as $c) {
            $rows[] = [$c['name'], $c['count'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Efektyviausiai sprendžiamos', 'Skaičius'];
        foreach ($a['effectivelyResolved'] ?? [] as $c) {
            $rows[] = [$c['name'], $c['count'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Darbuotojų aktyvumas', 'Problemų sk.'];
        foreach ($a['employeeActivity'] ?? [] as $e) {
            $rows[] = [$e['name'], $e['count'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Įstaiga', 'Išnagrinėta', 'Iš viso'];
        foreach ($a['institutionResults'] ?? [] as $i) {
            $rows[] = [$i['name'], $i['resolved'] ?? 0, $i['total'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Mėnuo', 'Pranešimų sk.'];
        foreach ($a['monthlyReports'] ?? [] as $m) {
            $rows[] = [$m['label'], $m['value'] ?? 0];
        }

        return $rows;
    }

    private function periodLabel(): string
    {
        return match ($this->period) {
            'week' => 'Paskutinė savaitė',
            'month' => 'Paskutinis mėnuo',
            'quarter' => 'Paskutinis ketvirtis',
            'year' => 'Paskutiniai metai',
            default => 'Visas laikotarpis',
        };
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35,
            'B' => 20,
            'C' => 15,
        ];
    }
}
