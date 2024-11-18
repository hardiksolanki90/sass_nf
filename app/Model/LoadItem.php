<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class LoadItem extends Model
{
    protected $table = 'load_item';

    protected $fillable = [
        "delivery_id",
        "van_id",
        "van_code",
        "storage_location_id",
        "storage_location_code",
        "zone_id ",
        "zone_name",
        "load_number",
        "salesman_code",
        "item_id",
        "item_uom_id",
        "item_uom",
        "salesman_id",
        "dmd_lower_upc",
        "loadqty",
        "prv_load_qty",
        "on_hold_qty",
        "return_qty",
        "sales_qty",
        "unload_qty",
        "report_date",
        "damage_qty",
        "expiry_qty"
    ];
}
