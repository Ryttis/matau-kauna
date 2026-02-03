<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            [
                'name' => 'Kauno miesto savivaldybė',
                'code' => 'KMS',
                'type' => 'savivaldybė',
                'contact_phone' => '+370 37 409 000',
                'contact_email' => 'info@kaunas.lt',
                'contact_address' => 'Laisvės al. 96, Kaunas',
                'integration_email' => 'problemos@kaunas.lt',
                'integration_dvs' => null,
                'integration_gis' => null,
                'is_active' => true,
                'is_email_only' => false,
            ],
            [
                'name' => 'Kauno regiono atliekų tvarkymo centras',
                'code' => 'KRATC',
                'type' => 'įmonė',
                'contact_phone' => '+370 37 123 456',
                'contact_email' => 'info@kratc.lt',
                'contact_address' => 'Pramonės g. 10, Kaunas',
                'integration_email' => 'uzklausos@kratc.lt',
                'integration_dvs' => null,
                'integration_gis' => null,
                'is_active' => true,
                'is_email_only' => false,
            ],
        ];

        foreach ($institutions as $data) {
            Institution::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
