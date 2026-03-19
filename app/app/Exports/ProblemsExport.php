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
            'Data',
            'Kategorija',
            'Adresas',
            'Aprašymas',
        ];
    }

    public function map($problem): array
    {
        return [
            $problem->internal_number,
            $this->statusLabels[$problem->status] ?? $problem->status,
            $problem->created_at?->format('Y-m-d') ?? '',
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
            'D' => 15,
            'E' => 30,
            'F' => 50,
        ];
    }
}
