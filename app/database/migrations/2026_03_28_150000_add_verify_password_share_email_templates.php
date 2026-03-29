<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'key' => EmailNotificationTemplate::KEY_VERIFY_EMAIL,
                'name' => 'Sistema: el. pašto patvirtinimas',
                'subject' => 'Patvirtinkite el. paštą – {app_name}',
                'body' => "Sveiki, {user_name}.\n\nUžbaikite registraciją: patvirtinkite el. pašto adresą.\n\nJei paskyros nekūrėte, ignoruokite šį laišką.",
                'is_enabled' => true,
            ],
            [
                'key' => EmailNotificationTemplate::KEY_PASSWORD_RESET,
                'name' => 'Sistema: slaptažodžio atkūrimas',
                'subject' => 'Slaptažodžio keitimas – {app_name}',
                'body' => "Sveiki, {user_name}.\n\nGavome prašymą pakeisti slaptažodį. Nuoroda galioja {expire_minutes} min.\n\nJei prašymo nesiuntėte, ignoruokite šį laišką.",
                'is_enabled' => true,
            ],
            [
                'key' => EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE,
                'name' => 'Įstaigos adminui: problemos informacija (rankinis siuntimas)',
                'subject' => 'Problemos informacija – {internal_number}',
                'body' => "Nr: {internal_number}\nAprašymas: {description_excerpt}\nAdresas: {address}\nSukūrimo data: {created_at}\n{deadline_line}",
                'is_enabled' => true,
            ],
        ];

        foreach ($rows as $row) {
            if (! EmailNotificationTemplate::getByKey($row['key'])) {
                EmailNotificationTemplate::create($row);
            }
        }
    }

    public function down(): void
    {
        EmailNotificationTemplate::query()
            ->whereIn('key', [
                EmailNotificationTemplate::KEY_VERIFY_EMAIL,
                EmailNotificationTemplate::KEY_PASSWORD_RESET,
                EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE,
            ])
            ->delete();
    }
};
