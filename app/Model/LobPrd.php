<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;


class LobPrd extends Model
{
    
	protected $table = "lobs";

    protected $connection = 'server_mysql';
    protected $fillable = [
        'uuid', 'organisation_id', 'user_id', 'name'
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = false;

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id', 'id');
    }

    public function cusotmerWarehouseMapping()
    {
        return $this->hasMany(CustomerWarehouseMapping::class, 'lob_id', 'id');
    }
}
