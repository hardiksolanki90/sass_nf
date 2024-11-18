<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
class SalesmanLoadExport implements FromCollection, WithHeadings
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
		
		$loadReq = DB::table('salesman_loads')
		->join('salesman_load_details as details', 'details.salesman_load_id', '=', 'salesman_loads.id','left')		
		->join('depots', 'depots.id', '=', 'salesman_loads.depot_id','left')		
		->join('items', 'items.id', '=', 'details.item_id','left')		
		->join('users as salesUser', 'salesUser.id', '=', 'salesman_loads.salesman_id','left')		 		 		
		 ->join('salesman_infos as salesInfo', 'salesInfo.user_id', '=', 'salesman_loads.salesman_id','left')
		 ->join('routes', 'routes.id', '=', 'salesman_loads.route_id','left')
		 ->join('trips', 'trips.id', '=', 'salesman_loads.trip_id','left')
		 ->select('salesman_loads.created_at','salesman_loads.load_number','depots.depot_name', 'salesUser.firstname as salesman_name','salesInfo.salesman_code', 'routes.route_name','routes.route_code','details.load_qty','details.requested_qty','items.item_name','items.item_code');

		if($start_date!= '' && $end_date == ''){
			$loadReq = $loadReq->whereDate('salesman_loads.created_at', $start_date);
		} else if($start_date != '' && $end_date != ''){
			$loadReq = $loadReq->whereBetween('salesman_loads.created_at', [$start_date, $end_date]);
		}
		 $loadReq = $loadReq->where('salesman_unloads.organisation_id', request()->user()->organisation_id)->orderBy('salesman_loads.created_at', 'desc');
         $loadReq = $loadReq->get();         
         return $loadReq;		
	}	

	/* private function data($users, $key, $lobData = null)
	{
		
	} */
	public function headings(): array
	{
		return [
			"Date",							
			"Load Period Number",					
			"Depot",
			"Salesman",
			"Salesman Code",
			"Route",						
			"Route Code",											
			"Approve Qty",
			"Requested Qty",
			"Item Name",
			"Item Code"
		];
	}
}
