<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class OrderView extends Model
{
    use LogsActivity, Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'order_id',
        'order_number',
        'customer_code',
        'customer_name',
        'merchandiser_code',
        'merchandiser_name',
        'item_name',
        'item_code',
        'item_uom',
        'item_qty',
        'order_picked',
        'is_mobiato_sync'
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

    public function order()
    {
        return $this->belongsTo(Order::class,  'order_id', 'id');
    }
}
