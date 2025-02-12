<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;



class ItemUomPrd extends Model
{
	
		protected $table = "item_uoms";

    protected $connection = 'server_mysql';
   // use SoftDeletes, LogsActivity, Organisationid;
    
    protected $fillable = [
        'uuid', 'organisation_id', 'code', 'name', 'status'
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

    public function organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function itemMainPrice()
    {
        return $this->hasMany(ItemMainPrice::class,  'item_uom_id', 'id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class,  'item_uom_id', 'id');
    }

    public function deliverieDetails()
    {
        return $this->hasMany(DeliveryDetail::class,  'item_uom_id', 'id');
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class,  'item_uom_id', 'id');
    }

    public function creditNoteDetails()
    {
        return $this->hasMany(CreditNoteDetail::class,  'item_uom_id', 'id');
    }

    public function debitNoteDetails()
    {
        return $this->hasMany(DebitNoteDetail::class,  'item_uom_id', 'id');
    }

    public function warehouseDetails()
    {
        return $this->hasMany(WarehouseDetail::class,  'item_uom_id', 'id');
    }

    public function PDPPromotionItems()
    {
        return $this->hasMany(PDPPromotionItem::class,  'item_uom_id', 'id');
    }

    public function PDPPromotionOfferItems()
    {
        return $this->hasMany(PDPPromotionOfferItem::class,  'item_uom_id', 'id');
    }
}