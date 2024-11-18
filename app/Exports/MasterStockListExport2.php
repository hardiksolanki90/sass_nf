<?php

namespace App\Exports;

use App\Model\CustomerMerchandizer;
use App\Model\CustomerVisit;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\DistributionStock;
use App\Model\SalesmanInfo;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterStockListExport2 implements FromCollection, WithHeadings
{

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
        $StartDate = $this->StartDate;
        $EndDate = $this->EndDate;

        $customer_count = 0;
        $item_count = 0;
        $out_of_stock_count = 0;

        $merchandisers = SalesmanInfo::whereIn(
            'user_id',
            [
                "38", "39"
            ]
        )
            ->orderBy('user_id', 'desc')
            ->get();

        $msl = new Collection();

        $begin = new DateTime($StartDate);
        $end = new DateTime($EndDate);
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date = $dt->format("Y-m-d");

            if ($merchandisers->count()) {
                foreach ($merchandisers as $m) {

                    $customer_visits = CustomerVisit::where('salesman_id', $m->user_id)
                        // if ($StartDate != '' && $EndDate != '') {
                        //     if ($StartDate == $EndDate) {
                        //         $customer_visit->where('date', $StartDate);
                        //     } else {
                        //         $customer_visit->whereBetween('date', [$StartDate, $EndDate]);
                        //     }
                        // }

                        ->where('date', $date)
                        ->groupBy('customer_id')
                        ->get();
                    // $customer_merchandiser = CustomerMerchandizer::select('customer_id')
                    //     ->where('merchandiser_id', $m->user_id)
                    //     ->get();

                    if (count($customer_visits)) {
                        $customer_ids = $customer_visits->pluck('customer_id')->toArray();

                        $dms = DistributionModelStock::select('id')
                            ->whereIn('customer_id', $customer_ids)
                            ->get();

                        if (count($dms)) {
                            $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $dms)
                                ->where('is_deleted', 0)
                                ->get();

                            if (count($dmsd)) {
                                foreach ($dmsd as $dm) {

                                    $item_ids = $dmsd->pluck('item_id')->toArray();
                                    $item_count = count($item_ids);
                                    $customer_count = count($dms);
                                    $ds_qeury = DistributionStock::whereIn('customer_id', $customer_ids)
                                        ->where('is_out_of_stock', 1)
                                        ->where('salesman_id', $m->user_id);
                                    // if ($StartDate != '' && $EndDate != '') {
                                    //     if ($StartDate == $EndDate) {
                                    //         $ds_qeury->whereDate('created_at', $StartDate);
                                    //     } else {
                                    //         $ds_qeury->whereBetween('created_at', [$StartDate, $EndDate]);
                                    //     }
                                    // }
                                    $ds = $ds_qeury->first();

                                    // $out_of_stock_count = count($ds);

                                    $per = round(($out_of_stock_count / $item_count) * 100, 2);

                                    $msl->push((object) [
                                        'date'              => $date,
                                        'merchandiser_id'   => $m->salesman_code,
                                        'item_code'         => $dm->item->item_code,
                                        'customer_count'    => ($customer_count > 0) ? $customer_count : "0",
                                        'item_count'        => ($item_count > 0) ? $item_count : "0",
                                        'out_of_stock'      => "$ds->is_out_of_stock",
                                        'percentage'        => ($per > 0) ? $per : '0'
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $msl;
    }

    public function headings(): array
    {
        return [
            "Date",
            "Merchandiser Code",
            "Item Code",
            "No of customer",
            "Shelf Item Count",
            "Out of Stock",
            "Percentage"
        ];
    }
}
