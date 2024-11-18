<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderDetailsReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $order_details_export, $columns;

    public function __construct(object  $order_details_export, array $columns)
    {
        $this->order_details_export = $order_details_export;
        $this->columns = $columns;
    }
    
    public function collection()
    {
        $order_details_export = $this->order_details_export;
        return $order_details_export;
    }

    public function headings(): array
    {
        $columns = $this->columns;
         return $columns;
    }
}
