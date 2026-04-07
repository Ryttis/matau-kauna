<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EmailNotificationTemplate::create([
            'key' => EmailNotificationTemplate::KEY_ACCOUNT_CREATED,
            'name' => 'Paskyra sukurta (administratoriaus)',
            'subject' => 'Jūsų paskyra sukurta – {app_name}',
            'body' => "Sveiki, {user_name}!\n\nJūsų paskyra sistemoje {app_name} buvo sukurta administratoriaus.\n\nPrisijungimo el. paštas: {user_email}\n\nPrisijunkite paspaudę žemiau esantį mygtuką ir, jei reikia, pasikeiskite slaptažodį.",
            'is_enabled' => true,
        ]);
    }

    public function down(): void
    {
        EmailNotificationTemplate::where('key', EmailNotificationTemplate::KEY_ACCOUNT_CREATED)->delete();
    }
};
