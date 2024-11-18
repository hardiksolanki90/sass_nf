<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Channel;
use App\Model\CustomerInfo;
use App\Model\CustomerVisit;
use App\Model\Order;
use App\Model\Region;
use App\Model\SalesmanInfo;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanDay;
use App\Model\JourneyPlanCustomer;
use App\Model\JourneyPlanWeek;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerMerchandizer;
use App\Model\Distribution;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\DistributionStock;
use Illuminate\Support\Collection;
use App\User;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class Dashboard4Controller_backup extends Controller
{
    private $organisation_id;

    public function index(Request $request)
    {
        if ($request->start_date && $request->end_date) {
            $start_date = date('Y-m-d', strtotime('-1 days', strtotime($request->start_date)));
            $end_date = date('Y-m-d', strtotime($request->end_date));
        }

        if (empty($request->start_date) && $request->end_date) {
            $end_date = $request->end_date;
            $start_date = date('Y-m-d', strtotime('-6 days', strtotime($end_date)));
        }

        if ($request->start_date && empty($request->end_date)) {
            $start_date = date('Y-m-d', strtotime('-1 days', strtotime($request->start_date)));
            $end_date = date('Y-m-d', strtotime('+7 days', strtotime($start_date)));
        }

        if (empty($request->start_date) && empty($request->end_date)) {
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime('-7 days', strtotime(date('Y-m-d'))));
        }

        $this->organisation_id = $request->user()->organisation_id;

        if ($request->type == "coverage") {
            $data = $this->coverage($request, $start_date, $end_date);
        }

        if ($request->type == "activeOutlets") {
            $data = $this->activeOutlets($request, $start_date, $end_date);
        }

        if ($request->type == "routeCompliance") {
            $data = $this->routeCompliance($request, $start_date, $end_date);
        }

        if ($request->type == "visitFrequency") {
            $data = $this->visitFrequency($request, $start_date, $end_date);
        }

        if ($request->type == "sos") {
            $data = $this->sos($request, $start_date, $end_date);
        }

        if ($request->type == "outofstock") {
            $data = $this->outofstock($request, $start_date, $end_date);
        }

        if ($request->type == "msl") {
            $data = $this->msl($request, $start_date, $end_date);
        }

        if ($request->type == "msl-listing") {
            $data = $this->mslListingData($request, $start_date, $end_date);
        }

        if ($request->type == "msl-listing-details") {
            $data = $this->MSLByCustomerDetail($request, $start_date, $end_date);
        }

        return prepareResult(true, $data, [], "dashboard listing", $this->success);
    }

    public function msl($request, $start_date, $end_date)
    {
        $details = array();
        $trends_data = array();
        $salesman_idss = array();
        $comparison = [];
        $details = [];
        $listing = [];
        $percentage = 0;


        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = array();
            $get_all_salesman = array();
            $salesman_idss = $request->nsm;
            $channel_user_item_ids = userChannelItems($request->user()->id);

            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);
                $nsm_user = User::find($nsm);
                $get_all_salesman[] = $all_salesman;

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->whereIn('salesman_id', $all_salesman);

                if ($start_date != '' && $end_date != '') {
                    if ($start_date == $end_date) {
                        $visit->whereDate('date', $start_date);
                    } else {
                        $visit->whereBetween('date', [$start_date, $end_date]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                foreach ($visits as $visit) {

                    if (is_object($visits)) {
                        $ds = DistributionStock::select(
                            DB::raw(
                                '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                            ),
                            DB::raw(
                                'COUNT(item_id) as item_count'
                            )
                        )
                            ->where('customer_id', $visit->customer_id)
                            ->whereIn('salesman_id', $all_salesman)
                            ->whereDate('created_at', $visit->dates)
                            ->whereIn('item_id', $channel_user_item_ids)
                            ->first();

                        $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                        $item_count[]         = $ds->item_count; // item count
                        // $percentage[]         = $ds->per; // precentage
                    }
                }

                $total_item_counts        = array_sum($item_count);
                $total_out_of_stock_counts = array_sum($out_of_stock_count);

                $percentage = 0;
                if ($total_item_counts > 0) {
                    $percentage = round((($total_item_counts - $total_out_of_stock_counts) / $total_item_counts) * 100, 2);
                }

                $comparison[] = array(
                    'name' => $nsm_user->getName(),
                    'steps' => round($percentage) ?? 0
                );

                $details[] = array(
                    'RES'               => $nsm_user->getName(),
                    'TOTAL_OUTLETS'     => ($total_item_counts > 0) ? $total_item_counts : "0",
                    'VISITS'            => ($total_out_of_stock_counts > 0) ? $total_out_of_stock_counts : '0',
                    'EXECUTION'         => ($percentage > 0) ? $percentage : '0'
                );
            }
            // $trends_data = $this->mslTrendData($get_all_salesman, $start_date, $end_date);
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_idss = $request->asm;
            $channel_user_item_ids = userChannelItems($request->user()->id);

            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $asm_user = User::find($asm);
                $get_all_salesman[] = $all_salesman;

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->whereIn('salesman_id', $all_salesman);

                if ($start_date != '' && $end_date != '') {
                    if ($start_date == $end_date) {
                        $visit->whereDate('date', $start_date);
                    } else {
                        $visit->whereBetween('date', [$start_date, $end_date]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                foreach ($visits as $visit) {

                    if (is_object($visits)) {
                        $ds = DistributionStock::select(
                            DB::raw(
                                '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                            ),
                            DB::raw(
                                'COUNT(item_id) as item_count'
                            )
                        )
                            ->where('customer_id', $visit->customer_id)
                            ->whereIn('salesman_id', $all_salesman)
                            ->whereDate('created_at', $visit->dates)
                            ->whereIn('item_id', $channel_user_item_ids)
                            ->first();

                        $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                        $item_count[]         = $ds->item_count; // item count
                        // $percentage[]         = $ds->per; // precentage
                    }
                }

                $total_item_counts        = array_sum($item_count);
                $total_out_of_stock_counts = array_sum($out_of_stock_count);

                $percentage = 0;
                if ($total_item_counts > 0) {
                    $percentage = round((($total_item_counts - $total_out_of_stock_counts) / $total_item_counts) * 100, 2);
                }


                $comparison[] = array(
                    'name' => $asm_user->getName(),
                    'steps' => round($percentage) ?? 0
                );

                $details[] = array(
                    'RES'               => $asm_user->getName(),
                    'TOTAL_OUTLETS'     => ($total_item_counts > 0) ? $total_item_counts : "0",
                    'VISITS'            => ($total_out_of_stock_counts > 0) ? $total_out_of_stock_counts : '0',
                    'EXECUTION'         => ($percentage > 0) ? $percentage : '0'
                );
            }
            // $trends_data = $this->mslTrendData($get_all_salesman, $start_date, $end_date);
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $salesman_idss = $request->channel_ids;
            $user = request()->user();
            $all_salesman_customer = getSalesman(true, $user->id);
            $get_all_salesman = array();
            $listing = array();
            $percentage = array();
            $comparison = [];
            $details = [];
            // Get the channel_id based on login user
            $channel_user_item_ids = userChannelItems($request->user()->id);

            foreach ($request->channel_ids as $channel_id) {
                $custome_ids = array();

                $channel = Channel::find($channel_id);
                $customerInfo = CustomerInfo::where('channel_id', $channel_id)->get();
                if (count($customerInfo)) {
                    $custome_ids = $customerInfo->pluck('user_id')->toArray();
                }
                $final_customer = array_intersect($all_salesman_customer, $custome_ids);

                $get_all_salesman[] = $final_customer;

                $item_count = array();
                $out_of_stock_count = array();
                $percentage = array();
                // salesman wise customer

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->whereIn('customer_id', $final_customer);

                if ($start_date != '' && $end_date != '') {
                    if ($start_date == $end_date) {
                        $visit->whereDate('date', $start_date);
                    } else {
                        $visit->whereBetween('date', [$start_date, $end_date]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                foreach ($visits as $visit) {

                    if (is_object($visits)) {
                        $ds = DistributionStock::select(
                            DB::raw(
                                '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                            ),
                            DB::raw(
                                'COUNT(item_id) as item_count'
                            )
                        )
                            ->where('customer_id', $visit->customer_id)
                            ->whereDate('created_at', $visit->dates)
                            ->whereIn('item_id', $channel_user_item_ids)
                            ->first();

                        $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                        $item_count[]         = $ds->item_count; // item count
                        // $percentage[]         = $ds->per; // precentage
                    }
                }

                $total_item_counts        = array_sum($item_count);
                $total_out_of_stock_counts = array_sum($out_of_stock_count);

                $percentage = 0;
                if ($total_item_counts > 0) {
                    $percentage = round((($total_item_counts - $total_out_of_stock_counts) / $total_item_counts) * 100, 2);
                }

                $comparison[] = array(
                    'name' => model($channel, 'name'),
                    'steps' => round($percentage) ?? 0
                );

                $details[] = array(
                    'RES'               => model($channel, 'name'),
                    'TOTAL_OUTLETS'     => ($total_item_counts > 0) ? $total_item_counts : "0",
                    'VISITS'            => ($total_out_of_stock_counts > 0) ? $total_out_of_stock_counts : '0',
                    'EXECUTION'         => ($percentage > 0) ? $percentage : '0'
                );
            }
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
        } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $supervisor = $request->supervisor;
            $channel_user_item_ids = userChannelItems($request->user()->id);
            $start_date = Carbon::parse($start_date)->addDay(1)->format('Y-m-d');

            foreach ($supervisor as $s) {
                $out_of_stock_count = array();
                $item_count       = array();

                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->where('salesman_supervisor', $s)
                    ->where('status', 1)
                    ->get();

                $salesman_ids = array();
                if (count($salesman_infos)) {
                    $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
                    $salesman_idss = $salesman_ids;
                }

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->whereIn('salesman_id', $salesman_ids);

                if ($start_date != '' && $end_date != '') {
                    if ($start_date == $end_date) {
                        $visit->whereDate('date', $start_date);
                    } else {
                        $visit->whereBetween('date', [$start_date, $end_date]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                if (count($visits)) {

                    foreach ($visits as $visit) {

                        $ds = DistributionStock::select(
                            DB::raw(
                                '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                            ),
                            DB::raw(
                                'COUNT(item_id) as item_count'
                            )
                        )
                            ->where('customer_id', $visit->customer_id)
                            ->whereIn('salesman_id', $salesman_ids)
                            ->whereDate('created_at', $visit->dates)
                            ->whereIn('item_id', $channel_user_item_ids)
                            ->first();

                        $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                        $item_count[]         = $ds->item_count; // item count
                        // $percentage[]         = $ds->per; // precentage

                    }
                }

                $supervisor = User::find($s);

                $total_item_counts        = array_sum($item_count);
                $total_out_of_stock_counts = array_sum($out_of_stock_count);

                $percentage = 0;
                if ($total_item_counts > 0) {
                    $percentage = round((($total_item_counts - $total_out_of_stock_counts) / $total_item_counts) * 100, 2);
                }

                $comparison[] = array(
                    'name' => $supervisor->getName(),
                    'steps' => $percentage ?? '0'
                );

                $details[] = array(
                    'RES'               => $supervisor->getName(),
                    'TOTAL_OUTLETS'     => ($total_item_counts > 0) ? $total_item_counts : "0",
                    'VISITS'            => ($total_out_of_stock_counts > 0) ? $total_out_of_stock_counts : '0',
                    'EXECUTION'         => ($percentage > 0) ? $percentage : '0'
                );
            }
        } else {
            // salesman get
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_ids = array();
            $comparison = array();
            $listing = new Collection();
            $percentage = array();
            $customer_count = 0;
            $item_count = 0;
            $out_of_stock_count = 0;

            // salesman user_id
            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
                $salesman_idss = $salesman_ids;
            }

            $start_date = Carbon::parse($start_date)->addDay(1)->format('Y-m-d');

            // trends data
            // $trends_data = $this->mslTrendData($salesman_ids, $start_date, $end_date);

            // compaire and details
            if (count($salesman_ids)) {
                // Get the channel_id based on login user
                $channel_user_item_ids = userChannelItems($request->user()->id);

                // $dis = Distribution::select('id')->get();

                foreach ($salesman_ids as $salesman_id) {
                    $item_count = array();
                    $out_of_stock_count = array();
                    $percentage = array();
                    // salesman wise customer

                    $visit = DB::table('customer_visits')
                        ->select(
                            DB::raw('customer_id, max(date) as dates')
                        )
                        ->where('salesman_id', $salesman_id);

                    if ($start_date != '' && $end_date != '') {
                        if ($start_date == $end_date) {
                            $visit->whereDate('date', $start_date);
                        } else {
                            $visit->whereBetween('date', [$start_date, $end_date]);
                        }
                    }

                    $visits = $visit->groupBy('customer_id')
                        ->orderBy('dates', 'desc')
                        ->get();

                    $dsd = DistributionStock::select(
                        DB::raw(
                            '(sum(is_out_of_stock > 0)) as out_of_stock',
                        ),
                        DB::raw(
                            'COUNT(item_id) as item_count'
                        )
                    );
                    if ($start_date != '' && $end_date != '') {
                        if ($start_date == $end_date) {
                            $dsd->whereDate('created_at', $start_date);
                        } else {
                            $dsd->whereBetween('created_at', [$start_date, $end_date]);
                        }
                    }
                    $ds = $dsd->where('salesman_id', $salesman_id)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->first();

                    $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                    $item_count[]         = $ds->item_count; // item count

                    $salesman_info = SalesmanInfo::where('user_id', $salesman_id)->first();

                    $item_counts        = array_sum($item_count);
                    $out_of_stock_counts = array_sum($out_of_stock_count);

                    $percentage = 0;
                    if ($item_counts > 0) {
                        $percentage = round((($item_counts - $out_of_stock_counts) / $item_counts) * 100, 2);
                    }

                    $comparison[] = array(
                        'name' => $salesman_info->user->getName(),
                        'steps' => $percentage ?? '0'
                    );

                    $details[] = array(
                        'RES'               => $salesman_info->user->getName(),
                        'TOTAL_OUTLETS'     => ($item_counts > 0) ? $item_counts : "0",
                        'VISITS'            => ($out_of_stock_counts > 0) ? $out_of_stock_counts : '0',
                        'EXECUTION'         => ($percentage > 0) ? $percentage : '0'
                    );
                }
            }
        }

        if (count($details)) {
            $exe = array_sum(collect($details)->pluck('EXECUTION')->toArray());
            $salesmans = count(array_unique($salesman_idss));
            if ($exe != 0 && $salesmans != 0) {
                $percentage = round($exe / $salesmans, 2);
            }
        }

        $msl_data = new \stdClass();
        $msl_data->title = "MSL";
        $msl_data->text = "Average # of visits made by a sales man in a day";
        $msl_data->percentage = $percentage;
        $msl_data->trends = $trends_data;
        $msl_data->comparison = $comparison;
        $msl_data->contribution = $comparison;
        $msl_data->details = $details;
        $msl_data->listing = $listing;
        return $msl_data;
    }

    private function mslListingData($request, $start_date, $end_date)
    {
        $customer_ids = array();
        $salesman_ids = array();
        $s_man = true;

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        // Get the channel_id based on login user
        $channel_user_item_ids = userChannelItems($request->user()->id);

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = getSalesman(false, $request->nsm);
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_ids = getSalesman(false, $request->asm);
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $s_man = false;
            $all_salesman_customer = getSalesman(true, $request->user()->id);
            $customerInfo = CustomerInfo::select('id', 'user_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();
            if (count($customerInfo)) {
                $customer_ids = $customerInfo->pluck('user_id')->toArray();
                $customer_id = array_intersect($all_salesman_customer, $customer_ids);
            }
        } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $salesman_infos = SalesmanInfo::select('id', 'user_id')
                ->whereIn('salesman_supervisor', $request->supervisor)
                ->where('status', 1)
                ->get();

            $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
        } else {
            // salesman get
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {

                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
        }

        $listing = new Collection();

        if ($s_man) {
            $visit = DB::table('customer_visits')
                ->select(
                    DB::raw('salesman_id, customer_id, max(date) as dates')
                )
                ->whereIn('salesman_id', $salesman_ids);

            if ($start_date != '' && $end_date != '') {
                if ($start_date == $end_date) {
                    $visit->whereDate('date', $start_date);
                } else {
                    $visit->whereBetween('date', [$start_date, $end_date]);
                }
            }

            $visits = $visit->groupBy('customer_id')
                ->orderBy('dates', 'desc')
                ->get();
        } else {
            $visit = DB::table('customer_visits')
                ->select(
                    DB::raw('customer_id, max(date) as dates')
                )
                ->whereIn('customer_id', $customer_ids);

            if ($start_date != '' && $end_date != '') {
                if ($start_date == $end_date) {
                    $visit->whereDate('date', $start_date);
                } else {
                    $visit->whereBetween('date', [$start_date, $end_date]);
                }
            }

            $visits = $visit->groupBy('customer_id')
                ->orderBy('dates', 'desc')
                ->get();
        }

        if (count($visits)) {

            foreach ($visits as $v) {

                $c_id = "";
                $c_code = "N/A";
                $c_name = "N/A";
                $supervisor_name = "N/A";
                $s_name = "N/A";
                $s_code = "N/A";
                $user = User::find($v->customer_id);
                if ($user) {
                    $c_id = $user->id;
                    $c_code = $user->customerInfo->customer_code;
                    $c_name = $user->getName();
                }

                $si = SalesmanInfo::where('user_id', $v->salesman_id)->first();

                if ($si) {
                    $s_code = $si->salesman_code;
                    $s_name = $si->user->getName();
                    $supervisor = User::find($si->salesman_supervisor);
                    if ($supervisor) {
                        $supervisor_name = $supervisor->getName();
                    }
                }

                $ds = DistributionStock::select(
                    DB::raw(
                        '(sum(is_out_of_stock > 0)) as out_of_stock',
                    ),
                    DB::raw(
                        'COUNT(item_id) as item_count'
                    )
                )
                    ->where('customer_id', $v->customer_id)
                    ->where('salesman_id', $v->salesman_id)
                    ->whereDate('created_at', $v->dates)
                    ->whereIn('item_id', $channel_user_item_ids)
                    ->first();

                // $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                // $item_count[]         = $ds->item_count; // item count


                $out_of_stock_count = $ds->out_of_stock; // Out of stock count
                $item_count = $ds->item_count; // 
                $per = 0;
                if ($item_count != 0) {
                    $per = round(($item_count - $out_of_stock_count) / $item_count * 100, 2);
                }
                $percentage = $per;

                $listing->push((object) [
                    'date'              => $start_date,
                    'customer_id'       => $c_id,
                    'customer_name'     => $c_name,
                    'customer_code'     => $c_code,
                    'merchandiser_code' => $s_code,
                    'merchandiser_name' => $s_name,
                    'supervisor_name'   => $supervisor_name,
                    'msl_item'          => $item_count,
                    'out_of_stock_item' => $out_of_stock_count,
                    'msl_compliance'    => $percentage
                ]);
            }
        }

        return $listing;
    }

    private function MSLByCustomerDetail($request, $start_date, $end_date)
    {
        if (!$request->customer_id) {
            return false;
        }

        $dis = Distribution::select('id')->get();
        $msld = new Collection();

        $dms = DistributionModelStock::select('id', 'customer_id')
            ->whereIn('distribution_id', $dis)
            ->where('customer_id', $request->customer_id)
            ->get();

        if (count($dms)) {
            $d_m_s_ids = $dms->pluck('id')->toArray();
            $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $d_m_s_ids)
                ->where('is_deleted', 0)
                ->get();

            if (count($dmsd)) {
                foreach ($dmsd as $dd) {

                    $ds = DistributionStock::where('customer_id', $request->customer_id)
                        ->whereIn('distribution_id', $dis)
                        ->whereBetween('created_at', [$start_date, $end_date])
                        ->where('item_id', $dd->item_id)
                        ->first();

                    $customerInfo = CustomerInfo::where('user_id', $request->customer_id)->first();

                    $msld->push((object) [
                        'date'          => $start_date,
                        'customer_code' => model($customerInfo, 'salesman_code'),
                        'customer_name' => $customerInfo->user->getName(),
                        'item_code'     => model($dd->item, 'item_code'),
                        'item_name'     => model($dd->item, 'item_name'),
                        'model_qty'     => isset($ds->capacity) ? model($ds, 'capacity') : "0",
                        'is_check'      => (is_object($ds)) ? "Yes" : "No",
                        'out_of_stock'  => isset($ds->is_out_of_stock) ? "Yes" : "No",
                    ]);
                }
            }
        }

        return $msld;
    }

    private function mslTrendData($salesman_id, $start_date, $end_date)
    {
        $trends_data_query = DB::table('salesman_infos')
            ->select(
                DB::raw('DATE(distribution_stocks.created_at) as date'),
                DB::raw('round(SUM(distribution_stocks.is_out_of_stock) / SUM(distribution_model_stock_details.item_id) * 100, 2) as value')
            )
            ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
            ->join('distribution_model_stocks', 'distribution_model_stocks.customer_id', '=', 'customer_merchandisers.customer_id')
            ->join('distribution_model_stock_details', 'distribution_model_stock_details.distribution_model_stock_id', '=', 'distribution_model_stocks.id')
            ->join('distribution_stocks', 'distribution_stocks.customer_id', '=', 'customer_merchandisers.customer_id')
            ->where('distribution_model_stock_details.is_deleted', 0);

        return $trends_data_query->whereIn('salesman_infos.user_id', $salesman_id)
            ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
            ->where('salesman_infos.organisation_id', $this->organisation_id)
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function visitFrequency2($request, $start_date, $end_date)
    {
        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $comparison_data = array();
            $customer_details = array();
            $customer_details_data = array();
            $listing = array();

            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);
                $get_all_salesman[] = $all_salesman;

                foreach ($all_salesman as $salesman_id) {
                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                        ->get();

                    if (count($customerMerchandiser)) {
                        $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                    } else {
                        $customer_id = array();
                    }

                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                    $no_of_customers =  count($customerMerchandiser) * $date_diff;

                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                    );

                    $visit_execution = 0;
                    if (isset($customer_visit[0]) && $no_of_customers != 0) {
                        $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customerMerchandiser)), 2);
                    }
                    $visit_executions[] = $visit_execution;
                    $salesman_user = User::find($salesman_id);

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison_data[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details_data[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }

                $i_user = User::find($nsm);
                if (count($comparison_data)) {
                    $steps = collect($comparison_data)->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $i_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                if (count($customer_details_data)) {
                    $TOTAL_OUTLETS = collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($customer_details_data)->pluck('VISITS')->toArray();
                    $EXECUTION = collect($customer_details_data)->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        'RES' => $i_user->firstname,
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
                $getCustomers[] = getSalesman(true, $nsm);
                // $customer_ids[] = $getCustomers;
            }


            $salesman_ids = Arr::collapse($get_all_salesman);
            $customer_ids = Arr::collapse($getCustomers);
            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesman_infos.salesman_supervisor AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $comparison_data = array();
            $customer_details = array();
            $customer_details_data = array();
            $listing = array();

            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $get_all_salesman[] = $all_salesman;

                foreach ($all_salesman as $salesman_id) {
                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                        ->get();

                    if (count($customerMerchandiser)) {
                        $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                    } else {
                        $customer_id = array();
                    }

                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                    $no_of_customers =  count($customerMerchandiser) * $date_diff;

                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                    );

                    $visit_execution = 0;
                    if (isset($customer_visit[0]) && $no_of_customers != 0) {
                        $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customerMerchandiser)), 2);
                    }
                    $visit_executions[] = $visit_execution;
                    $salesman_user = User::find($salesman_id);

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison_data[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details_data[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }

                $i_user = User::find($asm);
                if (count($comparison_data)) {
                    $steps = collect($comparison_data)->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $i_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                if (count($customer_details_data)) {
                    $TOTAL_OUTLETS = collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($customer_details_data)->pluck('VISITS')->toArray();
                    $EXECUTION = collect($customer_details_data)->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        'RES' => $i_user->firstname,
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
                $getCustomers[] = getSalesman(true, $asm);
                // $customer_ids[] = $getCustomers;
            }


            $salesman_ids = Arr::collapse($get_all_salesman);
            $customer_ids = Arr::collapse($getCustomers);
            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesman_infos.salesman_supervisor AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            foreach ($request->channel_ids as $channel) {
                $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                    ->where('channel_id', $channel)
                    ->get();

                $customer_ids = array();
                if (count($customer_infos) > 0) {
                    $customer_ids = $customer_infos->pluck('user_id')->toArray();
                }

                $customer_str = implode(',', $customer_ids);

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customer_ids) * $date_diff;
                if (count($customer_ids)) {
                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `customer_id` in ($customer_str)) as x"
                    );

                    $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customer_ids)), 2);
                    $visit_executions[] = $visit_execution;

                    $customer_visits[] = $customer_visit;

                    $channel_usr = Channel::find($channel);
                    $comparison[] = array(
                        'name' => $channel_usr->name,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $customer_details[] = array(
                        'RES' => $channel_usr->name,
                        'TOTAL_OUTLETS' => count($customer_ids),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }
            }

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($request->channel_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                //$percentage = ROUND($no_of_visits / $no_of_customers, 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $all_customer_ids = array();
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            if (count($customer_infos)) {
                $all_customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data = DB::table('channels')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.customer_id)) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing = DB::table('channels')->select(
                DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    COUNT(DISTINCT customer_visits.id) as countOfVisit,
                    customer_visits.latitude AS latitude')
            )
                // ->join('regions', 'regions.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {

            $visit_execution = 0;
            $visit_executions = array();
            $comparison = array();
            $customer_details = array();

            foreach ($request->region_ids as $region) {
                $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                    ->where('region_id', $region)
                    ->get();

                $customer_ids = array();
                if (count($customer_infos) > 0) {
                    $customer_ids = $customer_infos->pluck('user_id')->toArray();
                }

                if (count($customer_ids) == 0) {
                    continue;
                }

                $customer_str = implode(',', $customer_ids);

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customer_ids) * $date_diff;

                $customer_visit = DB::select(
                    "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `customer_id` in ($customer_str)) as x"
                );

                $customer_visits[] = $customer_visit;

                if (isset($customer_visit[0]) && $customer_visit[0]) {
                    $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customer_ids)), 2);
                }
                $visit_executions[] = $visit_execution;

                $region_usr = Region::find($region);

                $comparison[] = array(
                    'name' => $region_usr->region_name,
                    'steps' => $customer_visit[0]->visit_count
                );

                $customer_details[] = array(
                    'RES' => $region_usr->region_name,
                    'TOTAL_OUTLETS' => count($customer_ids),
                    'VISITS' => $customer_visit[0]->visit_count,
                    'EXECUTION' => $visit_execution
                );
            }

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($request->region_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                // $percentage = ROUND($no_of_visits / $no_of_customers, 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $all_customer_ids = array();
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            if (count($customer_infos)) {
                $all_customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data = DB::table('regions')
                ->select(
                    'customer_visits.added_on as date',
                    DB::raw('ROUND(COUNT(DISTINCT customer_visits.customer_id)) as value')
                )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing = DB::table('regions')->select(
                DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    COUNT(DISTINCT customer_visits.id) as countOfVisit,
                    customer_visits.latitude AS latitude')
            )
                // ->join('regions', 'regions.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                // $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                //     ->where(function ($query) use ($supervisor) {
                //         if (!empty($supervisor)) {
                //             foreach ($supervisor as $key => $filter_val) {
                //                 if ($key == 0) {
                //                     $query->where('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 } else {
                //                     $query->orWhere('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 }
                //             }
                //         }
                //     })
                //     ->where('status', 1)
                //     ->get();
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $customer_details = array();
            $listing = array();

            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            foreach ($salesman_ids as $salesman_id) {
                $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                    ->get();

                if (count($customerMerchandiser)) {
                    $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                } else {
                    $customer_id = array();
                }

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customerMerchandiser) * $date_diff;

                $customer_visit = DB::select(
                    "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                );

                $visit_execution = 0;
                if (isset($customer_visit[0]) && $no_of_customers != 0) {
                    $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customerMerchandiser) * $date_diff), 2);
                }
                $visit_executions[] = $visit_execution;
                $salesman_user = User::find($salesman_id);

                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $supervisor = '';
                    if (is_object($salesman_user)) {
                        $supervisor = $salesman_user->firstname;
                    }
                    $comparison[] = array(
                        'name' => $supervisor,
                        'steps' => $customer_visit[0]->visit_count
                    );
                } else {
                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );
                }

                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $supervisor = '';
                    if (is_object($salesman_user)) {
                        $supervisor = $salesman_user->firstname;
                    }
                    $customer_details[] = array(
                        'RES' => $supervisor,
                        // 'TOTAL_OUTLETS' => count($customer_id),
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                } else {
                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }
            }

            $customer_ids = array();
            $customer_ids = getSalesman(true);

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                // $percentage = ROUND($no_of_visits / count($customerMerchandiser), 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data_query = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing_query = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesmanSupervisor.firstname AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $listing = $listing_query->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                // ->groupBy('customer_visits.salesman_id')
                ->get();
        }

        $visit_per_day = new \stdClass();
        $visit_per_day->title = "visit Frequency";
        $visit_per_day->text = "Average # of visits made by a sales man in a day";
        $visit_per_day->percentage = $percentage;
        $visit_per_day->trends = $trends_data;
        $visit_per_day->comparison = $comparison;
        $visit_per_day->contribution = $comparison;
        $visit_per_day->details = $customer_details;
        $visit_per_day->listing = $listing;
        return $visit_per_day;
    }

    private function visitFrequency($request, $start_date, $end_date)
    {
        $start_date = Carbon::parse($start_date)->addDay()->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $comparison_data = array();
            $customer_details = array();
            $customer_details_data = array();
            $listing = array();

            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);
                $get_all_salesman[] = $all_salesman;

                foreach ($all_salesman as $salesman_id) {
                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                        ->get();

                    if (count($customerMerchandiser)) {
                        $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                    } else {
                        $customer_id = array();
                    }

                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                    $no_of_customers =  count($customerMerchandiser) * $date_diff;

                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                    );

                    $visit_execution = 0;
                    if (isset($customer_visit[0]) && $no_of_customers != 0) {
                        $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customerMerchandiser)), 2);
                    }
                    $visit_executions[] = $visit_execution;
                    $salesman_user = User::find($salesman_id);

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison_data[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details_data[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }

                $i_user = User::find($nsm);
                if (count($comparison_data)) {
                    $steps = collect($comparison_data)->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $i_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                if (count($customer_details_data)) {
                    $TOTAL_OUTLETS = collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($customer_details_data)->pluck('VISITS')->toArray();
                    $EXECUTION = collect($customer_details_data)->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        'RES' => $i_user->firstname,
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
                $getCustomers[] = getSalesman(true, $nsm);
                // $customer_ids[] = $getCustomers;
            }


            $salesman_ids = Arr::collapse($get_all_salesman);
            $customer_ids = Arr::collapse($getCustomers);
            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesman_infos.salesman_supervisor AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $comparison_data = array();
            $customer_details = array();
            $customer_details_data = array();
            $listing = array();

            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $get_all_salesman[] = $all_salesman;

                foreach ($all_salesman as $salesman_id) {
                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                        ->get();

                    if (count($customerMerchandiser)) {
                        $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                    } else {
                        $customer_id = array();
                    }

                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                    $no_of_customers =  count($customerMerchandiser) * $date_diff;

                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                    );

                    $visit_execution = 0;
                    if (isset($customer_visit[0]) && $no_of_customers != 0) {
                        $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customerMerchandiser)), 2);
                    }
                    $visit_executions[] = $visit_execution;
                    $salesman_user = User::find($salesman_id);

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison_data[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details_data[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }

                $i_user = User::find($asm);
                if (count($comparison_data)) {
                    $steps = collect($comparison_data)->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $i_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                if (count($customer_details_data)) {
                    $TOTAL_OUTLETS = collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($customer_details_data)->pluck('VISITS')->toArray();
                    $EXECUTION = collect($customer_details_data)->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        'RES' => $i_user->firstname,
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
                $getCustomers[] = getSalesman(true, $asm);
                // $customer_ids[] = $getCustomers;
            }


            $salesman_ids = Arr::collapse($get_all_salesman);
            $customer_ids = Arr::collapse($getCustomers);
            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesman_infos.salesman_supervisor AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            foreach ($request->channel_ids as $channel) {
                $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                    ->where('channel_id', $channel)
                    ->get();

                $customer_ids = array();
                if (count($customer_infos) > 0) {
                    $customer_ids = $customer_infos->pluck('user_id')->toArray();
                }

                $customer_str = implode(',', $customer_ids);

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customer_ids) * $date_diff;
                if (count($customer_ids)) {
                    $customer_visit = DB::select(
                        "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `customer_id` in ($customer_str)) as x"
                    );

                    $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customer_ids)), 2);
                    $visit_executions[] = $visit_execution;

                    $customer_visits[] = $customer_visit;

                    $channel_usr = Channel::find($channel);
                    $comparison[] = array(
                        'name' => $channel_usr->name,
                        'steps' => $customer_visit[0]->visit_count
                    );

                    $customer_details[] = array(
                        'RES' => $channel_usr->name,
                        'TOTAL_OUTLETS' => count($customer_ids),
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }
            }

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($request->channel_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                //$percentage = ROUND($no_of_visits / $no_of_customers, 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $all_customer_ids = array();
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            if (count($customer_infos)) {
                $all_customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data = DB::table('channels')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.customer_id)) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing = DB::table('channels')->select(
                DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    COUNT(DISTINCT customer_visits.id) as countOfVisit,
                    customer_visits.latitude AS latitude')
            )
                // ->join('regions', 'regions.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {

            $visit_execution = 0;
            $visit_executions = array();
            $comparison = array();
            $customer_details = array();

            foreach ($request->region_ids as $region) {
                $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                    ->where('region_id', $region)
                    ->get();

                $customer_ids = array();
                if (count($customer_infos) > 0) {
                    $customer_ids = $customer_infos->pluck('user_id')->toArray();
                }

                if (count($customer_ids) == 0) {
                    continue;
                }

                $customer_str = implode(',', $customer_ids);

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customer_ids) * $date_diff;

                $customer_visit = DB::select(
                    "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `customer_id` in ($customer_str)) as x"
                );

                $customer_visits[] = $customer_visit;

                if (isset($customer_visit[0]) && $customer_visit[0]) {
                    $visit_execution = (int)ROUND(($customer_visit[0]->visit_count / count($customer_ids)), 2);
                }
                $visit_executions[] = $visit_execution;

                $region_usr = Region::find($region);

                $comparison[] = array(
                    'name' => $region_usr->region_name,
                    'steps' => $customer_visit[0]->visit_count
                );

                $customer_details[] = array(
                    'RES' => $region_usr->region_name,
                    'TOTAL_OUTLETS' => count($customer_ids),
                    'VISITS' => $customer_visit[0]->visit_count,
                    'EXECUTION' => $visit_execution
                );
            }

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($request->region_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                // $percentage = ROUND($no_of_visits / $no_of_customers, 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $all_customer_ids = array();
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            if (count($customer_infos)) {
                $all_customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data = DB::table('regions')
                ->select(
                    'customer_visits.added_on as date',
                    DB::raw('ROUND(COUNT(DISTINCT customer_visits.customer_id)) as value')
                )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing = DB::table('regions')->select(
                DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    COUNT(DISTINCT customer_visits.id) as countOfVisit,
                    customer_visits.latitude AS latitude')
            )
                // ->join('regions', 'regions.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_ids = array();
            $visit_avg = array();
            $visit_executions = array();
            $comparison = array();
            $customer_details = array();
            $listing = array();

            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            foreach ($salesman_ids as $salesman_id) {
                $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)
                    ->get();

                if (count($customerMerchandiser)) {
                    $customer_id[] = $customerMerchandiser->pluck('customer_id')->toArray();
                } else {
                    $customer_id = array();
                }

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_customers =  count($customerMerchandiser) * $date_diff;

                $customer_visit = DB::select(
                    "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `added_on` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                );

                $visit_execution = 0;
                if (isset($customer_visit[0]) && $no_of_customers != 0) {
                    $visit_execution = ROUND($customer_visit[0]->visit_count / (count($customerMerchandiser) * $date_diff));
                }

                $visit_executions[] = $visit_execution;
                $salesman_user = User::find($salesman_id);

                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $comparison[] = array(
                        'name' => $salesman_user->salesmanInfo->salesman_supervisor,
                        'steps' => $customer_visit[0]->visit_count
                    );
                } else {
                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $comparison[] = array(
                        'name' => $salesman,
                        'steps' => $customer_visit[0]->visit_count
                    );
                }

                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $customer_details[] = array(
                        'RES' => $salesman_user->salesmanInfo->salesman_supervisor,
                        // 'TOTAL_OUTLETS' => count($customer_id),
                        'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                } else {
                    $salesman = '';
                    if (is_object($salesman_user)) {
                        $salesman = $salesman_user->firstname;
                    }
                    $customer_details[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                        'VISITS' => $customer_visit[0]->visit_count,
                        'EXECUTION' => $visit_execution
                    );
                }
            }

            $customer_ids = array();

            $customer_ids = getSalesman(true);

            $no_of_visits = array_sum($visit_executions);
            $no_of_customers = count($salesman_ids);

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                // $percentage = ROUND($no_of_visits / count($customerMerchandiser), 2);
                $percentage = ROUND($no_of_visits, 2);
            } else {
                $percentage = "0";
            }

            $trends_data = DB::table('salesman_infos')->select('customer_visits.added_on as date', DB::raw('ROUND(COUNT(DISTINCT customer_visits.id)) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                // ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                // ->groupBy('customer_visits.salesman_id')
                ->get();

            $listing = DB::table('salesman_infos')
                ->select(
                    DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesman_infos.salesman_supervisor AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.is_sequnece AS sequence,
                COUNT(DISTINCT customer_visits.id) as visit,
                COUNT(DISTINCT customer_visits.id) as unplanned,
                customer_visits.latitude AS latitude,
                customer_visits.longitude AS longitude')
                )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_visits', 'customer_visits.customer_id', '=', 'customer_infos.user_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $customer_ids)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('customer_visits.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                // ->groupBy('customer_visits.salesman_id')
                ->get();
        }

        $visit_per_day = new \stdClass();
        $visit_per_day->title = "visit Frequency";
        $visit_per_day->text = "Average # of visits made by a sales man in a day";
        $visit_per_day->percentage = $percentage;
        $visit_per_day->trends = $trends_data;
        $visit_per_day->comparison = $comparison;
        $visit_per_day->contribution = $comparison;
        $visit_per_day->details = $customer_details;
        $visit_per_day->listing = $listing;
        return $visit_per_day;
    }

    private function coverage3($request, $start_date, $end_date)
    {
        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();

            foreach ($request->nsm as $nsm) {
                $all_customers = getSalesman(true, $nsm);
                $salesman_user_ids = getSalesman(false, $nsm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $all_customers;

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");

                // get ASM
                $invite_user = User::find($nsm);

                $comparison_data = DB::table('salesman_infos')
                    ->select(
                        // (DB::select('select firstname as name FORM User where id = 1807')),
                        'users.firstname as name',
                        DB::raw('count(customer_visits.id) as steps')
                    )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                    ->where('customer_visits.shop_status', 'open')
                    ->whereNull('customer_visits.reason')
                    ->whereIn('customer_merchandisers.customer_id', $all_customers)
                    ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->groupBy('users.id')
                    ->get();

                if (count($comparison_data)) {
                    $steps_array = $comparison_data->pluck('steps')->toArray();
                    $steps = array_sum($steps_array);
                    $comparison = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => $steps
                    );
                }

                $name = "DISTINCT users.firstname as RES";
                $gBy = 'customer_visits.salesman_id';

                $customer_details_data = DB::table('salesman_infos')->select(
                    DB::raw($name),
                    DB::raw('COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                    DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                    DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                    ->where('customer_visits.shop_status', 'open')
                    ->whereNull('customer_visits.reason')
                    ->whereBetween('customer_visits.date', [$start_date, $end_date])
                    ->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->groupBy($gBy)
                    ->get();

                if (count($customer_details_data)) {

                    $VISITS = $customer_details_data->pluck('VISITS')->toArray();
                    $TOTAL_OUTLETS = $customer_details_data->pluck('TOTAL_OUTLETS')->toArray();
                    $EXECUTION = $customer_details_data->pluck('EXECUTION')->toArray();
                    $percentage[] = number_format(round(array_sum($EXECUTION) / count($salesman_user_ids), 2), 2);

                    $customer_details = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
            }

            if (array_sum($percentage) != 0) {
                $percentage = array_sum($percentage) / count($request->nsm) . "%";
            } else {
                $percentage = '0%';
            }

            $all_customers = Arr::collapse($all_customer_array);

            $trends_data = DB::table('salesman_infos')
                ->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_merchandisers.customer_id', $all_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                        customerInfo.firstname AS customer,
                        customer_infos.customer_code AS customerCode,
                        customer_categories.customer_category_name AS category,
                        salesman.firstname AS merchandiser,
                        salesman_infos.salesman_supervisor AS supervisor,
                        channels.name AS channel,
                        regions.region_name AS region,
                        customer_visits.total_task AS total_tasks_planned,
                        SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                        COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();
            $comparison = array();
            $customer_details = array();

            foreach ($request->asm as $asm) {
                $all_customers = getSalesman(true, $asm);
                $salesman_user_ids = getSalesman(false, $asm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $all_customers;

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");

                // get ASM
                $invite_user = User::find($asm);

                $comparison_data = DB::table('salesman_infos')
                    ->select(
                        // (DB::select('select firstname as name FORM User where id = 1807')),
                        'users.firstname as name',
                        DB::raw('count(customer_visits.id) as steps')
                    )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                    ->where('customer_visits.shop_status', 'open')
                    ->whereNull('customer_visits.reason')
                    ->whereIn('customer_merchandisers.customer_id', $all_customers)
                    ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->groupBy('users.id')
                    ->get();

                if (count($comparison_data)) {
                    $steps_array = $comparison_data->pluck('steps')->toArray();
                    $steps = array_sum($steps_array);
                    $comparison = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => $steps
                    );
                }

                $name = "DISTINCT users.firstname as RES";
                $gBy = 'customer_visits.salesman_id';

                $customer_details_data = DB::table('salesman_infos')->select(
                    DB::raw($name),
                    DB::raw('COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                    DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                    DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                    ->where('customer_visits.shop_status', 'open')
                    ->whereNull('customer_visits.reason')
                    ->whereBetween('customer_visits.date', [$start_date, $end_date])
                    ->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->groupBy($gBy)
                    ->get();

                if (count($customer_details_data)) {

                    $VISITS = $customer_details_data->pluck('VISITS')->toArray();
                    $TOTAL_OUTLETS = $customer_details_data->pluck('TOTAL_OUTLETS')->toArray();
                    $EXECUTION = $customer_details_data->pluck('EXECUTION')->toArray();
                    $percentage[] = number_format(round(array_sum($EXECUTION) / count($salesman_user_ids), 2), 2);

                    $customer_details = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
            }

            $percentage = array_sum($percentage) / count($request->asm) . "%";

            $all_customers = Arr::collapse($all_customer_array);

            $trends_data = DB::table('salesman_infos')
                ->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_merchandisers.customer_id', $all_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                        customerInfo.firstname AS customer,
                        customer_infos.customer_code AS customerCode,
                        customer_categories.customer_category_name AS category,
                        salesman.firstname AS merchandiser,
                        salesman_infos.salesman_supervisor AS supervisor,
                        channels.name AS channel,
                        regions.region_name AS region,
                        customer_visits.total_task AS total_tasks_planned,
                        SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                        COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('added_on', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            // if ($no_of_visits != 0 && $no_of_customers != 0) {
            //     $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            // } else {
            //     $percentage = "0%";
            // }

            $trends_data = DB::table('channels')->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                // ->groupBy('customer_visits.salesman_id')
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('channels')->select('channels.name as name', DB::raw('count(customer_visits.id) as steps'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();


            $customer_details = DB::table('channels')->select(
                DB::raw('DISTINCT channels.name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_merchandizers', 'customer_merchandizers.merchandizer_id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandizers.user_id', 'left')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($request->channel_ids), 2), 2) . '%';
            }

            $listing = DB::table('channels')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                // ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('channel')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')->whereIn('region_id', $request->region_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            // if ($no_of_visits != 0 && $no_of_customers != 0) {
            //     $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            // } else {
            //     $percentage = "0%";
            // }

            $trends_data = DB::table('regions')->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('regions')->select('regions.region_name as name', DB::raw('count(customer_visits.id) as steps'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $customer_details = DB::table('regions')->select(
                DB::raw('DISTINCT regions.region_name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_merchandizers', 'customer_merchandizers.merchandizer_id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandizers.user_id', 'left')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($request->region_ids), 2), 2) . '%';
            }

            $listing = DB::table('regions')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('region')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_user_ids = array();
            if (count($salesman_infos)) {
                $salesman_user_ids = $salesman_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_merchandiser = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
                ->whereIn('merchandiser_id', $salesman_user_ids)
                ->get();

            $all_customers = array();
            if (count($customer_merchandiser)) {
                $all_customers = $customer_merchandiser->pluck('customer_id')->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('salesman_id', $salesman_user_ids)
                // ->whereIn('customer_id', $all_customers)
                // ->groupBy('date')
                ->get();

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            // if ($no_of_visits != 0 && $no_of_customers != 0) {
            //     $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            // } else {
            //     $percentage = "0%";
            // }

            $trends_data_query = DB::table('salesman_infos')
                ->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                // ->groupBy('customer_visits.salesman_id')
                ->whereBetween('customer_visits.date', [$start_date, $end_date])
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                // $sname = "salesman_infos.salesman_supervisor as name";
                $sname = "salesmanSupervisor.firstname as name";
                $sgBy = 'salesman_infos.salesman_supervisor';
            } else {
                $sname = "users.firstname as name";
                $sgBy = 'users.id';
            }

            $comparison_query = DB::table('salesman_infos')
                ->select(
                    $sname,
                    DB::raw('count(customer_visits.id) as steps')
                )
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $comparison_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $comparison = $comparison_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->whereBetween('customer_visits.date', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy($sgBy)
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                // $name = "DISTINCT salesman_infos.salesman_supervisor as RES";
                $name = "DISTINCT salesmanSupervisor.firstname as RES";
                $gBy = 'salesman_infos.salesman_supervisor';
            } else {
                $name = "DISTINCT users.firstname as RES";
                $gBy = 'customer_visits.salesman_id';
            }

            $customer_details_query = DB::table('salesman_infos')
                ->select(
                    DB::raw($name),
                    DB::raw('COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                    DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                    // DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
                    DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
                )
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $customer_details_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $customer_details = $customer_details_query->whereBetween('customer_visits.date', [$start_date, $end_date])
                ->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                // ->groupBy('salesman_infos.user_id')
                ->groupBy($gBy)
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($salesman_user_ids), 2), 2) . '%';
            }

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesmanSupervisor.firstname AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $listing = $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date])
                ->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        }

        $coverage = new \stdClass();
        $coverage->title = "Coverage";
        $coverage->text = "Outlets Visited atleast once this month vs all outlet in the market";
        $coverage->percentage = $percentage;
        $coverage->trends = $trends_data;
        $coverage->comparison = $comparison;
        $coverage->contribution = $comparison;
        $coverage->details = $customer_details;
        $coverage->listing = $listing;

        return $coverage;
    }

    private function coverage($request, $start_date, $end_date)
    {

        $start_date = Carbon::parse($start_date)->addDay()->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');

        if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $comparison = array();
            $trends_data = array();
            $all_cusotmers = array();
            $all_salesmans = array();

            $customer_details = array();
            foreach ($request->supervisor as $supervisor) {
                $comparison_data = array();
                $customer_details_data = array();
                $visit_execution = 0;
                $all_salesman = SalesmanInfo::where('salesman_supervisor', $supervisor)->get();

                if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                    $date_diff = 1;
                } else {
                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                }

                $salesman_ids = array();

                if (count($all_salesman)) {
                    $salesman_ids = $all_salesman->pluck('user_id')->toArray();
                    $all_salesmans[] = $salesman_ids;
                }

                if (count($salesman_ids)) {
                    foreach ($salesman_ids as $salesman_id) {

                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (count($customerMerchandiser)) {
                            $all_cusotmers[] = $customerMerchandiser->pluck('customer_id')->toArray();
                        }

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        }

                        if (isset($customer_visit[0]) && $customer_visit[0]) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        $salesman = '';

                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $EXECUTION = 0;
                        if (count($customerMerchandiser) != 0 && $visit_execution != 0) {
                            $EXECUTION = round(($visit_execution / (count($customerMerchandiser) * $date_diff)) * 100, 2);
                        }

                        $customer_details_data[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                            'VISITS' => $visit_execution,
                            'EXECUTION' => $EXECUTION
                        );
                    }
                }

                $supervisor = User::find($supervisor);

                if (count($comparison_data)) {
                    $comparison[] = array(
                        'name' => $supervisor->firstname,
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                $percentage = '0%';
                $listing = array();

                if (count($customer_details_data)) {
                    $customer_details[] = array(
                        'RES' => $supervisor->firstname,
                        'TOTAL_OUTLETS' => array_sum(collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray()),
                        'VISITS' => array_sum(collect($customer_details_data)->pluck('VISITS')->toArray()),
                        'EXECUTION' => round(array_sum(collect($customer_details_data)->pluck('VISITS')->toArray()) / array_sum(collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray()) * 100, 2)
                    );
                }
            }

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = collect($customer_details)->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($request->supervisor), 2), 2) . '%';
            }


            $all_customer_ids = Arr::collapse($all_cusotmers);
            $all_salesman_ids = Arr::collapse($all_salesmans);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();


            $listing_data = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.date) as date,
                        customerInfo.firstname AS customer,
                        customer_infos.customer_code AS customerCode,
                        customer_categories.customer_category_name AS category,
                        salesman.firstname AS merchandiser,
                        salesman_infos.salesman_supervisor AS supervisor,
                        channels.name AS channel,
                        regions.region_name AS region,
                        customer_visits.total_task AS total_tasks_planned,
                        SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                        COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_data->where('customer_visits.date', $request->start_date);
            } else {
                $listing_data->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $listing = $listing_data->whereIn('customer_visits.customer_id', $all_salesman_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();

            $customer_details = array();
            $comparison = array();
            foreach ($request->nsm as $nsm) {
                $all_customers = getSalesman(true, $nsm);
                $salesman_user_ids = getSalesman(false, $nsm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $salesman_user_ids;

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");

                // $no_of_visits = count($customer_visits);
                $no_of_customers = count($all_customers) * $date_diff;

                // get ASM
                $invite_user = User::find($nsm);

                $comparison_data = array();
                $trends_data = array();
                $details = array();
                if (count($salesman_user_ids)) {
                    foreach ($salesman_user_ids as $salesman_id) {
                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        }

                        $visit_execution = 0;
                        if (isset($customer_visit[0]) && $no_of_customers != 0) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $no_visit = $visit_execution;

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $EXECUTION = 0;
                        if (count($customerMerchandiser) != 0 && $no_visit != 0) {
                            $EXECUTION = round(($no_visit / (count($customerMerchandiser) * $date_diff)) * 100, 2);
                        }

                        $details[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                            'VISITS' => $no_visit,
                            'EXECUTION' => round($EXECUTION, 2)
                        );
                    }
                }

                if ($comparison_data) {
                    $comparison[] = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                if (count($details)) {
                    $TOTAL_OUTLETS = collect($details)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($details)->pluck('VISITS')->toArray();
                    $EXECUTION = 0;

                    if (array_sum($TOTAL_OUTLETS) != 0 && array_sum($VISITS) != 0) {
                        $EXECUTION = round((array_sum($VISITS) / array_sum($TOTAL_OUTLETS)) * 100, 2);
                    }

                    $customer_details[] = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => $EXECUTION
                    );
                }
            }

            $percentage = '0%';
            if (count($customer_details)) {
                $exe = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($exe) / count($request->nsm), 2) . "%";
            }

            $all_customers = Arr::collapse($all_customer_array);
            $salesman_user_ids = Arr::collapse($all_salesman_array);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesmanSupervisor.firstname AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.total_task AS total_tasks_planned,
                SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();

            $customer_details = array();
            $comparison = array();
            foreach ($request->asm as $asm) {
                $all_customers = getSalesman(true, $asm);
                $salesman_user_ids = getSalesman(false, $asm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $salesman_user_ids;

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");

                // $no_of_visits = count($customer_visits);
                $no_of_customers = count($all_customers) * $date_diff;

                // get ASM
                $invite_user = User::find($asm);

                $comparison_data = array();
                $trends_data = array();
                $details = array();
                if (count($salesman_user_ids)) {
                    foreach ($salesman_user_ids as $salesman_id) {
                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                            );
                        }

                        $visit_execution = 0;
                        if (isset($customer_visit[0]) && $no_of_customers != 0) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $no_visit = $visit_execution;

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $EXECUTION = 0;
                        if (count($customerMerchandiser) != 0 && $no_visit != 0) {
                            $EXECUTION = round(($no_visit / (count($customerMerchandiser) * $date_diff)) * 100, 2);
                        }

                        $details[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                            'VISITS' => $no_visit,
                            'EXECUTION' => round($EXECUTION, 2)
                        );
                    }
                }

                if ($comparison_data) {
                    $comparison[] = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                if (count($details)) {
                    $TOTAL_OUTLETS = collect($details)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($details)->pluck('VISITS')->toArray();
                    $EXECUTION = 0;

                    if (array_sum($TOTAL_OUTLETS) != 0 && array_sum($VISITS) != 0) {
                        $EXECUTION = round((array_sum($VISITS) / array_sum($TOTAL_OUTLETS)) * 100, 2);
                    }

                    $customer_details[] = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => $EXECUTION
                    );
                }
            }

            $percentage = '0%';
            if (count($customer_details)) {
                $exe = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($exe) / count($request->asm), 2) . "%";
            }

            $all_customers = Arr::collapse($all_customer_array);
            $salesman_user_ids = Arr::collapse($all_salesman_array);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesmanSupervisor.firstname AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.total_task AS total_tasks_planned,
                SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('added_on', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            $trends_data = DB::table('channels')->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('channels')->select('channels.name as name', DB::raw('count(customer_visits.id) as steps'))
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();


            $customer_details = DB::table('channels')->select(
                DB::raw('DISTINCT channels.name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
            )
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($request->channel_ids), 2), 2) . '%';
            }

            $listing = DB::table('channels')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                // ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('channel')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')->whereIn('region_id', $request->region_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            $trends_data = DB::table('regions')->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('regions')->select('regions.region_name as name', DB::raw('count(customer_visits.id) as steps'))
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $customer_details = DB::table('regions')->select(
                DB::raw('DISTINCT regions.region_name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_infos.id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
            )
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($request->region_ids), 2), 2) . '%';
            }

            $listing = DB::table('regions')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('region')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_user_ids = array();
            if (count($salesman_infos)) {
                $salesman_user_ids = $salesman_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_merchandiser = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
                ->whereIn('merchandiser_id', $salesman_user_ids)
                ->get();

            $all_customers = array();
            if (count($customer_merchandiser)) {
                $all_customers = $customer_merchandiser->pluck('customer_id')->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('salesman_id', $salesman_user_ids)
                // ->whereIn('customer_id', $all_customers)
                ->groupBy('date')
                ->get();


            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $date_diff = 1;
            } else {
                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
            }

            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $comparison = array();
            $trends_data = array();
            if (count($salesman_user_ids)) {
                $customer_details = array();
                foreach ($salesman_user_ids as $salesman_id) {
                    $user = User::find($salesman_id);

                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                    if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                        $customer_visit = DB::select(
                            "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id') as x"
                        );
                    } else {
                        $customer_visit = DB::select(
                            "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                        );
                    }

                    $visit_execution = 0;
                    if (isset($customer_visit[0]) && $no_of_customers != 0) {
                        $visit_execution = ($customer_visit[0]->visit_count);
                    }

                    $salesman = '';
                    if (is_object($user)) {
                        $salesman = $user->firstname;
                    }

                    $no_visit = $visit_execution;

                    $comparison[] = array(
                        'name' => $salesman,
                        'steps' => $visit_execution
                    );

                    $salesman = '';
                    if (is_object($user)) {
                        $salesman = $user->firstname;
                    }

                    $EXECUTION = 0;
                    if (count($customerMerchandiser) != 0 && $no_visit != 0) {
                        $EXECUTION = round(($no_visit / (count($customerMerchandiser) * $date_diff)) * 100, 2);
                    }

                    $customer_details[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser) * $date_diff,
                        'VISITS' => $no_visit,
                        'EXECUTION' => $EXECUTION
                    );
                }
            }

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = collect($customer_details)->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = number_format(round(array_sum($TOTAL_OUTLETS) / count($salesman_user_ids), 2), 2) . '%';
            }

            $listing_query = DB::table('salesman_infos')->select(
                DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesmanSupervisor.firstname AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits')
            )
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason');

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.customer_id')
                ->groupBy('customer_visits.date')
                ->groupBy('merchandiser')
                ->get();
        }

        $coverage = new \stdClass();
        $coverage->title = "Coverage";
        $coverage->text = "Outlets Visited atleast once this month vs all outlet in the market";
        $coverage->percentage = $percentage;
        $coverage->trends = $trends_data;
        $coverage->comparison = $comparison;
        $coverage->contribution = $comparison;
        $coverage->details = $customer_details;
        $coverage->listing = $listing;

        return $coverage;
    }

    private function activeOutlets($request, $start_date, $end_date)
    {
        $start_date = Carbon::parse($start_date)->addDay()->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');

        if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $comparison = array();
            $trends_data = array();
            $all_cusotmers = array();
            $all_salesmans = array();

            $customer_details = array();
            foreach ($request->supervisor as $supervisor) {
                $comparison_data        = array();
                $customer_details_data  = array();
                $visit_execution        = 0;
                $visit_count_execution  = 0;
                $all_salesman = SalesmanInfo::where('salesman_supervisor', $supervisor)->where('status', 1)->get();

                if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                    $date_diff = 1;
                } else {
                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                }

                $salesman_ids = array();

                if (count($all_salesman)) {
                    $salesman_ids = $all_salesman->pluck('user_id')->toArray();
                    $all_salesmans[] = $salesman_ids;
                }

                if (count($salesman_ids)) {
                    foreach ($salesman_ids as $salesman_id) {
                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (count($customerMerchandiser)) {
                            $all_cusotmers[] = $customerMerchandiser->pluck('customer_id')->toArray();
                        }

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );
                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );

                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        }

                        if (isset($customer_visit[0]) && $customer_visit[0]) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        if (isset($customer_visit_execustion[0]) && $customer_visit_execustion[0]) {
                            $visit_count_execution = ($customer_visit_execustion[0]->visit_count_execution);
                        }

                        $salesman = '';

                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $EXECUTION = 0;
                        if ($visit_count_execution) {
                            $EXECUTION = $visit_count_execution;
                        }

                        $customer_details_data[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser),
                            'VISITS' => $visit_execution,
                            'EXECUTION' => $EXECUTION
                        );
                    }
                }

                $supervisor = User::find($supervisor);

                if (count($comparison_data)) {
                    $comparison[] = array(
                        'name' => $supervisor->firstname,
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                $percentage = '0';
                $listing = array();

                if (count($customer_details_data)) {
                    $customer_details[] = array(
                        'RES' => $supervisor->firstname,
                        'TOTAL_OUTLETS' => array_sum(collect($customer_details_data)->pluck('TOTAL_OUTLETS')->toArray()),
                        'VISITS' => array_sum(collect($customer_details_data)->pluck('VISITS')->toArray()),
                        'EXECUTION' => array_sum(collect($customer_details_data)->pluck('EXECUTION')->toArray())
                    );
                }
            }

            $percentage = '0';
            if (count($customer_details)) {
                $VISITS = collect($customer_details)->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = array_sum($TOTAL_OUTLETS);
            }

            $all_customer_ids = Arr::collapse($all_cusotmers);
            $all_salesman_ids = Arr::collapse($all_salesmans);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.customer_id', $all_customer_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();


            $listing_data = DB::table('salesman_infos')->select(DB::raw('DATE(customer_visits.date) as date,
                        customerInfo.firstname AS customer,
                        customer_infos.customer_code AS customerCode,
                        customer_categories.customer_category_name AS category,
                        salesman.firstname AS merchandiser,
                        superVisor.firstname AS supervisor,
                        channels.name AS channel,
                        regions.region_name AS region,
                        customer_visits.total_task AS total_tasks_planned,
                        SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                        COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as superVisor', 'superVisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'like', 'open')
                ->whereNull('customer_visits.reason');

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_data->where('customer_visits.date', $request->start_date);
            } else {
                $listing_data->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_data->whereIn('customer_visits.salesman_id', $all_salesman_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->where('salesman_infos.status', 1)
                // ->groupBy('supervisor')
                ->groupBy('customerCode')
                // ->groupBy('customer_visits.date')
                // ->groupBy('merchandiser')
                ->orderBy('date', 'desc')
                ->get();
        } else if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();

            $customer_details = array();
            $comparison = array();
            foreach ($request->nsm as $nsm) {
                $all_customers = getSalesman(true, $nsm);
                $salesman_user_ids = getSalesman(false, $nsm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $salesman_user_ids;

                if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                    $date_diff = 1;
                } else {
                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                }
                $no_of_customers = count($all_customers);

                // get NSM
                $invite_user = User::find($nsm);

                $comparison_data = array();
                $trends_data = array();
                $details = array();
                if (count($salesman_user_ids)) {
                    foreach ($salesman_user_ids as $salesman_id) {
                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );
                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );

                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        }

                        $visit_execution = 0;
                        if (isset($customer_visit[0]) && $no_of_customers != 0) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        if (isset($customer_visit_execustion[0]) && $customer_visit_execustion[0]) {
                            $visit_count_execution = ($customer_visit_execustion[0]->visit_count_execution);
                        }

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $no_visit = $visit_execution;

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $EXECUTION = 0;
                        if ($visit_count_execution != 0) {
                            $EXECUTION = $visit_count_execution;
                        }

                        $details[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser),
                            'VISITS' => $no_visit,
                            'EXECUTION' => $visit_count_execution
                        );
                    }
                }

                if ($comparison_data) {
                    $comparison[] = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                if (count($details)) {
                    $TOTAL_OUTLETS = collect($details)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($details)->pluck('VISITS')->toArray();
                    $EXECUTION = collect($details)->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => array_sum($EXECUTION)
                    );
                }
            }

            $percentage = '0';
            if (count($customer_details)) {
                $exe = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($exe));
            }

            $all_customers = Arr::collapse($all_customer_array);
            $salesman_user_ids = Arr::collapse($all_salesman_array);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesmanSupervisor.firstname AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.total_task AS total_tasks_planned,
                SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'like', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->where('salesman_infos.status', 1)
                ->groupBy('customerCode')
                // ->groupBy('customer_visits.salesman_id')
                // ->groupBy('customer_visits.date')
                ->orderBy('date', 'desc')
                ->get();
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();

            $customer_details = array();
            $comparison = array();
            foreach ($request->asm as $asm) {
                $all_customers = getSalesman(true, $asm);
                $salesman_user_ids = getSalesman(false, $asm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $salesman_user_ids;

                if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                    $date_diff = 1;
                } else {
                    $diff = date_diff(date_create($start_date), date_create($end_date));
                    $date_diff = $diff->format("%a");
                }
                // get ASM
                $invite_user = User::find($asm);

                $comparison_data = array();
                $trends_data = array();
                $details = array();
                if (count($salesman_user_ids)) {
                    foreach ($salesman_user_ids as $salesman_id) {
                        $user = User::find($salesman_id);

                        $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                        if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );
                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        } else {
                            $customer_visit = DB::select(
                                "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                            );

                            $customer_visit_execustion = DB::select(
                                "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                            );
                        }

                        $visit_execution = 0;
                        if (isset($customer_visit[0])) {
                            $visit_execution = ($customer_visit[0]->visit_count);
                        }

                        if (isset($customer_visit_execustion[0])) {
                            $visit_count_execution = ($customer_visit_execustion[0]->visit_count_execution);
                        }

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $no_visit = $visit_execution;

                        $comparison_data[] = array(
                            'name' => $salesman,
                            'steps' => $visit_execution
                        );

                        $salesman = '';
                        if (is_object($user)) {
                            $salesman = $user->firstname;
                        }

                        $EXECUTION = 0;
                        if ($visit_count_execution != 0) {
                            $EXECUTION = round($visit_count_execution);
                        }

                        $details[] = array(
                            'RES' => $salesman,
                            'TOTAL_OUTLETS' => count($customerMerchandiser),
                            'VISITS' => $no_visit,
                            'EXECUTION' => round($EXECUTION)
                        );
                    }
                }

                if ($comparison_data) {
                    $comparison[] = array(
                        'name' => model($invite_user, 'firstname'),
                        'steps' => array_sum(collect($comparison_data)->pluck('steps')->toArray())
                    );
                }

                if (count($details)) {
                    $TOTAL_OUTLETS = collect($details)->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = collect($details)->pluck('VISITS')->toArray();
                    $EXECUTION_SUM = collect($details)->pluck('EXECUTION')->toArray();
                    $EXECUTION = 0;

                    if (array_sum($EXECUTION_SUM) != 0) {
                        $EXECUTION = array_sum($EXECUTION_SUM);
                    }

                    $customer_details[] = array(
                        'RES' => model($invite_user, 'firstname'),
                        'TOTAL_OUTLETS' => array_sum($TOTAL_OUTLETS),
                        'VISITS' => array_sum($VISITS),
                        'EXECUTION' => $EXECUTION
                    );
                }
            }

            $percentage = '0%';
            if (count($customer_details)) {
                $exe = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($exe));
            }

            $all_customers = Arr::collapse($all_customer_array);
            $salesman_user_ids = Arr::collapse($all_salesman_array);

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                customerInfo.firstname AS customer,
                customer_infos.customer_code AS customerCode,
                customer_categories.customer_category_name AS category,
                salesman.firstname AS merchandiser,
                salesmanSupervisor.firstname AS supervisor,
                channels.name AS channel,
                regions.region_name AS region,
                customer_visits.total_task AS total_tasks_planned,
                SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'like', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->where('salesman_infos.status', 1)
                ->groupBy('customerCode')
                // ->groupBy('customer_visits.salesman_id')
                // ->groupBy('customer_visits.date')
                ->orderBy('date', 'desc')
                ->get();
        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('added_on', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $date_diff = 1;
            } else {
                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
            }
            $no_of_visits = count($customer_visits);
            $no_of_customers = count($all_customers);

            $trends_data = DB::table('channels')
                ->select(
                    'customer_visits.added_on as date',
                    DB::raw('count(customer_visits.id) as value')
                )
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('channels')->select(
                'channels.name as name',
                DB::raw('count(customer_visits.id) as steps')
            )
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();


            $customer_details = DB::table('channels')->select(
                DB::raw('DISTINCT channels.name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_infos.id)) AS EXECUTION')
            )
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('channels.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('TOTAL_OUTLETS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($EXECUTION) / count($request->channel_ids));
            }

            $listing = DB::table('channels')->select(
                DB::raw('DISTINCT DATE(customer_visits.date) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits')
            )
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('channel')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')->whereIn('region_id', $request->region_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->where('shop_status', 'open')
                ->whereNull('reason')
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                // ->groupBy('trip_id')
                ->get();

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $date_diff = 1;
            } else {
                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
            }

            $no_of_customers = count($all_customers);

            $trends_data = DB::table('regions')->select(
                'customer_visits.added_on as date',
                DB::raw('count(customer_visits.id) as value')
            )
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $comparison = DB::table('regions')->select(
                'regions.region_name as name',
                DB::raw('count(customer_visits.id) as steps')
            )
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $customer_details = DB::table('regions')->select(
                DB::raw('DISTINCT regions.region_name as RES'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / COUNT(DISTINCT customer_infos.id)) AS EXECUTION')
            )
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_infos.user_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('regions.id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $VISITS = $customer_details->pluck('VISITS')->toArray();
                $TOTAL_OUTLETS = $customer_details->pluck('TOTAL_OUTLETS')->toArray();
                $EXECUTION = $customer_details->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($EXECUTION) / count($request->region_ids));
            }

            $listing = DB::table('regions')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'salesman.id')
                ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->where('customer_visits.shop_status', 'open')
                ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_visits.customer_id', $all_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.added_on')
                ->groupBy('region')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);

                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_user_ids = array();
            if (count($salesman_infos)) {
                $salesman_user_ids = $salesman_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_merchandiser = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
                ->whereIn('merchandiser_id', $salesman_user_ids)
                ->get();

            $all_customers = array();
            if (count($customer_merchandiser)) {
                $all_customers = $customer_merchandiser->pluck('customer_id')->toArray();
            }

            $no_of_customers = count($all_customers);

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $date_diff = 1;
            } else {
                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
            }

            $comparison = array();
            $trends_data = array();

            if (count($salesman_user_ids)) {
                $customer_details = array();
                foreach ($salesman_user_ids as $salesman_id) {
                    $user = User::find($salesman_id);

                    $customerMerchandiser = CustomerMerchandiser::where('merchandiser_id', $salesman_id)->get();

                    // old code
                    // if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                    //     $start_date = Carbon::parse($request->start_date)->addDay()->format('Y-m-d');
                    //     $customer_visit = DB::select(
                    //         "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` = '$start_date' AND `salesman_id` = '$salesman_id') as x"
                    //     );
                    // } else {
                    //     $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
                    //     $customer_visit = DB::select(
                    //         "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'open' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id') as x"
                    //     );
                    // }

                    if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                        $customer_visit = DB::select(
                            "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                        );
                        $customer_visit_execustion = DB::select(
                            "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` = '$request->start_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                        );
                    } else {
                        $customer_visit = DB::select(
                            "SELECT COUNT(*) as visit_count FROM (SELECT DISTINCT date, customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `date`,`customer_id`) as x"
                        );

                        $customer_visit_execustion = DB::select(
                            "SELECT COUNT(*) as visit_count_execution FROM (SELECT DISTINCT customer_id FROM `customer_visits` WHERE `shop_status` = 'OPEN' AND `reason` IS NULL AND `date` BETWEEN '$start_date' AND '$end_date' AND `salesman_id` = '$salesman_id' group by `customer_id`) as x"
                        );
                    }

                    $visit_execution = 0;
                    if (isset($customer_visit[0])) {
                        $visit_execution = ($customer_visit[0]->visit_count);
                    }

                    if (isset($customer_visit_execustion[0]) && $customer_visit_execustion[0]) {
                        $visit_count_execution = ($customer_visit_execustion[0]->visit_count_execution);
                    }

                    $salesman = '';
                    if (is_object($user)) {
                        $salesman = $user->firstname;
                    }

                    $no_visit = $visit_execution;

                    $comparison[] = array(
                        'name' => $salesman,
                        'steps' => $visit_execution
                    );

                    $salesman = '';
                    if (is_object($user)) {
                        $salesman = $user->firstname;
                    }

                    $EXECUTION = 0;
                    if ($visit_count_execution != 0) {
                        $EXECUTION = $visit_count_execution;
                    }

                    $customer_details[] = array(
                        'RES' => $salesman,
                        'TOTAL_OUTLETS' => count($customerMerchandiser),
                        'VISITS' => $no_visit,
                        'EXECUTION' => $EXECUTION
                    );
                }
            }

            $trends_data_query = DB::table('salesman_infos')
                ->select(
                    'customer_visits.date as date',
                    DB::raw('count(DISTINCT customer_visits.customer_id) as value')
                )
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $trends_data_query->where('customer_visits.date', $request->start_date);
            } else {
                $trends_data_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }
            $trends_data = $trends_data_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            $percentage = '0';
            if (count($customer_details)) {
                $EXECUTION = collect($customer_details)->pluck('EXECUTION')->toArray();
                $percentage = round(array_sum($EXECUTION));
            }

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(customer_visits.added_on) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesmanSupervisor.firstname AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    customer_visits.total_task AS total_tasks_planned,
                    SUM(customer_visits.completed_task) AS no_of_tasks_completed,
                    COUNT(DISTINCT customer_visits.id) as total_visits'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('customer_visits', 'salesman_infos.user_id', '=', 'customer_visits.salesman_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->where('customer_visits.shop_status', 'like', 'open')
                ->whereNull('customer_visits.reason');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $listing_query->where('customer_visits.date', $request->start_date);
            } else {
                $listing_query->whereBetween('customer_visits.date', [$start_date, $end_date]);
            }

            $listing = $listing_query->whereIn('customer_visits.salesman_id', $salesman_user_ids)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->where('salesman_infos.status', 1)
                ->groupBy('customerCode')
                // ->groupBy('customer_visits.salesman_id')
                // ->groupBy('customer_visits.date')
                ->orderBy('date', 'desc')
                ->get();
        }

        $activeOutlets = new \stdClass();
        $activeOutlets->title = "Active Outlets";
        $activeOutlets->text = "Where atleast one order was made from a visit this month";
        $activeOutlets->percentage = $percentage;
        $activeOutlets->trends = $trends_data;
        $activeOutlets->comparison = $comparison;
        $activeOutlets->contribution = $comparison;
        $activeOutlets->details = $customer_details;
        $activeOutlets->listing = $listing;

        return $activeOutlets;
    }

    private function activeOutlets2($request, $start_date, $end_date)
    {
        if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();
            $comparison = array();
            $customer_details = array();
            $orders_customers_array = array();

            foreach ($request->asm as $asm) {
                $all_customers = getSalesman(true, $asm);
                $salesman_user_ids = getSalesman(false, $asm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $all_customers;

                $orders = Order::select('id', 'customer_id', 'depot_id', 'order_type_id', 'salesman_id', 'order_number', 'order_date', 'due_date', 'created_at')
                    ->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->whereIn('customer_id', $all_customers)
                    ->get();

                $orders_customers = array();
                if (count($orders)) {
                    $orders_customers = $orders->pluck('customer_id');
                }

                $orders_customers_array[] = $orders_customers;

                $comparison_data = DB::table('salesman_infos')->select(
                    'users.firstname as name',
                    DB::raw('count(orders.id) as steps')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                    ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                    ->whereIn('orders.customer_id', $orders_customers)
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->groupBy('users.id')
                    ->get();

                $invite_user = User::find($asm);

                if (count($comparison_data)) {
                    $steps = $comparison_data->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $invite_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_visits = count($orders_customers);
                $no_of_customers = count($all_customers);

                $customer_details_data = DB::table('salesman_infos')->select(
                    DB::raw('DISTINCT users.firstname as RES'),
                    DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                    DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                    DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ') AS EXECUTION')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                    ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->whereIn('customer_infos.user_id', $orders_customers)
                    ->where('users.organisation_id', $this->organisation_id)
                    ->groupBy('users.id')
                    ->get();

                if (count($customer_details_data)) {

                    $TOTAL_OUTLETS = $customer_details_data->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = $customer_details_data->pluck('VISITS')->toArray();
                    $EXECUTION = $customer_details_data->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        "RES" => $invite_user->firstname,
                        "TOTAL_OUTLETS" => array_sum($TOTAL_OUTLETS),
                        "VISITS" => array_sum($VISITS),
                        "EXECUTION" => array_sum($EXECUTION)
                    );
                }
            }

            $all_customers = Arr::collapse($all_customer_array);
            $all_salesman = Arr::collapse($all_salesman_array);
            $orders_customers = Arr::collapse($orders_customers_array);

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($orders_customers);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $trends_data = DB::table('salesman_infos')->select('orders.created_at as date', DB::raw('count(orders.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->orderBy('orders.created_at')
                ->get();

            $listing = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(orders.created_at) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    orders.order_number as orderNo,
                    orders.grand_total as orderValue'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('orders.created_at')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $all_customer_array = array();
            $all_salesman_array = array();
            $percentage = array();
            $comparison = array();
            $customer_details = array();
            $orders_customers_array = array();

            foreach ($request->nsm as $nsm) {
                $all_customers = getSalesman(true, $nsm);
                $salesman_user_ids = getSalesman(false, $nsm);

                $all_customer_array[] = $all_customers;
                $all_salesman_array[] = $all_customers;

                $orders = Order::select('id', 'customer_id', 'depot_id', 'order_type_id', 'salesman_id', 'order_number', 'order_date', 'due_date', 'created_at')
                    ->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                    ->whereBetween('created_at', [$start_date, $end_date])
                    ->whereIn('customer_id', $all_customers)
                    ->get();

                $orders_customers = array();
                if (count($orders)) {
                    $orders_customers = $orders->pluck('customer_id');
                }

                $orders_customers_array[] = $orders_customers;

                $comparison_data = DB::table('salesman_infos')->select(
                    'users.firstname as name',
                    DB::raw('count(orders.id) as steps')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                    ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                    ->whereIn('orders.customer_id', $orders_customers)
                    ->where('salesman_infos.organisation_id', $this->organisation_id)
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->groupBy('users.id')
                    ->get();

                $invite_user = User::find($nsm);

                if (count($comparison_data)) {
                    $steps = $comparison_data->pluck('steps')->toArray();
                    $comparison[] = array(
                        'name' => $invite_user->firstname,
                        'steps' => array_sum($steps)
                    );
                }

                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $no_of_visits = count($orders_customers);
                $no_of_customers = count($all_customers) * $date_diff;

                $customer_details_data = DB::table('salesman_infos')->select(
                    DB::raw('DISTINCT users.firstname as RES'),
                    DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                    DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                    DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
                )
                    ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                    ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                    ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                    ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->whereIn('customer_infos.user_id', $orders_customers)
                    ->where('users.organisation_id', $this->organisation_id)
                    ->groupBy('users.id')
                    ->get();

                if (count($customer_details_data)) {

                    $TOTAL_OUTLETS = $customer_details_data->pluck('TOTAL_OUTLETS')->toArray();
                    $VISITS = $customer_details_data->pluck('VISITS')->toArray();
                    $EXECUTION = $customer_details_data->pluck('EXECUTION')->toArray();

                    $customer_details[] = array(
                        "RES" => $invite_user->firstname,
                        "TOTAL_OUTLETS" => array_sum($TOTAL_OUTLETS),
                        "VISITS" => array_sum($VISITS),
                        "EXECUTION" => array_sum($EXECUTION)
                    );
                }
            }

            $all_customers = Arr::collapse($all_customer_array);
            $all_salesman = Arr::collapse($all_salesman_array);
            $orders_customers = Arr::collapse($orders_customers_array);

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($orders_customers);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $trends_data = DB::table('salesman_infos')->select('orders.created_at as date', DB::raw('count(orders.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->orderBy('orders.created_at')
                ->get();

            $listing = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(orders.created_at) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    orders.order_number as orderNo,
                    orders.grand_total as orderValue'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('orders.created_at')
                ->groupBy('merchandiser')
                ->get();
        } else if (is_array($request->channel_id) && sizeof($request->channel_id) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')->toArray();
            }

            $orders = Order::select('id', 'customer_id', 'depot_id', 'order_type_id', 'salesman_id', 'order_number', 'order_date', 'due_date', 'created_at')
                ->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->whereBetween('created_at', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                ->get();

            $orders_customers = array();
            if (count($orders)) {
                $orders_customers = $orders->pluck('customer_id');
            }

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($orders_customers);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $trends_data = DB::table('channels')->select(
                'orders.created_at as date',
                DB::raw('count(orders.id) as value')
            )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->orderBy('orders.created_at')
                ->get();

            $comparison = DB::table('channels')->select(
                'channels.name as name',
                DB::raw('count(orders.id) as steps')
            )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')

                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('channels.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy('users.id')
                ->get();

            $customer_details = DB::table('channels')->select(
                DB::raw('DISTINCT channels.name as RES'),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_merchandizers', 'customer_merchandizers.merchandizer_id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandizers.user_id', 'left')
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereIn('customer_infos.user_id', $orders_customers)
                ->where('users.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy('channels.id')
                ->get();

            $listing = DB::table('channels')->select(
                DB::raw('DISTINCT DATE(orders.created_at) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    orders.order_number as orderNo,
                    orders.grand_total as orderValue')
            )
                ->join('customer_infos', 'customer_infos.channel_id', '=', 'channels.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandisers.merchandiser_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_infos.merchandiser_id')
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                // ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->where('channels.organisation_id', $this->organisation_id)
                ->groupBy('orders.created_at')
                ->groupBy('channel')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_infos->pluck('user_id')->toArray();
            }

            $orders = Order::select('id', 'customer_id', 'depot_id', 'order_type_id', 'salesman_id', 'order_number', 'order_date', 'due_date', 'created_at')
                ->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->whereBetween('created_at', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                ->get();

            $orders_customers = array();
            if (count($orders)) {
                $orders_customers = $orders->pluck('customer_id');
            }

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($orders_customers);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $trends_data = DB::table('regions')->select(
                'orders.created_at as date',
                DB::raw('count(orders.id) as value')
            )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'regions.id', '=', 'customer_infos.region_id')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->where('regions.organisation_id', $this->organisation_id)
                ->orderBy('orders.created_at')
                ->get();

            $comparison = DB::table('regions')->select('regions.region_name as name', DB::raw('count(orders.id) as steps'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy('regions.id')
                ->get();

            $customer_details = DB::table('regions')->select(
                DB::raw('DISTINCT regions.region_name as RES'),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_merchandizers', 'customer_merchandizers.merchandizer_id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandizers.user_id', 'left')
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('regions.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy('regions.id')
                ->get();

            $listing = DB::table('regions')->select(
                DB::raw('DISTINCT DATE(orders.created_at) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesman_infos.salesman_supervisor AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    orders.order_number as orderNo,
                    orders.grand_total as orderValue')
            )
                ->join('customer_infos', 'customer_infos.region_id', '=', 'regions.id')
                ->join('customer_merchandisers', 'customer_merchandisers.customer_id', '=', 'customer_infos.user_id')
                ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandisers.merchandiser_id')
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                // ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('orders.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy('orders.created_at')
                ->groupBy('region')
                ->get();
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                // $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                //     ->where(function ($query) use ($supervisor) {
                //         if (!empty($supervisor)) {
                //             foreach ($supervisor as $key => $filter_val) {
                //                 if ($key == 0) {
                //                     $query->where('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 } else {
                //                     $query->orWhere('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 }
                //             }
                //         }
                //     })
                //     ->where('status', 1)
                //     ->groupBy('salesman_supervisor')
                //     ->get();
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $user = request()->user();
                $all_salesman = getSalesman(false, $user->id);
                if (count($all_salesman)) {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->whereIn('user_id', $all_salesman)
                        ->get();
                } else {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }
            }

            $salesman_user_ids = array();
            if (count($salesman_infos)) {
                $salesman_user_ids = $salesman_infos->pluck('user_id')
                    ->toArray();
            }

            $customer_merchandiser = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
                ->whereIn('merchandiser_id', $salesman_user_ids)
                ->get();

            $all_customers = array();
            if (count($customer_merchandiser)) {
                $all_customers = $customer_merchandiser->pluck('customer_id')->toArray();
            }

            $orders = Order::select('id', 'customer_id', 'depot_id', 'order_type_id', 'salesman_id', 'order_number', 'order_date', 'due_date', 'created_at')
                ->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
                ->whereBetween('created_at', [$start_date, $end_date])
                ->whereIn('customer_id', $all_customers)
                ->get();

            $orders_customers = array();
            if (count($orders)) {
                $orders_customers = $orders->pluck('customer_id');
            }

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $no_of_visits = count($orders_customers);
            $no_of_customers = count($all_customers) * $date_diff;

            if ($no_of_visits != 0 && $no_of_customers != 0) {
                $percentage = round($no_of_visits / $no_of_customers * 100, 2) . '%';
            } else {
                $percentage = "0%";
            }

            $trends_data_query = DB::table('salesman_infos')->select('orders.created_at as date', DB::raw('count(orders.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $trends_data_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $trends_data = $trends_data_query->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->orderBy('orders.created_at')
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $sname = "salesmanSupervisor.firstname as name";
                $sgBy = 'salesman_infos.salesman_supervisor';
            } else {
                $sname = "users.firstname as name";
                $sgBy = 'users.id';
            }

            $comparison_query = DB::table('salesman_infos')->select(
                $sname,
                DB::raw('count(orders.id) as steps')
            )
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $comparison_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $comparison = $comparison_query->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy($sgBy)
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $name = "DISTINCT salesmanSupervisor.firstname as RES";
                $gBy = 'salesman_infos.salesman_supervisor';
            } else {
                $name = "DISTINCT users.firstname as RES";
                $gBy = 'users.id';
            }

            $customer_details_data = DB::table('salesman_infos')->select(
                DB::raw($name),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
            )
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $customer_details_data->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $customer_details = $customer_details_data->whereBetween('orders.created_at', [$start_date, $end_date])
                ->whereIn('customer_infos.user_id', $orders_customers)
                ->where('users.organisation_id', $this->organisation_id)
                ->groupBy($gBy)
                ->get();

            $listing_query = DB::table('salesman_infos')->select(DB::raw('DISTINCT DATE(orders.created_at) as date,
                    customerInfo.firstname AS customer,
                    customer_infos.customer_code AS customerCode,
                    customer_categories.customer_category_name AS category,
                    salesman.firstname AS merchandiser,
                    salesmanSupervisor.firstname AS supervisor,
                    channels.name AS channel,
                    regions.region_name AS region,
                    orders.order_number as orderNo,
                    orders.grand_total as orderValue'))
                ->join('users as salesman', 'salesman.id', '=', 'salesman_infos.user_id')
                ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesman_infos.salesman_supervisor')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
                ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id');
            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $listing_query->whereIn('salesman_infos.salesman_supervisor', $request->supervisor);
            }
            $listing = $listing_query->whereBetween('orders.created_at', [$start_date, $end_date])
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy('orders.created_at')
                ->groupBy('merchandiser')
                ->get();
        }

        $activeOutlets = new \stdClass();
        $activeOutlets->title = "Active Outlets";
        $activeOutlets->text = "Where atleast one order was made from a visit this month";
        $activeOutlets->percentage = $percentage;
        $activeOutlets->trends = $trends_data;
        $activeOutlets->comparison = $comparison;
        $activeOutlets->contribution = $comparison;
        $activeOutlets->details = $customer_details;
        $activeOutlets->listing = $listing;

        return $activeOutlets;
    }

    private function routeCompliance($request, $start_date, $end_date)
    {

        $salesman_ids   = array();
        $isFilter       = false;
        $filter_type    = "salesman";
        if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $isFilter       = true;
            $filters        = $request->supervisor;
            $filter_type    = "supervisor";
            $supervisor = $request->supervisor;

            $salesmanInfos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                ->whereIn('salesman_supervisor', $supervisor)
                ->where('status', 1)
                // ->groupBy('salesman_supervisor')
                ->get();

            $salesman_ids = $salesmanInfos->pluck('user_id')->toArray();
        } else if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
            $isFilter       = true;
            $salesman_ids   = $request->salesman_ids;
        } else if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $getSalesman = array();
            $isFilter       = true;
            foreach ($request->nsm as $nsm) {
                $getSalesman[] = getSalesman(false, $nsm);
            }
            $salesman_ids = Arr::collapse($getSalesman);
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $getSalesman = array();
            $isFilter       = true;
            foreach ($request->asm as $asm) {
                $getSalesman[] = getSalesman(false, $asm);
            }
            $salesman_ids = Arr::collapse($getSalesman);
        } else {
            $isFilter       = true;
            $salesman_ids = getSalesman();
        }

        $customer_visit_query = CustomerVisit::select([DB::raw("SUM(CASE WHEN journey_plan_id > 0 THEN 1 ELSE 0 END) as total_journey"), DB::raw("SUM(CASE WHEN is_sequnece = '1' THEN 1 ELSE 0 END) as planed_journey"), DB::raw("SUM(CASE WHEN is_sequnece = '0' THEN 1 ELSE 0 END) as un_planed_journey"), 'id', 'customer_id', 'journey_plan_id', 'salesman_id', 'latitude', 'longitude', 'start_time', 'end_time', 'is_sequnece', 'date', 'created_at'])
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'customer.customerInfo'
            )
            ->groupBy('salesman_id', 'customer_visits.date', 'customer_id');

        if (!empty($salesman_ids)) {
            $customer_visit_query->whereIn('salesman_id', $salesman_ids);
        }

        if (!$request->start_date && !$request->end_date) {
            $start_date = Carbon::now()->subDay(7)->format('Y-m-d');
            $end_date = Carbon::now()->format('Y-m-d');
        } else {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');
        }

        if ($end_date == $start_date) {
            $customer_visit_query->whereDate('date', $start_date)->orderBy('date', 'desc')->get();
        } else if ($end_date) {
            // $start_date = Carbon::parse($request->start_date)->subDay(1)->format('Y-m-d');
            $customer_visit_query->whereBetween('date', [$start_date, $end_date])->orderBy('date', 'desc')->get();
        } else {
            $customer_visit_query->whereDate('date', $start_date)->orderBy('date', 'desc')->get();
        }

        $customer_visit     = array();
        if (!empty($salesman_ids) || $isFilter == false) {
            $customer_visit     = $customer_visit_query->orderBy('date', 'desc')->get();
        }

        $visit_report       = array();
        if (count($customer_visit)) {
            foreach ($customer_visit as $key => $visit) {
                $jp = 0;
                // customer visit salesman id
                $salesman_id    = $visit->salesman_id;
                $journey_plans  = JourneyPlan::select([DB::raw('group_concat(id) as plan_ids')])
                    ->where('is_merchandiser', 1)
                    ->where('id', $visit->journey_plan_id)
                    ->where('merchandiser_id', $salesman_id)
                    ->get();

                foreach ($journey_plans as $j_plan) {
                    if (isset($j_plan->plan_ids) && ($j_plan->plan_ids != '')) {
                        $plan_id = explode(',', $j_plan->plan_ids);
                        if (!empty($plan_id)) {
                            $day = date('l', strtotime($visit->date));
                            //                            $week = (int)date('W', strtotime($visit->date));
                            $firstOfMonth   = date("Y-m-01", strtotime($visit->date));
                            $week           =  intval(date("W", strtotime($visit->date))) - intval(date("W", strtotime($firstOfMonth))) + 2;

                            $week = weekOfMonth(strtotime($visit->date));

                            $journey_plan_week = JourneyPlanWeek::select([DB::raw('group_concat(id) as week_ids')])
                                ->whereIn('journey_plan_id', $plan_id)
                                ->where('week_number', "week" . $week)
                                ->first();

                            if (!empty($journey_plan_week)) {
                                $week_ids = explode(',', $journey_plan_week['week_ids']);

                                $journey_plan_days = JourneyPlanDay::select('id', 'journey_plan_id')
                                    ->whereIn('journey_plan_id', $plan_id)
                                    ->whereIn('journey_plan_week_id', $week_ids)
                                    ->where('day_name', $day)
                                    ->get();
                                foreach ($journey_plan_days as $jp_day) {
                                    $jp += JourneyPlanCustomer::where('journey_plan_id', $jp_day->journey_plan_id)
                                        ->where('journey_plan_day_id', $jp_day->id)
                                        ->count();
                                }
                            }
                        }
                    }
                }

                $strike_calls           = 0;
                $strike_calls_percent   = 0;
                $total_customers        = 0;
                $total_visit_customers  = 0;
                if ($salesman_id > 0) {
                    // $m_customers = CustomerInfo::select([DB::raw('COUNT(DISTINCT user_id) as customers')])->where('merchandiser_id', $salesman_id)->first();
                    $m_customers = CustomerMerchandiser::select([DB::raw('COUNT(DISTINCT customer_id) as customers')])
                        ->where('customer_merchandisers.merchandiser_id', $salesman_id)
                        ->first();

                    if (isset($m_customers->customers) && $m_customers->customers > 0) {
                        $total_customers = $m_customers->customers;
                    }
                }

                if (!isset($visit_report[$visit->date][$salesman_id])) {
                    $visit_report[$visit->date][$salesman_id]   = new \stdClass();
                }

                $visit_report[$visit->date][$salesman_id]->id           = $visit->id;
                $visit_report[$visit->date][$salesman_id]->date         = $visit->date;
                $visit_report[$visit->date][$salesman_id]->journeyPlan  = $jp;

                if (!isset($visit_report[$visit->date][$salesman_id]->totalJourney)) {
                    $visit_report[$visit->date][$salesman_id]->totalJourney = 0;
                }

                $visit_report[$visit->date][$salesman_id]->totalJourney           += 1;
                //$visit_report[$visit->date][$salesman_id]->planedJourney          = $jp;
                if (!isset($visit_report[$visit->date][$salesman_id]->planedJourney)) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney = 0;
                }
                if ($visit->is_sequnece == 1) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney += 1;
                }

                // change by hardik
                if ($jp < $visit_report[$visit->date][$salesman_id]->planedJourney) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney = $jp;
                }

                $visit_report[$visit->date][$salesman_id]->journeyPlanPercent     = ($visit_report[$visit->date][$salesman_id]->planedJourney > 0 && $jp > 0) ? (round(($visit_report[$visit->date][$salesman_id]->planedJourney / $jp), 2) * 100) . '%' : 0;

                if (!isset($visit_report[$visit->date][$salesman_id]->unPlanedJourney)) {
                    $visit_report[$visit->date][$salesman_id]->unPlanedJourney = 0;
                }

                if ($visit->is_sequnece == 0) {
                    $visit_report[$visit->date][$salesman_id]->unPlanedJourney += 1;
                }

                // Change my hardik
                $visit_report[$visit->date][$salesman_id]->unPlanedJourney = $visit_report[$visit->date][$salesman_id]->totalJourney - $visit_report[$visit->date][$salesman_id]->planedJourney;

                $visit_report[$visit->date][$salesman_id]->unPlanedJourneyPercent = ($visit_report[$visit->date][$salesman_id]->totalJourney > 0) ? (round(($visit_report[$visit->date][$salesman_id]->unPlanedJourney / $visit_report[$visit->date][$salesman_id]->totalJourney), 2) * 100) . '%' : 0;
                $visit_report[$visit->date][$salesman_id]->totalCustomers         = $total_customers;
                $visit_report[$visit->date][$salesman_id]->strike_calls           = "";
                $visit_report[$visit->date][$salesman_id]->strike_calls_percent   = "";
                $visit_report[$visit->date][$salesman_id]->merchandiserCode       = (is_object($visit->salesman->salesmanInfo)) ? $visit->salesman->salesmanInfo->salesman_code : "";
                $visit_report[$visit->date][$salesman_id]->merchandiserName       = $visit->salesman->getName();
                $visit_report[$visit->date][$salesman_id]->merchandiserFirstName  = $visit->salesman->firstname;
                $visit_report[$visit->date][$salesman_id]->salesmanSupervisorID     = isset($visit->salesman->salesmanInfo->salesman_supervisor) ?  $visit->salesman->salesmanInfo->salesman_supervisor : "";
                $visit_report[$visit->date][$salesman_id]->salesmanSupervisor     = isset($visit->salesman->salesmanInfo->supervisorUser) ?  $visit->salesman->salesmanInfo->supervisorUser->getName() : "";
            }
        }

        $final_report       = array();
        $date_wise_report   = array();
        // $startDate          = date('Y-m-d', strtotime($start_date));
        // $endDate            = date('Y-m-d', strtotime($end_date));
        $startDate          = Carbon::parse($request->start_date)->format('Y-m-d');
        $endDate            = Carbon::parse($request->end_date)->format('Y-m-d');

        // while ($startDate <= $endDate) {
        //     $report_date = $startDate;
        //     pre($visit_report[$report_date]);
        //     if (isset($visit_report[$report_date])) {
        //         $date_wise_report[$report_date] = $visit_report[$report_date];
        //     }
        // }

        $count                      = 0;
        $trends_data                = array();
        $comparison_data            = array();
        $contribution_data          = array();
        $trend_array                = array();
        $merchandiser_array         = array();
        $merchandiser_name          = array();
        $salesman_customers         = array();
        $salesman_details           = array();
        $total_journey              = 0;

        foreach ($visit_report as $visit_date => $report) {
            foreach ($report as $key => $row) {
                if (isset($row->totalCustomers)) {
                    $strike_calls           = $row->totalCustomers - $row->totalJourney;
                    $strike_calls_percent   = 0;
                    if ($row->totalCustomers > 0) {
                        $strike_calls_percent   = ($strike_calls > 0) ? round($row->totalJourney / $row->totalCustomers, 2) * 100 : 0;
                    }
                    $report[$key]->strike_calls          = $strike_calls;
                    $report[$key]->strike_calls_percent  = ROUND($strike_calls_percent, 2) . "%";
                }
                $final_report[$count]       = $row;
                $final_report[$count]->date = $visit_date;
                if (!isset($trend_array[$visit_date])) {
                    $trend_array[$visit_date] = 0;
                }
                $trend_array[$visit_date] += $row->totalJourney;
                if ($filter_type == "salesman") {
                    if (!isset($merchandiser_array[$row->merchandiserCode]['planed_journey'])) {
                        $merchandiser_array[$row->merchandiserCode]['planed_journey'] = 0;
                    }
                    if (!isset($merchandiser_array[$row->merchandiserCode]['journey_plan'])) {
                        $merchandiser_array[$row->merchandiserCode]['journey_plan'] = 0;
                    }
                    $merchandiser_array[$row->merchandiserCode]['planed_journey']   += $row->planedJourney;
                    $merchandiser_array[$row->merchandiserCode]['journey_plan']     += $row->journeyPlan;
                    $merchandiser_name[$row->merchandiserCode]                       = $row->merchandiserFirstName;
                } else if ($filter_type == "supervisor") {
                    if (!isset($merchandiser_array[$row->salesmanSupervisorID]['planed_journey'])) {
                        $merchandiser_array[$row->salesmanSupervisorID]['planed_journey'] = 0;
                    }
                    if (!isset($merchandiser_array[$row->salesmanSupervisorID]['journey_plan'])) {
                        $merchandiser_array[$row->salesmanSupervisorID]['journey_plan'] = 0;
                    }
                    $merchandiser_array[$row->salesmanSupervisorID]['planed_journey']   += $row->planedJourney;
                    $merchandiser_array[$row->salesmanSupervisorID]['journey_plan']     += $row->journeyPlan;
                    $merchandiser_name[$row->salesmanSupervisorID]                       = $row->salesmanSupervisorID;
                }
                $total_journey += $row->totalJourney;

                $salesman_customers[$row->merchandiserCode]  = $row->totalCustomers;
                $count++;
            }
        }

        foreach ($trend_array as $key => $value) {
            $trends_data[] = array(
                'date'  => $key,
                'value' => $value,
            );
        }

        $routeCompliancePercentageAvg = 0;
        if (!empty($merchandiser_array)) {
            $total_planed_visit     = 0;
            $total_percent          = 0;
            $total_salesman         = count($merchandiser_array);
            foreach ($merchandiser_array as $key => $value) {
                $planed_visit           = isset($value['planed_journey']) ? $value['planed_journey'] : ''; // planed visit
                $journey_plan           = isset($value['journey_plan']) ? $value['journey_plan'] : ''; //journey_plan
                $total_planed_visit     = $total_planed_visit + $planed_visit;
                $execution              = 0;
                if ($journey_plan > 0) {
                    $execution  = round($planed_visit / $journey_plan * 100, 2);
                }
                $total_percent  = $total_percent + $execution;
                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $user = User::find($merchandiser_name[$key]);
                    $name = model($user, 'firstname');
                } else {
                    $name = isset($merchandiser_name[$key]) ? $merchandiser_name[$key] : '';
                }

                $salesman_details[] = array(
                    "RES"               => $name, // salesman name
                    "VISITS"            => $planed_visit, // planed visit
                    "TOTAL_OUTLETS"     => $journey_plan, // journey plan
                    "EXECUTION"         => $execution . "%" //VISITS/ TOTAL OUTLETS *100
                );
                $comparison_data[] = array(
                    'name'  => $name,
                    'steps' => $planed_visit,
                );
            }

            foreach ($merchandiser_array as $key => $value) {
                $planed_visit   = isset($value['planed_journey']) ? $value['planed_journey'] : 0; // planed visit
                $steps          = ($total_planed_visit > 0) ? round($planed_visit / $total_planed_visit * 100, 2) : 0;

                if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                    $user = User::find($merchandiser_name[$key]);
                    $name = model($user, 'firstname');
                } else {
                    $name = isset($merchandiser_name[$key]) ? $merchandiser_name[$key] : '';
                }

                $contribution_data[] = array(
                    'name'  => $name,
                    'steps' => $steps,
                );
            }

            if ($total_salesman > 0) {
                $routeCompliancePercentageAvg = $total_percent / $total_salesman;
            }
        }

        // if (
        //     (is_array($request->nsm) && sizeof($request->nsm) >= 1) &&
        //     (is_array($request->asm) && sizeof($request->asm) >= 1)
        // ) {
        //     if (count($comparison_data)) {
        //         $steps = $comparison_data->pluck('steps')->toArray();
        //         $comparison_data = array(
        //             ''
        //         );
        //     }
        // }

        $routeCompliancePercentageAvg = "0%";
        if (count($salesman_details)) {
            $VISITS = collect($salesman_details)->pluck('VISITS')->toArray();
            $TOTAL_OUTLETS = collect($salesman_details)->pluck('TOTAL_OUTLETS')->toArray();

            $VISITS_sum = array_sum($VISITS);
            $TOTAL_OUTLETS_SUM = array_sum($TOTAL_OUTLETS);
            if ($VISITS_sum != 0 && $TOTAL_OUTLETS_SUM != 0) {
                $routeCompliancePercentageAvg = ($VISITS_sum / $TOTAL_OUTLETS_SUM) * 100;
            }
        }

        $routeCompliance                = new \stdClass();
        $routeCompliance->title         = "Route Compliance";
        $routeCompliance->text          = "Compliance to route plan";
        // $routeCompliance->percentage    = round($routeCompliancePercentageAvg, 2) . "%";
        $routeCompliance->percentage    = round($routeCompliancePercentageAvg, 2) . "%";
        $routeCompliance->trends        = $trends_data;
        $routeCompliance->comparison    = $comparison_data;
        $routeCompliance->contribution  = $contribution_data;
        $routeCompliance->details       = $salesman_details;
        $routeCompliance->listing       = $final_report;
        return $routeCompliance;
    }

    private function outofstock($request, $start_date, $end_date)
    {
        $filter1 = "";
        $filter2 = "";
        $filter3 = "";

        $outofstock = new \stdClass();
        $outofstock->title = "Out of stock";
        $outofstock->percentage = "0%";
        $outofstock->trends = [];
        $outofstock->comparison =  [];
        $outofstock->contribution = [];
        $outofstock->details = [];
        return $outofstock;

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            if (isset($request->brand_id) && $request->brand_id) {
                $brand_id = $request->brand_id;
                $filter1 = " AND item_id IN (SELECT id FROM items WHERE `brand_id` IN ($brand_id))";
            }
            if (isset($request->category_id) && $request->category_id) {
                $category_id = implode(",", $request->category_id);

                $filter2 = "AND item_id IN(SELECT id FROM items WHERE `item_major_category_id` IN ($category_id))";
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);
                $filter3 = "AND item_id IN (SELECT id FROM items WHERE id IN ($item_ids))";
            }
            foreach ($request->nsm as $nsm) {
                $customer_ids = getSalesman(true, $nsm);
                $salesman_ids = getSalesman(false, $nsm); {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }



                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $trends_data = DB::table('distributions')
                    ->select(
                        DB::raw('DATE(distribution_stocks.created_at) as date'),
                        DB::raw('count(DISTINCT distribution_stocks.id) as value')
                    )
                    ->join('distribution_stocks', 'distribution_stocks.distribution_id', '=', 'distributions.id')
                    ->join('distribution_model_stock_details', 'distributions.id', '=', 'distribution_stocks.distribution_id')
                    ->join('items', 'distribution_model_stock_details.item_id', '=', 'items.id')
                    ->join('users', 'users.id', '=', 'distribution_stocks.salesman_id')
                    ->where('distributions.organisation_id', $this->organisation_id)
                    ->where('distribution_stocks.is_out_of_stock', 1)
                    ->whereIn('distribution_stocks.customer_id', $customer_ids)
                    //->whereBetween('distributions.end_date', [$start_date, $end_date])
                    ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
                    ->whereNull('distributions.deleted_at')
                    ->whereNull('distribution_stocks.deleted_at')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                //-------------
                $outofstockarr = array();
                $outofstockper = array();
                $filter = "";
                $totalper = 0;
                $totalsal = count($salesman_ids);
                if (!empty($salesman_ids)) {

                    foreach ($salesman_ids as $key => $salesmanid) {

                        $organisation_id = request()->user()->organisation_id;
                        $outofstockprice = DB::select("SELECT
                        (SELECT
                            users.firstname
                        FROM
                            users
                        WHERE users.id = '$salesmanid') AS RES,
                        tab2.TOTAL_OUTLETS AS TOTAL_OUTLETS,
                        (SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                        `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND created_at between '$start_date' AND '$end_date' AND  `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS VISIT,round((((SELECT COUNT(`item_id`) AS VISIT  FROM `distribution_stocks` WHERE
                        `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.TOTAL_OUTLETS)*100),2) as EXECUTION
                        FROM
                        (SELECT COUNT(item_id)*$date_diff AS TOTAL_OUTLETS FROM `distribution_model_stock_details` WHERE   distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  AND deleted_at IS NULL AND
                        $filter
                        `distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `user_id` IN (select customer_id from customer_merchandisers where merchandiser_id IN ('$salesmanid')))) $filter1 $filter2 $filter3) tab2
                        ");
                        $outofstockarr[] = $outofstockprice[0];
                        $outofstockper[] = $outofstockprice[0]->EXECUTION;
                    }
                }
            }
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            //--------------
            if (isset($request->brand_id) && $request->brand_id) {
                $brand_id = $request->brand_id;
                $filter1 = " AND item_id IN (SELECT id FROM items WHERE `brand_id` IN ($brand_id))";
            }
            if (isset($request->category_id) && $request->category_id) {
                $category_id = implode(",", $request->category_id);

                $filter2 = "AND item_id IN(SELECT id FROM items WHERE `item_major_category_id` IN ($category_id))";
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);
                $filter3 = "AND item_id IN (SELECT id FROM items WHERE id IN ($item_ids))";
            }
            foreach ($request->asm as $asm) {
                $customer_ids = getSalesman(true, $asm);
                $salesman_ids = getSalesman(false, $asm); {
                    $salesman_infos = SalesmanInfo::select('id', 'user_id')
                        ->where('status', 1)
                        ->get();
                }



                $diff = date_diff(date_create($start_date), date_create($end_date));
                $date_diff = $diff->format("%a");
                $trends_data = DB::table('distributions')
                    ->select(
                        DB::raw('DATE(distribution_stocks.created_at) as date'),
                        DB::raw('count(DISTINCT distribution_stocks.id) as value')
                    )
                    ->join('distribution_stocks', 'distribution_stocks.distribution_id', '=', 'distributions.id')
                    ->join('distribution_model_stock_details', 'distributions.id', '=', 'distribution_stocks.distribution_id')
                    ->join('items', 'distribution_model_stock_details.item_id', '=', 'items.id')
                    ->join('users', 'users.id', '=', 'distribution_stocks.salesman_id')
                    ->where('distributions.organisation_id', $this->organisation_id)
                    ->where('distribution_stocks.is_out_of_stock', 1)
                    ->whereIn('distribution_stocks.customer_id', $customer_ids)
                    //->whereBetween('distributions.end_date', [$start_date, $end_date])
                    ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
                    ->whereNull('distributions.deleted_at')
                    ->whereNull('distribution_stocks.deleted_at')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                //-------------
                $outofstockarr = array();
                $outofstockper = array();
                $filter = "";
                $totalper = 0;
                $totalsal = count($salesman_ids);
                if (!empty($salesman_ids)) {

                    foreach ($salesman_ids as $key => $salesmanid) {

                        $organisation_id = request()->user()->organisation_id;
                        $outofstockprice = DB::select("SELECT
                        (SELECT
                            users.firstname
                        FROM
                            users
                        WHERE users.id = '$salesmanid') AS RES,
                        tab2.TOTAL_OUTLETS AS TOTAL_OUTLETS,
                        (SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                        `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND created_at between '$start_date' AND '$end_date' AND  `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS VISIT,round((((SELECT COUNT(`item_id`) AS VISIT  FROM `distribution_stocks` WHERE
                        `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.TOTAL_OUTLETS)*100),2) as EXECUTION
                        FROM
                        (SELECT COUNT(item_id)*$date_diff AS TOTAL_OUTLETS FROM `distribution_model_stock_details` WHERE   distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  AND deleted_at IS NULL AND
                        $filter
                        `distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `user_id` IN (select customer_id from customer_merchandisers where merchandiser_id IN ('$salesmanid')))) $filter1 $filter2 $filter3) tab2
                        ");
                        $outofstockarr[] = $outofstockprice[0];
                        $outofstockper[] = $outofstockprice[0]->EXECUTION;
                    }
                }
            }
            //---------------

        } else if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {

            if (isset($request->brand_id) && $request->brand_id) {
                $brand_id = $request->brand_id;
                $filter1 = " AND item_id IN (SELECT id FROM items WHERE `brand_id` IN ($brand_id))";
            }
            if (isset($request->category_id) && $request->category_id) {
                $category_id = implode(",", $request->category_id);
                $filter2 = "AND item_id IN (SELECT id FROM items WHERE `item_major_category_id` IN ($category_id))";
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);

                $filter3 = "AND item_id IN (SELECT id FROM items WHERE id IN ($item_ids))";
            }
            $channel = $request->channel_ids;
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->where('channel_id', $channel)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $diff = date_diff(date_create($start_date), date_create($end_date));

            $date_diff = $diff->format("%a");
            $totalper = 0;
            $totalsal = count($request->channel_ids);
            $outofstockarr = array();
            $outofstockper = array();
            $filter = "";
            if (!empty($request->channel_ids)) {

                foreach ($request->channel_ids as $key => $channelid) {

                    $organisation_id = request()->user()->organisation_id;
                    $outofstockprice = DB::select("SELECT
                    (SELECT
                    channels.name
                    FROM
                        channels
                    WHERE channels.id = '$channelid') AS RES,
                    tab2.TOTAL_OUTLETS AS TOTAL_OUTLETS,
                    (SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND created_at between '$start_date' AND '$end_date' AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS VISIT,round((((SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.TOTAL_OUTLETS)*100),2) as EXECUTION
                    FROM
                    (SELECT COUNT(item_id)*$date_diff AS TOTAL_OUTLETS FROM `distribution_model_stock_details` where distribution_id IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL) AND deleted_at IS NULL AND
                    $filter
                    `distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid'))) $filter1 $filter2 $filter3) tab2
                    ");
                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->EXECUTION;
                }
            }

            $totalper = array_sum($outofstockper);
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();



            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data_que = DB::table('distributions')
                ->select(
                    DB::raw('DATE(distribution_stocks.created_at) as date'),
                    DB::raw('count(DISTINCT distribution_stocks.id) as value')
                )
                ->join('distribution_stocks', 'distribution_stocks.distribution_id', '=', 'distributions.id')
                ->join('distribution_model_stock_details', 'distributions.id', '=', 'distribution_stocks.distribution_id')
                ->join('items', 'distribution_model_stock_details.item_id', '=', 'items.id')
                ->join('users', 'users.id', '=', 'distribution_stocks.salesman_id')
                ->where('distributions.organisation_id', $this->organisation_id)
                ->where('distribution_stocks.is_out_of_stock', 1)
                /* if (isset($request->brand_id) && $request->brand_id)
					 {
					$trends_data_que->where('items.brand_id', $request->brand_id);
					}
            if (isset($request->category_id) && $request->category_id) 
				{
					$trends_data_que->where('items.item_major_category_id', $request->category_id);
				}
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) 
			{
               
               $trends_data_que->whereIn('distribution_model_stock_details.item_id', $request->item_ids);
            }*/
                ->whereIn('distribution_stocks.customer_id', $customer_ids)
                // ->where('distributions.start_date', '<=', $start_date)
                // ->where('distributions.end_date', '>=', $end_date)
                ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
                ->whereNull('distributions.deleted_at')
                ->whereNull('distribution_stocks.deleted_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $trends_data = $trends_data_que;
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {

            if (isset($request->brand_id) && $request->brand_id) {
                $brand_id = $request->brand_id;
                //   $trends_data->where('items.brand_id', $request->brand_id);
                $filter1 = " AND item_id IN (SELECT id FROM items WHERE `brand_id` IN ($brand_id))";
            }
            if (isset($request->category_id) && $request->category_id) {
                $category_id = implode(",", $request->category_id);
                //   $trends_data->where('items.item_major_category_id', $request->category_id);
                $filter2 = "AND item_id IN
                (SELECT id FROM items WHERE `item_major_category_id` IN ($category_id))";
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);
                //$trends_data->whereIn('distribution_model_stock_details.item_id', $request->item_ids);
                $filter3 = "AND item_id IN (SELECT id FROM items WHERE id IN ($item_ids))";
            }
            $region = $request->region_ids;
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->where('region_id', $region)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            //-----------
            //-------------
            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $totalper = 0;
            $totalsal = count($request->region_ids);
            $outofstockarr = array();
            $outofstockper = array();
            $filter = "";
            if (!empty($request->region_ids)) {

                foreach ($request->region_ids as $key => $regionid) {

                    $organisation_id = request()->user()->organisation_id;
                    $outofstockprice = DB::select("SELECT
                    (SELECT
                        regions.region_name
                    FROM
                        regions
                    WHERE regions.id = '$regionid') AS RES,
                    tab2.TOTAL_OUTLETS AS TOTAL_OUTLETS,
                    (SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND created_at between '$start_date' AND '$end_date' AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS VISIT,
                    round((((SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date'  and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.TOTAL_OUTLETS)*100),2) as EXECUTION
                    FROM
                    (SELECT COUNT(item_id)*$date_diff AS TOTAL_OUTLETS FROM `distribution_model_stock_details` WHERE distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL) AND deleted_at IS NULL AND
                    $filter
                    `distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid'))) $filter1 $filter2 $filter3) tab2
                    ");

                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->EXECUTION;
                }
            }
            $totalper = array_sum($outofstockper);
            $trends_data = DB::table('distributions')
                ->select(
                    DB::raw('DATE(distribution_stocks.created_at) as date'),
                    DB::raw('count(DISTINCT distribution_stocks.id) as value')
                )
                ->join('distribution_stocks', 'distribution_stocks.distribution_id', '=', 'distributions.id')
                ->join('distribution_model_stock_details', 'distributions.id', '=', 'distribution_stocks.distribution_id')
                ->join('items', 'distribution_model_stock_details.item_id', '=', 'items.id')
                ->join('users', 'users.id', '=', 'distribution_stocks.salesman_id')
                ->where('distributions.organisation_id', $this->organisation_id)
                ->where('distribution_stocks.is_out_of_stock', 1)
                ->whereIn('distribution_stocks.customer_id', $customer_ids)
                ->where('distributions.start_date', '<=', $start_date)
                ->where('distributions.end_date', '>=', $end_date)
                ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
                ->whereNull('distributions.deleted_at')
                ->whereNull('distribution_stocks.deleted_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            //-----------
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {

                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->where('status', 1)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;

                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->where('status', 1)
                    ->get();
            }

            if (isset($request->brand_id) && $request->brand_id) {
                $brand_id = $request->brand_id;

                $filter1 = " AND item_id IN (SELECT id FROM items WHERE `brand_id` IN ($brand_id))";
            }
            if (isset($request->category_id) && $request->category_id) {
                $category_id = implode(",", $request->category_id);

                $filter2 = "AND item_id IN(SELECT id FROM items WHERE `item_major_category_id` IN ($category_id))";
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);
                //$trends_data->whereIn('distribution_model_stock_details.item_id', $request->item_ids);
                $filter3 = "AND item_id IN (SELECT id FROM items WHERE id IN ($item_ids))";
            }

            $salesman_ids = array();
            $user = request()->user();
            $salesman_ids = getSalesman(false, $user->id);
            $customer_ids = array();
            if (count($salesman_ids)) {
                $customer_merchadiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_ids)->get();
                if (count($customer_merchadiser)) {
                    $customer_ids = $customer_merchadiser->pluck('customer_id')->toArray();
                }
            }

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            $trends_data = DB::table('distributions')
                ->select(
                    DB::raw('DATE(distribution_stocks.created_at) as date'),
                    DB::raw('count(DISTINCT distribution_stocks.id) as value')
                )
                ->join('distribution_stocks', 'distribution_stocks.distribution_id', '=', 'distributions.id')
                ->join('distribution_model_stock_details', 'distributions.id', '=', 'distribution_stocks.distribution_id')
                ->join('items', 'distribution_model_stock_details.item_id', '=', 'items.id')
                ->join('users', 'users.id', '=', 'distribution_stocks.salesman_id')
                ->where('distributions.organisation_id', $this->organisation_id)
                ->where('distribution_stocks.is_out_of_stock', 1)
                ->whereIn('distribution_stocks.customer_id', $customer_ids)
                //->whereBetween('distributions.end_date', [$start_date, $end_date])
                ->whereBetween('distribution_stocks.created_at', [$start_date, $end_date])
                ->whereNull('distributions.deleted_at')
                ->whereNull('distribution_stocks.deleted_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            //-------------
            $outofstockarr = array();
            $outofstockper = array();
            $filter = "";
            $totalper = 0;
            $totalsal = count($salesman_ids);
            if (!empty($salesman_ids)) {

                foreach ($salesman_ids as $key => $salesmanid) {

                    $organisation_id = request()->user()->organisation_id;
                    $outofstockprice = DB::select("SELECT
                    (SELECT
                        users.firstname
                    FROM
                        users
                    WHERE users.id = '$salesmanid') AS RES,
                    tab2.TOTAL_OUTLETS AS TOTAL_OUTLETS,
                    (SELECT COUNT(`item_id`) AS VISIT FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND created_at between '$start_date' AND '$end_date' AND  `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS VISIT,round((((SELECT COUNT(`item_id`) AS VISIT  FROM `distribution_stocks` WHERE
                    `is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.TOTAL_OUTLETS)*100),2) as EXECUTION
                    FROM
                    (SELECT COUNT(item_id)*$date_diff AS TOTAL_OUTLETS FROM `distribution_model_stock_details` WHERE   distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  AND deleted_at IS NULL AND
                    $filter
                    `distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `user_id` IN (select customer_id from customer_merchandisers where merchandiser_id IN ('$salesmanid')))) $filter1 $filter2 $filter3) tab2
                    ");
                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->EXECUTION;
                }
            }
        }
        $totalper = array_sum($outofstockper);

        $percentage = 0;
        if ($totalper != 0 && $totalsal != 0) {
            $percentage = round($totalper / $totalsal, 2);
        }

        $outofstock = new \stdClass();
        $outofstock->title = "Out of stock";
        $outofstock->percentage = $percentage;
        $outofstock->trends = $trends_data;
        $outofstock->comparison =  $this->comparison(collect($outofstockarr), 'VISIT');
        $outofstock->contribution = $this->comparison(collect($outofstockarr), 'VISIT');
        $outofstock->details = $outofstockarr;
        return $outofstock;
    }

    private function sos($request, $start_date, $end_date)
    {
        $start_date = Carbon::parse($start_date)->addDay()->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');

        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')->whereIn('channel_id', $request->channel_ids)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DATE(s_o_s.created_at) as date'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as value'),
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id');
            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('s_o_s_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('s_o_s_our_brands.item_major_category_id', $request->category);
            }

            $trends_data = $trends_data_query->whereIn('s_o_s.customer_id', $customer_ids)
                ->whereBetween('s_o_s.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('s_o_s.created_at')
                ->get();

            $customer_details_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DISTINCT channels.name as RES'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves))  VISITS'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as TOTAL_OUTLETS'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as EXECUTION')
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 's_o_s.salesman_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('users', 'users.id', '=', 's_o_s.salesman_id');
            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('s_o_s_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('s_o_s_our_brands.item_major_category_id', $request->category);
            }
            $customer_details = $customer_details_query->where('users.organisation_id', $this->organisation_id)
                ->whereIn('s_o_s.customer_id', $customer_ids)
                ->whereBetween('s_o_s.added_on', [$start_date, $end_date])
                ->groupBy('channels.name')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('VISITS')->toArray();
                $planned = $customer_details->pluck('TOTAL_OUTLETS')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "VISITS");
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $trends_data_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DATE(s_o_s.created_at) as date'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as value'),
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id');
            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }

            $trends_data = $trends_data_query->whereIn('s_o_s.customer_id', $customer_ids)
                ->whereBetween('s_o_s.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('s_o_s.created_at')
                ->get();

            $customer_details_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DISTINCT regions.region_name as RES'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as VISITS'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as TOTAL_OUTLETS'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as EXECUTION')
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 's_o_s.salesman_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('users', 'users.id', '=', 's_o_s.salesman_id');
            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('s_o_s_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('s_o_s_our_brands.item_major_category_id', $request->category);
            }
            $customer_details = $customer_details_query->where('users.organisation_id', $this->organisation_id)
                ->whereIn('s_o_s.customer_id', $customer_ids)
                ->whereBetween('s_o_s.added_on', [$start_date, $end_date])
                ->groupBy('regions.region_name')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('VISIT')->toArray();
                $planned = $customer_details->pluck('TOTAL_OUTLETS')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "VISITS");
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                // $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                //     ->where(function ($query) use ($supervisor) {
                //         if (!empty($supervisor)) {
                //             foreach ($supervisor as $key => $filter_val) {
                //                 if ($key == 0) {
                //                     $query->where('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 } else {
                //                     $query->orWhere('salesman_supervisor', 'like', '%' . $filter_val . '%');
                //                 }
                //             }
                //         }
                //     })
                //     ->groupBy('salesman_supervisor')
                //     ->get();
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            } else {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->get();
            }

            $salesman_ids = array();
            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            $trends_data_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DATE(s_o_s.created_at) as date'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as value'),
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id');
            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('s_o_s_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('s_o_s_our_brands.item_major_category_id', $request->category);
            }


            $trends_data = $trends_data_query->whereIn('s_o_s.salesman_id', $salesman_ids)
                ->whereBetween('s_o_s.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('s_o_s.created_at')
                ->get();

            $customer_details_query = DB::table('s_o_s')
                ->select(
                    DB::raw('DISTINCT users.firstname as RES'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as VISITS'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as TOTAL_OUTLETS'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as EXECUTION')
                )
                ->join('s_o_s_our_brands', 's_o_s_our_brands.sos_id', '=', 's_o_s.id')
                ->join('users', 'users.id', '=', 's_o_s.salesman_id');
            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('s_o_s_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('s_o_s_our_brands.item_major_category_id', $request->category);
            }
            $customer_details = $customer_details_query->where('users.organisation_id', $this->organisation_id)
                ->whereIn('s_o_s.salesman_id', $salesman_ids)
                ->whereBetween('s_o_s.added_on', [$start_date, $end_date])
                ->groupBy('s_o_s.salesman_id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('VISITS')->toArray();
                $planned = $customer_details->pluck('TOTAL_OUTLETS')->toArray();
                if (array_sum($actual) != 0 && array_sum($planned) != 0) {
                    $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
                }
            }

            $comparison = $this->comparison($customer_details, "VISITS");
        }

        $contribution = $this->comparisonSet($customer_details);

        $sos = new \stdClass();
        $sos->title = "SOS";
        $sos->percentage = $percentage;
        $sos->trends = $trends_data;
        $sos->comparison = $comparison;
        $sos->contribution = $contribution;
        $sos->details = $customer_details;
        return $sos;
    }

    private function comparison($customer_details, $variable)
    {
        $comparison = array();
        if (count($customer_details)) {

            $actual = $customer_details->pluck($variable)->toArray();
            $sum_actual = 0;
            if (count($actual)) {
                $sum_actual = array_sum($actual);
            }

            foreach ($customer_details as $key => $details) {
                if ($sum_actual != 0) {
                    if ($details->$variable != 0) {
                        $comparison[] = array(
                            'name' => $details->RES,
                            'steps' => $details->$variable,
                            // 'all_actual' => $sum_actual,
                            // 'percentage' => number_format(ROUND($details->$variable / $sum_actual * 100, 2), 2)
                        );
                    } else {
                        $comparison[] = array(
                            'name' => $details->RES,
                            'steps' => 0,
                            // 'all_actual' => $sum_actual,
                            // 'percentage' => 0
                        );
                    }
                }
            }
        }
        return $comparison;
    }

    private function comparisonSet($customer_details)
    {
        if (!count($customer_details)) {
            return;
        }

        $comparison = array();

        if (count($customer_details)) {
            foreach ($customer_details as $detail) {
                $data = array(
                    'name' => $detail->RES,
                    'steps' => $detail->VISITS
                );
                $comparison[] = $data;
            }
        }
        return $comparison;
    }
}
