<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reporter_email_own_problems')->default(true)->after('email_notifications_enabled');
            $table->boolean('reporter_email_watched_problems')->default(true)->after('reporter_email_own_problems');
        });

        Schema::create('problem_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('problem_id')->constrained('problems')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'problem_id']);
        });

        if (! EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE)) {
            EmailNotificationTemplate::create([
                'key' => EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE,
                'name' => 'Pareiškėjui: problemos būsenos pasikeitimas',
                'subject' => 'Problema {internal_number}: būsena pakeista į „{new_status_label}“',
                'body' => "Sveiki,\n\nProblemai {internal_number} pakeista būsena.\n\nAnkstesnė būsena: {old_status_label}\nNauja būsena: {new_status_label}\n\nAprašymo fragmentas: {description_excerpt}\n\nPeržiūrėti: {url}",
                'is_enabled' => true,
            ]);
        }

        if (! EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY)) {
            EmailNotificationTemplate::create([
                'key' => EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY,
                'name' => 'Pareiškėjui: naujas viešas nagrinėjimo įrašas',
                'subject' => 'Problema {internal_number}: naujas nagrinėjimo įrašas',
                'body' => "Sveiki,\n\nProblemai {internal_number} pridėtas naujas viešas nagrinėjimo įrašas.\n\nBūsena: {status_label}\nAtsakingas: {responsible}\nData: {entry_date}\n\nKomentaras: {comment_excerpt}\n\nPeržiūrėti: {url}",
                'is_enabled' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_watches');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reporter_email_own_problems', 'reporter_email_watched_problems']);
        });

        EmailNotificationTemplate::query()
            ->whereIn('key', [
                EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE,
                EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY,
            ])
            ->delete();
    }
};
