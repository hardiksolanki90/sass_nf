<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\ConsolidateLoadReturnReport;
use App\Model\Goodreceiptnote;
use App\Model\Goodreceiptnotedetail;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
use App\Model\Item;
use App\Model\LoadItem;
use App\Model\ItemMainPrice;
use App\Model\ReturnView;
use App\Model\Route;
use App\Model\SalesmanTripInfos;
use App\Model\SalesmanUnload;
use App\Model\SalesmanUnloadDetail;
use App\Model\Storagelocation;
use App\Model\StoragelocationDetail;
use App\Model\Warehouse;
use App\Model\WorkFlowObject;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesmanUnloadController extends Controller
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

        $UnloadHeader_query = SalesmanUnload::with(
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'route:id,route_code,route_name',
            'salesmanUnloadDetail',
            'salesmanUnloadDetail.item:id,item_name,item_code',
            'salesmanUnloadDetail.itemUom:id,name',
            'salesmanUnloadDetail.reason:id,name,type,code',
            'salesmanUnloadDetail.storageocation:id,name,code',
            'salesmanUnloadDetail.van:id,van_code,plate_number,capacity',
            'salesmanUnloadDetail.route:id,route_code,route_name'
        );

        if ($request->date) {
            $UnloadHeader_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->code) {
            $UnloadHeader_query->where('code', 'like', '%' . $request->code . '%');
        }

        if ($request->trip) {
            $UnloadHeader_query->where('trip_id', 'like', '%' . $request->code . '%');
        }

        if ($request->load_type) {
            $UnloadHeader_query->where('load_type', 'like', '%' . $request->load_type . '%');
        }

        if ($request->salesman_name) {
            $name = $request->salesman_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $UnloadHeader_query->whereHas('salesman', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $UnloadHeader_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $UnloadHeader_query->whereHas('salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', "%$salesman_code%");
            });
        }

        if ($request->route) {
            $route = $request->route;
            $UnloadHeader_query->whereHas('route', function ($q) use ($route) {
                $q->where('route_name', 'like', "%$route%");
            });
        }

        if ($request->rout_code) {
            $route_code = $request->rout_code;
            $UnloadHeader_query->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', $route_code);
            });
        }

        // $UnloadHeader = $UnloadHeader_query->orderBy('id', 'desc')
        //     ->get();

        $all_UnloadHeader = $UnloadHeader_query->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $UnloadHeader = $all_UnloadHeader->items();

        $pagination = array();
        $pagination['total_pages'] = $all_UnloadHeader->lastPage();
        $pagination['current_page'] = (int)$all_UnloadHeader->perPage();
        $pagination['total_records'] = $all_UnloadHeader->total();

        $results = GetWorkFlowRuleObject('SalesmanUnload', $all_UnloadHeader->pluck('id')->toArray());
        $approve_need_order = array();
        $approve_need_order_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_order[] = $raw['object']->raw_id;
                $approve_need_order_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $UnloadHeader_array = array();
        if (count($UnloadHeader)) {
            foreach ($UnloadHeader as $key => $order1) {
                if (in_array($UnloadHeader[$key]->id, $approve_need_order)) {
                    $UnloadHeader[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_order_object_id[$UnloadHeader[$key]->id])) {
                        $UnloadHeader[$key]->objectid = $approve_need_order_object_id[$UnloadHeader[$key]->id];
                    } else {
                        $UnloadHeader[$key]->objectid = '';
                    }
                } else {
                    $UnloadHeader[$key]->need_to_approve = 'no';
                    $UnloadHeader[$key]->objectid = '';
                }

                if ($UnloadHeader[$key]->current_stage == 'Approved' || $UnloadHeader[$key]->current_stage == 'Cancelled' || request()->user()->usertype == 1 || in_array($UnloadHeader[$key]->id, $approve_need_order)) {
                    $UnloadHeader_array[] = $UnloadHeader[$key];
                }
            }
        }

        return prepareResult(true, $UnloadHeader_array, [], "Salesman Unload listing", $this->success, $pagination);

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($UnloadHeader_array[$offset])) {
                    $data_array[] = $UnloadHeader_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($UnloadHeader_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($UnloadHeader_array);
        } else {
            $data_array = $UnloadHeader_array;
        }

        return prepareResult(true, $data_array, [], "Salesman Unload listing", $this->success, $pagination);
    }

    public function unloadlist(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $input = $request->json()->all();

        $validate = $this->validations($input, "unloadlist");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating load header", $this->unprocessableEntity);
        }
        //$route_id = $request->route_id;
        $unloadtype = $request->unload_type;
        $filter = $request->filter;
        if ($request->filter == 'all') {
        }

        $unloadheaders = SalesmanUnload::with(
            'trip',
            'route:id,route_code,route_name',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code'
        );
        //        if ($request->filter != 'all') {
        //            $unloadheaders->whereBetween('transaction_date', [$start_date, $end_date]);
        //        }

        if ($request->trip) {
            $unloadheaders->where('trip_id', $request->trip);
        }

        if ($request->date) {
            $unloadheaders->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->code) {
            $unloadheaders->where('code', 'like', '%' . $request->code . '%');
        }

        if ($request->erp_status) {

            if ($request->erp_status == "Not Posted") {
                $unloadheaders->where('is_sync', 0)
                    ->whereNull('erp_id');
            }

            if ($request->erp_status == "Failed") {
                $unloadheaders->whereNotNull('erp_id')
                    ->where('is_sync', 0);
            }

            if ($request->erp_status == "Posted") {
                $unloadheaders->where('is_sync', 1)
                    ->where('erp_status', '!=', "Cancelled");
            }
        }

        if (!empty($request->load_type)) {
            $unloadheaders->where('unload_type', 'like', '%' . $request->load_type . '%');
        }

        if ($request->salesman_name) {
            $name = $request->salesman_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $unloadheaders->whereHas('salesman', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $unloadheaders->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $unloadheaders->whereHas('salesman.salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', $salesman_code);
            });
        }

        if ($request->rout_code) {
            $route_code = $request->rout_code;
            $unloadheaders->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', $route_code);
            });
        }

        if ($request->route) {
            $route = $request->route;
            $unloadheaders->whereHas('route', function ($q) use ($route) {
                $q->where('route_name', 'like', $route);
            });
        }

        $unloadheaders->orderBy('id', 'desc');
        $unloadheader = $unloadheaders->get();

        foreach ($unloadheader as $key => $trids) {
            // salesman_load_details is salesman_unload_details
            $unloadheader[$key]->salesman_unload_details = DB::table('salesman_unload_details')
                ->join('salesman_unloads', 'salesman_unloads.id', '=', 'salesman_unload_details.salesman_unload_id')
                ->join('items', 'items.id', '=', 'salesman_unload_details.item_id', 'left')
                ->join('item_uoms', 'item_uoms.id', '=', 'items.lower_unit_uom_id', 'left')
                ->join('trips', 'trips.id', '=', 'salesman_unloads.trip_id', 'left')
                ->join('users', 'trips.salesman_id', '=', 'users.id', 'left')
                ->join('routes', 'routes.id', '=', 'trips.route_id', 'left')
                ->join('salesman_infos', 'salesman_infos.id', '=', 'trips.salesman_id', 'left')
                ->select('salesman_unload_details.id', 'salesman_unload_details.uuid', 'salesman_unload_details.salesman_unload_id', 'salesman_unload_details.unload_type', 'trips.route_id as route_id', 'routes.route_code as route_code', 'routes.route_name as route_name', 'trips.salesman_id as salesman_id', 'salesman_infos.salesman_code as salesman_code', 'users.firstname as salesman_name', 'salesman_unloads.id as depo_code', 'salesman_unloads.transaction_date as load_date', 'salesman_unload_details.item_id', 'items.item_name', 'items.item_code', 'item_uoms.id as item_uom', 'item_uoms.name as item_uom_name', 'item_uoms.code as item_uom_code', 'salesman_unload_details.unload_qty as load_qty', 'salesman_unload_details.status as status', 'salesman_unload_details.created_at as created_at', 'salesman_unload_details.updated_at as updated_at', 'salesman_unload_details.deleted_at as deleted_at', 'salesman_unload_details.reason')
                ->where('salesman_unload_details.salesman_unload_id', $trids->id)
                ->get();

            // foreach ($unloadheader[$key]->salesman_unload_details as $k => $salesunloaddetail) {
            //     $items = Item::where('id', $salesunloaddetail->item_id)
            //         ->where('lower_unit_uom_id', $salesunloaddetail->item_uom)
            //         ->first();
            // }
        }

        // $data = array('UnloadData' => $unloadheader);
        $unload = $unloadheader;

        $unload_array = array();
        if (is_object($unload)) {
            foreach ($unload as $key => $unload1) {
                $unload_array[] = $unload[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($unload_array[$offset])) {
                    $data_array[] = $unload_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($unload_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($unload_array);
        } else {
            $data_array = $unload_array;
        }

        return prepareResult(true, $data_array, [], "Unload header listing", $this->success, $pagination);

        // return prepareResult(true, $data, [], "Unload header listing", $this->success);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating load header", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {

            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
            }

            if ($request->unload_type != 2) {

                // $repeat_number = codeCheck('SalesmanUnload', 'code', $request->code);
                // if (is_object($repeat_number)) {
                //     return prepareResult(false, [], [], "This salesman unload is already added.", $this->success);
                // }

                $repeat_number = codeCheck('SalesmanUnload', 'code', $request->code, 'transaction_date');
                if (is_object($repeat_number)) {
                    return prepareResult(true, $repeat_number, [], 'Record saved', $this->success);
                } else {
                    $repeat_number = codeCheck('SalesmanUnload', 'code', $request->code);
                    if (is_object($repeat_number)) {
                        return prepareResult(false, [], ["error" => "This unload number " . $request->code . " is already added."], "This unload number is already added.", $this->success);
                    }
                }

                $unloadheader = new SalesmanUnload;
                $unloadheader->trip_id      = (!empty($request->trip_id)) ? $request->trip_id : null;
                $unloadheader->code         = $request->code;
                $unloadheader->unload_type  = (!empty($request->unload_type)) ? $request->unload_type : null;
                $unloadheader->route_id     = (!empty($request->route_id)) ? $request->route_id : null;
                $unloadheader->van_id       = (!empty($request->van_id)) ? $request->van_id : null;
                $unloadheader->warehouse_id = (!empty($request->warehouse_id)) ? $request->warehouse_id : null;
                $unloadheader->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : null;
                $unloadheader->salesman_id  = (!empty($request->salesman_id)) ? $request->salesman_id : null;
                $unloadheader->transaction_date = date('Y-m-d', strtotime($request->transaction_date));
                $unloadheader->source       = 1;
                $unloadheader->status       = 1;
                $unloadheader->current_stage = $current_stage;
                $unloadheader->save();

                if ($isActivate = checkWorkFlowRule('SalesmanUnload', 'create', $current_organisation_id)) {
                    $this->createWorkFlowObject($isActivate, 'SalesmanUnload', $request, $unloadheader);
                }

                $salesman_info = new SalesmanTripInfos;
                $salesman_info->trips_id = $unloadheader->trip_id;
                $salesman_info->salesman_id = $unloadheader->salesman_id;
                $salesman_info->status = 4;
                $salesman_info->save();
            }
            //--------------------
            if ($request->unload_type == '2') {
                $routelocation = Storagelocation::where('route_id', $request->route_id)
                    ->where('loc_type', 2)
                    ->first();

                $route = Route::find($request->route_id);

                $warehouse = Warehouse::where('depot_id', $route->depot_id)
                    ->whereNull('route_id')
                    ->first();

                $warehouselocation = Storagelocation::where('warehouse_id', $warehouse->id)
                    ->where('loc_type', 1)
                    ->whereNull('route_id')
                    ->first();

                $goodreceiptnote = new Goodreceiptnote;
                $goodreceiptnote->salesman_id           = (!empty($request->salesman_id)) ? $request->salesman_id : null;
                $goodreceiptnote->route_id              = (!empty($request->route_id)) ? $request->route_id : null;
                $goodreceiptnote->trip_id               = (!empty($request->trip_id)) ? $request->trip_id : null;
                $goodreceiptnote->source_warehouse      = (!empty($routelocation)) ? $routelocation->id : null;
                $goodreceiptnote->destination_warehouse = $warehouselocation->id;
                $goodreceiptnote->grn_number            = $request->code;
                $goodreceiptnote->grn_date              = date('Y-m-d', strtotime($request->transaction_date));
                $goodreceiptnote->is_damaged            = (!empty($request->is_damaged)) ? $request->is_damaged : 0;
                $goodreceiptnote->current_stage         = $current_stage;
                $goodreceiptnote->save();

                if ($isActivate = checkWorkFlowRule('GRN', 'create', $current_organisation_id)) {
                    $this->createWorkFlowObject($isActivate, 'Goodreceiptnote', $request, $goodreceiptnote);
                }
            }

            if (is_array($request->items)) {
                foreach ($request->items as $key => $item) {
                    if ($request->unload_type == 2) {

                        $goodreceiptnotedetail = new Goodreceiptnotedetail;
                        $goodreceiptnotedetail->good_receipt_note_id    = $goodreceiptnote->id;
                        $goodreceiptnotedetail->item_id                 = $item['item_id'];
                        $goodreceiptnotedetail->item_uom_id             = $item['item_uom'];
                        $goodreceiptnotedetail->qty                     = $item['unload_qty'];
                        $goodreceiptnotedetail->original_item_qty       = $item['unload_qty'];
                        $goodreceiptnotedetail->reason                  = (!empty($item['reason'])) ? $item['reason'] : null;
                        $goodreceiptnotedetail->save();
                    } elseif ($request->unload_type == 4) {

                        // $transactionheaderde = TransactionDetail::with(
                        //     'Transaction',
                        //     'Transaction.trip',
                        //     'Transaction.trip.users'
                        // )
                        //     ->where('item_id', $item['item_id'])
                        //     ->whereHas('Transaction.trip', function ($query) use ($request) {
                        //         return $query->where('id', '=', $request->trip_id);
                        //     })
                        //     ->first();
                        $reults = getItemDetails2($item['item_id'], $item['item_uom'], $item['unload_qty']);

                        $unloaddetail = new SalesmanUnloadDetail;
                        $unloaddetail->salesman_unload_id   = $unloadheader->id;
                        $unloaddetail->item_id              = $item['item_id'];
                        $unloaddetail->item_uom             = $item['item_uom'];
                        $unloaddetail->unload_qty           = $item['unload_qty'];
                        $unloaddetail->original_item_qty    = $item['unload_qty'];
                        $unloaddetail->unload_date          = $item['unload_date'];
                        $unloaddetail->unload_type          = $item['unload_type'];
                        $unloaddetail->van_id               = (!empty($request->van_id)) ? $request->van_id : null;
                        $unloaddetail->warehouse_id         = (!empty($request->warehouse_id)) ? $request->warehouse_id : null;
                        $unloaddetail->storage_location_id  = (!empty($request->storage_location_id)) ? $request->storage_location_id : null;
                        $unloaddetail->reason               = (!empty($item['reason'])) ? $item['reason'] : null;
                        $unloaddetail->save();

                        //Warehouse Start
                        if ($request->unload_type == 4 && $item['unload_type'] == 1) {
                            $routes = Route::find($request->route_id);
                            if (is_object($routes)) {
                                $depot_id = $routes->depot_id;

                                $Warehouse = Warehouse::where('depot_id', $depot_id)
                                    ->first();

                                if (is_object($Warehouse)) {
                                    $warehouselocation = Storagelocation::where('warehouse_id', $Warehouse->id)
                                        ->where('loc_type', '1')
                                        ->first();

                                    if (is_object($warehouselocation)) {
                                        $routelocation = Storagelocation::where('route_id', $request->route_id)
                                            ->where('loc_type', '1')
                                            ->first();

                                        if (is_object($routelocation)) {
                                            $routestoragelocation_id = $routelocation->id;
                                            $warehousestoragelocation_id = $warehouselocation->id;
                                            $routelocation_detail = StoragelocationDetail::where('storage_location_id', $routestoragelocation_id)
                                                ->where('item_id', $item['item_id'])
                                                ->first();

                                            $warehouselocation_detail = StoragelocationDetail::where('storage_location_id', $warehousestoragelocation_id)
                                                ->where('item_id', $item['item_id'])
                                                ->first();

                                            if (is_object($warehouselocation_detail)) {
                                                $warehouselocation_detail->qty = ($warehouselocation_detail->qty + $reults['Qty']);
                                                $warehouselocation_detail->save();
                                            } else {
                                                $storagewarehousedetail = new StoragelocationDetail;
                                                $storagewarehousedetail->storage_location_id = $warehouselocation->id;
                                                $storagewarehousedetail->item_id            = $item['item_id'];
                                                $storagewarehousedetail->item_uom_id        = $reults['UOM'];
                                                $storagewarehousedetail->qty                = $reults['Qty'];
                                                $storagewarehousedetail->original_item_qty  = $reults['Qty'];
                                                $storagewarehousedetail->save();
                                            }
                                            if (is_object($routelocation_detail)) {

                                                $routelocation_detail->qty = ($routelocation_detail->qty - $reults['Qty']);
                                                $routelocation_detail->save();
                                            } else {

                                                $routestoragedetail = new StoragelocationDetail;
                                                $routestoragedetail->storage_location_id = $routelocation->id;
                                                $routestoragedetail->item_id = $item['item_id'];
                                                $routestoragedetail->item_uom_id = $reults['UOM'];
                                                $routestoragedetail->qty = $reults['Qty'];
                                                $routestoragedetail->original_item_qty  = $reults['Qty'];
                                                $routestoragedetail->save();
                                            }
                                        } else {
                                            return prepareResult(false, [], ["error" => "Route Location not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                        }
                                    } else {

                                        return prepareResult(false, [], ["error" => "Wherehouse Location not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                    }
                                } else {
                                    return prepareResult(false, [], ["error" => "Wherehouse not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                }
                            }
                        }
                    } elseif ($request->unload_type == 5) {

                        $items = Item::where('id', $item['item_id'])
                            ->where('lower_unit_uom_id', $item['item_uom'])
                            ->first();

                        if (is_object($items)) {
                            $price = $items->lower_unit_item_price;
                            $itemprice = $item['unload_qty'] * $price;
                            $vat = $itemprice * 0.05;
                            $total = $itemprice + $vat;
                        } else {

                            $ItemMainPrice_result = ItemMainPrice::where('item_id', $item['item_id'])
                                ->where('item_uom_id', $item['item_uom'])
                                ->first();

                            $price = $ItemMainPrice_result->item_price;
                            $itemprice = $item['unload_qty'] * $price;
                            $vat = $itemprice * 0.05;
                            $total = $itemprice + $vat;
                        }

                        $invoice = Invoice::where('invoice_number', $request->code)
                            ->first();

                        if (is_object($invoice)) {
                            $invoiceId = $invoice->id;
                            $invoiceDetail = new InvoiceDetail;

                            $invoiceDetail->invoice_id = $invoiceId;
                            $invoiceDetail->item_id = $item['item_id'];
                            $invoiceDetail->item_uom_id = $item['item_uom'];
                            $invoiceDetail->item_qty = $item['unload_qty'];
                            $invoiceDetail->item_price = $price;
                            $invoiceDetail->item_gross = $itemprice;
                            $invoiceDetail->item_net = $itemprice;
                            $invoiceDetail->item_vat = $vat;
                            $invoiceDetail->item_grand_total = $total;
                            $invoiceDetail->save();
                        } else {
                            //-----------------------------------------Start unload details--------------

                            $unloaddetail = new SalesmanUnloadDetail;
                            $unloaddetail->salesman_unload_id = $unloadheader->id;
                            $unloaddetail->item_id = $item['item_id'];
                            $unloaddetail->item_uom = $item['item_uom'];
                            $unloaddetail->unload_qty = $item['unload_qty'];
                            $unloaddetail->original_item_qty = $item['unload_qty'];
                            $unloaddetail->unload_date = $item['unload_date'];
                            $unloaddetail->unload_type = $item['unload_type'];
                            $unloaddetail->reason = (!empty($item['reason'])) ? $item['reason'] : null;
                            $unloaddetail->van_id = (!empty($request->van_id)) ? $request->van_id : null;
                            $unloaddetail->warehouse_id = (!empty($request->warehouse_id)) ? $request->warehouse_id : null;
                            $unloaddetail->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : null;
                            $unloaddetail->save();

                            //----------------------End unload details--------------

                            //----------------------Start Invoice Header
                            $invoice = new Invoice;
                            $invoice->customer_id = $request->customer_id;
                            $invoice->trip_id = $request->trip_id;
                            $invoice->salesman_id = $request->salesman_id;
                            $invoice->invoice_type = 4;
                            $invoice->invoice_number = $request->code;
                            $invoice->invoice_date = date('Y-m-d', strtotime($request->invoice_date));
                            $invoice->payment_term_id = 1;
                            $invoice->invoice_due_date = date('Y-m-d', strtotime($request->invoice_date));
                            $invoice->total_gross = $invoice->total_gross + $itemprice;
                            $invoice->total_net = $invoice->total_net + $itemprice;
                            $invoice->total_vat = $invoice->total_vat + $vat;
                            $invoice->grand_total = $request->grand_total + $total;
                            $invoice->pending_credit = $invoice->grand_total;
                            $invoice->current_stage = 'Pending';
                            $invoice->source = $request->source;
                            $invoice->status = 0;
                            $invoice->save();
                            //----------------------End Invoice Header
                            //-----------------------------------------Start Invoice Details--------------
                            $invoiceDetail = new InvoiceDetail;
                            $invoiceDetail->invoice_id = $invoice->id;
                            $invoiceDetail->item_id = $item['item_id'];
                            $invoiceDetail->item_uom_id = $item['item_uom'];
                            $invoiceDetail->item_qty = $item['unload_qty'];
                            $invoiceDetail->item_price = $price;
                            $invoiceDetail->item_gross = $itemprice;
                            $invoiceDetail->item_net = $itemprice;
                            $invoiceDetail->item_vat = $vat;
                            $invoiceDetail->item_grand_total = $total;
                            $invoiceDetail->save();
                        }
                        //-----------------------------------------End Invoice Details--------------
                    } else if ($request->unload_type == 1) {
                        $unloaddetail = new SalesmanUnloadDetail;
                        $unloaddetail->salesman_unload_id   = $unloadheader->id;
                        $unloaddetail->item_id              = $item['item_id'];
                        $unloaddetail->item_uom             = $item['item_uom'];
                        $unloaddetail->unload_qty           = $item['unload_qty'];
                        $unloaddetail->original_item_qty    = $item['unload_qty'];
                        $unloaddetail->unload_date          = $item['unload_date'];
                        $unloaddetail->unload_type          = $item['unload_type'];
                        $unloaddetail->van_id               = (!empty($item['van_id'])) ? $item['van_id'] : null;
                        $unloaddetail->route_id             = (!empty($item['van_id'])) ? getRouteByVan($item['van_id']) : null;
                        $unloaddetail->storage_location_id  = (!empty($item['storage_location_id'])) ? $item['storage_location_id'] : null;
                        $unloaddetail->warehouse_id         = (!empty($item['storage_location_id'])) ? getWarehuseBasedOnStorageLoacation($item['storage_location_id'], false) : null;
                        $unloaddetail->reason               = (!empty($item['reason'])) ? $item['reason'] : null;
                        $unloaddetail->save();

                        $count = $key + 1;

                        $this->consolidateLoadReturnReportEntry($count, $unloaddetail, $unloadheader);
                        // for the rfGen
                        // $this->returnViweEntry($unloaddetail, $unloadheader);

                        $this->saveUnloadLoadItem($unloaddetail, $unloadheader);
                    }
                }
            }

            // if mobile order
            if (isset($request->source) && $request->source == 1) {
                $user = User::find($request->user()->id);
                if (is_object($user)) {
                    $salesmanInfo = $user->salesmanInfo;
                    if ($salesmanInfo) {
                        updateMobileNumberRange($salesmanInfo, 'unload_from', $request->code);
                    }
                }
            }

            \DB::commit();

            // $unloadheader->getSaveData();
            updateNextComingNumber('App\Model\SalesmanUnload', 'unload_number');

            return prepareResult(true, $unloadheader, [], "Salesman Unload added successfully", $this->created);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating unload header.", $this->unauthorized);
        }
        $UnloadHeader = SalesmanUnload::with(
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'salesmanUnloadDetail',
            'salesmanUnloadDetail.item:id,item_name,item_code',
            'salesmanUnloadDetail.itemUom:id,name',
            'salesmanUnloadDetail.reason:id,name,type,code',
            'route:id,depot_id,route_code,route_name',
            'route.depot:id,depot_code,depot_name',
            'trip:id,trip_start,trip_start_date,trip_start_time,trip_end,trip_end_date,trip_end_time,trip_status',
        )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($UnloadHeader)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $UnloadHeader, [], "Salesman Unload Edit", $this->success);
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
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating load header.", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $unloadheader = SalesmanUnload::where('uuid', $uuid)->first();

            SalesmanUnloadDetail::where('salesman_unload_id', $unloadheader->id)->delete();

            $unloadheader->trip_id          = (!empty($request->trip_id)) ? $request->trip_id : null;
            $unloadheader->unload_type      = (!empty($request->unload_type)) ? $request->unload_type : null;
            $unloadheader->code             = (!empty($request->code)) ? $request->code : null;
            $unloadheader->route_id         = (!empty($request->route_id)) ? $request->route_id : null;
            $unloadheader->van_id           = (!empty($request->van_id)) ? $request->van_id : null;
            $unloadheader->warehouse_id     = (!empty($request->warehouse_id)) ? $request->warehouse_id : null;
            $unloadheader->storage_location_id  = (!empty($request->storage_location_id)) ? $request->storage_location_id : null;
            $unloadheader->salesman_id      = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $unloadheader->transaction_date = date('Y-m-d', strtotime($request->transaction_date));
            $unloadheader->save();

            if (is_array($request->items)) {
                foreach ($request->items as $item) {

                    if (is_object($item['item_uom'])) {
                        $item_uom_id = $item['item_uom']->id;
                    } else {
                        $item_uom_id = $item['item_uom'];
                        if (!empty($item_uom_id)) {
                            $item_uom_id = 3;
                        }
                    }

                    // $reults = getItemDetails($item['item_id'], $item_uom_id, $item['unload_qty']);

                    $unloaddetail = new SalesmanUnloadDetail;
                    $unloaddetail->salesman_unload_id   = $unloadheader->id;
                    $unloaddetail->route_id             = $request->route_id;
                    $unloaddetail->storage_location_id  = $request->storage_location_id;
                    $unloaddetail->item_id              = $item['item_id'];
                    $unloaddetail->item_uom             = $item_uom_id;
                    $unloaddetail->unload_qty           = $item['unload_qty'];
                    $unloaddetail->unload_date          = $item['unload_date'];
                    $unloaddetail->unload_type          = $item['unload_type'];
                    $unloaddetail->reason_id            = (!empty($item['reason_id'])) ? $item['reason_id'] : null;
                    $unloaddetail->van_id               = (!empty($request->van_id)) ? $request->van_id : null;
                    $unloaddetail->warehouse_id         = (!empty($request->warehouse_id)) ? $request->warehouse_id : null;
                    $unloaddetail->storage_location_id  = (!empty($request->storage_location_id)) ? $request->storage_location_id : null;
                    $unloaddetail->original_item_qty    = (!empty($item['original_item_qty'])) ? $item['original_item_qty'] : 0;
                    $unloaddetail->save();
                }
            }

            \DB::commit();

            $unloadheader->getSaveData();

            return prepareResult(true, $unloadheader, [], "Unload header updated successfully", $this->created);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating load header.", $this->unauthorized);
        }

        $loadheader = SalesmanUnload::where('uuid', $uuid)->first();

        if (is_object($loadheader)) {
            $loadheaderId = $loadheader->id;
            $loadheader->delete();
            if ($loadheader) {
                SalesmanUnload::where('salesman_load_id', $loadheaderId)->delete();
            }
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        } else {
            return prepareResult(true, [], [], "Record not found.", $this->not_found);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating load header", $this->unprocessableEntity);
        }

        $action = $request->action;
        $uuids = $request->salesman_load_ids;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'delete') {
            foreach ($uuids as $uuid) {
                $loadheader = SalesmanUnload::where('uuid', $uuid)->first();
                if (is_object($loadheader)) {
                    $loadheaderId = $loadheader->id;
                    $loadheader->delete();
                    if ($loadheader) {
                        SalesmanUnloadDetail::where('salesman_load_id', $loadheaderId)->delete();
                    }
                }
            }
            $loadheader = $this->index();
            return prepareResult(true, $loadheader, [], "Load header deleted success", $this->success);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'trip_id' => 'required',
                'unload_type' => 'required',
                'route_id' => 'required',
                'salesman_id' => 'required',
                'transaction_date' => 'required',
            ]);
        }
        if ($type == "unloadlist") {
            $validator = \Validator::make($input, [
                'filter' => 'required',
                'unload_type' => 'required',
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'salesman_load_ids' => 'required',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    private function returnViweEntry($load_detail, $header)
    {
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
            "PRE_RTE" => (isset($load_detail->van_id)) ? model($load_detail->van, 'van_code') : null,
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

    private function consolidateLoadReturnReportEntry($count, $load_detail, $header)
    {
        $to_location = "Good Return";
        if ($load_detail->unload_type == 2) {
            $to_location = "Damage";
        } else if ($load_detail->unload_type == 3) {
            $to_location = "Expiry";
        }

        ConsolidateLoadReturnReport::create([
            "SR_No" => $count,
            "Item" => model($load_detail->item, 'item_code'),
            "Item_description" => model($load_detail->item, 'item_name'),
            "qty" => model($load_detail, 'unload_qty'),
            "uom" => model($load_detail->itemUom, 'name'),
            "sec_qty" => "",
            "sec_uom" => "",
            "from_location" => "",
            "to_location" => $to_location,
            "from_lot_serial" => "",
            "to_lot_number" => "",
            "to_lot_status_code" => "",
            "load_date" => Carbon::parse($load_detail->unload_date)->format('Y-m-d'),
            "warehouse" => (isset($load_detail->storage_location_id)) ? model($load_detail->storageocation, 'code') : null,
            "storage_location_id" => (isset($load_detail->storage_location_id)) ? $load_detail->storage_location_id : null,
            "is_exported" => "NO",
            "salesman" => model($header->salesmanInfo, 'salesman_code'),
            "type" => "unload",
        ]);
    }

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id   = $work_flow_rule_id;
        $createObj->module_name         = $module_name;
        $createObj->raw_id              = $obj->id;
        $createObj->request_object      = $request->all();
        $createObj->save();
    }

    private function saveUnloadLoadItem($detail, $header)
    {
        $laod_item = LoadItem::where('salesman_id', $detail->salesman_id)
            ->where('item_id', $detail->item_id)
            ->where('item_uom_id', $detail->item_uom)
            ->where('report_date', $detail->unload_date)
            ->first();

        $main_price = ItemMainPrice::where('item_id', $detail->item_id)
            ->where('item_shipping_uom', 1)
            ->first();

        $dmd_lower_upc = ($main_price) ? $main_price->item_upc : 1;

        if ($laod_item) {
            $laod_item->update([
                'unload_qty' => $laod_item->unload_qty,
                'damage_qty' => ($detail->unload_type == 2) ? $laod_item->unload_qty : 0,
                'expiry_qty' => ($detail->unload_type == 3) ? $laod_item->unload_qty : 0
            ]);
        } else {
            $laod_item = new LoadItem;
            $laod_item->delivery_id             = NULL;
            $laod_item->van_id                  = $detail->van_id;
            $laod_item->van_code                = model($detail->van, 'van_code');
            $laod_item->storage_location_id     = $detail->storage_location_id;
            $laod_item->storage_location_code   = model($detail->storageocation, 'code');
            $laod_item->zone_id                 = NULL;
            $laod_item->zone_name               = NULL;
            $laod_item->load_number             = $header->code;
            $laod_item->salesman_id             = $header->salesman_id;
            $laod_item->salesman_code           = model($header->salesmanInfo, 'salesman_code');
            $laod_item->item_id                 = $detail->item_id;
            $laod_item->item_uom_id             = $detail->item_uom;
            $laod_item->item_uom                = model($detail->itemUom, 'code');
            $laod_item->loadqty                 = 0;
            $laod_item->return_qty              = 0;
            $laod_item->sales_qty               = 0;
            $laod_item->unload_qty              = $detail->unload_qty;
            $laod_item->damage_qty              = 0;
            $laod_item->expiry_qty              = 0;
            $laod_item->report_date             = $detail->unload_date;
            $laod_item->dmd_lower_upc           = $dmd_lower_upc;
            $laod_item->save();
        }
    }
}
