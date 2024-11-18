<?php

namespace App\Model;

use App\Model\Item;
use App\Model\ItemUom;
use App\Model\SalesmanUnload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class SalesmanUnloadDetail extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid', 'salesman_unload_id', 'reason_id', 'item_id', 'item_uom', 'unload_qty', 'original_item_qty', 'unload_date', 'unload_type', 'reason', 'status', 'storage_location_id', 'van_id'
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

    public function salesmanUnload()
    {
        return $this->belongsTo(SalesmanUnload::class, 'salesman_unload_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class, 'item_uom', 'id');
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

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }
}
