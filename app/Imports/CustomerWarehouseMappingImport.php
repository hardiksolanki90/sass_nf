<?php

namespace App\Imports;

use App\Model\CustomerInfo;
use App\Model\CustomerWarehouseMapping;
use App\Model\Lob;
use App\Model\Storagelocation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;

class CustomerWarehouseMappingImport implements ToModel
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
            $lob                = Lob::where('lob_code', $row[1])->first();
            $storage_location   = Storagelocation::where('code', $row[2])->first();

            if (
                $customer_info &&
                $lob &&
                $storage_location
            ) {

                $cwm = CustomerWarehouseMapping::where('customer_id', $customer_info->user_id)
                    ->where('lob_id', $lob->id)
                    ->where('storage_location_id', $storage_location->id)
                    ->first();
                if (!$cwm) {
                    $cwm = new CustomerWarehouseMapping;
                }
                $cwm->customer_id           = $customer_info->user_id;
                $cwm->customer_info_id      = $customer_info->id;
                $cwm->lob_id                = $lob->id;
                $cwm->storage_location_id   = $storage_location->id;
                $cwm->warehouse_id          = getWarehuseBasedOnStorageLoacation($storage_location->id, false);
                $cwm->save();

                return $cwm;
            }
        }
    }
}
