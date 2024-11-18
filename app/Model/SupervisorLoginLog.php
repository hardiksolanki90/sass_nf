<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\User;

class SupervisorLoginLog extends Model
{
    use SoftDeletes, Organisationid;
    
    protected $fillable = [
        'user_id', 'ip', 'device_token', 'vesion', 'device_name', 'imei_number'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,  'user_id', 'id');
    }
}