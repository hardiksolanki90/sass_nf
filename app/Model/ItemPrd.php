<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;
//use Spatie\Activitylog\Traits\LogsActivity;
//use App\Traits\Organisationid;
use App\Model\ItemMajorCategory;
use App\Model\ItemGroup;
use App\Model\ItemUom;
use App\Model\ItemMainPrice;
use App\Model\OrderDetailPrd;


class ItemPrd extends Model
{
	protected $table = "items";

    protected $connection = 'server_mysql';
    //use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'item_major_category_id',
        'item_group_id',
        'brand_id',
        'item_code',
        'erp_code',
        'item_name',
        'item_description',
        'item_barcode',
        'item_weight',
        'item_shelf_life',
        'lower_unit_item_upc',
        'lower_unit_uom_id',
        'lower_unit_item_price',
        'is_tax_apply',
        'item_vat_percentage',
        'item_excise',
        'current_stage',
        'current_stage_comment',
        'status',
        'stock_keeping_unit',
        'volume',
        'lower_unit_purchase_order_price',
        'item_shipping_uom',
        'item_image',
        'new_lunch',
        'start_date',
        'end_date'
        
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

    public function itemMajorCategory()
    {
        return $this->belongsTo(ItemMajorCategory::class,  'item_major_category_id', 'id');
    }

    public function itemGroup()
    {
        return $this->belongsTo(ItemGroup::class,  'item_group_id', 'id');
    }

    public function itemUomLowerUnit()
    {
        return $this->belongsTo(ItemUom::class,  'lower_unit_uom_id', 'id');
    }

    public function itemExciseUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_excise_uom_id', 'id');
    }

    public function itemMainPrice()
    {
        return $this->hasMany(ItemMainPrice::class,  'item_id', 'id');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class,  'item_id', 'id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class,  'item_id', 'id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class,  'brand_id', 'id');
    }

    public function outletProductCodeItems()
    {
        return $this->hasMany(OutletProductCodeItem::class,  'item_id', 'id');
    }

    public function deliveryDetails()
    {
        return $this->hasMany(DeliveryDetail::class,  'item_id', 'id');
    }

    public function invoicesDetails()
    {
        return $this->hasMany(InvoiceDetail::class,  'item_id', 'id');
    }

    public function creditNoteDetails()
    {
        return $this->hasMany(CreditNoteDetail::class,  'item_id', 'id');
    }

    public function debitNoteDetails()
    {
        return $this->hasMany(DebitNoteDetail::class,  'item_id', 'id');
    }

    public function routeItemGroupings()
    {
        return $this->hasMany(RouteItemGrouping::class,  'item_id', 'id');
    }

    public function warehouseDetails()
    {
        return $this->hasMany(WarehouseDetail::class,  'item_id', 'id');
    }

    public function PDPItems()
    {
        return $this->hasMany(PDPItem::class,  'item_id', 'id');
    }

    public function PDPPromotionItems()
    {
        return $this->hasMany(PDPPromotionItem::class,  'item_id', 'id');
    }

    public function PDPPromotionOfferItems()
    {
        return $this->hasMany(PDPPromotionOfferItem::class,  'item_id', 'id');
    }

    public function customFieldValueSave()
    {
        return $this->hasMany(CustomFieldValueSave::class,  'record_id', 'id');
    }

    public function productCatalog()
    {
        return $this->hasMany(ProductCatalog::class,  'item_id', 'id');
    }

    public function pricingCheckDetail()
    {
        return $this->hasMany(PricingCheckDetail::class,  'item_id', 'id')->orderBy('date', 'desc');
    }

    public function getSaveData()
    {
        $this->itemUomLowerUnit;
        $this->ItemMainPrice;

        if (count($this->ItemMainPrice)) {
            foreach ($this->ItemMainPrice as $key => $price) {
                $this->ItemMainPrice[$key]->itemUom = $price->itemUom;
            }
        }
        $this->itemMajorCategory;
        $this->itemGroup;
        $this->brand;
        $this->productCatalog;
        return $this;
    }
}
