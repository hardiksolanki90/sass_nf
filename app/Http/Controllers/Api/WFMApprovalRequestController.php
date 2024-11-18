<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\AssignTemplate;
use App\Model\CodeSetting;
use App\Model\Collection;
use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\CustomerInfo;
use App\Model\CustomerGroupMail;
use App\Model\DebitNote;
use App\Model\DebitNoteDetail;
use App\Model\Delivery;
use App\Model\DeliveryDetail;
use App\Model\Group;
use App\Model\GroupCustomer;
use App\Model\Goodreceiptnote;
use App\Model\Goodreceiptnotedetail;
use App\Model\Invoice;
use App\Model\Item;
use App\Model\ItemBasePrice;
use App\Model\ItemMainPrice;
use App\Model\JourneyPlan;
use App\Model\LoadRequest;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\DeviceDetail;

use App\Model\OrganisationRole;
use App\Model\ReturnView;
use App\Model\SalesmanInfo;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowObjectAction;
use App\Model\WorkFlowRuleApprovalRole;
use App\Model\WorkFlowRuleApprovalUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\rfGenView;
use App\Model\SalesmanUnload;
use App\Model\SalesmanUnloadDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
use Meneses\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\Mpdf;


class WFMApprovalRequestController extends Controller
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

        $workFlowRules = WorkFlowObject::select(
            'work_flow_objects.id as id',
            'work_flow_objects.uuid as uuid',
            'work_flow_objects.work_flow_rule_id',
            'work_flow_objects.module_name',
            'work_flow_objects.request_object',
            'work_flow_objects.currently_approved_stage',
            'work_flow_rules.work_flow_rule_name',
            'work_flow_rules.description',
            'work_flow_rules.event_trigger'
        )
            ->withoutGlobalScope('organisation_id')
            ->join('work_flow_rules', function ($join) {
                $join->on('work_flow_objects.work_flow_rule_id', '=', 'work_flow_rules.id');
            })
            ->where('work_flow_objects.organisation_id', auth()->user()->organisation_id)
            ->where('status', '1')
            ->where('is_approved_all', '0')
            ->where('is_anyone_reject', '0')
            ->get();

        $results = [];
        foreach ($workFlowRules as $key => $obj) {
            $checkCondition = WorkFlowRuleApprovalRole::query();
            if ($obj->currently_approved_stage > 0) {
                $checkCondition->skip($obj->currently_approved_stage);
            }

            $getResult = $checkCondition->where('work_flow_rule_id', $obj->work_flow_rule_id)
                ->orderBy('id', 'ASC')
                ->first();
            $userIds = [];
            if (is_object($getResult) && $getResult->workFlowRuleApprovalUsers->count() > 0) {
                //User based approval
                foreach ($getResult->workFlowRuleApprovalUsers as $prepareUserId) {
                    $userIds[] = $prepareUserId->user_id;
                }

                if (in_array(auth()->id(), $userIds)) {
                    $results[] = [
                        'object' => $obj,
                        'Action' => 'User',
                    ];
                }
            } else {
                //Roles based approval
                if (is_object($getResult) && $getResult->organisation_role_id == auth()->user()->role_id) {
                    $results[] = [
                        'object' => $obj,
                        'Action' => 'Role',
                    ];
                }
            }
        }

        return prepareResult(true, $results, [], "Request for approval.", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function action(Request $request, $uuid)
    { 
       
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating approval action", $this->unprocessableEntity);
        }
        $is_approved='0';

        DB::beginTransaction();
        try {
            $actionPerformed = WorkFlowObject::where('uuid', $uuid)
                ->first();
            if (is_object($actionPerformed)) {
                if (request()->user()->usertype == 1 || $actionPerformed->workFlowRule->is_or == 1) {
                    if ($request->action == 1) {
                        if (is_object($actionPerformed->workFlowRule->workFlowRuleApprovalUsers)) {
                            foreach ($actionPerformed->workFlowRule->workFlowRuleApprovalUsers as $approve_user) {
                                $actionPerformed->currently_approved_stage = $actionPerformed->currently_approved_stage + 1;
                            }
                        }
                    } else {
                        $actionPerformed->is_anyone_reject = 1;
                    }
                    $actionPerformed->save();

                    if (is_object($actionPerformed->workFlowRule->workFlowRuleApprovalUsers)) {
                        foreach ($actionPerformed->workFlowRule->workFlowRuleApprovalUsers as $approve_user) {
                            //Add log
                            $addLog = new WorkFlowObjectAction;
                            $addLog->work_flow_object_id = $actionPerformed->id;
                            $addLog->user_id = $approve_user->user_id;
                            $addLog->approved_or_rejected = $request->action;
                            $addLog->save();
                        }
                    }
                } else {
                    if ($request->action == 1) {
                        $actionPerformed->currently_approved_stage = $actionPerformed->currently_approved_stage + 1;
                    } else {
                        $actionPerformed->is_anyone_reject = 1;
                    }
                    $actionPerformed->save();

                    //Add log
                    $addLog = new WorkFlowObjectAction;
                    $addLog->work_flow_object_id = $actionPerformed->id;
                    $addLog->user_id = auth()->id();
                    $addLog->approved_or_rejected = $request->action;
                    $addLog->save();
                }
                ////Check All Approved
                $totalLevelDefine = $actionPerformed->workFlowRule->workFlowRuleApprovalRoles->count();
                $countActionTotal = $actionPerformed->workFlowObjectActions->count();
                if ($totalLevelDefine <= $countActionTotal) {
                    $actionPerformed->is_approved_all = 1;
                    $actionPerformed->save();

                    $getObj = $actionPerformed->request_object;
                    if ($actionPerformed->workFlowRule->event_trigger == 'deleted') {
                        //delete logic here according to module
                    } else {
                        //add && update logic here according to module
                        if ($request->action == 1) {
                            if ($actionPerformed->module_name == 'Customer') {
                                $CustomerInfo = CustomerInfo::find($actionPerformed->raw_id);
                                $CustomerInfo->current_stage = 'Approved';
                                $CustomerInfo->save();
                            } else if ($actionPerformed->module_name == 'Journey Plan') {
                                $JourneyPlan = JourneyPlan::find($actionPerformed->raw_id);
                                $JourneyPlan->current_stage = 'Approved';
                                $JourneyPlan->save();
                            }  else if ($actionPerformed->module_name == 'Credit Note') {

                                $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                $delivery = Delivery::where('customer_id', $CreditNote->customer_id)->where('delivery_date', date('Y-m-d'))->whereIn('approval_status', ['Shipment','Truck Allocated'])->first();
                                //dd($CreditNote->id,$CreditNote, 'test2', $delivery);
                                if ($delivery) {
                                    $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                    //dd($CreditNote);
                                    $CreditNote->delivery_driver_id = $CreditNote->salesman_id;
                                    $CreditNote->salesman_id = $delivery->salesman_id;
                                    $CreditNote->current_stage   = 'Approved';
                                    $CreditNote->approval_status = 'Truck Allocated';
                                    $CreditNote->status = 1;
                                    $CreditNote->approval_date = now();
                                    $CreditNote->truck_allocated_date = now();
                                    $CreditNote->save();
                                    $creditNoteDetail = CreditNoteDetail::where('credit_note_id', $CreditNote->id)->update(['salesman_id'=>$delivery->salesman_id]);
                                }else {

                                    $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                    $CreditNote->current_stage = 'Approved';
                                    $CreditNote->status = 1;
                                    $CreditNote->approval_date = now();
                                    $CreditNote->save();

                                    $is_approved='1';
                                }
                            } else if ($actionPerformed->module_name == 'Invoice') {
                                $Invoice = Invoice::find($actionPerformed->raw_id);
                                $Invoice->current_stage = 'Approved';
                                $Invoice->save();
                            } else if ($actionPerformed->module_name == 'Deliviery') {
                                $Delivery = Delivery::find($actionPerformed->raw_id);
                                $Delivery->current_stage = 'Approved';
                                $Delivery->save();
                            } else if ($actionPerformed->module_name == 'Order') {
                                $Order = Order::find($actionPerformed->raw_id);
								$Order->current_stage = 'Approved';
								$Order->save();
								$this->sendWarehouseAndScNotification($actionPerformed, $Order);
								$this->generateDelivery($Order);
                            } else if ($actionPerformed->module_name == 'Salesman') {
                                $SalesmanInfo = SalesmanInfo::find($actionPerformed->raw_id);
                                $SalesmanInfo->current_stage = 'Approved';
                                $SalesmanInfo->save();
                            } else if ($actionPerformed->module_name == 'Debit Note') {
                                $DebitNote = DebitNote::find($actionPerformed->raw_id);
                                $DebitNote->current_stage = 'Approved';
                                $DebitNote->save();
                            } else if ($actionPerformed->module_name == 'Collection') {
                                $Collection = Collection::find($actionPerformed->raw_id);
                                $Collection->current_stage = 'Approved';
                                $Collection->save();
                            } else if ($actionPerformed->module_name == 'Load Request') {
                                $loadr = LoadRequest::find($actionPerformed->raw_id);
                                $loadr->current_stage = 'Approved';
                                $loadr->save();
                            } else if ($actionPerformed->module_name == 'SalesmanUnload') {
                                $unload = SalesmanUnload::find($actionPerformed->raw_id);
                                $unload->current_stage = 'Approved';
                                $unload->save();
                                $this->generateUnloadDebitNote($unload->id);
                                $this->returnViweEntryUnLoad($unload);
                            } else if ($actionPerformed->module_name == 'GRN') {
                                $grn = Goodreceiptnote::find($actionPerformed->raw_id);
                                $grn->current_stage = 'Approved';
                                $grn->save();
                                $this->generateGRNDebitNote($grn->id);
                                $this->returnViweEntryGRN($grn);
                                if ($grn->credit_note_id) {
                                    $this->postReturnInJDE($grn->credit_note_id);
                                }
                            }
                        } else {
                            if ($actionPerformed->module_name == 'Customer') {
                                $CustomerInfo = CustomerInfo::find($actionPerformed->raw_id);
                                $CustomerInfo->current_stage = 'Rejected';
                                $CustomerInfo->save();
                            } else if ($actionPerformed->module_name == 'Journey Plan') {
                                $JourneyPlan = JourneyPlan::find($actionPerformed->raw_id);
                                $JourneyPlan->current_stage = 'Rejected';
                                $JourneyPlan->save();
                            } else if ($actionPerformed->module_name == 'Credit Note') {
                                $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                $CreditNote->current_stage = 'Rejected';
                                $CreditNote->save();
                            } else if ($actionPerformed->module_name == 'Invoice') {
                                $Invoice = Invoice::find($actionPerformed->raw_id);
                                $Invoice->current_stage = 'Rejected';
                                $Invoice->save();
                            } else if ($actionPerformed->module_name == 'Deliviery') {
                                $Delivery = Delivery::find($actionPerformed->raw_id);
                                $Delivery->current_stage = 'Rejected';
                                $Delivery->save();
                            } else if ($actionPerformed->module_name == 'Order') {
                                $Order = Order::find($actionPerformed->raw_id);
                                $Order->current_stage = 'Rejected';
                                $Order->save();
                            } else if ($actionPerformed->module_name == 'Salesman') {
                                $SalesmanInfo = SalesmanInfo::find($actionPerformed->raw_id);
                                $SalesmanInfo->current_stage = 'Rejected';
                                $SalesmanInfo->save();
                            } else if ($actionPerformed->module_name == 'Debit Note') {
                                $DebitNote = DebitNote::find($actionPerformed->raw_id);
                                $DebitNote->current_stage = 'Rejected';
                                $DebitNote->save();
                            } else if ($actionPerformed->module_name == 'Collection') {
                                $Collection = Collection::find($actionPerformed->raw_id);
                                $Collection->current_stage = 'Rejected';
                                $Collection->save();
                            } else if ($actionPerformed->module_name == 'SalesmanUnload') {
                                $Collection = SalesmanUnload::find($actionPerformed->raw_id);
                                $Collection->current_stage = 'Rejected';
                                $Collection->save();
                            }
                        }
                    }
                }

                DB::commit();
                if($is_approved=='1'){

                    if($CreditNote->approval_status != "Truck Allocated")
                    {
                        $merchandId = $CreditNote->salesman_id;
                    }else{
                        $merchandId = $CreditNote->delivery_driver_id;
                    }

                    $s_info = SalesmanInfo::where('user_id', $CreditNote->salesman_id)->first();
                    $name = $s_info->user->getName();
                    $s_code = $s_info->salesman_code;
                  
                    $customerInfo = $CreditNote->customerInfo;
                    $customerGRV = $CreditNote->customer_reference_number;
                    $credit_note_no = $CreditNote->credit_note_number;
                    $message = "Customer " . $customerInfo->customer_code . ' ' . $customerInfo->user->getName() ." Credit Note no : " .  $credit_note_no . " , GRV requisition no : " . $customerGRV . " has been Approved Successfully";

                    $dataNofi = array(
                        'uuid'          => $CreditNote->uuid,
                        'user_id'       => $merchandId,
                        'type'          => "Return",
                        'other'         => $merchandId,
                        'message'       => $message,
                        'status'        => 1,
                        'title'         => "Grv Approved",
                        'noti_type'     => "Grv Approved",
                        'reason'        => "",
                        'customer_id'   => $CreditNote->customer_id
                    );

                    $device_detail = DeviceDetail::where('user_id', $merchandId)
                        ->orderBy('id', 'desc')
                        ->first();

                    if (is_object($device_detail)) {
                        $t = $device_detail->device_token;
                        sendNotificationAndroid($dataNofi, $t);
                    }

                    if($merchandId)
                    {
                        saveNotificaiton($dataNofi);
                    }

                }

                return prepareResult(true, $addLog, [], "Action completed successfully", $this->success);
            } else {
                return prepareResult(false, [], "Record not found", "Record not found", $this->internal_server_error);
            }
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'action' => 'required',
            ]);
        }

        if ($type == "bulkAdd") {
            $validator = Validator::make($input, [
                'action' => 'required',
                'uuids' => 'required',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * This is the bulk approval
     *
     * @param Request $request
     * @return void
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "Unauthorized access"], "Unauthorized access.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "bulkAdd");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating approval action", $this->unprocessableEntity);
        }

        foreach ($request->uuids as $uuid) {
            DB::beginTransaction();
            try {
                $actionPerformed = WorkFlowObject::where('uuid', $uuid)
                    ->first();

                if (is_object($actionPerformed)) {
                    if (request()->user()->usertype == 1 || $actionPerformed->workFlowRule->is_or == 1) {

                        if ($request->action == 1) {
                            if (is_object($actionPerformed->workFlowRule->workFlowRuleApprovalUsers)) {
                                foreach ($actionPerformed->workFlowRule->workFlowRuleApprovalUsers as $approve_user) {
                                    $actionPerformed->currently_approved_stage = $actionPerformed->currently_approved_stage + 1;
                                }
                            }
                        } else {
                            $actionPerformed->is_anyone_reject = 1;
                        }
                        $actionPerformed->save();

                        if (is_object($actionPerformed->workFlowRule->workFlowRuleApprovalUsers)) {
                            foreach ($actionPerformed->workFlowRule->workFlowRuleApprovalUsers as $approve_user) {
                                //Add log
                                $addLog = new WorkFlowObjectAction;
                                $addLog->work_flow_object_id = $actionPerformed->id;
                                $addLog->user_id = $approve_user->user_id;
                                $addLog->approved_or_rejected = $request->action;
                                $addLog->save();
                            }
                        }
                    } else {
                        if ($request->action == 1) {
                            $actionPerformed->currently_approved_stage = $actionPerformed->currently_approved_stage + 1;
                        } else {
                            $actionPerformed->is_anyone_reject = 1;
                        }
                        $actionPerformed->save();

                        //Add log
                        $addLog = new WorkFlowObjectAction;
                        $addLog->work_flow_object_id = $actionPerformed->id;
                        $addLog->user_id = auth()->id();
                        $addLog->approved_or_rejected = $request->action;
                        $addLog->save();
                    }

                    $totalLevelDefine = $actionPerformed->workFlowRule->workFlowRuleApprovalRoles->count();
                    $countActionTotal = $actionPerformed->workFlowObjectActions->count();

                    if ($totalLevelDefine <= $countActionTotal) {
                        $actionPerformed->is_approved_all = 1;
                        $actionPerformed->save();

                        $getObj = $actionPerformed->request_object;
                        if ($actionPerformed->workFlowRule->event_trigger == 'deleted') {
                            //delete logic here according to module
                        } else {

                            //add && update logic here according to module
                            $wfrau = WorkFlowRuleApprovalUser::where('work_flow_rule_id', $actionPerformed->work_flow_rule_id)->get();
                            if ($request->action == 1) {
                                if ($actionPerformed->module_name == 'Customer') {
                                    $CustomerInfo = CustomerInfo::find($actionPerformed->raw_id);
                                    $CustomerInfo->current_stage = 'Approved';
                                    $CustomerInfo->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $CustomerInfo);
                                } else if ($actionPerformed->module_name == 'Journey Plan') {

                                    $JourneyPlan = JourneyPlan::find($actionPerformed->raw_id);
                                    $JourneyPlan->current_stage = 'Approved';
                                    $JourneyPlan->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $JourneyPlan);
                                } else if ($actionPerformed->module_name == 'Credit Note') {
                                    $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                    $CreditNote->current_stage = 'Approved';
                                    $CreditNote->approval_date = now();
                                    $CreditNote->status = 1;
                                    $CreditNote->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $CreditNote);
                                } else if ($actionPerformed->module_name == 'Invoice') {

                                    $Invoice = Invoice::find($actionPerformed->raw_id);
                                    $Invoice->current_stage = 'Approved';
                                    $Invoice->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Invoice);
                                } else if ($actionPerformed->module_name == 'Deliviery') {

                                    $Delivery = Delivery::find($actionPerformed->raw_id);
                                    $Delivery->current_stage = 'Approved';
                                    $Delivery->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Delivery);
                                } else if ($actionPerformed->module_name == 'Order') {
                                    $Order = Order::find($actionPerformed->raw_id);

                                    $errors = $this->checkOrderDetailPrice($Order);
                                    if ($errors['status'] === false) {
                                        return prepareResult(false, [], $errors, "Order not imported", $this->unprocessableEntity);
                                    }
                                    $Order->current_stage = 'Approved';
                                    $Order->save();
                                    // $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Order);
                                    $this->sendWarehouseAndScNotification($actionPerformed, $Order);

                                    $this->generateDelivery($Order);
                                } else if ($actionPerformed->module_name == 'Salesman') {

                                    $SalesmanInfo = SalesmanInfo::find($actionPerformed->raw_id);
                                    $SalesmanInfo->current_stage = 'Approved';
                                    $SalesmanInfo->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $SalesmanInfo);
                                } else if ($actionPerformed->module_name == 'Debit Note') {

                                    $DebitNote = DebitNote::find($actionPerformed->raw_id);
                                    $DebitNote->current_stage = 'Approved';
                                    $DebitNote->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $DebitNote);
                                } else if ($actionPerformed->module_name == 'Collection') {
                                    $load_request = Collection::find($actionPerformed->raw_id);
                                    $load_request->current_stage = 'Approved';
                                    $load_request->save();
                                } else if ($actionPerformed->module_name == 'Load Request') {
                                    $load_request = LoadRequest::find($actionPerformed->raw_id);
                                    $load_request->current_stage = 'Approved';
                                    $load_request->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $load_request);
                                } else if ($actionPerformed->module_name == 'SalesmanUnload') {
                                    $unload = SalesmanUnload::find($actionPerformed->raw_id);
                                    $unload->current_stage = 'Approved';
                                    $unload->save();
                                    $this->generateUnloadDebitNote($unload->id);
                                    $this->returnViweEntryUnLoad($unload);
                                } else if ($actionPerformed->module_name == 'GRN') {
                                    $grn = Goodreceiptnote::find($actionPerformed->raw_id);
                                    $grn->current_stage = 'Approved';
                                    $grn->save();
                                    $this->generateGRNDebitNote($grn->id);
                                    $this->returnViweEntryGRN($grn);
                                    if ($grn->credit_note_id) {
                                        $this->postReturnInJDE($grn->credit_note_id);
                                    }
                                }
                            } else {
                                if ($actionPerformed->module_name == 'Customer') {
                                    $CustomerInfo = CustomerInfo::find($actionPerformed->raw_id);
                                    $CustomerInfo->current_stage = 'Rejected';
                                    $CustomerInfo->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $CustomerInfo);
                                } else if ($actionPerformed->module_name == 'Journey Plan') {
                                    $JourneyPlan = JourneyPlan::find($actionPerformed->raw_id);
                                    $JourneyPlan->current_stage = 'Rejected';
                                    $JourneyPlan->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $JourneyPlan);
                                } else if ($actionPerformed->module_name == 'Credit Note') {
                                    $CreditNote = CreditNote::find($actionPerformed->raw_id);
                                    $CreditNote->current_stage = 'Rejected';
                                    $CreditNote->approval_date = now();
                                    $CreditNote->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $CreditNote);
                                } else if ($actionPerformed->module_name == 'Invoice') {
                                    $Invoice = Invoice::find($actionPerformed->raw_id);
                                    $Invoice->current_stage = 'Rejected';
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Invoice);
                                    $Invoice->save();
                                } else if ($actionPerformed->module_name == 'Deliviery') {
                                    $Delivery = Delivery::find($actionPerformed->raw_id);
                                    $Delivery->current_stage = 'Rejected';
                                    $Delivery->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Delivery);
                                } else if ($actionPerformed->module_name == 'Order') {
                                    $Order = Order::find($actionPerformed->raw_id);
                                    $Order->current_stage = 'Rejected';
                                    $Order->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Order);
                                } else if ($actionPerformed->module_name == 'Salesman') {
                                    $SalesmanInfo = SalesmanInfo::find($actionPerformed->raw_id);
                                    $SalesmanInfo->current_stage = 'Rejected';
                                    $SalesmanInfo->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $SalesmanInfo);
                                } else if ($actionPerformed->module_name == 'Debit Note') {
                                    $DebitNote = DebitNote::find($actionPerformed->raw_id);
                                    $DebitNote->current_stage = 'Rejected';
                                    $DebitNote->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $DebitNote);
                                } else if ($actionPerformed->module_name == 'Collection') {
                                    $Collection = Collection::find($actionPerformed->raw_id);
                                    $Collection->current_stage = 'Rejected';
                                    $Collection->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $Collection);
                                } else if ($actionPerformed->module_name == 'Load Request') {
                                    $load_request = LoadRequest::find($actionPerformed->raw_id);
                                    $load_request->current_stage = 'Rejected';
                                    $load_request->save();
                                    $this->sendNotificationToNextUser($wfrau, $actionPerformed, $load_request);
                                } else if ($actionPerformed->module_name == 'SalesmanUnload') {
                                    $unload = SalesmanUnload::find($actionPerformed->raw_id);
                                    $unload->current_stage = 'Rejected';
                                    $unload->save();
                                } else if ($actionPerformed->module_name == 'GRN') {
                                    $grn = Goodreceiptnote::find($actionPerformed->raw_id);
                                    $grn->current_stage = 'Rejected';
                                    $grn->save();
                                }
                            }
                        }
                    }
                    DB::commit();
                } else {
                    // return prepareResult(false, [], "Record not found", "Record not found", $this->internal_server_error);
                }
            } catch (\Exception $exception) {
                DB::rollback();
                // return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            } catch (\Throwable $exception) {
                DB::rollback();
                // return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            }
        }
        return prepareResult(true, [], [], "Action completed successfully", $this->success);
    }

    /*
     * This function send to the next user to notification
     * Created By Hardik Solanki
     */
    private function sendNotificationToNextUser($wfrau, $actionPerformed, $obj)
    {
        if (count($wfrau)) {
            foreach ($wfrau as $user) {
                $wfoa = WorkFlowObjectAction::where('user_id', $user->user_id)->first();
                if (!is_object($wfoa)) {
                    // Send Notification
                    $data = array(
                        'uuid' => (is_object($obj)) ? $obj->uuid : 0,
                        'user_id' => $user->user_id,
                        'type' => $actionPerformed->module_name,
                        'message' => "Approve the New " . $actionPerformed->module_name,
                        'status' => 1,
                    );
                    saveNotificaiton($data);
                }
            }
        }

        if ($obj->current_stage != 'Rejected') {
            $this->sendWarehouseAndScNotification($actionPerformed, $obj);
        }
    }

    /**
     * $obj = Order Object
     */
    private function generateDelivery($obj)
    {
        $orders = Order::where('id', $obj->id)->get();

        if (count($orders)) {
            foreach ($orders as $order) {
                $code = $this->dCode();

                DB::beginTransaction();
                try {
                    $status = 1;
                    $current_stage = 'Approved';
                    $current_organisation_id = request()->user()->organisation_id;
                    if ($isActivate = checkWorkFlowRule('Deliviery', 'create', $current_organisation_id)) {
                        $status = 0;
                        $current_stage = 'Pending';
                        //$this->createWorkFlowObject($isActivate, 'Deliviery);
                    }

                    $is_delivery_exist = Delivery::where('delivery_number', $code['number_is'])
                        ->first();

                    if ($is_delivery_exist) {
                        updateNextComingNumber('App\Model\Delivery', 'delivery');
                        continue;
                    }

                    $is_delivery_with_order_exist = Delivery::where('order_id', $order->id)
                        ->where('delivery_number', $code['number_is'])
                        ->first();


                    if ($is_delivery_with_order_exist) {
                        continue;
                    }
                    //pre($order);
                    // save Delivery
                    $delivery = $this->saveOrderDelivery($order, $code, $status, $current_stage);
                    // save Delivery Details
                    $this->saveOrderDeliveryDetails($order, $delivery);

                    if ($isActivate = checkWorkFlowRule('Delivery', 'create', $current_organisation_id)) {
                        $this->createWorkFlowObject($isActivate, 'Delivery', $order, $delivery);
                    }

                    DB::commit();
                    $order->sync_status = null;
                    $order->save();

                    //$this->sendCustomerMailFile($delivery, $order);
                } catch (\Exception $exception) {
                    DB::rollback();
                    $order->sync_status = $exception;
                    $order->save();
                    // return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                } catch (\Throwable $exception) {
                    DB::rollback();
                    $order->sync_status = $exception;
                    $order->save();
                    // return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                }
            }
        }
    }

    private function sendWarehouseAndScNotification($actionPerformed, $obj)
    {
        $orgRole = OrganisationRole::whereIn('name', ['Warehouse', 'SC'])->get();
        if (count($orgRole)) {
            $role_id = $orgRole->pluck('id')->toArray();
            if (count($role_id)) {
                $users = User::whereIn('role_id', $role_id)->get();
                if (count($users)) {
                    foreach ($users as $u) {
                        $data = array(
                            'uuid' => (is_object($obj)) ? $obj->uuid : 0,
                            'user_id' => $u->id,
                            'type' => $actionPerformed->module_name,
                            'message' => "Order " . $obj->order_number . " is approved by " . request()->user()->getName(),
                            'status' => 1,
                        );
                        saveNotificaiton($data);
                    }
                }
            }
        }
    }

    public function saverfGen($deliveryDetail, $order_detail, $order)
    {
        $rf_gen = new rfGenView();
        $rf_gen->GLDate         = Carbon::parse($deliveryDetail->created_at)->format('Y-m-d');
        $rf_gen->item_id        = $deliveryDetail->item_id;
        $rf_gen->ITM_CODE       = model($deliveryDetail->item, 'item_code');
        $rf_gen->ITM_NAME       = model($deliveryDetail->item, 'item_name');
        $rf_gen->TranDate       = model($deliveryDetail->delivery->order, 'order_date');
        $rf_gen->Order_Number   = model($deliveryDetail->delivery->order, 'order_number');
        $rf_gen->LOAD_NUMBER    = $deliveryDetail->delivery_id;
        $rf_gen->MCU_CODE       = model($deliveryDetail->delivery->storageocation, 'code');
        $rf_gen->DemandPUOM     = ($order_detail->item_uom_id == model($deliveryDetail->item, 'lower_unit_uom_id')) ? $order_detail->item_qty : 0;
        $rf_gen->DemandSUOM     = ($order_detail->item_uom_id != model($deliveryDetail->item, 'lower_unit_uom_id')) ? $order_detail->item_qty : 0;
        $rf_gen->mobiato_order_picked = 0;
        $rf_gen->order_detail_id = $order_detail->id;
        $rf_gen->RTE_CODE       = "MT1";
        $rf_gen->save();
    }

    public function generateGRNDebitNote($grn)
    {
        $grnd = Goodreceiptnotedetail::where('good_receipt_note_id', $grn)
            ->whereColumn('qty', '<', 'original_item_qty')
            ->get();

        $debit_note_number = '';
        if (count($grnd)) {
            foreach ($grnd as $key => $grn) {
                if ($grn) {
                    if ($debit_note_number == '') {
                        $debitnote = $this->debitNoteHeader($grn);
                        $debit_note_number = $debitnote->debit_note_number;
                    } else {
                        $debitnote = DebitNote::where('debit_note_number', $debit_note_number)->first();
                    }

                    $this->debitNoteDetail($debitnote, $grn);
                }
            }
            return prepareResult(true, [], [], "Debit note added successfully", $this->created);
        }
    }

    private function generateUnloadDebitNote($unload)
    {
        $suds = SalesmanUnloadDetail::where('salesman_unload_id', $unload)
            ->whereColumn('unload_qty', '<', 'original_item_qty')
            ->get();

        $debit_note_number = '';
        if (count($suds)) {
            foreach ($suds as $key => $sud) {
                if ($sud) {
                    if ($debit_note_number == '') {
                        $debitnote = $this->debitNoteHeader($sud, 'Unload');
                        $debit_note_number = $debitnote->debit_note_number;
                    } else {
                        $debitnote = DebitNote::where('debit_note_number', $debit_note_number)->first();
                    }

                    $this->debitNoteDetail($debitnote, $sud, 'Unload');
                }
            }

            return prepareResult(true, [], [], "Debit note added successfully", $this->created);
        }
    }

    private function debitNoteHeader($detail, $type = 'GRN')
    {
        $status = 1;
        $current_stage = 'Approved';
        $current_organisation_id = request()->user()->organisation_id;
        if ($isActivate = checkWorkFlowRule('Debit Note', 'create', $current_organisation_id)) {
            $status = 0;
            $current_stage = 'Pending';
        }

        $variable = "debit_note";
        $nextComingNumber['number_is'] = null;
        $nextComingNumber['prefix_is'] = null;
        if (CodeSetting::count() > 0) {
            $code_setting = CodeSetting::first();
            if ($code_setting['is_final_update_' . $variable] == 1) {
                $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
            }
        }

        if (isset($nextComingNumber['number_is'])) {
            $code = $nextComingNumber['number_is'];
        } else {
            $codeS = CodeSetting::where('is_final_update_debit_note', 0)->first();
            $code = "10170000";
            if ($codeS) {
                $codeS->prefix_code_debit_note = "101";
                $codeS->start_code_debit_note = "70000";
                $codeS->next_coming_number_debit_note = "10170000";
                $codeS->is_final_update_debit_note = 1;
                $codeS->save();
            }
        }

        $debitnote = new DebitNote();
        if ($type == "Unload") {
            $debitnote->customer_id             = (!empty($detail->salesmanUnload->salesman_id)) ? $detail->salesmanUnload->salesman_id : null;
            $debitnote->salesman_id             = (!empty($detail->salesmanUnload->salesman_id)) ? $detail->salesmanUnload->salesman_id : null;
            $debitnote->route_id                = (!empty($detail->salesmanUnload->route_id)) ? $detail->salesmanUnload->route_id : null;
            $debitnote->total_qty               = $detail->original_item_qty - $detail->unload_qty;
            $debitnote->reason                  = null;
        } else {
            $debitnote->customer_id             = (!empty($detail->goodReceiptNote->salesman_id)) ? $detail->goodReceiptNote->salesman_id : null;
            $debitnote->salesman_id             = (!empty($detail->goodReceiptNote->salesman_id)) ? $detail->goodReceiptNote->salesman_id : null;
            $debitnote->route_id                = (!empty($detail->goodReceiptNote->route_id)) ? $detail->goodReceiptNote->route_id : null;
            $debitnote->total_qty               = $detail->original_item_qty - $detail->qty;
            $debitnote->reason                  = $detail->reason;
            $debitnote->debit_note_type         = "grn";
        }
        $debitnote->debit_note_date         = now();
        $debitnote->debit_note_number       = nextComingNumber('App\Model\DebitNote', 'debit_note', 'debit_note_number', $code);
        $debitnote->total_gross             = 0;
        $debitnote->total_discount_amount   = 0;
        $debitnote->total_net               = 0;
        $debitnote->total_vat               = 0;
        $debitnote->total_excise            = 0;
        $debitnote->grand_total             = 0;
        $debitnote->pending_credit          = 0;
        $debitnote->debit_note_comment      = null;
        $debitnote->source                  = 3;
        $debitnote->status                  = $status;
        $debitnote->approval_status         = "Created";
        $debitnote->lob_id                  = null;
        $debitnote->save();

        updateNextComingNumber('App\Model\DebitNote', 'debit_note');

        return $debitnote;
    }

    private function debitNoteDetail($debitnote, $detail, $type = 'GRN')
    {
        if ($type == "Unload") {
            $item_price_obj = ItemBasePrice::where('item_id', $detail->item_id)
                ->where('item_uom_id', $detail->item_uom)
                ->where('start_date', '<=', date('Y-m-d'))
                ->where('end_date', '>=', date('Y-m-d'))
                ->orderBy('updated_at', 'asc')
                ->first();
        } else {
            $item_price_obj = ItemBasePrice::where('item_id', $detail->item_id)
                ->where('item_uom_id', $detail->item_uom_id)
                ->where('start_date', '<=', date('Y-m-d'))
                ->where('end_date', '>=', date('Y-m-d'))
                ->orderBy('updated_at', 'asc')
                ->first();
        }

        $item = Item::find($detail->item_id);
        $item_price = ($item_price_obj) ? $item_price_obj->price : 0;
        if ($type == 'Unload') {
            $qty = $detail->original_item_qty - $detail->unload_qty;
        } else {
            $qty = $detail->original_item_qty - $detail->qty;
        }
        $total_price = $item_price + (($item->is_item_excise == 1) ? $item->item_excise : 0);
        $item_gross = $qty * $total_price;
        $net_gross = $qty * $item_price;
        $item_excise = ($item->is_item_excise == 1) ? $item->item_excise : 0;
        $net_excise = $qty * ($item->is_item_excise == 1) ? $item->item_excise : 0;
        $total_net = $item_gross - 0;
        $item_vat = ($total_net * 5) / 100;
        $total = $total_net + $item_vat;

        $debitnoteDetail = new DebitNoteDetail();
        $debitnoteDetail->debit_note_id         = $debitnote->id;
        if ($type == 'Unload') {
            $debitnoteDetail->item_id               = $detail->item_id;
            $debitnoteDetail->item_uom_id           = $detail->item_uom;
            $debitnoteDetail->item_qty              = $detail->original_item_qty - $detail->unload_qty;
            $debitnoteDetail->item_condition        = $detail->unload_type;
        } else {
            $debitnoteDetail->item_id               = $detail->item_id;
            $debitnoteDetail->item_uom_id           = $detail->item_uom_id;
            $debitnoteDetail->item_qty              = $detail->original_item_qty - $detail->qty;
            $debitnoteDetail->item_condition        = 1;
        }
        $debitnoteDetail->discount_id           = 0;
        $debitnoteDetail->is_free               = 0;
        $debitnoteDetail->is_item_poi           = 0;
        $debitnoteDetail->promotion_id          = 0;
        $debitnoteDetail->item_price            = number_format(round($total_price, 2), 2);
        $debitnoteDetail->item_gross            = number_format($item_gross, 2);
        $debitnoteDetail->item_discount_amount  = 0;
        $debitnoteDetail->item_net              = number_format($net_gross, 2);
        $debitnoteDetail->item_vat              = number_format($item_vat, 2);
        $debitnoteDetail->item_excise           = number_format($item_excise, 2);
        $debitnoteDetail->item_grand_total      = number_format($total, 2);
        $debitnoteDetail->batch_number          = 0;
        $debitnoteDetail->reason                = $detail->reason_id;
        $debitnoteDetail->save();

        $debitnote->update([
            'total_qty' => $debitnote->total_qty + $qty,
            'total_gross' => $debitnote->total_gross + $item_gross,
            'total_discount_amount' => 0,
            'total_net' => $debitnote->total_net + $net_gross,
            'total_vat' => $debitnote->total_vat + $item_vat,
            'total_excise' => $debitnote->total_excise + $item_excise,
            'grand_total' => $debitnote->grand_total + $total,
        ]);
    }

    private function returnViweEntryGRN($header)
    {
        if (count($header->goodreceiptnotedetail)) {
            foreach ($header->goodreceiptnotedetail as $details) {
                $item_mp = ItemMainPrice::where('item_id', $details->item_id)
                    ->where('item_uom_id', $details->item_uom)
                    ->where('is_secondary', 1)
                    ->first();

                $ctn_qty = 0;
                $pcs_qty = 0;
                $Expired_PCS = 0;
                $Damaged_PCS = 0;

                $FLAG_GD_PCS = "N";
                $FLAG_GD_CTN = "N";
                $FLAG_DM = "N";
                $FLAG_EX = "N";

                if ($details->reason == "Damage Return") {
                    if ($item_mp) {
                        $get_conversition = getItemDetails2($details->item_id, $details->item_uom_id, $details->qty);
                        $Damaged_PCS = $get_conversition['Qty'];
                    } else {
                        $Damaged_PCS = $details->qty;
                    }
                    $FLAG_DM = "Y";
                } else if ($details->reason == "Expiry Return") {
                    if ($item_mp) {
                        $get_conversition = getItemDetails2($details->item_id, $details->item_uom_id, $details->qty);
                        $Expired_PCS = $get_conversition['Qty'];
                    } else {
                        $Expired_PCS = $details->qty;
                    }
                    $FLAG_EX = "Y";
                }

                ReturnView::create([
                    "MCU_CODE" => (isset($header->destination_warehouse)) ? model($header->destinationWarehouse, 'code') : null,
                    "MCU_NAME" => (isset($header->destination_warehouse)) ? model($header->destinationWarehouse, 'name') : null,
                    "RTE_CODE" => "MT1",
                    "PRE_RTE" => (model($header->van, 'van_code')) ? model($header->van, 'van_code') : null,
                    "TranDate" => Carbon::parse($details->unload_date)->format('Y-m-d'),
                    "SMN_CODE" => model($header->salesmanInfo, 'salesman_code'),
                    "SMN_NAME" => is_object($header->salesman) ? $header->salesman->getName() : "",
                    "ITM_CODE" => model($details->item, 'item_code'),
                    "ITM_NAME" => model($details->item, 'item_name'),
                    "GoodReturn_CTN" => $ctn_qty,
                    "GoodReturn_PCS" => $pcs_qty,
                    "Damaged_PCS" => $Damaged_PCS,
                    "Expired_PCS" => $Expired_PCS,
                    "NearExpiry_PCS" => 0,
                    "FLAG_GD_CTN" => $FLAG_GD_CTN,
                    "FLAG_GD_PCS" => $FLAG_GD_PCS,
                    "FLAG_DM" => $FLAG_DM,
                    "FLAG_EX" => $FLAG_EX,
                    "FLAG_NR" => "N",
                    'mobiato_return_picked' => 0,
                    'good_receipt_note_detail_detail_id' => $details->id,
                ]);
            }
        }
    }

    private function returnViweEntryUnLoad($header)
    {
        if (count($header->salesmanUnloadDetail)) {
            foreach ($header->salesmanUnloadDetail as $load_detail) {
                $item_mp = ItemMainPrice::where('item_id', $load_detail->item_id)
                    ->where('item_uom_id', $load_detail->item_uom)
                    ->where('is_secondary', 1)
                    ->first();

                $ctn_qty = 0;
                $pcs_qty = 0;
                $FLAG_GD_PCS = "N";
                $FLAG_GD_CTN = "N";

                if ($item_mp) {
                    $ctn_qty = $load_detail->unload_qty;
                    $FLAG_GD_CTN = "Y";
                } else {
                    $FLAG_GD_PCS = "Y";
                    $get_conversition = getItemDetails2($load_detail->item_id, $load_detail->item_uom, $load_detail->unload_qty);
                    $pcs_qty = $get_conversition['Qty'];
                }

                ReturnView::create([
                    "MCU_CODE" => (isset($load_detail->storage_location_id)) ? model($load_detail->storageocation, 'code') : null,
                    "MCU_NAME" => (isset($load_detail->storage_location_id)) ? model($load_detail->storageocation, 'name') : null,
                    // "RTE_CODE" => model($header->route, 'route_code'),
                    "RTE_CODE" => "MT1",
                    "PRE_RTE" => (model($header->van, 'van_code')) ? model($header->van, 'van_code') : null,
                    "TranDate" => Carbon::parse($load_detail->unload_date)->format('Y-m-d'),
                    "SMN_CODE" => model($header->salesmanInfo, 'salesman_code'),
                    "SMN_NAME" => is_object($header->salesman) ? $header->salesman->getName() : "",
                    "ITM_CODE" => model($load_detail->item, 'item_code'),
                    "ITM_NAME" => model($load_detail->item, 'item_name'),
                    "GoodReturn_CTN" => $ctn_qty,
                    "GoodReturn_PCS" => $pcs_qty,
                    "Damaged_PCS" => 0,
                    "Expired_PCS" => 0,
                    "NearExpiry_PCS" => 0,
                    "FLAG_GD_CTN" => $FLAG_GD_CTN,
                    "FLAG_GD_PCS" => $FLAG_GD_PCS,
                    "FLAG_DM" => "N",
                    "FLAG_EX" => "N",
                    "FLAG_NR" => "N",
                    'mobiato_return_picked' => 0,
                    'salesman_unload_detail_id' => $load_detail->id,
                ]);
            }
        }
    }

    public function postReturnInJDE($id)
    {
        if (!$id) {
            return;
        }

        $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_order_return_posting.php')
            ->withData(array('orderid' => $id))
            ->returnResponseObject()
            ->get();

        return prepareResult(true, [], [], "Credit Note posted in JDE.", $this->success);
    }

    public function checkOrderDetailPrice($order)
    {
        $errors = [];
        $od = OrderDetail::where('order_id', $order->id)
            ->where('item_price', 0)
            ->get();
        if (count($od)) {
            foreach ($od as $ord) {
                $errors[] = "The order number " . $order->order_number . " have 0 price of " . model($ord->item, 'item_code') . ' before approval delete this item.';
            }

            if (count($errors)) {
                return [
                    'status' => false,
                    'erros' => $errors
                ];
                exit;
            }
        }
    }

    private function sendCustomerMailFile($delivery_invoice, $order)
    {
        $group = Group::where('name', "Lulu")->first();

        $groupCustomer = GroupCustomer::where('group_id', $group->id)
            ->where('customer_id', $delivery_invoice->customer_id)
            ->first();

        if (!$groupCustomer) {
            return;
        }

        $html = view('html.delivery_send_mail', compact('delivery_invoice'))->render();

        if (!is_dir(public_path() . '/uploads/pdf/' . date('Y-m-d'))) {
            mkdir(public_path() . '/uploads/pdf/' . date('Y-m-d'), 0777);
        }

        $pdfFilePath = public_path() . '/uploads/pdf/' . date('Y-m-d') . '/' . "INV-" . model($order, 'customer_lop') . '-' . $delivery_invoice->delivery_number . '.pdf';

        $mpdf = new \Mpdf\Mpdf();

        $mpdf->WriteHTML($html);

        $mpdf->Output($pdfFilePath, 'F');

        $fileURL = 'uploads/pdf/' . date('Y-m-d') . '/' . "INV-" . model($order, 'customer_lop') . '-' . $delivery_invoice->delivery_number . '.pdf';

        $pdfFilePath = url($fileURL);

        CustomerGroupMail::create([
            'date'          => now()->addDay()->format('Y-m-d'),
            'group_id'      => $group->id,
            'customer_id'   => $delivery_invoice->customer_id,
            'file_name'     => date('Y-m-d') . '/' . "INV-" . model($order, 'customer_lop') . '-' . $delivery_invoice->delivery_number,
            'url'           => $pdfFilePath
        ]);
    }

    private function dCode()
    {
        $variable = "delivery";
        $nextComingNumber['number_is'] = null;
        $nextComingNumber['prefix_is'] = null;
        if (CodeSetting::count() > 0) {
            $code_setting = CodeSetting::first();
            if ($code_setting['is_final_update_' . $variable] == 1) {
                $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
            } else {
                $code_setting['is_code_auto_' . $variable] = "1";
                $code_setting['prefix_code_' . $variable] = "DELV0";
                $code_setting['start_code_' . $variable] = "00001";
                $code_setting['next_coming_number_' . $variable] = "DELV000001";
                $code_setting['is_final_update_' . $variable] = "1";
                $code_setting->save();

                $nextComingNumber = "DELV000001";
            }
        }

        return $nextComingNumber;
    }

    private function saveOrderDelivery($order, $code, $status, $current_stage)
    {

        $delivery = new Delivery();
        $delivery->delivery_number = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $code['number_is']);
        $delivery->order_id = $order->id;
        $delivery->customer_id = $order->customer_id;
        $delivery->salesman_id = null;
        $delivery->reason_id = null;
        $delivery->route_id = null;
        $delivery->storage_location_id = (!empty($order->storage_location_id)) ? $order->storage_location_id : null;
        $delivery->warehouse_id = (!empty($order->warehouse_id)) ? $order->warehouse_id : 0;
        $delivery->delivery_type = $order->order_type_id;
        $delivery->delivery_type_source = 2;
        $delivery->delivery_date    = $order->delivery_date;
        $delivery->delivery_time    = (isset($order->delivery_time)) ? $order->delivery_time : date('H:m:s');
        $delivery->delivery_weight  = $order->delivery_weight;
        $delivery->payment_term_id  = $order->payment_term_id;
        // $delivery->total_qty                = $order->total_qty;
        $delivery->total_qty        = 0;
        $delivery->total_gross      = $order->total_gross;
        $delivery->total_discount_amount = $order->total_discount_amount;
        $delivery->total_net        = $order->total_net;
        $delivery->total_vat        = $order->total_vat;
        $delivery->total_excise     = $order->total_excise;
        $delivery->grand_total      = $order->grand_total;
        $delivery->current_stage_comment = $order->current_stage_comment;
        $delivery->delivery_due_date = $order->due_date;
        $delivery->source           = $order->source;
        $delivery->status           = $status;
        $delivery->current_stage    = $current_stage;
        $delivery->approval_status  = "Created";
        $delivery->lob_id           = (!empty($order->lob_id)) ? $order->lob_id : null;
        $delivery->save();

        updateNextComingNumber('App\Model\Delivery', 'delivery');

        $data = [
            'created_user'          => request()->user()->id,
            'order_id'              => $order->id,
            'delviery_id'           => $delivery->id,
            'updated_user'          => NULL,
            'previous_request_body' => NULL,
            'request_body'          => $delivery,
            'action'                => 'WFM Delivery',
            'status'                => 'Created',
        ];

        saveOrderDeliveryLog($data);

        return $delivery;
    }

    private function saveOrderDeliveryDetails($order, $delivery)
    {
        $t_qty = 0;

        if (count($order->orderDetailsWithoutDelete)) {
            foreach ($order->orderDetailsWithoutDelete as $od) {
                //save DeliveryDetail

                $deliveryDetail = new DeliveryDetail();
                $deliveryDetail->uuid                   = $od->uuid;
                $deliveryDetail->delivery_id            = $delivery->id;
                $deliveryDetail->item_id                = $od->item_id;
                $deliveryDetail->item_uom_id            = $od->item_uom_id;
                $deliveryDetail->original_item_uom_id   = $od->item_uom_id;
                $deliveryDetail->discount_id            = $od->discount_id;
                $deliveryDetail->is_free                = $od->is_free;
                $deliveryDetail->is_item_poi            = $od->is_item_poi;
                $deliveryDetail->promotion_id           = $od->promotion_id;
                $deliveryDetail->reason_id              = null;
                $deliveryDetail->is_deleted             = 0;
                $deliveryDetail->item_qty               = $od->item_qty;
                $deliveryDetail->original_item_qty      = $od->item_qty;
                $deliveryDetail->open_qty               = $od->item_qty;
                $deliveryDetail->item_price             = $od->item_price;
                $deliveryDetail->item_gross             = $od->item_gross;
                $deliveryDetail->item_discount_amount   = $od->item_discount_amount;
                $deliveryDetail->item_net               = $od->item_net;
                $deliveryDetail->item_vat               = $od->item_vat;
                $deliveryDetail->item_excise            = $od->item_excise;
                $deliveryDetail->item_grand_total       = $od->item_grand_total;
                $deliveryDetail->batch_number           = $od->batch_number;
                $deliveryDetail->transportation_status  = "No";
                $deliveryDetail->save();

                $data = [
                    'created_user'          => request()->user()->id,
                    'order_id'              => $order->id,
                    'delviery_id'           => $deliveryDetail->id,
                    'updated_user'          => NULL,
                    'previous_request_body' => NULL,
                    'request_body'          => $deliveryDetail,
                    'action'                => 'WFM Delivery Detail',
                    'status'                => 'Created',
                ];

                saveOrderDeliveryLog($data);

                $this->saverfGen($deliveryDetail, $od, $order);

                $getItemQtyByUom = qtyConversion($od->item_id, $od->item_uom_id, $od->item_qty);

                $t_qty = $t_qty + $getItemQtyByUom['Qty'];
            }
        }

        $delivery->update([
            'total_qty' => $t_qty,
        ]);

        return $delivery;
    }
}
