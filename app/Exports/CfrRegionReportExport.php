<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeExport;

class CfrRegionReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $CustomerCollection, $columns;

    public function __construct(object  $CfrRegion, array $columns)
    {
        $this->CfrRegion = $CfrRegion;
        $this->columns = $columns;
    }
    public function collection()
    {
        $CfrRegion = $this->CfrRegion;

        return $CfrRegion;
    }

    public function headings(): array
    {
        $columns = $this->columns;

        return $columns;
    }

    public function title(): string
    {
        return 'DIFORTReport';
    }

    public function registerEvents(): array
    {
        return [
            // Handle by a closure.
            BeforeExport::class => function (BeforeExport $event) {
                $event->writer->getProperties()->setTitle('DIFORT Report');
            },
        ];
    }
}
