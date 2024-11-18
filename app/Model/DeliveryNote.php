<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\User;

class DeliveryNote extends Model
{
    protected $fillable = [
        'uuid', 'delivery_id', 'delivery_detail_id', 'salesman_id', 'item_uom_id', 'item_id', 'qty', 'delivery_note_number', 'is_cancel', 'reason_id'
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

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class, 'item_uom_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'user_id');
    }
}
