<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class SalesmanUnLoadExport implements FromCollection, WithHeadings
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

    public function collection()
    {
        $start_date = $this->StartDate;
        $end_date = $this->EndDate;

        $loadReq = DB::table('salesman_unloads')
            ->join('salesman_unload_details as details', 'details.salesman_unload_id', '=', 'salesman_unloads.id', 'left')
            ->join('items', 'items.id', '=', 'details.item_id', 'left')
            ->join('item_uoms', 'item_uoms.id', '=', 'details.item_uom', 'left')
            ->join('item_main_prices', 'item_main_prices.item_id', '=', 'items.id', 'left')
            ->join('item_uoms as secUOM', 'secUOM.id', '=', 'item_main_prices.item_uom_id', 'left')
            ->join('users as salesUser', 'salesUser.id', '=', 'salesman_unloads.salesman_id', 'left')
            ->join('salesman_infos as salesInfo', 'salesInfo.user_id', '=', 'salesman_unloads.salesman_id', 'left')
            ->join('routes', 'routes.id', '=', 'salesman_unloads.route_id', 'left')
            ->join('trips', 'trips.id', '=', 'salesman_unloads.trip_id', 'left')
            ->select(
                'salesman_unloads.created_at',
                'salesman_unloads.code',
                'salesUser.firstname as salesman_name',
                'salesInfo.salesman_code',
                'routes.route_name',
                'routes.route_code',
                'details.unload_type',
                'details.reason',
                'items.item_code',
                'items.item_name',
                'item_uoms.name as uom',
                'secUOM.name as secuoms',
                'item_main_prices.item_upc',
                'details.unload_qty'
            );

        if ($start_date != '' && $end_date == '') {
            $loadReq = $loadReq->whereDate('salesman_unloads.created_at', $start_date);
        } else if ($start_date != '' && $end_date != '') {
            $loadReq = $loadReq->whereBetween('salesman_unloads.created_at', [$start_date, $end_date]);
        }
        $loadReq = $loadReq->where('salesman_unloads.organisation_id', request()->user()->organisation_id)
            ->orderBy('salesman_unloads.created_at', 'desc');
        $loadReq = $loadReq->get();
        return $loadReq;
    }

    public function headings(): array
    {
        return [
            "Date",
            "Load Period Number",
            "Salesman",
            "Salesman Code",
            "Route",
            "Route Code",
            "Unload Type",
            "Unload Reason",
            "Item Code",
            "Item Name",
            "UOM",
            "Secondry UOM",
            "Secondry UPC",
            "Approve Qty"
        ];
    }
}
