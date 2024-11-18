<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Country;
use App\Model\CustomFieldValueSave;
use App\Model\ImportTempFile;
use App\Model\Route;
use Illuminate\Http\Request;
use App\User;
use App\Model\SalesmanInfo;
use App\Model\Delivery;
use App\Model\SalesmanRole;
use App\Model\SalesmanType;
use App\Model\SalesmanNumberRange;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SalesmanImport;
use App\Model\CreditNote;
use App\Model\DeliveryAssignTemplate;
use App\Model\Invoice;
use App\Model\SalesmanLob;
use App\Model\SalesmanLoginLog;
use Carbon\Carbon;
use File;
use Illuminate\Support\Facades\DB;

class SalesmanController extends Controller
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

        $salesman_type = "2";
        if (config('app.current_domain') == "presales") {
            $salesman_type = "1";
        }

        $users_query = SalesmanInfo::with(
            'user:id,uuid,organisation_id,usertype,firstname,lastname,email,mobile,role_id,country_id,status',
            'organisation:id,org_name',
            'route:id,route_code,route_name,status',
            'salesmanRole:id,name,code,status',
            'salesmanType:id,name,code,status',
            'salesmanRange',
            'supervisorUser:id,firstname,lastname',
            'salesmanHelper:id,firstname,lastname',
            'customFieldValueSave',
            'customFieldValueSave.customField',
            'salesmanlob:id,lob_id,salesman_info_id',
            'salesmanlob.lob:id,name',
        )
            ->where('salesman_type_id', $salesman_type);

        if ($request->salesman_code) {
            $users_query->where('salesman_code', 'like', '%' . $request->salesman_code . '%');
        }

        if (isset($request->status) && ($request->status == 1 || $request->status == 0)) {
            $users_query->where('status', $request->status);
        }

        if (count($all_salesman)) {
            $users_query->whereIn('user_id', $all_salesman);
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

        if ($request->supervisor) {
            $name = $request->supervisor;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $users_query->whereHas('supervisorUser', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $users_query->whereHas('supervisorUser', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->type) {
            $type = $request->type;
            $users_query->whereHas('salesmanType', function ($q) use ($type) {
                $q->where('name', 'like', '%' . $type . '%');
            });
        }

        $users = $users_query->orderBy('id', 'desc')->get();

        // approval
        $results = GetWorkFlowRuleObject('Salesman');
        $approve_need_salesman = array();
        $approve_need_salesman_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_salesman[] = $raw['object']->raw_id;
                $approve_need_salesman_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $users_array = array();
        if (is_object($users)) {
            foreach ($users as $key => $user1) {
                if (in_array($users[$key]->id, $approve_need_salesman)) {
                    $users[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_salesman_object_id[$users[$key]->id])) {
                        $users[$key]->objectid = $approve_need_salesman_object_id[$users[$key]->id];
                    } else {
                        $users[$key]->objectid = '';
                    }
                } else {
                    $users[$key]->need_to_approve = 'no';
                    $users[$key]->objectid = '';
                }

                if ($users[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || in_array($users[$key]->id, $approve_need_salesman)) {
                    $users_array[] = $users[$key];
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
                if (isset($users_array[$offset])) {
                    $data_array[] = $users_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($users_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($users_array);
        } else {
            $data_array = $users_array;
        }
        return prepareResult(true, $data_array, [], "Salesman listing", $this->success, $pagination);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function salesmanTypeList()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $salesman_type = SalesmanType::orderBy('id', 'desc')->get();

        if (is_object($salesman_type)) {
            foreach ($salesman_type as $key => $salesman_type1) {
                $salesman_type_array[] = $salesman_type[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($salesman_type_array[$offset])) {
                    $data_array[] = $salesman_type_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($salesman_type_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($salesman_type_array);
        } else {
            $data_array = $salesman_type_array;
        }
        return prepareResult(true, $data_array, [], "Salesman type listing", $this->success, $pagination);

        // return prepareResult(true, $salesman_type, [], "Salesman type listing", $this->success);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function salesmanRoleList()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $salesman_role = SalesmanRole::orderBy('id', 'desc')->get();

        if (is_object($salesman_role)) {
            foreach ($salesman_role as $key => $salesman_role1) {
                $salesman_role_array[] = $salesman_role[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($salesman_role_array[$offset])) {
                    $data_array[] = $salesman_role_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($salesman_role_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($salesman_role_array);
        } else {
            $data_array = $salesman_role_array;
        }

        return prepareResult(true, $data_array, [], "Salesman role listing", $this->success, $pagination);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Salesman", $this->unprocessableEntity);
        }

        // if ($request->salesman_type_id == 1 && !$request->route_id) {
        //     $validator = \Validator::make($input, [
        //         'route_id' => 'required|integer|exists:routes,id',
        //     ]);

        //     if ($validator->fails()) {
        //         return prepareResult(false, [], $validator->errors()->first(), "Error while validating Salesman", $this->unprocessableEntity);
        //     }
        // }

        DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Salesman', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Salesman',$request);
            }

            $user = new User;
            $user->usertype = 3;
            $user->parent_id = $request->parent_id;
            $user->firstname = $request->firstname;
            $user->lastname = $request->lastname;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->mobile = $request->mobile;
            $user->country_id = $request->country_id;
            $user->api_token = \Str::random(35);
            $user->is_approved_by_admin = $request->is_approved_by_admin;
            $user->role_id = $request->role_id;
            $user->status = $status;
            $user->save();

            $salesman_infos = new SalesmanInfo;
            $salesman_infos->user_id = $user->id;
            $salesman_infos->salesman_type_id = $request->salesman_type_id;

            $salesman_infos->salesman_code = nextComingNumber('App\Model\SalesmanInfo', 'salesman', 'salesman_code', $request->salesman_code);
            // $salesman_infos->salesman_code = $request->salesman_code;
            if ($request->salesman_profile) {
                $salesman_infos->profile_image = saveImage($request->firstname . ' ' . $request->lastname, $request->salesman_profile, 'salesman-profile');
            }

            if ($request->salesman_type_id != 3) {
                $salesman_infos->route_id = $request->route_id;
                $salesman_infos->salesman_role_id = $request->salesman_role_id;
                $salesman_infos->salesman_supervisor = $request->salesman_supervisor;
                $salesman_infos->is_lob = (!empty($request->is_lob)) ? $request->is_lob : 0;
            }
            $salesman_infos->salesman_helper_id = (!empty($request->salesman_helper_id)) ? $request->salesman_helper_id : null;

            $salesman_infos->current_stage = $current_stage;
            $salesman_infos->status = $status;
            $salesman_infos->category_id = (!empty($request->category_id)) ? $request->category_id : null;
            $salesman_infos->region_id = (!empty($request->region_id)) ? $request->region_id : null;
            $salesman_infos->printer_type = (!empty($request->printer_type) && $request->printer_type == "Zebra") ? 1 : ((!empty($request->printer_type) && $request->printer_type == "Bixolon") ? 2 : null);
            $salesman_infos->save();

            if ($request->is_lob == 1) {
                if (is_array($request->salesman_lob)) {
                    foreach ($request->salesman_lob as $salesman_lob_value) {
                        $salesman_lob = new SalesmanLob();
                        $salesman_lob->salesman_info_id  = $salesman_infos->id;
                        $salesman_lob->lob_id            = $salesman_lob_value['lob_id'];
                        $salesman_lob->save();
                    }
                }
            }

            if ($request->category_id == "Salesman" && config('app.current_domain') == "merchandising") {
                $salesman_number_range = new SalesmanNumberRange;
                $salesman_number_range->salesman_id = $salesman_infos->id;
                $salesman_number_range->customer_from = $request->customer_from;
                $salesman_number_range->customer_to = $request->customer_to;
                $salesman_number_range->order_from = $request->order_from;
                $salesman_number_range->order_to = $request->order_to;
                $salesman_number_range->invoice_from = $request->invoice_from;
                $salesman_number_range->invoice_to = $request->invoice_to;
                $salesman_number_range->collection_from = $request->collection_from;
                $salesman_number_range->collection_to = $request->collection_to;
                $salesman_number_range->credit_note_from = $request->credit_note_from;
                $salesman_number_range->credit_note_to = $request->credit_note_to;
                $salesman_number_range->unload_from = $request->unload_from;
                $salesman_number_range->unload_to = $request->unload_to;
                $salesman_number_range->exchange_from = "100000";
                $salesman_number_range->exchange_to = "999999";
                $salesman_number_range->save();
            }

            if ($isActivate = checkWorkFlowRule('Salesman', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Salesman', $request, $salesman_infos);
            }

            updateNextComingNumber('App\Model\SalesmanInfo', 'salesman');

            if (is_array($request->modules) && sizeof($request->modules) >= 1) {
                foreach ($request->modules as $module) {
                    savecustomField($salesman_infos->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
                }
            }

            DB::commit();
            $salesman_infos->getSaveData();
            return prepareResult(true, $salesman_infos, [], "Salesman added successfully", $this->success);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $users = User::where('uuid', $uuid)->first()->salesmanInfo;

        $users->salesmanRange;

        if (!is_object($users)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $users, [], "Salesman Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Salesman", $this->unprocessableEntity);
        }

        // if ($request->salesman_type_id == 1 && !$request->route_id) {
        //     $validator = \Validator::make($input, [
        //         'route_id' => 'required|integer|exists:routes,id',
        //     ]);

        //     if ($validator->fails()) {
        //         return prepareResult(false, [], $validator->errors()->first(), "Error while validating Salesman", $this->unprocessableEntity);
        //     }
        // }

        $status = 1;
        $current_stage = 'Approved';
        $current_organisation_id = request()->user()->organisation_id;
        if ($isActivate = checkWorkFlowRule('Salesman', 'create', $current_organisation_id)) {
            $status = 0;
            $current_stage = 'Pending';
        }

        // $user->email = $request->email;
        $user = User::where('uuid', $uuid)->first();

        $user->usertype = 3;
        $user->parent_id            = $request->parent_id;
        $user->firstname            = $request->firstname;
        $user->lastname             = $request->lastname;
        if ($request->password) {
            $user->password = Hash::make($request->password);
        }
        $user->mobile               = $request->mobile;
        $user->country_id           = $request->country_id;
        $user->is_approved_by_admin = $request->is_approved_by_admin;
        $user->role_id              = $request->role_id;
        $user->status               = $status;
        $user->save();

        $salesman_infos                     = $user->salesmanInfo;
        $salesman_infos->route_id           = $request->route_id;
        $salesman_infos->salesman_type_id   = $request->salesman_type_id;
        $salesman_infos->salesman_role_id   = $request->salesman_role_id;
        $salesman_infos->salesman_supervisor = $request->salesman_supervisor;
        $salesman_infos->block_start_date   = $request->block_start_date;
        $salesman_infos->block_end_date     = $request->block_end_date;
        if ($request->salesman_profile) {
            $salesman_infos->profile_image = saveImage($request->firstname . ' ' . $request->lastname, $request->salesman_profile, 'salesman-profile');
        }
        $salesman_infos->is_block = $request->is_block;
        if ($request->is_block == 1) {
            $salesman_infos->block_start_date = $request->block_start_date;
            $salesman_infos->block_end_date = $request->block_end_date;
        } else {
            $salesman_infos->block_start_date = null;
            $salesman_infos->block_end_date = null;
        }
        $salesman_infos->current_stage = $current_stage;
        $salesman_infos->status = $status;
        $salesman_infos->printer_type = (!empty($request->printer_type) && $request->printer_type == "Zebra") ? 1 : ((!empty($request->printer_type) && $request->printer_type == "Bixolon") ? 2 : null);
        $salesman_infos->save();

        // $salesman_number_range = SalesmanNumberRange::where('salesman_id', $salesman_infos->id)->first();
        // $salesman_number_range->salesman_id = $salesman_infos->id;
        // $salesman_number_range->customer_from = $request->customer_from;
        // $salesman_number_range->customer_to = $request->customer_to;
        // $salesman_number_range->order_from = $request->order_from;
        // $salesman_number_range->order_to = $request->order_to;
        // $salesman_number_range->invoice_from = $request->invoice_from;
        // $salesman_number_range->invoice_to = $request->invoice_to;
        // $salesman_number_range->collection_from = $request->collection_from;
        // $salesman_number_range->collection_to = $request->collection_to;
        // $salesman_number_range->credit_note_from = $request->credit_note_from;
        // $salesman_number_range->credit_note_to = $request->credit_note_to;
        // $salesman_number_range->unload_from = $request->unload_from;
        // $salesman_number_range->unload_to = $request->unload_to;
        // $salesman_number_range->save();

        if ($isActivate = checkWorkFlowRule('Salesman', 'edit', $current_organisation_id)) {
            $this->createWorkFlowObject($isActivate, 'Salesman', $request, $salesman_infos->id);
        }

        if (is_array($request->modules) && sizeof($request->modules) >= 1) {
            CustomFieldValueSave::where('record_id', $salesman_infos->id)->delete();
            foreach ($request->modules as $module) {
                savecustomField($salesman_infos->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
            }
        }
        $salesman_infos->getSaveData();
        return prepareResult(true, $user, [], "Salesman updated successfully", $this->success);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $uuid
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

        $user = User::where('uuid', $uuid)
            ->first();

        if (is_object($user)) {
            $user->salesmanInfo->delete();
            $user->delete();
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
                // 'country_id' => 'required|integer|exists:countries,id',
                'salesman_type_id' => 'required|integer|exists:salesman_types,id',
                'salesman_role_id' => 'required|integer|exists:salesman_roles,id',
                'salesman_code' => 'required|unique:salesman_infos,salesman_code',
                'firstname' => 'required',
                'lastname' => 'required',
                // 'email' => 'required|email|unique:users,email',
                'password' => 'required',
                'role_id' => 'required',
                'status' => 'required'
                // 'mobile' => 'required',
                // 'is_approved_by_admin' => 'required',
                // 'salesman_supervisor' => 'required'
            ]);
        }

        if ($type == "edit") {
            $validator = \Validator::make($input, [
                // 'route_id' => 'required|integer|exists:routes,id',
                // 'country_id' => 'required|integer|exists:countries,id',
                'salesman_type_id' => 'required|integer|exists:salesman_types,id',
                'salesman_role_id' => 'required|integer|exists:salesman_roles,id',
                'salesman_code' => 'required',
                'firstname' => 'required',
                'lastname' => 'required',
                'role_id' => 'required',
                'status' => 'required'
                // 'password' => 'required',
                // 'mobile' => 'required',
                // 'is_approved_by_admin' => 'required',
                // 'salesman_supervisor' => 'required'
            ]);
        }

        if ($type == "shipment-status") {
            $validator = \Validator::make($input, [
                'salesman_id' => 'required|integer|exists:salesman_infos,user_id',
                'trip' => 'required',
                'date' => 'required|date',
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'salesman_info_ids' => 'required'
            ]);
            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * This function is use for the mobiel helper list
     *
     * @return void
     */
    public function helperMobileList()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $users_query = SalesmanInfo::with(
            'user:id,uuid,organisation_id,usertype,firstname,lastname,email,mobile,role_id,country_id,status',
            'organisation:id,org_name',
            'route:id,route_code,route_name,status',
            'salesmanRole:id,name,code,status',
            'salesmanType:id,name,code,status',
            'salesmanRange',
            'salesmanSupervisor:id,firstname,lastname',
            'salesmanHelper:id,firstname,lastname',
            'customFieldValueSave',
            'customFieldValueSave.customField',
            'salesmanlob:id,lob_id,salesman_info_id',
            'salesmanlob.lob:id,name',
        )
            ->where('salesman_type_id',  '1');
        
        $users = $users_query->orderBy('id', 'desc')->get();

        $salesman_info_array = array();
        if (is_object($users)) {
            foreach ($users as $key => $salesman_info1) {
                $salesman_info_array[] = $users[$key];
            }
        }

        $data_array = array();
        $data_array = $salesman_info_array;

        return prepareResult(true, $data_array, [], "Helper listing", $this->success);
    }
    
    public function merchandiserList()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $salesman_info = SalesmanInfo::select('id', 'user_id', 'salesman_type_id', 'organisation_id')
            ->with(
                'user:id,uuid,organisation_id,usertype,firstname,lastname,email,mobile,role_id,country_id,status'
            )
            ->where('salesman_type_id', 2)
            ->orderBy('id', 'desc')
            ->get();

        $salesman_info_array = array();
        if (is_object($salesman_info)) {
            foreach ($salesman_info as $key => $salesman_info1) {
                $salesman_info_array[] = $salesman_info[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($salesman_info_array[$offset])) {
                    $data_array[] = $salesman_info_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($salesman_info_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_reocrds'] = count($salesman_info_array);
        } else {
            $data_array = $salesman_info_array;
        }

        return prepareResult(true, $data_array, [], "Merchandiser listing", $this->success);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating salesman info.", $this->unprocessableEntity);
        }

        $action = $request->action;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            $uuids = $request->salesman_info_ids;

            foreach ($uuids as $uuid) {
                SalesmanInfo::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0
                ]);
            }

            // $CustomerInfo = $this->index();
            return prepareResult(true, "", [], "SalesmanInfo status updated", $this->success);
        } else if ($action == 'delete') {
            $uuids = $request->salesman_info_ids;
            foreach ($uuids as $uuid) {
                SalesmanInfo::where('uuid', $uuid)->delete();
            }

            // $CustomerInfo = $this->index();
            return prepareResult(true, "", [], "SalesmanInfo deleted success", $this->success);
        }
    }

    public function imports(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Salesman not authenticate", $this->unauthorized);
        }

        $validator = \Validator::make($request->all(), [
            'salesman_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate region import", $this->unauthorized);
        }

        Excel::import(new SalesmanImport, request()->file('salesman_file'));
        return prepareResult(true, [], [], "Salesman successfully imported", $this->success);
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $mappingarray = array("First Name", "Last Name", "Email", "Password", "Mobile", "Country", "Status", "Route", "Merchandiser Type", "Merchandiser Role", "Merchandiser Code", "Merchandiser Supervisor", "Order From", "Order To", "Invoice From", "Invoice To", "Collection From", "Collection To", "Return From", "Return To", "Unload From", "Unload To");


        return prepareResult(true, $mappingarray, [], "Customer Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'salesman_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate salesman import", $this->unauthorized);
        }
        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('salesman_file')->store('import');
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

            $import = new SalesmanImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

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

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
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
        return prepareResult(true, $result, $errors, "salesman successfully imported", $this->success);
    }

    public function finalimport(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);

            if ($finaldata) :
                foreach ($finaldata as $row) :

                    $country = Country::where('name', 'LIKE', '%' . $row[5] . '%')->first();
                    $route = Route::where('route_name', $row[7])->first();
                    $salesman_type = SalesmanType::where('name', $row[8])->first();
                    $salesman_role = SalesmanRole::where('name', $row[9])->first();

                    $user = User::where('email', $row[2])->first();

                    $current_stage = 'Approved';
                    $current_organisation_id = request()->user()->organisation_id;
                    if ($row[6] == "Yes") {
                        $status = 1;
                    }
                    if ($row[6] == "No") {
                        $status = 0;
                    }
                    if ($isActivate = checkWorkFlowRule('Salesman', 'create', $current_organisation_id)) {
                        $status = 0;
                        $current_stage = 'Pending';
                        //$this->createWorkFlowObject($isActivate, 'Salesman',$request);
                    }

                    //$skipduplicate = 1 means skip the data
                    //$skipduplicate = 0 means overwrite the data
                    $skipduplicate = $request->skipduplicate;

                    if ($skipduplicate) {
                        $salesmanInfo = User::where('email', $row[2])->first();
                        if (is_object($salesmanInfo)) {
                            continue;
                        }
                        $user = new User;
                        $user->usertype     = 3;
                        $user->firstname    = $row[0];
                        $user->lastname     = $row[1];
                        $user->email        = $row[2];
                        $user->password     = Hash::make($row[3]);
                        $user->mobile       = $row[4];
                        $user->country_id   = (is_object($country)) ? $country->id : 0;
                        $user->api_token    = \Str::random(35);
                        $user->status       = $status;
                        $user->save();

                        $salesman_infos = new SalesmanInfo;
                        $salesman_infos->organisation_id        = $current_organisation_id;
                        $salesman_infos->user_id                = $user->id;
                        $salesman_infos->route_id               = (is_object($route)) ? $route->id : 0;
                        $salesman_infos->salesman_type_id       = (is_object($salesman_type)) ? $salesman_type->id : 0;
                        $salesman_infos->salesman_role_id       = (is_object($salesman_role)) ? $salesman_role->id : 0;
                        $salesman_infos->salesman_code          = $row[10];
                        $salesman_infos->salesman_supervisor    = $row[11];
                        $salesman_infos->current_stage          = $current_stage;
                        $salesman_infos->status                 = $status;
                        $salesman_infos->save();

                        $salesman_number_ranges = new SalesmanNumberRange;
                        $salesman_number_ranges->salesman_id        = (is_object($salesman_infos)) ? $salesman_infos->id : 0;
                        $salesman_number_ranges->route_id           = (is_object($route)) ? $route->id : NULL;
                        $salesman_number_ranges->order_from         = $row[12];
                        $salesman_number_ranges->order_to           = $row[13];
                        $salesman_number_ranges->invoice_from       = $row[14];
                        $salesman_number_ranges->invoice_to         = $row[15];
                        $salesman_number_ranges->collection_from    = $row[16];
                        $salesman_number_ranges->collection_to      = $row[17];
                        $salesman_number_ranges->credit_note_from   = $row[18];
                        $salesman_number_ranges->credit_note_to     = $row[19];
                        $salesman_number_ranges->unload_from        = $row[20];
                        $salesman_number_ranges->unload_to          = $row[21];
                        $salesman_number_ranges->save();
                    } else {
                        $salesman_infos = SalesmanInfo::where('salesman_code', $row[10])->first();
                        // $user_check = User::where('email', $row[2])->first();

                        if (is_object($salesman_infos)) {

                            $user = User::find($salesman_infos->user_id);
                            $user->usertype = 3;
                            // $user->parent_id = auth()->user()->id;
                            $user->firstname = $row[0];
                            $user->lastname  = $row[1];
                            $user->email = $row[2];
                            $user->password = Hash::make($row[3]);
                            $user->mobile = $row[4];
                            $user->country_id = (is_object($country)) ? $country->id : 0;
                            $user->api_token = \Str::random(35);
                            $user->status = $status;
                            $user->save();

                            $salesman_infos->organisation_id = $current_organisation_id;
                            $salesman_infos->user_id = $user->id;
                            $salesman_infos->route_id = (is_object($route)) ? $route->id : 0;
                            $salesman_infos->salesman_type_id = (is_object($salesman_type)) ? $salesman_type->id : 0;
                            $salesman_infos->salesman_role_id = (is_object($salesman_role)) ? $salesman_role->id : 0;
                            $salesman_infos->salesman_code = $row[10];
                            $salesman_infos->salesman_supervisor = $row[11];
                            $salesman_infos->current_stage = $current_stage;
                            $salesman_infos->status = $status;
                            $salesman_infos->save();

                            $salesman_number_ranges = SalesmanNumberRange::where('salesman_id', $salesman_infos->id)->first();
                            if ($route) {
                                $salesman_number_ranges = SalesmanNumberRange::where('route_id', $route->id)->first();
                            }

                            if (!is_object($salesman_number_ranges)) {
                                $salesman_number_ranges = new SalesmanNumberRange;
                            }

                            $salesman_number_ranges->salesman_id = (is_object($salesman_infos)) ? $salesman_infos->id : 0;
                            $salesman_number_ranges->route_id = (is_object($route)) ? $route->id : 0;
                            $salesman_number_ranges->order_from = $row[12];
                            $salesman_number_ranges->order_to = $row[13];
                            $salesman_number_ranges->invoice_from = $row[14];
                            $salesman_number_ranges->invoice_to = $row[15];
                            $salesman_number_ranges->collection_from = $row[16];
                            $salesman_number_ranges->collection_to = $row[17];
                            $salesman_number_ranges->credit_note_from = $row[18];
                            $salesman_number_ranges->credit_note_to  = $row[19];
                            $salesman_number_ranges->unload_from = $row[20];
                            $salesman_number_ranges->unload_to  = $row[21];
                            $salesman_number_ranges->save();
                        } else {
                            $user = new User;
                            $user->usertype = 3;
                            // $user->parent_id = auth()->user()->id;
                            $user->firstname = $row[0];
                            $user->lastname  = $row[1];
                            $user->email = $row[2];
                            $user->password = Hash::make($row[3]);
                            $user->mobile = $row[4];
                            $user->country_id = (is_object($country)) ? $country->id : 0;
                            $user->api_token = \Str::random(35);
                            $user->status = $status;
                            $user->save();

                            $salesman_infos = new SalesmanInfo;
                            $salesman_infos->organisation_id = $current_organisation_id;
                            $salesman_infos->user_id = $user->id;
                            $salesman_infos->route_id = (is_object($route)) ? $route->id : 0;
                            $salesman_infos->salesman_type_id = (is_object($salesman_type)) ? $salesman_type->id : 0;
                            $salesman_infos->salesman_role_id = (is_object($salesman_role)) ? $salesman_role->id : 0;
                            $salesman_infos->salesman_code = $row[10];
                            $salesman_infos->salesman_supervisor = $row[11];
                            $salesman_infos->current_stage = $current_stage;
                            $salesman_infos->status = $status;
                            $salesman_infos->save();

                            $salesman_number_ranges = new SalesmanNumberRange;
                            $salesman_number_ranges->salesman_id = (is_object($salesman_infos)) ? $salesman_infos->id : 0;
                            $salesman_number_ranges->order_from = $row[12];
                            $salesman_number_ranges->order_to = $row[13];
                            $salesman_number_ranges->invoice_from = $row[14];
                            $salesman_number_ranges->invoice_to = $row[15];
                            $salesman_number_ranges->collection_from = $row[16];
                            $salesman_number_ranges->collection_to = $row[17];
                            $salesman_number_ranges->credit_note_from = $row[18];
                            $salesman_number_ranges->credit_note_to  = $row[19];
                            $salesman_number_ranges->unload_from = $row[20];
                            $salesman_number_ranges->unload_to  = $row[21];
                            $salesman_number_ranges->save();
                        }
                    }
                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                \DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "salesman successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function salesmanLoginLog(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $salesman_login_log_query = SalesmanLoginLog::select('id', 'user_id', 'ip', 'device_token', 'vesion', 'device_name', 'imei_number', 'created_at');

        if ($request->merchandiser_id) {
            $salesman_login_log_query->where('user_id', $request->merchandiser_id);
        }

        if ($request->date) {
            $salesman_login_log_query->whereDate('created_at', $request->date);
        }

        $salesman_login_log = $salesman_login_log_query->orderBy('created_at', 'desc')->get();

        $salesman_login_log_array = array();
        if (is_object($salesman_login_log)) {
            foreach ($salesman_login_log as $key => $salesman_login_log1) {
                $salesman_login_log_array[] = $salesman_login_log[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($salesman_login_log_array[$offset])) {
                    $data_array[] = $salesman_login_log_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($salesman_login_log_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($salesman_login_log_array);
        } else {
            $data_array = $salesman_login_log_array;
        }

        return prepareResult(true, $data_array, [], "Salesman login log listing", $this->success, $pagination);
    }

    private function saveSalesmanNumberRage($salesman_infos_id, $request)
    {
        $salesman_number_range = new SalesmanNumberRange;
        $salesman_number_range->salesman_id = $salesman_infos_id;
        $salesman_number_range->customer_from = "M" . $salesman_infos_id . "S00001";
        $salesman_number_range->customer_to = "M" . $salesman_infos_id . "S99999";
        $salesman_number_range->order_from = "M" . $salesman_infos_id . "O00001";
        $salesman_number_range->order_to = "M" . $salesman_infos_id . "O99999";
        $salesman_number_range->invoice_from = "M" . $salesman_infos_id . "F00001";
        $salesman_number_range->invoice_to = "M" . $salesman_infos_id . "F99999";
        $salesman_number_range->collection_from = "M" . $salesman_infos_id . "C00001";
        $salesman_number_range->collection_to = "M" . $salesman_infos_id . "C99999";
        $salesman_number_range->credit_note_from = "M" . $salesman_infos_id . "R00001";
        $salesman_number_range->credit_note_to = "M" . $salesman_infos_id . "R99999";
        $salesman_number_range->unload_from = "M" . $salesman_infos_id . "U00001";
        $salesman_number_range->unload_to = "M" . $salesman_infos_id . "U99999";
        $salesman_number_range->save();
    }

    /**
     * This is the function get mobile sales data
     *
     * @return void
     */
    public function getMobileSalesData(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->user_id) && sizeof($request->user_id) >= 1) {
            return prepareResult(false, [], [], "Error while validating salesman id", $this->unauthorized);
        }

        $get_data = Invoice::whereIn('salesman_id', $request->user_id)->select('*');

        if (isset($request->from_date) && isset($request->to_date)) {
            $end_date = Carbon::parse($request->to_date)->addDay()->format('Y-m-d');
            $invoices = $get_data
                ->whereBetween('invoice_date', [$request->from_date, $end_date])
                ->get();
        } elseif ($request->from_date) {
            $invoices = $get_data
                ->where('invoice_date', '>=', $request->from_date)
                ->get();
        } elseif ($request->to_date) {
            $invoices = $get_data
                ->where('invoice_date', '=>', $request->to_date)
                ->get();
        } else {
            $invoices = $get_data->where('invoice_date', '=', now()->format('Y-m-d'))->get();
        }

        $end_date = Carbon::parse($request->to_date)->addDay()->format('Y-m-d');
        $tend_date = now()->addDay()->format('Y-m-d');
        $tstart_date = Carbon::now()->startOfMonth()->toDateString();

        $target_invoices = Invoice::select(DB::raw('sum(grand_total) as grand_total'))
            ->whereIn('salesman_id', $request->user_id)
            ->whereBetween('invoice_date', [$tstart_date, $tend_date])
            ->first();

        $get_data_credit_note = CreditNote::with(['customer' => function ($query) {
            $query->select('id', 'firstname AS customer_firstname', 'lastname AS customer_lastname');
        }])->where('salesman_id', $request->user_id)
            ->select('id AS creadit_note_id', 'salesman_id', 'customer_id', 'credit_note_number', 'credit_note_date', DB::raw('sum(grand_total) as grand_total'))
            ->groupBy('credit_note_date');

        if (isset($request->from_date) && isset($request->to_date)) {
            $end_date = Carbon::parse($request->to_date)->addDay()->format('Y-m-d');
            $credit_note = $get_data_credit_note
                ->whereBetween('credit_note_date', [$request->from_date, $end_date])
                ->get();
        } elseif ($request->from_date) {
            $credit_note = $get_data_credit_note->where('credit_note_date', '>=', $request->from_date)->get();
        } elseif ($request->to_date) {
            $credit_note = $get_data_credit_note->where('credit_note_date', '=<', $request->to_date)->get();
        } else {
            $credit_note = $get_data_credit_note->where('credit_note_date', now()->format('Y-m-d'))->get();
        }

        $total_return = 0;
        if (count($credit_note)) {
            $grand_total = $credit_note->pluck('grand_total')->toArray();
            $total_return = round(array_sum($grand_total), 2);
        }

        $invoice_ids = array();
        $total_sales = 0;
        $total_target = 0;
        if (count($invoices)) {
            $invoice_ids = $invoices->pluck('id')->toArray();
            $grand_total = $invoices->pluck('grand_total')->toArray();
            $total_sales = round(array_sum($grand_total), 2);
            // $total_target = $total_sales;
        }

        $moving_item = $this->itemMoving($invoice_ids);

        $load = $this->salesmanLoadDetails($request->user_id, $request->from_date, $request->to_date);

        $load_item = 0;
        if (count($load)) {
            $load_item = count($load->pluck('item_id')->toArray());
        }

        $unload = $this->salesmanUnloadDetail($request->user_id, $request->from_date, $request->to_date);

        $unload_item = 0;
        if (count($unload)) {
            $unload_item = count($unload->pluck('item_id')->toArray());
        }

        $collections = $this->collections($request->user_id, $request->from_date, $request->to_date);

        $payment_summary = 0;
        if (count($collections)) {
            $payment_summary    = round(array_sum($collections->pluck('invoice_amount')->toArray()), 2);
        }

        $brand_wise_sales       = $this->brandWiseSale($invoice_ids);
        $category_wise_sales    = $this->categoryWiseSale($invoice_ids);
        $item_wise_sales        = $this->itemWiseSales($invoice_ids);

        $data = array(
            'slow_item_moving'  => $moving_item['slow_item_moving'],
            'fast_item_moving'  => $moving_item['fast_item_moving'],
            'total_sales'       => $total_sales,
            'total_return'      => $total_return,
            'total_target'      => (!empty($target_invoices)) ? $target_invoices->grand_total : 0,
            'load_item'         => $load_item,
            'unload_item'       => $unload_item,
            'payment_summary'   => $payment_summary,
            'brand_wise_sales'   => $brand_wise_sales,
            'category_wise_sales'   => $category_wise_sales,
            'item_wise_sales'   => $item_wise_sales,
        );

        return prepareResult(true, $data, [], "Data success", $this->success);
    }

    public function tomorrowDelivery($salesman_id)
    {
        if (!$salesman_id) {
            return prepareResult(false, [], ['error' => 'Salesman id is required.'], "Salesman id is required.", $this->unprocessableEntity);
        }


        $dat = DeliveryAssignTemplate::select('delivery_id')
            ->where('delivery_driver_id', $salesman_id)
            ->get();

        if (count($dat)) {
            $delivery = Delivery::select('id', 'customer_id', 'delivery_number', 'order_id', 'current_stage', 'approval_status')
                ->with(
                    'deliveryAssignTemplate',
                    'order:id,order_number,customer_lop',
                    'customerInfo:id,user_id,customer_code',
                    'customer:id,firstname,lastname'
                )
                ->whereIn('id', $dat)
                ->whereNotIn('approval_status', ['Completed', 'Cancel'])
                ->whereBetween('delivery_date', [now()->format('Y-m-d'), now()->addDay()->format('Y-m-d')])
                ->get();

            if (count($delivery)) {
                return prepareResult(true, $delivery, [], "Salesman delivery.", $this->success);
            }
        }

        return prepareResult(false, [], ['error' => 'Delivery not found.'], "Delivery not found.", $this->not_found);
    }

    public function shipmentDeliveryStatus(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "shipment-status");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Salesman", $this->unprocessableEntity);
        }

        $status = 0;

        $date = $request->date;

        $dat = DeliveryAssignTemplate::where('delivery_driver_id', $request->salesman_id)
            ->where('trip', $request->trip)
            ->whereHas('delivery', function ($q) use ($date) {
                $q->where('delivery_date', $date)
                    ->orWhere('change_date', $date);
            })
            ->groupBy('delivery_id')
            ->get();

        if (count($dat)) {
            $delivery_ids = $dat->pluck('delivery_id')->toArray();

            $all_delivery = Delivery::select(
                DB::raw('count(id) as total_delivery'),
            )->where(function ($query) use ($date) {
                $query->where('delivery_date', '=', $date)
                    ->orWhere('change_date', '=', $date);
            })
                ->where('approval_status', '!=', 'Cancel')
                ->whereIn('id', $delivery_ids)
                ->first();

            $total_delivery = $all_delivery->total_delivery;

            $alls_delivery = Delivery::select(
                DB::raw('count(id) as total_delivery'),
            )->where(function ($query) use ($date) {
                $query->where('delivery_date', '=', $date)
                    ->orWhere('change_date', '=', $date);
            })
                ->whereIn('approval_status', ['Shipment', 'Completed', 'Cancel'])
                ->whereIn('id', $delivery_ids)
                ->first();

            $totals_delivery = $alls_delivery->total_delivery;
            if ($totals_delivery > 0 && $total_delivery > 0) {
                if ($totals_delivery == $total_delivery) {
                    $status = 1;
                }
            }
        }

        return prepareResult(true, $status, [], "Delivery shipment.", $this->success);
    }

    public function invoiceSubmitted($salesman_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$salesman_id) {
            return prepareResult(false, [], ['error' => 'Salesman id is required.'], "Salesman id is required.", $this->unprocessableEntity);
        }

        $inv = Invoice::select('id', 'invoice_number', 'invoice_date', 'customer_id', 'order_id')
            ->with('user:id,firstname,lastname', 'customerInfoDetails:id,user_id,customer_code', 'order:id,order_number')
            ->where('salesman_id', $salesman_id)
            ->where('is_submitted', 0)
            ->get();

        return prepareResult(true, $inv, [], "Invoice submitted.", $this->success);
    }

    public function invoiceSubmittedPosting(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->invoice_ids)) {
            return prepareResult(false, [], [], "invoice id must be array", $this->unprocessableEntity);
        }

        Invoice::whereIn('id', $request->invoice_ids)
            ->update([
                'is_submitted' => 1
            ]);

        return prepareResult(true, [], [], "Invoice submitted updated.", $this->success);
    }
}
