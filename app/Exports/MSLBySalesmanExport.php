<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MSLBySalesmanExport implements FromCollection, WithHeadings
{
    protected $collections, $columns;

    /**
     * @return \Illuminate\Support\Collection
     */
    public function __construct($collections, array $columns)
    {
        $this->collections = $collections;
        $this->columns = $columns;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $collections = $this->collections;
        return $collections;
    }

    public function headings(): array
    {
        $columns = $this->columns;
        return $columns;
    }
}
