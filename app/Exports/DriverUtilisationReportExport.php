<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DriverUtilisationReportExport implements FromCollection, WithHeadings
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
                    "zone_name"             => $value->zone_name,
                    "salesman_code"         => $value->salesman_code,
                    "salesman_name"         => $value->salesman_name,
                    "trucks_assigned"       => $value->trucks_assigned,
                    "trips_travelled"       => $value->trips_travelled,
                    "loaded_qty"            => $value->loaded_qty,
                    "invoice_qty"           => $value->invoice_qty,
                    "delivery_rate"         => $value->delivery_rate . "%",
                    "on_time_delivery_rate" => $value->on_time_delivery_rate . "%",
                    "pod_submit"            => $value->pod_submit . "%",
                    "capacity_trip"         => $value->capacity_trip,
                    "utilization"           => $value->utilization . "%",
                    "trip_truck"            => $value->trip_truck,
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
