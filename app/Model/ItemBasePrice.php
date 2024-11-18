<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ItemBasePrice extends Model
{
    protected $fillable = [
        'uuid',
        'start_date',
        'end_date',
        'storage_location_id',
        'warehouse_id',
        'item_id',
        'item_uom_id',
        'price'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function storageocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }
}
