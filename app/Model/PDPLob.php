<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Model\Lob;
use App\Model\PriceDiscoPromoPlan;

class PDPLob extends Model
{
    protected $fillable = [
        'uuid', 'price_disco_promo_plan_id', 'lob_id'
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

    public function priceDiscoPromoPlan()
    {
        return $this->belongsTo(PriceDiscoPromoPlan::class,  'price_disco_promo_plan_id', 'id');
    }

    public function lob()
    {
        return $this->belongsTo(Lob::class,  'lob_id', 'id');
    }
}
