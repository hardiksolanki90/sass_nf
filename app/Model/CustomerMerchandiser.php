<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\User;

class CustomerMerchandiser extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id', 'merchandiser_id'
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = false;

    public function customer()
    {
        return $this->belongsTo(User::class,  'customer_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class,  'merchandiser_id', 'id');
    }

    public function customerVisitByCustomer()
    {
        return $this->hasMany(CustomerVisit::class,  'customer_id', 'customer_id');
    }

    public function customerVisitBySalesman()
    {
        return $this->hasMany(CustomerVisit::class,  'salesman_id', 'merchandiser_id');
    }

    public function planogramCustomer()
    {
        return $this->hasMany(PlanogramCustomer::class, 'customer_id', 'customer_id');
    }
}
