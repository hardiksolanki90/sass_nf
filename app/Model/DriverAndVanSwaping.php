<?php

namespace App\Model;

use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\Van;
use App\Model\Delivery;
use App\User;
use App\Model\Route;
use Illuminate\Database\Eloquent\Model;

class DriverAndVanSwaping extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'order_id',
        'new_salesman_id',
        'old_salesman_id',
        'old_van_id',
        'new_van_id',
        'route_id',
        'reason_id',
        'date',
        'login_user_id'
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

    public function newSalesman()
    {
        return $this->belongsTo(User::class,  'new_salesman_id', 'id');
    }

    public function oldSalesman()
    {
        return $this->belongsTo(User::class,  'old_salesman_id', 'id');
    }

    public function oldVan()
    {
        return $this->belongsTo(Van::class,  'old_van_id', 'id');
    }

    public function newVan()
    {
        return $this->belongsTo(Van::class,  'new_van_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class,  'route_id', 'id');
    }

    public function login_user()
    {
        return $this->belongsTo(User::class,  'login_user_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class,  'reason_id', 'id');
    }
}
