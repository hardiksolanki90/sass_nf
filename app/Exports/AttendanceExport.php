<?php

namespace App\Exports;

use App\Model\BankInformation;
use App\Model\Trip;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromCollection, WithHeadings
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

        $all_salesman = getSalesman();

        $trips_query = Trip::select('id', 'salesman_id', 'trip_start_date', 'trip_start_time', 'trip_end_time')
            ->with(
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code'
            )
            ->whereIn('salesman_id', $all_salesman);

        if (($end_date == $start_date) && !empty($start_date)) {
            $trips_query->whereDate('trip_start_date', $start_date);
        } else if ($end_date) {
            $start_date = date('Y-m-d', strtotime('-1 days', strtotime($start_date)));
            $trips_query->whereBetween('trip_start_date', [$start_date, $end_date]);
        } else {
            $trips_query->whereDate('trip_start_date', $start_date);
        }

        $trips = $trips_query->orderBy('id', 'desc')->get();

        return $trips;
    }
    public function headings(): array
    {
        return [
            'Date',
            'Merchandiser Code',
            'Merchandiser Name',
            'JP',
            'Check In',
            'Check Out'
        ];
    }
}
