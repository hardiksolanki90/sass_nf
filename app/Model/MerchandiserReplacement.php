<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\User;

class MerchandiserReplacement extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'old_salesman_id', 'new_salesman_id', 'added_on'
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

    public function newSalesman()
    {
        return $this->belongsTo(User::class,  'new_salesman_id', 'id');
    }

    public function oldSalesman()
    {
        return $this->belongsTo(User::class,  'old_salesman_id', 'id');
    }

    public function newSalesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'new_salesman_id', 'user_id');
    }

    public function oldSalesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'old_salesman_id', 'user_id');
    }
}
