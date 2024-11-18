<?php

namespace App\Model;

use App\Model\Depot;
use App\Model\Organisation;
use App\Model\Route;
use App\Model\SalesmanLoadDetails;
use App\Traits\Organisationid;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class SalesmanLoad extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'load_number',
        'depot_id',
        'route_id',
        'order_id',
        'delivery_id',
        'salesman_id ',
        'trip_id',
        'trip_number',
        'load_date',
        'load_type',
        'load_confirm',
        'status',
        'storage_location_id',
        'warehouse_id',
        'van_id'
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = false;

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

    public function salesmanLoadDetails()
    {
        return $this->hasMany(SalesmanLoadDetails::class, 'salesman_load_id', 'id');
    }

    public function storageocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function depot()
    {
        return $this->belongsTo(Depot::class, 'depot_id', 'id');
    }

    public function salesman_infos()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id', 'id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class, 'van_id', 'id');
    }

    public function getSaveData()
    {
        $this->salesmanLoadDetails;
        foreach ($this->salesmanLoadDetails as $key => $detail) {
            $this->salesmanLoadDetails[$key]->item = $detail->item;
            $this->salesmanLoadDetails[$key]->itemUOM = $detail->itemUOM;
        }
        $this->depot;
        $this->salesman_infos;
        $this->route;
        $this->user;
        return $this;
    }
}
