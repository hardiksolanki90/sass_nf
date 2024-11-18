<?php

namespace App\Model;

use App\User;
use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeoApproval extends Model
{

    use SoftDeletes, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'salesmana_id',
        'salesman_lat',
        'salesman_long',
        'customer_id',
        'customer_lat',
        'customer_long',
        'supervisor_id',
        'radius',
        'status',
        'date',
        'reason',
        'request_reason'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class,  'supervisor_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }
}
