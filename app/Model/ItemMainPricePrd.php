<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\Model\ItemPrd;
use App\Model\ItemUomPrd;

class ItemMainPricePrd extends Model
{
    protected $table = "item_main_prices";

    protected $connection = 'server_mysql';
    //use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid', 'item_id', 'item_upc', 'item_uom_id', 'item_price', 'item_main_max_price', 'stock_keeping_unit', 'purchase_order_price', 'item_shipping_uom', 'is_secondary', 'status'
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
}
