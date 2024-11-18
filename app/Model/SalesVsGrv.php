<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class SalesVsGrv extends Model
{
    protected $fillable = [
        'date',
        'zone_id',
        'zone_name',
        'kam_id',
        'kam_name',
        'invoice_qty',
        'invoice_amount',
        'grv_qty',
        'grv_amount'
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'ksm_id', 'id');
    }
}
