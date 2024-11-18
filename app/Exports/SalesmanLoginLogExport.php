<?php

namespace App\Exports;

use App\User;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoginLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesmanLoginLogExport implements FromCollection, WithHeadings
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

    public function collection()
    {
        $collections = $this->collections;
        return $collections;
    }

    public function headings(): array
    {
        return [
            'Date', 'Merchandiser Name', 'Merchandiser Code', 'Version', 'Device Name', 'Device IMEI Number '
        ];
    }
}
