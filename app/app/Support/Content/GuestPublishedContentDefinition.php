<?php

namespace App\Support\Content;

final readonly class GuestPublishedContentDefinition
{
    public function __construct(
        public string $databaseType,
        public string $inertiaView,
        public string $fallbackTitle,
    ) {}
}
