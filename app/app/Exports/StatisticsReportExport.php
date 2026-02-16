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
        protected ?string $category
    ) {
    }

    public function array(): array
    {
        $a = $this->analytics;
        $periodLabel = $this->periodLabel();

        $rows = [
            ['Statistikos ataskaita'],
            ['Laikotarpis:', $periodLabel],
            ['Įstaiga:', $this->institution === 'all' || ! $this->institution ? 'Visos' : $this->institution],
            ['Kategorija:', $this->category === 'all' || ! $this->category ? 'Visos' : $this->category],
            [],
            ['Rodiklis', 'Reikšmė'],
            ['Aktyvūs naudotojai (periodas)', ($a['activeUsers']['current'] ?? 0) . ' / ' . ($a['activeUsers']['total'] ?? 0)],
            ['Gauti pranešimai', $a['receivedReports'] ?? 0],
            ['Vidutinis sprendimo laikas (d.)', $a['averageResolutionTime'] ?? 0],
            ['Išspręstos problemos (%)', $a['resolvedProblems'] ?? 0],
            ['Laiku išspręstos (%)', $a['resolvedOnTime'] ?? 0],
            ['Pagerėjimas (%)', $a['improvement'] ?? 0],
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
        $rows[] = ['Įstaiga', 'Išspręsta', 'Iš viso'];
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
