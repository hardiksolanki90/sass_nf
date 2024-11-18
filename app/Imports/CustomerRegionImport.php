<?php

namespace App\Imports;

use App\Model\CustomerInfo;
use App\Model\CustomerRegion;
use App\Model\Region;
use App\Model\Zone;
use Maatwebsite\Excel\Concerns\ToModel;

class CustomerRegionImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if ($row[0] != "Customer Code") {

            $customer_info      = CustomerInfo::where('customer_code', $row[0])->first();
            // $region             = Region::where('region_code', $row[1])->first();
            $zone               = Zone::where('name', $row[1])->first();

            if (
                $customer_info &&
                $zone
            ) {

                $cr = CustomerRegion::where('customer_id', $customer_info->user_id)
                    ->where('zone_id', $zone->id)
                    ->first();

                if (!$cr) {
                    $cr = new CustomerRegion();
                }
                $cr->customer_id = $customer_info->user_id;
                $cr->zone_id   = $zone->id;
                $cr->save();

                return $cr;
            }
        }
    }
}
