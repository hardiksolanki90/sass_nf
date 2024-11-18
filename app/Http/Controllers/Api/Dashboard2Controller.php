<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerVisit;
use App\Model\Order;
use App\Model\SalesmanInfo;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Dashboard2Controller extends Controller
{
    private $organisation_id;

    public function index(Request $request)
    {
        if ($request->start_date && $request->end_date) {
            // $start_date = date('Y-m-d', strtotime('-1 days', strtotime($request->start_date)));
            // $end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
            $start_date = $request->start_date;
            $end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
        }

        if (!$request->start_date && $request->end_date) {
            $end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
            $start_date = date('Y-m-d', strtotime('-7 days', strtotime($end_date)));
            // $end_date = $end_date;

        }

        if ($request->start_date && !$request->end_date) {
            $start_date = $request->start_date;
            $end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
            $end_date = date('Y-m-d', strtotime('+7 days', strtotime($end_date)));
        }

        if (!$request->start_date && !$request->end_date) {
            $end_date = date('Y-m-d', strtotime('+1 days', strtotime(date('Y-m-d'))));
            $start_date = date('Y-m-d', strtotime('-7 days', strtotime($end_date)));
        }

        $this->organisation_id = $request->user()->organisation_id;

        if ($request->type == 'planogram') {
            $data = $this->planogram($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == 'soa') {
            $data = $this->soa($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == 'sos') {
            $data = $this->sos($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == "oos") {
            $data = $this->outofstock($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == "shelf-price") {
            $data = $this->selfPrice($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == "coverage") {
            $data = $this->coverage($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }

        if ($request->type == "active-outlet") {
            $data = $this->activeOutlets($request, $start_date, $end_date);
            return prepareResult(true, $data, [], "dashboard listing", $this->success);
        }
    }

    private function outofstock($request, $start_date, $end_date)
    {
        $filter1 = "";
        $filter2 = "";
        $filter3 = "";

        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {

            //---------------

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
            $channel = $request->channel_ids;
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->where('channel_id', $channel)
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
  WHERE channels.id = '$channelid') AS Salesman,
  tab2.planned AS planned,
  (SELECT COUNT(`item_id`) AS actual FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND created_at between '$start_date' AND '$end_date' AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS actual,round((((SELECT COUNT(`item_id`) AS actual FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.planned)*100),2) as percentage
FROM
  (SELECT COUNT(item_id)*$date_diff AS planned FROM `distribution_model_stock_details` distribution_id IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL) AND deleted_at IS NULL AND
 $filter
`distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid'))) $filter1 $filter2 $filter3) tab2
 ");



                    //pre($shelfprice);exit;
                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->percentage;
                }
            }
            //-----------
            //------------------
            $totalper = array_sum($outofstockper);
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            /*if (isset($request->brand_id) && $request->brand_id) {
                $data_query->where('items.brand_id', $request->brand_id);
            }
            if (isset($request->category_id) && $request->category_id) {
                $data_query->where('items.item_major_category_id', $request->category_id);
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_ids = implode(",", $request->item_ids);
                $data_query->whereIn('distribution_model_stock_details.item_id', $request->item_ids);
            }*/

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
  WHERE regions.id = '$regionid') AS Salesman,
  tab2.planned AS planned,
  (SELECT COUNT(`item_id`) AS actual FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND created_at between '$start_date' AND '$end_date' AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS actual,
round((((SELECT COUNT(`item_id`) AS actual FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid')) AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date'  and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.planned)*100),2) as percentage
FROM
  (SELECT COUNT(item_id)*$date_diff AS planned FROM `distribution_model_stock_details` WHERE distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL) AND deleted_at IS NULL AND
 $filter
`distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid'))) $filter1 $filter2 $filter3) tab2
 ");



                    //pre($shelfprice);exit;
                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->percentage;
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
            /*if (isset($request->brand_id) || isset($request->category_id) || sizeof($request->item_ids) >= 1)
			{
				$filter = "item_id IN (SELECT id FROM items WHERE ";
			}*/
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





            /*else{
				$item_ids = $request->item_ids;
				$data_query->whereIn('distribution_model_stock_details.item_id', $request->item_ids);
			}*/
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
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->where('status', 1)
                    ->get();
            }

            $salesman_ids = array();
            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            $customer_ids = array();
            if (count($salesman_ids)) {
                $customer_merchadiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_ids)->get();
                /* $customer_info = CustomerInfo::whereIn('merchandiser_id', $salesman_ids)->get();*/
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
                ->whereBetween('distributions.end_date', [$start_date, $end_date])
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
  WHERE users.id = '$salesmanid') AS Salesman,
  tab2.planned AS planned,
  (SELECT COUNT(`item_id`) AS actual FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND created_at between '$start_date' AND '$end_date' AND  `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 ) AS actual,round((((SELECT COUNT(`item_id`) AS actual  FROM `distribution_stocks` WHERE
`is_out_of_stock` = 1 AND salesman_id IN ('$salesmanid') AND `distribution_id` IN (SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  $filter1 $filter2 $filter3 )/tab2.planned)*100),2) as percentage
FROM
  (SELECT COUNT(item_id)*$date_diff AS planned FROM `distribution_model_stock_details` WHERE   distribution_id IN(SELECT id FROM `distributions` WHERE start_date <= '$start_date' and end_date >= '$end_date' AND deleted_at IS NULL)  AND deleted_at IS NULL AND
 $filter
`distribution_model_stock_id` IN (SELECT DISTINCT id FROM `distribution_model_stocks` WHERE customer_id IN (SELECT user_id FROM `customer_infos` WHERE `user_id` IN (select customer_id from customer_merchandisers where merchandiser_id IN ('$salesmanid')))) $filter1 $filter2 $filter3) tab2
 ");



                    //pre($shelfprice);exit;
                    $outofstockarr[] = $outofstockprice[0];
                    $outofstockper[] = $outofstockprice[0]->percentage;
                }
            }
        }
        $totalper = array_sum($outofstockper);
        $outofstock = new \stdClass();
        $outofstock->title = "Out of stock";
        $outofstock->percentage = round($totalper / $totalsal, 2);
        $outofstock->trends = $trends_data;
        $outofstock->comparison =  $this->comparison(collect($outofstockarr), 'actual');
        $outofstock->contribution = $outofstockarr;
        $outofstock->details = $outofstockarr;
        return $outofstock;
    }

    private function selfPrice($request, $start_date, $end_date)
    {
        $shelfpricearr = array();
        $totalsal = 0;
        $trends_data = array();
        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {

            $channel = $request->channel_ids;
            $customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')
                ->where('channel_id', $channel)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }



            $brand_query = "";
            $category_query = "";
            $item_query = "";
            $itemfilter1 = "";
            $itemfilter2 = "";
            $itemfilter3 = "";
            if (isset($request->brand_id) && $request->brand_id) {

                $brand_query = 'brand_id IN( ' . $request->brand_id . ') and';
                $itemfilter1 = 'AND item_id IN (SELECT id FROM `items` WHERE brand_id IN (' . $request->brand_id . '))';
            }
            if (isset($request->category_id) && $request->category_id) {

                $category_query = 'item_major_category_id IN (' . implode(",", $request->category_id) . ') and';
                $itemfilter2 = 'AND item_id IN (SELECT id FROM `items` WHERE item_major_category_id IN (' . implode(",", $request->category_id) . '))';
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_query = 'item_id IN (' . implode(",", $request->item_ids) . ') and';
                $itemfilter3 = 'AND item_id IN (' . implode(",", $request->item_ids) . ')';
            }
            /*elseif(is_array($request->item_ids) && sizeof($request->item_ids) = 1){
					  $item_query = 'item_id IN ('.$request->item_ids.') and';
				 }*/
            $totalper = 0;
            $totalsal = count($request->channel_ids);
            if (!empty($request->channel_ids)) {
                $shelfpricearr = array();
                $shelfpriceper = array();
                foreach ($request->channel_ids as $key => $channelid) {


                    $customer_ids2 = array();


                    $organisation_id = request()->user()->organisation_id;

                    $customer_info2 = CustomerInfo::where('channel_id', $channelid)->get();
                    if (count($customer_info2)) {
                        $customer_ids2 = $customer_info2->pluck('user_id')->toArray();
                    }

                    $shelfcuid = array();
                    //$planned
                    $planned = array();


                    foreach ($customer_ids2 as $key => $customer_id2) {

                        $customer_item = DB::select("SELECT sum(store_price) as storeprice FROM `portfolio_management_items` WHERE `portfolio_management_id` IN (SELECT DISTINCT id FROM
        `portfolio_managements` WHERE organisation_id = '$this->organisation_id' AND deleted_at IS NULL 
        AND start_date <= '$start_date' AND end_date >= '$end_date') $itemfilter1 $itemfilter2 $itemfilter3 AND  FIND_IN_SET ($customer_id2,`customer_id`)  AND deleted_at IS NULL");

                        if (!empty($customer_item)) :
                            $shelfcuid[] = $customer_item[0]->storeprice;
                        else :
                            $shelfcuid[] = 0;
                        endif;
                        //$shelfcuid[] = $customer_item->store_price;
                    }

                    //echo 
                    $planned[] = array_sum($shelfcuid);
                    $planned = $planned[0];





                    $organisation_id = request()->user()->organisation_id;
                    $shelfprice = DB::select("select (SELECT
    channels.name
  FROM
    channels
  WHERE channels.id = '$channelid') AS Salesman,
		$planned AS planned,
  (SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details` where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid'))
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      )))) AS actual,ROUND(( (SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details`  where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query customer_id IN (SELECT user_id FROM `customer_infos` WHERE `channel_id` IN ('$channelid'))
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      ))))/$planned)*100,2) as percentage ");



                    //pre($shelfprice);exit;
                    $shelfpricearr[] = $shelfprice[0];
                    $shelfpriceper[] = $shelfprice[0]->percentage;
                }
            }
            $totalper = array_sum($shelfpriceper);

            $trends_data = DB::table('pricing_checks')
                ->select(
                    DB::raw('pricing_checks.added_on'),
                    DB::raw('SUM(pricing_check_detail_prices.price) as Actual'),

                )
                ->join('pricing_check_details', 'pricing_check_details.pricing_check_id', '=', 'pricing_checks.id')
                ->join('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_detail_id', '=', 'pricing_check_details.id')
                ->where('pricing_checks.organisation_id', $this->organisation_id)
                ->whereIn('pricing_checks.customer_id', $customer_ids)
                ->whereBetween('pricing_checks.added_on', [$start_date, $end_date])
                ->whereNull('pricing_checks.deleted_at')
                ->groupBy('pricing_checks.added_on')
                ->get();
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {

            $region = $request->region_ids;
            $customer_infos = CustomerInfo::select('id', 'user_id', 'region_id')
                ->where('region_id', $region)
                ->get();

            $customer_ids = array();
            if (count($customer_infos)) {
                $customer_ids = $customer_infos->pluck('user_id')->toArray();
            }

            $brand_query = "";
            $category_query = "";
            $item_query = "";
            $itemfilter1 = "";
            $itemfilter2 = "";
            $itemfilter3 = "";
            if (isset($request->brand_id) && $request->brand_id) {

                $brand_query = 'brand_id IN( ' . $request->brand_id . ') and';
                $itemfilter1 = 'AND item_id IN (SELECT id FROM `items` WHERE brand_id IN (' . $request->brand_id . '))';
            }
            if (isset($request->category_id) && $request->category_id) {

                $category_query = 'item_major_category_id IN (' . implode(",", $request->category_id) . ') and';
                $itemfilter2 = 'AND item_id IN (SELECT id FROM `items` WHERE item_major_category_id IN (' . implode(",", $request->category_id) . '))';
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_query = 'item_id IN (' . implode(",", $request->item_ids) . ') and';
                $itemfilter3 = 'AND item_id IN (' . implode(",", $request->item_ids) . ') ';
            }
            /*elseif(is_array($request->item_ids) && sizeof($request->item_ids) = 1){
					  $item_query = 'item_id IN ('.$request->item_ids.') and';
				 }*/
            $totalper = 0;
            $totalsal = count($request->region_ids);
            if (!empty($request->region_ids)) {
                $shelfpricearr = array();
                $shelfpriceper = array();
                foreach ($request->region_ids as $key => $regionid) {

                    $customer_ids2 = array();


                    $organisation_id = request()->user()->organisation_id;

                    $customer_info2 = CustomerInfo::where('region_id', $regionid)->get();
                    if (count($customer_info2)) {
                        $customer_ids2 = $customer_info2->pluck('user_id')->toArray();
                    }

                    $shelfcuid = array();
                    //$planned
                    $planned = array();


                    foreach ($customer_ids2 as $key => $customer_id2) {

                        $customer_item = DB::select("SELECT sum(store_price) as storeprice FROM `portfolio_management_items` WHERE `portfolio_management_id` IN (SELECT DISTINCT id FROM
        `portfolio_managements` WHERE organisation_id = '$this->organisation_id' AND deleted_at IS NULL 
        AND start_date <= '$start_date' AND end_date >= '$end_date') $itemfilter1 $itemfilter2 $itemfilter3 AND  FIND_IN_SET ($customer_id2,`customer_id`)  AND deleted_at IS NULL");

                        if (!empty($customer_item)) :
                            $shelfcuid[] = $customer_item[0]->storeprice;
                        else :
                            $shelfcuid[] = 0;
                        endif;
                        //$shelfcuid[] = $customer_item->store_price;
                    }

                    //echo 
                    $planned[] = array_sum($shelfcuid);
                    $planned = $planned[0];



                    $organisation_id = request()->user()->organisation_id;
                    $shelfprice = DB::select("select (SELECT
    regions.region_name
  FROM
    regions
  WHERE regions.id = '$regionid') AS Salesman,
		$planned AS planned,
  (SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details`  where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid'))
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      )))) AS actual,Round(((SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details`  where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query customer_id IN (SELECT user_id FROM `customer_infos` WHERE `region_id` IN ('$regionid'))
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      ))))/$planned)*100,2) as percentage
");



                    //pre($shelfprice);exit;
                    $shelfpricearr[] = $shelfprice[0];
                    $shelfpriceper[] = $shelfprice[0]->percentage;
                }
            }
            $totalper = array_sum($shelfpriceper);

            $trends_data = DB::table('pricing_checks')
                ->select(
                    DB::raw('pricing_checks.added_on'),
                    DB::raw('SUM(pricing_check_detail_prices.price) as Actual'),

                )
                ->join('pricing_check_details', 'pricing_check_details.pricing_check_id', '=', 'pricing_checks.id')
                ->join('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_detail_id', '=', 'pricing_check_details.id')
                ->where('pricing_checks.organisation_id', $this->organisation_id)
                ->whereIn('pricing_checks.customer_id', $customer_ids)
                ->whereBetween('pricing_checks.added_on', [$start_date, $end_date])
                ->whereNull('pricing_checks.deleted_at')
                ->groupBy('pricing_checks.added_on')
                ->get();
        } else if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
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
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->where('status', 1)
                    ->get();
            }

            $salesman_ids = array();
            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            $customer_ids = array();
            if (count($salesman_ids)) {
                $customer_merchadiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_ids)->get();
                /* $customer_info = CustomerInfo::whereIn('merchandiser_id', $salesman_ids)->get();*/
                if (count($customer_merchadiser)) {
                    $customer_ids = $customer_merchadiser->pluck('customer_id')->toArray();
                }
            }
            $brand_query = "";
            $category_query = "";
            $item_query = "";
            $itemfilter1 = "";
            $itemfilter2 = "";
            $itemfilter3 = "";
            if (isset($request->brand_id) && $request->brand_id) {

                $brand_query = 'brand_id IN( ' . $request->brand_id . ') and';
                $itemfilter1 = 'AND item_id IN (SELECT id FROM `items` WHERE brand_id IN (' . $request->brand_id . '))';
            }
            if (isset($request->category_id) && $request->category_id) {

                $category_query = 'item_major_category_id IN (' . implode(",", $request->category_id) . ') and';
                $itemfilter2 = 'AND item_id IN (SELECT id FROM `items` WHERE item_major_category_id IN (' . implode(",", $request->category_id) . '))';
            }
            if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                $item_query = 'item_id IN (' . implode(",", $request->item_ids) . ') and';
                $itemfilter3 = 'AND item_id IN (' . implode(",", $request->item_ids) . ')';
            }
            /*elseif(is_array($request->item_ids) && sizeof($request->item_ids) = 1){
					  $item_query = 'item_id IN ('.$request->item_ids.') and';
				 }*/
            $totalper = 0;
            $totalsal = count($salesman_ids);
            if (!empty($salesman_ids)) {
                $shelfpricearr = array();
                $shelfpriceper = array();

                foreach ($salesman_ids as $key => $salesmanid) {
                    $customer_ids2 = array();


                    $organisation_id = request()->user()->organisation_id;


                    $customer_merchadiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_ids)->get();
                    /* $customer_info = CustomerInfo::whereIn('merchandiser_id', $salesman_ids)->get();*/
                    if (count($customer_merchadiser)) {
                        $customer_ids2 = $customer_merchadiser->pluck('customer_id')->toArray();
                    }

                    $shelfcuid = array();
                    //$planned
                    $planned = array();


                    foreach ($customer_ids2 as $key => $customer_id2) {

                        $customer_item = DB::select("SELECT sum(store_price) as storeprice FROM `portfolio_management_items` WHERE `portfolio_management_id` IN (SELECT DISTINCT id FROM
        `portfolio_managements` WHERE organisation_id = '$this->organisation_id' AND deleted_at IS NULL 
        AND start_date <= '$start_date' AND end_date >= '$end_date') $itemfilter1 $itemfilter2 $itemfilter3 AND  FIND_IN_SET ($customer_id2,`customer_id`)  AND deleted_at IS NULL");

                        if (!empty($customer_item)) :
                            $shelfcuid[] = $customer_item[0]->storeprice;
                        else :
                            $shelfcuid[] = 0;
                        endif;
                        //$shelfcuid[] = $customer_item->store_price;
                    }

                    //echo 
                    $planned[] = array_sum($shelfcuid);
                    $planned = $planned[0];

                    $shelfprice = DB::select("select (SELECT
    users.firstname  FROM
    users
		WHERE users.id = $salesmanid) AS Salesman,
		$planned AS planned,
  (SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details`  where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query `salesman_id` = $salesmanid
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      )))) AS actual, round((((SELECT
    SUM(price)
  FROM
    `pricing_check_detail_prices`
  WHERE  `pricing_check_detail_id` IN
    (select distinct id from `pricing_check_details`  where $item_query $category_query pricing_check_id in (SELECT
      id
    FROM
      `pricing_checks`
    WHERE $brand_query `salesman_id` = $salesmanid
      AND deleted_at IS NULL
      AND (
        added_on BETWEEN '$start_date' AND '$end_date'
      ))))/$planned)*100),2) as percentage
");



                    //pre($shelfprice);exit;
                    $shelfpricearr[] = $shelfprice[0];
                    $shelfpriceper[] = $shelfprice[0]->percentage;
                }
                $totalper = array_sum($shelfpriceper);
            }






            $trends_data = DB::table('pricing_checks')
                ->select(
                    DB::raw('pricing_checks.added_on'),
                    DB::raw('SUM(pricing_check_detail_prices.price) as Actual'),

                )
                ->join('pricing_check_details', 'pricing_check_details.pricing_check_id', '=', 'pricing_checks.id')
                ->join('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_detail_id', '=', 'pricing_check_details.id')
                ->where('pricing_checks.organisation_id', $this->organisation_id)
                ->whereIn('pricing_checks.salesman_id', $salesman_ids)
                ->whereBetween('pricing_checks.added_on', [$start_date, $end_date])
                ->whereNull('pricing_checks.deleted_at')
                ->groupBy('pricing_checks.added_on')
                ->get();
        }
        if ($totalsal == 0) {
            $percentage = 0;
        } else {
            $percentage = round($totalper / $totalsal, 2);
        }

        $shelfprice = new \stdClass();
        $shelfprice->title = "Shelf-Price";
        $shelfprice->percentage = $percentage;
        $shelfprice->trends = $trends_data;
        $shelfprice->comparison =  $this->comparison(collect($shelfpricearr), 'actual');
        $shelfprice->contribution = $shelfpricearr;
        $shelfprice->details = $shelfpricearr;
        return $shelfprice;
    }

    private function sos($request, $start_date, $end_date)
    {

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
                    DB::raw('DISTINCT channels.name as Salesman'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as Actual'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as Planned'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as percentage')
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
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
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
                    DB::raw('DISTINCT regions.region_name as Salesman'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as Actual'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as Planned'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as percentage')
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
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
        } else {
            if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $request->salesman_ids)
                    ->get();
            } else if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $supervisor = $request->supervisor;
                $salesman_infos = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor')
                    ->whereIn('salesman_supervisor', $supervisor)
                    ->where('status', 1)
                    ->groupBy('salesman_supervisor')
                    ->get();
            }
            if (is_array($request->nsm) && sizeof($request->nsm) >= 1) {
                $getSalesman = array();
                foreach ($request->nsm as $nsm) {
                    $getSalesman[] = getSalesman(false, $nsm);
                }
                $salesman_ids = Arr::collapse($getSalesman);
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $salesman_ids)
                    ->get();
            } else if (is_array($request->asm) && sizeof($request->asm) >= 1) {
                $getSalesman = array();
                foreach ($request->asm as $asm) {
                    $getSalesman[] = getSalesman(false, $asm);
                }
                $salesman_ids = Arr::collapse($getSalesman);
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->whereIn('user_id', $salesman_ids)
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
                    DB::raw('DISTINCT users.firstname as Salesman'),
                    DB::raw('(SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) as Actual'),
                    DB::raw('(SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) as Planned'),
                    DB::raw('ROUND((SUM(s_o_s_our_brands.catured_block) + SUM(s_o_s_our_brands.catured_shelves)) / (SUM(s_o_s.block_store) + SUM(s_o_s.no_of_shelves)) * 100, 2) as percentage')
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
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
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

    private function soa($request, $start_date, $end_date)
    {
        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customerInfos = CustomerInfo::select('id', 'channel_id', 'user_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $customer_ids = array();
            if (count($customerInfos)) {
                $customer_ids = $customerInfos->pluck('user_id')->toArray();
            }

            $trends_data_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DATE(share_of_assortments.created_at) as date'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as value'),
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id')
                ->whereIn('share_of_assortments.customer_id', $customer_ids);
            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }
            $trends_data = $trends_data_query->where('share_of_assortments.organisation_id', $this->organisation_id)
                ->whereNull('share_of_assortments.deleted_at')
                ->whereIn('share_of_assortments.customer_id', $customer_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('share_of_assortments.created_at')
                ->get();

            $customer_details_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DISTINCT channels.name as Salesman'),
                    DB::raw('SUM(share_of_assortments.no_of_sku) as Planned'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as Actual'),
                    DB::raw('ROUND(SUM(share_of_assortment_our_brands.captured_sku) / SUM(share_of_assortments.no_of_sku) * 100, 2) as percentage')
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'share_of_assortments.customer_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                ->join('users', 'users.id', '=', 'share_of_assortments.salesman_id');
            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }
            $customer_details = $customer_details_query->where('share_of_assortments.organisation_id', $this->organisation_id)
                ->whereNull('share_of_assortments.deleted_at')
                ->whereIn('share_of_assortments.customer_id', $customer_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('channels.name')
                // ->orderBy('created_at')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customerInfos = CustomerInfo::select('id', 'region_id', 'user_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            $customer_ids = array();
            if (count($customerInfos)) {
                $customer_ids = $customerInfos->pluck('user_id')->toArray();
            }

            $trends_data_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DATE(share_of_assortments.created_at) as date'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as value'),
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id')
                ->whereIn('share_of_assortments.customer_id', $customer_ids);

            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }

            $trends_data = $trends_data_query->where('share_of_assortments.organisation_id', $this->organisation_id)
                ->whereNull('share_of_assortments.deleted_at')
                ->whereIn('share_of_assortments.customer_id', $customer_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('share_of_assortments.created_at')
                ->get();

            $customer_details_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DISTINCT regions.region_name as Salesman'),
                    DB::raw('SUM(share_of_assortments.no_of_sku) as Planned'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as Actual'),
                    DB::raw('ROUND(SUM(share_of_assortment_our_brands.captured_sku) / SUM(share_of_assortments.no_of_sku) * 100, 2) as percentage')
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'share_of_assortments.customer_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                ->join('users', 'users.id', '=', 'share_of_assortments.salesman_id');
            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }
            $customer_details = $customer_details_query->where('share_of_assortments.organisation_id', $this->organisation_id)
                ->whereNull('share_of_assortments.deleted_at')
                ->whereIn('share_of_assortments.customer_id', $customer_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('regions.region_name')
                // ->orderBy('created_at')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
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

            $trends_data_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DATE(share_of_assortments.created_at) as date'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as value'),
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id');
            if (isset($request->brand) && $request->brand) {
                $trends_data_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $trends_data_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }

            $trends_data = $trends_data_query->whereIn('share_of_assortments.salesman_id', $salesman_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('share_of_assortments.created_at')
                ->get();

            $customer_details_query = DB::table('share_of_assortments')
                ->select(
                    DB::raw('DISTINCT users.firstname as Salesman'),
                    DB::raw('SUM(share_of_assortments.no_of_sku) as Planned'),
                    DB::raw('SUM(share_of_assortment_our_brands.captured_sku) as Actual'),
                    DB::raw('ROUND(SUM(share_of_assortment_our_brands.captured_sku) / SUM(share_of_assortments.no_of_sku) * 100, 2) as percentage')
                )
                ->join('share_of_assortment_our_brands', 'share_of_assortment_our_brands.share_of_assortment_id', '=', 'share_of_assortments.id')
                ->join('users', 'users.id', '=', 'share_of_assortments.salesman_id');

            if (isset($request->brand) && $request->brand) {
                $customer_details_query->where('share_of_assortment_our_brands.brand_id', $request->brand);
            }

            if (isset($request->category) && $request->category) {
                $customer_details_query->where('share_of_assortment_our_brands.item_major_category_id', $request->category);
            }

            $customer_details = $customer_details_query->where('users.organisation_id', $this->organisation_id)
                ->whereIn('share_of_assortments.salesman_id', $salesman_ids)
                ->whereBetween('share_of_assortments.created_at', [$start_date, $end_date])
                ->groupBy('share_of_assortments.salesman_id')
                ->get();

            $percentage = '0%';
            if (count($customer_details)) {
                $actual = $customer_details->pluck('Actual')->toArray();
                $planned = $customer_details->pluck('Planned')->toArray();
                $percentage = number_format(round(array_sum($actual) / array_sum($planned) * 100, 2), 2);
            }

            $comparison = $this->comparison($customer_details, "Actual");
        }

        $contribution = $this->comparisonSet($customer_details);

        $soa = new \stdClass();
        $soa->title = "SOA";
        $soa->percentage = $percentage;
        $soa->trends = $trends_data;
        $soa->comparison = $comparison;
        $soa->contribution = $contribution;
        $soa->details = $customer_details;
        return $soa;
    }

    private function planogram($request, $start_date, $end_date)
    {
        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
            $customerInfos = CustomerInfo::select('id', 'channel_id', 'user_id')
                ->whereIn('channel_id', $request->channel_ids)
                ->get();

            $customer_ids = array();
            if (count($customerInfos)) {
                $customer_ids = $customerInfos->pluck('user_id')->toArray();
            }

            $planogram_distributions = DB::table('planogram_distributions')
                ->select(
                    DB::raw('DISTINCT users.id as id'),
                    DB::raw('users.firstname as name'),
                    DB::raw('COUNT(DISTINCT planogram_distributions.id) as totalTask'),
                    DB::raw('COUNT(DISTINCT planogram_posts.distribution_id) as completedTask')
                )
                ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
                ->join('users', 'users.id', '=', 'planogram_distributions.customer_id')
                ->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
                ->where('planograms.status', 1)
                ->where('planograms.start_date', '<=', $start_date)
                ->where('planograms.end_date', '>=', $end_date)
                ->whereIn('planogram_distributions.customer_id', $customer_ids)
                ->where('planograms.organisation_id', $this->organisation_id)
                ->groupBy('users.id', 'planogram_posts.distribution_id')
                ->get();

            $percentage = 0 . '%';
            $diff = date_diff(date_create($request->start_date), date_create($request->end_date));
            $date_diff = $diff->format("%a");

            if (count($planogram_distributions)) {
                $totalTask = $planogram_distributions->pluck('totalTask')->toArray();
                $completedTask = $planogram_distributions->pluck('completedTask')->toArray();

                if (array_sum($totalTask) != 0 && array_sum($completedTask) != 0) {
                    $total_task = $date_diff + array_sum($totalTask);
                    $percentage = round(array_sum($completedTask) / $total_task * 100, 2) . '%';
                }
            }

            $trends_data = DB::table('planogram_posts')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw("COUNT(planogram_posts.id) as value")
                )
                ->whereIn('customer_id', $customer_ids)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('created_at')
                ->get();

            $customer_details = DB::table('planogram_posts')
                ->select(
                    DB::raw('DISTINCT channels.name as Salesman'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) * ' . $date_diff . ') AS palned'),
                    DB::raw('COUNT(DISTINCT planogram_posts.id) as Actual'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) / ROUND(COUNT(DISTINCT planogram_posts.id) *' . $date_diff . ') * 100, 2) AS total_planogram')
                )
                ->join('planogram_distributions', 'planogram_distributions.customer_id', '=', 'planogram_posts.customer_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'planogram_posts.customer_id')
                ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
                // ->join('users', 'users.id', '=', 'planogram_posts.salesman_id')
                ->where('channels.organisation_id', $this->organisation_id)
                ->whereIn('planogram_posts.customer_id', $customer_ids)
                ->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
                // ->groupBy('planogram_distributions.planogram_customer_id')
                // ->orderBy('created_at')
                ->get();

            $comparison = $this->comparison($customer_details, "Actual");
            $contribution = $this->comparisonSet($customer_details);
        } else if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
            $customerInfos = CustomerInfo::select('id', 'region_id', 'user_id')
                ->whereIn('region_id', $request->region_ids)
                ->get();

            $customer_ids = array();
            if (count($customerInfos)) {
                $customer_ids = $customerInfos->pluck('user_id')->toArray();
            }

            $planogram_distributions = DB::table('planogram_distributions')
                ->select(
                    DB::raw('DISTINCT users.id as id'),
                    DB::raw('users.firstname as name'),
                    DB::raw('COUNT(DISTINCT planogram_distributions.id) as totalTask'),
                    DB::raw('COUNT(DISTINCT planogram_posts.distribution_id) as completedTask')
                )
                ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
                ->join('users', 'users.id', '=', 'planogram_distributions.customer_id')
                ->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
                ->where('planograms.status', 1)
                ->where('planograms.start_date', '<=', $start_date)
                ->where('planograms.end_date', '>=', $end_date)
                ->whereIn('planogram_distributions.customer_id', $customer_ids)
                ->where('planograms.organisation_id', $this->organisation_id)
                ->groupBy('users.id', 'planogram_posts.distribution_id')
                ->get();

            $percentage = 0 . '%';
            $diff = date_diff(date_create($request->start_date), date_create($request->end_date));
            $date_diff = $diff->format("%a");

            if (count($planogram_distributions)) {
                $totalTask = $planogram_distributions->pluck('totalTask')->toArray();
                $completedTask = $planogram_distributions->pluck('completedTask')->toArray();

                if (array_sum($totalTask) != 0 && array_sum($completedTask) != 0) {
                    $total_task = $date_diff + array_sum($totalTask);
                    $percentage = round(array_sum($completedTask) / $total_task * 100, 2) . '%';
                }
            }

            $trends_data = DB::table('planogram_posts')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw("COUNT(planogram_posts.id) as value")
                )
                ->whereIn('customer_id', $customer_ids)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('created_at')
                ->get();

            $customer_details = DB::table('planogram_posts')
                ->select(
                    DB::raw('DISTINCT regions.region_name as Salesman'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) * ' . $date_diff . ') AS palned'),
                    DB::raw('COUNT(DISTINCT planogram_posts.id) as Actual'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) / ROUND(COUNT(DISTINCT planogram_posts.id) *' . $date_diff . ') * 100, 2) AS total_planogram')
                )
                ->join('planogram_distributions', 'planogram_distributions.customer_id', '=', 'planogram_posts.customer_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'planogram_posts.customer_id')
                ->join('regions', 'regions.id', '=', 'customer_infos.region_id')
                // ->join('users', 'users.id', '=', 'planogram_posts.salesman_id')
                ->where('regions.organisation_id', $this->organisation_id)
                ->whereIn('planogram_posts.customer_id', $customer_ids)
                ->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
                // ->groupBy('planogram_distributions.planogram_customer_id')
                // ->orderBy('created_at')
                ->get();

            $comparison = $this->comparison($customer_details, "Actual");
            $contribution = $this->comparisonSet($customer_details);
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
                //     ->groupBy('salesman_supervisor')
                //     ->get();
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

            $salesman_ids = array();
            if (count($salesman_infos)) {
                $salesman_ids = $salesman_infos->pluck('user_id')->toArray();
            }

            $customerMerchandiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_ids)
                ->get();

            // $customerInfos = CustomerInfo::select('id', 'merchandiser_id', 'user_id')
            //     ->whereIn('merchandiser_id', $salesman_ids)
            //     ->get();

            $customer_ids = array();
            if (count($customerMerchandiser)) {
                $customer_ids = $customerMerchandiser->pluck('customer_id')->toArray();
            }

            // $customer_ids = array();
            // if (count($customerInfos)) {
            //     $customer_ids = $customerInfos->pluck('user_id')->toArray();
            // }

            $planogram_distributions = DB::table('planogram_distributions')
                ->select(
                    DB::raw('DISTINCT users.id as id'),
                    DB::raw('users.firstname as name'),
                    DB::raw('COUNT(DISTINCT planogram_distributions.id) as totalTask'),
                    DB::raw('COUNT(DISTINCT planogram_posts.distribution_id) as completedTask')
                )
                ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
                ->join('users', 'users.id', '=', 'planogram_distributions.customer_id')
                ->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
                ->where('planograms.status', 1)
                ->where('planograms.start_date', '<=', $start_date)
                ->where('planograms.end_date', '>=', $end_date)
                ->whereIn('planogram_distributions.customer_id', $customer_ids)
                ->where('planograms.organisation_id', $this->organisation_id)
                ->groupBy('users.id', 'planogram_posts.distribution_id')
                ->get();

            $percentage = 0 . '%';
            $diff = date_diff(date_create($start_date), date_create($end_date));
            $date_diff = $diff->format("%a");

            /*if (count($planogram_distributions)) {
                $totalTask = $planogram_distributions->pluck('totalTask')->toArray();
                $completedTask = $planogram_distributions->pluck('completedTask')->toArray();
                if (array_sum($totalTask) != 0 && array_sum($completedTask) != 0) {
                    $total_task = $date_diff + array_sum($totalTask);
                    $percentage = round(array_sum($completedTask) / $total_task * 100, 2);
                }
            }*/

            $trends_data = DB::table('planogram_posts')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw("COUNT(planogram_posts.id) as value")
                )
                ->whereIn('customer_id', $customer_ids)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->groupBy('date')
                ->orderBy('created_at')
                ->get();
            //---------------

            $planogramarr = array();
            $planogramper = array();
            $filter = "";
            $totalper = 0;
            $totalsal = count($salesman_ids);
            if (!empty($salesman_ids)) {

                foreach ($salesman_ids as $key => $salesmanid) {

                    $organisation_id = request()->user()->organisation_id;
                    $planogram_detail = DB::select("Select (SELECT
    users.firstname  FROM  users  WHERE users.id = '$salesmanid') AS Salesman,tab2.planned as planned,tab2.actual as Actual,round((tab2.actual/tab2.planned)*100,2) as percentage from(Select tab.planned,(select count(id) from `planogram_posts` where 
salesman_id in ($salesmanid) 
and `planogram_id` in (Select `id` from `planograms` where `organisation_id` = $organisation_id and `start_date` <= '$start_date' and `end_date` >='$end_date')
group by salesman_id) as actual
from( select count(id)*$date_diff as planned from `planogram_distributions` where 
customer_id in (select customer_id from `customer_merchandisers` where `merchandiser_id` in ($salesmanid))
AND `planogram_id` IN (SELECT `id` FROM `planograms` WHERE `organisation_id` = $organisation_id AND `start_date` <= '$start_date' AND `end_date` >='$end_date')
)tab)tab2");



                    //pre($shelfprice);exit;
                    $planogramarr[] = $planogram_detail[0];
                    $planogramper[] = $planogram_detail[0]->percentage;
                }
            }
            $totalper = array_sum($planogramper);
            $customer_details = collect($planogramarr);
            //---------------
            /* $customer_details = DB::table('planogram_posts')
                ->select(
                    DB::raw('DISTINCT users.firstname as Salesman'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) * ' . $date_diff . ') AS palned'),
                    DB::raw('COUNT(DISTINCT planogram_posts.id) as Actual'),
                    DB::raw('ROUND(COUNT(DISTINCT planogram_distributions.id) / ROUND(COUNT(DISTINCT planogram_posts.id) *' . $date_diff . ') * 100, 2) AS total_planogram')
                )
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'planogram_posts.salesman_id')
                ->join('planogram_distributions', 'planogram_distributions.customer_id', '=', 'planogram_posts.customer_id')
                ->join('users', 'users.id', '=', 'planogram_posts.salesman_id')
                ->where('users.organisation_id', $this->organisation_id)
                ->whereIn('planogram_posts.salesman_id', $salesman_ids)
                ->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
                // ->groupBy('planogram_distributions.planogram_customer_id')
                // ->orderBy('created_at')
                ->get();*/

            $comparison = $this->comparison($customer_details, "Actual");
            $contribution = $this->comparisonSet($customer_details);
        }

        $planogram = new \stdClass();
        $planogram->title = "Plan-o-gram";
        // $planogram->text = "Average # of visits made by a sales man in a day";
        $planogram->percentage = round($totalper / $totalsal, 2);
        $planogram->trends = $trends_data;
        $planogram->comparison = $comparison;
        $planogram->contribution = $contribution;
        $planogram->details = $customer_details;
        return $planogram;
    }

    private function coverage($request, $start_date, $end_date)
    {
        if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
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
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->where('status', 1)
                    ->get();
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
                ->whereIn('customer_id', $all_customers)
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

            $trends_data = DB::table('salesman_infos')
                ->select('customer_visits.added_on as date', DB::raw('count(customer_visits.id) as value'))
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                // ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_merchandisers.customer_id', $all_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                // ->groupBy('customer_visits.salesman_id')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->groupBy('customer_visits.date')
                ->orderBy('customer_visits.added_on')
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $sname = "salesman_infos.salesman_supervisor as name";
                $sgBy = 'salesman_infos.salesman_supervisor';
            } else {
                $sname = "users.firstname as name";
                $sgBy = 'users.id';
            }

            $comparison = DB::table('salesman_infos')
                ->select(
                    $sname,
                    DB::raw('count(customer_visits.id) as steps')
                )
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                // ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereIn('customer_merchandisers.customer_id', $all_customers)
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->groupBy($sgBy)
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $name = "DISTINCT salesman_infos.salesman_supervisor as RES";
                $gBy = 'salesman_infos.salesman_supervisor';
            } else {
                $name = "DISTINCT users.firstname as RES";
                $gBy = 'customer_visits.salesman_id';
            }

            $customer_details = DB::table('salesman_infos')->select(
                DB::raw($name),
                DB::raw('COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ' as TOTAL_OUTLETS'),
                DB::raw('COUNT(DISTINCT customer_visits.id) AS VISITS'),
                // DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
                DB::raw('ROUND(COUNT(DISTINCT customer_visits.id) / (COUNT(DISTINCT customer_merchandisers.customer_id) * ' . $date_diff . ') * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_merchandizers', 'customer_merchandizers.merchandizer_id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandizers.user_id', 'left')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                // ->join('customer_visits', 'customer_infos.user_id', '=', 'customer_visits.customer_id')
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_visits', 'customer_merchandisers.customer_id', '=', 'customer_visits.customer_id')
                ->where('customer_visits.shop_status', 'open')
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.date', [$start_date, $end_date])
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
                // ->where('customer_visits.completed_task', '!=', 0)
                ->whereNull('customer_visits.reason')
                ->whereBetween('customer_visits.added_on', [$start_date, $end_date])
                ->whereIn('customer_visits.customer_id', $all_customers)
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
        if (is_array($request->channel_id) && sizeof($request->channel_id) >= 1) {
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
                $salesman_infos = SalesmanInfo::select('id', 'user_id')
                    ->where('status', 1)
                    ->get();
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

            $trends_data = DB::table('salesman_infos')->select('orders.created_at as date', DB::raw('count(orders.id) as value'))
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                // ->join('customer_merchandizers', 'customer_merchandizers.user_id', '=', 'customer_infos.user_id')
                // ->join('salesman_infos', 'salesman_infos.user_id', '=', 'customer_merchandizers.merchandizer_id')
                // ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('orders', 'orders.customer_id', '=', 'customer_infos.user_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->orderBy('orders.created_at')
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $sname = "salesman_infos.salesman_supervisor as name";
                $sgBy = 'salesman_infos.salesman_supervisor';
            } else {
                $sname = "users.firstname as name";
                $sgBy = 'users.id';
            }

            $comparison = DB::table('salesman_infos')->select(
                $sname,
                DB::raw('count(orders.id) as steps')
            )
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereIn('orders.customer_id', $orders_customers)
                ->where('salesman_infos.organisation_id', $this->organisation_id)
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->groupBy($sgBy)
                // ->groupBy('customer_infos.merchandiser_id')
                // ->groupBy('customer_visits.date')
                // ->groupBy('customer_merchandizers.merchandizer_id')
                ->get();

            if (is_array($request->supervisor) && sizeof($request->supervisor) >= 1) {
                $name = "DISTINCT salesman_infos.salesman_supervisor as RES";
                $gBy = 'salesman_infos.salesman_supervisor';
            } else {
                $name = "DISTINCT users.firstname as RES";
                $gBy = 'users.id';
            }

            $customer_details = DB::table('salesman_infos')->select(
                DB::raw($name),
                DB::raw('COUNT(DISTINCT customer_infos.id) as TOTAL_OUTLETS'),
                DB::raw('COUNT(DISTINCT orders.id) AS VISITS'),
                DB::raw('ROUND(COUNT(DISTINCT orders.id) / ' . $no_of_customers . ' * 100, 2) AS EXECUTION')
            )
                // ->join('users', 'salesman_infos.user_id', '=', 'users.id')
                // ->join('customer_infos', 'customer_infos.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('users', 'users.id', '=', 'salesman_infos.user_id')
                ->join('customer_merchandisers', 'customer_merchandisers.merchandiser_id', '=', 'salesman_infos.user_id')
                ->join('customer_infos', 'customer_infos.user_id', '=', 'customer_merchandisers.customer_id', 'left')
                ->join('orders', 'customer_infos.user_id', '=', 'orders.customer_id')
                ->whereBetween('orders.created_at', [$start_date, $end_date])
                ->whereIn('customer_infos.user_id', $orders_customers)
                ->where('users.organisation_id', $this->organisation_id)
                ->groupBy($gBy)
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
                // ->where('customer_visits.shop_status', 'open')
                // ->where('customer_visits.completed_task', '!=', 0)
                // ->whereNull('customer_visits.reason')
                ->whereBetween('orders.created_at', [$start_date, $end_date])
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
                            'name' => $details->Salesman,
                            'steps' => $details->$variable,
                            'all_actual' => $sum_actual,
                            'percentage' => number_format(ROUND($details->$variable / $sum_actual * 100, 2), 2)
                        );
                    } else {
                        $comparison[] = array(
                            'name' => $details->Salesman,
                            'steps' => 0,
                            'all_actual' => $sum_actual,
                            'percentage' => 0
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
                    'name' => $detail->Salesman,
                    'steps' => $detail->Actual
                );
                $comparison[] = $data;
            }
        }
        return $comparison;
    }

    private function salesmanCustomers($request)
    {
        $salesman_ids = array();
        if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
            $salesman_ids = $request->salesman_ids;
        }

        $salesman_info_query = SalesmanInfo::select('id', 'user_id');
        if (count($salesman_ids)) {
            $salesman_info_query->whereIn('user_id', $request->salesman_ids);
        }
        $salesman_info = $salesman_info_query->get();

        $salesman_user_ids = array();
        if (count($salesman_info)) {
            $salesman_user_ids = $salesman_info->pluck('user_id')
                ->toArray();
        }

        $customer_merchandiser = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
            ->whereIn('merchandiser_id', $salesman_user_ids)
            ->get();

        $all_customers = array();
        if (count($customer_merchandiser)) {
            $all_customers = $customer_merchandiser->pluck('customer_id')->toArray();
        }
        return $all_customers;
    }
}
