<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomFieldValueSave;
use Illuminate\Http\Request;
use App\Model\Van;
use App\Imports\VanImport;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class VanController extends Controller
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

        if (!checkPermission('route-list')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$this->user->can('route-list') && $this->user->role_id != '1') {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $users_query = Van::with(
            'type:id,name',
            'category:id,name',
            'customFieldValueSave',
            'customFieldValueSave.customField'
        );
        if ($request->van_code) {
            $users_query->where('van_code', 'like', '%' . $request->van_code . '%');
        }
        if ($request->plate_number) {
            $users_query->where('plate_number',$request->plate_number);
        }
        if ($request->van_description) {
            $users_query->where('description', 'like', '%' . $request->van_description . '%');
        }
        if ($request->fuel_reading) {
            $users_query->where('reading',$request->fuel_reading);
        }

        if ($request->status) {
            $users_query->where('van_status',$request->status);
        }
       
        $van= $users_query->orderBy('id', 'desc')
            ->get();


        $van_array = array();
        if (is_object($van)) {
            foreach ($van as $key => $van1) {
                $van_array[] = $van[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($van_array[$offset])) {
                    $data_array[] = $van_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($van_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($van_array);
        } else {
            $data_array = $van_array;
        }

        return prepareResult(true, $data_array, [], "Van listing", $this->success, $pagination);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * @return [json] van object
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!checkPermission('region-save')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating van", $this->unprocessableEntity);
        }

        $van = new Van;
        $van->van_code          = nextComingNumber('App\Model\Van', 'van', 'van_code', $request->van_code);
        $van->plate_number      = $request->plate_number;
        $van->area_id           = $request->area_id;
        $van->description       = $request->description;
        $van->capacity          = $request->capacity;
        $van->reading           = $request->reading;
        $van->van_type_id       = $request->van_type_id;
        $van->van_category_id   = $request->van_category_id;
        $van->van_status        = $request->van_status;
        $van->save();

        if ($van) {
            updateNextComingNumber('App\Model\Van', 'van');
            if (is_array($request->modules) && sizeof($request->modules) >= 1) {
                foreach ($request->modules as $module) {
                    savecustomField($van->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
                }
            }
            return prepareResult(true, $van, [], "Van added successfully", $this->success);
        } else {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
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

        if (!checkPermission('region-update')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $van = Van::where('uuid', $uuid)
            ->first();

        if (!is_object($van)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating van", $this->unprocessableEntity);
        }

        // Create van object
        $van->area_id           = $request->area_id;
        $van->plate_number      = $request->plate_number;
        $van->description       = $request->description;
        $van->capacity          = $request->capacity;
        $van->reading           = $request->reading;
        $van->van_type_id       = $request->van_type_id;
        $van->van_category_id   = $request->van_category_id;
        $van->van_status        = $request->van_status;
        $van->save();

        if (is_array($request->modules) && sizeof($request->modules) >= 1) {
            CustomFieldValueSave::where('record_id', $van->id)->delete();
            foreach ($request->modules as $module) {
                savecustomField($van->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
            }
        }

        return prepareResult(true, $van, [], "Van updated successfully", $this->success);
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

        if (!checkPermission('region-edit')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating van", $this->unauthorized);
        }

        // Create region object
        $van = Van::where('uuid', $uuid)
            ->with(
                'type:id,name',
                'category:id,name',
                'customFieldValueSave',
                'customFieldValueSave.customField'
            )
            ->first();

        if (!is_object($van)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $van, [], "Region Edit", $this->success);
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

        if (!checkPermission('region-delete')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating van", $this->unauthorized);
        }

        $van = Van::where('uuid', $uuid)
            ->first();

        if (is_object($van)) {
            $van->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $action
     * @param  string  $status
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */

    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!checkPermission('region-bulk-action')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating region", $this->unprocessableEntity);
        }

        $action = $request->action;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            $uuids = $request->region_ids;
            foreach ($uuids as $uuid) {
                Van::where('uuid', $uuid)->update([
                    'van_status' => ($action == 'active') ? 1 : 0
                ]);
            }
            $region = $this->index();
            return prepareResult(true, $region, [], "Van status updated", $this->success);
        } else if ($action == 'delete') {
            $uuids = $request->region_ids;
            foreach ($uuids as $uuid) {
                Van::where('uuid', $uuid)->delete();
            }
            $region = $this->index();
            return prepareResult(true, $region, [], "Van deleted success", $this->success);
        } else if ($action == 'add') {
            $uuids = $request->region_ids;
            foreach ($uuids as $uuid) {
                $van = new Van;

                $van->van_code = nextComingNumber('App\Model\Van', 'van', 'van_code', $request->van_code);
                $van->plate_number = $uuid['plate_number'];
                $van->description = $uuid['description'];
                $van->capacity = $uuid['capacity'];
                $van->van_type_id = $uuid['van_type_id'];
                $van->van_category_id = $uuid['van_category_id'];
                $van->van_status = $uuid['van_status'];
                $van->save();

                updateNextComingNumber('App\Model\Van', 'van');
            }
            $region = $this->index();
            return prepareResult(true, $region, [], "Van added success", $this->success);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'plate_number'     => 'required',
                'description'     => 'required',
                'van_code'     => 'required',
                'van_type_id'     => 'required|integer|exists:van_types,id'
                // 'capacity'     => 'required',
                // 'van_category_id'     => 'required|integer|exists:van_categories,id'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'van_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate van import", $this->unauthorized);
        }

        Excel::import(new VanImport, request()->file('van_file'));
        return prepareResult(true, [], [], "Van successfully imported", $this->success);
    }

    public function getAssignedVehicle(){
        $orders = Order::orderBy('id','desc')->take(10)->get();
        $vans   = Van::orderBy('id','desc')->take(10)->get();
        $path = 'routific.json';
        $content = json_decode(file_get_contents($path), true);
        $request_headers = [
            'Content-Type:application/json',
            'Authorization:bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfaWQiOiI1MzEzZDZiYTNiMDBkMzA4MDA2ZTliOGEiLCJpYXQiOjEzOTM4MDkwODJ9.PR5qTHsqPogeIIe0NyH2oheaGR-SJXDsxPTcUQNq90E'
        ];
        $url = 'http://api.routific.com/v1/vrp';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($content));
        $response = curl_exec ($ch);
        $err = curl_error($ch);  
        curl_close ($ch);
        dd($response);
        return $response;
    }

    public function testGetAssignedVehicle(Request $request){
        $orders = Order::with('customerInfo')->where('delivery_date', $request->delivery_date)
            ->whereHas('customerInfo', function($query) use ($request){
                $query->whereIn('region_id', $request->region_id);
            })->get();

        $vans   = Van::whereIn('id', $request->vehicle_id)->whereIn('region', $request->region_id)->get();
        $visits = [];
        $fleet  = [];
        foreach ($orders as $key => $order) {
            $route = Route::where('id', $order->route_id)->first();
            $visits[$order->order_number]['location'] = [
                'name'=>$order->customer->firstname.' '.$order->customer->lastname,
                'lat'=>$order->customerInfo->customer_address_1_lat,
                'lng'=>$order->customerInfo->customer_address_1_lang
            ];
            $visits[$order->order_number]['load']     = $order->total_weight;
            $visits[$order->order_number]['duration'] = date('H:i', strtotime($order->customerInfo->total_window_time));
            $visits[$order->order_number]['start']    = date('H:i', strtotime($order->customerInfo->service_start_time));
            $visits[$order->order_number]['end']      = date('H:i', strtotime($order->customerInfo->service_end_time));
        }
        
        foreach ($vans as $key => $van) {
            $fleet[$van->plate_number]['start_location'] = [
                'id'   => $van->route->depot->depot_code ?? '',
                'name' => $van->route->depot->depot_name ?? '',
                'lat'  => $van->latitude,
                'lng'  => $van->longitude
            ];
            $fleet[$van->plate_number]['capacity'] = $van->capacity;
        }
        $array['visits'] = $visits;
        $array['fleet']  = $fleet;
        $request_headers = [
            'Content-Type:application/json',
            'Authorization:bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfaWQiOiI1MzEzZDZiYTNiMDBkMzA4MDA2ZTliOGEiLCJpYXQiOjEzOTM4MDkwODJ9.PR5qTHsqPogeIIe0NyH2oheaGR-SJXDsxPTcUQNq90E'
        ];
        $url = 'http://api.routific.com/v1/vrp';
        $ch  = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($array));
        $response = curl_exec ($ch);
        $err      = curl_error($ch);  
        curl_close ($ch);
        return $response;

    }
}
