<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;

class MerchandiserMsl extends Model
{
    use SoftDeletes, LogsActivity;

    //protected $table = 'merchandiser_msls';

    protected $fillable = [
        'uuid', 'organisation_id', 'date', 'customer_code', 'customer_id', 'customer_name', 'total_msl_item', 'msl_item_perform', 'msl_percentage', 'merchandiser_id', 'merchandiser_name'
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
}
