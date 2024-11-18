<?php

namespace App\Exports;

use App\Model\CustomerWarehouseMapping;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class CustomerWarehouseMappingExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $cwms = CustomerWarehouseMapping::with(
            'customerInfo:id,user_id,customer_code',
            'lob:id,name,lob_code',
            'storageocation:id,name,code'
        )
            ->get();

        $cwmCollection = new Collection();

        if (count($cwms)) {
            foreach ($cwms as $cwm) {
                $cwmCollection->push((object) [
                    'Customer_code'         => model($cwm->customerInfo, 'customer_code'),
                    'lob_code'              => model($cwm->lob, 'lob_code'),
                    'storage_location_code' => model($cwm->storageocation, 'code'),
                ]);
            }
        }

        return $cwmCollection;
    }

    public function headings(): array
    {
        return [
            "Customer Code",
            "Lob",
            "Warehouse",
        ];
    }
}
