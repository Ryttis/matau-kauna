<?php

namespace App\Enums;

enum ProblemStatus: string
{
    case UNREGISTERED = 'neuzregistruota';
    case NEW = 'nauja';
    case ASSIGNED = 'paskirta';
    case IN_REVIEW = 'nagrinejama';
    case RETURNED_FOR_REVIEW = 'grazinta_nagrinejimui';
    case RESOLVED = 'isspresta';
    case INACTIVE = 'neaktuali';

    public function label(): string
    {
        return match ($this) {
            self::UNREGISTERED => 'Pateikta',
            self::NEW => 'Nauja',
            self::ASSIGNED => 'Paskirta',
            self::IN_REVIEW => 'Nagrinėjama',
            self::RETURNED_FOR_REVIEW => 'Grąžinta',
            self::RESOLVED => 'Išnagrinėta',
            self::INACTIVE => 'Neaktuali',
        };
    }

    /**
     * Pavadinimas išorinėms sistemoms (ArcGIS). Būsena buvo pervadinta į
     * "Išnagrinėta", bet į GIS ir toliau siunčiamas istorinis tekstas, kad
     * įstaigoms be status_value_mapping perdavimai nenustotų veikti.
     */
    public function integrationLabel(): string
    {
        return match ($this) {
            self::RESOLVED => 'Išspręsta',
            default => $this->label(),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    public static function selectableOptions(): array
    {
        $cases = array_filter(
            self::cases(),
            fn ($case) => ! in_array($case, [self::UNREGISTERED, self::ASSIGNED], true)
        );

        return array_values(array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases));
    }

    public static function valuesEligibleForManualStaffUpdate(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $v) => ! in_array($v, [
                self::NEW->value,
                self::ASSIGNED->value,
                self::RETURNED_FOR_REVIEW->value,
            ], true)
        ));
    }

    public static function labels(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }

    public static function staffMapStatusValues(): array
    {
        return [
            self::UNREGISTERED->value,
            self::NEW->value,
            self::ASSIGNED->value,
            self::IN_REVIEW->value,
            self::RETURNED_FOR_REVIEW->value,
            self::RESOLVED->value,
            self::INACTIVE->value,
        ];
    }

    public static function publicMapStatusValues(): array
    {
        return [
            self::UNREGISTERED->value,
            self::NEW->value,
            self::ASSIGNED->value,
            self::IN_REVIEW->value,
            self::RETURNED_FOR_REVIEW->value,
            self::RESOLVED->value,
            self::INACTIVE->value,
        ];
    }

    public static function reporterDashboardMapStatusValues(): array
    {
        return [
            self::UNREGISTERED->value,
            self::NEW->value,
            self::ASSIGNED->value,
            self::IN_REVIEW->value,
            self::RETURNED_FOR_REVIEW->value,
            self::RESOLVED->value,
            self::INACTIVE->value,
        ];
    }

    public static function reporterOwnedProblemsQueryStatusValues(): array
    {
        return [
            self::NEW->value,
            self::ASSIGNED->value,
            self::IN_REVIEW->value,
            self::RETURNED_FOR_REVIEW->value,
            self::RESOLVED->value,
            self::UNREGISTERED->value,
            self::INACTIVE->value,
        ];
    }

    public static function reporterStatusValue(string $status): string
    {
        return match ($status) {
            self::UNREGISTERED->value, self::NEW->value, self::RETURNED_FOR_REVIEW->value => self::NEW->value,
            self::IN_REVIEW->value, self::ASSIGNED->value => self::IN_REVIEW->value,
            self::RESOLVED->value, self::INACTIVE->value => self::RESOLVED->value,
            default => $status,
        };
    }

    public static function reporterStatusLabel(string $status): string
    {
        return match (self::reporterStatusValue($status)) {
            self::NEW->value => 'Nauja',
            self::IN_REVIEW->value => 'Nagrinėjama',
            self::RESOLVED->value => 'Išnagrinėta',
            default => self::tryFrom($status)?->label() ?? $status,
        };
    }

    public static function rawValuesForReporterStatus(string $reporterStatus): array
    {
        return match ($reporterStatus) {
            self::NEW->value => [self::UNREGISTERED->value, self::NEW->value, self::RETURNED_FOR_REVIEW->value],
            self::IN_REVIEW->value => [self::IN_REVIEW->value, self::ASSIGNED->value],
            self::RESOLVED->value => [self::RESOLVED->value, self::INACTIVE->value],
            default => [],
        };
    }

    public static function rawValuesForReporterStatuses(array $reporterStatuses): array
    {
        $raw = [];
        foreach ($reporterStatuses as $reporterStatus) {
            foreach (self::rawValuesForReporterStatus((string) $reporterStatus) as $value) {
                $raw[$value] = true;
            }
        }

        return array_keys($raw);
    }
}
