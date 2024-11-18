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
use Carbon\CarbonPeriod;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class Dashboard4Controller extends Controller
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

    private function coverage($request, $start_date, $end_date)
    {
        $start_date         = Carbon::parse($start_date)->subDay()->format('Y-m-d');
        $percentage         = 0;
        $trends_data        = array();
        $comparison         = array();
        $comparison         = array();
        $customer_details   = array();
        $final_report       = array();
        $final_jp_id = '';
        $final_jp_days = '';
        $final_salesman_id = '';
        $final_salesman_id = '';

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $get_all_salesman = array();
            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);
                $get_all_salesman[] = $all_salesman;
            }

            $start_date = Carbon::parse($start_date)->addDay(2);
            $end_date = Carbon::parse($end_date);

            // $salesmanInfos = SalesmanInfo::whereIn('salesman_supervisor', $request->supervisor)->get();

            if (count($get_all_salesman)) {

                $salesman_user_ids = Arr::collapse($get_all_salesman);

                $s_id_string = implode(',', $salesman_user_ids);

                $s_day = $start_date->day;
                $e_day = $end_date->day;

                $s_month = $start_date->month;
                $e_month = $end_date->month;

                if ($s_month == $e_month) {
                    $months = $s_month;
                } else {
                    $months = $s_month . ',' . $e_month;
                    if ($e_day == "1") {
                        $previous = $end_date->subWeek();
                        $start_date = $previous;
                        $end_date = Carbon::parse($previous)->endOfMonth();

                        $s_day = $start_date->day;
                        $months = $start_date->month;
                        $e_day = $end_date->day;
                    } else {
                        if (in_array($s_day, [26, 27, 28, 29, 30, 31])) {
                            $start_date = Carbon::parse($end_date)->firstOfMonth();
                            $s_day = $start_date->day;
                            $months = $start_date->month;
                        }
                    }
                }

                $weeek_diff = DB::select("SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number");

                $week_count = count($weeek_diff);

                $customer_details = DB::select("SELECT CONCAT(users.firstname, ' ', users.lastname) as RES, round((SUM(`visit`) / SUM(`planned`) * 100), 2) as EXECUTION, round(SUM(`planned`)) as TOTAL_OUTLETS, round(SUM(`visit`)) as VISITS FROM `merchandiser_coverages` LEFT JOIN salesman_infos on salesman_infos.user_id = salesman_id LEFT JOIN users on users.id = salesman_infos.nsm_id WHERE `week_number` IN (SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number) AND month in ($months) and salesman_id in ($s_id_string) GROUP BY salesman_infos.nsm_id");

                $collect = collect($customer_details);

                $visits     = array_sum($collect->pluck('VISITS')->toArray());
                $total_out  = array_sum($collect->pluck('TOTAL_OUTLETS')->toArray());

                $percentage = 0;
                if ($visits > 0 && $total_out > 0) {
                    $percentage = round(($visits / $total_out) * 100, 2);
                }

                $jp_ids = JourneyPlan::select('id')->whereIn('merchandiser_id', $salesman_user_ids)->get();

                if (count($jp_ids)) {
                    $week_array = array();
                    for ($h = 1; $h <= $week_count; $h++) {
                        $week_array[] = "week" . $h;
                    }
                    $jp_week = JourneyPlanWeek::select('id')->whereIn('journey_plan_id', $jp_ids)
                        ->whereIn('week_number', $week_array)
                        ->get();
                    $jp_day = JourneyPlanDay::select('id')->whereIn('journey_plan_week_id', $jp_week)->get();

                    $final_jp_id = implode(',', $jp_ids->pluck('id')->toArray());
                    $final_jp_days = implode(',', $jp_day->pluck('id')->toArray());
                }
                $final_salesman_id = $s_id_string;
            }
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $get_all_salesman = array();
            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $get_all_salesman[] = $all_salesman;
            }

            $start_date = Carbon::parse($start_date)->addDay(2);
            $end_date = Carbon::parse($end_date);

            // $salesmanInfos = SalesmanInfo::whereIn('salesman_supervisor', $request->supervisor)->get();

            if (count($get_all_salesman)) {

                $salesman_user_ids = Arr::collapse($get_all_salesman);

                $s_id_string = implode(',', $salesman_user_ids);

                $s_day = $start_date->day;
                $e_day = $end_date->day;

                $s_month = $start_date->month;
                $e_month = $end_date->month;

                if ($s_month == $e_month) {
                    $months = $s_month;
                } else {
                    $months = $s_month . ',' . $e_month;
                    if ($e_day == "1") {
                        $previous = $end_date->subWeek();
                        $start_date = $previous;
                        $end_date = Carbon::parse($previous)->endOfMonth();

                        $s_day = $start_date->day;
                        $months = $start_date->month;
                        $e_day = $end_date->day;
                    } else {
                        if (in_array($s_day, [26, 27, 28, 29, 30, 31])) {
                            $start_date = Carbon::parse($end_date)->firstOfMonth();
                            $s_day = $start_date->day;
                            $months = $start_date->month;
                        }
                    }
                }

                $weeek_diff = DB::select("SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number");

                $week_count = count($weeek_diff);

                $customer_details = DB::select("SELECT CONCAT(users.firstname, ' ', users.lastname) as RES, round((SUM(`visit`) / SUM(`planned`) * 100), 2) as EXECUTION, round(SUM(`planned`)) as TOTAL_OUTLETS, round(SUM(`visit`)) as VISITS FROM `merchandiser_coverages` LEFT JOIN salesman_infos on salesman_infos.user_id = salesman_id LEFT JOIN users on users.id = salesman_infos.asm_id WHERE `week_number` IN (SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number) AND month in ($months) and salesman_id in ($s_id_string) GROUP BY salesman_infos.asm_id");

                $collect = collect($customer_details);

                $execution = array_sum($collect->pluck('EXECUTION')->toArray());

                $visits     = array_sum($collect->pluck('VISITS')->toArray());
                $total_out  = array_sum($collect->pluck('TOTAL_OUTLETS')->toArray());

                $percentage = 0;
                if ($visits > 0 && $total_out > 0) {
                    $percentage = round(($visits / $total_out) * 100, 2);
                }

                $jp_ids = JourneyPlan::select('id')->whereIn('merchandiser_id', $salesman_user_ids)->get();

                if (count($jp_ids)) {
                    $week_array = array();
                    for ($h = 1; $h <= $week_count; $h++) {
                        $week_array[] = "week" . $h;
                    }
                    $jp_week = JourneyPlanWeek::select('id')->whereIn('journey_plan_id', $jp_ids)
                        ->whereIn('week_number', $week_array)
                        ->get();
                    $jp_day = JourneyPlanDay::select('id')->whereIn('journey_plan_week_id', $jp_week)->get();

                    $final_jp_id = implode(',', $jp_ids->pluck('id')->toArray());
                    $final_jp_days = implode(',', $jp_day->pluck('id')->toArray());
                }
                $final_salesman_id = $s_id_string;
            }
        } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {

            $start_date = Carbon::parse($start_date)->addDay(2);
            $end_date = Carbon::parse($end_date);


            $salesmanInfos = SalesmanInfo::whereIn('salesman_supervisor', $request->supervisor)
                ->where('salesman_type_id', 2)
                ->where('status', 1)
                ->where('current_stage', 'Approved')
                ->get();

            if (count($salesmanInfos)) {

                $salesman_user_ids = $salesmanInfos->pluck('user_id')->toArray();

                $s_id_string = implode(',', $salesman_user_ids);

                $s_day = $start_date->day;
                $e_day = $end_date->day;

                $s_month = $start_date->month;
                $e_month = $end_date->month;

                if ($s_month == $e_month) {
                    $months = $s_month;
                } else {
                    $months = $s_month . ',' . $e_month;
                    if ($e_day == "1") {
                        $previous = $end_date->subWeek();
                        $start_date = $previous;
                        $end_date = Carbon::parse($previous)->endOfMonth();

                        $s_day = $start_date->day;
                        $months = $start_date->month;
                        $e_day = $end_date->day;
                    } else {
                        if (in_array($s_day, [26, 27, 28, 29, 30, 31])) {
                            $start_date = Carbon::parse($end_date)->firstOfMonth();
                            $s_day = $start_date->day;
                            $months = $start_date->month;
                        }
                    }
                }

                $weeek_diff = DB::select("SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number");

                $week_count = count($weeek_diff);

                $customer_details = DB::select("SELECT CONCAT(users.firstname, ' ', users.lastname) as RES, round((SUM(`visit`) / SUM(`planned`) * 100), 2) as EXECUTION, round(SUM(`planned`)) as TOTAL_OUTLETS, round(SUM(`visit`)) as VISITS FROM `merchandiser_coverages` LEFT JOIN salesman_infos on salesman_infos.user_id = salesman_id LEFT JOIN users on users.id = salesman_infos.salesman_supervisor WHERE `week_number` IN (SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number) AND month in ($months) and salesman_id in ($s_id_string) GROUP BY salesman_infos.salesman_supervisor");

                $collect = collect($customer_details);

                $execution = array_sum($collect->pluck('EXECUTION')->toArray());

                $visits     = array_sum($collect->pluck('VISITS')->toArray());
                $total_out  = array_sum($collect->pluck('TOTAL_OUTLETS')->toArray());

                $percentage = 0;
                if ($visits > 0 && $total_out > 0) {
                    $percentage = round(($visits / $total_out) * 100, 2);
                }

                $jp_ids = JourneyPlan::select('id')->whereIn('merchandiser_id', $salesman_user_ids)->get();

                if (count($jp_ids)) {
                    $week_array = array();
                    for ($h = 1; $h <= $week_count; $h++) {
                        $week_array[] = "week" . $h;
                    }
                    $jp_week = JourneyPlanWeek::select('id')->whereIn('journey_plan_id', $jp_ids)
                        ->whereIn('week_number', $week_array)
                        ->get();
                    $jp_day = JourneyPlanDay::select('id')->whereIn('journey_plan_week_id', $jp_week)->get();

                    $final_jp_id = implode(',', $jp_ids->pluck('id')->toArray());
                    $final_jp_days = implode(',', $jp_day->pluck('id')->toArray());
                }
                $final_salesman_id = $s_id_string;
            }
        } else {
            $start_date = Carbon::parse($start_date)->addDay(2);
            $end_date = Carbon::parse($end_date);
            // Week Diffent

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

            if (count($salesman_user_ids)) {

                $s_id_string = implode(',', $salesman_user_ids);

                $s_day = $start_date->day;
                $e_day = $end_date->day;

                $s_month = $start_date->month;
                $e_month = $end_date->month;

                if ($s_month == $e_month) {
                    $months = $s_month;
                } else {
                    $months = $s_month . ',' . $e_month;
                    if ($e_day == "1") {
                        $previous = $end_date->subWeek();
                        $start_date = $previous;
                        $end_date = Carbon::parse($previous)->endOfMonth();

                        $s_day = $start_date->day;
                        $months = $start_date->month;
                        $e_day = $end_date->day;
                    } else {
                        if (in_array($s_day, [26, 27, 28, 29, 30, 31])) {
                            $start_date = Carbon::parse($end_date)->firstOfMonth();
                            $s_day = $start_date->day;
                            $months = $start_date->month;
                        }
                    }
                }

                $weeek_diff = DB::select("SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number");

                $week_count = count($weeek_diff);

                $customer_details = DB::select("SELECT CONCAT(users.firstname, ' ', users.lastname) as RES, round((SUM(`visit`) / SUM(`planned`) * 100), 2) as EXECUTION, round(round(SUM(`planned`))) as TOTAL_OUTLETS, round(SUM(`visit`)) as VISITS FROM `merchandiser_coverages` LEFT JOIN users on users.id = salesman_id WHERE `week_number` IN (SELECT `week_id` FROM `merchandiser_coverge_weeks` WHERE `week_date` BETWEEN '$s_day' AND '$e_day' GROUP BY week_number) AND month in ($months) and salesman_id in ($s_id_string) GROUP BY salesman_id");
                $collect = collect($customer_details);

                $visits     = array_sum($collect->pluck('VISITS')->toArray());
                $total_out  = array_sum($collect->pluck('TOTAL_OUTLETS')->toArray());

                $percentage = 0;
                if ($visits > 0 && $total_out > 0) {
                    $percentage = round(($visits / $total_out) * 100, 2);
                }

                $jp_ids = JourneyPlan::select('id')->whereIn('merchandiser_id', $salesman_user_ids)->get();
                if (count($jp_ids)) {
                    $week_array = array();
                    for ($h = 1; $h <= $week_count; $h++) {
                        $week_array[] = "week" . $h;
                    }
                    $jp_week = JourneyPlanWeek::select('id')->whereIn('journey_plan_id', $jp_ids)
                        ->whereIn('week_number', $week_array)
                        ->get();
                    $jp_day = JourneyPlanDay::select('id')->whereIn('journey_plan_week_id', $jp_week)->get();

                    $final_jp_id = implode(',', $jp_ids->pluck('id')->toArray());
                    $final_jp_days = implode(',', $jp_day->pluck('id')->toArray());
                }
                $final_salesman_id = $s_id_string;
            }
        }

        if ($final_jp_id != '' && $final_jp_days != '') {

            if (($request->start_date == $request->end_date) && !empty($request->start_date)) {
                $start_date = Carbon::parse($start_date)->format('Y-m-d');
                $final_report = DB::select("SELECT
                date,
                customer_visits.customer_id,
                customer_infos.customer_code as customerCode,
                CONCAT(customerInfo.firstname, ' ', customerInfo.lastname) as customer,
                cc.customer_category_name AS category,
                si.salesman_code as merchandiserCode,
                CONCAT(salesman.firstname, ' ', salesman.lastname) as merchandiser,
                CONCAT(sup_user.firstname, ' ', sup_user.lastname) as supervisor,
                regions.region_name AS region,
                channels.name AS channel,
                COUNT(customer_visits.customer_id) AS no_of_tasks_completed, (SELECT COUNT(journey_plan_customers.customer_id) FROM journey_plan_customers WHERE journey_plan_customers.journey_plan_id in ($final_jp_id) AND journey_plan_customers.journey_plan_day_id IN($final_jp_days) AND journey_plan_customers.customer_id =(SELECT id FROM customer_infos WHERE customer_infos.user_id = customer_visits.customer_id)) AS total_tasks_planned FROM `customer_visits` LEFT JOIN customer_infos ON customer_visits.customer_id = customer_infos.user_id LEFT JOIN users as customerInfo ON customerInfo.id = customer_infos.user_id LEFT JOIN customer_categories AS cc ON customer_infos.customer_category_id = cc.id LEFT JOIN customer_merchandisers AS cm ON customer_infos.user_id = cm.customer_id LEFT JOIN salesman_infos AS si ON si.user_id = cm.merchandiser_id LEFT JOIN users AS salesman ON si.user_id = salesman.id LEFT JOIN users AS sup_user ON sup_user.id = si.salesman_supervisor LEFT JOIN regions ON regions.id = customer_infos.region_id LEFT JOIN channels ON channels.id = customer_infos.channel_id WHERE si.user_id in ($final_salesman_id) AND date '$start_date' GROUP BY customer_id");
            } else {
                $start_date = Carbon::parse($start_date)->format('Y-m-d');
                $end_date = Carbon::parse($end_date)->format('Y-m-d');

                $final_report = DB::select("SELECT  CONCAT(sup_user.firstname, ' ', sup_user.lastname) as supervisor,
                regions.region_name AS region,
                channels.name AS channel, CONCAT(salesman.firstname, ' ', salesman.lastname) as merchandiser,si.salesman_code as merchandiserCode,cc.customer_category_name AS category, customer_infos.customer_code as customerCode,CONCAT(customerInfo.firstname, ' ', customerInfo.lastname) as customer,JP.customer_id, count(JP.customer_id) as total_tasks_planned, (SELECT customer_infos.user_id FROM customer_infos WHERE customer_infos.id = JP.customer_id) as userid, (SELECT DISTINCT merchandiser_id FROM journey_plans WHERE journey_plans.id = JP.journey_plan_id) as salesman_id,
                (SELECT COUNT(DISTINCT date,customer_id) FROM customer_visits WHERE customer_visits.customer_id = (SELECT customer_infos.user_id FROM customer_infos WHERE customer_infos.id = JP.customer_id) AND salesman_id in ($final_salesman_id) AND DATE BETWEEN '$start_date' AND '$end_date' ORDER BY customer_id) as no_of_tasks_completed
                FROM `journey_plan_customers` as JP 
                LEFT JOIN users as customerInfo ON customerInfo.id = (SELECT customer_infos.user_id FROM customer_infos WHERE customer_infos.id = JP.customer_id) 
                LEFT JOIN customer_infos ON (SELECT customer_infos.user_id FROM customer_infos WHERE customer_infos.id = JP.customer_id) = customer_infos.user_id 
                LEFT JOIN customer_categories AS cc ON customer_infos.customer_category_id = cc.id
                LEFT JOIN salesman_infos AS si ON si.user_id = (SELECT DISTINCT merchandiser_id FROM journey_plans WHERE journey_plans.id = JP.journey_plan_id)
                LEFT JOIN users AS salesman ON si.user_id = salesman.id
                LEFT JOIN users AS sup_user ON sup_user.id = si.salesman_supervisor 
                LEFT JOIN regions ON regions.id = customer_infos.region_id 
                LEFT JOIN channels ON channels.id = customer_infos.channel_id
                WHERE  JP.journey_plan_id in ($final_jp_id) and JP.journey_plan_day_id in ($final_jp_days) GROUP by JP.customer_id");
            }

            if (count($final_report)) {
                foreach ($final_report as $key => $report) {
                    $final_report[$key]->no_of_tasks_completed = ($report->no_of_tasks_completed > $report->total_tasks_planned) ? $report->total_tasks_planned : $report->no_of_tasks_completed;
                }
            }
        }
        $coverage = new \stdClass();
        $coverage->title = "Coverage";
        $coverage->text = "Outlets Visited atleast once this month vs all outlet in the market";
        $coverage->percentage = $percentage . "%";
        $coverage->trends = $trends_data;
        $coverage->comparison = $comparison;
        $coverage->contribution = $comparison;
        $coverage->details = $customer_details;
        $coverage->listing = $final_report;

        return $coverage;
    }

    public function msl($request, $start_date, $end_date)
    {
        $details = array();
        $trends_data = array();
        $salesman_idss = array();
        $comparison = [];
        $listing = [];
        $percentage = 0;

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


        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = array();
            $get_all_salesman = array();
            $salesman_idss = $request->nsm;
            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);

                $nsm_user = User::find($nsm);
                $get_all_salesman[] = $all_salesman;

                $customerMerchandiser = CustomerMerchandiser::select('customer_id')
                    ->whereIn('merchandiser_id', $all_salesman)
                    ->get();

                if (count($customerMerchandiser)) {

                    $visit = CustomerVisit::select('customer_id')
                        ->whereIn('customer_id', $customerMerchandiser)
                        ->whereIn('salesman_id', $all_salesman);

                    if ($start_date != '' && $end_date != '') {
                        if ($start_date == $end_date) {
                            $visit->whereDate('date', $start_date);
                        } else {
                            $visit->whereBetween('date', [$start_date, $end_date]);
                        }
                    }

                    $visits = $visit->groupBy('customer_id')->get();

                    $dms = DistributionModelStock::select('id', 'customer_id')
                        ->whereIn('customer_id', $visits)
                        ->get();

                    if (count($dms)) {
                        $d_m_s_ids = $dms->pluck('id')->toArray();

                        $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                            ->whereIn('distribution_model_stock_id', $d_m_s_ids)
                            ->where('is_deleted', 0)
                            ->first();

                        $ds_qeury = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                            ->whereIn('salesman_id', $all_salesman)
                            ->whereIn('customer_id', $visit)
                            ->where('is_out_of_stock', 1);

                        if ($start_date != '' && $end_date != '') {
                            if ($start_date == $end_date) {
                                $ds_qeury->whereDate('created_at', $start_date);
                            } else {
                                $ds_qeury->whereBetween('created_at', [$start_date, $end_date]);
                            }
                        }

                        $ds = $ds_qeury->first();

                        $per = 0;
                        $out_of_stock_count = isset($ds->total_out_of_stock) ? $ds->total_out_of_stock : 0; // Out of stock count
                        $item_count = isset($dmsd->total_msl_item) ? $dmsd->total_msl_item : 0;

                        if ($out_of_stock_count != 0 && $item_count != 0) {
                            $per = round(($out_of_stock_count / $item_count) * 100, 2);
                        }

                        $comparison[] = array(
                            'name' => $nsm_user->getName(),
                            'steps' => round($per) ?? 0
                        );

                        $details[] = array(
                            'RES'               => $nsm_user->getName(),
                            'TOTAL_OUTLETS'     => ($item_count > 0) ? $item_count : "0",
                            'VISITS'            => ($out_of_stock_count > 0) ? $out_of_stock_count : '0',
                            'EXECUTION'         => ($per > 0) ? $per : '0'
                        );
                    }
                }
            }
            // $trends_data = $this->mslTrendData($get_all_salesman, $start_date, $end_date);
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_idss = $request->asm;
            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $asm_user = User::find($asm);
                $get_all_salesman[] = $all_salesman;

                $customerMerchandiser = CustomerMerchandiser::select('customer_id')
                    ->whereIn('merchandiser_id', $all_salesman)
                    ->get();

                if (count($customerMerchandiser)) {
                    // $customer_id = $customerMerchandiser->pluck('customer_id')->toArray();

                    $dms = DistributionModelStock::select('id', 'customer_id')
                        ->whereIn('customer_id', $customerMerchandiser)
                        ->get();

                    if (count($dms)) {
                        $d_m_s_ids = $dms->pluck('id')->toArray();
                        $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $d_m_s_ids)
                            ->where('is_deleted', 0)
                            ->get();

                        if ($dmsd) {
                            $ds_qeury = DistributionStock::whereIn('salesman_id', $all_salesman);

                            if ($start_date != '' && $end_date != '') {
                                if ($start_date == $end_date) {
                                    $ds_qeury->whereDate('created_at', $start_date);
                                } else {
                                    $ds_qeury->whereBetween('created_at', [$start_date, $end_date]);
                                }
                            }

                            $ds = $ds_qeury->get();

                            $out_of_stock_count = count($ds);

                            $per = 0;
                            $item_ids = $dmsd->pluck('item_id')->toArray();
                            $item_count = count($item_ids);
                            $customer_count = count($dms);
                            if ($out_of_stock_count != 0 && $item_count != 0) {
                                $per = round(($out_of_stock_count / $item_count) * 100, 2);
                            }

                            $comparison[] = array(
                                'name' => $asm_user->getName(),
                                'steps' => round($per) ?? 0
                            );

                            $details[] = array(
                                'RES'               => $asm_user->getName(),
                                'customer_count'    => ($customer_count > 0) ? $customer_count : "0",
                                'TOTAL_OUTLETS'     => ($item_count > 0) ? $item_count : "0",
                                'VISITS'            => ($out_of_stock_count > 0) ? $out_of_stock_count : '0',
                                'EXECUTION'         => ($per > 0) ? $per : '0'
                            );
                        }
                    }
                }
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

            foreach ($request->channel_ids as $channel_id) {
                $custome_ids = array();

                $channel = Channel::find($channel_id);
                $customerInfo = CustomerInfo::where('channel_id', $channel_id)->get();
                if (count($customerInfo)) {
                    $custome_ids = $customerInfo->pluck('user_id')->toArray();
                }
                $final_customer = array_intersect($all_salesman_customer, $custome_ids);

                $get_all_salesman[] = $final_customer;

                $visit = CustomerVisit::select('customer_id')
                    ->whereIn('customer_id', $final_customer);

                if ($start_date != '' && $end_date != '') {
                    if ($start_date == $end_date) {
                        $visit->whereDate('date', $start_date);
                    } else {
                        $visit->whereBetween('date', [$start_date, $end_date]);
                    }
                }

                $visits = $visit->groupBy('customer_id')->get();

                $dis = Distribution::select('id')->get();

                $dms = DistributionModelStock::select('id')
                    ->whereIn('customer_id', $visits)
                    ->whereIn('distribution_id', $dis)
                    ->get();

                if (count($dms)) {
                    // $d_m_s_ids = $dms->pluck('id')->toArray();

                    $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                        ->whereIn('distribution_model_stock_id', $dms)
                        ->where('is_deleted', 0)
                        ->first();

                    if ($dmsd) {
                        $ds_qeury = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                            ->whereIn('customer_id', $visits)
                            ->where('is_out_of_stock', 1);

                        if ($start_date != '' && $end_date != '') {
                            if ($start_date == $end_date) {
                                $ds_qeury->whereDate('created_at', $start_date);
                            } else {
                                $ds_qeury->whereBetween('created_at', [$start_date, $end_date]);
                            }
                        }

                        $ds = $ds_qeury->first();

                        $out_of_stock_count = $ds->total_out_of_stock; // Out of stock count
                        $per = 0;
                        $item_count = $dmsd->total_msl_item; // 500

                        if ($out_of_stock_count != 0 && $item_count != 0) {
                            $per = round(($out_of_stock_count / $item_count) * 100, 2);
                        }

                        $comparison[] = array(
                            'name' => model($channel, 'name'),
                            'steps' => round($per) ?? 0
                        );

                        $details[] = array(
                            'RES'               => model($channel, 'name'),
                            'TOTAL_OUTLETS'     => ($item_count > 0) ? $item_count : "0",
                            'VISITS'            => ($out_of_stock_count > 0) ? $out_of_stock_count : '0',
                            'EXECUTION'         => ($per > 0) ? $per : '0'
                        );
                    }
                }
            }
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
        } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
            $supervisor = $request->supervisor;
            foreach ($supervisor as $s) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->where('salesman_supervisor', $s)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();

                $salesman_ids = array();
                if (count($salesman_infos)) {
                    $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
                    $salesman_idss = $salesman_ids;
                }

                $start_date = Carbon::parse($start_date)->addDay(1)->format('Y-m-d');
                // salesman wise customer
                $customerMerchandiser = CustomerMerchandiser::select('customer_id')
                    ->whereIn('merchandiser_id', $salesman_ids)
                    ->get();

                $customer_id = array();
                if (count($customerMerchandiser)) {

                    $visit = CustomerVisit::select('customer_id')
                        ->whereIn('customer_id', $customerMerchandiser)
                        ->whereIn('salesman_id', $salesman_ids);

                    if ($start_date != '' && $end_date != '') {
                        if ($start_date == $end_date) {
                            $visit->whereDate('date', $start_date);
                        } else {
                            $visit->whereBetween('date', [$start_date, $end_date]);
                        }
                    }

                    $visits = $visit->groupBy('customer_id')->get();

                    $dms = DistributionModelStock::select('id', 'customer_id')
                        ->whereIn('customer_id', $visit)
                        ->get();

                    if (count($dms)) {
                        $d_m_s_ids = $dms->pluck('id')->toArray();

                        $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                            ->whereIn('distribution_model_stock_id', $d_m_s_ids)
                            ->where('is_deleted', 0)
                            ->first();

                        $ds_qeury = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                            ->whereIn('salesman_id', $salesman_ids)
                            ->whereIn('customer_id', $visit)
                            ->where('is_out_of_stock', 1);

                        if ($start_date != '' && $end_date != '') {
                            if ($start_date == $end_date) {
                                $ds_qeury->whereDate('created_at', $start_date);
                            } else {
                                $ds_qeury->whereBetween('created_at', [$start_date, $end_date]);
                            }
                        }

                        $ds = $ds_qeury->first();

                        $per = 0;
                        $out_of_stock_count = isset($ds->total_out_of_stock) ? $ds->total_out_of_stock : 0; // Out of stock count
                        $item_count = isset($dmsd->total_msl_item) ? $dmsd->total_msl_item : 0;

                        if ($out_of_stock_count != 0 && $item_count != 0) {
                            $per = round(($out_of_stock_count / $item_count) * 100, 2);
                        }

                        $salesman_info = User::find($s);

                        $comparison[] = array(
                            'name' => $salesman_info->getName(),
                            'steps' => round($per) ?? 0
                        );

                        $details[] = array(
                            'RES'               => $salesman_info->getName(),
                            'TOTAL_OUTLETS'     => ($item_count > 0) ? $item_count : "0",
                            'VISITS'            => ($out_of_stock_count > 0) ? $out_of_stock_count : '0',
                            'EXECUTION'         => ($per > 0) ? $per : '0'
                        );
                    }
                }
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
            $visitCustomers = array();

            // trends data
            // $trends_data = $this->mslTrendData($salesman_ids, $start_date, $end_date);

            // compaire and details
            if (count($salesman_ids)) {

                $dis = Distribution::select('id')->get();

                foreach ($salesman_ids as $salesman_id) {

                    // salesman wise customer
                    $customerMerchandiser = CustomerMerchandiser::select('customer_id')
                        ->where('merchandiser_id', $salesman_id)
                        ->get();

                    $visit = CustomerVisit::select('customer_id')
                        ->whereIn('customer_id', $customerMerchandiser)
                        ->where('salesman_id', $salesman_id);

                    if ($start_date != '' && $end_date != '') {
                        if ($start_date == $end_date) {
                            $visit->whereDate('date', $start_date);
                        } else {
                            $visit->whereBetween('date', [$start_date, $end_date]);
                        }
                    }

                    $visits = $visit->groupBy('customer_id')->get();

                    if (count($visits)) {

                        //  $dis = Distribution::select('id')->get();

                        $dms = DistributionModelStock::select('id')
                            ->whereIn('customer_id', $visits)
                            ->get();

                        if (count($dms)) {

                            $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                                ->whereIn('distribution_model_stock_id', $dms)
                                ->where('is_deleted', 0)
                                ->first();

                            $ds_qeury = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                                ->whereIn('customer_id', $visits)
                                ->where('is_out_of_stock', 1)
                                ->where('salesman_id', $salesman_id);

                            if ($start_date != '' && $end_date != '') {
                                if ($start_date == $end_date) {
                                    $ds_qeury->whereDate('created_at', $start_date);
                                } else {
                                    $ds_qeury->whereBetween('created_at', [$start_date, $end_date]);
                                }
                            }

                            $ds = $ds_qeury->first();

                            $out_of_stock_count = $ds->total_out_of_stock; // Out of stock count
                            $per = 0;
                            $item_count = $dmsd->total_msl_item; // 500

                            if ($out_of_stock_count != 0 && $item_count != 0) {
                                $per = round(($out_of_stock_count / $item_count) * 100, 2);
                            }

                            $salesman_info = SalesmanInfo::where('user_id', $salesman_id)->first();

                            $comparison[] = array(
                                'name' => $salesman_info->user->getName(),
                                'steps' => round($per) ?? 0
                            );

                            $details[] = array(
                                'RES'               => $salesman_info->user->getName(),
                                'TOTAL_OUTLETS'     => ($item_count > 0) ? $item_count : "0",
                                'VISITS'            => ($out_of_stock_count > 0) ? $out_of_stock_count : '0',
                                'EXECUTION'         => ($per > 0) ? $per : '0'
                            );
                        }
                    }
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
        $customer_id = array();
        $salesman_id = array();
        $s_man = true;

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $salesman_ids = array();
            foreach ($request->nsm as $nsm) {
                $s_id = getSalesman(false, $nsm);
                foreach ($s_id as $s) {
                    $salesman_ids[] = $s;
                }
            }

            $cm = CustomerMerchandiser::select('customer_id')
                ->whereIn('merchandiser_id', $salesman_ids)
                ->get();
            if ($cm) {
                $customer_id = $cm->pluck('customer_id')->toArray();
            }
            $salesman_id = $salesman_ids;
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $salesman_ids = array();
            foreach ($request->asm as $asm) {
                $s_id = getSalesman(false, $asm);
                foreach ($s_id as $s) {
                    $salesman_ids[] = $s;
                }
            }
            $salesman_id = $salesman_ids;
            $cm = CustomerMerchandiser::select('customer_id')
                ->whereIn('merchandiser_id', $salesman_ids)
                ->get();
            if ($cm) {
                $customer_id = $cm->pluck('customer_id')->toArray();
            }
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

            $salesman_id = $salesman_infos->pluck('user_id')->toArray();

            $cm = CustomerMerchandiser::select('customer_id')
                ->whereIn('merchandiser_id', $salesman_id)
                ->get();

            if (count($cm)) {
                $customer_id = $cm->pluck('customer_id')->toArray();
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

            $salesman_id = $salesman_infos->pluck('user_id')->toArray();

            $cm = CustomerMerchandiser::select('customer_id')
                ->whereIn('merchandiser_id', $salesman_id)
                ->get();

            if (count($cm)) {
                $customer_id = $cm->pluck('customer_id')->toArray();
            }
        }

        $listing = new Collection();

        $visit = CustomerVisit::select('customer_id')
            ->whereIn('customer_id', $customer_id);
        if ($start_date == $end_date) {
            $visit->where('date', $start_date);
        } else {
            $visit->whereBetween('date', [$start_date, $end_date]);
        }
        if ($s_man) {
            $visit->whereIn('salesman_id', $salesman_id);
        }
        $visits = $visit->groupBy('customer_id')->get();

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
                // get the salesman
                $cms = CustomerMerchandiser::select('merchandiser_id')
                    ->where('customer_id', $v->customer_id);
                if ($s_man) {
                    $cms->whereIn('merchandiser_id', $salesman_id);
                }
                $cm = $cms->first();

                if ($cm) {
                    $si = SalesmanInfo::where('user_id', $cm->merchandiser_id)->first();

                    if ($si) {
                        $s_code = $si->salesman_code;
                        $s_name = $si->user->getName();
                        $supervisor = User::find($si->salesman_supervisor);
                        if ($supervisor) {
                            $supervisor_name = $supervisor->getName();
                        }
                    }
                }

                $dms = DistributionModelStock::select('id')
                    ->where('customer_id', $v->customer_id)
                    // ->whereIn('distribution_id', $dis)
                    ->get();

                if (count($dms)) {

                    $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                        ->whereIn('distribution_model_stock_id', $dms)
                        ->where('is_deleted', 0)
                        ->first();

                    $dss = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                        ->where('customer_id', $v->customer_id)
                        ->where('is_out_of_stock', 1);

                    if ($start_date == $end_date) {
                        $dss->whereDate('created_at', $start_date);
                    } else {
                        $dss->whereBetween('created_at', [$start_date, $end_date]);
                    }

                    if ($s_man) {
                        $dss->whereIn('salesman_id', $salesman_id);
                    }

                    $ds = $dss->first();

                    $out_of_stock_count = $ds->total_out_of_stock; // Out of stock count
                    $item_count = $dmsd->total_msl_item; // 500
                    $per = 0;

                    if ($out_of_stock_count != 0 && $item_count != 0) {
                        $per = round(($out_of_stock_count / $item_count) * 100, 2);
                    }

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
                        'msl_compliance'    => $per
                    ]);
                }
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

    public function visitFrequency($request, $start_date, $end_date)
    {
        $start_date         = Carbon::parse($start_date)->subDay()->format('Y-m-d');
        $percentage         = 0;
        $trends_data        = array();
        $comparison         = array();
        $comparison         = array();
        $customer_details   = array();
        $listing            = array();

        if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $get_all_salesman = array();
            foreach ($request->nsm as $nsm) {
                $all_salesman = getSalesman(false, $nsm);
                $get_all_salesman[] = $all_salesman;
            }

            $all_salesman = Arr::collapse($get_all_salesman);

            $s_id_string = implode(',', $all_salesman);

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");
            if (count($all_salesman)) {
                $customer_details = DB::select("SELECT concat(users.firstname, ' ', users.lastname) as RES, SUM(TNSM.VISITS) as VISITS, SUM(TNSM.customercount) as TOTAL_OUTLETS, ROUND(SUM(TNSM.VISITS) / $date_diff) as EXECUTION FROM (SELECT SALM.VISITS, SALM.salesman_id, SALM.nsm_id,count(customer_merchandisers.customer_id) as customercount FROM (SELECT count(TEMP.is_sequnece) as VISITS,TEMP.nsm_id,TEMP.salesman_id FROM (select customer_id,is_sequnece,date,salesman_id,salesman_infos.nsm_id from `customer_visits` LEFT JOIN salesman_infos ON salesman_id = salesman_infos.user_id where `salesman_id` IN ($s_id_string) and `date` between '$start_date' and '$end_date' and `customer_visits`.`deleted_at` is null and customer_visits.`organisation_id` = 1 GROUP BY date,customer_id) AS TEMP GROUP BY TEMP.salesman_id) AS SALM LEFT JOIN customer_merchandisers ON  customer_merchandisers.merchandiser_id = SALM.salesman_id GROUP BY SALM.salesman_id) AS TNSM LEFT JOIN users ON users.id = TNSM.nsm_id GROUP BY TNSM.nsm_id");

                $percentage = round(array_sum(collect($customer_details)->pluck('VISITS')->toArray()) / $date_diff);

                $listing = $this->visitListing($s_id_string, $start_date, $end_date, $this->organisation_id);
            }
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
            $get_all_salesman = array();

            foreach ($request->asm as $asm) {
                $all_salesman = getSalesman(false, $asm);
                $get_all_salesman[] = $all_salesman;
            }

            $all_salesman = Arr::collapse($get_all_salesman);

            $s_id_string = implode(',', $all_salesman);

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");

            if (count($all_salesman)) {
                $customer_details = DB::select("SELECT concat(users.firstname, ' ', users.lastname) as RES, SUM(TNSM.VISITS) as VISITS, SUM(TNSM.customercount) as TOTAL_OUTLETS, ROUND(SUM(TNSM.VISITS) / $date_diff) as EXECUTION FROM (SELECT SALM.VISITS, SALM.salesman_id, SALM.asm_id,count(customer_merchandisers.customer_id) as customercount FROM (SELECT count(TEMP.is_sequnece) as VISITS,TEMP.asm_id,TEMP.salesman_id FROM (select customer_id,is_sequnece,date,salesman_id,salesman_infos.asm_id from `customer_visits` LEFT JOIN salesman_infos ON salesman_id = salesman_infos.user_id where `salesman_id` IN ($s_id_string) and `date` between '$start_date' and '$end_date' and `customer_visits`.`deleted_at` is null and customer_visits.`organisation_id` = 1 GROUP BY date,customer_id) AS TEMP GROUP BY TEMP.salesman_id) AS SALM LEFT JOIN customer_merchandisers ON  customer_merchandisers.merchandiser_id = SALM.salesman_id GROUP BY SALM.salesman_id) AS TNSM LEFT JOIN users ON users.id = TNSM.asm_id GROUP BY TNSM.asm_id");

                $percentage = round(array_sum(collect($customer_details)->pluck('VISITS')->toArray()) / $date_diff);

                $listing = $this->visitListing($s_id_string, $start_date, $end_date, $this->organisation_id);
            }
        } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {

            $salesman = SalesmanInfo::whereIn('salesman_supervisor', $request->supervisor)->get();

            $salesman_ids = array();
            if (count($salesman)) {
                $salesman_ids = $salesman->pluck('user_id')->toArray();
            }

            $s_id_string = implode(',', $salesman_ids);

            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");

            if (count($salesman_ids)) {

                $customer_details = DB::select("SELECT concat(users.firstname, ' ', users.lastname) as RES,SUM(TNSM.VISITS) as VISITS, SUM(TNSM.customercount) as TOTAL_OUTLETS, ROUND(SUM(TNSM.visits) / $date_diff) as EXECUTION FROM (SELECT SALM.VISITS, SALM.salesman_id, SALM.salesman_supervisor,count(customer_merchandisers.customer_id) as customercount FROM (SELECT count(TEMP.is_sequnece) as VISITS,TEMP.salesman_supervisor,TEMP.salesman_id FROM (select customer_id,is_sequnece,date,salesman_id,salesman_infos.salesman_supervisor from `customer_visits` LEFT JOIN salesman_infos ON salesman_id = salesman_infos.user_id where `salesman_id` IN ($s_id_string) and `date` between '$start_date' and '$end_date' and `customer_visits`.`deleted_at` is null and customer_visits.`organisation_id` = 1 GROUP BY date,customer_id) AS TEMP GROUP BY TEMP.salesman_id) AS SALM LEFT JOIN customer_merchandisers ON  customer_merchandisers.merchandiser_id = SALM.salesman_id GROUP BY SALM.salesman_id) AS TNSM LEFT JOIN users ON users.id = TNSM.salesman_supervisor GROUP BY TNSM.salesman_supervisor");

                $percentage = round(array_sum(collect($customer_details)->pluck('VISITS')->toArray()) / $date_diff);

                $listing = $this->visitListing($s_id_string, $start_date, $end_date, $this->organisation_id);
            }
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

            $s_id_string = implode(',', $salesman_user_ids);
            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");

            if (count($salesman_user_ids)) {
                $customer_details = DB::select("SELECT concat(users.firstname, ' ', users.lastname) as RES,SALM.VISITS as VISITS, count(customer_merchandisers.customer_id) as TOTAL_OUTLETS , round(SALM.VISITS / $date_diff) as EXECUTION FROM (SELECT count(TEMP.is_sequnece) as VISITS,TEMP.salesman_id FROM  (select customer_id,is_sequnece,date,salesman_id from `customer_visits` where `salesman_id` IN ($s_id_string) and `date` between '$start_date' and '$end_date' and `customer_visits`.`deleted_at` is null and `organisation_id` = 1 group by date, customer_id) AS TEMP GROUP BY TEMP.salesman_id) as SALM LEFT JOIN customer_merchandisers ON  customer_merchandisers.merchandiser_id = SALM.salesman_id LEFT JOIN users ON users.id = SALM.salesman_id GROUP BY SALM.salesman_id");

                $percentage = round(array_sum(collect($customer_details)->pluck('VISITS')->toArray()) / $date_diff);

                $listing = $this->visitListing($s_id_string, $start_date, $end_date, $this->organisation_id);
            }
        }

        $visit_per_day = new \stdClass();
        $visit_per_day->title = "visit Frequency";
        $visit_per_day->text = "Average # of visits made by a sales man in a day";
        $visit_per_day->percentage  = $percentage;
        $visit_per_day->trends      = $trends_data;
        $visit_per_day->comparison  = $comparison;
        $visit_per_day->contribution = $comparison;
        $visit_per_day->details     = $customer_details;
        $visit_per_day->listing     = $listing;
        return $visit_per_day;
    }

    private function visitListing($salesman_id, $start_date, $end_date, $organisation_id)
    {
        return array();
        return DB::select("select date, CONCAT(customerInfo.firstname,' ',customerInfo.lastname) AS customer,
        customer_infos.customer_code AS customerCode,
        customer_categories.customer_category_name AS category,
        CONCAT(salesman.firstname,' ',salesman.lastname) AS merchandiser,
        salesman_infos.salesman_supervisor AS supervisor,
        channels.name AS channel,
        regions.region_name AS region from `customer_visits` LEFT JOIN salesman_infos ON salesman_id = salesman_infos.user_id inner join `users` as `salesman` on `salesman`.`id` = `customer_visits`.`salesman_id` 
        inner join `customer_infos` on `customer_infos`.`user_id` = `customer_visits`.`customer_id` 
        inner join `users` as `customerInfo` on `customerInfo`.`id` = `customer_visits`.`customer_id` 
        inner join `channels` on `channels`.`id` = `customer_infos`.`channel_id` 
        inner join `regions` on `regions`.`id` = `customer_infos`.`region_id` 
        inner join `customer_categories` on `customer_categories`.`id` = `customer_infos`.`customer_category_id` where `salesman_id` IN ($salesman_id) and `date` between '$start_date' and '$end_date' and `customer_visits`.`deleted_at` is null and customer_visits.`organisation_id` = '$organisation_id' GROUP BY date,customer_id order by date");
    }

    public function activeOutlets($request)
    {
        if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {

            $customer_details = array();
            $salesman_id_array = array();
            foreach ($request->supervisor as $supervisor) {
                $salesman_infos = SalesmanInfo::select('user_id')
                    ->where('salesman_supervisor', $supervisor)
                    ->get();

                $salesman_infos_ids = $salesman_infos->pluck('user_id')->toArray();
                $salesman_id_array[] = $salesman_infos_ids;
                $cm = CustomerMerchandiser::select(DB::raw('IF(count(customer_id) > 0 , count(customer_id), 0) as customer'))
                    ->whereIn('merchandiser_id', $salesman_infos_ids)
                    ->first();

                $user = User::find($supervisor);
                $customer_details[] = array(
                    'RES'           => $user->getName(),
                    'TOTAL_OUTLETS' => $cm->customer,
                    'VISITS'        => $cm->customer,
                    'EXECUTION'     => $cm->customer
                );
            }

            $salesman_ids = Arr::collapse($salesman_id_array);
            $percentage = DB::table('customer_merchandisers')
                ->whereIn('customer_merchandisers.merchandiser_id', $salesman_ids)
                ->groupBy('customer_merchandisers.customer_id')
                ->get()
                ->count();
            $listing = $this->outletLising($salesman_ids);
        } else if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
            $customer_details = array();
            $salesman_id_array = array();

            foreach ($request->nsm as $nsm) {
                $all_customers = getSalesman(true, $nsm);
                $salesman_user_ids = getSalesman(false, $nsm);
                $salesman_id_array[] = $salesman_user_ids;

                $user = User::find($nsm);

                $customer_details[] = array(
                    'RES'           => $user->getName(),
                    'TOTAL_OUTLETS' => count($all_customers),
                    'VISITS'        => count($all_customers),
                    'EXECUTION'     => count($all_customers)
                );
            }

            $salesman_ids = Arr::collapse($salesman_id_array);
            $percentage = DB::table('customer_merchandisers')
                ->whereIn('customer_merchandisers.merchandiser_id', $salesman_ids)
                ->groupBy('customer_merchandisers.customer_id')
                ->get()
                ->count();
            $listing = $this->outletLising($salesman_ids);
        } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {

            $customer_details = array();
            $salesman_id_array = array();

            foreach ($request->asm as $asm) {
                $all_customers = getSalesman(true, $asm);
                $salesman_user_ids = getSalesman(false, $asm);
                $salesman_id_array[] = $salesman_user_ids;

                $user = User::find($asm);

                $customer_details[] = array(
                    'RES'           => $user->getName(),
                    'TOTAL_OUTLETS' => count($all_customers),
                    'VISITS'        => count($all_customers),
                    'EXECUTION'     => count($all_customers)
                );
            }

            $salesman_ids = Arr::collapse($salesman_id_array);
            $percentage = DB::table('customer_merchandisers')
                ->whereIn('customer_merchandisers.merchandiser_id', $salesman_ids)
                ->groupBy('customer_merchandisers.customer_id')
                ->get()
                ->count();
            $listing = $this->outletLising($salesman_ids);
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

            $customer_details = DB::table('customer_merchandisers')
                ->select(
                    DB::raw("CONCAT(users.firstname , ' ' , users.lastname) AS RES"),
                    DB::raw("count('customer_merchandisers.customer_id') AS TOTAL_OUTLETS"),
                    DB::raw("count('customer_merchandisers.customer_id') AS VISITS"),
                    DB::raw("count('customer_merchandisers.customer_id') AS EXECUTION")
                )
                ->leftJoin('users', 'users.id', '=', 'customer_merchandisers.merchandiser_id')
                ->whereIn('customer_merchandisers.merchandiser_id', $salesman_user_ids)
                ->groupBy('customer_merchandisers.merchandiser_id')
                ->get();

            $percentage = DB::table('customer_merchandisers')
                ->whereIn('customer_merchandisers.merchandiser_id', $salesman_user_ids)
                ->groupBy('customer_merchandisers.customer_id')
                ->get()
                ->count();

            $listing = $this->outletLising($salesman_user_ids);
        }

        $activeOutlets = new \stdClass();
        $activeOutlets->title       = "Active Outlets";
        $activeOutlets->text        = "Where atleast one order was made from a visit this month";
        $activeOutlets->percentage  = $percentage;
        $activeOutlets->trends      = array();
        $activeOutlets->comparison  = array();
        $activeOutlets->contribution = array();
        $activeOutlets->details     = $customer_details;
        $activeOutlets->listing     = $listing;

        return $activeOutlets;
    }

    public function outletLising($salesman_ids)
    {
        return DB::table('customer_merchandisers')->select(
            DB::raw('customerInfo.firstname AS customer,
            customer_infos.customer_code AS customerCode,
            customer_categories.customer_category_name AS category,
            salesman.firstname AS merchandiser,
            salesmanSupervisor.firstname AS supervisor,
            channels.name AS channel,
            regions.region_name AS region')
        )
            ->join('users as salesman', 'salesman.id', '=', 'customer_merchandisers.merchandiser_id')
            ->join('salesman_infos as salesmanInfo', 'salesmanInfo.user_id', '=', 'customer_merchandisers.merchandiser_id')
            ->join('users as salesmanSupervisor', 'salesmanSupervisor.id', '=', 'salesmanInfo.salesman_supervisor')
            ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id')
            ->join('users as customerInfo', 'customerInfo.id', '=', 'customer_infos.user_id')
            ->join('customer_categories', 'customer_categories.id', '=', 'customer_infos.customer_category_id')
            ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
            ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
            ->whereIn('salesman.id', $salesman_ids)
            ->where('salesmanInfo.organisation_id', $this->organisation_id)
            ->whereNull('salesman.deleted_at')
            ->whereNull('customerInfo.deleted_at')
            ->groupBy('customer_merchandisers.customer_id')
            ->get();
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

        if (!$request->start_date && !$request->end_date) {
            $start_date = Carbon::now()->subDay(7)->format('Y-m-d');
            $end_date = Carbon::now()->format('Y-m-d');
        } else {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');
        }

        $customer_visit_query = CustomerVisit::select([
            DB::raw("SUM(CASE WHEN journey_plan_id > 0 THEN 1 ELSE 0 END) as total_journey"),
            DB::raw("SUM(CASE WHEN is_sequnece = '1' THEN 1 ELSE 0 END) as planed_journey"),
            DB::raw("SUM(CASE WHEN is_sequnece = '0' THEN 1 ELSE 0 END) as un_planed_journey"),
            'id', 'customer_id', 'journey_plan_id', 'salesman_id', 'latitude', 'longitude', 'start_time', 'end_time', 'is_sequnece', 'date', 'created_at'
        ])
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'customer.customerInfo'
            )
            ->groupBy('salesman_id', 'customer_visits.date', 'customer_id');

        if (!empty($salesman_ids)) {
            $customer_visit_query->whereIn('salesman_id', $salesman_ids);
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