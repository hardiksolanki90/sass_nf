<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\User;
use App\Model\PlanogramPost;

class PlanogramDistribution extends Model
{
    use LogsActivity;

    protected $fillable = [
        'uuid', 'planogram_id', 'distribution_id', 'planogram_customer_id', 'customer_id'
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

    public function planogram()
    {
        return $this->belongsTo(Planogram::class,  'planogram_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function distribution()
    {
        return $this->belongsTo(Distribution::class,  'distribution_id', 'id');
    }

    public function planogramImages()
    {
        return $this->hasMany(PlanogramImage::class,  'planogram_distribution_id', 'id');
    }

    public function planogramPost()
    {
        return $this->hasMany(PlanogramPost::class,  'customer_id', 'customer_id');
    }
}
