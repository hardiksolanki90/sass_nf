<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;


class Vendor extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'vender_code', 'firstname', 'lastname', 'email', 'company_name', 'mobile', 'website', 'address1', 'address2', 'city', 'state', 'zip'
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

    public function customFieldValueSave()
    {
        return $this->hasMany(CustomFieldValueSave::class,  'record_id', 'id');
    }
}
