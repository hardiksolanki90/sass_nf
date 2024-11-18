<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemBasePriceExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $StartDate, $EndDate;
    public function __construct(String  $StartDate, String $EndDate)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
    }


    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {

        $start_date = $this->StartDate;
        $end_date = $this->EndDate;

        $data = DB::table('item_base_prices')
            ->select(
                'storagelocations.code as Branch_Plant',
                'items.item_code as item_code',
                'item_uoms.name as umo_name',
                'price',
                'item_base_prices.start_date',
                'item_base_prices.end_date'
            )
            ->leftJoin('storagelocations', 'storagelocations.id', '=', 'item_base_prices.storage_location_id')
            ->leftJoin('items', 'items.id', '=', 'item_base_prices.item_id')
            ->leftJoin('item_uoms', 'item_uoms.id', '=', 'item_base_prices.item_uom_id');

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $data->where('item_base_prices.start_date', $start_date);
            } else {
                $data->where('item_base_prices.start_date', "<=", $start_date)
                    ->where('item_base_prices.end_date', "=>", $end_date);

                // $data->whereBetween('item_base_prices.start_date', [$start_date, $end_date]);
            }
        } else {
            $data->where('item_base_prices.start_date', "<=", date('Y-m-d'))
                ->where('item_base_prices.end_date', ">=", date('Y-m-d'));
        }

        $datas = $data->get();
        return $datas;
    }

    public function headings(): array
    {
        return [
            'Branch Plant',
            'Item Code',
            'Item Uom',
            'Price',
            'Start Date',
            'End Date'
        ];
    }
}
