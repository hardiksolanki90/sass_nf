<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\DeviceDetail;
use App\Model\GeoApproval;
use App\Model\GeoApprovalRequestLog;
use App\Model\OrganisationRoleAttached;
use App\Model\SalesmanInfo;
use App\Model\MerchandiserGeoApproval;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class GeoApprovalController extends Controller
{

    /**
     * This funciton is used for salesman send geo Notification to supervisor
     *
     * @param Request $request
     * @return void
     */
    public function requestGeoApprovalSalesman(Request $request)
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

        $checkMerchandiserRequest = MerchandiserGeoApproval::where('merchandiser_id', $salesmanInfo->user_id)
            ->where('customer_id', $customerInfo->user_id)
            ->get();

        if ((count($checkMerchandiserRequest) + 1) >= 3) {
            // as per sugnesh i have changed unprocessableEntity to success 20-01-23
            return prepareResult(false, [], ['error' => 'You reached maximum limit of geo approval.'], "You reached maximum limit of geo approval.", $this->success);
        }

        if ((count($checkMerchandiserRequest) + 1) === 1) {
            $user_id = $request->supervisor_id;
            $sm = "Request Sent Successfully!!. Once supervisor will approve you can visit this customer.";
        } else if ((count($checkMerchandiserRequest) + 1) === 2) {
            //asm
            $user_id = $salesmanInfo->asm_id;
            $sm = "Request Sent Successfully!!. Once ASM will approve you can visit this customer.";
        } 
        // else if ((count($checkMerchandiserRequest) + 1) === 3) {
        //     //nsm
        //     $user_id = $salesmanInfo->nsm_id;
        //     $sm = "Request Sent Successfully!!. Once NSM will approve you can visit this customer.";
        // } else if ((count($checkMerchandiserRequest) + 1) === 4) {
        //     //director
        //     $users = User::where('role_id', '5')->first();
        //     $user_id = ($users) ? $users->id : 1609;
        //     $sm = "Request Sent Successfully!!. Once Sales Director will approve you can visit this customer.";
        // }

        $geo = new GeoApproval;
        $geo->salesman_id  = $request->salesman_id;
        $geo->salesman_lat  = $request->salesman_lat;
        $geo->salesman_long = $request->salesman_long;
        $geo->customer_id   = $request->customer_id;
        $geo->customer_lat  = $request->customer_lat;
        $geo->customer_long = $request->customer_long;
        $geo->supervisor_id = $user_id;
        $geo->radius        = $request->radius;
        $geo->request_reason        = $request->request_reason;
        $geo->date          = now()->format('Y-m-d');
        $geo->status        = "Pending";
        $geo->save();

        GeoApprovalRequestLog::create([
            'geo_approval_id' => $geo->id,
            'salesman_id' => $request->salesman_id,
            'supervisor_id' => $user_id,
            'customer_id' => $request->customer_id,
            'salesman_lat' => $request->salesman_lat,
            'salesman_long' => $request->salesman_long,
        ]);

        $name = $salesmanInfo->user->getName();
        $s_code = $salesmanInfo->salesman_code;
        $c_name = $customerInfo->user->getName();

        // supervisor
        $message = "$name has requested to geo approval for customer $customerInfo->customer_code - $c_name.";
        // $message = "Salesman $salesmanInfo->salesman_code - $name has requested to geo approval for customer  $customerInfo->customer_code - $c_name.";

        $dataNofi = array(
            'uuid'      => $geo->uuid,
            'user_id'   => $user_id,
            'title'     => "Geo Approval From Salesman",
            'type'      => "Geo Approval",
            'noti_type' => "Geo Approval",
            'sender_id' => $request->salesman_id,
            'other'     => $request->salesman_id,
            'message'   => $message,
            'status'    => 1,
        );

        $device_detail = DeviceDetail::where('user_id', $user_id)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        saveNotificaiton($dataNofi);

        $dataNofi1 = array(
            'uuid'      => $geo->uuid,
            'user_id'   => $user_id,
            'title'     => "Geo Approval From Salesman",
            'type'      => "Geo Approval",
            'noti_type' => "Geo Approval",
            'other'     => $request->salesman_id,
            'message'   => $message,
            'status'    => 1,
        );

        saveNotificaiton($dataNofi1);

        MerchandiserGeoApproval::create([
            'merchandiser_id' => $salesmanInfo->user_id,
            'customer_id' => $customerInfo->user_id,
            'date' => date('Y-m-d')
        ]);

        return prepareResult(true, $geo, [], $sm, $this->success);
    }

    /**
     * This funciton is used for salesman send geo Notification to supervisor
     *
     * @param Request $request
     * @return void
     */
    public function requestGeoApprovalSupervisor(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "supervisor");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating geo", $this->unprocessableEntity);
        }

        $geo = GeoApproval::where('uuid', $request->uuid)->first();
        if ($geo) {
            $geo->status    = $request->status;
            $geo->reason    = $request->reason;
            $geo->request_reason    = $request->request_reason;
            $geo->save();

            $customerInfo = CustomerInfo::where('user_id', $geo->customer_id)->first();
            // $salesmanInfo = SalesmanInfo::where('user_id', $geo->salesman_id)->first();

            // $supervisor = User::find($geo->supervisor_id);
            if (isset($request->user()->id)) {
                $supervisor = $request->user();
            } else {
                $supervisor = User::find($geo->supervisor_id);
            }
            $user = "";
            $c_name = $customerInfo->user->getName();
            $supervisor_name = $supervisor->getName();

            $geoA = GeoApprovalRequestLog::where('geo_approval_id', $geo->id)->first();
            $geoA->request_approval_id = $request->user()->id;
            $geoA->save();

            $message = "The $customerInfo->customer_code -  $c_name geo approval requested has been Approve by $supervisor_name.";

            $dataNofi = array(
                'uuid'      => $geo->uuid,
                'user_id'   => $geo->salesman_id,
                'type'      => "Geo Approval",
                'other'     => $geo->supervisor_id,
                'message'   => $message,
                'title'     => "Geo Approval From Supervisor",
                'type'      => "Geo Approval",
                'noti_type' => "Geo Approval",
                'status'    => $geo->status,
                'reason'    => $geo->reason,
                'customer_id' => $geo->customer_id,
                'lat'       =>  $geo->salesman_lat,
                'long'      =>  $geo->salesman_long
            );

            $device_detail = DeviceDetail::where('user_id', $geo->salesman_id)
                ->orderBy('id', 'desc')
                ->first();

            if (is_object($device_detail)) {
                $t = $device_detail->device_token;
                sendNotificationAndroid($dataNofi, $t);
            }

            saveNotificaiton($dataNofi);

            return prepareResult(true, $geo, [], "Geo Approval sent", $this->success);
        }
        return prepareResult(false, [], [], "Geo Approval not found", $this->not_found);
    }

    private function validations($input, $type)
    {
        $validator = [];
        $errors = [];
        $error = false;
        if ($type == "salesman") {
            $validator = \Validator::make($input, [
                'salesman_id'  => 'integer|exists:users,id',
                'salesman_lat'  => 'required',
                'salesman_long' => 'required',
                'customer_id'   => 'integer|exists:users,id',
                'customer_lat'  => 'required',
                'customer_long' => 'required',
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

    public function geoMail()
    {
        $data = array('content' => "Virat Gandhi", 'name' => 'solanki');

        Mail::send("emails.notification", $data, function ($message) {
            $message->to('sugneshlimbasiya@gmail.com', 'Tutorials Point')
                ->subject('Notication send');
            $message->from('hardiksolanki811@gmail.com', 'Sugnesh');
        });
    }
}
