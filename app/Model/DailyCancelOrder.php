<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DailyCancelOrder extends Model
{

    protected $fillable = [
        'date',
        'ksm_id',
        'ksm_name',
        'reason_name',
        'reason_id',
        'zone_name',
        'zone_id',
        'qty',
        'amount'
    ];
}
