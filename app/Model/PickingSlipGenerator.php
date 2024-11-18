<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class PickingSlipGenerator extends Model
{
    protected $fillable = [
        'uuid', 'order_id', 'picking_slip_generator_id', 'date', 'time', 'date_time'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'picking_slip_generator_id', 'id');
    }
}
