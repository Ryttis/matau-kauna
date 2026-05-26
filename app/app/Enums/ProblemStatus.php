<?php

namespace App\Enums;

enum ProblemStatus: string
{
    case UNREGISTERED = 'neuzregistruota';
    case NEW = 'nauja';
    case IN_REVIEW = 'nagrinejama';
    case RETURNED_FOR_REVIEW = 'grazinta_nagrinejimui';
    case RESOLVED = 'isspresta';
    case INACTIVE = 'neaktuali';

    public function label(): string
    {
        return match ($this) {
            self::UNREGISTERED => 'Pateikta',
            self::NEW => 'Nauja',
            self::IN_REVIEW => 'Nagrinėjama',
            self::RETURNED_FOR_REVIEW => 'Grąžinta nagrinėjimui',
            self::RESOLVED => 'Išspręsta',
            self::INACTIVE => 'Neaktuali',
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
        $cases = array_filter(self::cases(), fn ($case) => $case !== self::UNREGISTERED);

        return array_values(array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases));
    }

    public static function valuesEligibleForManualStaffUpdate(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $v) => $v !== self::NEW->value
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
            self::IN_REVIEW->value,
            self::RETURNED_FOR_REVIEW->value,
            self::RESOLVED->value,
            self::INACTIVE->value,
        ];
    }

    public static function publicMapStatusValues(): array
    {
        return [
            self::IN_REVIEW->value,
            self::RESOLVED->value,
        ];
    }

    public static function reporterDashboardMapStatusValues(): array
    {
        return [
            self::UNREGISTERED->value,
            self::NEW->value,
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
            self::UNREGISTERED->value, self::NEW->value => self::NEW->value,
            self::IN_REVIEW->value, self::RETURNED_FOR_REVIEW->value => self::IN_REVIEW->value,
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
}
