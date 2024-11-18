<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;

class DeliveryLog extends Model
{

    use Organisationid;

    protected $fillable = [
        'changed_user_id',
        'order_id',
        'delivery_id',
        'delivery_detail_id',
        'customer_id',
        'salesman_id',
        'item_id',
        'item_uom_id',
        'reason_id',
        'customer_code',
        'customer_name',
        'salesman_code',
        'salesman_name',
        'item_name',
        'item_code',
        'item_uom',
        'item_qty',
        'original_item_qty',
        'action',
        'reason'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class,  'delivery_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class,  'order_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'id');
    }
}
