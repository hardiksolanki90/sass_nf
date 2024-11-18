<?php

namespace App\Model;

use App\Model\Organisation;
use App\Model\Route;
use App\Traits\Organisationid;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class SalesmanUnload extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'storage_location_id', 'warehouse_id', 'van_id', 'code', 'trip_id', 'unload_type', 'route_id', 'salesman_id', 'transaction_date', 'status', 'source', 'current_stage', 'current_stage_comment',
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

    public function salesmanUnloadDetail()
    {
        return $this->hasMany(SalesmanUnloadDetail::class, 'salesman_unload_id', 'id');
    }

    public function unloadDetail()
    {
        return $this->hasMany(SalesmanUnloadDetail::class, 'salesman_unload_id', 'id')->where('unload_type', 1);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'user_id');
    }

    public function storageocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class, 'van_id', 'id');
    }

    public function getSaveData()
    {
        $this->salesman;
        $this->trip;
        $this->salesmanUnloadDetail;
        if (count($this->salesmanUnloadDetail)) {
            foreach ($this->salesmanUnloadDetail as $key => $detail) {
                $this->salesmanUnloadDetail[$key]->item = $detail->item;
                $this->salesmanUnloadDetail[$key]->item = $detail->itemUom;
            }
        }

        return $this;
    }

    public function getSaveNewData()
    {
        $this->salesman;
        $this->trip;
        $this->route;

        $this->salesmanUnloadDetail;
        if (count($this->salesmanUnloadDetail)) {
            foreach ($this->salesmanUnloadDetail as $key => $detail) {
                if ($this->salesmanUnloadDetail[$key]->unload_type == 1) {
                    $this->salesmanUnloadDetail[$key]->item = $detail->item;
                    $this->salesmanUnloadDetail[$key]->item = $detail->itemUom;
                }
            }
        }
        if (is_object($this->salesman)) {
            $this->salesman->salesmanInfo;
        }
        if (is_object($this->route)) {
            $this->route->depot;
        }
        if (is_object($this->route)) {
            $depot = $this->route->depot;
            $Warehouse = Warehouse::where('depot_id', $depot->id)->first();

            $warehouselocation = Storagelocation::where('warehouse_id', $Warehouse->id)
                ->where('loc_type', '1')
                ->first();

            $this->src_location = $warehouselocation->name;
        }
        return $this;
    }
}
