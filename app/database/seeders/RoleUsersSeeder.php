<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleUsersSeeder extends Seeder
{
    /**
     * Created 5 roles:
     * SAdmin: Sistemos administratorius
     * PAdmin: Problemų administratorius
     * IAdmin: Įstaigos administratorius
     * IDarbuotojas: Įstaigos darbuotojas
     * SAtstovas: Savivaldybės atstovas
     */
    public function run(): void
    {
        DB::table('users')->truncate();

        $users = [
            [
                'email' => 'sadmin@test.lt',
                'name' => 'SAdmin',
                'password' => 'Admin123',
                'role' => UserRole::ROLE_SYSTEM_ADMIN,
            ],
            [
                'email' => 'padmin@test.lt',
                'name' => 'PAdmin',
                'password' => 'Admin123',
                'role' => UserRole::ROLE_PROBLEM_ADMIN,
            ],
            [
                'email' => 'iadmin@test.lt',
                'name' => 'IAdmin',
                'password' => 'Admin123',
                'role' => UserRole::ROLE_INSTITUTION_ADMIN,
            ],
            [
                'email' => 'idarbuotojas@test.lt',
                'name' => 'IDarbuotojas',
                'password' => 'Admin123',
                'role' => UserRole::ROLE_EMPLOYEE,
            ],
            [
                'email' => 'satstovas@test.lt',
                'name' => 'SAtstovas',
                'password' => 'Admin123',
                'role' => UserRole::ROLE_MUNICIPALITY_REP,
            ],
        ];

        foreach ($users as $data) {
            $roleConstant = $data['role'];
            unset($data['role']);

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                ]
            );

            $role = UserRole::where('role', $roleConstant)->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }
    }
}
