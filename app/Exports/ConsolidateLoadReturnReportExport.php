<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ConsolidateLoadReturnReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $CustomerCollection, $columns;

    public function __construct(object  $ConsolidatedLoad, array $columns)
    {
        $this->ConsolidatedLoad = $ConsolidatedLoad;
        $this->columns = $columns;
    }

    public function collection()
    {
        $ConsolidatedLoad = $this->ConsolidatedLoad;

        return $ConsolidatedLoad;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }
}
