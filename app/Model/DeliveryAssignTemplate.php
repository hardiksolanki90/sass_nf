<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\User;
class DeliveryAssignTemplate extends Model
{
    protected $fillable = [
        'uuid',
        'order_id',
        'delivery_id',
        'delivery_details_id',
        'customer_id',
        'delivery_driver_id',
        'item_id',
        'item_uom_id',
        'qty',
        'amount',
        'delivery_sequence',
        'trip',
        'actual_trip',
        'trip_sequence',
        'van_id',
        'is_last_trip'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class,  'order_id', 'id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class,  'delivery_id', 'id');
    }

    public function deliveryDetail()
    {
        return $this->belongsTo(DeliveryDetail::class,  'delivery_detail_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(User::class,  'delivery_driver_id', 'id');
    }

    public function deliveryDriverInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'delivery_driver_id', 'user_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class,  'van_id', 'id');
    }
}
