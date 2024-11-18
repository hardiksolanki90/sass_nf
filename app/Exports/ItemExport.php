<?php

namespace App\Exports;

use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\ItemMajorCategory;
use App\Model\ItemGroup;
use App\Model\Brand;
use App\Model\ItemUom;
use Maatwebsite\Excel\Concerns\FromCollection;
use DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemExport implements FromCollection, WithHeadings
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

		$item = Item::with(
			'itemUomLowerUnit:id,name,code',
			'ItemMainPrice:id,item_id,item_upc,item_uom_id,item_price,purchase_order_price,stock_keeping_unit,item_shipping_uom,is_secondary',
			'ItemMainPrice.itemUom:id,name,code',
			'itemMajorCategory:id,uuid,name',
			'itemGroup:id,uuid,name,code,status',
			'brand:id,uuid,brand_name,status'
		);

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$item->whereDate('created_at', $start_date);
			} else {
				$item->whereBetween('created_at', [$start_date, $end_date]);
			}
		}

		$items = $item->get();

		$itemCollection = new Collection();

		if (count($items)) {
			foreach ($items as $i) {

				$uom_1 = '';
				$uom_upc_1 = '';

				$uom_2 = '';
				$uom_upc_2 = '';

				$uom_3 = '';
				$uom_upc_3 = '';

				if (count($i->itemMainPrice)) {
					foreach ($i->itemMainPrice as $k => $main_price) {
						if ($k == 0 && $main_price->is_secondary == 1) {
							$uom_1 =  model($main_price->itemUom, 'name');
							$uom_upc_1 = model($main_price, 'item_upc');
						}
						if ($k == 1) {
							$uom_2 =  model($main_price->itemUom, 'name');
							$uom_upc_2 = model($main_price, 'item_upc');
						}
						if ($k == 2) {
							$uom_3 =  model($main_price->itemUom, 'name');
							$uom_upc_3 = model($main_price, 'item_upc');
						}
					}
				}

				$itemCollection->push((object) [
					'item_code' => $i->item_code,
					'item_name' => $i->item_name,
					'item_description' => $i->item_description,
					'item_barcode' => $i->item_barcode,
					'item_weight' => $i->item_weight,
					'item_shelf_life' => $i->item_shelf_life,
					'is_tax_apply' => ($i->is_tax_apply === 1) ? "Yes" : "No",
					'item_vat_percentage' => $i->item_vat_percentage,
					// 'is_item_excise' => ($i->is_item_excise === 1) ? "Yes" : "No",
					// 'item_excise_uom_id' => model($i->itemExciseUom, 'name'),
					'item_excise' => $i->item_excise,
					'item_major_category' => model($i->itemMajorCategory, 'name'),
					'item_group' => model($i->itemGroup, 'name'),
					'brand_name' => model($i->brand, 'brand_name'),
					'lower_item_uom' => model($i->itemUomLowerUnit, 'name'),
					'lower_unit_item_upc' => $i->lower_unit_item_upc,
					'uom_1' => $uom_1,
					'uom_upc_1' => $uom_upc_1,
					'uom_2' => $uom_2,
					'uom_upc_2' => $uom_upc_2,
					'uom_3' => $uom_3,
					'uom_upc_3' => $uom_upc_3,
					'status' => ($i->status == 1) ? "Active" : "Inactive"
				]);
			}
		}

		return $itemCollection;
	}

	public function headings(): array
	{
		return [
			"Item code",
			"Item name",
			"Item description",
			"Item barcode",
			"Item weight",
			"Item shelf life",
			"Is tax apply",
			"Item vat percentage",
			// "Is Excise Apply",
			// "Excise UOM",
			"Excise Amount",
			"Item major category",
			"Item group",
			"Brand name",
			"Lower Unit",
			"Lower unit item upc",
			"Secondary",
			"Secondary UPC",
			"Third",
			"Third UPC",
			"Other",
			"Other UPC",
			"Status"
		];
	}
}
