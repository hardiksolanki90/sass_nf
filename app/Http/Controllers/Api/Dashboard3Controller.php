<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Channel;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerVisit;
use App\Model\Order;
use App\Model\Reason;
use App\Model\Region;
use App\Model\SalesmanInfo;
use App\Model\Trip;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanDay;
use App\Model\JourneyPlanCustomer;
use App\Model\JourneyPlanWeek;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;
use DateTime;
use Illuminate\Support\Facades\Cache;

class Dashboard3Controller extends Controller
{
	private $organisation_id;

	public function index(Request $request)
	{
		if ($request->start_date && $request->end_date) {
			$start_date = $request->start_date;
			//$end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
			$end_date = $request->end_date;
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

		$coverage = $this->coverage($request, $start_date, $end_date);

		//$data = $coverage


		return prepareResult(true, $coverage, [], "dashboard listing", $this->success);
	}

	private function coverage($request, $start_date, $end_date)
	{

		if ($request->cache == true) {
			Cache::get('dashboard_cache');
		}


		if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
			$customer_infos = CustomerInfo::select('id', 'user_id', 'channel_id')->whereIn('channel_id', $request->channel_ids)
				->get();

			$all_customers = array();
			if (count($customer_infos)) {
				$all_customers = $customer_infos->pluck('user_id')
					->toArray();
			}
			$customer_visits = CustomerVisit::select('id', 'route_id', 'trip_id', 'customer_id', 'salesman_id', 'journey_plan_id', 'latitude', 'longitude', 'shop_status', 'start_time', 'end_time', 'is_sequnece', 'date', 'reason', 'added_on')->with('customer:id,firstname,lastname', 'salesman:id,firstname,lastname')
				->where('shop_status', 'open')
				->whereNull('reason')
				->whereBetween('added_on', [$start_date, $end_date])->whereIn('customer_id', $all_customers)
				// ->groupBy('trip_id')
				->get();

			$diff = date_diff(date_create($start_date), date_create($end_date));
			$date_diff = $diff->format("%a") + 1;
			$date_diff_sec = $date_diff * 86400;;
			$no_of_visits = count($customer_visits);
			$no_of_customers = count($all_customers) * $date_diff;
			$customer_dashboard1 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as coverage'));
			$customer_dashboard1->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard1->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->where('customer_visits.date', '>=', $end_date)
					->where('customer_visits.date', '<=', $start_date);
			});


			/* $customer_dashboard1->where('shop_status', 'open');
			 $customer_dashboard1->whereNull('reason');
			 $customer_dashboard1->whereBetween('date', [$start_date, $end_date]);*/
			$customer_dashboard1->whereIn('users.id', $all_customers);
			$customer_dashboard1->groupBy('users.id');

			$percentage1 = $customer_dashboard1->get();

