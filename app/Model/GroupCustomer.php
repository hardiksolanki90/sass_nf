<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class GroupCustomer extends Model
{
    protected $fillable = [
        'group_id',
        'customer_id'
    ];
}