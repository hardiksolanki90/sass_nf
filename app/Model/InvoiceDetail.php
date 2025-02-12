<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Model\Invoice;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\PriceDiscoPromoPlan;

class InvoiceDetail extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
         'uuid', 'invoice_id', 'item_id', 'item_uom_id', 'van_id', 'discount_id', 'is_free', 'is_item_poi', 'promotion_id', 'item_qty', 'lower_unit_qty', 'item_price', 'item_gross', 'item_discount_amount', 'item_net', 'item_vat', 'item_excise', 'item_grand_total', 'base_price', 'batch_number', 'original_item_qty', 'erp_post_id', 'erp_response_error', 'is_deleted', 'delv_id', 'deleted_import_data'
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class,  'invoice_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function discount()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class,  'discount_id', 'id');
    }

    public function promotion()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class,  'promotion_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class,  'van_id', 'id');
    }
}
