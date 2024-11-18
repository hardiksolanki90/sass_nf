<?php

namespace App\Exports;

use App\Model\BankInformation;
use App\Model\CustomerVisit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class JourneyPlanVisitExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $StartDate, $EndDate, $journey_plan_id;

    public function __construct($StartDate, $EndDate, int $journey_plan_id)
    {
        $this->StartDate = $StartDate;
        $this->EndDate  = $EndDate;
        $this->journey_plan_id  = $journey_plan_id;
    }

    public function collection()
    {
        $start_date = $this->StartDate;
        $end_date   = $this->EndDate;
        $journey_plan_id   = $this->journey_plan_id;

        $customerVisit_query = CustomerVisit::select(
            'id',
            'customer_id',
            'start_time',
            'end_time',
            'is_sequnece',
            'date',
            'added_on',
            'reason',
            'comment',
            'latitude',
            'longitude',
            'status'
        )
            ->with('customer')
            ->where('journey_plan_id', $journey_plan_id);

        if ($start_date != '' && $end_date != '') {
            $customerVisit_query->whereBetween('date', [$start_date, $end_date]);
        }

        $customerVisits = $customerVisit_query->get();

        $customerVisit = new Collection();

        $customer_code = 'N/A';
        foreach ($customerVisits as $key => $visit) {
            if (is_object($visit->customer)) {
                if ($visit->customer->customerInfo) {
                    $customer_code = $visit->customer->customerInfo->customer_code;
                }
            }

            $customerVisit->push((object)[
                'date'          => $visit->added_on,
                'Customer_Code' => $customer_code,
                'Customer_Name' => (!empty($visit->customer)) ? $visit->customer->getName() : "",
                'Start_Time'    => (!empty($visit->start_time)) ? $visit->start_time : "",
                'End Time'      => (!empty($visit->end_time)) ? $visit->end_time : "",
                'Longitude'     => (!empty($visit->longitude)) ? $visit->longitude : "",
                'Latitude'      => (!empty($visit->latitude)) ? $visit->latitude : "",
                'Is Sequence'   => ($visit->is_sequnece == 1) ? 'Yes' : "No",
                'Status'        => ($visit->status == 1) ? 'Yes' : "No",
                'Reason'        => (!empty($visit->reason)) ? $visit->reason : "",
                'Comment'       => (!empty($visit->comment)) ? $visit->comment : ""
            ]);
        }

        return $customerVisit;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Customer Code',
            'Customer Name',
            'Start Time',
            'End Time',
            'Longitude',
            'Latitude',
            'Is Sequence',
            'Status',
            'Reason',
            'Comment'
        ];
    }
}
