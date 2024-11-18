<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class CustomerGroupBasedPricingPrd extends Model
{

    protected $table = "customer_group_based_pricings";

    protected $connection = 'server_mysql';

    protected $fillable = [
        'uuid',
        'start_date',
        'end_date',
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

    public function item()
    {
        return $this->belongsTo(ItemPrd::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUomPrd::class,  'item_uom_id', 'id');
    }
}
