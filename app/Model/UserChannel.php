<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;

class UserChannel extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'name', 'status'
    ];

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

    public function brandChannels()
    {
        return $this->hasMany(BrandChannel::class, 'user_channel_id', 'id');
    }
}
