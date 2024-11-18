<?php

namespace App\Exports;

use App\Model\SalesmanInfo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MechandiserExport implements FromCollection, WithHeadings
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
        );
        if ($start_date != '' && $end_date != '') {
            $users_q->whereBetween('created_at', [$start_date, $end_date]);
        }

        if (count($all_salesman)) {
            $users_q->whereIn('user_id', $all_salesman);
        }

        $users = $users_q->get();


        $salesmanObj = new Collection;

        if (is_object($users)) {
            foreach ($users as $key => $user) {

                $salesmanObj->push((object) [
                    "First_Name"                 => model($user->user, 'firstname'),
                    "Last_Name"                 => model($user->user, 'lastname'),
                    "Email"                     => model($user->user, 'email'),
                    "Mobile"                     => model($user->user, 'mobile'),
                    "Route"                     => model($user->route, 'route_name'),
                    "Status"                     => ($user->status == 1) ? "Yes" : "No",
                    "Merchandiser_Type"         => model($user->salesmanType, 'name'),
                    "Merchandiser_Role"         => model($user->salesmanRole, 'name'),
                    "Merchandiser_Code"         => model($user, 'salesman_code'),
                    "Merchandiser_Supervisor"     => model($user->salesmanSupervisor, 'firstname'),
                    'customer_from'             => model($user->salesmanRange, 'customer_from'),
                    'customer_to'                 => model($user->salesmanRange, 'customer_to'),
                    "Order_From"                 => model($user->salesmanRange, 'order_from'),
                    "Order_To"                     => model($user->salesmanRange, 'order_to'),
                    "Invoice_From"                 => model($user->salesmanRange, 'invoice_from'),
                    "Invoice_To"                 => model($user->salesmanRange, 'invoice_to'),
                    "Collection_From"             => model($user->salesmanRange, 'collection_from'),
                    "Collection_To"             => model($user->salesmanRange, 'collection_to'),
                    "Return_From"                 => model($user->salesmanRange, 'credit_note_from'),
                    "Return_To"                 => model($user->salesmanRange, 'credit_note_to'),
                    "Unload_From"                 => model($user->salesmanRange, 'unload_from'),
                    "Unload_To"                 => model($user->salesmanRange, 'unload_to'),
                    "Merchandiser_Status"         => model($user, 'current_stage')
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
            "Status",
            "Merchandiser Type",
            "Merchandiser Role",
            "Merchandiser Code",
            "Merchandiser Supervisor",
            'Customer From',
            'Customer To',
            "Order From",
            "Order To",
            "Invoice From",
            "Invoice To",
            "Collection From",
            "Collection To",
            "Return From",
            "Return To",
            "Unload From",
            "Unload To",
            "Merchandiser Status"
        ];
    }
}
