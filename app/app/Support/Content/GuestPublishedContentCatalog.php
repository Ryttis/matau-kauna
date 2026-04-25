<?php

namespace App\Support\Content;

use App\Models\ContentVersion;

final class GuestPublishedContentCatalog
{
    private static function definitionsByDatabaseType(): array
    {
        static $cache = null;

        return $cache ??= [
            ContentVersion::TYPE_TERMS => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_TERMS,
                'Guest/Terms',
                'Taisyklės',
            ),
            ContentVersion::TYPE_FAQ => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_FAQ,
                'Guest/Faq',
                'DUK',
            ),
            ContentVersion::TYPE_PRIVACY => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_PRIVACY,
                'Guest/PrivacyPolicy',
                'Privatumo politika',
            ),
            ContentVersion::TYPE_COOKIES => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_COOKIES,
                'Guest/CookiePolicy',
                'Slapukų informacija',
            ),
            ContentVersion::TYPE_CONTACTS => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_CONTACTS,
                'Guest/Contacts',
                'Kontaktai',
            ),
            ContentVersion::TYPE_HELP => new GuestPublishedContentDefinition(
                ContentVersion::TYPE_HELP,
                'Guest/Help',
                'Pagalba',
            ),
        ];
    }

    public static function definitionForGuestPage(string $databaseType): ?GuestPublishedContentDefinition
    {
        return self::definitionsByDatabaseType()[$databaseType] ?? null;
    }

    public static function databaseTypesServedAsPublicJson(): array
    {
        return array_keys(self::definitionsByDatabaseType());
    }

    public static function publicJsonRoutePattern(): string
    {
        return implode('|', self::databaseTypesServedAsPublicJson());
    }
}
