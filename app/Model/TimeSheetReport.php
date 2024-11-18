<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\User;

class TimeSheetReport extends Model
{
    use LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'gl_date', 'transaction_date', 'day_start', 'day_end', 'customer_code', 'customer_name', 'check_in', 'check_out', 'total_time_spend', 'total_trip_time_spend', 'salesman_id', 'salesman_code', 'salesman_name', 'zone_Id', 'zone_name'
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
}
