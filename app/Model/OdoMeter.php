<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\SalesmanLoadDetails;
use App\Model\Route;
use App\Model\Depot;
use App\Model\SalesmanVehicle;
use App\User;

class OdoMeter extends Model
{
    use SoftDeletes,Organisationid;
    use \Awobaz\Compoships\Compoships;

    protected $fillable = [
        'uuid', 'organisation_id', 'salesman_id', 'trip_id', 'date', 'van_id', 'start_fuel', 'end_fuel', 'status', 'diesel',
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
    
    public function user()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'user_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class,  'trip_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class,  'van_id', 'id');
    }

    public function salesman_vehicles()
    {
        return $this->belongsTo(SalesmanVehicle::class,  ['van_id', 'date'], ['van_id', 'date']);
    }
}
