<?php

namespace App\Model;
use App\Traits\Organisationid;  
use App\Model\Organisation;
use App\Model\CustomerInfo; 
use App\Model\SalesmanInfo;
use App\Model\ReasonType;
use App\Model\Region;
use App\User;

use Illuminate\Database\Eloquent\Model;

class ReturnGrvReport extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'date', 
        'region_id', 
        'region_code', 
        'region_name',
        'customer_id',
        'customer_code',
        'customer_name',
        'qty',
        'reason_id',
        'reason_name',
        'salesman_id',
        'salesman_code',
        'salesman_name',
        'amount'
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

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

}
