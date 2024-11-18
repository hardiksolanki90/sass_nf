<?php

namespace App\Imports;

use App\Model\Item;
use App\Model\ItemMainPrice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;

class ItemMinimumImport implements ToModel
{
    /**
     * @param array $row
     */
    public function model(array $row)
    {
        if ($row[0] != "Item Code" && $row[0] != "") {
            $item = Item::where('item_code', $row[0])->first();
            if ($item) {

                $imp = ItemMainPrice::where('item_id', $item->id)
                    ->where('is_secondary', 1)
                    ->first();

                $item->is_tax_apply         = ($row[1] > 0) ? 1 : 0;
                $item->item_vat_percentage  = $row[1];
                $item->item_excise          = ($row[2] > 0) ? $row[2] : 0;
                $item->item_excise_uom_id   = (is_object($imp)) ? $imp->item_uom_id : $item->item_excise_uom_id;
                $item->is_item_excise       = ($row[2] > 0) ? 1 : 0;
                $item->save();
            }
        }
    }
}
