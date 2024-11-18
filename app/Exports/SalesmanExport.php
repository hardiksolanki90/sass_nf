<?php

namespace App\Exports;

use App\Model\SalesmanInfo;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class SalesmanExport implements FromCollection, WithHeadings
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

		$all_salesman = getSalesman(false);

		$users_q = SalesmanInfo::with(
			'user:id,uuid,organisation_id,usertype,firstname,lastname,email,mobile',
			'route:id,route_code,route_name,status',
			'salesmanRole:id,name,code,status',
			'salesmanType:id,name,code,status',
			'salesmanRange:id,salesman_id,customer_from,customer_to,order_from,order_to,invoice_from,invoice_to,collection_from,collection_to,credit_note_from,credit_note_to,unload_from,unload_to'
		)
			->where('salesman_type_id', 1);

		if ($start_date != '' && $end_date == '') {
			if ($start_date == $end_date) {
				$users_q->whereDate('created_at', $start_date);
			} else {
				$e_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');
				$users_q->whereBetween('created_at', [$start_date, $e_date]);
			}
		}

		if (count($all_salesman)) {
			$users_q->whereIn('user_id', $all_salesman);
		}

		$users = $users_q->get();


		$salesmanObj = new Collection;

		if (is_object($users)) {
			foreach ($users as $key => $user) {

				$salesmanObj->push((object) [
					"First_Name" 				=> model($user->user, 'firstname'),
					"Last_Name" 				=> model($user->user, 'lastname'),
					"Email" 					=> model($user->user, 'email'),
					"Mobile" 					=> model($user->user, 'mobile'),
					"Route" 					=> model($user->route, 'route_name'),
					"Status" 					=> ($user->status == 1) ? "Active" : "Inactive",
					"salesman_Type" 			=> model($user->salesmanType, 'name'),
					"salesman_Role" 			=> model($user->salesmanRole, 'name'),
					"salesman_Code" 			=> model($user, 'salesman_code'),
					"salesman_Supervisor" 		=> model($user->salesmanSupervisor, 'firstname'),
					// "salesman_Status" 			=> model($user, 'current_stage')
				]);
			}
		}
		return $salesmanObj;
	}

	public function headings(): array
	{
		return [
			"First Name",
			"Last Name",
			"Email",
			"Mobile",
			"Route",
			"Salesman Status",
			"Salesman Type",
			"Salesman Role",
			"Salesman Code",
			"Salesman Supervisor"
		];
	}
}
