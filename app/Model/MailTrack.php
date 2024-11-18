<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use App\User;

class MailTrack extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'user_id', 'email', 'subject', 'message'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,  'user_id', 'id');
    }
}
