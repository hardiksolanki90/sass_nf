<?php

namespace App\Exports;

use App\Model\ItemBranchPlant;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemBranchPlantExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return ItemBranchPlant::select(
            'items.item_code',
            'items.item_name',
            'lobs.name',
            'storagelocations.name',
            'storagelocations.code',
            DB::raw('IF(item_branch_plants.status > 0, "Active", "Inactive") as status')
        )
            ->join('lobs', function ($join) {
                $join->on('lobs.id', '=', 'item_branch_plants.lob_id');
            })
            ->join('items', function ($join) {
                $join->on('items.id', '=', 'item_branch_plants.item_id');
            })
            ->join('storagelocations', function ($join) {
                $join->on('storagelocations.id', '=', 'item_branch_plants.storage_location_id');
            })
            ->get();
    }

    public function headings(): array
    {
        return [
            "Item Code",
            "Item Name",
            "Warehouse",
            "Warehouse Code",
            "Status",
        ];
    }
}
