<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\ConsolidateLoadReturnReport;
use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\Goodreceiptnote;
use App\Model\Goodreceiptnotedetail;
use App\Model\ItemMainPrice;
use App\Model\CustomerWarehouseMapping;
use App\Model\LoadItem;
use App\Model\ReturnView;
use App\Model\SalesmanInfo;
use App\Model\StoragelocationDetail;
use App\Model\WarehouseDetail;
use App\Model\WarehouseDetailLog;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowRuleApprovalUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class GoodreceiptnoteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "Unauthorized access"], "User not authenticate.", $this->unauthorized);
        }

        $goodreceiptnote_query = Goodreceiptnote::with(
            'goodreceiptnotedetail',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'route:id,depot_id,route_code,route_name',
            'route.depot:id,depot_code,depot_name',
            'sourceWarehouse',
            'destinationWarehouse',
            'goodreceiptnotedetail.item:id,item_name,item_code',
            'goodreceiptnotedetail.itemUom:id,name,code',
            'goodreceiptnotedetail.reasonType:id,name,code,type'
        );

        if ($request->salesman_name) {
            $goodreceiptnote_query->whereHas('salesman', function ($q) use ($request) {
                $q->where(
                    DB::raw('CONCAT(firstname, " ",lastname)'),
                    'LIKE', '%' . $request->salesman_name . '%'
                    )
                    ->orWhereRaw(
                        "TRIM(CONCAT(firstname, ' ', lastname)) like '%{$request->salesman_name}%'"
                    )

                ->orWhere('firstname', 'like', '%' . $request->salesman_name . '%')
                ->orWhere('lastname', 'like', '%' . $request->salesman_name . '%');

            });
        }

        if ($request->salesman_code) {
            $goodreceiptnote_query->whereHas('salesmanInfo', function($query) use ($request){
                $query->where('salesman_code', $request->salesman_code);
            });
        }

        if ($request->date) {
            $goodreceiptnote_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->code) {
            $goodreceiptnote_query->where('grn_number', 'like', '%' . $request->code . '%');
        }

        if ($request->current_stage) {
            $goodreceiptnote_query->where('current_stage', 'like', '%' . $request->current_stage . '%');
        }

        if ($request->customer_reference_number) {
            $goodreceiptnote_query->where('customer_reference_number', 'like', '%' . $request->customer_reference_number . '%');
        }

        if ($request->sourceWarehouse) {
            $warehouseName = $request->sourceWarehouse;
            $goodreceiptnote_query->whereHas('sourceWarehouse', function ($q) use ($warehouseName) {
                $q->where('name', 'like', '%' . $warehouseName . '%');
            });
        }

        if ($request->destinationWarehouse) {
            $warehouseName = $request->destinationWarehouse;
            $goodreceiptnote_query->whereHas('destinationWarehouse', function ($q) use ($warehouseName) {
                $q->where('name', 'like', '%' . $warehouseName . '%');
            });
        }

        // $goodreceiptnote = $goodreceiptnote_query->orderBy('id', 'desc')->get();

        $all_goodreceiptnote = $goodreceiptnote_query->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $goodreceiptnote = $all_goodreceiptnote->items();

        $pagination = array();
        $pagination['total_pages'] = $all_goodreceiptnote->lastPage();
        $pagination['current_page'] = (int)$all_goodreceiptnote->perPage();
        $pagination['total_records'] = $all_goodreceiptnote->total();

        // approval
        $results = GetWorkFlowRuleObject('GRN', $all_goodreceiptnote->pluck('id')->toArray());
        $approve_need_grn = array();
        $approve_need_grn_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_grn[] = $raw['object']->raw_id;
                $approve_need_grn_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        $goodreceiptnote_array = array();
        if (is_object(collect($goodreceiptnote))) {
            foreach ($goodreceiptnote as $key => $order1) {
                if (in_array($goodreceiptnote[$key]->id, $approve_need_grn)) {
                    $goodreceiptnote[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_grn_object_id[$goodreceiptnote[$key]->id])) {
                        $goodreceiptnote[$key]->objectid = $approve_need_grn_object_id[$goodreceiptnote[$key]->id];
                    } else {
                        $goodreceiptnote[$key]->objectid = '';
                    }
                } else {
                    $goodreceiptnote[$key]->need_to_approve = 'no';
                    $goodreceiptnote[$key]->objectid = '';
                }

                if ($goodreceiptnote[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || in_array($goodreceiptnote[$key]->id, $approve_need_grn)) {
                    $goodreceiptnote_array[] = $goodreceiptnote[$key];
                }
            }
        }

        return prepareResult(true, $goodreceiptnote_array, [], "Good receipt note listing", $this->success, $pagination);

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($goodreceiptnote_array[$offset])) {
                    $data_array[] = $goodreceiptnote_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($goodreceiptnote_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($goodreceiptnote_array);
        } else {
            $data_array = $goodreceiptnote_array;
        }

        return prepareResult(true, $data_array, [], "Good receipt note listing", $this->success, $pagination);
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
            return prepareResult(false, [], ["error" => "Unauthorized access"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating good receipt note", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ['error' => "Error Please add atleast one items."], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {

            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('GRN', 'Create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Order);
            }

            $goodreceiptnote = new Goodreceiptnote;
            $goodreceiptnote->credit_note_id           = (!empty($request->credit_note_id)) ? $request->credit_note_id : null;
            $goodreceiptnote->salesman_id               = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $goodreceiptnote->route_id                  = (!empty($request->route_id)) ? $request->route_id : null;
            $goodreceiptnote->trip_id                   = (!empty($request->trip_id)) ? $request->trip_id : null;
            $goodreceiptnote->van_id                    = (!empty($request->van_id)) ? $request->van_id : null;
            $goodreceiptnote->is_damaged                = (!empty($request->is_damaged)) ? $request->is_damaged : 0;
            $goodreceiptnote->source_warehouse          = (!empty($request->source_warehouse)) ? $request->source_warehouse : getWarehouseByRoute($request->route_id);
            $goodreceiptnote->destination_warehouse     = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : $this->getDestinationWarehouse($request);
            $goodreceiptnote->grn_number                = (!empty($request->grn_number)) ? $request->grn_number : null;
            $goodreceiptnote->grn_date                  = (!empty($request->grn_date)) ? $request->grn_date : null;
            $goodreceiptnote->grn_remark                = (!empty($request->grn_remark)) ? $request->grn_remark : null;
            $goodreceiptnote->status                    = $status;
            $goodreceiptnote->current_stage             = $current_stage;
            $goodreceiptnote->current_stage_comment     = (!empty($request->current_stage_comment)) ? $request->current_stage_comment : null;
            $goodreceiptnote->customer_reference_number  = (!empty($request->customer_refrence_number)) ? $request->customer_refrence_number : null;
            $goodreceiptnote->source                    = (!empty($request->source)) ? $request->source : 3;
            $goodreceiptnote->approval_status           = "Created";
            $goodreceiptnote->save();

            if (is_array($request->items)) {
                foreach ($request->items as $key => $item) {
                    $goodreceiptnotedetail = new Goodreceiptnotedetail;
                    $goodreceiptnotedetail->good_receipt_note_id = $goodreceiptnote->id;
                    $goodreceiptnotedetail->item_id              = $item['item_id'];
                    $goodreceiptnotedetail->item_uom_id          = $item['item_uom_id'];
                    $goodreceiptnotedetail->reason               = $item['reason'];
                    $goodreceiptnotedetail->qty                  = $item['qty'];
                    $goodreceiptnotedetail->original_item_qty    = $item['qty'];
                    $goodreceiptnotedetail->credit_note_detail_id   = $item['credit_note_detail_id'];
                    $goodreceiptnotedetail->save();

                    $this->loadItem($goodreceiptnote, $item, $goodreceiptnotedetail);

                    $count = $key + 1;

                    $this->consolidateLoadReturnReportEntry($goodreceiptnote, $goodreceiptnotedetail, $count);

                    // $this->returnViweEntry($goodreceiptnote, $goodreceiptnotedetail);
                }
            }

            if ($isActivate = checkWorkFlowRule('GRN', 'Create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'GRN', $request, $goodreceiptnote);
            }

            DB::commit();

            updateNextComingNumber('App\Model\Goodreceiptnote', 'goodreceiptnote');

            $goodreceiptnote->getSaveData();

            return prepareResult(true, $goodreceiptnote, [], "Good receipt note added successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
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
            return prepareResult(false, [], [], "Error while validating good receipt note.", $this->unauthorized);
        }

        $goodreceiptnote = Goodreceiptnote::with(
            'goodreceiptnotedetail',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'route:id,depot_id,route_code,route_name',
            'route.depot:id,depot_code,depot_name',
            'sourceWarehouse',
            'destinationWarehouse',
            'goodreceiptnotedetail.item:id,item_name,item_code',
            'goodreceiptnotedetail.itemUom:id,name,code',
            'goodreceiptnotedetail.reasonType:id,name,code,type'
        )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($goodreceiptnote)) {
            return prepareResult(false, [], ['error' => "Oops!!!, something went wrong, please try again."], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $goodreceiptnote, [], "Good receipt note Edit", $this->success);
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
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating good receipt note.", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            // $status = $request->status;
            // $current_stage = 'Approved';
            // $current_organisation_id = request()->user()->organisation_id;
            // if ($isActivate = checkWorkFlowRule('GRN', 'Edited', $current_organisation_id)) {
            //     $current_stage = 'Pending';
            //     //$this->createWorkFlowObject($isActivate, 'Order);
            // }

            $goodreceiptnote = Goodreceiptnote::where('uuid', $uuid)->first();
            $goodreceiptnote->credit_note_id           = (!empty($request->credit_note_id)) ? $request->credit_note_id : null;
            $goodreceiptnote->salesman_id           = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $goodreceiptnote->route_id              = (!empty($request->route_id)) ? $request->route_id : null;
            $goodreceiptnote->trip_id               = (!empty($request->trip_id)) ? $request->trip_id : null;
            $goodreceiptnote->van_id                = (!empty($request->van_id)) ? $request->van_id : null;
            $goodreceiptnote->is_damaged             = (!empty($request->is_damaged)) ? $request->is_damaged : null;
            $goodreceiptnote->source_warehouse      = (!empty($request->source_warehouse)) ? $request->source_warehouse : getWarehouseByRoute($request->route_id);
            $goodreceiptnote->destination_warehouse     = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : $this->getDestinationWarehouse($request);
            $goodreceiptnote->grn_number            = (!empty($request->grn_number)) ? $request->grn_number : null;
            $goodreceiptnote->grn_date              = (!empty($request->grn_date)) ? $request->grn_date : null;
            $goodreceiptnote->grn_remark            = (!empty($request->grn_remark)) ? $request->grn_remark : null;
            // $goodreceiptnote->status                = $status;
            // $goodreceiptnote->current_stage         = $current_stage;
            $goodreceiptnote->current_stage_comment = (!empty($request->current_stage_comment)) ? $request->current_stage_comment : null;
            $goodreceiptnote->customer_reference_number = (!empty($request->customer_refrence_number)) ? $request->customer_refrence_number : null;
            $goodreceiptnote->approval_status       = "Updated";
            $goodreceiptnote->source                = (!empty($request->source)) ? $request->source : 3;
            $goodreceiptnote->save();

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    $goodreceiptnotedetail = Goodreceiptnotedetail::find($item['id']);
                    $goodreceiptnotedetail->good_receipt_note_id    = $goodreceiptnote->id;
                    $goodreceiptnotedetail->item_id                 = $item['item_id'];
                    $goodreceiptnotedetail->item_uom_id             = $item['item_uom_id'];
                    $goodreceiptnotedetail->reason                  = $item['reason'];
                    $goodreceiptnotedetail->qty                     = $item['qty'];
                    $goodreceiptnotedetail->credit_note_detail_id   = $item['credit_note_detail_id'];
                    $goodreceiptnotedetail->reason_id               = (!empty($item['reason_id'])) ? $item['reason_id'] : null;
                    $goodreceiptnotedetail->save();

                    $this->updateCreditNoteDetails($request, $item);
                }
            }

            // if (is_array($request->items)) {
            //     foreach ($request->items as $item) {
            //         if ($item['id'] > 0) {
            //             $goodreceiptnotedetail = Goodreceiptnotedetail::find($item['id']);
            //             if ($goodreceiptnotedetail->qty != $item['qty']) {

            //                 if ($goodreceiptnotedetail->qty > $item['qty']) {

            //                     $qty = ($item['qty'] - $goodreceiptnotedetail->qty);
            //                     $warehousedetail = WarehouseDetail::where('warehouse_id', $request->source_warehouse)
            //                         ->where('item_id', $item['item_id'])
            //                         ->where('item_uom_id', $item['item_uom_id'])
            //                         ->first();

            //                     if ($warehousedetail) {
            //                         $update_qty = ($warehousedetail->qty - $qty);
            //                         $warehousedetail = WarehouseDetail::find($warehousedetail->id);
            //                         $warehousedetail->qty = $update_qty;
            //                         $warehousedetail->save();
            //                     } else {
            //                         $warehousedetail = new WarehouseDetail;
            //                         $warehousedetail->warehouse_id         = $request->source_warehouse;
            //                         $warehousedetail->item_id         = $item['item_id'];
            //                         $warehousedetail->item_uom_id            = $item['item_uom_id'];
            //                         $warehousedetail->qty            = (0 - $qty);
            //                         $warehousedetail->batch       = '';
            //                         $warehousedetail->save();
            //                     }
            //                     //add log
            //                     $warehousedetail_log = new WarehouseDetailLog;
            //                     $warehousedetail_log->warehouse_id = (!empty($request->source_warehouse)) ? $request->source_warehouse : null;
            //                     $warehousedetail_log->warehouse_detail_id = $warehousedetail->id;
            //                     $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //                     $warehousedetail_log->qty = $qty;
            //                     $warehousedetail_log->action_type = 'Unload';
            //                     $warehousedetail_log->save();
            //                     //add log

            //                     //update destination warehouse
            //                     $warehousedetail_dest = WarehouseDetail::where('warehouse_id', $request->destination_warehouse)
            //                         ->where('item_id', $item['item_id'])
            //                         ->where('item_uom_id', $item['item_uom_id'])
            //                         ->first();
            //                     if ($warehousedetail_dest) {
            //                         $warehousedetail_dest->qty            = ($warehousedetail_dest->qty + $qty);
            //                         $warehousedetail_dest->save();
            //                     } else {
            //                         $warehousedetail_dest = new WarehouseDetail;
            //                         $warehousedetail_dest->warehouse_id         = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                         $warehousedetail_dest->item_id         = $item['item_id'];
            //                         $warehousedetail_dest->item_uom_id            = $item['item_uom_id'];
            //                         $warehousedetail_dest->qty            = $qty;
            //                         $warehousedetail_dest->batch       = '';
            //                         $warehousedetail_dest->save();
            //                     }

            //                     //add log
            //                     $warehousedetail_log = new WarehouseDetailLog;
            //                     $warehousedetail_log->warehouse_id = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                     $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
            //                     $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //                     $warehousedetail_log->qty = $qty;
            //                     $warehousedetail_log->action_type = 'Load';
            //                     $warehousedetail_log->save();
            //                     //add log
            //                 } else {
            //                     $qty = ($goodreceiptnotedetail->qty - $item['qty']);
            //                     $warehousedetail = WarehouseDetail::where('warehouse_id', $request->source_warehouse)
            //                         ->where('item_id', $item['item_id'])
            //                         ->where('item_uom_id', $item['item_uom_id'])
            //                         ->first();
            //                     if ($warehousedetail) {
            //                         $update_qty = ($warehousedetail->qty + $qty);
            //                         $warehousedetail_dest = WarehouseDetail::find($warehousedetail->id);
            //                         $warehousedetail_dest->qty = $update_qty;
            //                         $warehousedetail_dest->save();
            //                     } else {
            //                         $warehousedetail_dest = new WarehouseDetail;
            //                         $warehousedetail_dest->warehouse_id         = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                         $warehousedetail_dest->item_id         = $item['item_id'];
            //                         $warehousedetail_dest->item_uom_id            = $item['item_uom_id'];
            //                         $warehousedetail_dest->qty            = $qty;
            //                         $warehousedetail_dest->batch       = '';
            //                         $warehousedetail_dest->save();
            //                     }

            //                     //add log
            //                     $warehousedetail_log = new WarehouseDetailLog;
            //                     $warehousedetail_log->warehouse_id = (!empty($request->source_warehouse)) ? $request->source_warehouse : null;
            //                     $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
            //                     $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //                     $warehousedetail_log->qty = $qty;
            //                     $warehousedetail_log->action_type = 'Load';
            //                     $warehousedetail_log->save();
            //                     //add log

            //                     //update destination warehouse
            //                     $warehousedetail_dest = WarehouseDetail::where('warehouse_id', $request->destination_warehouse)
            //                         ->where('item_id', $item['item_id'])
            //                         ->where('item_uom_id', $item['item_uom_id'])
            //                         ->first();
            //                     if ($warehousedetail_dest) {
            //                         $warehousedetail_dest->qty    = ($warehousedetail_dest->qty - $qty);
            //                         $warehousedetail_dest->save();
            //                     } else {
            //                         $warehousedetail_dest = new WarehouseDetail;
            //                         $warehousedetail_dest->warehouse_id         = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                         $warehousedetail_dest->item_id         = $item['item_id'];
            //                         $warehousedetail_dest->item_uom_id            = $item['item_uom_id'];
            //                         $warehousedetail_dest->qty            = $qty;
            //                         $warehousedetail_dest->batch       = '';
            //                         $warehousedetail_dest->save();
            //                     }

            //                     //add log
            //                     $warehousedetail_log = new WarehouseDetailLog;
            //                     $warehousedetail_log->warehouse_id = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                     $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
            //                     $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //                     $warehousedetail_log->qty = $qty;
            //                     $warehousedetail_log->action_type = 'Unload';
            //                     $warehousedetail_log->save();
            //                     //add log
            //                 }

            //                 $goodreceiptnotedetail = Goodreceiptnotedetail::find($item['id']);
            //                 $goodreceiptnotedetail->good_receipt_note_id      = $goodreceiptnote->id;
            //                 $goodreceiptnotedetail->item_id           = $item['item_id'];
            //                 $goodreceiptnotedetail->item_uom_id       = $item['item_uom_id'];
            //                 $goodreceiptnotedetail->reason           = $item['reason'];
            //                 $goodreceiptnotedetail->qty                = $item['qty'];
            //                 $goodreceiptnotedetail->created_at      = date('Y-m-d H:i:s');
            //                 $goodreceiptnotedetail->updated_at      = date('Y-m-d H:i:s');
            //                 $goodreceiptnotedetail->save();
            //             }
            //         } else {
            //             $goodreceiptnotedetail = new Goodreceiptnotedetail;
            //             $goodreceiptnotedetail->good_receipt_note_id      = $goodreceiptnote->id;
            //             $goodreceiptnotedetail->item_id       = $item['item_id'];
            //             $goodreceiptnotedetail->item_uom_id   = $item['item_uom_id'];
            //             $goodreceiptnotedetail->qty   = $item['qty'];
            //             $goodreceiptnotedetail->reason           = $item['reason'];
            //             $goodreceiptnotedetail->created_at        = date('Y-m-d H:i:s');
            //             $goodreceiptnotedetail->updated_at        = date('Y-m-d H:i:s');
            //             $goodreceiptnotedetail->save();

            //             // update source warehouse
            //             $warehousedetail = WarehouseDetail::where('warehouse_id', $request->source_warehouse)
            //                 ->where('item_id', $item['item_id'])
            //                 ->where('item_uom_id', $item['item_uom_id'])
            //                 ->first();
            //             if ($warehousedetail) {
            //                 $update_qty = ($warehousedetail->qty - $item['qty']);
            //                 $warehousedetail_update = WarehouseDetail::find($warehousedetail->id);
            //                 $warehousedetail_update->qty = $update_qty;
            //                 $warehousedetail_update->save();
            //             } else {
            //                 $warehousedetail = new WarehouseDetail;
            //                 $warehousedetail->warehouse_id         = (!empty($request->source_warehouse)) ? $request->source_warehouse : null;
            //                 $warehousedetail->item_id         = $item['item_id'];
            //                 $warehousedetail->item_uom_id            = $item['item_uom_id'];
            //                 $warehousedetail->qty            = (0 - $item['qty']);
            //                 $warehousedetail->batch       = '';
            //                 $warehousedetail->save();
            //             }
            //             //add log
            //             $warehousedetail_log = new WarehouseDetailLog;
            //             $warehousedetail_log->warehouse_id = (!empty($request->source_warehouse)) ? $request->source_warehouse : null;
            //             $warehousedetail_log->warehouse_detail_id = $warehousedetail->id;
            //             $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //             $warehousedetail_log->qty = $item['qty'];
            //             $warehousedetail_log->action_type = 'Unload';
            //             $warehousedetail_log->created_at       = date('Y-m-d H:i:s');
            //             $warehousedetail_log->updated_at       = date('Y-m-d H:i:s');
            //             $warehousedetail_log->save();
            //             //add log

            //             //update source warehouse

            //             //update destination warehouse
            //             $warehousedetail_dest = WarehouseDetail::where('warehouse_id', $request->destination_warehouse)
            //                 ->where('item_id', $item['item_id'])
            //                 ->where('item_uom_id', $item['item_uom_id'])
            //                 ->first();
            //             if ($warehousedetail_dest) {
            //                 $warehousedetail_dest->qty            = ($warehousedetail_dest->qty + $item['qty']);
            //                 $warehousedetail_dest->updated_at       = date('Y-m-d H:i:s');
            //                 $warehousedetail_dest->save();
            //             } else {
            //                 $warehousedetail_dest = new WarehouseDetail;
            //                 $warehousedetail_dest->warehouse_id         = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //                 $warehousedetail_dest->item_id         = $item['item_id'];
            //                 $warehousedetail_dest->item_uom_id            = $item['item_uom_id'];
            //                 $warehousedetail_dest->qty            = $item['qty'];
            //                 $warehousedetail_dest->batch       = '';
            //                 $warehousedetail_dest->save();
            //             }

            //             //add log
            //             $warehousedetail_log = new WarehouseDetailLog;
            //             $warehousedetail_log->warehouse_id = (!empty($request->destination_warehouse)) ? $request->destination_warehouse : null;
            //             $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
            //             $warehousedetail_log->item_uom_id = $item['item_uom_id'];
            //             $warehousedetail_log->qty = $item['qty'];
            //             $warehousedetail_log->action_type = 'Load';
            //             $warehousedetail_log->created_at       = date('Y-m-d H:i:s');
            //             $warehousedetail_log->updated_at       = date('Y-m-d H:i:s');
            //             $warehousedetail_log->save();
            //             //add log

            //             //update destination warehouse
            //         }
            //     }
            // }

            if ($isActivate = checkWorkFlowRule('GRN', 'Edited', $request->user()->organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'GRN', $request, $goodreceiptnote);
            }

            DB::commit();

            $goodreceiptnote->getSaveData();

            return prepareResult(true, $goodreceiptnote, [], "Good receipt note updated successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }
    /**
     * approve the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        DB::beginTransaction();
        try {

            $goodreceiptnote = Goodreceiptnote::where('uuid', $uuid)->first();
            $goodreceiptnotedetail = Goodreceiptnotedetail::where('good_receipt_note_id', $goodreceiptnote->id)
                ->first();

            if (is_object($goodreceiptnotedetail)) {
                //----------------
                $routestoragelocation_id = $goodreceiptnote->source_warehouse;
                $warehousestoragelocation_id = $goodreceiptnote->destination_warehouse;
                $routelocation_detail = StoragelocationDetail::where('storage_location_id', $routestoragelocation_id)
                    ->where('item_id', $goodreceiptnotedetail->item_id)
                    ->where('item_uom_id', $goodreceiptnotedetail->item_uom_id)
                    ->first();

                $warehouselocation_detail = StoragelocationDetail::where('storage_location_id', $warehousestoragelocation_id)
                    ->where('item_id', $goodreceiptnotedetail->item_id)
                    ->where('item_uom_id', $goodreceiptnotedetail->item_uom_id)
                    ->first();

                if (is_object($warehouselocation_detail)) {
                    $warehouselocation_detail->qty = ($warehouselocation_detail->qty + $goodreceiptnotedetail->qty);
                    $warehouselocation_detail->save();
                } else {
                    $storagewarehousedetail = new StoragelocationDetail;
                    $storagewarehousedetail->storage_location_id = $routestoragelocation_id;
                    $storagewarehousedetail->item_id = $goodreceiptnotedetail->item_id;
                    $storagewarehousedetail->item_uom_id = $goodreceiptnotedetail->item_uom_id;
                    $storagewarehousedetail->qty = $goodreceiptnotedetail->qty;
                    $storagewarehousedetail->save();
                }

                if (is_object($routelocation_detail)) {
                    $routelocation_detail->qty = ($routelocation_detail->qty - $goodreceiptnotedetail->qty);
                    $routelocation_detail->save();
                } else {
                    $routestoragedetail = new StoragelocationDetail;
                    $routestoragedetail->storage_location_id = $routestoragelocation_id;
                    $routestoragedetail->item_id = $goodreceiptnotedetail->item_id;
                    $routestoragedetail->item_uom_id = $goodreceiptnotedetail->item_uom_id;
                    $routestoragedetail->qty = $goodreceiptnotedetail->qty;
                    $routestoragedetail->save();
                }
            }
            //----------------
            DB::commit();

            return prepareResult(true, $goodreceiptnote, [], "Good receipt note Approved successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
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
            return prepareResult(false, [], ['error' => "Error while validating good receipt note."], "Error while validating good receipt note.", $this->unauthorized);
        }

        $goodreceiptnote = Goodreceiptnote::where('uuid', $uuid)->first();
        if (is_object($goodreceiptnote)) {
            $goodreceiptnoteId = $goodreceiptnote->id;
            $source_warehouse = $goodreceiptnote->source_warehouse;
            $destination_warehouse = $goodreceiptnote->destination_warehouse;
            $goodreceiptnote->delete();
            if ($goodreceiptnote) {
                $goodreceiptnotedetail = Goodreceiptnotedetail::where('good_receipt_note_id', $goodreceiptnoteId)->orderBy('id', 'desc')->get();
                if ($goodreceiptnotedetail) {
                    foreach ($goodreceiptnotedetail as $notedetail) {
                        $warehousedetail_dest = WarehouseDetail::where('warehouse_id', $destination_warehouse)
                            ->where('item_id', $notedetail->item_id)
                            ->where('item_uom_id', $notedetail->item_uom_id)
                            ->first();
                        if ($warehousedetail_dest) {
                            $warehousedetail_dest->qty = ($warehousedetail_dest->qty - $notedetail->qty);
                            $warehousedetail_dest->save();

                            //add log
                            $warehousedetail_log = new WarehouseDetailLog;
                            $warehousedetail_log->warehouse_id = $destination_warehouse;
                            $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
                            $warehousedetail_log->item_uom_id = $notedetail->item_uom_id;
                            $warehousedetail_log->qty = $notedetail->qty;
                            $warehousedetail_log->action_type = 'Unload';
                            $warehousedetail_log->save();
                            //add log
                        }

                        $warehousedetail = WarehouseDetail::where('warehouse_id', $source_warehouse)
                            ->where('item_id', $notedetail->item_id)
                            ->where('item_uom_id', $notedetail->item_uom_id)
                            ->first();
                        if ($warehousedetail) {
                            $warehousedetail->qty = ($warehousedetail->qty + $notedetail->qty);
                            $warehousedetail->save();

                            //add log
                            $warehousedetail_log = new WarehouseDetailLog;
                            $warehousedetail_log->warehouse_id = $source_warehouse;
                            $warehousedetail_log->warehouse_detail_id = $warehousedetail->id;
                            $warehousedetail_log->item_uom_id = $notedetail->item_uom_id;
                            $warehousedetail_log->qty = $notedetail->qty;
                            $warehousedetail_log->action_type = 'Load';
                            $warehousedetail_log->save();
                            //add log
                        }
                    }
                }
                Goodreceiptnotedetail::where('good_receipt_note_id', $goodreceiptnoteId)->delete();
            }
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        } else {
            return prepareResult(true, [], ['error' => "Record not found."], "Record not found.", $this->not_found);
        }

        return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
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
            return prepareResult(false, [], ["error" => "Unauthorized access"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating good receipt note", $this->unprocessableEntity);
        }

        $action = $request->action;
        $uuids = $request->goodreceiptnote_ids;

        if (empty($action)) {
            return prepareResult(false, [], ['error' => "Please provide valid action parameter value."], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'delete') {
            foreach ($uuids as $uuid) {
                $goodreceiptnote = Goodreceiptnote::where('uuid', $uuid)->first();
                if (is_object($goodreceiptnote)) {
                    $goodreceiptnoteId = $goodreceiptnote->id;
                    $source_warehouse = $goodreceiptnote->source_warehouse;
                    $destination_warehouse = $goodreceiptnote->destination_warehouse;
                    $goodreceiptnote->delete();
                    if ($goodreceiptnote) {
                        $goodreceiptnotedetail = Goodreceiptnotedetail::where('good_receipt_note_id', $goodreceiptnoteId)->get();
                        if ($goodreceiptnotedetail) {
                            foreach ($goodreceiptnotedetail as $notedetail) {
                                $warehousedetail_dest = WarehouseDetail::where('warehouse_id', $destination_warehouse)
                                    ->where('item_id', $notedetail->item_id)
                                    ->where('item_uom_id', $notedetail->item_uom_id)
                                    ->first();
                                if ($warehousedetail_dest) {
                                    $warehousedetail_dest->qty = ($warehousedetail_dest->qty - $notedetail->qty);
                                    $warehousedetail_dest->save();

                                    //add log
                                    $warehousedetail_log = new WarehouseDetailLog;
                                    $warehousedetail_log->warehouse_id = $destination_warehouse;
                                    $warehousedetail_log->warehouse_detail_id = $warehousedetail_dest->id;
                                    $warehousedetail_log->item_uom_id = $notedetail->item_uom_id;
                                    $warehousedetail_log->qty = $notedetail->qty;
                                    $warehousedetail_log->action_type = 'Unload';
                                    $warehousedetail_log->save();
                                    //add log
                                }

                                $warehousedetail = WarehouseDetail::where('warehouse_id', $source_warehouse)
                                    ->where('item_id', $notedetail->item_id)
                                    ->where('item_uom_id', $notedetail->item_uom_id)
                                    ->first();
                                if ($warehousedetail) {
                                    $warehousedetail->qty = ($warehousedetail->qty + $notedetail->qty);
                                    $warehousedetail->save();

                                    //add log
                                    $warehousedetail_log = new WarehouseDetailLog;
                                    $warehousedetail_log->warehouse_id = $source_warehouse;
                                    $warehousedetail_log->warehouse_detail_id = $warehousedetail->id;
                                    $warehousedetail_log->item_uom_id = $notedetail->item_uom_id;
                                    $warehousedetail_log->qty = $notedetail->qty;
                                    $warehousedetail_log->action_type = 'Load';
                                    $warehousedetail_log->save();
                                    //add log
                                }
                            }
                        }
                        Goodreceiptnotedetail::where('good_receipt_note_id', $goodreceiptnoteId)->delete();
                    }
                }
            }
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
            $goodreceiptnote = $this->index();
            return prepareResult(true, $goodreceiptnote, [], "good receipt note deleted success", $this->success);
        }
    }
    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                // 'source_warehouse' => 'required',
                'destination_warehouse' => 'required',
                'grn_number' => 'required',
                'grn_date' => 'required',
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = Validator::make($input, [
                'action' => 'required',
                'goodreceiptnote_ids' => 'required',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
        if (isset($obj->id)) {
            // $module_path = 'App\\Model\\' . $module_name;
            $module = Goodreceiptnote::where('id', $obj->id)
                ->where('organisation_id', request()->user()->organisation_id)
                ->where('approval_status', 'Updated')
                ->first();

            if ($module) {
                WorkFlowObject::where('raw_id', $obj->id)->delete();
            }
        }

        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id = $work_flow_rule_id;
        $createObj->module_name = $module_name;
        $createObj->raw_id = $obj->id;
        $createObj->request_object = $request->all();
        $createObj->save();

        $wfrau = WorkFlowRuleApprovalUser::where('work_flow_rule_id', $work_flow_rule_id)->first();

        $data = array(
            'uuid' => (is_object($obj)) ? $obj->uuid : 0,
            'user_id' => $wfrau->user_id,
            'type' => $module_name,
            'message' => "Approve the New " . $module_name,
            'status' => 1,
        );
        saveNotificaiton($data);
    }

    private function consolidateLoadReturnReportEntry($header, $details, $count)
    {
        $to_location = "Good Return";
        if ($details->reason == "Damage Return") {
            $to_location = "Damage";
        } else if ($details->reason == "Expiry Return") {
            $to_location = "Expiry";
        }

        ConsolidateLoadReturnReport::create([
            "SR_No" => $count,
            "Item" => model($details->item, 'item_code'),
            "Item_description" => model($details->item, 'item_name'),
            "qty" => model($details, 'qty'),
            "uom" => model($details->itemUom, 'name'),
            "sec_qty" => "",
            "sec_uom" => "",
            "from_location" => "",
            "to_location" => $to_location,
            "from_lot_serial" => "",
            "to_lot_number" => "",
            "to_lot_status_code" => "",
            "load_date" => Carbon::parse($header->grn_date)->format('Y-m-d'),
            "warehouse" => (isset($header->destination_warehouse)) ? model($header->destinationWarehouse, 'code') : null,
            "storage_location_id" => (isset($header->destination_warehouse)) ? $header->destination_warehouse : null,
            "is_exported" => "NO",
            "salesman" => model($header->salesmanInfo, 'salesman_code'),
            "type" => "grn",
        ]);
    }

    private function returnViweEntry($header, $details)
    {
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
            "MCU_CODE" => (isset($header->destination_warehouse)) ? model($header->destination_warehouse, 'code') : null,
            "MCU_NAME" => (isset($header->destination_warehouse)) ? model($header->destination_warehouse, 'name') : null,
            "RTE_CODE" => "MT1",
            "PRE_RTE" => (isset($header->van_id)) ? model($header->van, 'van_code') : null,
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
            "",
        ]);
    }

    public function loadItem($goodreceiptnote, $item, $goodreceiptnotedetail)
    {
        $load_item = LoadItem::where('salesman_id', $goodreceiptnote->salesman_id)
            ->where('item_id', $item['item_id'])
            ->where('item_uom_id', $item['item_uom_id'])
            ->where('report_date', $goodreceiptnote->grn_date)
            ->first();

        $main_price = ItemMainPrice::where('item_id', $goodreceiptnotedetail->item_id)
            ->where('item_shipping_uom', 1)
            ->first();

        $dmd_lower_upc = ($main_price) ? $main_price->item_upc : 1;

        if ($load_item) {
            $load_item->update([
                'return_qty' => $goodreceiptnotedetail->qty
            ]);
        } else {

            $load_item = new LoadItem;
            $load_item->delivery_id             = NULL;
            $load_item->van_id                  = $goodreceiptnote->van_id;
            $load_item->van_code                = model($goodreceiptnote->van, 'van_code');
            $load_item->storage_location_id     = $goodreceiptnote->destination_warehouse;
            $load_item->storage_location_code   = model($goodreceiptnote->destination_warehouse, 'code');
            $load_item->zone_id                 = NULL;
            $load_item->zone_name               = NULL;
            $load_item->load_number             = $goodreceiptnote->grn_number;
            $load_item->salesman_id             = $goodreceiptnote->salesman_id;
            $load_item->salesman_code           = model($goodreceiptnote->salesmanInfo, 'salesman_code');
            $load_item->item_id                 = $goodreceiptnotedetail->item_id;
            $load_item->item_uom_id             = $goodreceiptnotedetail->item_uom_id;
            $load_item->item_uom                = model($goodreceiptnotedetail->itemUom, 'code');
            $load_item->loadqty                 = 0;
            $load_item->return_qty              = $goodreceiptnotedetail->qty;
            $load_item->sales_qty               = 0; // Invoice qty
            $load_item->unload_qty              = 0;
            $load_item->damage_qty              = ($goodreceiptnotedetail->reason == "Damage Return") ? $goodreceiptnotedetail->qty : 0;
            $load_item->expiry_qty              = ($goodreceiptnotedetail->reason == "Expiry Return") ? $goodreceiptnotedetail->qty : 0;
            $load_item->report_date             = $goodreceiptnotedetail->qty;
            $load_item->dmd_lower_upc           = $dmd_lower_upc;
            $load_item->save();
        }
    }

    /**
     * Update Credit Note Detail
     *
     * @param [type] $request
     * @param [type] $detail
     * @return void
     */
    private function updateCreditNoteDetails($request, $detail)
    {
        $t_price = 0;
        $t_qty = 0;
        $t_gross = 0;
        $t_discount_amount = 0;
        $t_net = 0;
        $t_vat = 0;
        $t_excise = 0;
        $t_grand_total = 0;

        if ($request->credit_note_id) {
            $cr = CreditNote::find($request->credit_note_id);
            if ($cr) {
                $stdObject = new stdClass();
                $stdObject->customer_id = model($cr->customerInfo, 'id');
                $stdObject->item_id     = $detail['item_id'];
                $stdObject->item_uom_id = $detail['item_uom_id'];
                $stdObject->item_qty    = $detail['qty'];
                $stdObject->lob_id      = $cr->lob_id;
                $stdObject->delivery_date = $request->credit_note_date;

                $item_apply = (array) item_apply_price($stdObject);

                $crd = CreditNoteDetail::find($detail['credit_note_detail_id']);

                $crd->item_qty              = $detail['qty'];
                $crd->item_id               = $detail['item_id'];
                $crd->item_uom_id           = $detail['item_uom_id'];
                $crd->discount_id           = (isset($item_apply['discount_id']) ? $item_apply['discount_id'] : 0);
                $crd->is_free               = (isset($item_apply['is_free']) ? $item_apply['is_free'] : 0);
                $crd->is_item_poi           = (isset($item_apply['is_item_poi']) ? $item_apply['is_item_poi'] : 0);
                $crd->promotion_id          = (isset($item_apply['promotion_id']) ? $item_apply['promotion_id'] : 0);
                $crd->item_price            = (!empty($item_apply['item_price'])) ? (float) str_replace(',', '', $item_apply['item_price']) : 0;
                $crd->item_gross            = (!empty($item_apply['item_gross'])) ? (float) str_replace(',', '', $item_apply['item_gross']) : 0;
                $crd->item_discount_amount  = (!empty($item_apply['discount_id'])) ? $item_apply['discount_id'] : 0;
                $crd->item_net              = (!empty($item_apply['total_net'])) ? (float) str_replace(',', '', $item_apply['total_net']) : 0;
                $crd->item_vat              = (!empty($item_apply['total_vat'])) ? (float) str_replace(',', '', $item_apply['total_vat']) : 0;
                $crd->item_excise           = (!empty($item_apply['total_excise'])) ? (float) str_replace(',', '', $item_apply['total_excise']) : 0;
                $crd->item_grand_total      = (!empty($item_apply['total'])) ? (float) str_replace(',', '', $item_apply['total']) : 0;
                $crd->save();

                $cr->update([
                    'item_qty'              => ($t_qty + $detail['qty']),
                    'item_price'            => ($t_price + $crd->item_price),
                    'item_gross'            => ($t_gross + $crd->item_gross),
                    'item_discount_amount'  => ($t_discount_amount + $crd->item_discount_amount),
                    'item_net'              => ($t_net + $crd->item_net),
                    'item_vat'              => ($t_vat + $crd->item_vat),
                    'item_excise'           => ($t_excise + $crd->item_excise),
                    'item_grand_total'      => ($t_grand_total + $crd->item_grand_total)
                ]);
            }
        }
    }
    public function updateTruck(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        // $validate = $this->validations($input, "truck-update");
        // if ($validate["error"]) {
        //     return prepareResult(false, [], $validate['errors']->first(), "Error while validating credit note", $this->unprocessableEntity);
        // }

        $s_info = SalesmanInfo::find($request->salesman_id);

        $creditNote = CreditNote::find($request->credit_note_id);

        if ($creditNote) {

            $creditNote->salesman_id   = $request->salesman_id;
            $creditNote->route_id   = $s_info->route_id;
            $creditNote->approval_status   = "Truck Allocated";
            $creditNote->save();

            return prepareResult(true, $creditNote, [], "Credit Note imported.", $this->success);
        }

        return prepareResult(false, [], ["error" => "Credit Note not found."], "Credit Note not found..", $this->unprocessableEntity);
    }
    private function getDestinationWarehouse($request)
    {
        $cr = CreditNote::find($request->credit_note_id);
        if ($cr) {
            $cwm = CustomerWarehouseMapping::where('customer_id', $cr->customer_id)->first();
            if ($cwm) {
                return $cwm->storage_location_id;
            }
        }
    }
}
