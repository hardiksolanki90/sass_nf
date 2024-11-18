<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
// use Spatie\Activitylog\Traits\LogsActivity;
// use App\Traits\Organisationid;

class OrderPrd extends Model
{
    // use SoftDeletes, LogsActivity;

    protected $table = "orders";

    protected $connection = 'server_mysql';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'lob_id',
        'customer_id',
        'depot_id',
        'order_type_id',
        'salesman_id',
        'route_id',
        'storage_location_id',
        'warehouse_id',
        'customer_lop',
        'order_number',
        'order_date',
        'due_date',
        'delivery_date',
        'payment_term_id',
        'reason_id',
        'total_qty',
        'total_cancel_qty',
        'total_gross',
        'total_discount_amount',
        'total_net',
        'total_vat',
        'total_excise',
        'grand_total',
        'any_comment',
        'delivered_qty',
        'open_qty',
        'current_stage',
        'current_stage_comment',
        'sign_image',
        'sync_status',
        'source',
        'status',
        'is_approved',
        'order_status',
        'picking_status',
        'transportation_status',
        'shipment_status',
        'invoice_status',
        'erp_number',
        'order_created_user_id',
        'order_generate_picking',
        'invoice_id'
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
