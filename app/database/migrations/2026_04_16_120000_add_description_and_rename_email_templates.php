<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('email_notification_templates', 'description')) {
            Schema::table('email_notification_templates', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        $rows = [
            'account_created' => [
                'name' => 'Administratoriaus sukurta paskyra',
                'description' => 'Kai administratorius sukuria naują naudotoją, adresu siunčiamas pranešimas su prisijungimo duomenimis ir nuoroda į prisijungimo puslapį.',
            ],
            'deadline_approaching' => [
                'name' => 'Priminimas prieš atlikimo terminą',
                'description' => 'Kai artėja problemos atlikimo terminas, atsakingiems naudotojams išsiunčiamas priminimas su problemos nuoroda. (Automatizacija)',
            ],
            'deadline_overdue' => [
                'name' => 'Priminimas po atlikimo termino',
                'description' => 'Kai atlikimo terminas jau praėjęs, išsiunčiamas priminimas apie vėlavimą su problemos nuoroda. (Automatizacija)',
            ],
            'employee_assignment' => [
                'name' => 'Darbuotojo priskyrimo pranešimas',
                'description' => 'Kai problemai priskiriamas įstaigos darbuotojas arba komanda, darbuotojui siunčiama trumpa santrauka ir nuoroda į problemą sistemoje.',
            ],
            'inactive_reporter_reminder' => [
                'name' => 'Neaktyvios pareiškėjo paskyros perspėjimas',
                'description' => 'Kai pareiškėjas ilgai neprisijungia, pagal automatizacijos puslapyje nustatytas taisykles išsiunčiamas perspėjimas su galima paskyros pašalinimo data ir prisijungimo nuoroda. (Automatizacija)',
            ],
            'institution_assignment' => [
                'name' => 'Naujos problemos įstaigos administratoriams',
                'description' => 'Kai įstaigai priskiria naujas problemas arba problemą perduoda kitai įstaigai, sistema praneša įstaigos administratoriams, kurie nori gauti tokius laiškus. Laiške pateikiamas priskirtų problemų sąrašas su nuorodomis.',
            ],
            'institution_invitation' => [
                'name' => 'Pakvietimas į įstaigos paskyrą',
                'description' => 'Kai siunčiamas pakvietimas naujam įstaigos naudotojui, laiške yra registracijos nuoroda, rolė, įstaigos pavadinimas ir kvietimo galiojimo laikas.',
            ],
            'institution_problem_share_email' => [
                'name' => 'Problemos skyrimas el. paštu',
                'description' => 'Vienos problemos santrauka rankiniu būdu siunčiama į bet kurį nurodytą el. paštą priskyrimo įstaigai puslapyje. Tai tik informacija, be papildomo automatinio veiksmo.',
            ],
            'manual_assign_to_institution_email' => [
                'name' => 'Masinis laiškas ir įstaigos priskyrimas',
                'description' => 'Masiniame priskyrimo puslapyje pasirinkus problemas ir įstaigos kontaktinį paštą, išsiunčiama santrauka ir tos problemos iš karto priskiria pasirinktai įstaigai.',
            ],
            'password_reset' => [
                'name' => 'Slaptažodžio atkūrimas',
                'description' => 'Kai naudotojas prašo atkurti slaptažodį, siunčiamas laiškas su laikina nuoroda, galiojančia ribotą laiką.',
            ],
            'reporter_review_entry' => [
                'name' => 'Pareiškėjui viešas nagrinėjimo įrašas',
                'description' => 'Kai prie problemos pridedamas viešas nagrinėjimo įrašas, pareiškėjui siunčiamas pranešimas su trumpa informacija ir nuoroda į problemą savo srityje.',
            ],
            'reporter_status_change' => [
                'name' => 'Pareiškėjui problemos būsenos keitimas',
                'description' => 'Kai darbuotojų aplinkoje keičiama problemos būsena, pareiškėjui siunčiamas pranešimas su sena ir nauja būsena bei nuoroda į problemą.',
            ],
            'verify_email_address' => [
                'name' => 'El. pašto patvirtinimas',
                'description' => 'Registracijos ar paskyros patvirtinimo metu siunčiamas laiškas su nuoroda, kad būtų patvirtintas el. pašto adresas.',
            ],
        ];

        foreach ($rows as $key => $data) {
            DB::table('email_notification_templates')
                ->where('key', $key)
                ->update([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('email_notification_templates', 'description')) {
            Schema::table('email_notification_templates', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
