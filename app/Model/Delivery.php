<?php

namespace App\Model;

use App\Model\DeliveryDetail;
use App\Model\Invoice;
use App\Model\Lob;
use App\Model\Order;
use App\Model\Organisation;
use App\Model\PaymentTerm;
use App\Model\Warehouse;
use App\Traits\Organisationid;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Delivery extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'order_id',
        'customer_id',
        'salesman_id',
        'reason_id',
        'route_id',
        'warehouse_id',
        'storage_location_id',
        'delivery_type',
        'delivery_type_source',
        'delivery_number',
        'delivery_date',
        'delivery_time',
        'delivery_weight',
        'payment_term_id',
        'total_qty',
        'total_cancel_qty',
        'total_discount_amount',
        'total_net',
        'total_vat',
        'total_excise',
        'grand_total',
        'current_stage',
        'current_stage_comment',
        'source',
        'invoice_number',
        'status',
        'approval_status',
        'is_approved',
        'is_truck_allocated',
        'invoice_route_id'
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
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class, 'customer_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'id');
    }

    public function salesmanInfo2()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'user_id');
    }

    public function deliveryDetails()
    {
        return $this->hasMany(DeliveryDetail::class, 'delivery_id', 'id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'delivery_id', 'id');
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id', 'id');
    }

    public function orderType()
    {
        return $this->belongsTo(OrderType::class, 'delivery_type', 'id');
    }

    public function lob()
    {
        return $this->belongsTo(Lob::class, 'lob_id', 'id');
    }

    public function storageocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(ReasonType::class, 'reason_id', 'id');
    }

    public function deliveryType()
    {
        return $this->belongsTo(OrderType::class, 'delivery_type', 'id');
    }

    public function customerRegion()
    {
        return $this->belongsTo(CustomerRegion::class, 'customer_id', 'customer_id');
    }

    public function deliveryAssignTemplate()
    {
        return $this->hasMany(DeliveryAssignTemplate::class, 'delivery_id', 'id');
    }

    public function getSaveData()
    {
        $this->order;
        $this->salesman;
        $this->customer;
        $this->invoice;
        $this->paymentTerm;
        $this->deliveryDetails;

        if (is_object($this->deliveryDetails)) {
            foreach ($this->deliveryDetails as $key => $details) {
                $this->deliveryDetails[$key]->item = $details->item;
                $this->deliveryDetails->itemUom = $details->itemUom;
            }
        }
        $this->lob;

        return $this;
    }
}
