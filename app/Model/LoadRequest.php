<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\User;

class LoadRequest extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;
    // protected $table = 'load_request';
    protected $fillable = [
        'uuid', 'organisation_id', 'route_id', 'salesman_id', 'load_number', 'load_type', 'load_date', 'status'
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
	
	public function Organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }
	public function Route()
    {
        return $this->belongsTo(Route::class,  'route_id', 'id');
    }
	public function Salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }
	public function LoadRequestDetail()
    {
        return $this->hasMany(LoadRequestDetail::class,  'load_request_id', 'id');
    }

    public function getSaveData()
    {
        $this->Route;
        $this->Salesman;
        $this->LoadRequestDetail;
        return $this;
    }
}
