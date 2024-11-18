<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;
use App\User;

class MerchandiserUpdated extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'merchandiser_code', 'merchandiser_id', 'is_updated'
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

    public function salesman()
    {
        return $this->belongsTo(User::class,  'merchandiser_id', 'id');
    }
}
