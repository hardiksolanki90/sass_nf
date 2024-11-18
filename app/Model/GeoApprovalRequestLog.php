<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeoApprovalRequestLog extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'geo_approval_id',
        'salesman_id',
        'supervisor_id',
        'customer_id',
        'salesman_lat',
        'salesman_long',
        'request_approval_id',
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
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function approvalUser()
    {
        return $this->belongsTo(User::class, 'request_approval_id', 'id');
    }
}
