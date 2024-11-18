<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\DeviceDetail;
use App\Model\OrganisationRoleAttached;
use App\Model\OverdueLimitRequests;
use App\Model\SalesmanInfo;
use App\User;
use Illuminate\Http\Request;

class OverdueLimitController extends Controller
{
    /**
     * This funciton is used for salesman send geo Notification to supervisor
     *
     * @param Request $request
     * @return void
     */
    public function requestOverDueLimitApprovalSalesman(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "salesman");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating geo", $this->unprocessableEntity);
        }

        $customerInfo = CustomerInfo::where('user_id', $request->customer_id)->first();
        $salesmanInfo = SalesmanInfo::where('user_id', $request->salesman_id)->first();

        $olr = new OverdueLimitRequests();
        $olr->salesman_id   = $request->salesman_id;
        $olr->customer_id   = $request->customer_id;
        $olr->supervisor_id = $request->supervisor_id;
        $olr->type          = $request->type;
        $olr->reason        = $request->reason;
        $olr->request_reason        = $request->request_reason;
        $olr->status        = "Pending";
        $olr->save();

        $name = $salesmanInfo->user->getName();
        $c_name = $customerInfo->user->getName();
        $c_code = $customerInfo->customer_code;

        if ($request->type == "Over Due") {
            $message = "Customer $c_code - $c_name is blocked for over due, Would you like to allow?";
        }

        if ($request->type == "Credit Limit") {
            $message = "Customer $c_code - $c_name is blocked for no available limit, Would you like to allow?";
        }

        $dataNofi = array(
            'uuid'      => $olr->uuid,
            'user_id'   => $olr->supervisor_id,
            'title'     => "$olr->type Approval From Salesman",
            'type'      => "$olr->type",
            'noti_type' => "$olr->type",
            'other'     => $olr->salesman_id,
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

            // $c_name = $customerInfo->user->getName();
            $sales_analitics_name = $user->getName();

            $message = "The $customerInfo->customer_code -  $c_name geo approval requested has been Approve by $sales_analitics_name.";

            if ($user) {
                $dataNofi1 = array(
                    'uuid'      => $olr->uuid,
                    'user_id'   => $user->id,
                    'title'     => "$olr->type Approval From Salesman",
                    'type'      => "$olr->type",
                    'noti_type' => "$olr->type",
                    'other'     => $olr->salesman_id,
                    'message'   => $message,
                    'status'    => 1,
                );

                saveNotificaiton($dataNofi1);
            }
        }

        return prepareResult(true, $olr, [], "Overdue limit request sent", $this->success);
    }

    /**
     * This funciton is used for salesman send geo Notification to supervisor
     *
     * @param Request $request
     * @return void
     */
    public function requestOverDueLimitSupervisor(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "supervisor");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating olr", $this->unprocessableEntity);
        }

        $olr = OverdueLimitRequests::where('uuid', $request->uuid)->first();
        $olr->status    = $request->status;
        $olr->reason    = $request->reason;
        $olr->request_reason    = $request->request_reason;
        $olr->save();

        $supervisor = User::find($olr->supervisor_id);
        $customerInfo = CustomerInfo::where('user_id', $olr->customer_id)->first();

        $c_name = $customerInfo->user->getName();
        $supervisor_name = $supervisor->getName();

        if ($olr->type == "Over Due") {
            $message = "Supervisor $supervisor_name has approve the Over due request for $customerInfo->customer_code -  $c_name.";
        }

        if ($olr->type == "Credit Limit") {
            $message = "Supervisor $supervisor_name has approve the limits request for $customerInfo->customer_code -  $c_name.";
        }

        $dataNofi = array(
            'uuid'      => $olr->uuid,
            'user_id'   => $olr->salesman_id,
            'type'      => "$olr->type",
            'other'     => $olr->supervisor_id,
            'message'   => $message,
            'status'    => 1,
            'title'     => "$olr->type Approval From Supervisor",
            'type'      => "$olr->type",
            'noti_type' => "$olr->type",
            'status'    => $request->status,
            'reason'    => $request->reason,
        );

        $device_detail = DeviceDetail::where('user_id', $olr->salesman_id)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        saveNotificaiton($dataNofi);

        return prepareResult(true, $olr, [], "Over due limit sent", $this->success);
    }

    private function validations($input, $type)
    {
        $validator = [];
        $errors = [];
        $error = false;
        if ($type == "salesman") {
            $validator = \Validator::make($input, [
                'salesman_id'   => 'integer|exists:users,id',
                'customer_id'   => 'integer|exists:users,id',
                'type'          => 'required',
                'supervisor_id' => 'integer|exists:users,id',
            ]);
        }

        if ($type == "supervisor") {
            $validator = \Validator::make($input, [
                'uuid'  => 'required',
                'status' => 'required',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }
}
