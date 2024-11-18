<?php

namespace App\Imports;

use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\SalesmanInfo;
use App\User;
use Maatwebsite\Excel\Concerns\ToModel;

class CustomerMerchandiserImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $salesmanInfo = SalesmanInfo::where('salesman_code', 'like', '%' . $row[0] . '%')->first();
        $customerInfo = CustomerInfo::where('customer_code', 'like', '%' . $row[1] . '%')->first();
        if (is_object($salesmanInfo) && is_object($customerInfo)) {
            return new CustomerMerchandiser([
                'merchandiser_id'    => $salesmanInfo->user_id,
                'customer_id'     => $customerInfo->user_id
            ]);
        }
    }
}
