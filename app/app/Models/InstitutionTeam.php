<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function employees()
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_institution_team',
            'institution_team_id',
            'employee_id'
        );
    }
}
