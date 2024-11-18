<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ReturnView extends Model
{
    protected $fillable = [
        'MCU_CODE',
        'MCU_NAME',
        'RTE_CODE',
        "PRE_RTE",
        'TranDate',
        'SMN_CODE',
        'SMN_NAME',
        'ITM_CODE',
        'ITM_NAME',
        'GoodReturn_CTN',
        'GoodReturn_PCS',
        'Damaged_PCS',
        'Expired_PCS',
        'NearExpiry_PCS',
        'FLAG_GD_CTN',
        'FLAG_GD_PCS',
        'FLAG_DM',
        'FLAG_EX',
        'FLAG_NR',
        'salesman_unload_detail_id',
        'mobiato_return_picked',
        'good_receipt_note_detail_detail_id'
    ];
}
