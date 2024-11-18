<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\Model\StoragelocationPrd;
use App\Model\ItemPrd;
use App\Model\ItemUomPrd;


class StoragelocationDetailPrd extends Model
{
	protected $table = "storagelocation_details";

    protected $connection = 'server_mysql';
  //  use SoftDeletes, LogsActivity;
    
    protected $fillable = [
        'uuid', 'storage_location_id', 'item_id', 'item_uom_id', 'qty', 'status'
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

   public function Storagelocation()
    {
        return $this->belongsTo(Storagelocation::class,  'storage_location_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function getSaveData()
    {
        $this->Storagelocation;
        $this->item;
        $this->itemUom;
        return $this;
    }
}
