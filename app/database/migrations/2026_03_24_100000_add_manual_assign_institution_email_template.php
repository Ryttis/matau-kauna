<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL)) {
            return;
        }

        EmailNotificationTemplate::create([
            'key' => EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL,
            'name' => 'Įstaigai: el. paštas iš priskyrimo',
            'subject' => 'Problemų priskyrimo informacija',
            'body' => "Priskirtų problemų sąrašas:\n\n{problem_list}\n\nPriskirta įstaiga: {institution_name}",
            'is_enabled' => true,
        ]);
    }

    public function down(): void
    {
        EmailNotificationTemplate::query()
            ->where('key', EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL)
            ->delete();
    }
};
