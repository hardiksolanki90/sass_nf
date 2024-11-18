<?php

namespace App\Model;

use App\Model\Van;
use App\User;
use Illuminate\Database\Eloquent\Model;

class SalesmanVehicle extends Model
{
    use \Awobaz\Compoships\Compoships;
    protected $fillable = [
        'uuid', 'salesman_id', 'van_id', 'date','helper1_id','helper2_id'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function helperInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'helper1_id', 'user_id');
    }

    public function van()
    {
        return $this->belongsTo(Van::class,  'van_id', 'id');
    }
}
