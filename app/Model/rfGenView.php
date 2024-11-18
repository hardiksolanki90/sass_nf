<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class rfGenView extends Model
{
    protected $fillable = [
        'uuid',
        'GLDate',
        'item_id',
        'ITM_CODE',
        'ITM_NAME',
        'TranDate',
        'Order_Number',
        'MCU_CODE',
        'LOAD_NUMBER',
        'DemandPUOM',
        'DemandSUOM',
        'PrevretSUom',
        'PrevretPUom',
        'OrderPicked',
        'mobiato_order_picked',
        'order_detail_id'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'Order_Number', 'order_number');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'LOAD_NUMBER', 'delivery_number');
    }

    public function salesmanLoad()
    {
        return $this->belongsTo(SalesmanLoad::class, 'MCU_CODE', 'laod_number');
    }
}
