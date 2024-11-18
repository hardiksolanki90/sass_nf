<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\User;
use App\Model\SalesmanInfo;

class DeliveryDriverJourneyPlan extends Model
{

    use LogsActivity;

    protected $fillable = [
        'uuid',
        'date',
        'delivery_driver_id',
        'customer_id',
        'is_visited'
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

    public function merchandiser()
    {
        return $this->belongsTo(User::class, 'delivery_driver_id', 'id');
    }

    public function salesman_infos()
    {
        return $this->belongsTo(SalesmanInfo::class,  'delivery_driver_id', 'user_id');
    }
}
