<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\Invoice;
use App\User;
use App\Model\CreditNoteDetail;
use App\Model\Lob;

class CreditNote extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'invoice_id',
        'customer_id',
        'salesman_id',
        'trip_id',
        'route_id',
        'credit_note_number',
        'credit_note_date',
        'customer_reference_number',
        'order_type_id',
        'payment_term_id',
        'storage_location_id',
        'reason_id',
        'warehouse_id',
        'return_type',
        'total_qty',
        'total_discount_amount',
        'total_net',
        'total_vat',
        'total_excise',
        'grand_total',
        'pdc_amount',
        'credit_note_comment',
        'current_stage',
        'current_stage_comment',
        'lob_id',
        'approval_status',
        'reason',
        'merchandiser_image_1',
        'merchandiser_image_2',
        'merchandiser_image_3',
        'merchandiser_image_4',
        'merchandiser_status',
        'delivery_driver_image_1',
        'delivery_driver_image_2',
        'delivery_driver_image_3',
        'delivery_driver_image_4',
        'delivery_driver_status',
        'pending_credit',
        'is_exchange',
        'exchange_number',
        'picking_date',
        'wh_approve_date',
        'unload_date',
        'erp_id',
        "created_user_id"
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class,  'invoice_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'user_id');
    }
   
    public function driver()
    {
        return $this->belongsTo(User::class,  'delivery_driver_id', 'id');
    }
    public function supervisor()
    {
        return $this->belongsTo(User::class,  'supervisor_id', 'id');
    }
    public function driverInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'user_id');
    }
    public function route()
    {
        return $this->belongsTo(Route::class,  'route_id', 'id');
    }
    public function reason()
    {
        return $this->belongsTo(ReasonType::class,  'reason_id', 'id');
    }

    public function creditNoteDetails()
    {
        return $this->hasMany(CreditNoteDetail::class,  'credit_note_id', 'id');
    }

    public function lob()
    {
        return $this->belongsTo(Lob::class, 'lob_id', 'id');
    }
    
    public function storageocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }
    
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }
    
    public function customerInfoDetails()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function creditnotedetail()
    {
        return $this->hasMany(CreditNoteDetail::class,  'credit_note_id', 'id')
            ->selectRaw(
                'credit_note_id,item_id,	
                                    SUM(credit_note_details.item_gross) as Total_creditnote'
            )
            ->groupBy('item_id')->with('item:id,item_code,item_name,item_major_category_id', 'item.itemMajorCategory:id,name');
    }

    public function getSaveData()
    {
        $this->invoice;
        $this->customer;
        $this->salesman;
        $this->creditNoteDetails;

        if (count($this->creditNoteDetails)) {
            foreach ($this->creditNoteDetails as $key => $detail) {
                $this->creditNoteDetails[$key]->item = $detail->item;
                $this->creditNoteDetails[$key]->itemUom = $detail->itemUom;
            }
        }
        return $this;
    }
}
