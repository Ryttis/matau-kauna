<?php

use App\Models\EmailNotificationTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        $defaults = [
            [
                'key' => EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT,
                'name' => 'Įstaigai priskirtos problemos',
                'subject' => 'Naujos priskirtos problemos',
                'body' => "Jums priskirtos naujos problemos:\n{problem_list}\n\nĮstaiga: {institution_name}",
                'is_enabled' => true,
            ],
            [
                'key' => EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT,
                'name' => 'Darbuotojui priskirta problema',
                'subject' => 'Jums priskirta nauja problema',
                'body' => "Jums priskirta nauja problema:\n{internal_number} | {description_excerpt} | {url}",
                'is_enabled' => true,
            ],
            [
                'key' => EmailNotificationTemplate::KEY_DEADLINE_APPROACHING,
                'name' => 'Priminimas: artėja terminas',
                'subject' => 'Problema {internal_number} – artėja terminas',
                'body' => "Terminas išspręsti problemą: {deadline_date}.\n\n{internal_number} | {description_excerpt}\n{url}",
                'is_enabled' => true,
            ],
            [
                'key' => EmailNotificationTemplate::KEY_DEADLINE_OVERDUE,
                'name' => 'Priminimas: terminas praėjo',
                'subject' => 'Problema {internal_number} – terminas praėjo',
                'body' => "Ši problema turėjo būti išspręsta iki {deadline_date}.\n\n{internal_number} | {description_excerpt}\n{url}",
                'is_enabled' => true,
            ],
        ];

        foreach ($defaults as $row) {
            EmailNotificationTemplate::create($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_templates');
    }
};
