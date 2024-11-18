<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BrandChannel extends Model
{
    protected $fillable = [
        'user_channel_id', 'brand_id'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    public function userChannel()
    {
        return $this->belongsTo(UserChannel::class, 'user_channel_id', 'id');
    }
}
