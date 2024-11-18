<?php

namespace App\Model;

use App\User;
use Illuminate\Database\Eloquent\Model;

class UserBranchPlantAssign extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'storage_location_id'
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
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function storagelocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }
}
