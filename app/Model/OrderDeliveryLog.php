<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class OrderDeliveryLog extends Model
{
    protected $fillable = [
        'created_user',
        'order_id',
        'delviery_id',
        'updated_user',
        'previous_request_body',
        'request_body',
        'action',
        'status'
    ];
}
