<?php

namespace App\Exports;

use App\Model\CustomerRegion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerRegionMappingExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $cwms = CustomerRegion::with(
            'customerInfo:id,user_id,customer_code',
            'zone:id,name'
        )
            ->get();

        $cwmCollection = new Collection();

        if (count($cwms)) {
            foreach ($cwms as $cwm) {
                $cwmCollection->push((object) [
                    'Customer_code' => model($cwm->customerInfo, 'customer_code'),
                    'zone'          => model($cwm->zone, 'name')
                ]);
            }
        }

        return $cwmCollection;
    }

    public function headings(): array
    {
        $columns = ['Customer Code', 'Zone'];

        return $columns;
    }
}
