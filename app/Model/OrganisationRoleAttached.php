<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\User;

class OrganisationRoleAttached extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'user_id', 'last_role_id'
    ];

    protected $table = "organisation_role_attached";

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {

            $model->uuid = (string)\Uuid::generate();
        });
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