			//Active OUTLETS
			$customer_dashboard2 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(orders.customer_id)=0,0,round((count(orders.customer_id)/' . $date_diff . ')*100,2)) as activeoutlet'));
			$customer_dashboard2->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard2->leftjoin("customer_visits", function ($join) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id");
			});
			$customer_dashboard2->leftJoin("orders", function ($join) use ($end_date, $start_date) {
				$join->on("orders.id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			// $customer_dashboard2->where('shop_status', 'open');
			/*$customer_dashboard2->whereNull('customer_visits.reason');
			  $customer_dashboard2->where('completed_task', '!=', 0);*/
			// $customer_dashboard2->whereBetween('date', [$start_date, $end_date]);
			$customer_dashboard2->whereIn('users.id', $all_customers);
			$customer_dashboard2->groupBy('users.id');
			$percentage2 = $customer_dashboard2->get();

			// Execution
			$customer_dashboard3 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((sum(customer_visits.completed_task)/sum(customer_visits.total_task))*100,2)) as execution'));
			$customer_dashboard3->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard3->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			// $customer_dashboard3->whereNull('customer_visits.reason');
			// $customer_dashboard3->where('completed_task', '!=', 0);
			// $customer_dashboard3->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard3->whereIn('users.id', $all_customers);
			$customer_dashboard3->groupBy('users.id');
			$percentage3 = $customer_dashboard3->get();
			//Visit per day
			// visitFrequency
			$customer_dashboard4 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((count(customer_visits.customer_id)/count(distinct customer_visits.salesman_id)),2)) as visitfrequency'));

			$customer_dashboard4->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard4->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->where('customer_visits.date', '>=', $end_date)
					->where('customer_visits.date', '<=', $start_date);
			});
			// $customer_dashboard4->whereNull('customer_visits.reason');
			//$customer_dashboard4->where('completed_task', '!=', 0);
			// $customer_dashboard4->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard4->whereIn('users.id', $all_customers);
			$customer_dashboard4->groupBy('users.id');
			$percentage4 = $customer_dashboard4->get();
			//Visitperday
			$customer_dashboard5 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round(count(customer_visits.customer_id)/' . $date_diff . ')) as visitperday'));
			$customer_dashboard5->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard5->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			//$customer_dashboard5->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard5->whereIn('users.id', $all_customers);
			$customer_dashboard5->groupBy('users.id');
			$percentage5 = $customer_dashboard5->get();
			//Time spent
			$customer_dashboard6 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,SEC_TO_TIME(round(SUM(TIME_TO_SEC(customer_visits.visit_total_time))/' . $date_diff . '))) as timeSpent'));
			$customer_dashboard6->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard6->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
				//->whereBetween('customer_visits.date', array($start_date,$end_date));
			});


			$customer_dashboard6->whereIn('users.id', $all_customers);
			$customer_dashboard6->groupBy('users.id');
			$percentage6 = $customer_dashboard6->get();
			//Route compliance
			$customer_dashboard7 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as routeCompliance'));
			$customer_dashboard7->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard7->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				//$end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->where('is_sequnece', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			//$customer_dashboard7->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard7->whereIn('users.id', $all_customers);
			$customer_dashboard7->groupBy('users.id');
			$percentage7 = $customer_dashboard7->get();
			//--Shelf price
			$customer_dashboard8 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(pricing_check_detail_prices.price)is null,0,ROUND((sum(pricing_check_detail_prices.price)/sum(portfolio_management_items.store_price))*100,2)) as shelfprice'));
			$customer_dashboard8->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard8->join('portfolio_management_customers', 'users.id', '=', 'portfolio_management_customers.user_id');
			//$customer_dashboard8->join('portfolio_management_items', 'portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id');
			$customer_dashboard8->leftjoin("portfolio_management_items", function ($join) {
				$join->on('portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id')
					->whereRaw('FIND_IN_SET(?,portfolio_management_items.customer_id)', 'users.id');
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin("portfolio_managements", function ($join) use ($end_date, $start_date) {
				$join->on("portfolio_managements.id", "=", "portfolio_management_items.portfolio_management_id")
					->where("portfolio_managements.start_date", "<=", $start_date)
					->where("portfolio_managements.end_date", ">=", $end_date);
			});
			$customer_dashboard8->leftjoin("pricing_checks", function ($join) {
				$join->on("users.id", "=", "pricing_checks.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_id', '=', 'pricing_checks.id');


			//$customer_dashboard8
			$customer_dashboard8->whereIn('users.id', $all_customers);
			$customer_dashboard8->where('users.organisation_id', $this->organisation_id);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard8->groupBy('users.id');


			$percentage8 = $customer_dashboard8->get();
			//-----------
			//-----------Planogram
			$customer_dashboard9 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(planogram_posts.id) is null,0,ROUND((count(planogram_posts.id)/count(planogram_distributions.id))*100,2)) as PlanogramCompliance'));
			$customer_dashboard9->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard9->leftjoin("planogram_distributions", function ($join) {
				$join->on("users.id", "=", "planogram_distributions.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard9->leftjoin("planograms", function ($join) use ($end_date, $start_date) {
				$join->on("planograms.id", "=", "planogram_distributions.planogram_id")
					->where("planograms.start_date", "<=", $start_date)
					->where("planograms.end_date", ">=", $end_date);
			});

			$customer_dashboard9->leftjoin('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id');
			$customer_dashboard9->whereIn('users.id', $all_customers);
			$customer_dashboard9->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard9->groupBy('users.id');


			$percentage9 = $customer_dashboard9->get();
			//-----------Sos
			//-----------Planogram
			$customer_dashboard10 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(s_o_s_our_brands.catured_block)is null,0,ROUND((sum(s_o_s_our_brands.catured_block)+sum(s_o_s_our_brands.catured_shelves))/((s_o_s.block_store)+(s_o_s.no_of_shelves))*100,2)) as sos'));
			$customer_dashboard10->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard10->leftjoin("s_o_s", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "s_o_s.customer_id")
					->whereBetween('s_o_s.date', [$start_date, $end_date]);
			});
			$customer_dashboard10->leftjoin("s_o_s_our_brands", function ($join) {
				$join->on("s_o_s_our_brands.sos_id", "=", "s_o_s.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard10->whereIn('users.id', $all_customers);
			$customer_dashboard10->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard10->groupBy('users.id');


			$percentage10 = $customer_dashboard10->get();
			//-----------shareofassortment
			$customer_dashboard11 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(share_of_assortments.no_of_sku) IS NULL,0,ROUND((sum(share_of_assortment_our_brands.captured_sku)/
			  sum(share_of_assortments.no_of_sku))*100,2)) as share_of_assortment_our_brands'));
			$customer_dashboard11->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard11->leftjoin("share_of_assortments", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "share_of_assortments.customer_id")
					->whereBetween('share_of_assortments.date', [$start_date, $end_date]);

				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard11->leftjoin("share_of_assortment_our_brands", function ($join) {
				$join->on("share_of_assortment_our_brands.share_of_assortment_id", "=", "share_of_assortments.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard11->whereIn('users.id', $all_customers);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard11->groupBy('users.id');


			$percentage11 = $customer_dashboard11->get();
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
			$date_diff = $diff->format("%a") + 1;
			$date_diff_sec = $date_diff * 86400;
			$no_of_visits = count($customer_visits);
			$no_of_customers = count($all_customers) * $date_diff;

			$customer_dashboard1 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as coverage'));
			$customer_dashboard1->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard1->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});


			/* $customer_dashboard1->where('shop_status', 'open');
			 $customer_dashboard1->whereNull('reason');
			 $customer_dashboard1->whereBetween('date', [$start_date, $end_date]);*/
			$customer_dashboard1->whereIn('users.id', $all_customers);
			$customer_dashboard1->groupBy('users.id');

			$percentage1 = $customer_dashboard1->get();

			//Active OUTLETS
			$customer_dashboard2 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(orders.customer_id)=0,0,round((count(orders.customer_id)/' . $date_diff . ')*100,2)) as activeoutlet'));
			$customer_dashboard2->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard2->leftjoin("customer_visits", function ($join) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id");
			});
			$customer_dashboard2->leftJoin("orders", function ($join) use ($end_date, $start_date) {
				$join->on("orders.id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->where('completed_task', '!=', 0)
					->where('customer_visits.date', '>=', $end_date)
					->where('customer_visits.date', '<=', $start_date);
			});

			// $customer_dashboard2->where('shop_status', 'open');
			/*$customer_dashboard2->whereNull('customer_visits.reason');
			  $customer_dashboard2->where('completed_task', '!=', 0);*/
			// $customer_dashboard2->whereBetween('date', [$start_date, $end_date]);
			$customer_dashboard2->whereIn('users.id', $all_customers);
			$customer_dashboard2->groupBy('users.id');
			$percentage2 = $customer_dashboard2->get();

			// Execution
			$customer_dashboard3 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((sum(customer_visits.completed_task)/sum(customer_visits.total_task))*100,2)) as execution'));
			$customer_dashboard3->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard3->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			// $customer_dashboard3->whereNull('customer_visits.reason');
			// $customer_dashboard3->where('completed_task', '!=', 0);
			// $customer_dashboard3->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard3->whereIn('users.id', $all_customers);
			$customer_dashboard3->groupBy('users.id');
			$percentage3 = $customer_dashboard3->get();
			//Visit per day
			// visitFrequency
			$customer_dashboard4 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((count(customer_visits.customer_id)/count(distinct customer_visits.salesman_id)),2)) as visitfrequency'));

			$customer_dashboard4->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard4->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});
			// $customer_dashboard4->whereNull('customer_visits.reason');
			//$customer_dashboard4->where('completed_task', '!=', 0);
			// $customer_dashboard4->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard4->whereIn('users.id', $all_customers);
			$customer_dashboard4->groupBy('users.id');
			$percentage4 = $customer_dashboard4->get();
			//Visitperday
			$customer_dashboard5 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round(count(customer_visits.customer_id)/' . $date_diff . ')) as visitperday'));
			$customer_dashboard5->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard5->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			//$customer_dashboard5->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard5->whereIn('users.id', $all_customers);
			$customer_dashboard5->groupBy('users.id');
			$percentage5 = $customer_dashboard5->get();
			//Time spent
			$customer_dashboard6 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,SEC_TO_TIME(round(SUM(TIME_TO_SEC(customer_visits.visit_total_time))/' . $date_diff . '))) as timeSpent'));
			$customer_dashboard6->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard6->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
				//->whereBetween('customer_visits.date', array($start_date,$end_date));
			});


			$customer_dashboard6->whereIn('users.id', $all_customers);
			$customer_dashboard6->groupBy('users.id');
			$percentage6 = $customer_dashboard6->get();
			//Route compliance
			$customer_dashboard7 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as routeCompliance'));
			$customer_dashboard7->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard7->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				//$end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date])
					->where('is_sequnece', '!=', 0);
			});

			//$customer_dashboard7->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard7->whereIn('users.id', $all_customers);
			$customer_dashboard7->groupBy('users.id');
			$percentage7 = $customer_dashboard7->get();
			//-----------Shelf price
			$customer_dashboard8 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(pricing_check_detail_prices.price)is null,0,ROUND((sum(pricing_check_detail_prices.price)/sum(portfolio_management_items.store_price))*100,2)) as shelfprice'));
			$customer_dashboard8->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard8->join('portfolio_management_customers', 'users.id', '=', 'portfolio_management_customers.user_id');
			//$customer_dashboard8->join('portfolio_management_items', 'portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id');
			$customer_dashboard8->leftjoin("portfolio_management_items", function ($join) {
				$join->on('portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id')
					->whereRaw('FIND_IN_SET(?,portfolio_management_items.customer_id)', 'users.id');
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin("portfolio_managements", function ($join) use ($end_date, $start_date) {
				$join->on("portfolio_managements.id", "=", "portfolio_management_items.portfolio_management_id")
					->where("portfolio_managements.start_date", "<=", $start_date)
					->where("portfolio_managements.end_date", ">=", $end_date);
			});
			$customer_dashboard8->leftjoin("pricing_checks", function ($join) {
				$join->on("users.id", "=", "pricing_checks.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_id', '=', 'pricing_checks.id');


			//$customer_dashboard8
			$customer_dashboard8->whereIn('users.id', $all_customers);
			$customer_dashboard8->where('users.organisation_id', $this->organisation_id);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard8->groupBy('users.id');


			$percentage8 = $customer_dashboard8->get();
			//-----------
			//-----------Planogram
			$customer_dashboard9 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(planogram_posts.id) is null,0,ROUND((count(planogram_posts.id)/count(planogram_distributions.id))*100,2)) as PlanogramCompliance'));
			$customer_dashboard9->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard9->leftjoin("planogram_distributions", function ($join) {
				$join->on("users.id", "=", "planogram_distributions.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard9->leftjoin("planograms", function ($join) use ($end_date, $start_date) {
				$join->on("planograms.id", "=", "planogram_distributions.planogram_id")
					->where("planograms.start_date", "<=", $start_date)
					->where("planograms.end_date", ">=", $end_date);
			});

			$customer_dashboard9->leftjoin('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id');
			$customer_dashboard9->whereIn('users.id', $all_customers);
			$customer_dashboard9->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard9->groupBy('users.id');


			$percentage9 = $customer_dashboard9->get();
			//-----------Sos
			//-----------Planogram
			$customer_dashboard10 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(s_o_s_our_brands.catured_block)is null,0,ROUND((sum(s_o_s_our_brands.catured_block)+sum(s_o_s_our_brands.catured_shelves))/((s_o_s.block_store)+(s_o_s.no_of_shelves))*100,2)) as sos'));
			$customer_dashboard10->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard10->leftjoin("s_o_s", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "s_o_s.customer_id")
					->whereBetween('s_o_s.date', [$start_date, $end_date]);
			});
			$customer_dashboard10->leftjoin("s_o_s_our_brands", function ($join) {
				$join->on("s_o_s_our_brands.sos_id", "=", "s_o_s.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard10->whereIn('users.id', $all_customers);
			$customer_dashboard10->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard10->groupBy('users.id');


			$percentage10 = $customer_dashboard10->get();
			//-----------shareofassortment
			$customer_dashboard11 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(share_of_assortments.no_of_sku) IS NULL,0,ROUND((sum(share_of_assortment_our_brands.captured_sku)/
			  sum(share_of_assortments.no_of_sku))*100,2)) as share_of_assortment_our_brands'));
			$customer_dashboard11->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard11->leftjoin("share_of_assortments", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "share_of_assortments.customer_id")
					->whereBetween('share_of_assortments.date', [$start_date, $end_date]);

				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard11->leftjoin("share_of_assortment_our_brands", function ($join) {
				$join->on("share_of_assortment_our_brands.share_of_assortment_id", "=", "share_of_assortments.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard11->whereIn('users.id', $all_customers);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard11->groupBy('users.id');


			$percentage11 = $customer_dashboard11->get();
		} else {
			$salesman_ids = array();
			if (is_array($request->salesman_ids) && sizeof($request->salesman_ids) >= 1) {
				$salesman_ids = $request->salesman_ids;
			}

			$salesman_info_query = SalesmanInfo::select('id', 'user_id')
				->where('status', 1);
			//->get();
			if (count($salesman_ids)) {
				$salesman_info_query->whereIn('user_id', $request->salesman_ids);
			}
			$salesman_info = $salesman_info_query->get();

			$salesman_user_ids = array();
			if (count($salesman_info)) {
				$salesman_user_ids = $salesman_info->pluck('user_id')
					->toArray();
			}
			$all_customers = array();
			//print_r();
			// $customer_infos =  CustomerMerchandiser::whereIn('merchandiser_id', $salesman_user_ids)->get();
			$customer_ids = array();
			if (count($salesman_user_ids)) {
				$customer_merchadiser = CustomerMerchandiser::whereIn('merchandiser_id', $salesman_user_ids)->get();
				/* $customer_info = CustomerInfo::whereIn('merchandiser_id', $salesman_ids)->get();*/
				if (count($customer_merchadiser)) {
					$all_customers = $customer_merchadiser->pluck('customer_id')->toArray();
				}
			}
			//print_r($all_customers);
			//print_r($all_customers);exit;
			/* $all_customers = array();
            if (count($customer_infos)) {
                $all_customers = $customer_merchadiser->pluck('customer_id')->toArray();
            }*/

			$resultarray = array();
			$diff = date_diff(date_create($start_date), date_create($end_date));
			$date_diff = $diff->format("%a") + 1;
			$date_diff_sec = $date_diff * 86400;
			//$per= '%'
			$no_of_customers = count($all_customers) * $date_diff;

			$customer_dashboard1 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as coverage'));
			$customer_dashboard1->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard1->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});


			/* $customer_dashboard1->where('shop_status', 'open');
			 $customer_dashboard1->whereNull('reason');
			 $customer_dashboard1->whereBetween('date', [$start_date, $end_date]);*/
			$customer_dashboard1->whereIn('users.id', $all_customers);
			$customer_dashboard1->groupBy('users.id');

			$percentage1 = $customer_dashboard1->get();

			//Active OUTLETS
			$customer_dashboard2 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(orders.customer_id)=0,0,round((count(orders.customer_id)/' . $date_diff . ')*100,2)) as activeoutlet'));
			$customer_dashboard2->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard2->leftjoin("customer_visits", function ($join) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id");
			});
			$customer_dashboard2->leftJoin("orders", function ($join) use ($end_date, $start_date) {
				$join->on("orders.id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('shop_status', 'open')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			// $customer_dashboard2->where('shop_status', 'open');
			/*$customer_dashboard2->whereNull('customer_visits.reason');
			  $customer_dashboard2->where('completed_task', '!=', 0);*/
			// $customer_dashboard2->whereBetween('date', [$start_date, $end_date]);
			$customer_dashboard2->whereIn('users.id', $all_customers);
			$customer_dashboard2->groupBy('users.id');
			$percentage2 = $customer_dashboard2->get();

			// Execution
			$customer_dashboard3 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((sum(customer_visits.completed_task)/sum(customer_visits.total_task))*100,2)) as execution'));
			$customer_dashboard3->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard3->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			// $customer_dashboard3->whereNull('customer_visits.reason');
			// $customer_dashboard3->where('completed_task', '!=', 0);
			// $customer_dashboard3->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard3->whereIn('users.id', $all_customers);
			$customer_dashboard3->groupBy('users.id');
			$percentage3 = $customer_dashboard3->get();
			//Visit per day
			// visitFrequency
			$customer_dashboard4 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round((count(customer_visits.customer_id)/count(distinct customer_visits.salesman_id)),2)) as visitfrequency'));

			$customer_dashboard4->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard4->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});
			// $customer_dashboard4->whereNull('customer_visits.reason');
			//$customer_dashboard4->where('completed_task', '!=', 0);
			// $customer_dashboard4->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard4->whereIn('users.id', $all_customers);
			$customer_dashboard4->groupBy('users.id');
			$percentage4 = $customer_dashboard4->get();
			//Visitperday
			$customer_dashboard5 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,round(count(customer_visits.customer_id)/' . $date_diff . ')) as visitperday'));
			$customer_dashboard5->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard5->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});

			//$customer_dashboard5->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard5->whereIn('users.id', $all_customers);
			$customer_dashboard5->groupBy('users.id');
			$percentage5 = $customer_dashboard5->get();
			//Time spent
			$customer_dashboard6 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,SEC_TO_TIME(round(SUM(TIME_TO_SEC(customer_visits.visit_total_time))/' . $date_diff . '))) as timeSpent'));
			$customer_dashboard6->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard6->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('customer_visits.completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date]);
			});


			$customer_dashboard6->whereIn('users.id', $all_customers);
			$customer_dashboard6->groupBy('users.id');
			$percentage6 = $customer_dashboard6->get();
			//Route compliance
			$customer_dashboard7 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(customer_visits.customer_id)=0,0,ROUND((count(customer_visits.customer_id)/' . $date_diff . ')*100,2)) as routeCompliance'));
			$customer_dashboard7->leftjoin("customer_infos", function ($join) {
				$join->on("users.id", "=", "customer_infos.user_id");
			});
			$customer_dashboard7->leftjoin("customer_visits", function ($join) use ($end_date, $start_date) {
				//$end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
				$join->on("customer_infos.user_id", "=", "customer_visits.customer_id")
					->whereNull('customer_visits.reason')
					->where('completed_task', '!=', 0)
					->whereBetween('customer_visits.date', [$start_date, $end_date])
					->where('is_sequnece', '!=', 0);
			});

			//$customer_dashboard7->whereBetween('customer_visits.date', [$start_date, $end_date]);
			$customer_dashboard7->whereIn('users.id', $all_customers);
			$customer_dashboard7->groupBy('users.id');
			$percentage7 = $customer_dashboard7->get();
			//-----------Shelf price
			$customer_dashboard8 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(pricing_check_detail_prices.price)is null,0,ROUND((sum(pricing_check_detail_prices.price)/sum(portfolio_management_items.store_price))*100,2)) as shelfprice'));
			$customer_dashboard8->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard8->join('portfolio_management_customers', 'users.id', '=', 'portfolio_management_customers.user_id');
			//$customer_dashboard8->join('portfolio_management_items', 'portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id');
			$customer_dashboard8->leftjoin("portfolio_management_items", function ($join) {
				$join->on('portfolio_management_customers.portfolio_management_id', '=', 'portfolio_management_items.portfolio_management_id')
					->whereRaw('FIND_IN_SET(?,portfolio_management_items.customer_id)', 'users.id');
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin("portfolio_managements", function ($join) use ($end_date, $start_date) {
				$join->on("portfolio_managements.id", "=", "portfolio_management_items.portfolio_management_id")
					->where("portfolio_managements.start_date", "<=", $start_date)
					->where("portfolio_managements.end_date", ">=", $end_date);
			});
			$customer_dashboard8->leftjoin("pricing_checks", function ($join) {
				$join->on("users.id", "=", "pricing_checks.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard8->leftjoin('pricing_check_detail_prices', 'pricing_check_detail_prices.pricing_check_id', '=', 'pricing_checks.id');


			//$customer_dashboard8
			$customer_dashboard8->whereIn('users.id', $all_customers);
			$customer_dashboard8->where('users.organisation_id', $this->organisation_id);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard8->groupBy('users.id');


			$percentage8 = $customer_dashboard8->get();
			//-----------
			//-----------Planogram
			$customer_dashboard9 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(count(planogram_posts.id) is null,0,ROUND((count(planogram_posts.id)/count(planogram_distributions.id))*100,2)) as PlanogramCompliance'));
			$customer_dashboard9->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard9->leftjoin("planogram_distributions", function ($join) {
				$join->on("users.id", "=", "planogram_distributions.customer_id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard9->leftjoin("planograms", function ($join) use ($end_date, $start_date) {
				$join->on("planograms.id", "=", "planogram_distributions.planogram_id")
					->where("planograms.start_date", "<=", $start_date)
					->where("planograms.end_date", ">=", $end_date);
			});

			$customer_dashboard9->leftjoin('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id');
			$customer_dashboard9->whereIn('users.id', $all_customers);
			$customer_dashboard9->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard9->groupBy('users.id');


			$percentage9 = $customer_dashboard9->get();
			//-----------Sos
			//-----------Planogram
			$customer_dashboard10 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(s_o_s_our_brands.catured_block)is null,0,ROUND((sum(s_o_s_our_brands.catured_block)+sum(s_o_s_our_brands.catured_shelves))/((s_o_s.block_store)+(s_o_s.no_of_shelves))*100,2)) as sos'));
			$customer_dashboard10->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard10->leftjoin("s_o_s", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "s_o_s.customer_id")
					->whereBetween('s_o_s.date', [$start_date, $end_date]);
			});
			$customer_dashboard10->leftjoin("s_o_s_our_brands", function ($join) {
				$join->on("s_o_s_our_brands.sos_id", "=", "s_o_s.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard10->whereIn('users.id', $all_customers);
			$customer_dashboard10->where('users.organisation_id', $this->organisation_id);
			$customer_dashboard10->groupBy('users.id');


			$percentage10 = $customer_dashboard10->get();
			//-----------shareofassortment
			$customer_dashboard11 = DB::table('users')->select('customer_infos.customer_code', 'users.firstname', DB::raw('if(sum(share_of_assortments.no_of_sku) IS NULL,0,ROUND((sum(share_of_assortment_our_brands.captured_sku)/
			  sum(share_of_assortments.no_of_sku))*100,2)) as share_of_assortment_our_brands'));
			$customer_dashboard11->join('customer_infos', 'user_id', '=', 'users.id');
			$customer_dashboard11->leftjoin("share_of_assortments", function ($join) use ($end_date, $start_date) {
				$join->on("users.id", "=", "share_of_assortments.customer_id")
					->whereBetween('share_of_assortments.date', [$start_date, $end_date]);

				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});
			$customer_dashboard11->leftjoin("share_of_assortment_our_brands", function ($join) {
				$join->on("share_of_assortment_our_brands.share_of_assortment_id", "=", "share_of_assortments.id");
				//$join->on("planogram_posts.organisation_id", "=",$this->organisation_id);
			});

			//->join('planogram_posts', 'users.id', '=', 'planogram_posts.customer_id', 'left')
			///$customer_dashboard10->join('planogram_distributions', 'users.id', '=', 'planogram_distributions.customer_id', 'left');
			// ->join('planogram_posts', 'planogram_posts.customer_id', '=', 'planogram_distributions.customer_id', 'left')
			//->join('planograms', 'planograms.id', '=', 'planogram_distributions.planogram_id')
			//->where('planograms.status', 1)
			//->where('planograms.start_date', '<=', $start_date)
			//->whereBetween('planogram_posts.created_at', [$start_date, $end_date])
			$customer_dashboard11->whereIn('users.id', $all_customers);
			//->where('planogram_posts.organisation_id', $this->organisation_id)
			$customer_dashboard11->groupBy('users.id');


			$percentage11 = $customer_dashboard11->get();
		}
		//print_r($percentage5);
		$resultarray = array(
			'Coverage' => $percentage1,
			'Active' => $percentage2,
			'Execution' => $percentage3,
			'visitFrequency' => $percentage4,
			'visitperday' => $percentage5,
			'timeSpent' => $percentage6,
			'Routecompliance' => $percentage7,
			'shelfprice' => $percentage8,
			'planogram' => $percentage9,
			'sos' => $percentage10,
			'shareofassortment' => $percentage11

		);


		Cache::put('dashboard_cache', (object)$resultarray, 10);

		return (object)$resultarray;
	}
}
