<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class SpotReport extends Model
{

    
    protected $fillable = [
        'organisation_id',
        'tran_date',
        'zone_id',
        'zone_name',
        'ksm_id',
        'ksm_name',
        'reason',
        'qty',
        'amount',
    ];
}
