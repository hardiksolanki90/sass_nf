<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class CustomerBasedPricing extends Model
{
    protected $fillable = [
        'uuid',
        'start_date',
        'end_date',
        'key',
        'customer_id',
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

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
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
