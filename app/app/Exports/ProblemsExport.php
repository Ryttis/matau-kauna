<?php

namespace App\Exports;

use App\Enums\ProblemStatus;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ProblemsExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths
{
    protected $problems;

    public function __construct($problems)
    {
        $this->problems = $problems;
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
            'Data',
            'Pavadinimas',
            'Kategorija',
            'Adresas',
            'Aprašymas',
        ];
    }

    public function map($problem): array
    {
        $statusLabels = ProblemStatus::labels();

        return [
            $problem->internal_number,
            $statusLabels[$problem->status] ?? $problem->status,
            $problem->created_at?->format('Y-m-d') ?? '',
            $problem->title,
            $problem->category,
            $problem->address,
            $problem->description,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 20,
            'C' => 12,
            'D' => 30,
            'E' => 15,
            'F' => 30,
            'G' => 50,
        ];
    }
}
