<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderSCReportExport implements FromCollection, WithHeadings
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
                    "order_no"              => $value->order_no,
                    "order_type"            => "SA",
                    "sold_to"               => $value->customer_code,
                    "sold_name"             => $value->customer_name,
                    "item_code"             => $value->item_code,
                    "item_name"             => $value->item_name,
                    "line_type"             => "S",
                    "cancel_order"          => $value->cancel_reason,
                    "order_qty"             => $value->order_qty,
                    "load_qty"              => $value->load_qty ,
                    "cancel_qty"            => $value->cancel_qty,
                    "invoice_qty"           => $value->invoice_qty,
                    "spot_return"           => $value->spot_return,
                    "uom"                   => $value->item_uom,
                    "extended_amt"          => $value->extend_amt,
                    "order_date"            => $value->order_date,
                    "delivery_date"         => $value->delivery_date,
                    "lpo_no"                => $value->customer_lpo,
                    "branch_plant"          => $value->	branch_plant,
                    "invoice_no"            => $value->invoice_no,
                    "invoice_date"          => $value->invoice_date,
                    "load_date"          => $value->load_date,
                    "shipment_date"          => $value->shipment_time,
                    "on_hold"               => $value->on_hold,
                    "Driver"                => $value->	driver,
                    "Driver Reason"         => $value->driver_reason,
                    "actual_trip"         => $value->actual_trip,
                    "trip"         => $value->trip,
                    "vehicle"         => $value->vehicle,
                    "Helper1"         => $value->helper1,
                    "Helper2"         => $value->helper2,
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
