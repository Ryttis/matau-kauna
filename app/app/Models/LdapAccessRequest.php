<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdapAccessRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys(['first_seen_at', 'last_seen_at', 'approved_at', 'rejected_at'], 'datetime');
    }
}
