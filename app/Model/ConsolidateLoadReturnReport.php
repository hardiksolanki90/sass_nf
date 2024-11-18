<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ConsolidateLoadReturnReport extends Model
{
    protected $fillable = [
        "SR_No",
        "Item",
        "Item_description",
        "qty",
        "uom",
        "sec_qty",
        "sec_uom",
        "from_location",
        "to_location",
        "from_lot_serial",
        "to_lot_number",
        "to_lot_status_code",
        "load_date",
        "warehouse",
        "is_exported",
        "salesman",
        'storage_location_id',
        'type'
    ];

    
}
