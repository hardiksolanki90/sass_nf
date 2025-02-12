<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Model\CreditNote;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\PriceDiscoPromoPlan;

class CreditNoteDetail extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid',
        'credit_note_id',
        'salesman_id',
        'item_id',
        'item_condition',
        'item_uom_id',
        'discount_id',
        'is_free',
        'is_item_poi',
        'promotion_id',
        'item_qty',
        'item_price',
        'item_gross',
        'item_discount_amount',
        'item_net',
        'item_vat',
        'item_excise',
        'item_grand_total',
        'batch_number',
        'reason',
        'reason_id',
        'is_deleted',
        'item_expiry_date',
        'invoice_id',
        'invoice_total',
        'credit_note_status',
        'credit_note_notes_id',
        'template_order_id',
        'template_sold_to_outlet_id',
        'template_item_id',
        'template_driver_id',
        'template_order_number',
        'template_sold_to_outlet_code',
        'template_sold_to_outlet_name',
        'template_return_request_date',
        'template_item_name',
        'template_item_code',
        'template_total_value_in_case',
        'template_total_amount',
        'template_delivery_sequnce',
        'template_trip',
        'template_trip_sequnce',
        'template_vechicle',
        'template_driver_name',
        'template_driver_code',
        'template_is_last_trip',
        'erp_number',
        'erp_response_error'
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

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class,  'credit_note_id', 'id');
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class,  'invoice_id', 'id');
    }
}
