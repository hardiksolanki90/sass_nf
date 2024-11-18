<?php

namespace App\Exports;

use App\Model\CustomerVisit;
use App\Model\OrganisationRoleAttached;
use App\Model\SalesmanInfo;
use App\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomCustomerVisitExport implements FromCollection, WithHeadings
{
    protected $StartDate, $EndDate, $email;

    public function __construct(String  $StartDate, String $EndDate, String $email)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
        $this->email = $email;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $start_date = $this->StartDate;
        $end_date = $this->EndDate;
        $email = $this->email;

        $customer_visits_collection = new Collection();

        $user = User::where('email', $email)->first();
        if ($user) {
            $org_role_attached = OrganisationRoleAttached::where('user_id', $user->id)->first();
            if ($org_role_attached) {
                $salesman_infos = SalesmanInfo::select('user_id')->whereIn('salesman_supervisor', explode(',', $org_role_attached->last_role_id))->get();
                if ($salesman_infos->count()) {
                    $customer_visit = CustomerVisit::select('customer_id', 'salesman_id', 'latitude', 'longitude', 'date')
                        ->with(
                            'customer:id,firstname,lastname',
                            'customer.customerInfo:id,user_id,customer_code,customer_address_1_lat,customer_address_1_lang,radius',
                            'salesman:id,firstname,lastname',
                            'salesman.salesmanInfo:id,user_id,salesman_code',
                        );
                    if ($start_date != '' && $end_date == '') {
                        $customer_visit->where('date', $start_date);
                    } else if ($start_date != '' && $end_date != '') {
                        $customer_visit->whereBetween('date', [$start_date, $end_date]);
                    }

                    $customer_visits = $customer_visit->whereIn('salesman_id', $salesman_infos)->get();

                    if ($customer_visits->count()) {
                        foreach ($customer_visits as $visit) {
                            $customer_code = '';
                            $customer_name = '';
                            $salesman_code = '';
                            $salesman_name = '';
                            $customer_lat = "";
                            $customer_long = "";
                            $radius = "";

                            if (is_object($visit->customer)) {
                                $customer_name = $visit->customer->firstname . ' ' . $visit->customer->lastname;

                                if (is_object($visit->customer->customerInfo)) {
                                    $customer_code = $visit->customer->customerInfo->customer_code;
                                    $customer_lat = $visit->customer->customerInfo->customer_address_1_lat;
                                    $customer_long = $visit->customer->customerInfo->customer_address_1_lang;
                                    $radius = $visit->customer->customerInfo->radius;
                                }
                            }

                            if (is_object($visit->salesman)) {
                                $salesman_name = $visit->salesman->firstname . ' ' . $visit->salesman->lastname;

                                if (is_object($visit->salesman->salesmanInfo)) {
                                    $salesman_code = $visit->salesman->salesmanInfo->salesman_code;
                                }
                            }

                            $latFrom = deg2rad($visit->latitude);
                            $lonFrom = deg2rad($visit->longitude);
                            $latTo = deg2rad($customer_lat);
                            $lonTo = deg2rad($customer_long);

                            $latDelta = $latTo - $latFrom;
                            $lonDelta = $lonTo - $lonFrom;

                            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
                            $distance = $angle * "6371000";

                            $dis = (!empty($distance)) ? $distance : "0";

                            $customer_visits_collection->push((object) [
                                'date'          => $visit->date,
                                'customer_code' => $customer_code,
                                'customer_name' => $customer_name,
                                'salesman_code' => $salesman_code,
                                'salesman_name' => $salesman_name,
                                'customer_lat'  => $customer_lat,
                                'customer_long' => $customer_long,
                                'visit_lat'     => $visit->latitude,
                                'visit_long'    => $visit->longitude,
                                'raduis'        => $radius,
                                'distance'      => $dis,
                                'deviation'     => ($radius < $dis) ? '1' : '0' 
                            ]);
                        }
                    }
                }
            }
        }
        return $customer_visits_collection;
    }

    public function headings(): array
    {
        return array(
            'Date',
            'Customer Code',
            'Customer Name',
            'Merchandiser Code',
            'Merchandiser Name',
            'Customer Lat',
            'Customer Long',
            'Merchandiser Lat',
            'Merchandiser Long',
            'Raduis',
            'Distance(In Meter)',
            'Deviation'
        );
    }
}
