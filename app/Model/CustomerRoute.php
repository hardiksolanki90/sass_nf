<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Model\CustomerLob;
use App\Model\Route;

class CustomerRoute extends Model
{
    protected $fillable = [
        'uuid', 'customer_id', 'customer_lob_id', 'is_lob'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id')->where('is_lob', 0);
    }

    public function customerLob()
    {
        return $this->belongsTo(CustomerLob::class,  'customer_lob_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class,  'route_id', 'id');
    }
}
