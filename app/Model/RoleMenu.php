<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class RoleMenu extends Model
{
    protected $fillable = [
        'uuid', 'organisation_id', 'name'
    ];
}
