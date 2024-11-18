<?php

namespace App\Http\Controllers\Api;

use File;
use App\User;
use App\Model\Lob;
use Carbon\Carbon;
use App\Model\Route;
use App\Model\Region;
use App\Model\Channel;
use App\Model\Expense;
use App\Model\Invoice;
use App\Model\Delivery;
use App\Model\Warehouse;
use App\Model\Collection;
use App\Model\CreditNote;
use App\Model\Estimation;
use App\Model\PaymentTerm;
use App\Model\CustomerInfo;
use App\Model\CustomerType;
use App\Model\SalesmanInfo;
use App\Imports\UsersImport;
use App\Model\CountryMaster;
use Illuminate\Http\Request;
use App\Model\ImportTempFile;
use App\Model\WorkFlowObject;
use Ixudra\Curl\Facades\Curl;
use App\Model\CustomerComment;
use App\Model\Storagelocation;
use App\Model\CustomerCategory;
use App\Imports\CustomersImport;
use App\Model\SalesOrganisation;
use Illuminate\Support\Facades\DB;
use App\Model\CustomerMerchandiser;
use App\Model\CustomFieldValueSave;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Model\CustomerWarehouseMapping;
use App\Model\OrganisationRoleAttached;
use Illuminate\Support\Facades\Validator;
use Meneses\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
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

        $getRoleData = array();
        $oruser = OrganisationRoleAttached::where('user_id', request()->user()->id)->first();
        if (is_object($oruser)) {
            if ($request->all != true) {
                $getRoleData = getSalesman(true);
            }
        }

        $users_query = CustomerInfo::select('id', 'uuid', 'organisation_id', 'user_id', 'region_id', 'route_id', 'customer_group_id', 'sales_organisation_id', 'channel_id', 'customer_code', 'erp_code', 'customer_type_id', 'payment_term_id', 'customer_category_id', 'customer_address_1', 'customer_address_2', 'customer_city', 'customer_state', 'customer_zipcode', 'customer_phone', 'customer_address_1_lat', 'customer_address_1_lang', 'customer_address_2_lat', 'customer_address_2_lang', 'balance', 'credit_limit', 'credit_days', 'ship_to_party', 'sold_to_party', 'payer', 'bill_to_payer', 'current_stage', 'current_stage_comment', 'status', 'trn_no')
            ->with(
                'user:id,organisation_id,usertype,firstname,lastname,email,mobile,role_id,country_id,status,parent_id',
                'customerMerchandiser',
                'customerMerchandiser.salesman:id,firstname,lastname',
                'customerMerchandiser.salesman.salesmanInfo:id,user_id,salesman_code',
                'route:id,route_code,route_name,status',
                'channel:id,name,status',
                'region:id,region_name,region_status',
                'customerGroup:id,group_code,group_name',
                'customerCategory:id,customer_category_code,customer_category_name',
                'customerType:id,customer_type_name',
                'salesOrganisation:id,name',
                'shipToParty:id,user_id',
                'shipToParty.user:id,firstname,lastname',
                'soldToParty:id,user_id',
                'soldToParty.user:id,firstname,lastname',
                'payer:id,user_id',
                'payer.user:id,firstname,lastname',
                'billToPayer:id,user_id',
                'billToPayer.user:id,firstname,lastname',
                'customFieldValueSave',
                'customFieldValueSave.customField',
                'customerlob',
                'customerlob.lob',
                'customerlob.region',
                'customerlob.country',
                'customerlob.route',
                'customerlob.salesOrganisation',
                'customerlob.channel',
                'customerlob.customerGroup',
                'customerlob.customerCategory',
                'customerlob.customerType',
                'customerlob.shipToParty',
                'customerlob.soldToParty',
                'customerlob.payer',
                'customerlob.billToPayer',
                'merchandiser:id,firstname,lastname'
            );

        if ($request->customer_code) {
            $users_query->where('customer_code', 'like', '%' . $request->customer_code . '%');
        }

        if ($request->customer_category) {
            $customer_category = $request->customer_category;
            $users_query->whereHas('customerCategory', function ($q) use ($customer_category) {
                $q->where('customer_category_name', 'like', '%' . $customer_category . '%');
            });
        }

        if ($request->channel) {
            $channel = $request->channel;
            $users_query->whereHas('channel', function ($q) use ($channel) {
                $q->where('name', 'like', '%' . $channel . '%');
            });
        }

        if ($request->customer_phone) {
            $users_query->where('customer_phone', 'like', '%' . $request->customer_phone . '%');
        }

        if ($request->email) {
            $email = $request->email;
            $users_query->whereHas('user', function ($q) use ($email) {
                $q->where('email', $email);
            });
        }

        if (count($getRoleData)) {
            $users_query->whereIn('user_id', $getRoleData);
        }

        if ($request->name) {
            $name = $request->name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $users_query->whereHas('user', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $users_query->whereHas('user', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        $all_user = $users_query->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $users = $all_user->items();

        $pagination = array();
        $pagination['total_pages'] = $all_user->lastPage();
        $pagination['current_page'] = (int) $all_user->perPage();
        $pagination['total_records'] = $all_user->total();

        // $users = $users_query->get();

        // approval
        $results = GetWorkFlowRuleObject('Customer');
        $approve_need_customer = array();
        $approve_need_customer_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_customer[] = $raw['object']->raw_id;
                $approve_need_customer_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $users_array = array();
        if (is_object(collect($users))) {
            foreach ($users as $key => $user1) {
                if (in_array($users[$key]->id, $approve_need_customer)) {
                    $users[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_customer_object_id[$users[$key]->id])) {
                        $users[$key]->objectid = $approve_need_customer_object_id[$users[$key]->id];
                    } else {
                        $users[$key]->objectid = '';
                    }
                } else {
                    $users[$key]->need_to_approve = 'no';
                    $users[$key]->objectid = '';
                }

                if (count($users[$key]->customerlob)) {
                    foreach ($users[$key]->customerlob as $k => $cl) {
                        $cwmp = CustomerWarehouseMapping::with('storageocation:id,name,code')
                            ->where('customer_id', $users[$key]->user_id)
                            ->where('lob_id', $cl->lob_id)
                            ->get();
                        $users[$key]->customerlob[$k]->customer_warehouse_mapping = $cwmp;
                    }
                }

                if ($users[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || $users[$key]->user->parent_id == auth()->id() || in_array($users[$key]->id, $approve_need_customer)) {
                    $users_array[] = $users[$key];
                }
            }
        }

        // $data_array = array();
        // $page = (isset($request->page)) ? $request->page : '';
        // $limit = (isset($request->page_size)) ? $request->page_size : '';
        // $pagination = array();
        // if ($page != '' && $limit != '') {
        //     $offset = ($page - 1) * $limit;
        //     for ($i = 0; $i < $limit; $i++) {
        //         if (isset($users_array[$offset])) {
        //             $data_array[] = $users_array[$offset];
        //         }
        //         $offset++;
        //     }

        //     $pagination['total_pages'] = ceil(count($users_array) / $limit);
        //     $pagination['current_page'] = (int)$page;
        //     $pagination['total_records'] = count($users_array);
        // } else {
        //     $data_array = $users_array;
        // }
        return prepareResult(true, $users_array, [], "Customer listing", $this->success, $pagination);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Customer", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Customer',$request);
            }

            $user = new User;
            $user->usertype = 2;
            $user->parent_id = $request->parent_id;
            $user->firstname = $request->firstname;
            $user->lastname = $request->lastname;
            $user->email = $request->email;
            $user->password = \Hash::make('abcdefg');
            $user->mobile = $request->mobile;
            $user->country_id = $request->country_id;
            $user->api_token = \Str::random(35);
            $user->status = $status;
            $user->save();

            $customer_infos = new CustomerInfo;
            $customer_infos->user_id = $user->id;
            $customer_infos->region_id = $request->region_id;
            $customer_infos->customer_group_id = $request->customer_group_id;
            $customer_infos->sales_organisation_id = $request->sales_organisation_id;
            $customer_infos->route_id = $request->route_id;
            $customer_infos->channel_id = $request->channel_id;
            $customer_infos->customer_category_id = $request->customer_category_id;
            $customer_infos->customer_code = nextComingNumber('App\Model\CustomerInfo', 'customer', 'customer_code', $request->customer_code);
            // $customer_infos->customer_code = $request->customer_code;
            $customer_infos->erp_code = $request->erp_code;
            $customer_infos->customer_type_id = $request->customer_type_id;
            $customer_infos->customer_address_1 = $request->customer_address_1;
            $customer_infos->customer_address_2 = $request->customer_address_2;
            $customer_infos->customer_city = $request->customer_city;
            $customer_infos->customer_state = $request->customer_state;
            $customer_infos->customer_zipcode = $request->customer_zipcode;
            $customer_infos->customer_phone = $request->customer_phone;

            $customer_infos->customer_address_1_lat = $request->customer_address_1_lat;
            $customer_infos->customer_address_1_lang = $request->customer_address_1_lang;
            $customer_infos->customer_address_2_lat = $request->customer_address_2_lat;
            $customer_infos->customer_address_2_lang = $request->customer_address_2_lang;
            if ($request->customer_profile) {
                $customer_infos->profile_image = saveImage($request->firstname . ' ' . $request->lastname, $request->customer_profile, 'customer-profile');
            }

            $customer_infos->balance = $request->balance;
            $customer_infos->credit_limit = $request->credit_limit;
            $customer_infos->credit_days = $request->credit_days;
            $customer_infos->payment_term_id = $request->payment_term_id;
            $customer_infos->current_stage = $current_stage;
            $customer_infos->current_stage_comment = $request->current_stage_comment;

            $customer_infos->status = $status;
            $customer_infos->trn_no = (isset($request->trn_no)) ? $request->trn_no : null;
            $customer_infos->save();

            $this->saveMerchandiser($request->merchandiser_id, $user->id);

            if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Customer', $request, $customer_infos->id);
            }

            //action history
            create_action_history("Customer", $customer_infos->id, auth()->user()->id, "create", "Customer created by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            $updateInfo = CustomerInfo::find($customer_infos->id);
            $getInfoSTP = CustomerInfo::select('id')->where('customer_code', $request->ship_to_party)->first();
            if ($getInfoSTP) {
                $updateInfo->ship_to_party = $getInfoSTP->id;
            }

            $getInfoSTParty = CustomerInfo::select('id')->where('customer_code', $request->sold_to_party)->first();
            if ($getInfoSTParty) {
                $updateInfo->sold_to_party = $getInfoSTParty->id;
            }

            $getInfoP = CustomerInfo::select('id')->where('customer_code', $request->payer)->first();
            if ($getInfoP) {
                $updateInfo->payer = $getInfoP->id;
            }

            $getInfoBTP = CustomerInfo::select('id')->where('customer_code', $request->bill_to_payer)->first();

            if ($getInfoBTP) {
                $updateInfo->bill_to_payer = $getInfoBTP->id;
            }
            $updateInfo->save();

            if (!$getInfoSTP || !$getInfoSTParty || !$getInfoP || !$getInfoBTP) {
                \DB::rollback();
                return prepareResult(false, [], ['ship_to_party' => $getInfoSTP, 'sold_to_party' => $getInfoSTParty, 'payer' => $getInfoP, 'bill_to_payer' => $getInfoBTP], "Please enter proper value of ship to party, sold to party, payer & bill to payer information.", $this->internal_server_error);
            }

            if (is_array($request->modules) && sizeof($request->modules) >= 1) {
                foreach ($request->modules as $module) {
                    savecustomField($customer_infos->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
                }
            }

            \DB::commit();
            updateNextComingNumber('App\Model\CustomerInfo', 'customer');

            $customer_infos->getSaveData();
            return prepareResult(true, $customer_infos, [], "Customer added successfully", $this->success);
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
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $users = CustomerInfo::where('uuid', $uuid)
            ->with(
                'user:id,organisation_id,usertype,firstname,lastname,email,mobile,role_id,country_id,status,parent_id',
                'customerMerchandiser',
                'customerMerchandiser.salesman:id,firstname,lastname',
                'customerMerchandiser.salesman.salesmanInfo:id,user_id,salesman_code',
                'route:id,route_code,route_name,status',
                'channel:id,name,status',
                'region:id,region_name,region_status',
                'customerGroup:id,group_code,group_name',
                'customerCategory:id,customer_category_code,customer_category_name',
                'customerType:id,customer_type_name',
                'salesOrganisation:id,name',
                'shipToParty:id,user_id',
                'shipToParty.user:id,firstname,lastname',
                'soldToParty:id,user_id',
                'soldToParty.user:id,firstname,lastname',
                'payer:id,user_id',
                'payer.user:id,firstname,lastname',
                'billToPayer:id,user_id',
                'billToPayer.user:id,firstname,lastname',
                'customFieldValueSave',
                'customFieldValueSave.customField',
                'customerlob',
                'customerlob.lob',
                'customerlob.region',
                'customerlob.country',
                'customerlob.route',
                'customerlob.salesOrganisation',
                'customerlob.channel',
                'customerlob.customerGroup',
                'customerlob.customerCategory',
                'customerlob.customerType',
                'customerlob.shipToParty',
                'customerlob.soldToParty',
                'customerlob.payer',
                'customerlob.billToPayer',
                'merchandiser:id,firstname,lastname'
            )->first();

        if (!is_object($users)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $users, [], "Customer Edit", $this->success);
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
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "edit");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Customer", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Customer',$request);
            }
            $customer_infos = CustomerInfo::where('uuid', $uuid)->first();

            CustomerMerchandiser::where('customer_id', $customer_infos->user_id)->delete();

            $customer_infos->region_id = $request->region_id;
            // $customer_infos->merchandiser_id = $request->merchandiser_id;
            $customer_infos->customer_group_id = $request->customer_group_id;
            $customer_infos->sales_organisation_id = $request->sales_organisation_id;
            $customer_infos->route_id = $request->route_id;
            $customer_infos->channel_id = $request->channel_id;
            $customer_infos->erp_code = $request->erp_code;
            $customer_infos->customer_category_id = $request->customer_category_id;
            $customer_infos->customer_type_id = $request->customer_type_id;
            $customer_infos->customer_address_1 = $request->customer_address_1;
            $customer_infos->customer_address_2 = $request->customer_address_2;
            $customer_infos->customer_city = $request->customer_city;
            $customer_infos->customer_state = $request->customer_state;
            $customer_infos->customer_zipcode = $request->customer_zipcode;
            $customer_infos->customer_phone = $request->customer_phone;
            $customer_infos->customer_address_1_lat = $request->customer_address_1_lat;
            $customer_infos->customer_address_1_lang = $request->customer_address_1_lang;
            $customer_infos->customer_address_2_lat = $request->customer_address_2_lat;
            $customer_infos->customer_address_2_lang = $request->customer_address_2_lang;
            if ($request->customer_profile) {
                $customer_infos->profile_image = saveImage($request->firstname . ' ' . $request->lastname, $request->customer_profile, 'customer-profile');
            }
            $customer_infos->balance = $request->balance;
            $customer_infos->credit_limit = $request->credit_limit;
            $customer_infos->credit_days = $request->credit_days;
            $customer_infos->payment_term_id = $request->payment_term_id;
            $customer_infos->current_stage_comment = $request->current_stage_comment;
            $customer_infos->current_stage = $current_stage;
            $customer_infos->status = $status;
            $customer_infos->trn_no = (isset($request->trn_no)) ? $request->trn_no : null;
            $customer_infos->save();

            $user = $customer_infos->user;
            $user->parent_id = $request->parent_id;
            $user->firstname = $request->firstname;
            $user->lastname = $request->lastname;
            // $user->email = $request->email;
            // $user->password = $request->password;
            $user->mobile = $request->mobile;
            $user->country_id = $request->country_id;
            $user->status = $status;
            $user->save();

            $this->saveMerchandiser($request->merchandiser_id, $user->id);

            if ($isActivate = checkWorkFlowRule('Customer', 'edit')) {
                $this->createWorkFlowObject($isActivate, 'Customer', $request, $customer_infos->id);
            }

            //action history
            create_action_history("Customer", $customer_infos->id, auth()->user()->id, "update", "Customer updated by " . auth()->user()->firstname . " " . auth()->user()->lastname);
            //action history

            $updateInfo = CustomerInfo::find($customer_infos->id);
            $getInfoSTP = CustomerInfo::select('id')->where('customer_code', $request->ship_to_party)->first();
            if ($getInfoSTP) {
                $updateInfo->ship_to_party = $getInfoSTP->id;
            }

            $getInfoSTParty = CustomerInfo::select('id')->where('customer_code', $request->sold_to_party)->first();
            if ($getInfoSTParty) {
                $updateInfo->sold_to_party = $getInfoSTParty->id;
            }

            $getInfoP = CustomerInfo::select('id')->where('customer_code', $request->payer)->first();
            if ($getInfoP) {
                $updateInfo->payer = $getInfoP->id;
            }

            $getInfoBTP = CustomerInfo::select('id')->where('customer_code', $request->bill_to_payer)->first();

            if ($getInfoBTP) {
                $updateInfo->bill_to_payer = $getInfoBTP->id;
            }
            $updateInfo->save();

            if (!$getInfoSTP || !$getInfoSTParty || !$getInfoP || !$getInfoBTP) {
                \DB::rollback();
                return prepareResult(false, [], ['ship_to_party' => $getInfoSTP, 'sold_to_party' => $getInfoSTParty, 'payer' => $getInfoP, 'bill_to_payer' => $getInfoBTP], "Please enter proper value of ship to party, sold to party, payer & bill to payer information.", $this->internal_server_error);
            }

            if (is_array($request->modules) && sizeof($request->modules) >= 1) {
                CustomFieldValueSave::where('record_id', $customer_infos->id)->delete();
                foreach ($request->modules as $module) {
                    savecustomField($customer_infos->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
                }
            }

            \DB::commit();

            $customer_infos->getSaveData();

            return prepareResult(true, $customer_infos, [], "Customer updated successfully", $this->success);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
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
            return prepareResult(false, [], [], "Error while validating depots", $this->unauthorized);
        }

        $customer_infos = CustomerInfo::where('uuid', $uuid)->first();

        $user = $customer_infos->user;

        if (is_object($user)) {
            $user->delete();
            $customer_infos->delete();
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
                'region_id' => 'required|integer|exists:regions,id',
                'channel_id' => 'required|integer|exists:channels,id',
                'sales_organisation_id' => 'required|integer|exists:sales_organisations,id',
                'ship_to_party' => 'required',
                'sold_to_party' => 'required',
                'payer' => 'required',
                'bill_to_payer' => 'required',
                'firstname' => 'required',
                // 'lastname' => 'required',
                'email' => 'required|email|unique:users,email',
                'status' => 'required',
                'customer_address_1' => 'required',
                // 'password' => 'required',
                // 'country_id' => 'required|integer|exists:countries,id',
                // 'customer_group_id' => 'required|integer|exists:customer_groups,id',
                // 'mobile' => 'required',
                // 'role_id' => 'required',
                // 'customer_type_id' => 'required',
                // 'customer_city' => 'required',
                // 'customer_state' => 'required',
                // 'customer_zipcode' => 'required'
            ]);
        }

        if ($type == "edit") {
            $validator = \Validator::make($input, [
                'region_id' => 'required|integer|exists:regions,id',
                'channel_id' => 'required|integer|exists:channels,id',
                'sales_organisation_id' => 'required|integer|exists:sales_organisations,id',
                'firstname' => 'required',
                // 'lastname' => 'required',
                'customer_code' => 'required',
                'status' => 'required',
                'customer_address_1' => 'required',
                // 'password' => 'required',
                // 'customer_type_id' => 'required',
                // 'country_id' => 'required|integer|exists:countries,id',
                // 'customer_group_id' => 'required|integer|exists:customer_groups,id',
                // 'mobile' => 'required',
                // 'role_id' => 'required',
                // 'customer_city' => 'required',
                // 'customer_state' => 'required',
                // 'customer_zipcode' => 'required'
            ]);
            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'customer_ids' => 'required',
            ]);
            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function customerComment(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "comment");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Customer CommentS", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $user = new CustomerComment;
            $user->customer_id = $request->customer_id;
            $user->comment = $request->comment;
            $user->comment_date = date("Y-m-d");
            $user->status = 1;
            $user->save();

            \DB::commit();
            return prepareResult(true, $user, [], "Comment added successfully", $this->success);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function customerDetails($customer_id)
    {
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $search = (isset($_REQUEST['search'])) ? $_REQUEST['search'] : '';

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$customer_id) {
            return prepareResult(false, [], [], "Error while validating customer id.", $this->unauthorized);
        }

        if ($search == '') {
            return prepareResult(false, [], [], "Error while validating searching module", $this->unauthorized);
        }

        //Customer Invoices
        if ($search == 'invoice') {
            $data_array = Invoice::select(
                'invoices.id',
                'invoices.uuid',
                'invoices.customer_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.grand_total',
                'invoices.status',
                'collection_details.pending_amount',
                \DB::raw("CASE
                        WHEN collection_details.pending_amount=0 or collection_details.pending_amount=0.00 THEN 'Paid'
                        WHEN invoices.grand_total = collection_details.pending_amount THEN 'Approved'
                        WHEN invoices.grand_total > collection_details.pending_amount THEN 'Overdue'
                        ELSE 'Draft'
                    END As status")
            )
                ->leftJoin('collection_details', function ($join) {
                    $join->on('collection_details.invoice_id', '=', 'invoices.id');
                    $join->on(DB::raw('collection_details.id'), DB::raw('(SELECT MAX(id) from collection_details where invoice_id=invoices.id)'), DB::raw(''));
                })
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Invoice listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Customer Credit Notes
        if ($search == 'creditnote') {
            $data_array = CreditNote::select(
                'id',
                'uuid',
                'customer_id',
                'credit_note_number',
                'credit_note_date',
                'grand_total',
                \DB::raw("CASE WHEN (status = 1) THEN 'Active' ELSE 'InActive' END AS status")
            )
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Credit Note listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Customer Expenses
        if ($search == 'expense') {
            $data_array = Expense::select(
                'id',
                'uuid',
                'customer_id',
                'reference',
                'amount',
                'expense_date',
                \DB::raw("CASE WHEN (status = 1) THEN 'Active' ELSE 'InActive' END AS status")
            )
                ->with('expenseCategory:id,name')
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Expense listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Customer Delivery Details
        if ($search == 'delivery_detail') {
            $data_array = Delivery::select(
                'id',
                'uuid',
                'customer_id',
                'delivery_number',
                'delivery_date',
                'grand_total',
                \DB::raw("CASE WHEN (status = 1) THEN 'Active' ELSE 'InActive' END AS status")
            )
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Delivery Detail listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Customer Estimation
        if ($search == 'estimation') {
            $data_array = Estimation::select(
                'id',
                'uuid',
                'customer_id',
                'reference',
                'estimate_code',
                'estimate_date',
                'total',
                \DB::raw("CASE WHEN (status = 1) THEN 'Active' ELSE 'InActive' END AS status")
            )
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Estimation listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Customer Collection
        if ($search == 'collection') {
            $data_array = Collection::select(
                'id',
                'uuid',
                'customer_id',
                'collection_number',
                'invoice_amount',
                \DB::raw("CASE
                        WHEN payemnt_type=1 THEN 'Cash'
                        WHEN payemnt_type=2 THEN 'Cheque'
                        WHEN payemnt_type=3 THEN 'NEFT'
                        ELSE ''
                    END As payment_mode")
            )
                ->where('customer_id', $customer_id);

            if ($page != '' && $limit != '') {
                $data_array = $data_array->orderBy('id', 'desc')->paginate($limit)->toArray();
                $dataArray['total_pages'] = ceil($data_array['total'] / $limit);
                $dataArray['current_page'] = (int) $data_array['current_page'];
                $dataArray['total_records'] = (int) $data_array['total'];
                $dataArray['data'] = $data_array['data'];
                return prepareResult(true, $dataArray, [], "Customer Collection listing", $this->success);
            } else {
                $data_array = $data_array->orderBy('id', 'desc')->get()->toArray();
                $dataArray = $data_array;
            }
        }

        //Prepare Results
        return prepareResult(true, $dataArray, [], "Customer Detail listing", $this->success);
    }

    public function deleteCustomerComment($comment_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$comment_id) {
            return prepareResult(false, [], [], "Error while validating customer comment", $this->unauthorized);
        }

        $customer_comment = CustomerComment::where('id', $comment_id)->first();

        if (is_object($customer_comment)) {
            $customer_comment->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "No Record Found", $this->unauthorized);
    }

    public function listCustomerComments($customer_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$customer_id) {
            return prepareResult(false, [], [], "Error while validating customer id", $this->unauthorized);
        }

        $customer_comment = CustomerComment::where('customer_id', $customer_id)->orderBy('created_at', 'DESC')->get();

        $dataArray = $customer_comment;

        //Prepare Results
        return prepareResult(true, $dataArray, [], "Customer comments listing", $this->success);
    }

    public function customerTypes()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $customer_type = CustomerType::get();

        return prepareResult(true, $customer_type, [], "Customer type listing", $this->success);
    }

    public function createWorkFlowObject1($work_flow_rule_id, $module_name, $row, $raw_id)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id = $work_flow_rule_id;
        $createObj->module_name = $module_name;
        $createObj->raw_id = $raw_id;
        $createObj->request_object = $row;
        $createObj->save();
    }

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $raw_id)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id = $work_flow_rule_id;
        $createObj->module_name = $module_name;
        $createObj->raw_id = $raw_id;
        $createObj->request_object = $request->all();
        $createObj->save();
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'customer_file' => 'required|mimes:xlsx,xls,csv,txt',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer import", $this->unauthorized);
        }
        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('customer_file')->store('import');
            $filename = storage_path("app/" . $file);
            $fp = fopen($filename, "r");
            $content = fread($fp, filesize($filename));
            $lines = explode("\n", $content);
            $heading_array_line = isset($lines[0]) ? $lines[0] : '';
            $heading_array = explode(",", trim($heading_array_line));
            fclose($fp);

            if (!$heading_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }
            if (!$map_key_value_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }
            /*$file_data = fopen(storage_path("app/".$file), "r");
            $row_counter = 1;
            while(!feof($file_data)) {
            if($row_counter == 1){
            echo fgets($file_data). "<br>";
            }
            $row_counter++;
            }
            fclose($file_data);
             */
            //exit;

            $import = new UsersImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            //print_r($import);
            //exit;
            $succussrecords = 0;
            $successfileids = 0;
            if ($import->successAllRecords()) {
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
        return prepareResult(true, $result, $errors, "Customer successfully imported", $this->success);
    }

    public function finalimport(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);

            $skipduplicate = $request->skipduplicate;

            if ($finaldata) :
                foreach ($finaldata as $row) :
                    $status = 0;
                    $current_stage = 'Approved';

                    $country = CountryMaster::where('name', 'LIKE', '%' . $row[5] . '%')->first();
                    $region = Region::where('region_name', $row[7])->first();
                    // $CustomerGroup = CustomerGroup::where('group_name', $row[8])->first();
                    $SalesOrganisation = SalesOrganisation::where('name', $row[8])->first();
                    $Route = Route::where('route_name', $row[9])->first();
                    $Channel = Channel::where('name', $row[10])->first();
                    $CustomerCategory = CustomerCategory::where('customer_category_name', $row[11])->first();
                    $CustomerType = CustomerType::where('customer_type_name', $row[13])->first();
                    $PaymentTerm = PaymentTerm::where('name', $row[23])->first();
                    // $Merchandiser = User::where('firstname', $row[24])->first();
                    $Merchandiser = 0;
                    if (isset($row[24]) && $row[24]) {
                        $Merchandiser = SalesmanInfo::where('salesman_code', $row[24])->first();
                    }

                    $current_organisation_id = request()->user()->organisation_id;

                    $customer_infos = CustomerInfo::where('customer_code', $row[12])->first();
                    if ($skipduplicate) {
                        if (is_object($customer_infos)) {
                            continue;
                        }

                        $status = $row[6];
                        $current_stage = 'Approved';
                        $current_organisation_id = request()->user()->organisation_id;
                        if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                            $status = 0;
                            $current_stage = 'Pending';
                            //$this->createWorkFlowObject($isActivate, 'Customer',$request);
                        }

                        $user = new User;
                        $user->usertype = 2;
                        $user->parent_id = auth()->user()->id;
                        $user->firstname = $row[0];
                        $user->lastname = $row[1];
                        $user->email = $row[2];
                        $user->password = Hash::make($row[3]);
                        $user->email_verified_at = date('Y-m-d H:i:s');
                        $user->mobile = $row[4];
                        $user->country_id = (is_object($country)) ? $country->id : 0;
                        $user->api_token = \Str::random(35);
                        $user->status = $status;
                        $user->save();

                        $customer_infos = new CustomerInfo;
                        $customer_infos->user_id = $user->id;
                        $customer_infos->region_id = (is_object($region)) ? $region->id : 0;
                        // $customer_infos->customer_group_id = (is_object($CustomerGroup)) ? $CustomerGroup->id : 0;
                        $customer_infos->sales_organisation_id = (is_object($SalesOrganisation)) ? $SalesOrganisation->id : 0;
                        $customer_infos->route_id = (is_object($Route)) ? $Route->id : null;
                        $customer_infos->channel_id = (is_object($Channel)) ? $Channel->id : 0;
                        $customer_infos->customer_category_id = (is_object($CustomerCategory)) ? $CustomerCategory->id : 0;
                        $customer_infos->customer_code = $row[12];
                        $customer_infos->customer_type_id = (is_object($CustomerType)) ? $CustomerType->id : 0;
                        $customer_infos->customer_address_1 = $row[14];
                        $customer_infos->customer_address_2 = $row[15];
                        $customer_infos->customer_city = $row[16];
                        $customer_infos->customer_state = $row[17];
                        $customer_infos->customer_zipcode = $row[18];
                        $customer_infos->customer_phone = $row[19];

                        if (is_object($CustomerType) && $CustomerType->id != 2) {
                            $customer_infos->balance = $row[20];
                            $customer_infos->credit_limit = $row[21];
                            $customer_infos->credit_days = $row[22];
                            if (is_object($PaymentTerm)) {
                                $customer_infos->payment_term_id = $PaymentTerm->id;
                            }
                        }
                        // $customer_infos->merchandiser_id = (is_object($Merchandiser)) ? $Merchandiser->user_id : Null;
                        $customer_infos->customer_address_1_lat = $row[29];
                        $customer_infos->customer_address_1_lang = $row[30];
                        $customer_infos->erp_code = $row[12];
                        $customer_infos->current_stage = $current_stage;
                        $customer_infos->current_stage_comment = "";
                        $customer_infos->status = $status;
                        $customer_infos->save();

                        if (is_object($Merchandiser)) {
                            $customer_merchandiser = CustomerMerchandiser::where('customer_id', $customer_infos->user_id)
                                ->where('merchandiser_id', $Merchandiser->user_id)
                                ->first();

                            if (!is_object($customer_merchandiser)) {
                                $customer_merchandiser = new CustomerMerchandiser;
                                $customer_merchandiser->customer_id = $customer_infos->user_id;
                                $customer_merchandiser->merchandiser_id = $Merchandiser->user_id;
                                $customer_merchandiser->save();
                            }
                        }

                        $updateInfo = CustomerInfo::find($customer_infos->id);
                        $getInfoSTP = CustomerInfo::select('id')->where('customer_code', $row[25])->first();
                        if ($getInfoSTP) {
                            $updateInfo->ship_to_party = $getInfoSTP->id;
                        }

                        $getInfoSTParty = CustomerInfo::select('id')->where('customer_code', $row[26])->first();
                        if ($getInfoSTParty) {
                            $updateInfo->sold_to_party = $getInfoSTParty->id;
                        }

                        $getInfoP = CustomerInfo::select('id')->where('customer_code', $row[27])->first();
                        if ($getInfoP) {
                            $updateInfo->payer = $getInfoP->id;
                        }

                        $getInfoBTP = CustomerInfo::select('id')->where('customer_code', $row[28])->first();

                        if ($getInfoBTP) {
                            $updateInfo->bill_to_payer = $getInfoBTP->id;
                        }
                        $updateInfo->save();
                    } else {
                        if (is_object($customer_infos)) {
                            $user = User::find($customer_infos->user_id);
                            $user->usertype = 2;
                            $user->parent_id = auth()->user()->id;
                            $user->firstname = $row[0];
                            $user->lastname = $row[1];
                            $user->email = $row[2];
                            $user->email_verified_at = date('Y-m-d H:i:s');
                            $user->password = Hash::make($row[3]);
                            $user->mobile = $row[4];
                            $user->country_id = (is_object($country)) ? $country->id : 0;
                            $user->api_token = \Str::random(35);
                            $user->status = $row[6];
                            $user->save();

                            // $customer_infos = CustomerInfo::where('user_id', $user->id)->first();
                            $customer_infos->user_id = $user->id;
                            $customer_infos->region_id = (is_object($region)) ? $region->id : 0;
                            // $customer_infos->customer_group_id = (is_object($CustomerGroup)) ? $CustomerGroup->id : 0;
                            $customer_infos->sales_organisation_id = (is_object($SalesOrganisation)) ? $SalesOrganisation->id : 0;
                            $customer_infos->route_id = (is_object($Route)) ? $Route->id : 0;
                            $customer_infos->channel_id = (is_object($Channel)) ? $Channel->id : 0;
                            $customer_infos->customer_category_id = (is_object($CustomerCategory)) ? $CustomerCategory->id : 0;
                            $customer_infos->customer_code = $row[12];
                            $customer_infos->customer_type_id = (is_object($CustomerType)) ? $CustomerType->id : 0;
                            $customer_infos->customer_address_1 = $row[14];
                            $customer_infos->customer_address_2 = $row[15];
                            $customer_infos->customer_city = $row[16];
                            $customer_infos->customer_state = $row[17];
                            $customer_infos->customer_zipcode = $row[18];
                            $customer_infos->customer_phone = $row[19];
                            if (is_object($CustomerType) && $CustomerType->id != 2) {
                                $customer_infos->balance = $row[20];
                                $customer_infos->credit_limit = $row[21];
                                $customer_infos->credit_days = $row[22];
                                if (is_object($PaymentTerm)) {
                                    $customer_infos->payment_term_id = $PaymentTerm->id;
                                }
                            }
                            // $customer_infos->merchandiser_id = (is_object($Merchandiser)) ? $Merchandiser->user_id : Null;
                            $customer_infos->customer_address_1_lat = $row[29];
                            $customer_infos->customer_address_1_lang = $row[30];
                            $customer_infos->erp_code = $row[12];

                            $customer_infos->current_stage = $current_stage;
                            $customer_infos->current_stage_comment = "";

                            $customer_infos->status = $status;

                            $customer_infos->save();

                            if (is_object($Merchandiser)) {

                                $customer_merchandiser = CustomerMerchandiser::where('customer_id', $customer_infos->user_id)
                                    ->where('merchandiser_id', $Merchandiser->user_id)
                                    ->first();

                                if (!is_object($customer_merchandiser)) {
                                    $customer_merchandiser = new CustomerMerchandiser;
                                    $customer_merchandiser->customer_id = $customer_infos->user_id;
                                    $customer_merchandiser->merchandiser_id = $Merchandiser->user_id;
                                    $customer_merchandiser->save();
                                }
                            }

                            $updateInfo = CustomerInfo::find($customer_infos->id);
                            $getInfoSTP = CustomerInfo::select('id')->where('customer_code', $row[25])->first();
                            if ($getInfoSTP) {
                                $updateInfo->ship_to_party = $getInfoSTP->id;
                            }

                            $getInfoSTParty = CustomerInfo::select('id')->where('customer_code', $row[26])->first();
                            if ($getInfoSTParty) {
                                $updateInfo->sold_to_party = $getInfoSTParty->id;
                            }

                            $getInfoP = CustomerInfo::select('id')->where('customer_code', $row[27])->first();
                            if ($getInfoP) {
                                $updateInfo->payer = $getInfoP->id;
                            }

                            $getInfoBTP = CustomerInfo::select('id')->where('customer_code', $row[28])->first();

                            if ($getInfoBTP) {
                                $updateInfo->bill_to_payer = $getInfoBTP->id;
                            }
                            $updateInfo->save();
                        } else {
                            $status = $row[6];
                            $current_stage = 'Approved';
                            $current_organisation_id = request()->user()->organisation_id;
                            if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                                $status = 0;
                                $current_stage = 'Pending';
                                //$this->createWorkFlowObject($isActivate, 'Customer',$request);
                            }

                            $user = new User;
                            $user->usertype = 2;
                            $user->parent_id = auth()->user()->id;
                            $user->firstname = $row[0];
                            $user->lastname = $row[1];
                            $user->email = $row[2];
                            $user->password = Hash::make($row[3]);
                            $user->email_verified_at = date('Y-m-d H:i:s');
                            $user->mobile = $row[4];
                            $user->country_id = (is_object($country)) ? $country->id : 0;
                            $user->api_token = \Str::random(35);
                            $user->status = $status;
                            $user->save();

                            $customer_infos = new CustomerInfo;
                            $customer_infos->user_id = $user->id;
                            $customer_infos->region_id = (is_object($region)) ? $region->id : 0;
                            // $customer_infos->customer_group_id = (is_object($CustomerGroup)) ? $CustomerGroup->id : 0;
                            $customer_infos->sales_organisation_id = (is_object($SalesOrganisation)) ? $SalesOrganisation->id : 0;
                            $customer_infos->route_id = (is_object($Route)) ? $Route->id : null;
                            $customer_infos->channel_id = (is_object($Channel)) ? $Channel->id : 0;
                            $customer_infos->customer_category_id = (is_object($CustomerCategory)) ? $CustomerCategory->id : 0;
                            $customer_infos->customer_code = $row[12];
                            $customer_infos->customer_type_id = (is_object($CustomerType)) ? $CustomerType->id : 0;
                            $customer_infos->customer_address_1 = $row[14];
                            $customer_infos->customer_address_2 = $row[15];
                            $customer_infos->customer_city = $row[16];
                            $customer_infos->customer_state = $row[17];
                            $customer_infos->customer_zipcode = $row[18];
                            $customer_infos->customer_phone = $row[19];

                            if (is_object($CustomerType) && $CustomerType->id != 2) {
                                $customer_infos->balance = $row[20];
                                $customer_infos->credit_limit = $row[21];
                                $customer_infos->credit_days = $row[22];
                                if (is_object($PaymentTerm)) {
                                    $customer_infos->payment_term_id = $PaymentTerm->id;
                                }
                            }
                            // $customer_infos->merchandiser_id = (is_object($Merchandiser)) ? $Merchandiser->user_id : Null;
                            $customer_infos->customer_address_1_lat = $row[29];
                            $customer_infos->customer_address_1_lang = $row[30];
                            $customer_infos->erp_code = $row[12];

                            $customer_infos->current_stage = $current_stage;
                            $customer_infos->current_stage_comment = "";

                            $customer_infos->status = $status;

                            $customer_infos->save();

                            $updateInfo = CustomerInfo::find($customer_infos->id);
                            $getInfoSTP = CustomerInfo::select('id')->where('customer_code', $row[25])->first();
                            if ($getInfoSTP) {
                                $updateInfo->ship_to_party = $getInfoSTP->id;
                            }

                            $getInfoSTParty = CustomerInfo::select('id')->where('customer_code', $row[26])->first();
                            if ($getInfoSTParty) {
                                $updateInfo->sold_to_party = $getInfoSTParty->id;
                            }

                            $getInfoP = CustomerInfo::select('id')->where('customer_code', $row[27])->first();
                            if ($getInfoP) {
                                $updateInfo->payer = $getInfoP->id;
                            }

                            $getInfoBTP = CustomerInfo::select('id')->where('customer_code', $row[28])->first();

                            if ($getInfoBTP) {
                                $updateInfo->bill_to_payer = $getInfoBTP->id;
                            }
                            $updateInfo->save();

                            if ($isActivate = checkWorkFlowRule('Customer', 'create', $current_organisation_id)) {
                                $this->createWorkFlowObject1($isActivate, 'Customer', $row, $customer_infos->id);
                            }
                        }
                    }

                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Customer successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function customerBalances($customer_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$customer_id) {
            return prepareResult(false, [], [], "Error while validating customer id.", $this->unprocessableEntity);
        }

        //Customer Invoices
        $invoices = Invoice::select(
            DB::raw('SUM(collection_details.pending_amount) as outstanding_receivable')
        )
            ->leftJoin('collection_details', function ($join) {
                $join->on('collection_details.invoice_id', '=', 'invoices.id');
                $join->on(DB::raw('collection_details.id'), DB::raw('(SELECT MAX(id) from collection_details where invoice_id=invoices.id)'));
            })
            ->where('customer_id', $customer_id)
            ->first();

        $dataArray['outstanding_receivable'] = $invoices['outstanding_receivable'];

        //Customer Unused Credit
        $creditNote = CreditNote::select(DB::raw('SUM(pending_credit) as unused_credit'))
            ->where('customer_id', $customer_id)
            ->first();

        $dataArray['unused_credit'] = $creditNote['unused_credit'];

        //Prepare Results
        return prepareResult(true, $dataArray, [], "Customer Balances", $this->success);
    }

    public function customerBalanceStatement(Request $request)
    {
        $input = $request->json()->all();
        $customer_id = $input['customer_id'];
        $startdate = Carbon::parse($input['startdate'])->format('Y-m-d');
        $enddate = Carbon::parse($input['enddate'])->format('Y-m-d');
        $status = (isset($input['status']) ? $input['status'] : '');

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$customer_id and !$startdate and !$enddate) {
            return prepareResult(false, [], [], "Error while validating parameters.", $this->unauthorized);
        }

        $lastDateOfPre = Carbon::parse($startdate)->subDays(1);
        $startDateCurrent = Carbon::parse($startdate)->format("d/m/Y");
        $endDateCurrent = Carbon::parse($enddate)->format("d/m/Y");
        //Customer Invoices
        $userDetails = User::Select('*')
            ->with(
                'organisation',
                'organisation.countryInfo:id,name',
                'customerInfo'
            )
            ->where('id', $customer_id)
            ->first();

        $previousBalance = Invoice::select(
            DB::raw('SUM(collection_details.pending_amount) as opening_balance')
        )
            ->leftJoin('collection_details', function ($join) {
                $join->on('collection_details.invoice_id', '=', 'invoices.id');
                $join->on(DB::raw('collection_details.id'), DB::raw('(SELECT MAX(id) from collection_details where invoice_id=invoices.id)'));
            })
            ->where('customer_id', $customer_id)
            ->where('invoice_date', '<=', $lastDateOfPre)
            ->first()->toArray();

        $openBalance = 0.00;
        if (!empty($previousBalance['opening_balance'])) {
            $openBalance = $previousBalance['opening_balance'];
        }

        $openingBalance['c_date'] = $startDateCurrent;
        $openingBalance['transaction'] = '***Opening Balance***';
        $openingBalance['detail'] = '';
        $openingBalance['amount'] = $openBalance;
        $openingBalance['payment'] = '';
        $openingBalance['status'] = '0';

        //Customer Invoices
        $invoices = Invoice::select(DB::raw("DATE_FORMAT(invoice_date,'%d/%m/%Y') as c_date,'Bill of Supply' as transaction,CONCAT(invoice_number,' - due on ',DATE_FORMAT(invoice_due_date,'%d/%m/%y')) as detail,grand_total as amount,'0.00' as payment,1 as status"))
            ->where('customer_id', $customer_id)
            ->whereBetween('invoice_date', array("$startdate", "$enddate"))
            ->orderBy('invoice_date', 'ASC');

        $collections = Collection::select(DB::raw("DATE_FORMAT(cheque_date,'%d/%m/%Y') as c_date,'Payment Received' as transaction,CONCAT(invoice_amount,' for payment of ',collection_number) as detail,'0.00' as amount,invoice_amount as payment,2 as status"))
            ->where('customer_id', $customer_id)
            ->whereBetween('cheque_date', array("$startdate", "$enddate"))
            ->orderBy('cheque_date', 'ASC');

        $balanceStatement = CreditNote::select(DB::raw("DATE_FORMAT(credit_note_date,'%d/%m/%Y') as c_date,'Credit Note' as transaction,credit_note_number as detail,grand_total  as amount,'0.00' as payment,3 as status"))
            ->where('customer_id', $customer_id)
            ->whereBetween('credit_note_date', array("$startdate", "$enddate"))
            ->orderBy('credit_note_date', 'ASC')
            ->union($invoices)->union($collections)
            ->orderBy('c_date', 'ASC')
            ->get();

        $balanceStatement->splice(0, 0, [$openingBalance]);

        if (!is_object($balanceStatement)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        $dataArray['balanceStatement'] = $balanceStatement;
        $openingBalance = $invoiceAmount = $paymentReceived = $paymentReceived = number_format((float) 0, 2, '.', '');
        foreach ($balanceStatement as $balance) {
            if ($balance['status'] == 0) {
                $openingBalance = number_format((float) $openingBalance + $balance['amount'], 2, '.', '');
            } elseif ($balance['status'] == 1) {
                $invoiceAmount = number_format((float) $invoiceAmount + $balance['amount'], 2, '.', '');
            } elseif ($balance['status'] == 2) {
                $paymentReceived = number_format((float) $paymentReceived + $balance['payment'], 2, '.', '');
            } elseif ($balance['status'] == 3) {
                $paymentReceived = number_format((float) $paymentReceived + $balance['amount'], 2, '.', '');
            }
        }
        $balanceDue = number_format((float) $openingBalance + $invoiceAmount - $paymentReceived, 2, '.', '');
        $accountSummary['statement_date'] = $startDateCurrent . " To " . $endDateCurrent;
        $accountSummary['openingBalance'] = $openingBalance;
        $accountSummary['invoiceAmount'] = $invoiceAmount;
        $accountSummary['paymentReceived'] = $paymentReceived;
        $accountSummary['balanceDue'] = $balanceDue;

        $dataArray['userDetails'] = $userDetails;
        $dataArray['accountSummary'] = (object) $accountSummary;

        if ($status == "pdf") {
            $pdfFilePath = public_path() . "/uploads/statement/balance_statement.pdf";
            PDF::loadView('html.balance_statement_pdf', $dataArray)->save($pdfFilePath);

            $pdfFilePath = url('uploads/statement/balance_statement.pdf');
            $dataArray = array();
            $dataArray['file_url'] = $pdfFilePath;
        } else {
            $html = view('html.balance_statement', $dataArray)->render();
            $dataArray['html_string'] = $html;
        }

        //Prepare Results
        return prepareResult(true, $dataArray, [], "Customer Balance Statement", $this->success);
    }

    public function invoiceChart(Request $request)
    {
        $input = $request->json()->all();
        $customer_id = $input['customer_id'];
        $totalMonths = $input['totalMonths'];

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$customer_id) {
            return prepareResult(false, [], [], "Error while validating customer id.", $this->unauthorized);
        }

        $startDate = Carbon::now()->subMonths($totalMonths);

        //Customer Invoices
        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
        $expenses = DB::select("SELECT y_m,yearmonth,SUM(invoiceBalance) as balance,SUM(expenseBalance) as expenseBalance FROM (
                    (SELECT
                        DATE_FORMAT(`expense_date`, '%Y-%m') AS y_m,
                        DATE_FORMAT(`expense_date`, '%b %Y') AS yearmonth,
                        0 AS invoiceBalance,
                        SUM(amount) AS expenseBalance
                        FROM `expenses`
                        WHERE `customer_id` = $customer_id AND `expense_date` >=  '$startDate' AND `deleted_at` IS NULL
                        GROUP BY `y_m`)
                    UNION
                    (SELECT
                        DATE_FORMAT(`invoice_date`, '%Y-%m') AS y_m,
                        DATE_FORMAT(`invoice_date`, '%b %Y') AS yearmonth,
                        SUM(grand_total) AS balance,
                        0 AS expenseBalance
                    FROM `invoices`
                    WHERE `customer_id` = $customer_id AND `invoice_date` >=  '$startDate' AND `deleted_at` IS NULL
                    GROUP BY `y_m`)
                    ) as  charts GROUP BY y_m
                    ");

        // Mechanism for Getting empty months
        $yms = array();
        $now = date('Y-m');
        for ($x = $totalMonths - 1; $x >= 0; $x--) {
            $ym = date('Y-m', strtotime($now . " -$x month"));
            //            $ym = date_format(strtotime($ym),'y/m');
            $yms[$ym] = $ym;
        }

        $data_sorted = array();

        foreach ($yms as $key => $value) {
            $found_obj = 0;
            $count = 0;
            $yr_mon = $value;
            foreach ($expenses as $k => $v) {
                if ($v->y_m == $yr_mon) {
                    $count++;
                    $found_obj = $v;
                }
            }
            if ($count == 0) {
                //                Months Not Exists
                $dt_comp = $yr_mon . "-01";
                $date_formatted = date('M Y', strtotime($dt_comp));
                $empty_obj = (object) ['y_m' => $yr_mon, 'yearmonth' => $date_formatted, 'balance' => (string) "0.00", 'expenseBalance' => (string) "0.00"];
                array_push($data_sorted, $empty_obj);
            } else {
                array_push($data_sorted, $found_obj);
            }
        }

        $dataArray = $data_sorted;

        //Prepare Results
        return prepareResult(true, $dataArray, [], "Customer Invoice Chart", $this->success);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string $action
     * @param  string $status
     * @param  string $uuid
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        // if (!checkPermission('item-group-bulk-action')) {
        //     return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        // }

        $input = $request->json()->all();
        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating customer.", $this->unprocessableEntity);
        }

        $action = $request->action;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            $uuids = $request->customer_ids;

            foreach ($uuids as $uuid) {
                CustomerInfo::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0,
                ]);
            }

            // $CustomerInfo = $this->index();
            return prepareResult(true, "", [], "Customer Info status updated", $this->success);
        } else if ($action == 'delete') {
            $uuids = $request->customer_ids;
            foreach ($uuids as $uuid) {
                CustomerInfo::where('uuid', $uuid)->delete();
            }

            $CustomerInfo = $this->index();
            return prepareResult(true, $CustomerInfo, [], "Customer Info deleted success", $this->success);
        }
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $mappingarray = array("First Name", "Last Name", "Email", "Password", "Mobile", "Country", "Status", "Region", "Sales Organisation", "Route", "Channel", "Customer Category", "Customer Code", "Customer Type", "Office Address", "Home Address", "City", "State", "Zipcode", "Phone", "Balance", "Credit Limit", "Credit Days", "Payment Term", "Merchandiser Code", "Ship to party", "Sold to party", "Payer", "Bill to party", "LATITUDE", "LONGITUDE", "ERP Code");

        return prepareResult(true, $mappingarray, [], "Customer Mapping Field.", $this->success);
    }

    private function saveMerchandiser($merchandiser, $customer_id)
    {
        return collect($merchandiser)->map(function ($merchandiser_id, $key) use ($customer_id) {
            CustomerMerchandiser::create([
                'merchandiser_id' => $merchandiser_id,
                'customer_id' => $customer_id,
            ]);
        });
    }

    public function customerDropDown(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $search = '';
        $org_id = $request->user()->organisation_id;
        $type = 2; // Customer
        if ($request->search) {
            $search = $request->search;
        }
        $data_array = DB::select('call getCustomerDropDownList(?, ?, ?)', [$type, $search, $org_id]);
        return prepareResult(true, $data_array, [], "Customer Dropdown List", $this->success, 0);
    } //end customerDropDown

    /**
     * Display a listing of the resource.
     *
     * @param  int $user_id
     * @return \Illuminate\Http\Response
     */
    public function customer_lob($user_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $users = CustomerInfo::select('id', 'user_id', 'is_lob')
            ->where('user_id', $user_id)
            ->where('is_lob', 1)
            ->with(
                'customerlob',
                'customerlob.paymentTerm',
                'customerlob.customerType',
                'customerlob.lob:id,name'
            )->get();

        if (!is_object($users) || $users->isEmpty()) {
            return prepareResult(false, [], [], "Customer lob list not present.", $this->unprocessableEntity);
        }

        $users_array = array();
        if (is_object($users)) {
            foreach ($users as $key => $users_1) {
                if (count($users_1->customerlob)) {
                    foreach ($users_1->customerlob as $k => $cl) {
                        $cwmp = CustomerWarehouseMapping::with('storageocation:id,name,code')
                            ->where('customer_id', $users_1->user_id)
                            ->where('lob_id', $cl->lob_id)
                            ->get();
                        $users[$key]->customerlob[$k]->customer_warehouse_mapping = $cwmp;
                    }
                }
                $users_array[] = $users[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($users_array[$offset])) {
                    $data_array[] = $users_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($users_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($users_array);
        } else {
            $data_array = $users_array;
        }
        return prepareResult(true, $data_array, [], "Customer lob list", $this->success, $pagination);
    }

    public function getWarehouse($lob_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$lob_id) {
            return prepareResult(false, [], ['error' => "Please Provide Lob id"], "User not authenticate", $this->unauthorized);
        }

        $lob = Lob::find($lob_id);

        if ($lob) {
            $warehouse = Warehouse::where('name', 'like', '%' . $lob->name . '%')->first();

            if ($warehouse) {
                $storage_location = Storagelocation::select('id', 'code', 'name')
                    ->where('warehouse_id', $warehouse->id)
                    ->where('warehouse_type', 34)
                    ->get();

                return prepareResult(true, $storage_location, [], "Storage locaiton list", $this->success);
            }
            return prepareResult(false, [], ['error' => "Warehouse not registed"], "Warehouse not registed", $this->unauthorized);
        }

        return prepareResult(false, [], ['error' => "Please Provide correct lob id"], "Please Provide correct lob id", $this->unauthorized);
    }

    /**
     * Get data base on salesman
     * Delivery's cusotmers
     */

    public function getSalesmanDeliveryCustomer(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $salesman_id = $request->salesman_id;

        if (!$salesman_id) {
            return prepareResult(false, [], ["error" => "Salesman not found"], "Salesman not found.", $this->unauthorized);
        }


        $date = ($request->date) ? Carbon::parse($request->date)->format('Y-m-d') : now()->format('Y-m-d');
        // $date = now()->addDay()->format('Y-m-d');

        $delivery = Delivery::where('delivery_date', $date)
            ->orWhere('change_date', $date)
            ->whereHas('deliveryAssignTemplate', function ($q) use ($salesman_id) {
                $q->where('delivery_driver_id', $salesman_id);
            })
            ->whereNotIn('approval_status', ['Completed', 'Cancel'])
            ->whereNotIn('current_stage', ['Completed', 'Cancelled'])
            ->get()
            ->pluck('customer_id')
            ->toArray();

        $creditNote = CreditNote::whereHas('creditNoteDetails', function ($q) use ($salesman_id) {
            $q->where('salesman_id', $salesman_id);
        })
            ->where('approval_status', '!=', 'Completed')
            ->where('current_stage', '!=', 'Completed')
            ->get()
            ->pluck('customer_id')
            ->toArray();

        $merge = array_merge($delivery, $creditNote);

        if (count($merge)) {
            $customer = CustomerInfo::with(
                'user:id,usertype,firstname,lastname,email,mobile,role_id,country_id,status,parent_id',
                'user_country',
                'customerRoute:id,uuid,customer_id,customer_lob_id,route_id,is_lob',
                'customerRoute.route:id,route_code,route_name,status,depot_id',
                'customerRoute.route.depot:id,depot_code,depot_name',
                // 'route:id,route_code,route_name,status,depot_id',
                // 'route.depot:id,depot_code,depot_name',
                'channel:id,name,status',
                'region:id,region_name,region_status',
                'customerGroup:id,group_code,group_name',
                'customerCategory:id,customer_category_code,customer_category_name',
                'customerType:id,customer_type_name',
                'salesOrganisation:id,name',
                'paymentTerm:id,name',
                'shipToParty:id,user_id,customer_code',
                'shipToParty.user:id,firstname,lastname',
                'soldToParty:id,user_id,customer_code',
                'soldToParty.user:id,firstname,lastname',
                'payer:id,user_id,customer_code',
                'payer.user:id,firstname,lastname',
                'billToPayer:id,user_id,customer_code',
                //'paymentTerm:id,name,number_of_days',
                'billToPayer.user:id,firstname,lastname',
                'customFieldValueSave',
                'customFieldValueSave.customField',
                'customerlob',
                'customerlob.country:id,name',
                // 'customerlob.route:id,route_code,route_name,status,depot_id',
                // 'customerlob.route.depot:id,depot_code,depot_name',
                'customerlob.customerRoute',
                'customerlob.customerRoute.route:id,route_code,route_name,status,depot_id',
                'customerlob.customerRoute.route.depot:id,depot_code,depot_name',
                'customerlob.channel:id,name,status',
                'customerlob.region:id,region_code,region_name,region_status',
                'customerlob.customerType:id,customer_type_name',
                'customerlob.customerCategory:id,customer_category_code,customer_category_name',
                'customerlob.customerGroup:id,group_code,group_name',
                'customerlob.salesOrganisation:id,name',
                'customerlob.lob:id,name',
                'customerlob.paymentTerm:id,name',
                'customerlob.shipToParty:id,customer_code,user_id',
                'customerlob.shipToParty.user:id,firstname,lastname',
                'customerlob.soldToParty:id,customer_code,user_id',
                'customerlob.soldToParty.user:id,firstname,lastname',
                'customerlob.payer:id,customer_code,user_id',
                'customerlob.payer.user:id,firstname,lastname',
                'customerlob.billToPayer:id,customer_code,user_id',
                'customerlob.billToPayer.user:id,firstname,lastname',
                'customerlob.customerBlockTypes:id,customer_id,type,customer_lob_id,is_block',
                'customerDocument'
            )
                ->whereIn('user_id', $merge)
                ->get();

            if (count($customer)) {
                foreach ($customer as $key => $c) {
                    if (count($customer[$key]->customerlob)) {
                        foreach ($customer[$key]->customerlob as $k => $cl) {
                            $cwmp = CustomerWarehouseMapping::with('storageocation:id,name,code')
                                ->where('customer_id', $customer[$key]->user_id)
                                ->where('lob_id', $cl->lob_id)
                                ->get();
                            $customer[$key]->customerlob[$k]->customer_warehouse_mapping = $cwmp;
                        }
                    }
                }
            }

            return prepareResult(true, $customer, [], "Customer list.", $this->success);
        }

        return prepareResult(false, [], ["error" => "Delivery not found"], "Delivery not found.", $this->unauthorized);
    }

    public function JDENewCustomerDownload()
    {
        $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_customer-new.php')
            ->returnResponseObject()
            ->get();

        return prepareResult(true, [], [], "Download New Cusotmer.", $this->success);
    }

    public function JDENewLobCustomerDownload()
    {
        $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_customer_lob_test.php')
            ->returnResponseObject()
            ->get();

        return prepareResult(true, [], [], "Download New Lob Cusotmer.", $this->success);
    }

    public function importCustomerLatLong(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => 'User not authenticate'], 'User not authenticate.', $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, 'Failed to validate customer import', $this->unauthorized);
        }

        try {
            $import = new CustomersImport;
            Excel::import($import, $request->file('file'));

            if ($import->updatedRecords > 0) {
                return prepareResult(true, [], [], 'Customer Latitude and Longitude Updated Successfully!', $this->success);
            } else {
                return prepareResult(false, [], [], 'No records were updated.', $this->success);
            }
        } catch (\Throwable $th) {
            return prepareResult(false, $th->getMessage(), [], "Something Went Wrong!!!", $this->internal_server_error);
        }
    }
}
