<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_INVITATION)) {
            return;
        }

        EmailNotificationTemplate::create([
            'key' => EmailNotificationTemplate::KEY_INSTITUTION_INVITATION,
            'name' => 'Įstaigos naudotojo pakvietimas',
            'subject' => 'Pakvietimas į {app_name}',
            'body' => "Sveiki.\n\nJūs pakviesti prisijungti prie {app_name} kaip {role_name} įstaigoje „{institution_name}“.\n\nNuoroda galioja iki {expires_at}.",
            'is_enabled' => true,
        ]);
    }

    public function down(): void
    {
        EmailNotificationTemplate::query()
            ->where('key', EmailNotificationTemplate::KEY_INSTITUTION_INVITATION)
            ->delete();
    }
};
