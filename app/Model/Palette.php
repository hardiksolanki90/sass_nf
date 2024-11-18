<?php

namespace App\Model;

use App\User;
use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;

class Palette extends Model
{
    use Organisationid;
    protected $fillable = [
        'uuid',
        'organisation_id',
        'salesmana_id',
        'date',
        'item_id',
        'qty',
        'type'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'salesman_id', 'id');
    }

    public function salesmanInfo()
    {
        return $this->belongsTo(SalesmanInfo::class,  'salesman_id', 'user_id');
    }
}
