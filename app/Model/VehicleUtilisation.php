<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class VehicleUtilisation extends Model
{

    protected $fillable = [
        'region_id',
        'region_code',
        'region_name',
        'vehicle_id',
        'vehicle_code',
        'invoice_count',
        'invoice_qty',
        'customer_count',
        'delivery_qty',
        'cancle_count',
        'cancel_qty',
        'transcation_date',
        'less_delivery_count',
        'order_count',
        'order_qty',
        'load_qty',
        'vehicle_capacity',
        'start_km',
        'end_km',
        'diesel',
        'pod_submit',
        'salesman_type',
    ];
}
