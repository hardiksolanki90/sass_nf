<?php

namespace App\Exports;

use App\Model\AssignInventory;
use App\Model\AssignInventoryCustomer;
use App\Model\AssignInventoryDetails;
use App\Model\AssignInventoryPost;
use App\Model\Item;
use App\Model\ItemUom;
use App\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignInventoryDetailsExport implements FromCollection,WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
	protected $StartDate,$EndDate;
	public function __construct(String  $StartDate,String $EndDate)
	{
		$this->StartDate = $StartDate;
		$this->EndDate = $EndDate;
	}
    public function collection()
    {
		$organisation_id = request()->user()->organisation_id;
		
		$start_date = $this->StartDate;
		$end_date = $this->EndDate;
		
		
		$assigninventory_query = DB::table('assign_inventories')->select('assign_inventories.activity_name','assign_inventories.valid_from','assign_inventories.valid_to','assign_inventories.status','customer_infos.customer_code','users.firstname','items.item_code','items.item_name','item_uoms.name','assign_inventory_details.capacity');
        $assigninventory_query->join("assign_inventory_customers", function ($join) {
            $join->on("assign_inventory_customers.assign_inventory_id", "=", "assign_inventories.id");
        });
        $assigninventory_query->join("assign_inventory_details", function ($join) {
            $join->on("assign_inventory_details.assign_inventory_id", "=", "assign_inventory_customers.assign_inventory_id");
        });
		$assigninventory_query->join("customer_infos", function ($join) {
            $join->on("customer_infos.id", "=", "assign_inventory_customers.customer_id");
        });
        $assigninventory_query->join("users", function ($join) {
            $join->on("users.id", "=", "customer_infos.user_id");
        });
		$assigninventory_query->join("item_uoms", function ($join) {
            $join->on("item_uoms.id", "=", "assign_inventory_details.item_uom_id");
        });
		$assigninventory_query->join("items", function ($join) {
            $join->on("items.id", "=", "assign_inventory_details.item_id");
        });
       
		         
		
		if($start_date!='' && $end_date!=''){
			$assigninventory_query->whereBetween('assign_inventories.created_at', [$start_date, $end_date]);
		}
		$assigninventory_query->where('assign_inventories.organisation_id', $organisation_id);
        $assign_inventory = $assigninventory_query->get();

		
		
		return $assign_inventory;
    }
	public function headings(): array
    {
        return [
            'BackStore Name',
			'Valid from',
			'Valid to',
			'Status',
			'Customer Code',
			'Customer Name',
			'Item',
			'Item Name',
			'Item UOM',
			'Model Stock Capacity'
        ];
    }
}
