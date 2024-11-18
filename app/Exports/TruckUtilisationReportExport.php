<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TruckUtilisationReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $details, $columns;

    public function __construct(object  $details, array $columns)
    {
        $this->details = $details;

        $this->columns = $columns;
    }

    public function collection()
    {
        $truckUtilizationCollection = new Collection();
        if (count($this->details)) {
            foreach ($this->details as $value) {
                $truckUtilizationCollection->push((object) [
                    "transcation_date"      => $value->transcation_date,
                    "zone_name"             => $value->zone_name,
                    "salesman_code"         => $value->salesman_code,
                    "salesman_name"         => $value->salesman_name,
                    "trip_number"           => $value->trip_number,
                    "vehicle_code"          => $value->vehicle_code,
                    "no_of_vehical"         => $value->no_of_vehical,
                    "windows_to_delivered"  => $value->windows_to_delivered,
                    "windoes_less_case"     => $value->windoes_less_case,
                    "no_orders"             => $value->no_orders,
                    "order_qty"             => $value->order_qty,
                    "delivery_qty"          => $value->delivery_qty,
                    "windows_delivered"     => $value->windows_delivered,
                    "windows_delivered_per" => $value->windows_delivered_per,
                    "windows_less_delivery" => $value->windows_less_delivery,
                    "capacity_day"          => $value->capacity_day,
                    "Utilazation"           => $value->Utilazation,
                    "avgcaswindow"          => $value->avgcaswindow,
                    "trip"                  => $value->trip
                ]);
            }
            return $truckUtilizationCollection;
        }

        return $truckUtilizationCollection;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }
}
