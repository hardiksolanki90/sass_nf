<?php

namespace App\Http\Controllers\Api;

use App\Exports\JourneyPlanVisitExport;
use App\Http\Controllers\Controller;
use App\Imports\JourneyPlanImport;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\SalesmanInfo;
use App\Model\ImportTempFile;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanCustomer;
use App\Model\JourneyPlanDay;
use App\Model\JourneyPlanWeek;
use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use File;
use Illuminate\Support\Facades\DB;
use URL;


class JourneyPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $all_salesman = array();

        $all_salesman = getSalesman(false);
        $journey_plans_query = JourneyPlan::with(
            'route:id,area_id,route_code,route_name',
            'merchandiser:id,firstname,lastname',
            'merchandiser.salesmanInfo:id,user_id,salesman_code'
            // ,
            // 'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
            // 'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
            // 'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time'
        );
        // ->get();
        if ($request->name) {
            $journey_plans_query->where('name', 'like', "%" . $request->name . "%");
        }
        if (count($all_salesman)) {
            $journey_plans_query->whereIn('merchandiser_id', $all_salesman);
        }
        if ($request->start_date) {
            // $journey_plans_query->whereDate('start_date', '<=', date('Y-m-d', strtotime($request->start_date)));
            $journey_plans_query->where('start_date', '<=', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            // $journey_plans_query->whereDate('end_date', '<=', $request->end_date);
            $journey_plans_query->where('end_date', '>=', date('Y-m-d', strtotime($request->end_date)));
        }

        if ($request->merchanidser_code) {
            $m_code = $request->merchanidser_code;
            $journey_plans_query->whereHas('merchandiser.salesmanInfo', function ($q) use ($m_code) {
                $q->where('salesman_code', 'like', '%' . $m_code . '%');
            });
        }

        $journey_plans = $journey_plans_query->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        $results = GetWorkFlowRuleObject('Journey Plan');
        $approve_need_journey_plans = array();
        $approve_need_journey_plans_detail_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_journey_plans[] = $raw['object']->raw_id;
                $approve_need_journey_plans_detail_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $journey_plans_array = array();
        if (is_object($journey_plans)) {
            foreach ($journey_plans as $key => $journey_plans1) {
                if (in_array($journey_plans[$key]->id, $approve_need_journey_plans)) {
                    $journey_plans[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_journey_plans_detail_object_id[$journey_plans[$key]->id])) {
                        $journey_plans[$key]->objectid = $approve_need_journey_plans_detail_object_id[$journey_plans[$key]->id];
                    } else {
                        $journey_plans[$key]->objectid = '';
                    }
                } else {
                    $journey_plans[$key]->need_to_approve = 'no';
                    $journey_plans[$key]->objectid = '';
                }

                if (
                    $journey_plans[$key]->current_stage == 'Approved' ||
                    request()->user()->usertype == 1 ||
                    in_array(
                        $journey_plans[$key]->id,
                        $approve_need_journey_plans
                    )
                ) {
                    $journey_plans_array[] = $journey_plans[$key];
                }
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($journey_plans_array[$offset])) {
                    $data_array[] = $journey_plans_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($journey_plans_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($journey_plans_array);
        } else {
            $data_array = $journey_plans_array;
        }
        return prepareResult(true, $data_array, [], "Journey plan listing", $this->success, $pagination);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Journey Plan", $this->unprocessableEntity);
        }

        if ($request->plan_type == 2) {
            if (is_array($request->weeks) && sizeof($request->weeks) < 1) {
                return prepareResult(false, [], [], "Error Please add atleast one week.", $this->unprocessableEntity);
            }
        }

        if (is_array($request->days) && sizeof($request->days) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one day.", $this->unprocessableEntity);
        }

        if (is_array($request->customers) && sizeof($request->customers) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one customer.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {

            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Journey Plan', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Journey Plan',$request);
            }

            $journey_plans = new JourneyPlan;
            $journey_plans->route_id = $request->route_id;
            $journey_plans->is_merchandiser = $request->is_merchandiser;
            $journey_plans->merchandiser_id = $request->merchandiser_id;
            $journey_plans->name = $request->name;
            $journey_plans->description = $request->description;
            $journey_plans->start_date = $request->start_date;
            $journey_plans->no_end_date = $request->no_end_date;

            if ($request->no_end_date == 0) {
                $journey_plans->end_date = $request->end_date;
            }

            $journey_plans->start_time = $request->start_time;
            $journey_plans->end_time = $request->end_time;
            $journey_plans->start_day_of_the_week = $request->start_day_of_the_week;
            $journey_plans->plan_type = $request->plan_type;
            $journey_plans->is_enforce = $request->is_enforce;
            $journey_plans->current_stage = $current_stage;

            if ($request->plan_type == 2) {
                $journey_plans->week_1 = $request->weeks['week_1'];
                $journey_plans->week_2 = $request->weeks['week_2'];
                $journey_plans->week_3 = $request->weeks['week_3'];
                $journey_plans->week_4 = $request->weeks['week_4'];
                $journey_plans->week_5 = $request->weeks['week_5'];
            }

            $journey_plans->save();

            if ($isActivate = checkWorkFlowRule('Journey Plan', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Journey Plan', $request, $journey_plans->id);
            }

            if ($journey_plans->plan_type == 2) {
                foreach ($request->customers as $key => $days) {
                    $journey_plans_weeks = new JourneyPlanWeek;
                    $journey_plans_weeks->journey_plan_id = $journey_plans->id;
                    $journey_plans_weeks->week_number = $key;
                    $journey_plans_weeks->save();
                    foreach ($days as $dkey => $day) {
                        $journey_plans_days = new JourneyPlanDay;
                        $journey_plans_days->journey_plan_id = $journey_plans->id;
                        $journey_plans_days->journey_plan_week_id = $journey_plans_weeks->id;
                        $journey_plans_days->day_name = $day['day_name'];
                        $journey_plans_days->day_number = $day['day_number'];
                        $journey_plans_days->save();
                        foreach ($day['customers'] as $ckey => $customer) {
                            $journey_plans_customers = new JourneyPlanCustomer;
                            $journey_plans_customers->journey_plan_id = $journey_plans->id;
                            $journey_plans_customers->journey_plan_day_id = $journey_plans_days->id;
                            $journey_plans_customers->is_msl = ($customer['is_msl'] == 1) ? 1 : 0;
                            $journey_plans_customers->customer_id = $customer['customer_id'];
                            $journey_plans_customers->day_customer_sequence = $customer['day_customer_sequence'];
                            $journey_plans_customers->day_start_time = $customer['day_start_time'];
                            $journey_plans_customers->day_end_time = $customer['day_end_time'];
                            $journey_plans_customers->save();

                            $this->saveCustomerMerchandiser($request->merchandiser_id, $customer['customer_id']);
                        }
                    }
                }
            } else {
                foreach ($request->customers as $key => $day) {
                    $journey_plans_days = new JourneyPlanDay;
                    $journey_plans_days->journey_plan_id = $journey_plans->id;
                    $journey_plans_days->day_name = $day['day_name'];
                    $journey_plans_days->day_number = $day['day_number'];
                    $journey_plans_days->save();
                    foreach ($day['customers'] as $ckey => $customer) {
                        $journey_plans_customers = new JourneyPlanCustomer;
                        $journey_plans_customers->journey_plan_day_id = $journey_plans_days->id;
                        $journey_plans_customers->journey_plan_id = $journey_plans->id;
                        $journey_plans_customers->is_msl = ($customer['is_msl'] == 1) ? 1 : 0;
                        $journey_plans_customers->customer_id = $customer['customer_id'];
                        $journey_plans_customers->day_customer_sequence = $customer['day_customer_sequence'];
                        $journey_plans_customers->day_start_time = $customer['day_start_time'];
                        $journey_plans_customers->day_end_time = $customer['day_end_time'];
                        $journey_plans_customers->save();

                        $this->saveCustomerMerchandiser($request->merchandiser_id, $customer['customer_id']);
                    }
                }
            }

            DB::commit();

            $journey_plans->getSaveData();

            return prepareResult(true, $journey_plans, [], "Journey Plans added successfully", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */


    public function show($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Journey plan", $this->unauthorized);
        }

        $checkJPPlanType = JourneyPlan::select('plan_type')->where('uuid', $uuid)->first();

        if (is_object($checkJPPlanType)) {
            if ($checkJPPlanType->plan_type == 1) {
                $journey_plan = JourneyPlan::where('uuid', $uuid)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'merchandiser:id,firstname,lastname',
                    'merchandiser.salesmanInfo:id,user_id,salesman_code',
                    'journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo:id,user_id,customer_code,trn_no',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )->first();
            } else {
                $journey_plan = JourneyPlan::where('uuid', $uuid)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'merchandiser:id,firstname,lastname',
                    'merchandiser.salesmanInfo:id,user_id,salesman_code',
                    'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                    'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo:id,user_id,customer_code,trn_no',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )
                    ->first();

                if (is_object($journey_plan)) {
                    if (count($journey_plan->journeyPlanWeeks)) {
                        foreach ($journey_plan->journeyPlanWeeks as $k => $week) {
                            if ($week->week_number == "week6") {
                                unset($journey_plan->journeyPlanWeeks[$k]);
                            }
                        }
                    }
                }
            }
        } else {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $journey_plan, [], "Journey Plan show", $this->success);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Journey plan", $this->unauthorized);
        }

        $checkJPPlanType = JourneyPlan::select('plan_type')->where('uuid', $uuid)->first();
        if (is_object($checkJPPlanType)) {
            if ($checkJPPlanType->plan_type == 1) {
                $journey_plan = JourneyPlan::where('uuid', $uuid)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo:id,user_id,customer_code',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )->first();
            } else {
                $journey_plan = JourneyPlan::where('uuid', $uuid)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                    'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo:id,user_id,customer_code',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )
                    ->first();
            }
        } else {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $journey_plan, [], "Journey Plan Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Journey Plan", $this->unprocessableEntity);
        }

        if ($request->plan_type == 2) {
            if (is_array($request->weeks) && sizeof($request->weeks) < 1) {
                return prepareResult(false, [], [], "Error Please add atleast one week.", $this->unprocessableEntity);
            }
        }

        if (is_array($request->days) && sizeof($request->days) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one day.", $this->unprocessableEntity);
        }

        if (is_array($request->customers) && sizeof($request->customers) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one customer.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {

            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Journey Plan', 'edit', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Journey Plan',$request);
            }

            $journey_plans = JourneyPlan::where('uuid', $uuid)->first();

            $journey_plans->route_id = $request->route_id;
            $journey_plans->is_merchandiser = $request->is_merchandiser;
            $journey_plans->merchandiser_id = $request->merchandiser_id;
            $journey_plans->name = $request->name;
            $journey_plans->description = $request->description;
            $journey_plans->start_date = $request->start_date;
            $journey_plans->no_end_date = $request->no_end_date;
            if (!$request->no_end_date) {
                $journey_plans->end_date = $request->end_date;
            }
            $journey_plans->start_time = $request->start_time;
            $journey_plans->end_time = $request->end_time;
            $journey_plans->start_day_of_the_week = $request->start_day_of_the_week;
            $journey_plans->plan_type = $request->plan_type;
            $journey_plans->is_enforce = $request->is_enforce;
            $journey_plans->current_stage = $current_stage;
            if ($request->plan_type == 2) {
                $journey_plans->week_1 = $request->weeks['week_1'];
                $journey_plans->week_2 = $request->weeks['week_2'];
                $journey_plans->week_3 = $request->weeks['week_3'];
                $journey_plans->week_4 = $request->weeks['week_4'];
                $journey_plans->week_5 = $request->weeks['week_5'];
            }
            $journey_plans->save();

            updateMerchandiser($current_organisation_id, $request->merchandiser_id, false);

            if ($isActivate = checkWorkFlowRule('Journey Plan', 'edit', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Journey Plan', $request, $journey_plans->id);
            }

            if ($journey_plans->plan_type == 2) {
                JourneyPlanWeek::where('journey_plan_id', $journey_plans->id)->delete();
                JourneyPlanDay::where('journey_plan_id', $journey_plans->id)->delete();
                JourneyPlanCustomer::where('journey_plan_id', $journey_plans->id)->delete();

                foreach ($request->customers as $key => $days) {
                    $journey_plans_weeks = new JourneyPlanWeek;
                    $journey_plans_weeks->journey_plan_id = $journey_plans->id;
                    $journey_plans_weeks->week_number = $key;
                    $journey_plans_weeks->save();
                    foreach ($days as $dkey => $day) {
                        $journey_plans_days = new JourneyPlanDay;
                        $journey_plans_days->journey_plan_id = $journey_plans->id;
                        $journey_plans_days->journey_plan_week_id = $journey_plans_weeks->id;
                        $journey_plans_days->day_name = $day['day_name'];
                        $journey_plans_days->day_number = $day['day_number'];
                        $journey_plans_days->save();
                        foreach ($day['customers'] as $ckey => $customer) {
                            $journey_plans_customers = new JourneyPlanCustomer;
                            $journey_plans_customers->journey_plan_id = $journey_plans->id;
                            $journey_plans_customers->journey_plan_day_id = $journey_plans_days->id;
                            $journey_plans_customers->customer_id = $customer['customer_id'];
                            $journey_plans_customers->is_msl = ($customer['is_msl'] == 1) ? 1 : 0;
                            $journey_plans_customers->day_customer_sequence = $customer['day_customer_sequence'];
                            $journey_plans_customers->day_start_time = $customer['day_start_time'];
                            $journey_plans_customers->day_end_time = $customer['day_end_time'];
                            $journey_plans_customers->save();

                            $this->saveCustomerMerchandiser($request->merchandiser_id, $customer['customer_id']);
                        }
                    }
                }
            } else {
                JourneyPlanDay::where('journey_plan_id', $journey_plans->id)->delete();
                JourneyPlanCustomer::where('journey_plan_id', $journey_plans->id)->delete();

                foreach ($request->customers as $key => $day) {
                    $journey_plans_days = new JourneyPlanDay;
                    $journey_plans_days->journey_plan_id = $journey_plans->id;
                    $journey_plans_days->day_name = $day['day_name'];
                    $journey_plans_days->day_number = $day['day_number'];
                    $journey_plans_days->save();
                    foreach ($day['customers'] as $ckey => $customer) {
                        $journey_plans_customers = new JourneyPlanCustomer;
                        $journey_plans_customers->journey_plan_day_id = $journey_plans_days->id;
                        $journey_plans_customers->journey_plan_id = $journey_plans->id;
                        $journey_plans_customers->customer_id = $customer['customer_id'];
                        $journey_plans_customers->is_msl = ($customer['is_msl'] == 1) ? 1 : 0;
                        $journey_plans_customers->day_customer_sequence = $customer['day_customer_sequence'];
                        $journey_plans_customers->day_start_time = $customer['day_start_time'];
                        $journey_plans_customers->day_end_time = $customer['day_end_time'];
                        $journey_plans_customers->save();

                        $this->saveCustomerMerchandiser($request->merchandiser_id, $customer['customer_id']);
                    }
                }
            }

            DB::commit();
            $journey_plans->getSaveData();
            return prepareResult(true, $journey_plans, [], "Journey Plans added successfully", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Journey Plan", $this->unauthorized);
        }

        $journey_plan = JourneyPlan::where('uuid', $uuid)
            ->first();

        if (is_object($journey_plan)) {
            $journey_plan->delete();

            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                // 'route_id' => 'required|integer|exists:routes,id',
                'name' => 'required',
                'start_date' => 'required',
                'no_end_date' => 'required',
                // 'start_time' => 'required',
                // 'end_time' => 'required',
                'start_day_of_the_week' => 'required|integer',
                'plan_type' => 'required|integer',
            ]);
        }
        if ($type == "routePlan") {
            $validator = \Validator::make($input, [
                'route_id' => 'required|integer|exists:routes,id'
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function showRoute($id, Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$id) {
            return prepareResult(false, [], [], "Error while validating Journey plan", $this->unauthorized);
        }

        $checkJPPlanType = JourneyPlan::select('plan_type')
            ->where('route_id', $id)
            ->first();

        if ($checkJPPlanType) {
            if ($checkJPPlanType->plan_type == 1) {
                $journey_plan = JourneyPlan::where('route_id', $id)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )->first();
            } else {
                $journey_plan = JourneyPlan::where('route_id', $id)->with(
                    'route:id,organisation_id,uuid,area_id,route_code,route_name,status',
                    'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                    'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )
                    ->first();
            }

            if (!is_object($journey_plan)) {
                return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
            }

            return prepareResult(true, $journey_plan, [], "Journey Plan show", $this->success);
        } else {

            return prepareResult(true, [], [], "Journey Plan show", $this->success);
        }
    }

    public function journeyPlanByMerchandise($merchandiser_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$merchandiser_id) {
            return prepareResult(false, [], [], "Error while validating Journey plan", $this->unauthorized);
        }

        $journey_plan = JourneyPlan::with(
            'merchandiser:id,firstname,lastname',
            'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
            'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
            'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
            'journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
            'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
            'journeyPlanDays.journeyPlanCustomers.customerInfo',
            'journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname',
            'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo',
            'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
        )
            ->where('merchandiser_id', $merchandiser_id)
            ->where('start_date', '<=', date('Y-m-d'))
            ->where('status', 1)
            // ->where('current_stage', 'Approved')
            ->where(function ($q) {
                $q->whereDate('end_date', '>=', date('Y-m-d'))
                    ->orWhereNull('end_date');
            })
            ->orderBy('id', 'desc')
            ->first();

        return prepareResult(true, $journey_plan, [], "Journey Plan listing", $this->success);
    }

    public function journeyPlanBySupervisor($supervisor_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$supervisor_id) {
            return prepareResult(false, [], [], "Error while validating Journey plan", $this->unauthorized);
        }

        $salesmanInfos = SalesmanInfo::where('salesman_supervisor', $supervisor_id)
        ->where('salesman_type_id', 2)
        ->where('status', 1)
        ->where('current_stage', 'Approved')
        ->get();

        if (count($salesmanInfos)) {

            $salesman_user_ids = $salesmanInfos->pluck('user_id')->toArray();
            $s_id_string = implode(',', $salesman_user_ids);

            $jp_array = array();

            foreach ( $salesman_user_ids as $salessman) {

                $journey_plan = JourneyPlan::with(
                    'merchandiser:id,firstname,lastname',
                    'journeyPlanWeeks:id,journey_plan_id,uuid,week_number',
                    'journeyPlanWeeks.journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanDays:id,journey_plan_id,uuid,journey_plan_week_id,day_number,day_name',
                    'journeyPlanDays.journeyPlanCustomers:id,journey_plan_day_id,customer_id,day_customer_sequence,day_start_time,day_end_time,is_msl',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo',
                    'journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo',
                    'journeyPlanWeeks.journeyPlanDays.journeyPlanCustomers.customerInfo.user:id,firstname,lastname'
                )
                    ->where('merchandiser_id', $salessman)
                    ->where('start_date', '<=', date('Y-m-d'))
                    ->where('status', 1)
                    // ->where('current_stage', 'Approved')
                    ->where(function ($q) {
                        $q->whereDate('end_date', '>=', date('Y-m-d'))
                            ->orWhereNull('end_date');
                    })
                    ->orderBy('id', 'desc')
                    ->first();

                    if($journey_plan)
                    {
                        $jp_array[] = $journey_plan;
                    }
                    
            }
            
    
            return prepareResult(true, $jp_array, [], "Journey Plan listing", $this->success);
        }else{
            return prepareResult(true, [], [], "Journey Plan listing", $this->success);
        }
       
    }
    public function imports(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'journeyplan_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Journey plan import", $this->unauthorized);
        }

        Excel::import(new JourneyPlanImport, request()->file('journeyplan_file'));
        return prepareResult(true, [], [], "Journey plan successfully imported", $this->success);
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $mappingarray = array(
            "Journey Name", "Desc", "Start Date", "End Date", "Start Time", "End Time", "Day Wise", "Week Wise", "First Day Of Week", "Enforce Flag", "Merchandiser", "Customer", "Week1 Sunday", "Week1 Sunday Start Time", "Week1 Sunday End Time", "Week1 Sunday Is MSL", "Week1 Monday", "Week1 Monday Start Time", "Week1 Monday End Time", "Week1 Monday Is MSL", "Week1 Tuesday", "Week1 Tuesday Start Time", "Week1 Tuesday End Time", "Week1 Tuesday Is MSL", "Week1 Wednesday", "Week1 Wednesday Start Time", "Week1 Wednesday End Time", "Week1 Wednesday Is MSL", "Week1 Thrusday",
            "Week1 Thrusday Start Time", "Week1 Thrusday End Time", "Week1 Thrusday Is MSL", "Week1 Friday", "Week1 Friday Start Time", "Week1 Friday End Time", "Week1 Friday Is MSL", "Week1 Saturday", "Week1 Saturday Start Time", "Week1 Saturday End Time", "Week1 Saturday Is MSL", "Week2 Sunday", "Week2 Sunday Start Time", "Week2 Sunday End Time", "Week2 Sunday Is MSL", "Week2 Monday", "Week2 Monday Start Time", "Week2 Monday End Time", "Week2 Monday Is MSL", "Week2 Tuesday", "Week2 Tuesday Start Time", "Week2 Tuesday End Time", "Week2 Tuesday Is MSL", "Week2 Wednesday", "Week2 Wednesday Start Time", "Week2 Wednesday End Time", "Week2 Wednesday Is MSL", "Week2 Thrusday", "Week2 Thrusday Start Time", "Week2 Thrusday End Time", "Week2 Thrusday Is MSL",
            "Week2 Friday", "Week2 Friday Start Time", "Week2 Friday End Time", "Week2 Friday Is MSL", "Week2 Saturday", "Week2 Saturday Start Time", "Week2 Saturday End Time", "Week2 Saturday Is MSL", "Week3 Sunday", "Week3 Sunday Start Time", "Week3 Sunday End Time", "Week3 Sunday Is MSL", "Week3 Monday", "Week3 Monday Start Time", "Week3 Monday End Time", "Week3 Monday Is MSL", "Week3 Tuesday", "Week3 Tuesday Start Time", "Week3 Tuesday End Time", "Week3 Tuesday Is MSL", "Week3 Wednesday", "Week3 Wednesday Start Time", "Week3 Wednesday End Time", "Week3 Wednesday Is MSL", "Week3 Thrusday", "Week3 Thrusday Start Time", "Week3 Thrusday End Time", "Week3 Thrusday Is MSL", "Week3 Friday", "Week3 Friday Start Time",
            "Week3 Friday End Time", "Week3 Friday Is MSL", "Week3 Saturday", "Week3 Saturday Start Time", "Week3 Saturday End Time", "Week3 Saturday Is MSL", "Week4 Sunday", "Week4 Sunday Start Time", "Week4 Sunday End Time", "Week4 Sunday Is MSL", "Week4 Monday", "Week4 Monday Start Time", "Week4 Monday End Time", "Week4 Monday Is MSL", "Week4 Tuesday", "Week4 Tuesday Start Time", "Week4 Tuesday End Time", "Week4 Tuesday Is MSL", "Week4 Wednesday", "Week4 Wednesday Start Time", "Week4 Wednesday End Time", "Week4 Wednesday Is MSL", "Week4 Thrusday", "Week4 Thrusday Start Time", "Week4 Thrusday End Time", "Week4 Thrusday Is MSL", "Week4 Friday", "Week4 Friday Start Time", "Week4 Friday End Time", "Week4 Friday Is MSL", "Week4 Saturday",
            "Week4 Saturday Start Time", "Week4 Saturday End Time", "Week4 Saturday Is MSL", "Week5 Sunday", "Week5 Sunday Start Time", "Week5 Sunday End Time", "Week5 Sunday Is MSL", "Week5 Monday", "Week5 Monday Start Time", "Week5 Monday End Time", "Week5 Monday Is MSL", "Week5 Tuesday", "Week5 Tuesday Start Time", "Week5 Tuesday End Time", "Week5 Tuesday Is MSL", "Week5 Wednesday", "Week5 Wednesday Start Time", "Week5 Wednesday End Time", "Week5 Wednesday Is MSL", "Week5 Thrusday", "Week5 Thrusday Start Time", "Week5 Thrusday End Time", "Week5 Thrusday Is MSL", "Week5 Friday", "Week5 Friday Start Time", "Week5 Friday End Time", "Week5 Friday Is MSL", "Week5 Saturday", "Week5 Saturday Start Time", "Week5 Saturday End Time"
        );

        return prepareResult(true, $mappingarray, [], "journey plan Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'journeyplan_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Jorney Plan import", $this->unauthorized);
        }

        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('journeyplan_file')->store('import');
            $filename = storage_path("app/" . $file);
            $fp = fopen($filename, "r");
            $content = fread($fp, filesize($filename));
            $lines = explode("\n", $content);
            $heading_array_line = isset($lines[1]) ? $lines[1] : '';
            $heading_array = explode(",", trim($heading_array_line));
            fclose($fp);


            // echo "<pre>";
            // print_r($lines);
            // print_r($heading_array);
            // print_r($map_key_value_array);
            // exit;
            if (!$heading_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }
            if (!$map_key_value_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }

            $import = new JourneyPlanImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            //print_r($import);
            //exit;
            $succussrecords = 0;
            $successfileids = 0;
            if ($import->successAllRecords()) {
                // pre($import->successAllRecords());
                $succussrecords = count($import->successAllRecords());
                $data = json_encode($import->successAllRecords());
                $fileName = time() . '_datafile.txt';
                File::put(storage_path() . '/app/tempimport/' . $fileName, $data);

                $importtempfiles = new ImportTempFile;
                $importtempfiles->FileName = $fileName;
                $importtempfiles->save();
                $successfileids = $importtempfiles->id;
            }
            $errorrecords = 0;
            $errror_array = array();
            if ($import->failures()) {

                foreach ($import->failures() as $failure_key => $failure) {
                    //echo $failure_key.'--------'.$failure->row().'||';
                    //print_r($failure);
                    if ($failure->row() != 1) {
                        $failure->row(); // row that went wrong
                        $failure->attribute(); // either heading key (if using heading row concern) or column index
                        $failure->errors(); // Actual error messages from Laravel validator
                        $failure->values(); // The values of the row that has failed.
                        //print_r($failure->errors());

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
                            //$errror_array['errormessage'][] = array("There was an error on row ".$failure->row().". ".$error_msg);
                            //$errror_array['errorresult'][] = $failure->values();
                            $error_result = array();
                            $error_row_loop = 0;
                            foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
                                $error_result[$map_key_value_array_value] = isset($failure->values()[$error_row_loop]) ? $failure->values()[$error_row_loop] : '';
                                $error_row_loop++;
                            }
                            $errror_array[] = array(
                                'errormessage' => "There was an error on row " . $failure->row() . ". " . $error_msg,
                                'errorresult' => $error_result, //$failure->values(),
                                //'attribute' => $failure->attribute(),//$failure->values(),
                                //'error_result' => $error_result,
                                //'map_key_value_array' => $map_key_value_array,
                            );
                        }
                    }
                }
                $errorrecords = count($errror_array);
            }
            //echo '<pre>';
            //print_r($import->failures());
            //echo '</pre>';
            $errors = $errror_array;
            $result['successrecordscount'] = $succussrecords;
            $result['errorrcount'] = $errorrecords;
            $result['successfileids'] = $successfileids;


            //}
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                if ($failure->row() != 1) {
                    info($failure->row());
                    info($failure->attribute());
                    $failure->row(); // row that went wrong
                    $failure->attribute(); // either heading key (if using heading row concern) or column index
                    $failure->errors(); // Actual error messages from Laravel validator
                    $failure->values(); // The values of the row that has failed.
                    $errors[] = $failure->errors();
                }
            }

            return prepareResult(true, [], $errors, "Failed to validate bank import", $this->success);
        }
        return prepareResult(true, $result, $errors, "Journey Plan successfully imported", $this->success);
    }

    public function finalimport2(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            // pre($finaldata);
            // if ($finaldata) :
            //     foreach ($finaldata as $row) :
            //         $customer = CustomerInfo::where('customer_code', $row[11])->first();
            //         $merchandiser = SalesmanInfo::where('salesman_code', $row[10])->first();
            //         $journeyPlan = JourneyPlan::where('name', $row[0])->first();
            //         $current_organisation_id = request()->user()->organisation_id;
            //
            //         if (is_object($journeyPlan)) {
            //             DB::beginTransaction();
            //             try {
            //                 if (!is_object($merchandiser) or !is_object($customer)) {
            //                     if(!is_object($merchandiser)){
            //                         return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
            //                     }
            //                     if(!is_object($customer)){
            //                         return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
            //                     }
            //                 }
            //                 $journeyPlan->name = $row[0];
            //                 $journeyPlan->description = $row[1];
            //                 $journeyPlan->start_date = date('Y-m-d', strtotime($row[2]));
            //
            //                 if (isset($row[3]) and $row[3] != "") {
            //                     $journeyPlan->end_date = date('Y-m-d', strtotime($row[3]));
            //                 } else {
            //                     $journeyPlan->no_end_date = 1;
            //                 }
            //                 $journeyPlan->is_enforce = $row[9];
            //                 $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
            //
            //                 if ($row[6] == "Yes") {
            //                     $planType = 1;
            //                     $dayNumber = 0;
            //                     if ($row[8] == "Monday") {
            //                         $dayNumber = 1;
            //                     } else if ($row[8] == "Tuesday") {
            //                         $dayNumber = 2;
            //                     } else if ($row[8] == "Wednesday") {
            //                         $dayNumber = 3;
            //                     } else if ($row[8] == "Thursday") {
            //                         $dayNumber = 4;
            //                     } else if ($row[8] == "Friday") {
            //                         $dayNumber = 5;
            //                     } else if ($row[8] == "Saturday") {
            //                         $dayNumber = 6;
            //                     } else if ($row[8] == "Sunday") {
            //                         $dayNumber = 7;
            //                     }
            //                     $journeyPlan->start_day_of_the_week = $dayNumber;
            //                 } else if ($row[7] == "Yes") {
            //                     $planType = 2;
            //                 }
            //                 $journeyPlan->plan_type = $planType;
            //                 $weekArray = [];
            //                 $count = 0;
            //                 if ($planType == 2) {
            //                     if (isset($row[12]) and $row[12] != "") {
            //                         $journeyPlan->week_1 = 1;
            //                         $weekArray[$count]['week'] = "week1";
            //                         $weekArray[$count]['column'] = 12;
            //                         $count++;
            //                     }
            //                     if (isset($row[40]) and $row[40] != "") {
            //                         $journeyPlan->week_2 = 1;
            //                         $weekArray[$count]['week'] = "week2";
            //                         $weekArray[$count]['column'] = 33;
            //                         $count++;
            //                     }
            //                     if (isset($row[68]) and $row[68] != "") {
            //                         $journeyPlan->week_3 = 1;
            //                         $weekArray[$count]['week'] = "week3";
            //                         $weekArray[$count]['column'] = 54;
            //                         $count++;
            //                     }
            //                     if (isset($row[96]) and $row[96] != "") {
            //                         $journeyPlan->week_4 = 1;
            //                         $weekArray[$count]['week'] = "week4";
            //                         $weekArray[$count]['column'] = 75;
            //                         $count++;
            //                     }
            //                     if (isset($row[124]) and $row[124] != "") {
            //                         $journeyPlan->week_5 = 1;
            //                         $weekArray[$count]['week'] = "week5";
            //                         $weekArray[$count]['column'] = 96;
            //                     }
            //                 }
            //                 $journeyPlan->save();
            //
            //                 $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                 $preData->delete();
            //                 $journey_plan_weeks_ids = [];
            //                 foreach ($weekArray as $key => $weekData) {
            //                     $journey_plan_weeks = new JourneyPlanWeek;
            //                     $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
            //                     $journey_plan_weeks->week_number = $weekData['week'];
            //                     $journey_plan_weeks->save();
            //
            //                     $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
            //                     $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
            //                     $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
            //                 }
            //
            //                 foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {
            //                     $startColumn = $journeyPlanWeek['column'];
            //                     if ($row[$startColumn] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 1;
            //                         $journey_plan_days->day_name = "Sunday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 1;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 1];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 2];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 3] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 2;
            //                         $journey_plan_days->day_name = "Monday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 2;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 4];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 5];
            //                         $journey_plan_customer->save();
            //
            //                     }
            //                     if ($row[$startColumn + 6] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 3;
            //                         $journey_plan_days->day_name = "Tuesday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 3;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 7];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 8];
            //                         $journey_plan_customer->save();
            //
            //                     }
            //                     if ($row[$startColumn + 9] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 4;
            //                         $journey_plan_days->day_name = "Wednesday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 4;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 10];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 11];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 12] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 5;
            //                         $journey_plan_days->day_name = "Thursday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 5;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 13];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 14];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 15] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 6;
            //                         $journey_plan_days->day_name = "Friday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 6;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 16];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 17];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 18] == "Yes") {
            //                         $preData = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 7;
            //                         $journey_plan_days->day_name = "Saturday";
            //                         $journey_plan_days->save();
            //
            //                         $preData = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id);
            //                         $preData->delete();
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 6;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 19];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 20];
            //                         $journey_plan_customer->save();
            //                     }
            //                 }
            //
            //                 DB::commit();
            //             } catch (\Exception $exception) {
            //                 DB::rollback();
            //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            //             } catch (\Throwable $exception) {
            //                 DB::rollback();
            //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            //             }
            //
            //         } else {
            //             DB::beginTransaction();
            //             try {
            //                 if (!is_object($merchandiser) or !is_object($customer)) {
            //                     if(!is_object($merchandiser)){
            //                         return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
            //                     }
            //                     if(!is_object($customer)){
            //                         return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
            //                     }
            //                 }
            //                 $journeyPlan = new JourneyPlan;
            //                 $journeyPlan->organisation_id = $current_organisation_id;
            //                 $journeyPlan->name = $row[0];
            //                 $journeyPlan->description = $row[1];
            //                 $journeyPlan->start_date = Carbon::createFromFormat('d/m/Y', $row[2])->format('Y-m-d');
            //
            //                 if (isset($row[3]) and $row[3] != "") {
            //                     $journeyPlan->end_date = Carbon::createFromFormat('d/m/Y', $row[3])->format('Y-m-d');
            //                 }
            //                 $journeyPlan->is_enforce = $row[9];
            //                 $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
            //
            //                 if ($row[6] == "Yes") {
            //                     $planType = 1;
            //                     $dayNumber = 0;
            //                     if ($row[8] == "Monday") {
            //                         $dayNumber = 1;
            //                     } else if ($row[8] == "Tuesday") {
            //                         $dayNumber = 2;
            //                     } else if ($row[8] == "Wednesday") {
            //                         $dayNumber = 3;
            //                     } else if ($row[8] == "Thursday") {
            //                         $dayNumber = 4;
            //                     } else if ($row[8] == "Friday") {
            //                         $dayNumber = 5;
            //                     } else if ($row[8] == "Saturday") {
            //                         $dayNumber = 6;
            //                     } else if ($row[8] == "Sunday") {
            //                         $dayNumber = 7;
            //                     }
            //                     $journeyPlan->start_day_of_the_week = $dayNumber;
            //                 } else if ($row[7] == "Yes") {
            //                     $planType = 2;
            //                 }
            //                 $journeyPlan->plan_type = $planType;
            //                 $weekArray = [];
            //                 $count = 0;
            //                 if ($planType == 2) {
            //                     if (isset($row[12]) and $row[12] != "") {
            //                         $journeyPlan->week_1 = 1;
            //                         $weekArray[$count]['week'] = "week1";
            //                         $weekArray[$count]['column'] = 12;
            //                         $count++;
            //                     }
            //                     if (isset($row[40]) and $row[40] != "") {
            //                         $journeyPlan->week_2 = 1;
            //                         $weekArray[$count]['week'] = "week2";
            //                         $weekArray[$count]['column'] = 33;
            //                         $count++;
            //                     }
            //                     if (isset($row[68]) and $row[68] != "") {
            //                         $journeyPlan->week_3 = 1;
            //                         $weekArray[$count]['week'] = "week3";
            //                         $weekArray[$count]['column'] = 54;
            //                         $count++;
            //                     }
            //                     if (isset($row[96]) and $row[96] != "") {
            //                         $journeyPlan->week_4 = 1;
            //                         $weekArray[$count]['week'] = "week4";
            //                         $weekArray[$count]['column'] = 75;
            //                         $count++;
            //                     }
            //                     if (isset($row[124]) and $row[124] != "") {
            //                         $journeyPlan->week_5 = 1;
            //                         $weekArray[$count]['week'] = "week5";
            //                         $weekArray[$count]['column'] = 96;
            //                     }
            //                 }
            //                 $journeyPlan->save();
            //                 $journey_plan_weeks_ids = [];
            //                 foreach ($weekArray as $key => $weekData) {
            //                     $journey_plan_weeks = new JourneyPlanWeek;
            //                     $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
            //                     $journey_plan_weeks->week_number = $weekData['week'];
            //                     $journey_plan_weeks->save();
            //
            //                     $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
            //                     $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
            //                     $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
            //                 }
            //                 foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {
            //                     $startColumn = $journeyPlanWeek['column'];
            //                     if ($row[$startColumn] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 1;
            //                         $journey_plan_days->day_name = "Sunday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 1;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 1];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 2];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 3] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 2;
            //                         $journey_plan_days->day_name = "Monday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 2;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 4];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 5];
            //                         $journey_plan_customer->save();
            //
            //                     }
            //                     if ($row[$startColumn + 6] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 3;
            //                         $journey_plan_days->day_name = "Tuesday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 3;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 7];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 8];
            //                         $journey_plan_customer->save();
            //
            //                     }
            //                     if ($row[$startColumn + 9] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 4;
            //                         $journey_plan_days->day_name = "Wednesday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 4;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 10];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 11];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 12] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 5;
            //                         $journey_plan_days->day_name = "Thursday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 5;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 13];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 14];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 15] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 6;
            //                         $journey_plan_days->day_name = "Friday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 6;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 16];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 17];
            //                         $journey_plan_customer->save();
            //                     }
            //                     if ($row[$startColumn + 18] == "Yes") {
            //                         $journey_plan_days = new JourneyPlanDay;
            //                         $journey_plan_days->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_days->journey_plan_week_id = $journeyPlanWeek['week_id'];
            //                         $journey_plan_days->day_number = 7;
            //                         $journey_plan_days->day_name = "Saturday";
            //                         $journey_plan_days->save();
            //
            //                         $journey_plan_customer = new JourneyPlanCustomer;
            //                         $journey_plan_customer->journey_plan_id = $journeyPlanWeek['journey_id'];
            //                         $journey_plan_customer->journey_plan_day_id = $journey_plan_days->id;
            //                         $journey_plan_customer->customer_id = (is_object($customer)) ? $customer->id : 0;
            //                         $journey_plan_customer->day_customer_sequence = 6;
            //                         $journey_plan_customer->day_start_time = $row[$startColumn + 19];
            //                         $journey_plan_customer->day_end_time = $row[$startColumn + 20];
            //                         $journey_plan_customer->save();
            //                     }
            //                 }
            //
            //                 DB::commit();
            //             } catch (\Exception $exception) {
            //                 DB::rollback();
            //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            //             } catch (\Throwable $exception) {
            //                 DB::rollback();
            //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            //             }
            //         }
            //     endforeach;
            //     unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            //     DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            // endif;
            return prepareResult(true, [], [], "journey plan successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function finalimport_old(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        $skipduplicate = $request->skipduplicate;

        //$skipduplicate = 1 means skip the data
        //$skipduplicate = 0 means overwrite the data

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            $current_organisation_id = request()->user()->organisation_id;
            $old_jp = 0;
            if ($finaldata) :
                foreach ($finaldata as $rKey => $row) :
                    if ($skipduplicate == 1) {

                        $customer = CustomerInfo::where('customer_code', $row[11])->first();
                        $customer_code = $customer->user_id;
                        $merchandiser = SalesmanInfo::where('salesman_code', $row[10])->first();

                        // if jp is their
                        $journeyPlan = JourneyPlan::where('merchandiser_id', $merchandiser->user_id)
                            ->first();

                        if (!is_object($journeyPlan)) {
                            $journeyPlan = new JourneyPlan;
                        } else {
                            // if jp customer
                            $plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                ->where('customer_id', $customer_code)
                                ->first();

                            if (is_object($plan_customer)) {
                                continue;
                            }
                        }

                        DB::beginTransaction();
                        try {
                            if (!is_object($merchandiser) or !is_object($customer)) {
                                if (!is_object($merchandiser)) {
                                    return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
                                }
                                if (!is_object($customer)) {
                                    return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
                                }
                            }

                            $save = true;
                            if (isset($journeyPlan->id) && $journeyPlan->id) {
                                $save = false;
                            }
                            // $journeyPlan = new JourneyPlan;
                            $journeyPlan->organisation_id = $current_organisation_id;
                            $journeyPlan->name = $row[0];
                            $journeyPlan->description = $row[1];
                            $journeyPlan->start_date = date('Y-m-d', strtotime($row[2]));

                            if (isset($row[3]) and $row[3] != "") {
                                $journeyPlan->end_date = date('Y-m-d', strtotime($row[3]));
                                $journeyPlan->no_end_date = 0;
                            } else {
                                $journeyPlan->no_end_date = 1;
                            }

                            if ($row[9] == 'No') {
                                $journeyPlan->is_enforce = 0;
                            } else {
                                $journeyPlan->is_enforce = 1;
                            }

                            if (is_object($merchandiser)) {
                                $journeyPlan->is_merchandiser = 1;
                                $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
                            } else {
                                $journeyPlan->merchandiser_id = Null;
                                $journeyPlan->is_merchandiser = 0;
                            }

                            if ($row[6] == "Yes") {
                                $planType = 1;
                                $dayNumber = 0;
                                if ($row[8] == "Monday") {
                                    $dayNumber = 1;
                                } else if ($row[8] == "Tuesday") {
                                    $dayNumber = 2;
                                } else if ($row[8] == "Wednesday") {
                                    $dayNumber = 3;
                                } else if ($row[8] == "Thursday") {
                                    $dayNumber = 4;
                                } else if ($row[8] == "Friday") {
                                    $dayNumber = 5;
                                } else if ($row[8] == "Saturday") {
                                    $dayNumber = 6;
                                } else if ($row[8] == "Sunday") {
                                    $dayNumber = 7;
                                }
                                $journeyPlan->start_day_of_the_week = $dayNumber;
                            } else if ($row[7] == "Yes") {
                                $planType = 2;
                                $dayNumber = 0;
                                if ($row[8] == "Monday") {
                                    $dayNumber = 1;
                                } else if ($row[8] == "Tuesday") {
                                    $dayNumber = 2;
                                } else if ($row[8] == "Wednesday") {
                                    $dayNumber = 3;
                                } else if ($row[8] == "Thursday") {
                                    $dayNumber = 4;
                                } else if ($row[8] == "Friday") {
                                    $dayNumber = 5;
                                } else if ($row[8] == "Saturday") {
                                    $dayNumber = 6;
                                } else if ($row[8] == "Sunday") {
                                    $dayNumber = 7;
                                }
                                $journeyPlan->start_day_of_the_week = $dayNumber;
                            }
                            $journeyPlan->plan_type = $planType;
                            $weekArray = [];
                            $monthArray = [];
                            $count = 0;

                            if ($planType == 2) {
                                if (
                                    (isset($row[12]) and $row[12] != "") ||
                                    (isset($row[16]) and $row[16] != "") ||
                                    (isset($row[20]) and $row[20] != "") ||
                                    (isset($row[24]) and $row[24] != "") ||
                                    (isset($row[28]) and $row[28] != "") ||
                                    (isset($row[32]) and $row[32] != "") ||
                                    (isset($row[36]) and $row[36] != "")
                                ) {
                                    $journeyPlan->week_1 = 1;
                                    $weekArray[$count]['week'] = "week1";
                                    $weekArray[$count]['column'] = 12;
                                    $count++;
                                }

                                if (
                                    (isset($row[40]) and $row[40] != "") ||
                                    (isset($row[44]) and $row[44] != "") ||
                                    (isset($row[48]) and $row[48] != "") ||
                                    (isset($row[52]) and $row[52] != "") ||
                                    (isset($row[56]) and $row[56] != "") ||
                                    (isset($row[60]) and $row[60] != "") ||
                                    (isset($row[64]) and $row[64] != "")
                                ) {
                                    $journeyPlan->week_2 = 2;
                                    $weekArray[$count]['week'] = "week2";
                                    $weekArray[$count]['column'] = 40;
                                    $count++;
                                }

                                if (
                                    (isset($row[68]) and $row[68] != "") ||
                                    (isset($row[72]) and $row[72] != "") ||
                                    (isset($row[76]) and $row[76] != "") ||
                                    (isset($row[80]) and $row[80] != "") ||
                                    (isset($row[84]) and $row[84] != "") ||
                                    (isset($row[88]) and $row[88] != "") ||
                                    (isset($row[92]) and $row[92] != "")
                                ) {
                                    $journeyPlan->week_3 = 3;
                                    $weekArray[$count]['week'] = "week3";
                                    $weekArray[$count]['column'] = 68;
                                    $count++;
                                }

                                if (
                                    (isset($row[96]) and $row[96] != "") ||
                                    (isset($row[100]) and $row[100] != "") ||
                                    (isset($row[104]) and $row[104] != "") ||
                                    (isset($row[108]) and $row[108] != "") ||
                                    (isset($row[112]) and $row[112] != "") ||
                                    (isset($row[116]) and $row[116] != "") ||
                                    (isset($row[120]) and $row[120] != "")
                                ) {
                                    $journeyPlan->week_4 = 4;
                                    $weekArray[$count]['week'] = "week4";
                                    $weekArray[$count]['column'] = 96;
                                    $count++;
                                }

                                if (
                                    (isset($row[124]) and $row[124] != "") ||
                                    (isset($row[128]) and $row[128] != "") ||
                                    (isset($row[132]) and $row[132] != "") ||
                                    (isset($row[136]) and $row[136] != "") ||
                                    (isset($row[140]) and $row[140] != "") ||
                                    (isset($row[144]) and $row[144] != "") ||
                                    (isset($row[148]) and $row[148] != "")
                                ) {
                                    $journeyPlan->week_5 = 5;
                                    $weekArray[$count]['week'] = "week5";
                                    $weekArray[$count]['column'] = 124;
                                }
                            }

                            if ($planType == 1) {
                                if (
                                    (isset($row[12]) and $row[12] != "") ||
                                    (isset($row[40]) and $row[40] != "") ||
                                    (isset($row[68]) and $row[68] != "") ||
                                    (isset($row[96]) and $row[96] != "") ||
                                    (isset($row[124]) and $row[124] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 7;
                                    $monthArray[$count]['day_name'] = "Sunday";
                                    if (isset($row[12]) and $row[12] != "") {
                                        $monthArray[$count]['column'] = 12;
                                    } else if ((isset($row[40]) and $row[40] != "")) {
                                        $monthArray[$count]['column'] = 40;
                                    } else if ((isset($row[68]) and $row[68] != "")) {
                                        $monthArray[$count]['column'] = 68;
                                    } else if ((isset($row[96]) and $row[96] != "")) {
                                        $monthArray[$count]['column'] = 96;
                                    } else if ((isset($row[124]) and $row[124] != "")) {
                                        $monthArray[$count]['column'] = 124;
                                    }
                                    $count++;
                                }

                                if (
                                    (isset($row[16]) and $row[16] != "") ||
                                    (isset($row[44]) and $row[44] != "") ||
                                    (isset($row[72]) and $row[72] != "") ||
                                    (isset($row[100]) and $row[100] != "") ||
                                    (isset($row[128]) and $row[128] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 1;
                                    $monthArray[$count]['day_name'] = "Monday";
                                    if (isset($row[16]) and $row[16] != "") {
                                        $monthArray[$count]['column'] = 16;
                                    } else if ((isset($row[44]) and $row[44] != "")) {
                                        $monthArray[$count]['column'] = 44;
                                    } else if ((isset($row[72]) and $row[72] != "")) {
                                        $monthArray[$count]['column'] = 72;
                                    } else if ((isset($row[100]) and $row[100] != "")) {
                                        $monthArray[$count]['column'] = 100;
                                    } else if ((isset($row[128]) and $row[128] != "")) {
                                        $monthArray[$count]['column'] = 128;
                                    }
                                    $count++;
                                }

                                if (
                                    (isset($row[20]) and $row[20] != "") ||
                                    (isset($row[48]) and $row[48] != "") ||
                                    (isset($row[76]) and $row[76] != "") ||
                                    (isset($row[104]) and $row[104] != "") ||
                                    (isset($row[132]) and $row[132] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 2;
                                    $monthArray[$count]['day_name'] = "Tuesday";
                                    if (isset($row[20]) and $row[20] != "") {
                                        $monthArray[$count]['column'] = 20;
                                    } else if ((isset($row[48]) and $row[48] != "")) {
                                        $monthArray[$count]['column'] = 48;
                                    } else if ((isset($row[76]) and $row[76] != "")) {
                                        $monthArray[$count]['column'] = 76;
                                    } else if ((isset($row[104]) and $row[104] != "")) {
                                        $monthArray[$count]['column'] = 104;
                                    } else if ((isset($row[132]) and $row[132] != "")) {
                                        $monthArray[$count]['column'] = 132;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[24]) and $row[24] != "") ||
                                    (isset($row[52]) and $row[52] != "") ||
                                    (isset($row[80]) and $row[80] != "") ||
                                    (isset($row[108]) and $row[108] != "") ||
                                    (isset($row[136]) and $row[136] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 3;
                                    $monthArray[$count]['day_name'] = "Wednesday";
                                    if (isset($row[24]) and $row[24] != "") {
                                        $monthArray[$count]['column'] = 24;
                                    } else if ((isset($row[52]) and $row[52] != "")) {
                                        $monthArray[$count]['column'] = 52;
                                    } else if ((isset($row[80]) and $row[80] != "")) {
                                        $monthArray[$count]['column'] = 80;
                                    } else if ((isset($row[108]) and $row[108] != "")) {
                                        $monthArray[$count]['column'] = 108;
                                    } else if ((isset($row[136]) and $row[152] != "")) {
                                        $monthArray[$count]['column'] = 152;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[28]) and $row[28] != "") ||
                                    (isset($row[56]) and $row[56] != "") ||
                                    (isset($row[84]) and $row[84] != "") ||
                                    (isset($row[112]) and $row[112] != "") ||
                                    (isset($row[140]) and $row[140] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 4;
                                    $monthArray[$count]['day_name'] = "Thursday";
                                    if (isset($row[28]) and $row[28] != "") {
                                        $monthArray[$count]['column'] = 28;
                                    } else if ((isset($row[56]) and $row[56] != "")) {
                                        $monthArray[$count]['column'] = 56;
                                    } else if ((isset($row[84]) and $row[84] != "")) {
                                        $monthArray[$count]['column'] = 65;
                                    } else if ((isset($row[112]) and $row[112] != "")) {
                                        $monthArray[$count]['column'] = 112;
                                    } else if ((isset($row[140]) and $row[140] != "")) {
                                        $monthArray[$count]['column'] = 140;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[32]) and $row[32] != "") ||
                                    (isset($row[60]) and $row[60] != "") ||
                                    (isset($row[88]) and $row[88] != "") ||
                                    (isset($row[116]) and $row[116] != "") ||
                                    (isset($row[144]) and $row[144] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 5;
                                    $monthArray[$count]['day_name'] = "Friday";
                                    if (isset($row[32]) and $row[32] != "") {
                                        $monthArray[$count]['column'] = 32;
                                    } else if ((isset($row[60]) and $row[60] != "")) {
                                        $monthArray[$count]['column'] = 60;
                                    } else if ((isset($row[88]) and $row[88] != "")) {
                                        $monthArray[$count]['column'] = 88;
                                    } else if ((isset($row[116]) and $row[116] != "")) {
                                        $monthArray[$count]['column'] = 116;
                                    } else if ((isset($row[144]) and $row[144] != "")) {
                                        $monthArray[$count]['column'] = 111;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[36]) and $row[36] != "") ||
                                    (isset($row[64]) and $row[64] != "") ||
                                    (isset($row[92]) and $row[92] != "") ||
                                    (isset($row[94]) and $row[94] != "") ||
                                    (isset($row[148]) and $row[148] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 6;
                                    $monthArray[$count]['day_name'] = "Saturday";
                                    if (isset($row[36]) and $row[36] != "") {
                                        $monthArray[$count]['column'] = 36;
                                    } else if ((isset($row[64]) and $row[64] != "")) {
                                        $monthArray[$count]['column'] = 64;
                                    } else if ((isset($row[92]) and $row[92] != "")) {
                                        $monthArray[$count]['column'] = 92;
                                    } else if ((isset($row[120]) and $row[120] != "")) {
                                        $monthArray[$count]['column'] = 120;
                                    } else if ((isset($row[148]) and $row[148] != "")) {
                                        $monthArray[$count]['column'] = 114;
                                    }
                                    $count++;
                                }
                            }

                            $journeyPlan->status = 1;
                            $journeyPlan->current_stage = "Approved";

                            if ($save == true) {
                                $journeyPlan->save();
                            }

                            $journey_plan_months_ids = [];
                            foreach ($monthArray as $key => $monthData) {

                                $journey_plan_days = new JourneyPlanDay;

                                $journey_plan_days->journey_plan_id = $journeyPlan->id;
                                $journey_plan_days->journey_plan_week_id = NULL;
                                $journey_plan_days->day_number = $monthData['day_number'];
                                $journey_plan_days->day_name = $monthData['day_name'];
                                $journey_plan_days->save();

                                $start_time = "10:00";
                                $end_time = "06:00";

                                if ($row[$monthData['column']] == "Yes") {
                                    $start_time = $row[$monthData['column'] + 1];
                                    $end_time = $row[$monthData['column'] + 2];
                                }
                                $is_msl = ($row[$monthData['column'] + 3] == "Yes") ? 1 : 0;

                                $journey_plan_customer = $this->savePlanCustomer(
                                    $journeyPlan->id,
                                    $journey_plan_days->id,
                                    (is_object($customer)) ? $customer->id : 0,
                                    $start_time,
                                    $end_time,
                                    (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                    $is_msl
                                );
                            }

                            $journey_plan_weeks_ids = [];
                            foreach ($weekArray as $key => $weekData) {
                                if (isset($journeyPlan->id) && $journeyPlan->id) {
                                    $journey_plan_weeks = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id)
                                        ->where('week_number', $weekData['week'])
                                        ->first();
                                    if (!is_object($journey_plan_weeks)) {
                                        $journey_plan_weeks = new JourneyPlanWeek;
                                    }
                                } else {
                                    $journey_plan_weeks = new JourneyPlanWeek;
                                }
                                $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
                                $journey_plan_weeks->week_number = $weekData['week'];
                                $journey_plan_weeks->save();

                                $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
                                $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
                                $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
                            }


                            foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {

                                $startColumn = $journeyPlanWeek['column'];

                                if (isset($row[$startColumn]) && $row[$startColumn]) {

                                    $journey_plan_days = $this->savePlanDay(
                                        $journeyPlanWeek['journey_id'],
                                        $journeyPlanWeek['week_id'],
                                        7,
                                        "Sunday"
                                    );

                                    $is_msl = ($row[$startColumn + 3] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'], // Jorney Plan ID
                                        $journey_plan_days->id, // Jorney Plan Day ID
                                        (is_object($customer)) ? $customer->id : 0, // Customer info Id
                                        $row[$startColumn + 1], // Jorney Plan Start Time
                                        $row[$startColumn + 2], // Jorney Plan End Time
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0, // Jorney Plan Merchandiser id
                                        $is_msl // Customer is MSL
                                    );
                                }

                                if (isset($row[$startColumn + 4]) && $row[$startColumn + 4]) {

                                    $journey_plan_days = $this->savePlanDay(
                                        $journeyPlanWeek['journey_id'],
                                        $journeyPlanWeek['week_id'],
                                        1,
                                        "Monday"
                                    );

                                    $is_msl = ($row[$startColumn + 7] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 5],
                                        $row[$startColumn + 6],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                if (isset($row[$startColumn + 8]) && $row[$startColumn + 8]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 2, "Tuesday");

                                    $is_msl = ($row[$startColumn + 11] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 9],
                                        $row[$startColumn + 10],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                if (isset($row[$startColumn + 12]) && $row[$startColumn + 12]) {

                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 3, "Wednesday");

                                    $is_msl = ($row[$startColumn + 15] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 13],
                                        $row[$startColumn + 14],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                if (isset($row[$startColumn + 16]) && $row[$startColumn + 16]) {

                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 4, "Thursday");

                                    $is_msl = ($row[$startColumn + 19] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 17],
                                        $row[$startColumn + 18],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                if (isset($row[$startColumn + 20]) && $row[$startColumn + 20]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 5, "Friday");

                                    $is_msl = ($row[$startColumn + 23] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 21],
                                        $row[$startColumn + 22],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                if (isset($row[$startColumn + 24]) && $row[$startColumn + 24]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 6, "Saturday");

                                    $is_msl = ($row[$startColumn + 27] == "Yes") ? 1 : 0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        6,
                                        $row[$startColumn + 25],
                                        $row[$startColumn + 26],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0
                                    );
                                }
                            }

                            DB::commit();
                        } catch (\Exception $exception) {
                            DB::rollback();
                            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        } catch (\Throwable $exception) {
                            DB::rollback();
                            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    } else {
                        $customer = CustomerInfo::where('customer_code', $row[11])->first();
                        $customer_code = $customer->user_id;
                        $merchandiser = SalesmanInfo::where('salesman_code', $row[10])->first();

                        $journeyPlan = JourneyPlan::where('merchandiser_id', $merchandiser->user_id)
                            ->first();

                        if (!is_object($journeyPlan)) {
                            $journeyPlan = new JourneyPlan;
                        } else {
                            $plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                ->where('customer_id', $customer_code)
                                ->first();
                        }

                        if (is_object($journeyPlan)) {
                            DB::beginTransaction();
                            try {
                                if (!is_object($merchandiser) or !is_object($customer)) {
                                    if (!is_object($merchandiser)) {
                                        return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
                                    }
                                    if (!is_object($customer)) {
                                        return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
                                    }
                                }

                                $new_jp = $journeyPlan->id;

                                if ($new_jp != $old_jp) {
                                    CustomerMerchandiser::where('merchandiser_id', $merchandiser->user_id)->delete();

                                    JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                        ->delete();

                                    $old_jp = $journeyPlan->id;
                                }

                                $save = true;
                                if (isset($journeyPlan->id) && $journeyPlan->id) {
                                    $save = false;
                                }
                                $journeyPlan->organisation_id = $current_organisation_id;
                                $journeyPlan->name = $row[0];
                                $journeyPlan->description = $row[1];
                                $journeyPlan->start_date = date('Y-m-d', strtotime($row[2]));

                                if (isset($row[3]) and $row[3] != "") {
                                    $journeyPlan->end_date = date('Y-m-d', strtotime($row[3]));
                                    $journeyPlan->no_end_date = 0;
                                } else {
                                    $journeyPlan->no_end_date = 1;
                                }

                                if ($row[9] == 'No') {
                                    $journeyPlan->is_enforce = 0;
                                } else {
                                    $journeyPlan->is_enforce = 1;
                                }

                                if (is_object($merchandiser)) {
                                    $journeyPlan->is_merchandiser = 1;
                                    $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
                                } else {
                                    $journeyPlan->merchandiser_id = Null;
                                    $journeyPlan->is_merchandiser = 0;
                                }

                                if ($row[6] == "Yes") {
                                    $planType = 1;
                                    $dayNumber = 0;
                                    if ($row[8] == "Monday") {
                                        $dayNumber = 1;
                                    } else if ($row[8] == "Tuesday") {
                                        $dayNumber = 2;
                                    } else if ($row[8] == "Wednesday") {
                                        $dayNumber = 3;
                                    } else if ($row[8] == "Thursday") {
                                        $dayNumber = 4;
                                    } else if ($row[8] == "Friday") {
                                        $dayNumber = 5;
                                    } else if ($row[8] == "Saturday") {
                                        $dayNumber = 6;
                                    } else if ($row[8] == "Sunday") {
                                        $dayNumber = 7;
                                    }
                                    $journeyPlan->start_day_of_the_week = $dayNumber;
                                } else if ($row[7] == "Yes") {
                                    $planType = 2;
                                    $dayNumber = 0;
                                    if ($row[8] == "Monday") {
                                        $dayNumber = 1;
                                    } else if ($row[8] == "Tuesday") {
                                        $dayNumber = 2;
                                    } else if ($row[8] == "Wednesday") {
                                        $dayNumber = 3;
                                    } else if ($row[8] == "Thursday") {
                                        $dayNumber = 4;
                                    } else if ($row[8] == "Friday") {
                                        $dayNumber = 5;
                                    } else if ($row[8] == "Saturday") {
                                        $dayNumber = 6;
                                    } else if ($row[8] == "Sunday") {
                                        $dayNumber = 7;
                                    }
                                    $journeyPlan->start_day_of_the_week = $dayNumber;
                                }

                                $journeyPlan->plan_type = $planType;
                                $weekArray = [];
                                $monthArray = [];
                                $count = 0;

                                if ($planType == 2) {
                                    if (
                                        (isset($row[12]) and $row[12] != "") ||
                                        (isset($row[16]) and $row[16] != "") ||
                                        (isset($row[20]) and $row[20] != "") ||
                                        (isset($row[24]) and $row[24] != "") ||
                                        (isset($row[28]) and $row[28] != "") ||
                                        (isset($row[32]) and $row[32] != "") ||
                                        (isset($row[36]) and $row[36] != "")
                                    ) {
                                        $journeyPlan->week_1 = 1;
                                        $weekArray[$count]['week'] = "week1";
                                        $weekArray[$count]['column'] = 12;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[40]) and $row[40] != "") ||
                                        (isset($row[44]) and $row[44] != "") ||
                                        (isset($row[48]) and $row[48] != "") ||
                                        (isset($row[52]) and $row[52] != "") ||
                                        (isset($row[56]) and $row[56] != "") ||
                                        (isset($row[60]) and $row[60] != "") ||
                                        (isset($row[64]) and $row[64] != "")
                                    ) {
                                        $journeyPlan->week_2 = 2;
                                        $weekArray[$count]['week'] = "week2";
                                        $weekArray[$count]['column'] = 40;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[68]) and $row[68] != "") ||
                                        (isset($row[72]) and $row[72] != "") ||
                                        (isset($row[76]) and $row[76] != "") ||
                                        (isset($row[80]) and $row[80] != "") ||
                                        (isset($row[84]) and $row[84] != "") ||
                                        (isset($row[88]) and $row[88] != "") ||
                                        (isset($row[92]) and $row[92] != "")
                                    ) {
                                        $journeyPlan->week_3 = 3;
                                        $weekArray[$count]['week'] = "week3";
                                        $weekArray[$count]['column'] = 68;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[96]) and $row[96] != "") ||
                                        (isset($row[100]) and $row[100] != "") ||
                                        (isset($row[104]) and $row[104] != "") ||
                                        (isset($row[108]) and $row[108] != "") ||
                                        (isset($row[112]) and $row[112] != "") ||
                                        (isset($row[116]) and $row[116] != "") ||
                                        (isset($row[120]) and $row[120] != "")
                                    ) {
                                        $journeyPlan->week_4 = 4;
                                        $weekArray[$count]['week'] = "week4";
                                        $weekArray[$count]['column'] = 96;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[124]) and $row[124] != "") ||
                                        (isset($row[128]) and $row[128] != "") ||
                                        (isset($row[132]) and $row[132] != "") ||
                                        (isset($row[136]) and $row[136] != "") ||
                                        (isset($row[140]) and $row[140] != "") ||
                                        (isset($row[144]) and $row[144] != "") ||
                                        (isset($row[148]) and $row[148] != "")
                                    ) {
                                        $journeyPlan->week_5 = 5;
                                        $weekArray[$count]['week'] = "week5";
                                        $weekArray[$count]['column'] = 124;
                                    }
                                }

                                if ($planType == 1) {
                                    if (
                                        (isset($row[12]) and $row[12] != "") ||
                                        (isset($row[40]) and $row[40] != "") ||
                                        (isset($row[68]) and $row[68] != "") ||
                                        (isset($row[96]) and $row[96] != "") ||
                                        (isset($row[124]) and $row[124] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 7;
                                        $monthArray[$count]['day_name'] = "Sunday";
                                        if (isset($row[12]) and $row[12] != "") {
                                            $monthArray[$count]['column'] = 12;
                                        } else if ((isset($row[40]) and $row[40] != "")) {
                                            $monthArray[$count]['column'] = 40;
                                        } else if ((isset($row[68]) and $row[68] != "")) {
                                            $monthArray[$count]['column'] = 68;
                                        } else if ((isset($row[96]) and $row[96] != "")) {
                                            $monthArray[$count]['column'] = 96;
                                        } else if ((isset($row[124]) and $row[124] != "")) {
                                            $monthArray[$count]['column'] = 124;
                                        }
                                        $count++;
                                    }

                                    if (
                                        (isset($row[16]) and $row[16] != "") ||
                                        (isset($row[44]) and $row[44] != "") ||
                                        (isset($row[72]) and $row[72] != "") ||
                                        (isset($row[100]) and $row[100] != "") ||
                                        (isset($row[128]) and $row[128] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 1;
                                        $monthArray[$count]['day_name'] = "Monday";
                                        if (isset($row[16]) and $row[16] != "") {
                                            $monthArray[$count]['column'] = 16;
                                        } else if ((isset($row[44]) and $row[44] != "")) {
                                            $monthArray[$count]['column'] = 44;
                                        } else if ((isset($row[72]) and $row[72] != "")) {
                                            $monthArray[$count]['column'] = 72;
                                        } else if ((isset($row[100]) and $row[100] != "")) {
                                            $monthArray[$count]['column'] = 100;
                                        } else if ((isset($row[128]) and $row[128] != "")) {
                                            $monthArray[$count]['column'] = 128;
                                        }
                                        $count++;
                                    }

                                    if (
                                        (isset($row[20]) and $row[20] != "") ||
                                        (isset($row[48]) and $row[48] != "") ||
                                        (isset($row[76]) and $row[76] != "") ||
                                        (isset($row[104]) and $row[104] != "") ||
                                        (isset($row[132]) and $row[132] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 2;
                                        $monthArray[$count]['day_name'] = "Tuesday";
                                        if (isset($row[20]) and $row[20] != "") {
                                            $monthArray[$count]['column'] = 20;
                                        } else if ((isset($row[48]) and $row[48] != "")) {
                                            $monthArray[$count]['column'] = 48;
                                        } else if ((isset($row[76]) and $row[76] != "")) {
                                            $monthArray[$count]['column'] = 76;
                                        } else if ((isset($row[104]) and $row[104] != "")) {
                                            $monthArray[$count]['column'] = 104;
                                        } else if ((isset($row[132]) and $row[132] != "")) {
                                            $monthArray[$count]['column'] = 132;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[24]) and $row[24] != "") ||
                                        (isset($row[52]) and $row[52] != "") ||
                                        (isset($row[80]) and $row[80] != "") ||
                                        (isset($row[108]) and $row[108] != "") ||
                                        (isset($row[136]) and $row[136] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 3;
                                        $monthArray[$count]['day_name'] = "Wednesday";
                                        if (isset($row[24]) and $row[24] != "") {
                                            $monthArray[$count]['column'] = 24;
                                        } else if ((isset($row[52]) and $row[52] != "")) {
                                            $monthArray[$count]['column'] = 52;
                                        } else if ((isset($row[80]) and $row[80] != "")) {
                                            $monthArray[$count]['column'] = 80;
                                        } else if ((isset($row[108]) and $row[108] != "")) {
                                            $monthArray[$count]['column'] = 108;
                                        } else if ((isset($row[136]) and $row[152] != "")) {
                                            $monthArray[$count]['column'] = 152;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[28]) and $row[28] != "") ||
                                        (isset($row[56]) and $row[56] != "") ||
                                        (isset($row[84]) and $row[84] != "") ||
                                        (isset($row[112]) and $row[112] != "") ||
                                        (isset($row[140]) and $row[140] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 4;
                                        $monthArray[$count]['day_name'] = "Thursday";
                                        if (isset($row[28]) and $row[28] != "") {
                                            $monthArray[$count]['column'] = 28;
                                        } else if ((isset($row[56]) and $row[56] != "")) {
                                            $monthArray[$count]['column'] = 56;
                                        } else if ((isset($row[84]) and $row[84] != "")) {
                                            $monthArray[$count]['column'] = 65;
                                        } else if ((isset($row[112]) and $row[112] != "")) {
                                            $monthArray[$count]['column'] = 112;
                                        } else if ((isset($row[140]) and $row[140] != "")) {
                                            $monthArray[$count]['column'] = 140;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[32]) and $row[32] != "") ||
                                        (isset($row[60]) and $row[60] != "") ||
                                        (isset($row[88]) and $row[88] != "") ||
                                        (isset($row[116]) and $row[116] != "") ||
                                        (isset($row[144]) and $row[144] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 5;
                                        $monthArray[$count]['day_name'] = "Friday";
                                        if (isset($row[32]) and $row[32] != "") {
                                            $monthArray[$count]['column'] = 32;
                                        } else if ((isset($row[60]) and $row[60] != "")) {
                                            $monthArray[$count]['column'] = 60;
                                        } else if ((isset($row[88]) and $row[88] != "")) {
                                            $monthArray[$count]['column'] = 88;
                                        } else if ((isset($row[116]) and $row[116] != "")) {
                                            $monthArray[$count]['column'] = 116;
                                        } else if ((isset($row[144]) and $row[144] != "")) {
                                            $monthArray[$count]['column'] = 111;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[36]) and $row[36] != "") ||
                                        (isset($row[64]) and $row[64] != "") ||
                                        (isset($row[92]) and $row[92] != "") ||
                                        (isset($row[94]) and $row[94] != "") ||
                                        (isset($row[148]) and $row[148] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 6;
                                        $monthArray[$count]['day_name'] = "Saturday";
                                        if (isset($row[36]) and $row[36] != "") {
                                            $monthArray[$count]['column'] = 36;
                                        } else if ((isset($row[64]) and $row[64] != "")) {
                                            $monthArray[$count]['column'] = 64;
                                        } else if ((isset($row[92]) and $row[92] != "")) {
                                            $monthArray[$count]['column'] = 92;
                                        } else if ((isset($row[120]) and $row[120] != "")) {
                                            $monthArray[$count]['column'] = 120;
                                        } else if ((isset($row[148]) and $row[148] != "")) {
                                            $monthArray[$count]['column'] = 114;
                                        }
                                        $count++;
                                    }
                                }

                                $journeyPlan->status = 1;
                                $journeyPlan->current_stage = "Approved";
                                if ($save == true) {
                                    $journeyPlan->save();
                                }

                                $journey_plan_months_ids = [];

                                foreach ($monthArray as $key => $monthData) {

                                    $journey_plan_days = new JourneyPlanDay;

                                    $journey_plan_days->journey_plan_id = $journeyPlan->id;
                                    $journey_plan_days->journey_plan_week_id = NULL;
                                    $journey_plan_days->day_number = $monthData['day_number'];
                                    $journey_plan_days->day_name = $monthData['day_name'];
                                    $journey_plan_days->save();

                                    $start_time = "10:00";
                                    $end_time = "06:00";

                                    if ($row[$monthData['column']] == "Yes") {
                                        $start_time = $row[$monthData['column'] + 1];
                                        $end_time = $row[$monthData['column'] + 2];
                                    }
                                    $is_msl =  0;

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlan->id,
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $start_time,
                                        $end_time,
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl
                                    );
                                }

                                $journey_plan_weeks_ids = [];
                                foreach ($weekArray as $key => $weekData) {
                                    if (isset($journeyPlan->id) && $journeyPlan->id) {
                                        $journey_plan_weeks = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id)
                                            ->where('week_number', $weekData['week'])
                                            ->first();
                                        if (!is_object($journey_plan_weeks)) {
                                            $journey_plan_weeks = new JourneyPlanWeek;
                                        }
                                    } else {
                                        $journey_plan_weeks = new JourneyPlanWeek;
                                    }
                                    $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
                                    $journey_plan_weeks->week_number = $weekData['week'];
                                    $journey_plan_weeks->save();

                                    $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
                                    $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
                                    $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
                                }

                                foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {

                                    $startColumn = $journeyPlanWeek['column'];

                                    if (isset($row[$startColumn]) && $row[$startColumn]) {

                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            7,
                                            "Sunday"
                                        );

                                        $is_msl = ($row[$startColumn + 3] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 1],
                                            $row[$startColumn + 2],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 4]) && $row[$startColumn + 4]) {
                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 1, "Monday");
                                        $is_msl = ($row[$startColumn + 7] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 5],
                                            $row[$startColumn + 6],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 8]) && $row[$startColumn + 8]) {
                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 2, "Tuesday");

                                        $is_msl = ($row[$startColumn + 11] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 9],
                                            $row[$startColumn + 10],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 12]) && $row[$startColumn + 12]) {

                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 3, "Wednesday");

                                        $is_msl = ($row[$startColumn + 15] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 13],
                                            $row[$startColumn + 14],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 16]) && $row[$startColumn + 16]) {

                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 4, "Thursday");

                                        $is_msl = ($row[$startColumn + 19] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 17],
                                            $row[$startColumn + 18],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 20]) && $row[$startColumn + 20]) {
                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 5, "Friday");

                                        $is_msl = ($row[$startColumn + 23] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 21],
                                            $row[$startColumn + 22],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl
                                        );
                                    }

                                    if (isset($row[$startColumn + 24]) && $row[$startColumn + 24]) {
                                        $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 6, "Saturday");

                                        $is_msl = ($row[$startColumn + 27] == "Yes") ? 1 : 0;

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            6,
                                            $row[$startColumn + 25],
                                            $row[$startColumn + 26],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0
                                        );
                                    }
                                }

                                DB::commit();
                            } catch (\Exception $exception) {
                                DB::rollback();
                                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            } catch (\Throwable $exception) {
                                DB::rollback();
                                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            }
                        }
                    }
                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "journey plan successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function finalimport(Request $request)
    {   
        // echo "123";die;
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        $skipduplicate = $request->skipduplicate;

        //$skipduplicate = 1 means skip the data
        //$skipduplicate = 0 means overwrite the data

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            $current_organisation_id = request()->user()->organisation_id;
            $old_jp = 0;

            if ($finaldata) :
                foreach ($finaldata as $rKey => $row) :
                    if ($skipduplicate == 1) {

                        $customer = CustomerInfo::where('customer_code', $row[11])->first();
                        $customer_code = $customer->user_id;
                        $merchandiser = SalesmanInfo::where('salesman_code', $row[10])->first();

                        // if jp is their
                        $journeyPlan = JourneyPlan::where('merchandiser_id', $merchandiser->user_id)
                            ->first();
                       
                        if (!is_object($journeyPlan)) {
                            $journeyPlan = new JourneyPlan;
                        } else {
                            // if jp customer
                            $plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                ->where('customer_id', $customer_code)
                                ->first();

                            if (is_object($plan_customer)) {
                                continue;
                            }
                        }

                        DB::beginTransaction();
                        try {
                            if (!is_object($merchandiser) or !is_object($customer)) {
                                if (!is_object($merchandiser)) {
                                    return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
                                }
                                if (!is_object($customer)) {
                                    return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
                                }
                            }

                            $save = true;
                            if (isset($journeyPlan->id) && $journeyPlan->id) {
                                $save = false;
                            }
                            // $journeyPlan = new JourneyPlan;
                            $journeyPlan->organisation_id = $current_organisation_id;
                            $journeyPlan->name = $row[0];
                            $journeyPlan->description = $row[1];
                            $journeyPlan->start_date = date('Y-m-d', strtotime($row[2]));

                            if (isset($row[3]) and $row[3] != "") {
                                $journeyPlan->end_date = date('Y-m-d', strtotime($row[3]));
                                $journeyPlan->no_end_date = 0;
                            } else {
                                $journeyPlan->no_end_date = 1;
                            }

                            if ($row[9] == 'No') {
                                $journeyPlan->is_enforce = 0;
                            } else {
                                $journeyPlan->is_enforce = 1;
                            }

                            if (is_object($merchandiser)) {
                                $journeyPlan->is_merchandiser = 1;
                                $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
                            } else {
                                $journeyPlan->merchandiser_id = Null;
                                $journeyPlan->is_merchandiser = 0;
                            }

                            if ($row[6] == "Yes") {
                                $planType = 1;
                                $dayNumber = 0;
                                if ($row[8] == "Monday") {
                                    $dayNumber = 1;
                                } else if ($row[8] == "Tuesday") {
                                    $dayNumber = 2;
                                } else if ($row[8] == "Wednesday") {
                                    $dayNumber = 3;
                                } else if ($row[8] == "Thursday") {
                                    $dayNumber = 4;
                                } else if ($row[8] == "Friday") {
                                    $dayNumber = 5;
                                } else if ($row[8] == "Saturday") {
                                    $dayNumber = 6;
                                } else if ($row[8] == "Sunday") {
                                    $dayNumber = 7;
                                }
                                $journeyPlan->start_day_of_the_week = $dayNumber;
                            } else if ($row[7] == "Yes") {
                                $planType = 2;
                                $dayNumber = 0;
                                if ($row[8] == "Monday") {
                                    $dayNumber = 1;
                                } else if ($row[8] == "Tuesday") {
                                    $dayNumber = 2;
                                } else if ($row[8] == "Wednesday") {
                                    $dayNumber = 3;
                                } else if ($row[8] == "Thursday") {
                                    $dayNumber = 4;
                                } else if ($row[8] == "Friday") {
                                    $dayNumber = 5;
                                } else if ($row[8] == "Saturday") {
                                    $dayNumber = 6;
                                } else if ($row[8] == "Sunday") {
                                    $dayNumber = 7;
                                }
                                $journeyPlan->start_day_of_the_week = $dayNumber;
                            }
                            $journeyPlan->plan_type = $planType;
                            $weekArray = [];
                            $monthArray = [];
                            $count = 0;

                            if ($planType == 2) {
                                if (
                                    (isset($row[12]) and $row[12] != "") ||
                                    (isset($row[16]) and $row[16] != "") ||
                                    (isset($row[20]) and $row[20] != "") ||
                                    (isset($row[24]) and $row[24] != "") ||
                                    (isset($row[28]) and $row[28] != "") ||
                                    (isset($row[32]) and $row[32] != "") ||
                                    (isset($row[36]) and $row[36] != "")
                                ) {
                                    $journeyPlan->week_1 = 1;
                                    $weekArray[$count]['week'] = "week1";
                                    $weekArray[$count]['column'] = 12;
                                    $count++;
                                }

                                if (
                                    (isset($row[40]) and $row[40] != "") ||
                                    (isset($row[44]) and $row[44] != "") ||
                                    (isset($row[48]) and $row[48] != "") ||
                                    (isset($row[52]) and $row[52] != "") ||
                                    (isset($row[56]) and $row[56] != "") ||
                                    (isset($row[60]) and $row[60] != "") ||
                                    (isset($row[64]) and $row[64] != "")
                                ) {
                                    $journeyPlan->week_2 = 2;
                                    $weekArray[$count]['week'] = "week2";
                                    $weekArray[$count]['column'] = 40;
                                    $count++;
                                }

                                if (
                                    (isset($row[68]) and $row[68] != "") ||
                                    (isset($row[72]) and $row[72] != "") ||
                                    (isset($row[76]) and $row[76] != "") ||
                                    (isset($row[80]) and $row[80] != "") ||
                                    (isset($row[84]) and $row[84] != "") ||
                                    (isset($row[88]) and $row[88] != "") ||
                                    (isset($row[92]) and $row[92] != "")
                                ) {
                                    $journeyPlan->week_3 = 3;
                                    $weekArray[$count]['week'] = "week3";
                                    $weekArray[$count]['column'] = 68;
                                    $count++;
                                }

                                if (
                                    (isset($row[96]) and $row[96] != "") ||
                                    (isset($row[100]) and $row[100] != "") ||
                                    (isset($row[104]) and $row[104] != "") ||
                                    (isset($row[108]) and $row[108] != "") ||
                                    (isset($row[112]) and $row[112] != "") ||
                                    (isset($row[116]) and $row[116] != "") ||
                                    (isset($row[120]) and $row[120] != "")
                                ) {
                                    $journeyPlan->week_4 = 4;
                                    $weekArray[$count]['week'] = "week4";
                                    $weekArray[$count]['column'] = 96;
                                    $count++;
                                }

                                if (
                                    (isset($row[124]) and $row[124] != "") ||
                                    (isset($row[128]) and $row[128] != "") ||
                                    (isset($row[132]) and $row[132] != "") ||
                                    (isset($row[136]) and $row[136] != "") ||
                                    (isset($row[140]) and $row[140] != "") ||
                                    (isset($row[144]) and $row[144] != "") ||
                                    (isset($row[148]) and $row[148] != "")
                                ) {
                                    $journeyPlan->week_5 = 5;
                                    $weekArray[$count]['week'] = "week5";
                                    $weekArray[$count]['column'] = 124;
                                }
                            }

                            if ($planType == 1) {
                                if (
                                    (isset($row[12]) and $row[12] != "") ||
                                    (isset($row[40]) and $row[40] != "") ||
                                    (isset($row[68]) and $row[68] != "") ||
                                    (isset($row[96]) and $row[96] != "") ||
                                    (isset($row[124]) and $row[124] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 7;
                                    $monthArray[$count]['day_name'] = "Sunday";
                                    if (isset($row[12]) and $row[12] != "") {
                                        $monthArray[$count]['column'] = 12;
                                    } else if ((isset($row[40]) and $row[40] != "")) {
                                        $monthArray[$count]['column'] = 40;
                                    } else if ((isset($row[68]) and $row[68] != "")) {
                                        $monthArray[$count]['column'] = 68;
                                    } else if ((isset($row[96]) and $row[96] != "")) {
                                        $monthArray[$count]['column'] = 96;
                                    } else if ((isset($row[124]) and $row[124] != "")) {
                                        $monthArray[$count]['column'] = 124;
                                    }
                                    $count++;
                                }

                                if (
                                    (isset($row[16]) and $row[16] != "") ||
                                    (isset($row[44]) and $row[44] != "") ||
                                    (isset($row[72]) and $row[72] != "") ||
                                    (isset($row[100]) and $row[100] != "") ||
                                    (isset($row[128]) and $row[128] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 1;
                                    $monthArray[$count]['day_name'] = "Monday";
                                    if (isset($row[16]) and $row[16] != "") {
                                        $monthArray[$count]['column'] = 16;
                                    } else if ((isset($row[44]) and $row[44] != "")) {
                                        $monthArray[$count]['column'] = 44;
                                    } else if ((isset($row[72]) and $row[72] != "")) {
                                        $monthArray[$count]['column'] = 72;
                                    } else if ((isset($row[100]) and $row[100] != "")) {
                                        $monthArray[$count]['column'] = 100;
                                    } else if ((isset($row[128]) and $row[128] != "")) {
                                        $monthArray[$count]['column'] = 128;
                                    }
                                    $count++;
                                }

                                if (
                                    (isset($row[20]) and $row[20] != "") ||
                                    (isset($row[48]) and $row[48] != "") ||
                                    (isset($row[76]) and $row[76] != "") ||
                                    (isset($row[104]) and $row[104] != "") ||
                                    (isset($row[132]) and $row[132] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 2;
                                    $monthArray[$count]['day_name'] = "Tuesday";
                                    if (isset($row[20]) and $row[20] != "") {
                                        $monthArray[$count]['column'] = 20;
                                    } else if ((isset($row[48]) and $row[48] != "")) {
                                        $monthArray[$count]['column'] = 48;
                                    } else if ((isset($row[76]) and $row[76] != "")) {
                                        $monthArray[$count]['column'] = 76;
                                    } else if ((isset($row[104]) and $row[104] != "")) {
                                        $monthArray[$count]['column'] = 104;
                                    } else if ((isset($row[132]) and $row[132] != "")) {
                                        $monthArray[$count]['column'] = 132;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[24]) and $row[24] != "") ||
                                    (isset($row[52]) and $row[52] != "") ||
                                    (isset($row[80]) and $row[80] != "") ||
                                    (isset($row[108]) and $row[108] != "") ||
                                    (isset($row[136]) and $row[136] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 3;
                                    $monthArray[$count]['day_name'] = "Wednesday";
                                    if (isset($row[24]) and $row[24] != "") {
                                        $monthArray[$count]['column'] = 24;
                                    } else if ((isset($row[52]) and $row[52] != "")) {
                                        $monthArray[$count]['column'] = 52;
                                    } else if ((isset($row[80]) and $row[80] != "")) {
                                        $monthArray[$count]['column'] = 80;
                                    } else if ((isset($row[108]) and $row[108] != "")) {
                                        $monthArray[$count]['column'] = 108;
                                    } else if ((isset($row[136]) and $row[152] != "")) {
                                        $monthArray[$count]['column'] = 152;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[28]) and $row[28] != "") ||
                                    (isset($row[56]) and $row[56] != "") ||
                                    (isset($row[84]) and $row[84] != "") ||
                                    (isset($row[112]) and $row[112] != "") ||
                                    (isset($row[140]) and $row[140] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 4;
                                    $monthArray[$count]['day_name'] = "Thursday";
                                    if (isset($row[28]) and $row[28] != "") {
                                        $monthArray[$count]['column'] = 28;
                                    } else if ((isset($row[56]) and $row[56] != "")) {
                                        $monthArray[$count]['column'] = 56;
                                    } else if ((isset($row[84]) and $row[84] != "")) {
                                        $monthArray[$count]['column'] = 65;
                                    } else if ((isset($row[112]) and $row[112] != "")) {
                                        $monthArray[$count]['column'] = 112;
                                    } else if ((isset($row[140]) and $row[140] != "")) {
                                        $monthArray[$count]['column'] = 140;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[32]) and $row[32] != "") ||
                                    (isset($row[60]) and $row[60] != "") ||
                                    (isset($row[88]) and $row[88] != "") ||
                                    (isset($row[116]) and $row[116] != "") ||
                                    (isset($row[144]) and $row[144] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 5;
                                    $monthArray[$count]['day_name'] = "Friday";
                                    if (isset($row[32]) and $row[32] != "") {
                                        $monthArray[$count]['column'] = 32;
                                    } else if ((isset($row[60]) and $row[60] != "")) {
                                        $monthArray[$count]['column'] = 60;
                                    } else if ((isset($row[88]) and $row[88] != "")) {
                                        $monthArray[$count]['column'] = 88;
                                    } else if ((isset($row[116]) and $row[116] != "")) {
                                        $monthArray[$count]['column'] = 116;
                                    } else if ((isset($row[144]) and $row[144] != "")) {
                                        $monthArray[$count]['column'] = 111;
                                    }
                                    $count++;
                                }
                                if (
                                    (isset($row[36]) and $row[36] != "") ||
                                    (isset($row[64]) and $row[64] != "") ||
                                    (isset($row[92]) and $row[92] != "") ||
                                    (isset($row[94]) and $row[94] != "") ||
                                    (isset($row[148]) and $row[148] != "")
                                ) {
                                    $monthArray[$count]['day_number'] = 6;
                                    $monthArray[$count]['day_name'] = "Saturday";
                                    if (isset($row[36]) and $row[36] != "") {
                                        $monthArray[$count]['column'] = 36;
                                    } else if ((isset($row[64]) and $row[64] != "")) {
                                        $monthArray[$count]['column'] = 64;
                                    } else if ((isset($row[92]) and $row[92] != "")) {
                                        $monthArray[$count]['column'] = 92;
                                    } else if ((isset($row[120]) and $row[120] != "")) {
                                        $monthArray[$count]['column'] = 120;
                                    } else if ((isset($row[148]) and $row[148] != "")) {
                                        $monthArray[$count]['column'] = 114;
                                    }
                                    $count++;
                                }
                            }

                            $journeyPlan->status = 1;
                            $journeyPlan->current_stage = "Approved";

                            if ($save == true) {
                                $journeyPlan->save();
                            }

                            $journey_plan_months_ids = [];
                            foreach ($monthArray as $key => $monthData) {

                                $journey_plan_days = new JourneyPlanDay;

                                $journey_plan_days->journey_plan_id = $journeyPlan->id;
                                $journey_plan_days->journey_plan_week_id = NULL;
                                $journey_plan_days->day_number = $monthData['day_number'];
                                $journey_plan_days->day_name = $monthData['day_name'];
                                $journey_plan_days->save();

                                $start_time = "10:00";
                                $end_time = "06:00";

                                if ($row[$monthData['column']] == "Yes") {
                                    $start_time = $row[$monthData['column'] + 1];
                                    $end_time = $row[$monthData['column'] + 2];
                                }

                                $is_msl = ($row[$monthData['column'] + 3] == "Yes") ? 1 : 0;

                                $is_customer_save = $row[$monthData['column']];

                                $journey_plan_customer = $this->savePlanCustomer(
                                    $journeyPlan->id,
                                    $journey_plan_days->id,
                                    (is_object($customer)) ? $customer->id : 0,
                                    $start_time,
                                    $end_time,
                                    (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                    $is_msl,
                                    $is_customer_save,
                                    $skipduplicate
                                );
                            }

                            $journey_plan_weeks_ids = [];
                            foreach ($weekArray as $key => $weekData) {
                                if (isset($journeyPlan->id) && $journeyPlan->id) {
                                    $journey_plan_weeks = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id)
                                        ->where('week_number', $weekData['week'])
                                        ->first();
                                    if (!is_object($journey_plan_weeks)) {
                                        $journey_plan_weeks = new JourneyPlanWeek;
                                    }
                                } else {
                                    $journey_plan_weeks = new JourneyPlanWeek;
                                }
                                $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
                                $journey_plan_weeks->week_number = $weekData['week'];
                                $journey_plan_weeks->save();

                                $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
                                $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
                                $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
                            }


                            foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {

                                $startColumn = $journeyPlanWeek['column'];

                                if (isset($row[$startColumn]) && $row[$startColumn]) {

                                    $journey_plan_days = $this->savePlanDay(
                                        $journeyPlanWeek['journey_id'],
                                        $journeyPlanWeek['week_id'],
                                        7,
                                        "Sunday"
                                    );

                                    $is_msl = ($row[$startColumn + 3] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'], // Jorney Plan ID
                                        $journey_plan_days->id, // Jorney Plan Day ID
                                        (is_object($customer)) ? $customer->id : 0, // Customer info Id
                                        $row[$startColumn + 1], // Jorney Plan Start Time
                                        $row[$startColumn + 2], // Jorney Plan End Time
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0, // Jorney Plan Merchandiser id
                                        $is_msl, // Customer is MSL
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 4]) && $row[$startColumn + 4]) {

                                    $journey_plan_days = $this->savePlanDay(
                                        $journeyPlanWeek['journey_id'],
                                        $journeyPlanWeek['week_id'],
                                        1,
                                        "Monday"
                                    );

                                    $is_msl = ($row[$startColumn + 7] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 4];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 5],
                                        $row[$startColumn + 6],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl, // Customer is MSL
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 8]) && $row[$startColumn + 8]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 2, "Tuesday");

                                    $is_msl = ($row[$startColumn + 11] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 8];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 9],
                                        $row[$startColumn + 10],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 12]) && $row[$startColumn + 12]) {

                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 3, "Wednesday");

                                    $is_msl = ($row[$startColumn + 15] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 12];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 13],
                                        $row[$startColumn + 14],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 16]) && $row[$startColumn + 16]) {

                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 4, "Thursday");

                                    $is_msl = ($row[$startColumn + 19] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 16];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 17],
                                        $row[$startColumn + 18],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 20]) && $row[$startColumn + 20]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 5, "Friday");

                                    $is_msl = ($row[$startColumn + 23] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 20];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 21],
                                        $row[$startColumn + 22],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                if (isset($row[$startColumn + 24]) && $row[$startColumn + 24]) {
                                    $journey_plan_days = $this->savePlanDay($journeyPlanWeek['journey_id'], $journeyPlanWeek['week_id'], 6, "Saturday");

                                    $is_msl = ($row[$startColumn + 27] == "Yes") ? 1 : 0;

                                    $is_customer_save = $row[$startColumn + 24];

                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlanWeek['journey_id'],
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $row[$startColumn + 25],
                                        $row[$startColumn + 26],
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }
                            }

                            DB::commit();
                        } catch (\Exception $exception) {
                            DB::rollback();
                            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        } catch (\Throwable $exception) {
                            DB::rollback();
                            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    } else {
                        $customer = CustomerInfo::where('customer_code', $row[11])->first();
                        $customer_code = $customer->user_id;
                        $merchandiser = SalesmanInfo::where('salesman_code', $row[10])->first();

                        $journeyPlan = JourneyPlan::where('merchandiser_id', $merchandiser->user_id)
                            ->first();
                            // echo  $journeyPlan;die;
                        if (!is_object($journeyPlan)) {
                            $journeyPlan = new JourneyPlan;
                        } else {
                            $plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                ->where('customer_id', $customer_code)
                                ->first();
                        }

                        if (is_object($journeyPlan)) {
                            // echo "found";
                            DB::beginTransaction();
                            try {
                                if (!is_object($merchandiser) or !is_object($customer)) {
                                    if (!is_object($merchandiser)) {
                                        return prepareResult(false, [], [], "merchandiser not exists", $this->unauthorized);
                                    }
                                    if (!is_object($customer)) {
                                        return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
                                    }
                                }

                                $new_jp = $journeyPlan->id;

                                if ($new_jp != $old_jp) {
                                    CustomerMerchandiser::where('merchandiser_id', $merchandiser->user_id)->delete();

                                    JourneyPlanCustomer::where('journey_plan_id', $journeyPlan->id)
                                        ->delete();

                                    $old_jp = $journeyPlan->id;
                                }

                                $save = true;
                                if (isset($journeyPlan->id) && $journeyPlan->id) {
                                    $save = false;
                                }
                                // echo $save."---";die;
                                $journeyPlan->organisation_id = $current_organisation_id;
                                $journeyPlan->name = $row[0];
                                $journeyPlan->description = $row[1];
                                $journeyPlan->start_date = date('Y-m-d', strtotime($row[2]));

                                if (isset($row[3]) and $row[3] != "") {
                                    $journeyPlan->end_date = date('Y-m-d', strtotime($row[3]));
                                    $journeyPlan->no_end_date = 0;
                                } else {
                                    $journeyPlan->no_end_date = 1;
                                }

                                if ($row[9] == 'No') {
                                    $journeyPlan->is_enforce = 0;
                                } else {
                                    $journeyPlan->is_enforce = 1;
                                }

                                if (is_object($merchandiser)) {
                                    $journeyPlan->is_merchandiser = 1;
                                    $journeyPlan->merchandiser_id = (is_object($merchandiser)) ? $merchandiser->user_id : 0;
                                } else {
                                    $journeyPlan->merchandiser_id = Null;
                                    $journeyPlan->is_merchandiser = 0;
                                }

                                if ($row[6] == "Yes") {
                                    $planType = 1;
                                    $dayNumber = 0;
                                    if ($row[8] == "Monday") {
                                        $dayNumber = 1;
                                    } else if ($row[8] == "Tuesday") {
                                        $dayNumber = 2;
                                    } else if ($row[8] == "Wednesday") {
                                        $dayNumber = 3;
                                    } else if ($row[8] == "Thursday") {
                                        $dayNumber = 4;
                                    } else if ($row[8] == "Friday") {
                                        $dayNumber = 5;
                                    } else if ($row[8] == "Saturday") {
                                        $dayNumber = 6;
                                    } else if ($row[8] == "Sunday") {
                                        $dayNumber = 7;
                                    }
                                    $journeyPlan->start_day_of_the_week = $dayNumber;
                                } else if ($row[7] == "Yes") {
                                    $planType = 2;
                                    $dayNumber = 0;
                                    if ($row[8] == "Monday") {
                                        $dayNumber = 1;
                                    } else if ($row[8] == "Tuesday") {
                                        $dayNumber = 2;
                                    } else if ($row[8] == "Wednesday") {
                                        $dayNumber = 3;
                                    } else if ($row[8] == "Thursday") {
                                        $dayNumber = 4;
                                    } else if ($row[8] == "Friday") {
                                        $dayNumber = 5;
                                    } else if ($row[8] == "Saturday") {
                                        $dayNumber = 6;
                                    } else if ($row[8] == "Sunday") {
                                        $dayNumber = 7;
                                    }
                                    $journeyPlan->start_day_of_the_week = $dayNumber;
                                }

                                $journeyPlan->plan_type = $planType;
                                $weekArray = [];
                                $monthArray = [];
                                $count = 0;

                                if ($planType == 2) {
                                    if (
                                        (isset($row[12]) and $row[12] != "") ||
                                        (isset($row[16]) and $row[16] != "") ||
                                        (isset($row[20]) and $row[20] != "") ||
                                        (isset($row[24]) and $row[24] != "") ||
                                        (isset($row[28]) and $row[28] != "") ||
                                        (isset($row[32]) and $row[32] != "") ||
                                        (isset($row[36]) and $row[36] != "")
                                    ) {
                                        $journeyPlan->week_1 = 1;
                                        $weekArray[$count]['week'] = "week1";
                                        $weekArray[$count]['column'] = 12;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[40]) and $row[40] != "") ||
                                        (isset($row[44]) and $row[44] != "") ||
                                        (isset($row[48]) and $row[48] != "") ||
                                        (isset($row[52]) and $row[52] != "") ||
                                        (isset($row[56]) and $row[56] != "") ||
                                        (isset($row[60]) and $row[60] != "") ||
                                        (isset($row[64]) and $row[64] != "")
                                    ) {
                                        $journeyPlan->week_2 = 2;
                                        $weekArray[$count]['week'] = "week2";
                                        $weekArray[$count]['column'] = 40;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[68]) and $row[68] != "") ||
                                        (isset($row[72]) and $row[72] != "") ||
                                        (isset($row[76]) and $row[76] != "") ||
                                        (isset($row[80]) and $row[80] != "") ||
                                        (isset($row[84]) and $row[84] != "") ||
                                        (isset($row[88]) and $row[88] != "") ||
                                        (isset($row[92]) and $row[92] != "")
                                    ) {
                                        $journeyPlan->week_3 = 3;
                                        $weekArray[$count]['week'] = "week3";
                                        $weekArray[$count]['column'] = 68;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[96]) and $row[96] != "") ||
                                        (isset($row[100]) and $row[100] != "") ||
                                        (isset($row[104]) and $row[104] != "") ||
                                        (isset($row[108]) and $row[108] != "") ||
                                        (isset($row[112]) and $row[112] != "") ||
                                        (isset($row[116]) and $row[116] != "") ||
                                        (isset($row[120]) and $row[120] != "")
                                    ) {
                                        $journeyPlan->week_4 = 4;
                                        $weekArray[$count]['week'] = "week4";
                                        $weekArray[$count]['column'] = 96;
                                        $count++;
                                    }

                                    if (
                                        (isset($row[124]) and $row[124] != "") ||
                                        (isset($row[128]) and $row[128] != "") ||
                                        (isset($row[132]) and $row[132] != "") ||
                                        (isset($row[136]) and $row[136] != "") ||
                                        (isset($row[140]) and $row[140] != "") ||
                                        (isset($row[144]) and $row[144] != "") ||
                                        (isset($row[148]) and $row[148] != "")
                                    ) {
                                        $journeyPlan->week_5 = 5;
                                        $weekArray[$count]['week'] = "week5";
                                        $weekArray[$count]['column'] = 124;
                                    }
                                }

                                if ($planType == 1) {
                                    if (
                                        (isset($row[12]) and $row[12] != "") ||
                                        (isset($row[40]) and $row[40] != "") ||
                                        (isset($row[68]) and $row[68] != "") ||
                                        (isset($row[96]) and $row[96] != "") ||
                                        (isset($row[124]) and $row[124] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 7;
                                        $monthArray[$count]['day_name'] = "Sunday";
                                        if (isset($row[12]) and $row[12] != "") {
                                            $monthArray[$count]['column'] = 12;
                                        } else if ((isset($row[40]) and $row[40] != "")) {
                                            $monthArray[$count]['column'] = 40;
                                        } else if ((isset($row[68]) and $row[68] != "")) {
                                            $monthArray[$count]['column'] = 68;
                                        } else if ((isset($row[96]) and $row[96] != "")) {
                                            $monthArray[$count]['column'] = 96;
                                        } else if ((isset($row[124]) and $row[124] != "")) {
                                            $monthArray[$count]['column'] = 124;
                                        }
                                        $count++;
                                    }

                                    if (
                                        (isset($row[16]) and $row[16] != "") ||
                                        (isset($row[44]) and $row[44] != "") ||
                                        (isset($row[72]) and $row[72] != "") ||
                                        (isset($row[100]) and $row[100] != "") ||
                                        (isset($row[128]) and $row[128] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 1;
                                        $monthArray[$count]['day_name'] = "Monday";
                                        if (isset($row[16]) and $row[16] != "") {
                                            $monthArray[$count]['column'] = 16;
                                        } else if ((isset($row[44]) and $row[44] != "")) {
                                            $monthArray[$count]['column'] = 44;
                                        } else if ((isset($row[72]) and $row[72] != "")) {
                                            $monthArray[$count]['column'] = 72;
                                        } else if ((isset($row[100]) and $row[100] != "")) {
                                            $monthArray[$count]['column'] = 100;
                                        } else if ((isset($row[128]) and $row[128] != "")) {
                                            $monthArray[$count]['column'] = 128;
                                        }
                                        $count++;
                                    }

                                    if (
                                        (isset($row[20]) and $row[20] != "") ||
                                        (isset($row[48]) and $row[48] != "") ||
                                        (isset($row[76]) and $row[76] != "") ||
                                        (isset($row[104]) and $row[104] != "") ||
                                        (isset($row[132]) and $row[132] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 2;
                                        $monthArray[$count]['day_name'] = "Tuesday";
                                        if (isset($row[20]) and $row[20] != "") {
                                            $monthArray[$count]['column'] = 20;
                                        } else if ((isset($row[48]) and $row[48] != "")) {
                                            $monthArray[$count]['column'] = 48;
                                        } else if ((isset($row[76]) and $row[76] != "")) {
                                            $monthArray[$count]['column'] = 76;
                                        } else if ((isset($row[104]) and $row[104] != "")) {
                                            $monthArray[$count]['column'] = 104;
                                        } else if ((isset($row[132]) and $row[132] != "")) {
                                            $monthArray[$count]['column'] = 132;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[24]) and $row[24] != "") ||
                                        (isset($row[52]) and $row[52] != "") ||
                                        (isset($row[80]) and $row[80] != "") ||
                                        (isset($row[108]) and $row[108] != "") ||
                                        (isset($row[136]) and $row[136] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 3;
                                        $monthArray[$count]['day_name'] = "Wednesday";
                                        if (isset($row[24]) and $row[24] != "") {
                                            $monthArray[$count]['column'] = 24;
                                        } else if ((isset($row[52]) and $row[52] != "")) {
                                            $monthArray[$count]['column'] = 52;
                                        } else if ((isset($row[80]) and $row[80] != "")) {
                                            $monthArray[$count]['column'] = 80;
                                        } else if ((isset($row[108]) and $row[108] != "")) {
                                            $monthArray[$count]['column'] = 108;
                                        } else if ((isset($row[136]) and $row[152] != "")) {
                                            $monthArray[$count]['column'] = 152;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[28]) and $row[28] != "") ||
                                        (isset($row[56]) and $row[56] != "") ||
                                        (isset($row[84]) and $row[84] != "") ||
                                        (isset($row[112]) and $row[112] != "") ||
                                        (isset($row[140]) and $row[140] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 4;
                                        $monthArray[$count]['day_name'] = "Thursday";
                                        if (isset($row[28]) and $row[28] != "") {
                                            $monthArray[$count]['column'] = 28;
                                        } else if ((isset($row[56]) and $row[56] != "")) {
                                            $monthArray[$count]['column'] = 56;
                                        } else if ((isset($row[84]) and $row[84] != "")) {
                                            $monthArray[$count]['column'] = 65;
                                        } else if ((isset($row[112]) and $row[112] != "")) {
                                            $monthArray[$count]['column'] = 112;
                                        } else if ((isset($row[140]) and $row[140] != "")) {
                                            $monthArray[$count]['column'] = 140;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[32]) and $row[32] != "") ||
                                        (isset($row[60]) and $row[60] != "") ||
                                        (isset($row[88]) and $row[88] != "") ||
                                        (isset($row[116]) and $row[116] != "") ||
                                        (isset($row[144]) and $row[144] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 5;
                                        $monthArray[$count]['day_name'] = "Friday";
                                        if (isset($row[32]) and $row[32] != "") {
                                            $monthArray[$count]['column'] = 32;
                                        } else if ((isset($row[60]) and $row[60] != "")) {
                                            $monthArray[$count]['column'] = 60;
                                        } else if ((isset($row[88]) and $row[88] != "")) {
                                            $monthArray[$count]['column'] = 88;
                                        } else if ((isset($row[116]) and $row[116] != "")) {
                                            $monthArray[$count]['column'] = 116;
                                        } else if ((isset($row[144]) and $row[144] != "")) {
                                            $monthArray[$count]['column'] = 111;
                                        }
                                        $count++;
                                    }
                                    if (
                                        (isset($row[36]) and $row[36] != "") ||
                                        (isset($row[64]) and $row[64] != "") ||
                                        (isset($row[92]) and $row[92] != "") ||
                                        (isset($row[94]) and $row[94] != "") ||
                                        (isset($row[148]) and $row[148] != "")
                                    ) {
                                        $monthArray[$count]['day_number'] = 6;
                                        $monthArray[$count]['day_name'] = "Saturday";
                                        if (isset($row[36]) and $row[36] != "") {
                                            $monthArray[$count]['column'] = 36;
                                        } else if ((isset($row[64]) and $row[64] != "")) {
                                            $monthArray[$count]['column'] = 64;
                                        } else if ((isset($row[92]) and $row[92] != "")) {
                                            $monthArray[$count]['column'] = 92;
                                        } else if ((isset($row[120]) and $row[120] != "")) {
                                            $monthArray[$count]['column'] = 120;
                                        } else if ((isset($row[148]) and $row[148] != "")) {
                                            $monthArray[$count]['column'] = 114;
                                        }
                                        $count++;
                                    }
                                }
                               
                                $journeyPlan->status = 1;
                                $journeyPlan->current_stage = "Approved";
                                if ($save == true) {
                                    $journeyPlan->save();
                                }

                                $journey_plan_months_ids = [];

                                foreach ($monthArray as $key => $monthData) {

                                    $journey_plan_days = new JourneyPlanDay;

                                    $journey_plan_days->journey_plan_id = $journeyPlan->id;
                                    $journey_plan_days->journey_plan_week_id = NULL;
                                    $journey_plan_days->day_number = $monthData['day_number'];
                                    $journey_plan_days->day_name = $monthData['day_name'];
                                    $journey_plan_days->save();

                                    $start_time = "10:00";
                                    $end_time = "06:00";

                                    if ($row[$monthData['column']] == "Yes") {
                                        $start_time = $row[$monthData['column'] + 1];
                                        $end_time = $row[$monthData['column'] + 2];
                                    }
                                    $is_msl =  0;

                                    $is_customer_save = $row[$monthData['column']];
                                   
                                    $journey_plan_customer = $this->savePlanCustomer(
                                        $journeyPlan->id,
                                        $journey_plan_days->id,
                                        (is_object($customer)) ? $customer->id : 0,
                                        $start_time,
                                        $end_time,
                                        (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                        $is_msl,
                                        $is_customer_save,
                                        $skipduplicate
                                    );
                                }

                                $journey_plan_weeks_ids = [];
                                foreach ($weekArray as $key => $weekData) {
                                    if (isset($journeyPlan->id) && $journeyPlan->id) {
                                        $journey_plan_weeks = JourneyPlanWeek::where('journey_plan_id', $journeyPlan->id)
                                            ->where('week_number', $weekData['week'])
                                            ->first();
                                        if (!is_object($journey_plan_weeks)) {
                                            $journey_plan_weeks = new JourneyPlanWeek;
                                        }
                                    } else {
                                        $journey_plan_weeks = new JourneyPlanWeek;
                                    }
                                    $journey_plan_weeks->journey_plan_id = $journeyPlan->id;
                                    $journey_plan_weeks->week_number = $weekData['week'];
                                    $journey_plan_weeks->save();

                                    $journey_plan_weeks_ids[$key]['journey_id'] = $journeyPlan->id;
                                    $journey_plan_weeks_ids[$key]['week_id'] = $journey_plan_weeks->id;
                                    $journey_plan_weeks_ids[$key]['column'] = $weekData['column'];
                                }

                                // pre($journey_plan_weeks_ids);

                                foreach ($journey_plan_weeks_ids as $key => $journeyPlanWeek) {

                                    $startColumn = $journeyPlanWeek['column'];

                                    if (isset($row[$startColumn]) && $row[$startColumn]) {

                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            7,
                                            "Sunday"
                                        );

                                        $is_msl = ($row[$startColumn + 3] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn];
                                        // echo   $journeyPlan->id;die;
                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 1],
                                            $row[$startColumn + 2],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 4]) && $row[$startColumn + 4]) {
                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            1,
                                            "Monday"
                                        );

                                        $is_msl = ($row[$startColumn + 7] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 4];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 5],
                                            $row[$startColumn + 6],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 8]) && $row[$startColumn + 8]) {
                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            2,
                                            "Tuesday"
                                        );

                                        $is_msl = ($row[$startColumn + 11] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 8];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 9],
                                            $row[$startColumn + 10],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 12]) && $row[$startColumn + 12]) {

                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            3,
                                            "Wednesday"
                                        );

                                        $is_msl = ($row[$startColumn + 15] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 12];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 13],
                                            $row[$startColumn + 14],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 16]) && $row[$startColumn + 16]) {

                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            4,
                                            "Thursday"
                                        );

                                        $is_msl = ($row[$startColumn + 19] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 16];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 17],
                                            $row[$startColumn + 18],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 20]) && $row[$startColumn + 20]) {
                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            5,
                                            "Friday"
                                        );

                                        $is_msl = ($row[$startColumn + 23] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 20];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 21],
                                            $row[$startColumn + 22],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }

                                    if (isset($row[$startColumn + 24]) && $row[$startColumn + 24]) {
                                        $journey_plan_days = $this->savePlanDay(
                                            $journeyPlanWeek['journey_id'],
                                            $journeyPlanWeek['week_id'],
                                            6,
                                            "Saturday"
                                        );

                                        $is_msl = ($row[$startColumn + 27] == "Yes") ? 1 : 0;

                                        $is_customer_save = $row[$startColumn + 24];

                                        $journey_plan_customer = $this->savePlanCustomer(
                                            $journeyPlanWeek['journey_id'],
                                            $journey_plan_days->id,
                                            (is_object($customer)) ? $customer->id : 0,
                                            $row[$startColumn + 25],
                                            $row[$startColumn + 26],
                                            (is_object($merchandiser)) ? $merchandiser->user_id : 0,
                                            $is_msl,
                                            $is_customer_save,
                                            $skipduplicate
                                        );
                                    }
                                }

                                DB::commit();
                            } catch (\Exception $exception) {
                                DB::rollback();
                                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            } catch (\Throwable $exception) {
                                DB::rollback();
                                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            }
                        }
                    }
                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "journey plan successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    private function savePlanDay($journey_id, $week_id, $day_number, $day_name)
    {
        if (isset($journey_id) && $journey_id) {
            $journey_plan_days = JourneyPlanDay::where('journey_plan_id', $journey_id)
                ->where('journey_plan_week_id', $week_id)
                ->where('day_number', $day_number)
                ->first();

            if (!is_object($journey_plan_days)) {
                $journey_plan_days = new JourneyPlanDay;
            }
        } else {
            $journey_plan_days = new JourneyPlanDay;
        }

        $journey_plan_days->journey_plan_id = $journey_id;
        $journey_plan_days->journey_plan_week_id = $week_id;
        $journey_plan_days->day_number = $day_number;
        $journey_plan_days->day_name = $day_name;
        $journey_plan_days->save();

        return $journey_plan_days;
    }

    private function savePlanCustomer_old($journey_id, $journey_plan_days_id, $customer_id, $start_time, $end_time, $merchandiser_id, $is_msl)
    {
        if (isset($journey_id) && $journey_id) {
            $journey_plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
                ->where('journey_plan_day_id', $journey_plan_days_id)
                ->where('customer_id', $customer_id)
                ->first();

            if (!is_object($journey_plan_customer)) {
                $journey_plan_customer = new JourneyPlanCustomer;
            }
        } else {
            $journey_plan_customer = new JourneyPlanCustomer;
        }

        $customer_info = CustomerInfo::find($customer_id);

        $customer_merchandiser = CustomerMerchandiser::where('customer_id', $customer_info->user_id)
            ->where('merchandiser_id', $merchandiser_id)
            ->first();

        if (!is_object($customer_merchandiser)) {
            $customer = User::find($customer_info->user_id);
            $merchandiser = User::find($merchandiser_id);

            if (is_object($customer) && is_object($merchandiser)) {
                $customer_merchandiser = new CustomerMerchandiser;
                $customer_merchandiser->customer_id = $customer_info->user_id;
                $customer_merchandiser->merchandiser_id = $merchandiser_id;
                $customer_merchandiser->save();
            }
        }

        // $jpc = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
        //     ->where('journey_plan_day_id', $journey_plan_days_id)
        //     ->orderBy('id', 'desc')
        //     ->first();

        // $day_sequence = 1;
        // if (is_object($jpc)) {
        //     $day_sequence = $jpc->day_customer_sequence + 1;
        // }

        $journey_plan_customer->journey_plan_id = $journey_id;
        $journey_plan_customer->journey_plan_day_id = $journey_plan_days_id;
        $journey_plan_customer->customer_id = $customer_id;
        $journey_plan_customer->day_customer_sequence = 1;
        $journey_plan_customer->is_msl = $is_msl;
        $journey_plan_customer->day_start_time = $start_time;
        $journey_plan_customer->day_end_time = $end_time;
        $journey_plan_customer->save();

        $jpc = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
            ->where('journey_plan_day_id', $journey_plan_days_id)
            ->get();

        for ($i = 0; $i < count($jpc); $i++) {
            $jpc[$i]->day_customer_sequence = $i + 1;
            $jpc[$i]->save();
        }

        return $journey_plan_customer;
    }

    private function savePlanCustomer($journey_id, $journey_plan_days_id, $customer_id, $start_time, $end_time, $merchandiser_id, $is_msl, $is_customer_save, $is_skip)
    {
        // echo $is_customer_save;die;
        if (isset($journey_id) && $journey_id) {
            $journey_plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
                ->where('journey_plan_day_id', $journey_plan_days_id)
                ->where('customer_id', $customer_id)
                ->first();

            if (!is_object($journey_plan_customer)) {
                $journey_plan_customer = new JourneyPlanCustomer;
            }
            
        } else {
            $journey_plan_customer = new JourneyPlanCustomer;
        }

        $customer_info = CustomerInfo::find($customer_id);

        $customer_merchandiser = CustomerMerchandiser::where('customer_id', $customer_info->user_id)
            ->where('merchandiser_id', $merchandiser_id)
            ->first();

        if (!is_object($customer_merchandiser)) {
            $customer = User::find($customer_info->user_id);
            $merchandiser = User::find($merchandiser_id);

            if (is_object($customer) && is_object($merchandiser)) {
                $customer_merchandiser = new CustomerMerchandiser;
                $customer_merchandiser->customer_id = $customer_info->user_id;
                $customer_merchandiser->merchandiser_id = $merchandiser_id;
                $customer_merchandiser->save();
            }
        }

        // $jpc = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
        //     ->where('journey_plan_day_id', $journey_plan_days_id)
        //     ->orderBy('id', 'desc')
        //     ->first();

        // $day_sequence = 1;
        // if (is_object($jpc)) {
        //     $day_sequence = $jpc->day_customer_sequence + 1;
        // }

        $journey_plan_customer->journey_plan_id = $journey_id;
        $journey_plan_customer->journey_plan_day_id = $journey_plan_days_id;
        $journey_plan_customer->customer_id = $customer_id;
        $journey_plan_customer->day_customer_sequence = 1;
        $journey_plan_customer->is_msl = $is_msl;
        $journey_plan_customer->day_start_time = $start_time;
        $journey_plan_customer->day_end_time = $end_time;
         if ($is_customer_save == "1") {
            $journey_plan_customer->save();
        }

        if ($is_customer_save == "0" && $is_skip == 0) {
            $jpcd = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
                ->where('journey_plan_day_id', $journey_plan_days_id)
                ->where('customer_id', $customer_id)
                ->first();
            if ($jpcd) {
                $jpcd->delete();
            }
        }

        $jpc = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
            ->where('journey_plan_day_id', $journey_plan_days_id)
            ->get();

        for ($i = 0; $i < count($jpc); $i++) {
            $jpc[$i]->day_customer_sequence = $i + 1;
            $jpc[$i]->save();
        }

        return $journey_plan_customer;
    }

    private function deletePlanCustomer($journey_id, $week_id = null, $journey_plan_day_number = null, $customer_id = null)
    {
        $journey_plan_days = JourneyPlanDay::where('journey_plan_id', $journey_id)
            ->where('journey_plan_week_id', $week_id)
            ->where('day_number', $journey_plan_day_number)
            ->first();

        // if (is_object($journey_plan_days)) {
        //     $journey_plan_days->delete();
        // }

        if (isset($journey_plan_days->id) && $journey_plan_days->id) {
            $journey_plan_customer = JourneyPlanCustomer::where('journey_plan_id', $journey_id)
                ->where('journey_plan_day_id', $journey_plan_days->id)
                ->where('customer_id', $customer_id)
                ->first();

            if (is_object($journey_plan_customer)) {
                $journey_plan_customer->delete();
            }
        }
    }

    private function saveCustomerMerchandiser($merchandiser_id, $customer_id)
    {
        $customer = CustomerInfo::find($customer_id);
        if (is_object($customer)) {
            $customer_merchandiser = CustomerMerchandiser::where('customer_id', $customer->user_id)
                ->where('merchandiser_id', $merchandiser_id)
                ->first();

            if (empty($customer_merchandiser)) {
                $merchandiser = User::find($merchandiser_id);
                if (is_object($customer) && is_object($merchandiser)) {
                    $customer_merchandiser = new CustomerMerchandiser;
                    $customer_merchandiser->customer_id = $customer->user_id;
                    $customer_merchandiser->merchandiser_id = $merchandiser_id;
                    $customer_merchandiser->save();
                }
            }
        }
    }

    public function getDayNamefromVal($dayNumber)
    {
        $day = null;

        if ($dayNumber == 1) {
            $day = 'Monday';
        } elseif ($dayNumber == 2) {
            $day = 'Tuesday';
        } elseif ($dayNumber == 3) {
            $day = 'Wednesday';
        } elseif ($dayNumber == 4) {
            $day = 'Thrusday';
        } elseif ($dayNumber == 5) {
            $day = 'Friday';
        } elseif ($dayNumber == 6) {
            $day = 'Saturday';
        } elseif ($dayNumber == 7) {

            $day = 'Sunday';
        }
        return $day;
    }

    /**
     *  This function is used for week 5 data add into the week 6
     */
    public function mergeWeekData()
    {
        $jp = JourneyPlan::get();

        DB::beginTransaction();
        try {
            $jp->each(function ($j, $key) {

                $week = JourneyPlanWeek::where('journey_plan_id', $j->id)
                    ->where('week_number', 'week6')
                    ->first();

                if (!is_object($week)) {
                    $week6 = new JourneyPlanWeek;
                    $week6->journey_plan_id = $j->id;
                    $week6->week_number = "week6";
                    $week6->save();

                    // week 5 data
                    $week5 = JourneyPlanWeek::with(
                        'journeyPlanDays',
                        'journeyPlanDays.journeyPlanCustomers'
                    )
                        ->where('journey_plan_id', $j->id)
                        ->where('week_number', 'week5')
                        ->first();

                    if (is_object($week5)) {
                        if (count($week5->journeyPlanDays)) {

                            foreach ($week5->journeyPlanDays as $day) {
                                $week6_days = new JourneyPlanDay;
                                $week6_days->journey_plan_id        = $j->id;
                                $week6_days->journey_plan_week_id   = $week6->id;
                                $week6_days->day_number             = $day->day_number;
                                $week6_days->day_name               = $day->day_name;
                                $week6_days->save();

                                // Add week 6 customer
                                if (count($day->journeyPlanCustomers)) {
                                    foreach ($day->journeyPlanCustomers as $customer) {
                                        $jp_customer = new JourneyPlanCustomer;
                                        $jp_customer->journey_plan_id       = $j->id;
                                        $jp_customer->journey_plan_day_id   = $week6_days->id;
                                        $jp_customer->customer_id           = $customer->customer_id;
                                        $jp_customer->is_msl = ($customer['is_msl'] == 1) ? 1 : 0;
                                        $jp_customer->day_customer_sequence = $customer->day_customer_sequence;
                                        $jp_customer->day_start_time        = $customer->day_start_time;
                                        $jp_customer->day_end_time          = $customer->day_end_time;
                                        $jp_customer->save();
                                    }
                                }
                            }
                        }
                    }
                    DB::commit();
                }
            });
            return prepareResult(true, [], [], "Update journey plan successfully imported", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * This function is download the customer visit by JP
     *
     * @param Request $request
     * @return void
     */
    public function downloadCustomerVisit(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->journey_plan_id) {
            return prepareResult(false, [], [], "Error while validating Journey Plan", $this->unprocessableEntity);
        }

        // type all = 1 and 0 particular date

        if ($request->type == 0) {
            if (!$request->start_date && !$request->end_date) {
                return prepareResult(false, [], "Please add start date and end date", "Error while validating Journey Plan", $this->unprocessableEntity);
            }
        }

        $start_date         = $request->start_date;
        $end_date           = $request->end_date;
        $journey_plan_id    = $request->journey_plan_id;

        Excel::store(new JourneyPlanVisitExport($start_date, $end_date, $journey_plan_id), 'jp_visit.csv');
        $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/jp_visit.csv'));

        return prepareResult(true, $result, [], "Data successfully exported", $this->created);
    }
}
