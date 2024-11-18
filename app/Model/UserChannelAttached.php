<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserChannelAttached extends Model
{
    protected $fillable = [
        'user_id', 'user_channel_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,  'user_id', 'id');
    }

    public function channel()
    {
        return $this->belongsTo(UserChannel::class,  'user_channel_id', 'id');
    }
    
    public function brandChannels()
    {
        return $this->hasMany(BrandChannel::class,  'user_channel_id', 'id');
    }
}
