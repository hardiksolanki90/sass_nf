<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoadingChartByWarehouseReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $loaddetails_export, $columns;

    public function __construct(object  $loaddetails_export, array $columns)
    {
        $this->loaddetails_export = $loaddetails_export;
        $this->columns = $columns;
    }
    
    public function collection()
    {
        $loaddetails_export = $this->loaddetails_export;
        return $loaddetails_export;
    }

    public function headings(): array
    {
        $columns = $this->columns;
         return $columns;
    }
}
