<?php

namespace App\Model;

use App\Model\Region;
use App\User;
use Illuminate\Database\Eloquent\Model;

class CustomerRegion extends Model
{
    protected $fillable = [
        'uuid',
        'customer_id',
        'zone_id',
        'region_id'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class,  'region_id', 'id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class,  'zone_id', 'id');
    }
}
