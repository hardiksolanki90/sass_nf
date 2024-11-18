<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DIFOTReport extends Model
{
    use SoftDeletes;

    protected $table = 'difot_reports';

    protected $fillable = [
        'id', 'region_code', 'region_id', 'invoice_number', 'customer_code', 'customer_id', 'difot', 'report_date'
    ];
}
