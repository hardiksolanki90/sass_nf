<?php

namespace App\Exports;

use App\User;
use App\Model\CustomerInfo;
use App\Model\SalesmanInfo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersExport implements FromCollection, WithHeadings
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

		$users = CustomerInfo::with(
			'user:id,organisation_id,usertype,firstname,lastname,email',
			'route:id,route_name',
			'channel:id,name',
			'region:id,region_name',
			'customerGroup:id,group_name',
			'salesOrganisation:id,name'
		);

		if ($start_date != '' && $end_date != '') {
			$users = $users->whereBetween('created_at', [$start_date, $end_date]);
		}

		$users = $users->get();
		if (is_object($users)) {
			foreach ($users as $key => $user) {
				if (isset($users[$key]->customer_code)) {
					$customerCode = $users[$key]->customer_code;
				} else {
					$customerCode = "";
				}
				$ERPCode = $users[$key]->erp_code;
				$officeAddres = $users[$key]->customer_address_1;
				$homeAddres = $users[$key]->customer_address_2;
				$city = $users[$key]->customer_city;
				$state = $users[$key]->customer_state;
				$zip = $users[$key]->customer_zipcode;
				$phone = $users[$key]->customer_phone;
				$balance = $users[$key]->balance;
				$credit_limit = $users[$key]->credit_limit;
				$credit_days = $users[$key]->credit_days;
				$profile_image = $users[$key]->profile_image;
				$current_stage = $users[$key]->current_stage;
				$status = $users[$key]->status;
				$ship_to_party = $users[$key]->ship_to_party;
				$bill_to_payer = $users[$key]->bill_to_payer;
				$sold_to_party = $users[$key]->sold_to_party;
				$payer = $users[$key]->payer;
				$merchandiser = $users[$key]->merchandiser_id;

				$routeName = "";
				if (is_object($users[$key]->route)) {
					$routeName = $users[$key]->route->route_name;
				}

				$regionName = "";
				if (is_object($users[$key]->region)) {
					$regionName = $users[$key]->region->region_name;
				}

				unset($users[$key]->id);
				unset($users[$key]->uuid);
				unset($users[$key]->organisation_id);
				unset($users[$key]->user_id);
				unset($users[$key]->erp_code);
				unset($users[$key]->customer_code);
				unset($users[$key]->customer_address_1);
				unset($users[$key]->customer_address_2);
				unset($users[$key]->customer_city);
				unset($users[$key]->customer_state);
				unset($users[$key]->customer_zipcode);
				unset($users[$key]->customer_phone);
				unset($users[$key]->balance);
				unset($users[$key]->credit_days);
				unset($users[$key]->profile_image);
				unset($users[$key]->current_stage);
				unset($users[$key]->credit_limit);
				unset($users[$key]->region_id);
				unset($users[$key]->route_id);
				unset($users[$key]->payment_term_id);
				unset($users[$key]->customer_group_id);
				unset($users[$key]->sales_organisation_id);
				unset($users[$key]->channel_id);
				unset($users[$key]->customer_category_id);
				unset($users[$key]->customer_type_id);
				unset($users[$key]->current_stage_comment);
				unset($users[$key]->status);
				unset($users[$key]->created_at);
				unset($users[$key]->updated_at);
				unset($users[$key]->deleted_at);

				unset($users[$key]->ship_to_party);
				unset($users[$key]->sold_to_party);
				unset($users[$key]->payer);
				unset($users[$key]->bill_to_payer);
				unset($users[$key]->customer_address_1_lat);
				unset($users[$key]->customer_address_1_lang);
				unset($users[$key]->customer_address_2_lat);
				unset($users[$key]->customer_address_2_lang);
				unset($users[$key]->merchandiser_id);

				if (is_object($users[$key]->user)) {
					$users[$key]->firstname = $users[$key]->user->firstname;
					$users[$key]->lastname = $users[$key]->user->lastname;
					$users[$key]->email = $users[$key]->user->email;
				}
				$users[$key]->customerCode = $customerCode;
				$users[$key]->erpCode = $ERPCode;

				$users[$key]->officeAddress = $officeAddres;
				$users[$key]->homeAddress = $homeAddres;
				$users[$key]->city = $city;
				$users[$key]->state = $state;
				$users[$key]->zip = $zip;
				$users[$key]->phone = $phone;

				$users[$key]->merchandiser = 0;
				if ($merchandiser) {
					$m = SalesmanInfo::where('user_id', $merchandiser)->first();
					if (is_object($m)) {
						$users[$key]->merchandiser = $m->salesman_code;
					}
				}
				// $users[$key]->merchandiser = $merchandiser;

				$users[$key]->route = 0;
				if ($routeName) {
					$users[$key]->route = $routeName;
				}

				$users[$key]->channelName = 0;
				if (is_object($users[$key]->channel)) {
					$users[$key]->channelName = $users[$key]->channel->name;
				}

				$users[$key]->regionName =  0;
				if ($regionName) {
					$users[$key]->regionName = $regionName;
				}

				$users[$key]->groupName = 0;
				if (is_object($users[$key]->customerGroup)) {
					$users[$key]->groupName = $users[$key]->customerGroup->group_name;
				}

				$users[$key]->salesOrganisationName = "N/A";
				if (is_object($users[$key]->salesOrganisation)) {
					$users[$key]->salesOrganisationName = $users[$key]->salesOrganisation->name;
				}

				$users[$key]->Balance = $balance;
				$users[$key]->creditLimit = $credit_limit;
				if ($customerCode) {
					$customer_info = CustomerInfo::where('customer_code', $customerCode)->first();
					$ship_to_party = "";
					$bill_to_payer = "";
					$sold_to_party = "";
					$payer = "";
					$ship_to_party_obj = CustomerInfo::find($ship_to_party);
					if ($ship_to_party_obj) {
						$ship_to_party = $ship_to_party_obj->customer_code;
					}
					$bill_to_payer_obj = CustomerInfo::find($bill_to_payer);
					if ($bill_to_payer_obj) {
						$bill_to_payer = $bill_to_payer_obj->customer_code;
					}
					$sold_to_party_obj = CustomerInfo::find($sold_to_party);
					if ($sold_to_party_obj) {
						$sold_to_party = $sold_to_party_obj->customer_code;
					}
					$payer_obj = CustomerInfo::find($payer);
					if ($payer_obj) {
						$payer = $payer_obj->customer_code;
					}
				}
				$users[$key]->creditDays = $credit_days;
				$users[$key]->shipToParty = $ship_to_party;
				$users[$key]->billToPayer = $bill_to_payer;
				$users[$key]->soldToParty = $sold_to_party;
				$users[$key]->payer = $payer;

				$users[$key]->status = "No";
				if ($status) {
					$users[$key]->status = "Yes";
				}

				$users[$key]->currentStage = $current_stage;
				$users[$key]->profileImage = $profile_image;
			}
		}

		return $users;
	}

	public function headings(): array
	{
		return [
			"First Name",
			"Last Name",
			"Email",
			"Customer Code",
			"ERP Code",
			"Office Address",
			"Home Address",
			"City",
			"State",
			"Zipcode",
			"Phone",
			"Merchandiser Code",
			"Route Name",
			"Channel Name",
			"Region Name",
			"Group Name",
			"sales Organisation Name",
			"Balance",
			"Credit Limit",
			"Credit Days",
			"Ship To Party",
			"Bill To Payer",
			"Sold To Party",
			"Payer",
			"Status",
			"Current Stage",
			"Profile Image"
			// "Password",
			// "Mobile",
			// "Country",
			// "Status",
			// "Region",
			// "Group Name",
			// "Sales Organisation",
			// "Route",
			// "Channel",
			// "Customer Category",
			// "Customer Type"
		];
	}
}
