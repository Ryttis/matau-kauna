<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'institution_id',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function teams()
    {
        return $this->belongsToMany(
            InstitutionTeam::class,
            'employee_institution_team',
            'employee_id',
            'institution_team_id'
        );
    }
}
