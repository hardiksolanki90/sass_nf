<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoadingChartFinalByRouteReportExport implements FromCollection,WithHeadings
{
    protected $details, $columns;

    public function __construct(object  $details, array $columns)
    {
        $this->details = $details;
        $this->columns = $columns;
    }

    public function collection()
    {
        $data = new Collection();
        if (count($this->details)) {
            foreach ($this->details as $value) {
                $data->push((object) [
                    "item_code" => $value->item_code,
                    "item_name" => $value->item_name,
                    "dmd_lower_upc" => $value->dmd_lower_upc,
                    "p_ref_pack" => $value->p_ref_pack,
                    "p_ref_pc" => $value->p_ref_pc,
                    "dmd_packs" => $value->dmd_packs,
                    "dmd_pcs" => $value->dmd_pcs,
                    "g_ret_pack" => $value->g_ret_pack,
                    "g_ret_pcs" => $value->g_ret_pcs,
                    "dmg_pcs" => $value->dmg_pcs,
                    "exp_pcs" => $value->exp_pcs,
                    "N_exp_pc" => $value->N_exp_pc,
                    "N_sales_packs" => $value->N_sales_packs,
                    "N_sales_pc" => $value->N_sales_pc
                ]);
            }
        }


        return $data;
    }

    /* private function data($users, $key, $lobData = null)
	{
		
	} */
    public function headings(): array
    {
        return $this->columns;
    }
}
