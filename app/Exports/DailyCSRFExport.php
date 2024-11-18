<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DailyCSRFExport implements FromCollection, WithHeadings
{

    /**
     * @return \Illuminate\Support\Collection
     */
    protected $CustomerCollection, $columns;

    public function __construct(object  $CustomerCollection, array $columns)
    {
        $this->CustomerCollection = $CustomerCollection;
        $this->columns = $columns;
    }


    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $CustomerCollection = $this->CustomerCollection;

        return $CustomerCollection;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }
}
