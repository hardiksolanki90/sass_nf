<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Model\SalesmanInfo;
use App\Model\CustomerInfo;
use App\User;

class MerchandiserGeoApproval extends Model
{
    protected $fillable = [
        'uuid', 'merchandiser_id', 'customer_id', 'date'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function merchandiser()
    {
        return $this->belongsTo(SalesmanInfo::class,  'merchandiser_id', 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(CustomerInfo::class,  'merchandiser_id', 'user_id');
    }

    public function approvalUser()
    {
        return $this->belongsTo(User::class,  'approval_user_id', 'id');
    }
}
