<?php
// app/Imports/CustomersImport.php
namespace App\Imports;

use App\Model\CustomerInfo;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomersImport implements ToModel, WithHeadingRow
{
    public $updatedRecords = 0;

    public function model(array $row)
    {
        $customer = CustomerInfo::where('customer_code', $row['customer_code'])->first();

        if ($customer) {
            $originalLat = $customer->customer_address_1_lat;
            $originalLon = $customer->customer_address_1_lang;

            if ($originalLat != $row['customer_latitude'] || $originalLon != $row['customer_longitude']) {
                $customer->update([
                    'customer_address_1_lat' => $row['customer_latitude'],
                    'customer_address_1_lang' => $row['customer_longitude'],
                    'customer_address_2_lat' => $row['customer_latitude'],
                    'customer_address_2_lang' => $row['customer_longitude'],
                ]);
                $this->updatedRecords++;
            }
        }

        return $customer;
    }
}
