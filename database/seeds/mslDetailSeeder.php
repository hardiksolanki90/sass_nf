<?php

use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerVisit;
use App\Model\Distribution;
use App\Model\DistributionStock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class mslDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $begin = new DateTime("2022-02-20");
        if ("2022-02-20" == "2022-02-28") {
            $e = Carbon::parse("2022-02-28")->addDay(1)->format('Y-m-d');
            $end = new DateTime($e);
        } else {
            $end = new DateTime("2022-02-28");
        }
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date = $dt->format("Y-m-d");

            // $customerinfo = CustomerInfo::select('user_id')->get();

            $customerVisit = CustomerVisit::select('customer_id')
                ->whereDate('created_at', $date)
                ->get();

            foreach ($customerVisit as $c) {
                $cm = CustomerMerchandiser::where('customer_id', $c->customer_id)->first();

                $ds = DistributionStock::select(DB::raw('COUNT(item_id) as item_count'))
                    ->where('customer_id', $c->customer_id)
                    ->whereDate('created_at', $date)
                    ->first();

                DB::table('msl_details')->insert(
                    array(
                        'date'                  =>   $date,
                        'salesman_id'           =>  (is_object($cm)) ? $cm->merchandiser_id : 0,
                        'customer_id'           =>   $c->customer_id,
                        'distribution_id'       =>   "26",
                        'out_of_stock_count'    =>   $ds->item_count
                    )
                );
            }
        }
    }
}
