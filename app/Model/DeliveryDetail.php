<?php

namespace App\Model;

use App\Model\Delivery;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\PriceDiscoPromoPlan;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryDetail extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid',
        'delivery_id',
        'salesman_id',
        'item_id',
        'item_uom_id',
        'original_item_uom_id',
        'discount_id',
        'is_free',
        'is_item_poi',
        'promotion_id',
        'reason_id',
        'item_qty',
        'item_price',
        'item_gross',
        'item_discount_amount',
        'item_net',
        'item_vat',
        'item_excise',
        'item_grand_total',
        'batch_number',
        'invoiced_qty',
        'open_qty',
        'original_item_qty',
        'is_picking',
        'delivery_status',
        'picking_status',
        'transportation_status',
        'shipment_status',
        'invoice_status',
        'delivery_note_id',
        'cancel_qty',
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = false;

    // public static function boot()
    // {
    //     parent::boot();
    //     self::creating(function ($model) {
    //         $model->uuid = (string) \Uuid::generate();
    //     });
    // }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class, 'item_uom_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'user_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'id', 'item_id');
    }

    public function itemUoms()
    {
        return $this->hasMany(ItemUom::class, 'id', 'item_uom_id');
    }

    public function discount()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class, 'discount_id', 'id');
    }

    public function promotion()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class, 'promotion_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class, 'van_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class, 'template_sold_to_outlet_id', 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'template_sold_to_outlet_id', 'id');
    }

    public function deliveryAssignTemplate()
    {
        return $this->hasMany(DeliveryAssignTemplate::class, 'delivery_details_id', 'id');
    }
}
