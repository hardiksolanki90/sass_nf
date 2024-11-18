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

class DeliveryReport extends Model
{
    // use SoftDeletes, LogsActivity, Organisationid;
    protected $table = "deliveries_report";

    protected $fillable = [
        'region',
        'order_id',
        'salesman_id',
        'salesman_name',
        'salesman_code',
        'order_number',
        'date',
    ];

    // protected static $logAttributes = ['*'];

    // protected static $logOnlyDirty = false;

    // public static function boot()
    // {
    //     parent::boot();
    //     self::creating(function ($model) {
    //         $model->uuid = (string) \Uuid::generate();
    //     });
    // }
    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    
}
