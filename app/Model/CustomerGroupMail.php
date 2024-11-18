<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CustomerGroupMail extends Model
{
    protected $fillable = [
        'date',
        'group_id',
        'customer_id',
        'storage_location_id',
        'file_name',
        'url'
    ];
}
