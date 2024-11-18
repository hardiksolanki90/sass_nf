<?php

namespace App\Model;

use App\Model\Organisation;
use App\Model\VanCategory;
use App\Model\VanType;
use App\Model\OdoMeter;
use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Van extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'van_code', 'area_id', 'plate_number', 'description', 'capacity', 'van_type_id', 'van_category_id', 'van_status', 'reading',
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
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function type()
    {
        return $this->belongsTo(VanType::class, 'van_type_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(VanCategory::class, 'van_category_id', 'id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id', 'id');
    }

    public function customFieldValueSave()
    {
        return $this->hasMany(CustomFieldValueSave::class, 'record_id', 'id');
    }

    public function odoMeter()
    {
        return $this->hasMany(OdoMeter::class, 'van_id', 'id')->latest()->first();
    }

    public function route()
    {
        return $this->hasOne(Route::class, 'van_id', 'id');
    }
}
