<?php

namespace App\Exports;

use App\Enums\ProblemStatus;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProblemsExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping
{
    protected $problems;

    private array $statusLabels;

    public function __construct($problems)
    {
        $this->problems = $problems;
        $this->statusLabels = ProblemStatus::labels();
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->problems;
    }

    public function headings(): array
    {
        return [
            'Nr.',
            'Būsena',
            'Užregistruota',
            'Atnaujinta',
            'Pareiškėjas',
            'Priskirta',
            'Kategorija',
            'Pobūdis',
            'Adresas',
            'Viešai',
            'Aprašymas',
        ];
    }

    public function map($problem): array
    {
        return [
            $problem->internal_number,
            $this->statusLabels[$problem->status] ?? $problem->status,
            $problem->created_at?->format('Y-m-d') ?? '',
            $problem->status_updated_at?->format('Y-m-d H:i') ?? '',
            $this->reporterName($problem),
            $this->assignmentLabel($problem),
            $problem->category,
            $problem->problemNature?->name ?? '',
            $problem->address,
            $problem->is_published_publicly ? 'Taip' : 'Ne',
            $problem->description,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 20,
            'C' => 14,
            'D' => 18,
            'E' => 24,
            'F' => 35,
            'G' => 18,
            'H' => 22,
            'I' => 30,
            'J' => 8,
            'K' => 50,
        ];
    }

    private function reporterName($problem): string
    {
        $reporter = $problem->reportedByUser;
        if (! $reporter) {
            return '';
        }

        return $this->personName($reporter);
    }

    private function assignmentLabel($problem): string
    {
        $parts = [
            trim((string) ($problem->institution?->name ?? '')),
            $problem->assignedEmployee ? $this->personName($problem->assignedEmployee) : '',
            trim((string) ($problem->assignedInstitutionTeam?->name ?? '')),
        ];

        return implode(' / ', array_filter($parts, fn ($part) => $part !== ''));
    }

    private function personName($person): string
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) ($person->name ?? '')),
            trim((string) ($person->surname ?? '')),
        ], fn ($part) => $part !== '')));

        return $fullName !== '' ? $fullName : trim((string) ($person->email ?? ''));
    }
}
