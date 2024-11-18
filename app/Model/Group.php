<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Model\GroupCustomer;

class Group extends Model
{
    protected $fillable = [
        'name',
        'email'
    ];

    public function groupCustomer()
    {
        return $this->hasMany(GroupCustomer::class, 'group_id', 'id');
    }
}
