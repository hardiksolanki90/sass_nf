<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\DeviceDetail;
use App\Model\OrganisationRoleAttached;
use App\Model\SalesmanInfo;
use App\Model\SalesmanRouteChangeApproval;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesmanRouteChangeController extends Controller
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

        $route = SalesmanRouteChangeApproval::with(
            'salesman:id,firstname,lastname',
            'customer:id,firstname,lastname',
            'supervisor:id,firstname,lastname',
            'journeyPlan:id,name'
        )
            ->orderBy('id', 'desc')
            ->get();

        $route_array = array();
        if (is_object($route)) {
            foreach ($route as $key => $route1) {
                $route_array[] = $route[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($route_array[$offset])) {
                    $data_array[] = $route_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($route_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($route_array);
        } else {
            $data_array = $route_array;
        }

        return prepareResult(true, $data_array, [], "Route listing", $this->success, $pagination);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Salesman route change approval", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {

            $srca = new SalesmanRouteChangeApproval;
            $srca->salesman_id      = $request->salesman_id;
            $srca->customer_id      = $request->customer_id;
            $srca->journey_plan_id  = $request->journey_plan_id;
            $srca->supervisor_id    = $request->supervisor_id;
            $srca->approval_date    = date('Y-m-d');
            $srca->route_approval   = "Pending";
            $srca->reason           = $request->reason;
            $srca->request_reason           = $request->request_reason;
            $srca->save();

            // Send Notification

            $customerInfo = CustomerInfo::where('user_id', $request->customer_id)->first();
            $salesmanInfo = SalesmanInfo::where('user_id', $request->salesman_id)->first();

            $name = $salesmanInfo->user->getName();
            $c_name = $customerInfo->user->getName();

            $message = "$name has requested to route deviation for customer $customerInfo->customer_code - $c_name.";
            // $message = "Salesman $salesmanInfo->salesman_code - $name has requested to geo approval for customer  $customerInfo->customer_code - $c_name.";

            $dataNofi = array(
                'uuid'      => $srca->uuid,
                'user_id'   => $request->supervisor_id,
                'title'     => "Route Deviation Approval From Salesman",
                'type'      => "Route Deviation",
                'noti_type' => "Route Deviation",
                'other'     => $request->salesman_id,
                'message'   => $message,
                'status'    => 1,
            );

            $device_detail = DeviceDetail::where('user_id', $request->supervisor_id)
                ->orderBy('id', 'desc')
                ->first();

            if (is_object($device_detail)) {
                $t = $device_detail->device_token;
                sendNotificationAndroid($dataNofi, $t);
            }

            saveNotificaiton($dataNofi);

            $ora = OrganisationRoleAttached::where('last_role_id', 'like', "%" . $request->supervisor_id . "%")->get();
            if ($ora->count()) {
                $ora_id = $ora->pluck('user_id')->toArray();
                $user = User::whereIn('id', $ora_id)->where('role_id', 7)->first();

                $c_name = $customerInfo->user->getName();
                $sales_analitics_name = $user->getName();

                $message = "The $customerInfo->customer_code -  $c_name geo approval requested has been Approve by $sales_analitics_name.";

                if ($user) {
                    $dataNofi1 = array(
                        'uuid'      => $srca->uuid,
                        'user_id'   => $user->id,
                        'title'     => "Route Deviation Approval From Salesman",
                        'type'      => "Route Deviation",
                        'noti_type' => "Route Deviation",
                        'other'     => $request->salesman_id,
                        'message'   => $message,
                        'status'    => 1,
                    );

                    saveNotificaiton($dataNofi1);
                }
            }

            \DB::commit();
            return prepareResult(true, $srca, [], "Salesman route approval added successfully", $this->success);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function approval($uuid, Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "approval");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Salesman route change approval", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {

            $srca = SalesmanRouteChangeApproval::where('uuid', $uuid)->first();
            $srca->route_approval = $request->route_approval;
            $srca->reason = $request->reason;
            $srca->request_reason = $request->request_reason;
            $srca->save();

            // Send Notification
            if ($srca) {
                $customerInfo = CustomerInfo::where('user_id', $srca->customer_id)->first();
                $salesmanInfo = SalesmanInfo::where('user_id', $srca->salesman_id)->first();

                $name = $salesmanInfo->user->getName();
                $c_name = $customerInfo->user->getName();

                $message = "$name has requested to route deviation for customer $customerInfo->customer_code - $c_name.";
                // $message = "Salesman $salesmanInfo->salesman_code - $name has requested to geo approval for customer  $customerInfo->customer_code - $c_name.";

                $dataNofi = array(
                    'uuid'      => $srca->uuid,
                    'user_id'   => $srca->supervisor_id,
                    'title'     => "Route Deviation Approval From Salesman",
                    'type'      => "Route Deviation",
                    'noti_type' => "Route Deviation",
                    'other'     => $srca->salesman_id,
                    'message'   => $message,
                    'status'    => 1,
                );

                $device_detail = DeviceDetail::where('user_id', $srca->supervisor_id)
                    ->orderBy('id', 'desc')
                    ->first();

                if (is_object($device_detail)) {
                    $t = $device_detail->device_token;
                    sendNotificationAndroid($dataNofi, $t);
                }

                saveNotificaiton($dataNofi);

                $ora = OrganisationRoleAttached::where('last_role_id', $srca->supervisor_id)->get();

                if ($ora->count()) {
                    $ora_id = $ora->pluck('user_id')->toArray();
                    $user = User::whereIn('id', $ora_id)->first();

                    $c_name = $customerInfo->user->getName();
                    $sales_analitics_name = $user->getName();

                    $message = "The $customerInfo->customer_code -  $c_name route deviation approval requested has been Approve by $sales_analitics_name.";

                    if ($user) {
                        $dataNofi1 = array(
                            'uuid'      => $srca->uuid,
                            'user_id'   => $srca->salesman_id,
                            'title'     => "Route Deviation Approval From Supervisor",
                            'type'      => "Route Deviation",
                            'noti_type' => "Route Deviation",
                            'other'     => $user->id,
                            'message'   => $message,
                            'status'    => 1,
                        );

                        saveNotificaiton($dataNofi1);
                    }
                }

                \DB::commit();
                return prepareResult(true, $srca, [], "Salesman route approval added successfully", $this->success);
            }
            return prepareResult(false, [], [], "Salesman route approval not found", $this->not_found);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'salesman_id' => 'required|integer|exists:users,id',
                'customer_id' => 'required|integer|exists:users,id',
                'journey_plan_id' => 'required|integer|exists:users,id',
                'supervisor_id' => 'required|integer|exists:users,id',
                'reason' => 'required'
            ]);
        }

        if ($type == "approval") {
            $validator = Validator::make($input, [
                'route_approval' => 'required'
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }
}
