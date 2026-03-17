<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER)) {
            return;
        }
        EmailNotificationTemplate::create([
            'key' => EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER,
            'name' => 'Priminimas: neaktyvi pareiškėjo paskyra bus ištrinta',
            'subject' => 'Jūsų paskyra bus ištrinta dėl neaktyvumo',
            'body' => "Sveiki, {user_name}.\n\nJūsų paskyra nebuvo naudojama daugiau nei {inactivity_days} dienų. Paskyra bus automatiškai ištrinta {deletion_date}, jei neprisijungsite.\n\nJei norite išlaikyti paskyrą, prisijunkite prie sistemos.",
            'is_enabled' => true,
        ]);
    }

    public function down(): void
    {
        EmailNotificationTemplate::query()
            ->where('key', EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER)
            ->delete();
    }
};
