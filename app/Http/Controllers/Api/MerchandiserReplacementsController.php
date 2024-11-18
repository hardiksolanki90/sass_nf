<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Collection;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanWeek;
use App\Model\JourneyPlanDay;
use App\Model\JourneyPlanCustomer;
use App\Model\MerchandiserReplacement;
use App\Model\RouteItemGrouping;
use Illuminate\Http\Request;

class MerchandiserReplacementsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $merchandiser_replacement = MerchandiserReplacement::with(
            'newSalesman:id,firstname,lastname',
            'oldSalesman:id,firstname,lastname',
            'newSalesmanInfo',
            'oldSalesmanInfo'
        )
            ->orderBy('id', 'desc')
            ->get();

        $merchandiser_replacement_array = array();
        if (is_object($merchandiser_replacement)) {
            foreach ($merchandiser_replacement as $key => $merchandiser_replacement1) {
                $merchandiser_replacement_array[] = $merchandiser_replacement[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($merchandiser_replacement_array[$offset])) {
                    $data_array[] = $merchandiser_replacement_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($merchandiser_replacement_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($merchandiser_replacement_array);
        } else {
            $data_array = $merchandiser_replacement_array;
        }

        return prepareResult(true, $data_array, [], "Merchandiser Replacement listing", $this->success, $pagination);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Merchandiser Replace", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $merchandiser_replacement = new MerchandiserReplacement;
            $merchandiser_replacement->old_salesman_id = $request->old_salesman_id;
            $merchandiser_replacement->new_salesman_id = $request->new_salesman_id;
            $merchandiser_replacement->added_on = date('Y-m-d');
            $merchandiser_replacement->type = 'replace';
            $merchandiser_replacement->save();

            $customerInfo = CustomerMerchandiser::select('id', 'customer_id', 'merchandiser_id')
                ->where('merchandiser_id', $request->old_salesman_id)
                ->update(['merchandiser_id' => $request->new_salesman_id]);

            $route_item_groupings = RouteItemGrouping::select('id', 'merchandiser_id')
                ->where('merchandiser_id', $request->old_salesman_id)
                ->update(['merchandiser_id' => $request->new_salesman_id]);

            $new_journey_plans = JourneyPlan::with(
                'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id'
            )
                ->where('merchandiser_id', $request->new_salesman_id)
                ->first();

            if (is_object($new_journey_plans)) {
                // Old JourneyPlan
                $old_journey_plans = JourneyPlan::with(
                    'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                    'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id'
                )
                    ->where('merchandiser_id', $request->old_salesman_id)
                    ->first();


                if (is_object($old_journey_plans)) {
                    $old_journey_plans->status = 0;
                    $old_journey_plans->current_stage = "Rejected";
                    $old_journey_plans->save();

                    // Old JourneyPlanWeek
                    if (count($old_journey_plans->journeyPlanWeeks)) {
                        foreach ($old_journey_plans->journeyPlanWeeks as $oKey => $oWeek) {
                            // New JourneyPlanWeek search
                            // old week1 find new week1
                            $new_journey_plan_week = JourneyPlanWeek::select('id', 'journey_plan_id', 'week_number')
                                ->where('week_number', $oWeek->week_number)
                                ->where('journey_plan_id', $new_journey_plans->id)
                                ->first();

                            if (is_object($new_journey_plan_week)) {
                                // old journeyPlanDays
                                if (count($oWeek->journeyPlanDays)) {
                                    foreach ($oWeek->journeyPlanDays as $dKey => $days) {
                                        //getting new journey plan day
                                        $new_journey_plans_days = JourneyPlanDay::where('day_name', $days->day_name)
                                            ->where('journey_plan_id', $new_journey_plans->id)
                                            ->where('journey_plan_week_id', $new_journey_plan_week->id)
                                            ->first();

                                        if (!is_object($new_journey_plans_days)) {
                                            $new_journey_plans_days = new JourneyPlanDay;
                                            $new_journey_plans_days->journey_plan_id = $new_journey_plans->id;
                                            $new_journey_plans_days->journey_plan_week_id = $new_journey_plan_week->id;
                                            $new_journey_plans_days->day_number = $days->day_number;
                                            $new_journey_plans_days->day_name = $days->day_name;
                                            $new_journey_plans_days->save();
                                        }

                                        $new_journey_plan_day_id = $new_journey_plans_days->id;
                                        $new_journey_plan_id = $new_journey_plans_days->journey_plan_id;
                                        $new_journey_plan_week_id = $new_journey_plans->journey_plan_week_id;
                                        $journey_plan_day_name = $new_journey_plans->day_name;

                                        if (count($days->journeyPlanCustomers)) {
                                            foreach ($days->journeyPlanCustomers as $cKey => $customer) {
                                                $new_journey_plan_customer = new JourneyPlanCustomer;
                                                $new_journey_plan_customer->journey_plan_id = $new_journey_plan_id;
                                                $new_journey_plan_customer->journey_plan_day_id = $new_journey_plan_day_id;
                                                $new_journey_plan_customer->customer_id = $customer->customer_id;
                                                $new_journey_plan_customer->day_customer_sequence = count($days->journeyPlanCustomers) + 1;
                                                $new_journey_plan_customer->save();
                                            }
                                        }
                                    }
                                }
                            } else {
                                // add new week
                                $new_journey_plan_week = new JourneyPlanWeek;
                                $new_journey_plan_week->journey_plan_id = $new_journey_plans->id;
                                $new_journey_plan_week->week_number = $oWeek->week_number;
                                $new_journey_plan_week->save();

                                // old week days
                                if (count($oWeek->journeyPlanDays)) {
                                    foreach ($oWeek->journeyPlanDays as $dKey => $days) {
                                        $new_journey_plans_days = JourneyPlanDay::where('day_name', $days->day_name)
                                            ->where('journey_plan_id', $new_journey_plans->id)
                                            ->where('journey_plan_week_id', $new_journey_plan_week->id)
                                            ->first();

                                        if (!is_object($new_journey_plans_days)) {
                                            $new_journey_plan_day = new JourneyPlanDay;
                                            $new_journey_plan_day->journey_plan_id = $new_journey_plans->id;
                                            $new_journey_plan_day->journey_plan_week_id = $new_journey_plan_week->id;
                                            $new_journey_plan_day->day_number = $days->day_number;
                                            $new_journey_plan_day->day_name = $days->day_name;
                                            $new_journey_plan_day->save();

                                            if (count($days->journeyPlanCustomers)) {
                                                foreach ($days->journeyPlanCustomers as $cKey => $customer) {

                                                    $new_journey_plan_customer = new JourneyPlanCustomer;
                                                    $new_journey_plan_customer->journey_plan_id = $new_journey_plans->id;
                                                    $new_journey_plan_customer->journey_plan_day_id = $new_journey_plan_day->id;
                                                    $new_journey_plan_customer->customer_id = $customer->customer_id;
                                                    $new_journey_plan_customer->day_customer_sequence = $cKey + 1;
                                                    $new_journey_plan_customer->save();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $journey_plans = JourneyPlan::select('id', 'is_merchandiser', 'merchandiser_id')
                    ->where('is_merchandiser', 1)
                    ->where('merchandiser_id', $request->old_salesman_id)
                    ->update(['merchandiser_id' => $request->new_salesman_id]);
            }

            \DB::commit();
            return prepareResult(true, $merchandiser_replacement, [], "Merchandiser replacement added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeSwap(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), $validate['errors']->first(), $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $merchandiser_replacement = new MerchandiserReplacement;
            $merchandiser_replacement->old_salesman_id = $request->old_salesman_id;
            $merchandiser_replacement->new_salesman_id = $request->new_salesman_id;
            $merchandiser_replacement->added_on = date('Y-m-d');
            $merchandiser_replacement->type = 'swap';
            $merchandiser_replacement->save();

            $old_salesman_id = $request->old_salesman_id;
            $new_salesman_id = $request->new_salesman_id;

            $jp_old = JourneyPlan::where('merchandiser_id', $request->old_salesman_id)->first();
            $jp_new = JourneyPlan::where('merchandiser_id', $request->new_salesman_id)->first();

            $old_cm = CustomerMerchandiser::where('merchandiser_id', $request->old_salesman_id)->get();
            $new_cm = CustomerMerchandiser::where('merchandiser_id', $request->new_salesman_id)->get();

            if (is_object($old_cm) && is_object($new_cm)) {
                collect($new_cm)->each(function ($newM, $key) use ($old_salesman_id) {
                    $newM->merchandiser_id = $old_salesman_id;
                    $newM->update();
                });

                collect($old_cm)->each(function ($oldM, $key) use ($new_salesman_id) {
                    $oldM->merchandiser_id = $new_salesman_id;
                    $oldM->update();
                });

                $jp_old->merchandiser_id    = $request->new_salesman_id;
                $jp_old->name               = $jp_new->name;
                $jp_old->description        = $jp_new->description;

                $jp_new->merchandiser_id    = $request->old_salesman_id;
                $jp_new->name               = $jp_old->name;
                $jp_new->description        = $jp_old->description;

                $jp_old->save();
                $jp_new->save();
            } else {
                return prepareResult(false, [], [], "Journey Plan is not exist", $this->unprocessableEntity);
            }

            \DB::commit();
            return prepareResult(true, $merchandiser_replacement, [], "Merchandiser replacement added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'old_salesman_id' => 'required|integer|exists:journey_plans,merchandiser_id',
                'new_salesman_id' => 'required|integer|exists:journey_plans,merchandiser_id'
            ]);
        }
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }
}
