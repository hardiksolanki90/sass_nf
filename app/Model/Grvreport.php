<?php

namespace App\Model;

use App\Traits\Organisationid;
use App\User;
use Illuminate\Database\Eloquent\Model;

class Grvreport extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid',
		'organisation_id',
        'ksm_id',
        'ksm_name',
        'reason',
		'qty',
		'amount',
		'tran_date'
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function kam()
    {
        return $this->belongsTo(User::class, 'kam_id', 'id');
    }

    public function kas()
    {
        return $this->belongsTo(User::class, 'kas_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class, 'customer_id', 'user_id');
    }
}
