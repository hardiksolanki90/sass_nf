<?php

namespace App\Exports;

use App\Model\Palette;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PalettesExport implements FromCollection, WithHeadings
{
    protected $StartDate, $EndDate;


    public function __construct(String $StartDate, String $EndDate)
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

        $palette_query = Palette::select(
            'date as Date',
            'concat(users.firstname, " ", users.lastname) as salesmanName',
            'salesman_infos.salesman_code as salesmanCode',
            'items.item_code as itemCode',
            'items.item_name as itemName',
            'qty',
            'type'
        )
            ->join('salesman_infos', function ($join) {
                $join->on('palettes.salesman_id', '=', 'salesman_infos.user_id');
            })
            ->join('users', function ($join) {
                $join->on('palettes.salesman_id', '=', 'users.id');
            })
            ->join('items', function ($join) {
                $join->on('palettes.item_id', '=', 'items.id');
            });

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $palette_query->where('date', $end_date);
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $palette_query->whereBetween('date', [$start_date, $endDate]);
            }
        }

        $palettes = $palette_query->get();
        return $palettes;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Salesman Code',
            'Salesman Name',
            'Item Code',
            'Item Name',
            'Qty',
            'Type'
        ];
    }
}
