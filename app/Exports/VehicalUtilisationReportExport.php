<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VehicalUtilisationReportExport implements FromCollection, WithHeadings
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
        $VehicalUtilizationCollection = new Collection();
        if (count($this->details)) {
            foreach ($this->details as $value) {
                $VehicalUtilizationCollection->push((object) [
                    "Zone Name"             => $value->region_name,
                    "Total volume Orderd"           => $value->total_volume_orderd,
                    "Total volume delivered"          => $value->total_volume_delivered,
                    "No of Vehical"         => $value->no_of_vehical,
                    "No of Trips"  => $value->no_of_trips,
                    "No of Windows"     => $value->no_of_windows,
                    "Window to Delivery"             => $value->avg_windows_delivered,
                    "Utilazation"             => $value->Utilazation,
                    "avg case window"          => $value->avgcaswindow,
                    "avg_trips"     => $value->trip,
                    "Trip 1 Utilization" => $value->trip_1_utilization,
                    "Trip 2 Utilization" => $value->trip_2_utilization,
                    "Trip 3 Utilization" => $value->trip_3_utilization
                    
                ]);
            }
            return $VehicalUtilizationCollection;
        }

        return $VehicalUtilizationCollection;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }
}
