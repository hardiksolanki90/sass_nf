<?php

namespace App\Exports;

use App\User;
use App\Model\LoadRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use DB;

class LoadRequestExport implements FromCollection, WithHeadings
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

		$loadReq = DB::table('load_requests')
			->join('load_request_details as details', 'details.load_request_id', '=', 'load_requests.id', 'left')
			->join('items', 'items.id', '=', 'details.item_id', 'left')
			->join('users as salesUser', 'salesUser.id', '=', 'load_requests.salesman_id', 'left')
			->join('salesman_infos as salesInfo', 'salesInfo.user_id', '=', 'load_requests.salesman_id', 'left')
			->join('routes', 'routes.id', '=', 'load_requests.route_id', 'left')

			->join('trips', 'trips.id', '=', 'load_requests.trip_id', 'left')
			->select('load_requests.created_at', 'salesUser.firstname as salesman_name', 'salesInfo.salesman_code', 'load_requests.load_number', 'load_requests.load_type', 'routes.route_name', 'routes.route_code', 'load_requests.current_stage', 'load_requests.status', 'details.qty', 'details.requested_qty', 'items.item_name', 'items.item_code');

		if ($start_date != '' && $end_date == '') {
			$loadReq = $loadReq->whereDate('load_requests.created_at', $start_date);
		} else if ($start_date != '' && $end_date != '') {
			$loadReq = $loadReq->whereBetween('load_requests.created_at', [$start_date, $end_date]);
		}
		$loadReq = $loadReq->orderBy('load_requests.created_at', 'desc');
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
			"Salesman",
			"Salesman Code",
			"Load No.",
			"Load Type",
			"Route",
			"Route Code",
			"Approval",
			"Status",
			"Qty",
			"Requested Qty",
			"Item Name",
			"Item Code"
		];
	}
}
