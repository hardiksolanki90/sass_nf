<?php

namespace App\Exports;

use App\Model\CustomerKamMapping;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerKsmKamMappingExport implements FromCollection, WithHeadings
{

    /**
     * @return \Illuminate\Support\Collection
     */
    protected $StartDate, $EndDate;

    public function __construct(String  $StartDate, String $EndDate)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $s_date = $this->StartDate;
        $e_date = $this->EndDate;

        $ckm = CustomerKamMapping::select(
            'ci.customer_code as customerCode',
            DB::raw('concat(kas.firstname , " ", kas.lastname)'),
            DB::raw('concat(kam.firstname , " ", kam.lastname)'),
        )
            ->withoutGlobalScope('organisation_id')
            ->join('customer_infos as ci', function ($join) {
                $join->on('ci.user_id', '=', 'customer_kam_mappings.customer_id');
            })
            ->join('users as kam', function ($join) {
                $join->on('kam.id', '=', 'customer_kam_mappings.kam_id');
            })
            ->join('users as kas', function ($join) {
                $join->on('kas.id', '=', 'customer_kam_mappings.kas_id');
            });

        if ($s_date != "" && $e_date != "") {
            if ($s_date == $e_date) {
                $ckm->whereDate('created_at', $s_date);
            } else {
                $ckm->whereBetween('created_at', [$s_date, $e_date]);
            }
        }

        $ckms = $ckm->get();

        return $ckms;
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'KSM',
            'KAM'
        ];
    }
}
