<?php

namespace Database\Seeders;

use App\Models\UserRolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'users.view' => 'Naudotojų peržiūra',
        'users.manage' => 'Naudotojų valdymas',
        'roles.view' => 'Rolių peržiūra',
        'roles.manage' => 'Rolių valdymas',
        'permissions.view' => 'Teisių peržiūra',
        'permissions.manage' => 'Teisių valdymas',
        'invitations.manage' => 'Kvietimų valdymas',
        'teams.manage' => 'Komandų valdymas',
        'automation.manage' => 'Automatizavimo valdymas',
        'issues.view' => 'Problemų peržiūra',
        'issues.edit' => 'Problemų redagavimas',
        'issues.assign' => 'Problemų priskyrimas',
        'issues.reassign' => 'Problemų perskyrimas',
        'issues.delete' => 'Problemų šalinimas',
        'issues.merge' => 'Problemų sujungimas',
        'issues.export' => 'Problemų eksportas',
        'issues.publish' => 'Problemų viešinimas',
        'classification.edit' => 'Klasifikatorių redagavimas',
        'statistics.view' => 'Statistikos peržiūra',
        'admin.access' => 'Sistemos administravimo prieiga',
        'archive.manage' => 'Archyvo valdymas',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission => $name) {
            UserRolePermission::updateOrCreate(
                ['permission' => $permission],
                ['name' => $name]
            );
        }
    }
}
