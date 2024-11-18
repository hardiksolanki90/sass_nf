<?php

namespace App\Imports;

use App\Model\Item;
use App\Model\ItemBasePrice;
use App\Model\ItemUom;
use App\Model\Storagelocation;
use Maatwebsite\Excel\Concerns\ToModel;

class ItemBasePriceImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return ItemBasePrice|null
     */
    public function model(array $row)
    {
        if ($row[0] != "WarehouseCode" && $row[0] != "") {

            $storage_location   = Storagelocation::where('code', $row[0])->first();
            $item               = Item::where('item_code', $row[1])->first();
            $itemUom            = ItemUom::where('name', $row[2])->first();
            $price              = $row[3];
            $start_date         = $row[4];
            $end_date           = $row[5];

            if (
                $storage_location &&
                $item &&
                $itemUom
            ) {

                return ItemBasePrice::updateOrCreate(
                    ['storage_location_id' => $storage_location->id, 'item_id' => $item->id, 'item_uom_id' => $itemUom->id],
                    [
                        'storage_location_id'   => $storage_location->id,
                        'warehouse_id'          => getWarehuseBasedOnStorageLoacation($storage_location->id, false),
                        'item_id'               => $item->id,
                        'item_uom_id'           => $itemUom->id,
                        'price'                 => $price,
                        'start_date'            => $start_date,
                        'end_date'              => $end_date
                    ]
                );
            }
        }
    }
}
