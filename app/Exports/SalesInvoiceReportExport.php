<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesInvoiceReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $details_export, $columns;

    public function __construct(object  $details_export, array $columns)
    {
        $this->details_export = $details_export;
        $this->columns = $columns;
    }
    
    public function collection()
    {
        $details_export = $this->details_export;
        return $details_export;
    }

    public function headings(): array
    {
        $columns = $this->columns;

         return $columns;
    }
}
