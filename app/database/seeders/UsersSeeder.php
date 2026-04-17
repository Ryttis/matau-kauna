<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    private const DEFAULT_PASSWORD_ENV = 'SEED_DEFAULT_PASSWORD';

    public const USERS = [
        [
            'name' => 'Mantas Kazlauskas',
            'email' => 'mantas.kazlauskas@kaunas.lt',
            'password' => 'Admin123',
        ],
        [
            'name' => 'Ieva Petrauskienė',
            'email' => 'ieva.petrauskiene@kaunas.lt',
            'password' => 'Admin123',
        ],
        [
            'name' => 'Tomas Jankauskas',
            'email' => 'tomas.jankauskas@kaunas.lt',
            'password' => 'Admin123',
        ],
        [
            'name' => 'Rasa Vaitkevičiūtė',
            'email' => 'rasa.vaitkeviciute@kaunas.lt',
            'password' => 'Admin123',
        ],
        [
            'name' => 'Lukas Bielskis',
            'email' => 'lukas.bielskis@kaunas.lt',
            'password' => 'Admin123',
        ],
        [
            'name' => 'Aistė Žukaitė',
            'email' => 'aiste.zukaite@kaunas.lt',
            'password' => 'Admin123',
        ],
    ];

    public function run(): void
    {
        $defaultPassword = (string) env(self::DEFAULT_PASSWORD_ENV, 'Admin123');

        foreach (self::USERS as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->email_verified_at = $user->email_verified_at ?? Carbon::now();

            if (! $user->exists) {
                $password = $data['password'] ?: $defaultPassword;
                $user->password = Hash::make($password);
            }

            $user->save();
        }
    }
}
