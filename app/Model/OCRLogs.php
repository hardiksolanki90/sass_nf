<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class OCRLogs extends Model
{
    protected $fillable = [
        'uuid', 'customer_lpo', 'item_no', 'qty', 'file_type'
    ];


    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }
}
