<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\CustomFieldValueSave;
use Illuminate\Http\Request;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Imports\RouteImport;
use App\Model\CustomerRoute;
use App\Model\CustomerVisit;
use App\Model\CustomerWarehouseMapping;
use App\Model\SalesmanNumberRange;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class RoutesController extends Controller
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

        $route_query = Route::with(
            'areas:id,area_name,uuid',
            'depot:id,depot_name',
            'van',
            'salesmanNumberRange',
            'customFieldValueSave',
            'customFieldValueSave.customField'
        )
            ->orderBy('id', 'desc');

        if ($request->route_code) {
            $route_query->where('route_code', $request->route_code);
        }

        if ($request->route_name) {
            $route_query->where('route_name', 'like', '%' . $request->route_name . '%');
        }

        if ($request->area_name) {
            $area_name = $request->area_name;
            $route_query->whereHas('areas', function ($q) use ($area_name) {
                $q->where('route_name', 'like', '%' . $area_name . '%');
            });
        }

        if ($request->depot_name) {
            $depot_name = $request->depot_name;
            $route_query->whereHas('depot', function ($q) use ($depot_name) {
                $q->where('depot_name', 'like', '%' . $depot_name . '%');
            });
        }

        // $route = $route_query->get();
        $all_route = $route_query->paginate($request->page_size);
        $route = $all_route->items();

        $pagination = array();
        $pagination['total_pages'] = $all_route->lastPage();
        $pagination['current_page'] = (int)$all_route->perPage();
        $pagination['total_records'] = $all_route->total();

        return prepareResult(true, $route, [], "Route listing", $this->success, $pagination);
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

        if (!checkPermission('route-save')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Route", $this->unprocessableEntity);
        }

        $exist = Route::where('route_code', $request->route_code)->first();
        if (is_object($exist)) {
            return prepareResult(false, [], 'Route Code is already added.', "Error while validating route", $this->unprocessableEntity);
        }

        $route = new Route;
        $route->area_id     = $request->area_id;
        $route->depot_id    = $request->depot_id;
        $route->route_code  = nextComingNumber('App\Model\Route', 'route', 'route_code', $request->route_code);
        $route->route_name  = $request->route_name;
        $route->status      = $request->status;
        $route->van_id      = $request->van_id;
        $route->save();

        if (config('app.current_domain') != "merchandising") {
            $salesman_number_range = new SalesmanNumberRange();
            $salesman_number_range->route_id            = $route->id;
            $salesman_number_range->customer_from       = $request->customer_from;
            $salesman_number_range->customer_to         = $request->customer_to;
            $salesman_number_range->order_from          = $request->order_from;
            $salesman_number_range->order_to            = $request->order_to;
            $salesman_number_range->invoice_from        = $request->invoice_from;
            $salesman_number_range->invoice_to          = $request->invoice_to;
            $salesman_number_range->collection_from     = $request->collection_from;
            $salesman_number_range->collection_to       = $request->collection_to;
            $salesman_number_range->credit_note_from    = $request->credit_note_from;
            $salesman_number_range->credit_note_to      = $request->credit_note_to;
            $salesman_number_range->unload_from         = $request->unload_from;
            $salesman_number_range->unload_to           = $request->unload_to;
            $salesman_number_range->exchange_from       = "100000";
            $salesman_number_range->exchange_to         = "999999";
            $salesman_number_range->save();
        }

        if ($route) {
            updateNextComingNumber('App\Model\Route', 'route');
            if (is_array($request->modules) && sizeof($request->modules) >= 1) {
                foreach ($request->modules as $module) {
                    savecustomField($route->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
                }
            }

            $route->getSaveData();
            // cache()->forget($this->og_id . 'route');
            return prepareResult(true, $route, [], "Route added successfully", $this->success);
        } else {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
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

        if (!checkPermission('route-edit')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating route", $this->unauthorized);
        }

        $route = Route::where('uuid', $uuid)
            ->with(
                'areas:id,area_name,uuid',
                'depot:id,depot_name',
                'van',
                'salesmanNumberRange',
                'customFieldValueSave',
                'customFieldValueSave.customField'
            )
            ->first();

        if (!is_object($route)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $route, [], "Route Edit", $this->success);
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

        if (!checkPermission('route-update')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating route", $this->unprocessableEntity);
        }

        $route = Route::where('uuid', $uuid)
            ->first();

        if (!is_object($route)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        $route->area_id = $request->area_id;
        $route->depot_id = $request->depot_id;
        $route->route_name = $request->route_name;
        $route->status = $request->status;
        $route->van_id = $request->van_id;
        $route->save();

        if (config('app.current_domain') != "merchandising") {
            $salesman_number_range = SalesmanNumberRange::where('route_id', $route->id)->first();
            $salesman_number_range->route_id            = $route->id;
            $salesman_number_range->customer_from       = $request->customer_from;
            $salesman_number_range->customer_to         = $request->customer_to;
            $salesman_number_range->order_from          = $request->order_from;
            $salesman_number_range->order_to            = $request->order_to;
            $salesman_number_range->invoice_from        = $request->invoice_from;
            $salesman_number_range->invoice_to          = $request->invoice_to;
            $salesman_number_range->collection_from     = $request->collection_from;
            $salesman_number_range->collection_to       = $request->collection_to;
            $salesman_number_range->credit_note_from    = $request->credit_note_from;
            $salesman_number_range->credit_note_to      = $request->credit_note_to;
            $salesman_number_range->unload_from         = $request->unload_from;
            $salesman_number_range->unload_to           = $request->unload_to;
            $salesman_number_range->exchange_from       = "100000";
            $salesman_number_range->exchange_to         = "999999";
            $salesman_number_range->save();
        }

        if (is_array($request->modules) && sizeof($request->modules) >= 1) {
            CustomFieldValueSave::where('record_id', $route->id)->delete();
            foreach ($request->modules as $module) {
                savecustomField($route->id, $module['module_id'], $module['custom_field_id'], $module['custom_field_value']);
            }
        }

        return prepareResult(true, $route, [], "Route updated successfully", $this->success);
    }

    /**
     * customer of the specified route.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function routeCustomers(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "allCustomer");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating route customer", $this->unprocessableEntity);
        }

        $route_id = $request->route_id;

        $customer_routes = CustomerRoute::where('route_id', $route_id)->get();

        $customer_ids = array();
        if (count($customer_routes)) {
            $customer_ids = $customer_routes->pluck('customer_id')->toArray();
        }

        $customers = CustomerInfo::with(
            'user:id,usertype,firstname,lastname,email,mobile,role_id,country_id,status,parent_id',
            'user_country',
            'customerRoute',
            'customerRoute.route:id,route_code,route_name,status,depot_id',
            'customerRoute.route.depot:id,depot_code,depot_name',
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
            ->whereIn('id', $customer_ids)
            ->orderBy('id', 'desc')
            ->get();

        // return prepareResult(true, $customers, [], "Route customer listed successfully", $this->success);

        $customers_array = array();
        if (is_object($customers)) {
            foreach ($customers as $key => $customer) {

                $customer_visit = CustomerVisit::where('customer_id', $customer->user_id)
                    ->where('salesman_id', $customer->merchandiser_id)
                    ->orderBy('added_on', 'DESC')
                    ->first();

                if (is_object($customer_visit)) {
                    $customers[$key]->last_visit = $customer_visit->added_on;
                } else {
                    $customers[$key]->last_visit = '';
                }

                if (count($customer->customerlob)) {
                    foreach ($customer->customerlob as $k => $cl) {
                        $cwmp = CustomerWarehouseMapping::with('storageocation:id,name,code')
                            ->where('customer_id', $customer->user_id)
                            ->where('lob_id', $cl->lob_id)
                            ->get();
                        $customers[$key]->customerlob[$k]->customer_warehouse_mapping = $cwmp;
                    }
                }

                $customers_array[] = $customers[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($customers_array[$offset])) {
                    $data_array[] = $customers_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($customers_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($customers_array);
        } else {
            $data_array = $customers_array;
        }

        return prepareResult(true, $data_array, [], "Route customer listed successfully", $this->success, $pagination);
    }

    /**
     * customer of the specified route.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function multipleRouteCustomers(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        if (is_array($request->route_id) && sizeof($request->route_id) < 1) {
            return prepareResult(false, [], 'Please add routes', "Error while validating route customer", $this->unprocessableEntity);
        }


        $route_id = $request->route_id;

        $customers = CustomerInfo::select('id', 'customer_code', 'user_id')
            ->with(
                'user:id,usertype,firstname,lastname',
                'customerlob',
                'customerlob.customerRoute'
            )
            ->whereHas('customerRoute', function ($q) use ($route_id) {
                $q->whereIn('route_id', $route_id);
            })
            ->orWhereHas('customerlob.customerRoute', function ($q) use ($route_id) {
                $q->whereIn('route_id', $route_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $customers, [], "Route customer listed successfully", $this->success);

        $customers_array = array();
        if (is_object($customers)) {
            foreach ($customers as $key => $customer) {

                $customer_visit = CustomerVisit::where('customer_id', $customer->user_id)
                    ->where('salesman_id', $customer->merchandiser_id)
                    ->orderBy('added_on', 'DESC')
                    ->first();

                if (is_object($customer_visit)) {
                    $customers[$key]->last_visit = $customer_visit->added_on;
                } else {
                    $customers[$key]->last_visit = '';
                }
                $customers_array[] = $customers[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($customers_array[$offset])) {
                    $data_array[] = $customers_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($customers_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($customers_array);
        } else {
            $data_array = $customers_array;
        }

        return prepareResult(true, $data_array, [], "Route customer listed successfully", $this->success, $pagination);
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

        if (!checkPermission('route-delete')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating area", $this->unauthorized);
        }

        $route = Route::where('uuid', $uuid)
            ->first();

        if (is_object($route)) {
            $route->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    public function depotRoutes($depot_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!checkPermission('route-bulk-action')) {
            return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$depot_id) {
            return prepareResult(false, [], [], "Error while validating depots", $this->unauthorized);
        }

        $depot = Route::select('id', 'route_code', 'depot_id', 'route_name')
            ->where('depot_id', $depot_id)
            ->orderBy('id', 'desc')
            ->get();

        if (is_object($depot)) {
            return prepareResult(true, $depot, [], "Route depot listed successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    public function routeSalesman($route_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$route_id) {
            return prepareResult(false, [], [], "Error while validating salesman", $this->unauthorized);
        }

        $salesman_info = SalesmanInfo::with('user:id,firstname,lastname')
            ->select('id', 'route_id', 'user_id', 'salesman_code')
            ->where('route_id', $route_id)
            ->orderBy('id', 'desc')
            ->get();

        if (is_object($salesman_info)) {
            return prepareResult(true, $salesman_info, [], "Route Salesman listed successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'area_id' => 'required|integer|exists:areas,id',
                'depot_id' => 'required|integer|exists:depots,id',
                'route_name' => 'required',
                'route_code' => 'required',
                'status' => 'required',
                'van_id' => 'required|not_in:0',

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
            'route_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate route import", $this->unauthorized);
        }

        Excel::import(new RouteImport, request()->file('route_file'));
        return prepareResult(true, [], [], "Route successfully imported", $this->success);
    }

    public function routeSupervisor($route_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$route_id) {
            return prepareResult(false, [], [], "Error while validating route supervisor", $this->unauthorized);
        }

        $salesman_info = SalesmanInfo::select('id', 'route_id', 'user_id', 'salesman_supervisor')
            ->with(
                'route:id,route_name',
                'salesmanSupervisor:id,firstname,lastname',
            )
            ->where('route_id', $route_id)
            ->orderBy('id', 'desc')
            ->groupBy('salesman_supervisor')
            ->get();

        foreach ($salesman_info as $key => $salesman) {
            $salesmanInfo = SalesmanInfo::with('user:id,firstname,lastname')
                ->where('salesman_supervisor', $salesman->salesman_supervisor)
                ->get();

            $salesman_info[$key]->salesmans = $salesmanInfo;
        }

        if (is_object($salesman_info)) {
            return prepareResult(true, $salesman_info, [], "Route Salesman listed successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }
}
