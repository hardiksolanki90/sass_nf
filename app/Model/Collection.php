<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\Invoice;
use App\User;

class Collection extends Model
{
    use LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'invoice_id', 'customer_id', 'salesman_id', 'collection_number', 'payemnt_type', 'invoice_amount', 'cheque_number', 'cheque_date', 'bank_info', 'transaction_number', 'current_stage', 'current_stage_comment'
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

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function collectiondetails()
    {
        return $this->hasMany(CollectionDetails::class,  'collection_id', 'id');
    }

    public function getSaveData()
    {
        $this->invoice;
        $this->customer;
        $this->salesman;
        $this->collectiondetails;
        return $this;
    }
}
