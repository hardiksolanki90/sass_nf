<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class OrderReport extends Model
{

    
    protected $fillable = [
        'organisation_id',
        'order_no',
        'customer_code',
        'customer_name',
        'item_code',
        'item_name',
        'item_uom',
        'order_qty',
        'load_qty',
        'cancel_qty',
        'invoice_qty',
        'spot_return',
        'order_date',
        'delivery_date',
        'invoice_date',
        'load_date',
        'invoice_no',
        'customer_lpo',
        'storage_location_id',
        'branch_plant',
        'on_hold',
        'driver',
        'driver',
        'cancel_reason',
        'driver_reason',
        'extend_amt',
        'trip',
        'actual_trip',
        'vehicle',
        'helper1',
        'helper2',
    ];
}
