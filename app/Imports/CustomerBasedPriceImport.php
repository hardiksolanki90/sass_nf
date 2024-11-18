<?php

namespace App\Imports;

use App\Model\CustomerBasedPricing;
use App\Model\CustomerInfo;
use App\Model\Item;
use App\Model\ItemUom;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;

class CustomerBasedPriceImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return CustomerBasedPricing|null
     */
    public function model(array $row)
    {
        // Customer Code
        if ($row[0] != "Customer Code" && $row[0] != "") {
            $customer_code  = $row[0];
            $item_code      = $row[1];
            $uom            = $row[2];
            $price          = $row[3];
            $key            = $row[4];

            $customerInfo = CustomerInfo::where('customer_code', $customer_code)->first();
            $item         = Item::where('item_code', $item_code)->first();
            $itemUom      = ItemUom::where('name', $uom)->first();

            if (
                $customerInfo &&
                $item &&
                $itemUom
            ) {
                $start_date     = Carbon::parse($row[5])->format('Y-m-d');
                $end_date       = Carbon::parse($row[6])->format('Y-m-d');
                // $start_date     = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[5])->format('Y-m-d');
                // $end_date       = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[6])->format('Y-m-d');

                $CustomerBasedPricing = new CustomerBasedPricing();
                $CustomerBasedPricing->key           = $key;
                $CustomerBasedPricing->customer_id   = $customerInfo->user_id;
                $CustomerBasedPricing->item_id       = $item->id;
                $CustomerBasedPricing->item_uom_id   = $itemUom->id;
                $CustomerBasedPricing->price         = $price;
                $CustomerBasedPricing->start_date    = $start_date;
                $CustomerBasedPricing->end_date      = $end_date;
                $CustomerBasedPricing->save();
            }
        }
    }

    // public function batchSize(): int
    // {
    //     return 1000;
    // }

    // public function chunkSize(): int
    // {
    //     return 1000;
    // }
}
