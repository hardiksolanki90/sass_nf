<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PalletReportExport implements FromCollection,WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
	protected $palletreportexport,$columns;
	public function __construct(object  $palletreportexport,array $columns)
	{
		
		$this->palletreportexport = $palletreportexport;
		$this->columns = $columns;

		
	}
    public function collection()
    {
		$palletreportexport = $this->palletreportexport;
		

		return $palletreportexport;
    }
	public function headings(): array
    {
		$columns = $this->columns;
		
        return $columns;
    }
}
