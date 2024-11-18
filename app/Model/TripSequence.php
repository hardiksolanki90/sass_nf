<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TripSequence extends Model
{
    protected $fillable = [
        'uuid',
        'salesman_id',
        'route_id',
        'date',
        'login_time',
        'logout_time',
        'trip_number',
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class, 'salesman_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }
}
