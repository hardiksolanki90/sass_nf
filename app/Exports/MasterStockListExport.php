<?php

namespace App\Exports;

use App\Model\CustomerMerchandizer;
use App\Model\CustomerVisit;
use App\Model\Distribution;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\DistributionStock;
use App\Model\SalesmanInfo;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MasterStockListExport implements FromCollection, WithHeadings
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

        $merchandisers = SalesmanInfo::orderBy('user_id', 'desc')
            ->get();
        $msl = new Collection();

        $begin = new DateTime($StartDate);
        if ($StartDate == $EndDate) {
            $e = Carbon::parse($EndDate)->addDay(1)->format('Y-m-d');
            $end = new DateTime($e);
        } else {
            $end = new DateTime($EndDate);
        }
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);


        // Date Foreach
        foreach ($period as $dt) {
            $date = $dt->format("Y-m-d");
            if ($merchandisers->count()) {
                // Merchandiser
                foreach ($merchandisers as $m) {

                    $customer_visit = CustomerVisit::where('salesman_id', $m->user_id);
                    if ($StartDate != '' && $EndDate != '') {
                        if ($StartDate == $EndDate) {
                            $customer_visit->where('date', $StartDate);
                        } else {
                            $customer_visit->whereBetween('date', [$StartDate, $EndDate]);
                        }
                    }

                    $customer_visits = $customer_visit->where('date', $date)
                        ->groupBy('customer_id')
                        ->get();

                    // $customer_merchandiser = CustomerMerchandizer::select('customer_id')
                    //     ->where('merchandiser_id', $m->user_id)
                    //     ->get();

                    if (count($customer_visits)) {
                        $customer_ids = $customer_visits->pluck('customer_id')->toArray();
                        // Customer Where model stock
                        $dis = Distribution::select('id')->get();
                        $dms = DistributionModelStock::select('id', 'customer_id')
                            ->whereIn('distribution_id', $dis)
                            ->whereIn('customer_id', $customer_ids)
                            ->get();

                        if (count($dms)) {
                            foreach ($dms as $d_m_s) {
                                // Details
                                $dmsd = DistributionModelStockDetails::where('distribution_model_stock_id', $d_m_s->id)
                                    ->where('is_deleted', 0)
                                    ->get();


                                if (count($dmsd)) {
                                    foreach ($dmsd as $dm) {

                                        $ds_qeury = DistributionStock::where('customer_id', $d_m_s->customer_id)
                                            ->where('salesman_id', $m->user_id)
                                            ->where('item_id', $dm->item_id);
                                        if ($StartDate != '' && $EndDate != '') {
                                            if ($StartDate == $EndDate) {
                                                $ds_qeury->whereDate('created_at', $StartDate);
                                            } else {
                                                $ds_qeury->whereBetween('created_at', [$StartDate, $EndDate]);
                                            }
                                        }

                                        $ds = $ds_qeury->first();

                                        $msl->push((object) [
                                            'date'              => $date,
                                            'merchandiser_id'   => $m->salesman_code,
                                            'customer_code'     => model($d_m_s->customerInfo, 'customer_code'),
                                            'item_code'         => $dm->item->item_code,
                                            'check_in'          => is_object($ds) ? "Yes" : "No",
                                            'out_of_stock'      => isset($ds->is_out_of_stock) ? "Yes" : "No",
                                        ]);
                                    }
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
            "Customer Code",
            "Item Code",
            "Check in",
            "Out of Stock"
        ];
    }
}
