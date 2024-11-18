<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use DB;

class PalletExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $StartDate, $EndDate, $type;
    public function __construct(String  $StartDate, String $EndDate, String $Type)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
        $this->type = $Type;
    }
    public function collection()
    {

        $start_date = $this->StartDate;
        $end_date = $this->EndDate;
        $my_type = $this->type;


        if ($my_type == "1") {
            $pallet = DB::table('palettes')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', '.salesman_id', 'left')
                ->join('users', 'users.id', '=', 'palettes.salesman_id', 'left')
                ->select('salesman_infos.salesman_code', 'users.firstname')
                ->selectRaw('SUM(CASE WHEN palettes.type="add" THEN palettes.qty ELSE 0 END)')
                ->selectRaw('SUM(CASE WHEN palettes.type="return" THEN palettes.qty ELSE 0 END)')
                ->selectRaw('SUM(CASE WHEN palettes.type="add" THEN palettes.qty ELSE 0 END) - SUM(CASE WHEN palettes.type="return" THEN palettes.qty ELSE 0 END)')
                ->groupBy('palettes.salesman_id');
            if ($start_date != '' && $end_date != '') {
                if ($start_date == $end_date) {
                    $pallet->whereBetween('palettes.created_at', $start_date);
                } else {
                    $pallet->whereBetween('palettes.created_at', [$start_date, $end_date]);
                }
            }
            $pallet = $pallet->where('palettes.organisation_id', auth()->user()->organisation_id)->get();

            return $pallet;
        } else {
            $pallet = DB::table('palettes')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', '.salesman_id', 'left')
                ->join('users', 'users.id', '=', 'palettes.salesman_id', 'left')
                ->join('items', 'items.id', '=', 'palettes.item_id', 'left')
                ->select(
                    'salesman_infos.salesman_code',
                    'users.firstname',
                    'palettes.date',
                    'items.item_code',
                    'items.item_name',
                    'palettes.qty',
                )
                ->selectRaw('(CASE WHEN palettes.type="add" THEN "Allocated" ELSE "Return" END)');
            if ($start_date != '' && $end_date != '') {
                if ($start_date == $end_date) {
                    $pallet->whereBetween('palettes.created_at', $start_date);
                } else {
                    $pallet->whereBetween('palettes.created_at', [$start_date, $end_date]);
                }
            }
            $pallet = $pallet->where('palettes.organisation_id', auth()->user()->organisation_id)->get();
            return $pallet;
        }
    }

    public function headings(): array
    {
        $type = $this->type;
        if ($type == "1") {
            return [
                "Salesman Code",
                "Salesman Name",
                "Total Allocated",
                "Total Return",
                "Pending",
            ];
        } else {
            return [
                "Salesman Code",
                "Salesman Name",
                "Date",
                "Item Code",
                "Item Name",
                "Qty",
                "Type",
            ];
        }
    }
}
