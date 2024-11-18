<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\Model\Distribution;
use App\Model\DistributionModelStockDetails;
use App\User;

class DistributionModelStock extends Model
{
    use SoftDeletes, LogsActivity, Organisationid;

    protected $fillable = [
        'uuid', 'organisation_id', 'customer_id', 'distribution_id', 'total_number_of_facing'
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
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function distribution()
    {
        return $this->belongsTo(Distribution::class,  'distribution_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function customerInfo()
    {
        return $this->belongsTo(CustomerInfo::class,  'customer_id', 'user_id');
    }

    public function distributionModelStockDetails()
    {
        return $this->hasMany(DistributionModelStockDetails::class,  'distribution_model_stock_id', 'id')->where('is_deleted', 0);
    }

    public function distributionModelStockDetailsWithItem()
    {
        $item_ids = userChannelItems(request()->user()->id);

        return $this->hasMany(DistributionModelStockDetails::class,  'distribution_model_stock_id', 'id')->where('is_deleted', 0)
        ->whereIn('item_id', $item_ids);
    }
}
