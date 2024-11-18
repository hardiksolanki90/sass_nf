<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GlobalReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $CustomerCollection, $columns;

    public function __construct(object  $details, array $columns)
    {
        $this->details = $details;
        $this->columns = $columns;
    }

    public function collection()
    {
        $details = $this->details;

        return $details;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }
}
