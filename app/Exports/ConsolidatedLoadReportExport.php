<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeExport;

class ConsolidatedLoadReportExport implements FromCollection, WithHeadings, WithTitle, WithEvents
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

    public function title(): string
    {
        return 'Consolidate Load Report';
    }

    public function registerEvents(): array
    {
        return [
            // Handle by a closure.
            BeforeExport::class => function (BeforeExport $event) {
                $event->writer->getProperties()->setTitle('Consolidate Load Report');
            },
        ];
    }
}
