<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notifications extends Model
{

    use Organisationid, SoftDeletes;

    protected $fillable = [
        'uuid', 'organisation_id', 'user_id', 'url', 'type', 'message', 'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the user that owns the Notifications
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function geoApproval()
    {
        return $this->belongsTo(GeoApproval::class, 'uuid', 'uuid');
    }
}
