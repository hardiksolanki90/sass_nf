<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\Model\PriceDiscoPromoPlan;
use App\Model\ItemPrd;

class PDPItemPrd extends Model
{
	protected $table = "p_d_p_promotion_items";

    protected $connection = 'server_mysql';	
   // use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid', 'price_disco_promo_plan_id', 'item_id', 'price', 'lob_id'
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

    public function priceDiscoPromoPlan()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class,  'price_disco_promo_plan_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class, 'item_uom_id', 'id');
    }
}
