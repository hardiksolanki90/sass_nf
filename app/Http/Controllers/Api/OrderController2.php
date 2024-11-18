<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\ItemMainPrice;
use App\Model\Item;
use App\Model\PriceDiscoPromoPlan;
use App\Model\CustomerInfo;
use App\Model\Delivery;
use App\Model\Route;
use App\Model\PDPDiscountSlab;
use App\Model\PDPItem;
use App\Model\PDPPromotionItem;
use App\Model\SalesmanNumberRange;
use App\Model\WorkFlowObject;
use App\User;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Imports\OrderImport;
use App\Model\CodeSetting;
use App\Model\DeliveryDetail;
use App\Model\OrderLog;
use App\Model\OrderView;
use Illuminate\Support\Facades\DB;

class OrderController2 extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $orders_query = Order::with(array('customer' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name',
                'orderDetails',
                'orderDetails.reason:id,name',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.item.itemUomLowerUnit',
                'depot:id,depot_name',
                'lob',
                'storageocation:id,code,name'
            );
        // ->where('order_date', date('Y-m-d'));

        if ($request->date) {
            $orders_query->whereDate('created_at', $request->date);
        }

        if ($request->order_number) {
            $orders_query->where('order_number', 'like', '%' . $request->order_number . '%');
        }

        if ($request->due_date) {
            $orders_query->where('due_date', date('Y-m-d', strtotime($request->due_date)));
        }

        if ($request->current_stage) {
            $orders_query->where('current_stage', 'like', '%' . $request->current_stage . '%');
        }

        if (isset($request->approval)) {
            $orders_query->where('status', $request->approval);
        }

        if ($request->salesman) {
            $name = $request->salesman;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $orders_query->whereHas('salesman', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $orders_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $orders_query->whereHas('salesman.salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', '%' . $salesman_code . '%');
            });
        }


        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $orders_query->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $orders_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $customer_code = $request->customer_code;
            $orders_query->whereHas('customer.customerInfo', function ($q) use ($customer_code) {
                $q->where('customer_code', 'like', '%' . $customer_code . '%');
            });
        }

        if ($request->route_name) {
            $route_name = $request->route_name;
            $orders_query->whereHas('route', function ($q) use ($route_name) {
                $q->where('route_name', 'like', '%' . $route_name . '%');
            });
        }

        if ($request->route_code) {
            $route_code = $request->route_code;
            $orders_query->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', '%' . $route_code . '%');
            });
        }

        $orders = $orders_query->orderBy('id', 'desc')
            ->get();

        $all_orders = $orders_query->orderBy('id', 'desc')->paginate($request->page_size ?? 10);
        $orders = $all_orders->items();

        $pagination = array();
        $pagination['total_pages'] = $all_orders->lastPage();
        $pagination['current_page'] = (int)$all_orders->perPage();
        $pagination['total_records'] = $all_orders->total();

        // approval
        $results = GetWorkFlowRuleObject('Order');
        $approve_need_order = array();
        $approve_need_order_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_order[] = $raw['object']->raw_id;
                $approve_need_order_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $orders_array = array();
        if (is_object(collect($orders))) {
            foreach ($orders as $key => $order1) {
                if (in_array($orders[$key]->id, $approve_need_order)) {
                    $orders[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_order_object_id[$orders[$key]->id])) {
                        $orders[$key]->objectid = $approve_need_order_object_id[$orders[$key]->id];
                    } else {
                        $orders[$key]->objectid = '';
                    }
                } else {
                    $orders[$key]->need_to_approve = 'no';
                    $orders[$key]->objectid = '';
                }

                if ($orders[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || in_array($orders[$key]->id, $approve_need_order)) {
                    $orders_array[] = $orders[$key];
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
        //         if (isset($orders_array[$offset])) {
        //             $data_array[] = $orders_array[$offset];
        //         }
        //         $offset++;
        //     }

        //     $pagination['total_pages'] = ceil(count($orders_array) / $limit);
        //     $pagination['current_page'] = (int)$page;
        //     $pagination['total_records'] = count($orders_array);
        // } else {
        //     $data_array = $orders_array;
        // }
        return prepareResult(true, $orders_array, [], "Todays Orders listing", $this->success, $pagination);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "Error Please add Salesman"], "Error while validating order.", $this->unprocessableEntity);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating order.", $this->unprocessableEntity);
        }

        if ($request->source == 1 && $request->payment_term_id != "") {
            $validate = $this->validations($input, "addPayment");
            if ($validate["error"]) {
                return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating order.", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items"], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } elseif (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Order);
            }

            $order = new Order;
            if ($request->source == 1) {
                $repeat_number = codeCheck('Order', 'order_number', $request->order_number);
                if (is_object($repeat_number)) {
                    return prepareResult(false, [], ["error" => "This Order Number is already added"], "This Order Number is already added.", $this->unprocessableEntity);
                }

                $order->order_number = $request->order_number;
            } else {
                $order->order_number = nextComingNumber('App\Model\Order', 'order', 'order_number', $request->order_number);
            }

            $order->customer_id             = (!empty($request->customer_id)) ? $request->customer_id : null;
            $order->depot_id                = (!empty($request->depot_id)) ? $request->depot_id : null;
            $order->order_type_id           = $request->order_type_id;
            $order->order_date              = date('Y-m-d');
            $order->delivery_date           = $request->delivery_date;
            $order->salesman_id             = $request->salesman_id;
            $order->route_id                = (!empty($route_id)) ? $route_id : null;
            $order->reason_id               = $request->reason_id ?? null;
            $order->customer_lop            = (!empty($request->customer_lop)) ? $request->customer_lop : null;
            $order->payment_term_id         = $request->payment_term_id;
            $order->due_date                = $request->due_date;
            $order->total_qty               = $request->total_qty;
            $order->total_gross             = $request->total_gross;
            $order->total_discount_amount   = $request->total_discount_amount;
            $order->total_net               = $request->total_net;
            $order->total_vat               = $request->total_vat;
            $order->total_excise            = $request->total_excise;
            $order->grand_total             = $request->grand_total;
            $order->any_comment             = $request->any_comment;
            $order->source                  = $request->source;
            $order->status                  = $status;
            $order->current_stage           = $current_stage;
            $order->current_stage_comment   = $request->current_stage_comment;
            $order->approval_status         = "Created";
            $order->warehouse_id            = $request->warehouse_id;
            $order->lob_id                  = (!empty($request->lob_id)) ? $request->lob_id : null;
            $order->storage_location_id     = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $order->save();


            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    $orderDetail = new OrderDetail;
                    $orderDetail->order_id              = $order->id;
                    $orderDetail->item_id               = $item['item_id'];
                    $orderDetail->item_uom_id           = $item['item_uom_id'];
                    $orderDetail->original_item_uom_id  = $item['item_uom_id'];
                    $orderDetail->discount_id           = $item['discount_id'];
                    $orderDetail->is_free               = $item['is_free'];
                    $orderDetail->is_item_poi           = $item['is_item_poi'];
                    $orderDetail->promotion_id          = $item['promotion_id'];
                    $orderDetail->reason_id             = $item['reason_id'] ?? null;
                    $orderDetail->is_deleted            = (!empty($item['is_deleted'])) ? $item['is_deleted'] : 0;
                    $orderDetail->item_qty              = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->item_weight           = (!empty($item['item_weight'])) ? $item['item_weight'] : 0;
                    $orderDetail->item_price            = (!empty($item['item_price'])) ? $item['item_price'] : 0;
                    $orderDetail->item_gross            = (!empty($item['item_gross'])) ? $item['item_gross'] : 0;
                    $orderDetail->item_discount_amount  = (!empty($item['item_discount_amount'])) ? $item['item_discount_amount'] : 0;
                    $orderDetail->item_net              = (!empty($item['item_net'])) ? $item['item_net'] : 0;
                    $orderDetail->item_vat              = (!empty($item['item_vat'])) ? $item['item_vat'] : 0;
                    $orderDetail->item_excise           = (!empty($item['item_excise'])) ? $item['item_excise'] : 0;
                    $orderDetail->item_grand_total      = (!empty($item['item_grand_total'])) ? $item['item_grand_total'] : 0;
                    $orderDetail->original_item_qty     = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->save();
                }
            }

            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Order', $request, $order);
            }

            // if mobile order
            if (is_object($order) && $order->source == 1) {
                $user = User::find($request->user()->id);
                if (is_object($user)) {
                    $salesmanInfo = $user->salesmanInfo;
                    $smr = SalesmanNumberRange::where('salesman_id', $salesmanInfo->id)->first();
                    $smr->order_from = $request->order_number;
                    $smr->save();
                }
            }

            create_action_history("Order", $order->id, auth()->user()->id, "create", "Customer created by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            // backend
            if ($request->source != 1) {
                updateNextComingNumber('App\Model\Order', 'order');
            }

            DB::commit();
            $order->getSaveData();
            return prepareResult(true, $order, [], "Order added successfully", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
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

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating order.", $this->unauthorized);
        }

        $order = Order::with(array('customer' => function ($query) {
            $query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name',
                'orderDetails',
                'orderDetails.reason:id,name',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.item.itemMainPrice',
                'orderDetails.item.itemUomLowerUnit',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.item.itemUomLowerUnit',
                'depot:id,depot_name',
                'lob',
                'storageocation:id,code,name'
            )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($order)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }
        return prepareResult(true, $order, [], "Order Edit", $this->success);
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

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], "Error Please add Salesman", "Error while validating salesman", $this->unprocessableEntity);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order.", $this->unprocessableEntity);
        }

        if ($request->source == 1 && $request->payment_term_id != "") {
            $validate = $this->validations($input, "addPayment");
            if ($validate["error"]) {
                return prepareResult(false, [], $validate['errors']->first(), "Error while validating order", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }


        \DB::beginTransaction();
        try {

            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Order',$request);
            }

            $order = Order::where('uuid', $uuid)->first();

            //Delete old record
            OrderDetail::where('order_id', $order->id)->delete();

            $order->customer_id             = (!empty($request->customer_id)) ? $request->customer_id : null;
            $order->depot_id                = (!empty($request->depot_id)) ? $request->depot_id : null;
            $order->order_type_id           = $request->order_type_id;
            $order->order_number            = $request->order_number;
            $order->order_date              = date('Y-m-d');
            $order->delivery_date           = $request->delivery_date;
            $order->salesman_id             = $request->salesman_id;
            $order->route_id                = (!empty($route_id)) ? $route_id : null;
            $order->customer_lop            = (!empty($request->customer_lop)) ? $request->customer_lop : null;
            $order->payment_term_id         = $request->payment_term_id;
            $order->reason_id               = $request->reason_id ?? null;
            $order->due_date                = $request->due_date;
            $order->total_qty               = $request->total_qty;
            $order->total_gross             = $request->total_gross;
            $order->total_discount_amount   = $request->total_discount_amount;
            $order->total_net               = $request->total_net;
            $order->total_vat               = $request->total_vat;
            $order->total_excise            = $request->total_excise;
            $order->grand_total             = $request->grand_total;
            $order->any_comment             = $request->any_comment;
            $order->source                  = $request->source;
            $order->status                  = $status;
            $order->current_stage           = $current_stage;
            $order->warehouse_id            = $request->warehouse_id;
            $order->lob_id                  = (!empty($request->lob_id)) ? $request->lob_id : null;
            $order->storage_location_id     = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $order->approval_status         = "Updated";
            $order->save();

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    $orderDetail = new OrderDetail;
                    $orderDetail->order_id              = $order->id;
                    $orderDetail->item_id               = $item['item_id'];
                    $orderDetail->item_uom_id           = $item['item_uom_id'];
                    $orderDetail->original_item_uom_id  = (!empty($item['original_item_uom_id'])) ? $item['original_item_uom_id'] : $item['item_uom_id'];
                    $orderDetail->discount_id           = $item['discount_id'];
                    $orderDetail->is_free               = $item['is_free'];
                    $orderDetail->is_item_poi           = $item['is_item_poi'];
                    $orderDetail->promotion_id          = $item['promotion_id'];
                    $orderDetail->reason_id             = (($item['reason_id']) > 0) ? $item['reason_id'] : null;
                    $orderDetail->is_deleted            = (!empty($item['is_deleted'])) ? $item['is_deleted'] : 0;
                    $orderDetail->item_qty              = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->item_weight           = (!empty($item['item_weight'])) ? $item['item_weight'] : 0;
                    $orderDetail->item_price            = (!empty($item['item_price'])) ? $item['item_price'] : 0;
                    $orderDetail->item_gross            = (!empty($item['item_gross'])) ? $item['item_gross'] : 0;
                    $orderDetail->item_discount_amount  = (!empty($item['item_discount_amount'])) ? $item['item_discount_amount'] : 0;
                    $orderDetail->item_net              = (!empty($item['item_net'])) ? $item['item_net'] : 0;
                    $orderDetail->item_vat              = (!empty($item['item_vat'])) ? $item['item_vat'] : 0;
                    $orderDetail->item_excise           = (!empty($item['item_excise'])) ? $item['item_excise'] : 0;
                    $orderDetail->item_grand_total      = (!empty($item['item_grand_total'])) ? $item['item_grand_total'] : 0;
                    $orderDetail->original_item_qty     = (!empty($item['original_item_qty'])) ? $item['original_item_qty'] : 0;
                    $orderDetail->save();

                    // if (isset($orderDetail->reason_id) && config('app.current_domain') == "presale") {
                    if (isset($orderDetail->reason_id)) {
                        $this->orderLogs($order, $orderDetail);
                    }
                }
            }

            if ($isActivate = checkWorkFlowRule('Order', 'edit', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Order', $request, $order->id);
            }

            create_action_history("Order", $order->id, auth()->user()->id, "update", "Customer update by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            \DB::commit();
            $order->getSaveData();
            return prepareResult(true, $order, [], "Order updated successfully", $this->success);
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
            return prepareResult(false, [], [], "Error while validating order.", $this->unauthorized);
        }

        $order = Order::where('uuid', $uuid)
            ->first();

        if (is_object($order)) {
            $orderId = $order->id;
            $order->delete();
            if ($order) {
                OrderDetail::where('order_id', $orderId)->delete();
                Delivery::where('order_id', $orderId)->delete();
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
     * @param  \Illuminate\Http\Request $request
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order", $this->unprocessableEntity);
        }

        $action = $request->action;
        $uuids = $request->order_ids;
        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            foreach ($uuids as $uuid) {
                Order::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0
                ]);
            }
            $order = $this->index();
            return prepareResult(true, $order, [], "Region status updated", $this->success);
        } else if ($action == 'delete') {
            foreach ($uuids as $uuid) {
                $order = Order::where('uuid', $uuid)
                    ->first();
                $orderId = $order->id;
                $order->delete();
                if ($order) {
                    OrderDetail::where('order_id', $orderId)->delete();
                    Delivery::where('order_id', $orderId)->delete();
                }
            }
            $order = $this->index();
            return prepareResult(true, $order, [], "Region deleted success", $this->success);
        }
    }

    /**
     * this function is use for the store the order change and delete the qty
     */
    public function orderLogs($order, $orderDetail)
    {
        $orderLog = new OrderLog();
        $orderLog->changed_user_id  = request()->user()->id;
        $orderLog->order_id                 = $order->id;
        $orderLog->order_detail_id          = $orderDetail->id;
        $orderLog->customer_id              = $order->customer_id;
        $orderLog->salesman_id              = $order->salesman_id;
        $orderLog->item_id                  = $orderDetail->item_id;
        $orderLog->item_uom_id              = $orderDetail->item_uom_id;
        $orderLog->reason_id                = $orderDetail->reason_id;
        $orderLog->customer_code            = model($order->customerInfo, 'customer_code');
        $orderLog->customer_name            = model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname');
        $orderLog->salesman_code            = model($order->salesmanInfo, 'salesman_code');
        $orderLog->salesman_name            = model($order->salesman, 'firstname') . ' ' . model($order->salesman, 'lastname');
        $orderLog->item_name                = model($orderDetail->item, 'item_name');
        $orderLog->item_code                = model($orderDetail->item, 'item_code');
        $orderLog->item_uom                 = model($orderDetail->itemUom, 'name');
        $orderLog->item_qty                 = $orderDetail->item_qty;
        $orderLog->original_item_qty        = $orderDetail->original_item_qty ?? 0;
        $orderLog->action                   = ($orderDetail->is_deleted == 1) ? "deleted" : "change qty";
        $orderLog->reason                   = model($orderDetail->reasonType, 'name');
        $orderLog->save();
    }


    /**
     * Get price specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function itemApplyPrice(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "item-apply-price");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;

                //////////Default Price
                $getItemInfo = Item::where('id', $request->item_id)
                    ->first();

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        $item_excise = $getItemInfo->item_excise;
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                ////Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                if ($request->customer_id) {

                    //////////Check Price : different level
                    $getPricingList = PDPItem::select('p_d_p_items.id as p_d_p_item_id', 'price', 'combination_plan_key_id', 'price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                        ->join('price_disco_promo_plans', function ($join) {
                            $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                        })
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->where('price_disco_promo_plans.organisation_id', auth()->user()->organisation_id)
                        ->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC')
                        ->get();

                    // pre($getPricingList);
                    if ($getPricingList->count() > 0) {
                        $getKey = [];
                        $getDiscountKey = [];
                        foreach ($getPricingList as $key => $filterPrice) {
                            if ($filterPrice->use_for == 'Pricing') {
                                $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            } else {
                                $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            }
                        }

                        $useThisPrice = '';
                        foreach ($getKey as $checking) {
                            $usePrice = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $usePrice = true;
                                } else {
                                    $usePrice = false;
                                    break;
                                }
                            }

                            if ($usePrice) {
                                $useThisPrice = $checking['price'];
                                break;
                            }
                        }

                        $useThisType = '';
                        $useThisDiscountPercentage = '';
                        $useThisDiscountType = '';
                        $useThisDiscount = '';
                        $useThisDiscountQty = '';
                        $useThisDiscountApply = '';

                        foreach ($getDiscountKey as $checking) {
                            $useDiscount = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $useDiscount = true;
                                } else {
                                    $useDiscount = false;
                                    break;
                                }
                            }

                            if ($useDiscount) {
                                $is_discount = false;
                                $useThisType = $checking['type'];
                                $useThisDiscountType = $checking['discount_type'];
                                if ($checking['discount_type'] == 1) {
                                    $useThisDiscount = $checking['discount_value'];
                                }
                                if ($checking['discount_type'] == 2) {
                                    $useThisDiscountPercentage = $checking['discount_percentage'];
                                }
                                $useThisDiscountID = $checking['price_disco_promo_plan_id'];
                                $useThisDiscountQty = $checking['qty_to'];
                                $useThisDiscountApply = $checking['discount_apply_on'];
                                $is_discount = true;
                                break;
                            }
                        }

                        //return prepareResult(true, $checkKeyForPrice, [], "Item price.", $this->created);
                    }

                    $item_qty = $request->item_qty;
                    if ($lower_uom) {
                        $item_price = $itemPrice->lower_unit_item_price;
                    } else {
                        $item_price = $itemPrice->item_price;
                    }

                    if (isset($usePrice) && $usePrice) {
                        $item_price = $useThisPrice;
                    }

                    if (isset($useDiscount) && $useDiscount) {
                        // Slab

                        if ($useThisType == 2) {
                            $discount_slab = PDPDiscountSlab::where('price_disco_promo_plan_id', $useThisDiscountID)->get();
                            $slab_obj = '';
                            foreach ($discount_slab as $slab) {
                                if ($useThisDiscountApply == 1) {
                                    if (!$slab->max_slab) {
                                        if ($item_qty >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_qty >= $slab->min_slab && $item_qty <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                                if ($useThisDiscountApply == 2) {
                                    $item_gross = $item_qty * $item_price;
                                    if (!$slab->max_slab) {
                                        if ($item_gross >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_gross >= $slab->min_slab && $item_gross <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                            }
                            // slab value
                            if ($useThisDiscountType == 1) {
                                $discount = $slab_obj->value;
                                $discount_id = $useThisDiscountID;
                            }
                            // slab percentage
                            if ($useThisDiscountType == 2) {
                                $discount_id = $useThisDiscountID;
                                $item_gross = $item_qty * $item_price;
                                $discount = $item_gross * $slab_obj->percentage / 100;
                                $discount_per = $slab_obj->percentage;
                            }
                        } else {
                            // 1 is qty
                            if ($useThisDiscountApply == 1) {
                                if ($request->item_qty >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }

                            // 2 is value
                            if ($useThisDiscountApply == 2) {
                                $item_gross = $item_qty * $item_price;
                                if ($item_gross >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$request->customer_id) {
                    $item_qty = $request->item_qty;
                    $item_price = $itemPrice->item_price;
                }

                $item_gross = $item_qty * $item_price;
                $total_net = $item_gross - $discount;
                $item_excise = ($total_net * $item_excise) / 100;
                $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                $total = $total_net + $item_excise + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => $item_price,
                    'item_gross' => $item_gross,
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => $total_net,
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => $item_excise,
                    'total_vat' => $item_vat,
                    'total' => $total,
                ];
            }

            \DB::commit();
            return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function itemApplyPriceMultiple(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        if (empty($input))
            return prepareResult(false, [], [], "Error while validating empty data array", $this->unprocessableEntity);

        $totalItems = count($input);
        $itemPriceInfo = array();
        for ($i = 0; $i < $totalItems; $i++) {
            $validate = $this->validations($input[$i], "item-apply-price");
            if ($validate["error"])
                return prepareResult(false, [], [], "Error while validating data array", $this->unprocessableEntity);

            try {
                $retData = $this->singleItemApplyPrice((object)$input[$i]);
                if ($retData['status']) {
                    if (!empty($retData['itemPriceInfo']))
                        $itemPriceInfo[] = $retData['itemPriceInfo'];
                } else {
                    return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                }
            } catch (Throwable $exception) {
                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            }
        }

        return prepareResult(true, $itemPriceInfo, [], "Item prices.", $this->created);
    }

    private function singleItemApplyPrice($request)
    {

        \DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;

                //////////Default Price
                $getItemInfo = Item::where('id', $request->item_id)
                    ->first();

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        $item_excise = $getItemInfo->item_excise;
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                ////Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                if ($request->customer_id) {

                    //////////Check Price : different level
                    $getPricingList = PDPItem::select('p_d_p_items.id as p_d_p_item_id', 'price', 'combination_plan_key_id', 'price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                        ->join('price_disco_promo_plans', function ($join) {
                            $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                        })
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->where('price_disco_promo_plans.organisation_id', auth()->user()->organisation_id)
                        ->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC')
                        ->get();

                    // pre($getPricingList);
                    if ($getPricingList->count() > 0) {
                        $getKey = [];
                        $getDiscountKey = [];
                        foreach ($getPricingList as $key => $filterPrice) {
                            if ($filterPrice->use_for == 'Pricing') {
                                $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            } else {
                                $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            }
                        }

                        // $checkKeyForPrice = $this->arrayOrderDesc($getKey, 'hierarchyNumber');

                        $useThisPrice = '';
                        foreach ($getKey as $checking) {
                            $usePrice = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $usePrice = true;
                                } else {
                                    $usePrice = false;
                                    break;
                                }
                            }

                            if ($usePrice) {
                                $useThisPrice = $checking['price'];
                                break;
                            }
                        }

                        $useThisType = '';
                        $useThisDiscountPercentage = '';
                        $useThisDiscountType = '';
                        $useThisDiscount = '';
                        $useThisDiscountQty = '';
                        $useThisDiscountApply = '';

                        foreach ($getDiscountKey as $checking) {
                            $useDiscount = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $useDiscount = true;
                                } else {
                                    $useDiscount = false;
                                    break;
                                }
                            }

                            if ($useDiscount) {
                                $is_discount = false;
                                $useThisType = $checking['type'];
                                $useThisDiscountType = $checking['discount_type'];
                                if ($checking['discount_type'] == 1) {
                                    $useThisDiscount = $checking['discount_value'];
                                }
                                if ($checking['discount_type'] == 2) {
                                    $useThisDiscountPercentage = $checking['discount_percentage'];
                                }
                                $useThisDiscountID = $checking['price_disco_promo_plan_id'];
                                $useThisDiscountQty = $checking['qty_to'];
                                $useThisDiscountApply = $checking['discount_apply_on'];
                                $is_discount = true;
                                break;
                            }
                        }

                        //return prepareResult(true, $checkKeyForPrice, [], "Item price.", $this->created);
                    }

                    $item_qty = $request->item_qty;
                    if ($lower_uom) {
                        $item_price = $itemPrice->lower_unit_item_price;
                    } else {
                        $item_price = $itemPrice->item_price;
                    }

                    if (isset($usePrice) && $usePrice) {
                        $item_price = $useThisPrice;
                    }

                    if (isset($useDiscount) && $useDiscount) {
                        // Slab

                        if ($useThisType == 2) {
                            $discount_slab = PDPDiscountSlab::where('price_disco_promo_plan_id', $useThisDiscountID)->get();
                            $slab_obj = '';
                            foreach ($discount_slab as $slab) {
                                if ($useThisDiscountApply == 1) {
                                    if (!$slab->max_slab) {
                                        if ($item_qty >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_qty >= $slab->min_slab && $item_qty <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                                if ($useThisDiscountApply == 2) {
                                    $item_gross = $item_qty * $item_price;
                                    if (!$slab->max_slab) {
                                        if ($item_gross >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_gross >= $slab->min_slab && $item_gross <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                            }
                            // slab value
                            if ($useThisDiscountType == 1) {
                                $discount = $slab_obj->value;
                                $discount_id = $useThisDiscountID;
                            }
                            // slab percentage
                            if ($useThisDiscountType == 2) {
                                $discount_id = $useThisDiscountID;
                                $item_gross = $item_qty * $item_price;
                                $discount = $item_gross * $slab_obj->percentage / 100;
                                $discount_per = $slab_obj->percentage;
                            }
                        } else {
                            // 1 is qty
                            if ($useThisDiscountApply == 1) {
                                if ($request->item_qty >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }

                            // 2 is value
                            if ($useThisDiscountApply == 2) {
                                $item_gross = $item_qty * $item_price;
                                if ($item_gross >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$request->customer_id) {
                    $item_qty = $request->item_qty;
                    $item_price = $itemPrice->item_price;
                }

                $item_gross = $item_qty * $item_price;
                $total_net = $item_gross - $discount;
                $item_excise = ($total_net * $item_excise) / 100;
                $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                $total = $total_net + $item_excise + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => $item_price,
                    'item_gross' => $item_gross,
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => $total_net,
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => $item_excise,
                    'total_vat' => $item_vat,
                    'total' => $total,
                ];
            }

            \DB::commit();
            $retArray['status'] = true;
            $retArray['itemPriceInfo'] = $itemPriceInfo;
        } catch (\Exception $exception) {
            $retArray['status'] = false;
        } catch (\Throwable $exception) {
            $retArray['status'] = false;
        }

        return $retArray;
    }

    private function makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $combination_key_code, $combination_key, $price_disco_promo_plan_id, $p_d_p_item_id, $price, $priority_sequence)
    {
        $keyCodes = '';
        $combination_actual_id = '';
        foreach (explode('/', $combination_key_code) as $hierarchyNumber) {
            switch ($hierarchyNumber) {
                case '1':
                    if (empty($add)) {
                        $add = $customerCountry;
                    } else {
                        $add = '/' . $customerCountry;
                    }
                    // $add  = $customerCountry;
                    break;
                case '2':
                    if (empty($add)) {
                        $add = $customerRegion;
                    } else {
                        $add = '/' . $customerRegion;
                    }
                    // $add  = '/' . $customerRegion;
                    break;
                case '3':
                    if (empty($add)) {
                        $add = $customerArea;
                    } else {
                        $add = '/' . $customerArea;
                    }
                    // $add  = '/' . $customerArea;
                    break;
                case '4':
                    if (empty($add)) {
                        $add = $customerRoute;
                    } else {
                        $add = '/' . $customerRoute;
                    }
                    // $add  = '/' . $customerRoute;
                    break;
                case '5':
                    if (empty($add)) {
                        $add = $customerSalesOrganisation;
                    } else {
                        $add = '/' . $customerSalesOrganisation;
                    }
                    break;
                case '6':
                    if (empty($add)) {
                        $add = $customerChannel;
                    } else {
                        $add = '/' . $customerChannel;
                    }
                    // $add  = '/' . $customerChannel;
                    break;
                case '7':
                    if (empty($add)) {
                        $add = $customerCustomerCategory;
                    } else {
                        $add = '/' . $customerCustomerCategory;
                    }
                    // $add  = '/' . $customerCustomerCategory;
                    break;
                case '8':
                    if (empty($add)) {
                        $add = $customerCustomer;
                    } else {
                        $add = '/' . $customerCustomer;
                    }
                    // $add  = '/' . $customerCustomer;
                    break;
                case '9':
                    if (empty($add)) {
                        $add = $itemMajorCategory;
                    } else {
                        $add = '/' . $itemMajorCategory;
                    }
                    // $add  = '/' . $itemMajorCategory;
                    break;
                case '10':
                    if (empty($add)) {
                        $add = $itemItemGroup;
                    } else {
                        $add = '/' . $itemItemGroup;
                    }
                    // $add  = '/' . $itemItemGroup;
                    break;
                case '11':
                    if (empty($add)) {
                        $add = $item;
                    } else {
                        $add = '/' . $item;
                    }
                    // $add  = '/' . $item;
                    break;
                default:
                    # code...
                    break;
            }
            $keyCodes .= $hierarchyNumber;
            $combination_actual_id .= $add;
        }

        $getIdentify = PriceDiscoPromoPlan::find($price_disco_promo_plan_id);

        $discount = array();
        if ($getIdentify->use_for == 'Promotion') {
            return array(
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
                'auto_sequence_by_code' => $hierarchyNumber,
                'hierarchyNumber' => $keyCodes,
                'p_d_p_promotion_items' => $p_d_p_item_id,
                'priority_sequence' => $priority_sequence,
                'price' => $price,
                'use_for' => $getIdentify->use_for
            );
        }

        if ($getIdentify->use_for == 'Discount') {
            return array(
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
                // 'auto_sequence_by_depth' => explode('/', $combination_key_code),
                // 'auto_sequence_by_depth_count' => count(explode('/', $combination_key_code)),
                'auto_sequence_by_code' => $hierarchyNumber,
                'hierarchyNumber' => $keyCodes,
                'p_d_p_item_id' => $p_d_p_item_id,
                'priority_sequence' => $priority_sequence,
                'price' => $price,
                'use_for' => $getIdentify->use_for,
                'type' => $getIdentify->type,
                'qty_from' => $getIdentify->qty_from,
                'qty_to' => $getIdentify->qty_to,
                'discount_type' => $getIdentify->discount_type,
                'discount_value' => $getIdentify->discount_value,
                'discount_percentage' => $getIdentify->discount_percentage,
                'discount_apply_on' => $getIdentify->discount_apply_on
            );
        }

        $returnData = [
            'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
            'combination_key' => $combination_key,
            'combination_key_code' => $combination_key_code,
            'combination_actual_id' => $combination_actual_id,
            // 'auto_sequence_by_depth' => explode('/', $combination_key_code),
            // 'auto_sequence_by_depth_count' => count(explode('/', $combination_key_code)),
            'auto_sequence_by_code' => $hierarchyNumber,
            'hierarchyNumber' => $keyCodes,
            'p_d_p_item_id' => $p_d_p_item_id,
            'priority_sequence' => $priority_sequence,
            'price' => $price,
            'use_for' => $getIdentify->use_for
        ];

        return $returnData;
    }

    private function arrayOrderDesc()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            // if (is_string($field)) {
            //     $tmp = array();
            //     foreach ($data as $key => $row)
            //         $tmp[$key] = $row[$field];
            //     $args[$n] = $tmp;
            // }
            foreach ($data as $key => $row) {
                $return_fare[$n] = $row[$field];
                $one_way_fare[$n] = $row['priority_sequence'];
            }
        }
        $sorted = array_multisort(
            array_column($data, 'hierarchyNumber'),
            SORT_ASC,
            array_column($data, 'priority_sequence'),
            SORT_DESC,
            $data
        );

        return $data;
        // $sorted = array_multisort($data, 'one_way_fare', SORT_ASC, 'return_fare', SORT_DESC);
        // $args[] = &$data;
        // call_user_func_array('array_multisort', $args);
        // return array_pop($args);
    }

    private function checkDataExistOrNot($combination_key_number, $combination_actual_id, $price_disco_promo_plan_id)
    {
        switch ($combination_key_number) {
            case '1':
                $model = 'App\Model\PDPCountry';
                $field = 'country_id';
                break;
            case '2':
                $model = 'App\Model\PDPRegion';
                $field = 'region_id';
                break;
            case '3':
                $model = 'App\Model\PDPArea';
                $field = 'area_id';
                break;
            case '4':
                $model = 'App\Model\PDPRoute';
                $field = 'route_id';
                break;
            case '5':
                $model = 'App\Model\PDPSalesOrganisation';
                $field = 'sales_organisation_id';
                break;
            case '6':
                $model = 'App\Model\PDPChannel';
                $field = 'channel_id';
                break;
            case '7':
                $model = 'App\Model\PDPCustomerCategory';
                $field = 'customer_category_id';
                break;
            case '8':
                $model = 'App\Model\PDPCustomer';
                $field = 'customer_id';
                break;
            case '9':
                $model = 'App\Model\PDPItemMajorCategory';
                $field = 'item_major_category_id';
                break;
            case '10':
                $model = 'App\Model\PDPItemGroup';
                $field = 'item_group_id';
                break;
            case '11':
                $model = 'App\Model\PDPItem';
                $field = 'item_id';
                break;
            default:
                $model = '';
                $field = '';
                break;
        }

        $checkExistOrNot = $model::where('price_disco_promo_plan_id', $price_disco_promo_plan_id)->where($field, $combination_actual_id)->first();

        // pre($checkExistOrNot);

        if ($checkExistOrNot) {
            return true;
        }

        return false;
    }

    private function getListByParam($obj, $param)
    {
        $object = $obj;
        $array = [];
        $get = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($object), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($get as $key => $value) {
            if ($key === $param) {
                $array = array_merge($array, $value);
            }
        }
        return $array;
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'order_type_id' => 'required|integer|exists:order_types,id',
                'order_number' => 'required',
                'due_date' => 'required|date',
                'delivery_date' => 'required|date',
                'total_qty' => 'required',
                'total_discount_amount' => 'required',
                'total_vat' => 'required',
                'total_net' => 'required',
                'total_excise' => 'required',
                'grand_total' => 'required',
                'source' => 'required|integer',
            ]);
        }

        if ($type == "addPayment") {
            $validator = \Validator::make($input, [
                'payment_term_id' => 'required|integer|exists:payment_terms,id'
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'order_ids' => 'required'
            ]);
        }

        if ($type == 'item-apply-price') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'normal-item-apply-price') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'applyPDP') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id'
                // 'item_uom_id'   => 'required|integer|exists:item_uoms,id',
                // 'item_qty'      => 'required|numeric',
            ]);
        }

        if ($type == 'cancel') {
            $validator = \Validator::make($input, [
                'order_id' => 'required|integer|exists:orders,id',
                'reason_id' => 'required|integer|exists:reason_types,id',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * Get price specified item and item UOM.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function normalItemApplyPrice(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "normal-item-apply-price");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;

                //////////Default Price
                $getItemInfo = Item::where('id', $request->item_id)
                    ->first();

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        $item_excise = $getItemInfo->item_excise;
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                ////Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                // if (!$request->customer_id) {
                if ($lower_uom) {
                    $item_price = $itemPrice->lower_unit_item_price;
                } else {
                    $item_price = $itemPrice->item_price;
                }
                $item_qty = $request->item_qty;
                // $item_price     = $itemPrice->item_price;
                // }

                $item_gross = $item_qty * $item_price;
                $total_net = $item_gross - $discount;
                $item_excise = ($total_net * $item_excise) / 100;
                $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                $total = $total_net + $item_excise + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => $item_price,
                    'item_gross' => $item_gross,
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => $total_net,
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => $item_excise,
                    'total_vat' => $item_vat,
                    'total' => $total,
                ];
            }

            \DB::commit();
            return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Get price specified item and item UOM.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function itemApplyPromotion(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (is_array($request->item_id) && sizeof($request->item_id) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (is_array($request->item_uom_id) && sizeof($request->item_uom_id) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items UOM.", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $itemPromotionInfo = [];
            $offerItems = [];
            $item_vat_percentage = 0;
            $item_excise = 0;
            $getTotal = 0;
            $discount = 0;
            $discount_id = 0;
            $discount_per = 0;

            $itemPrice = ItemMainPrice::whereIn('item_id', $request->item_id)
                ->whereIn('item_uom_id', $request->item_uom_id)
                ->get();

            if (count($itemPrice)) {
                $getItemInfo = Item::whereIn('id', $request->item_id)
                    ->get();
            }

            if ($request->customer_id) {
                //Get Customer Info
                $getCustomerInfo = CustomerInfo::find($request->customer_id);
                //Location
                $customerCountry = $getCustomerInfo->user->country_id; //1
                $customerRegion = $getCustomerInfo->region_id; //2
                $customerRoute = $getCustomerInfo->route_id; //4

                //Customer
                $getAreaFromRoute = Route::find($customerRoute);
                $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                $customerChannel = $getCustomerInfo->channel_id; //6
                $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                $customerCustomer = $getCustomerInfo->id; //8
            }

            if ($request->customer_id) {

                $getPricingList = PDPPromotionItem::select('p_d_p_promotion_items.id as p_d_p_promotion_items_id', 'p_d_p_promotion_items.price_disco_promo_plan_id', 'p_d_p_promotion_items.item_id', 'p_d_p_promotion_items.item_uom_id', 'p_d_p_promotion_items.item_qty', 'p_d_p_promotion_items.price', 'combination_plan_key_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'priority_sequence', 'use_for')
                    ->join('price_disco_promo_plans', function ($join) {
                        $join->on('p_d_p_promotion_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                    })
                    ->join('combination_plan_keys', function ($join) {
                        $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                    })
                    ->whereIn('item_id', $request->item_id)
                    ->whereIn('item_uom_id', $request->item_uom_id)
                    ->whereIn('item_qty', $request->item_qty)
                    ->where('price_disco_promo_plans.organisation_id', auth()->user()->organisation_id)
                    ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.status', 1)
                    ->where('combination_plan_keys.status', 1)
                    ->orderBy('price_disco_promo_plans.priority_sequence', 'ASC')
                    ->orderBy('combination_plan_keys.combination_key_code', 'DESC')
                    ->get();

                if ($getPricingList->count() > 0) {
                    $getKey = [];
                    $getDiscountKey = [];
                    foreach ($getPricingList as $key => $filterPrice) {
                        $items = Item::where('id', $filterPrice->item_id)->first();
                        $itemMajorCategory = $items->item_major_category_id; //9
                        $itemItemGroup = $items->item_group_id; //10
                        $item = $items->id; //11

                        $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_promotion_items_id, $filterPrice->price, $filterPrice->priority_sequence);
                    }

                    $result = array();
                    $price_disco_promo_plan_id = '';
                    foreach ($getKey as $element) {
                        if ($price_disco_promo_plan_id != $element['price_disco_promo_plan_id']) {
                            $price_disco_promo_plan_id = $element['price_disco_promo_plan_id'];
                            $result[] = $element;
                        }
                    }

                    // CHeck order item and offer item
                    foreach ($result as $checking) {
                        $usePromotion = false;
                        foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                            $combination_actual_id = explode('/', $checking['combination_actual_id']);
                            $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                            if ($isFind) {
                                $usePromotion = true;
                            } else {
                                $usePromotion = false;
                                break;
                            }
                        }

                        if ($checking['price_disco_promo_plan_id']) {

                            $price_disco_promo_plan = PriceDiscoPromoPlan::where('id', $checking['price_disco_promo_plan_id'])
                                ->with('PDPPromotionItems', 'PDPPromotionItems.item', 'PDPPromotionItems.itemUom')
                                ->first();

                            $is_promotion = false;
                            $promotion_item = array();
                            $PDPPromotionItems = $price_disco_promo_plan->PDPPromotionItems;

                            $price_disco_promo_plan_offer = PriceDiscoPromoPlan::where('id', $checking['price_disco_promo_plan_id'])
                                ->with('PDPPromotionOfferItems', 'PDPPromotionOfferItems.item', 'PDPPromotionOfferItems.itemUom:id,name')
                                ->first();

                            foreach ($PDPPromotionItems as $key => $item) {
                                $qty = $request->item_qty[$key];
                                if ($item->item_qty == $request->item_qty[$key]) {
                                    $is_promotion = true;
                                    $offerItems = $price_disco_promo_plan_offer->PDPPromotionOfferItems;
                                    $item_price = $item->price;
                                    $item_qty = $qty;
                                    $item_gross = $item_qty * $item_price;
                                    $total_net = $item_gross - $discount;
                                    $item_excise = ($total_net * $item_excise) / 100;
                                    $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                                    $total = $total_net + $item_excise + $item_vat;

                                    $itemPromotionInfo[] = [
                                        'item_price' => $item_price,
                                        'item_gross' => $item_gross,
                                        'discount' => $discount,
                                        'total_net' => $total_net,
                                        'is_free' => true,
                                        'is_item_poi' => false,
                                        'order_item_type' => $price_disco_promo_plan->order_item_type,
                                        'offer_item_type' => $price_disco_promo_plan->offer_item_type,
                                        'promotion_id' => $item->id,
                                        'total_excise' => $item_excise,
                                        'total_vat' => $item_vat,
                                        'total' => $total,
                                    ];
                                }
                            }
                        }
                    }
                    if (is_array($offerItems) && sizeof($offerItems) < 1) {
                        $offerItems = $offerItems->pluck('item')->toArray();
                    }
                }
            }

            $offerData = array('offer_items' => $offerItems, 'itemPromotionInfo' => $itemPromotionInfo);

            \DB::commit();
            return prepareResult(true, $offerData, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
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
            'order_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate order import", $this->unauthorized);
        }

        Excel::import(new OrderImport, request()->file('order_file'));
        return prepareResult(true, [], [], "Order successfully imported", $this->success);
    }

    /**
     * This funciton is use for the cancel the order and put it the reason
     *
     * @param Request $request
     * @param mixed $reason_id
     * @param mixed $order_id
     * @return void
     * Hardik Solanki - 24-05
     */
    public function cancel(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "cancel");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order cancel", $this->unprocessableEntity);
        }

        $order = Order::find($request->order_id);
        if ($order) {

            $order->reason_id       = $request->reason_id;
            $order->current_stage   = "";
            $order->save();

            return prepareResult(true, $order, [], "Order cancelled", $this->success);
        }


            /**
     * This function is generate the delivery on order id
     * @param array $order_id
     */

    public function orderToPicking(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->order_ids)) {
            return prepareResult(false, [], ["error" => "Order id is not added."], "Order id is not added.", $this->unauthorized);
        }

        $orders = Order::whereIn('id', $request->order_ids)
            ->where('current_stage', "Approved")
            ->whereIn('approval_status', ["Created", "Updated", "Partial-Delivered", "Delivered"])
            ->get();

        if (count($orders)) {
            foreach ($orders as $order) {
                $variable = "delivery";
                $nextComingNumber['number_is'] = null;
                $nextComingNumber['prefix_is'] = null;
                if (CodeSetting::count() > 0) {
                    $code_setting = CodeSetting::first();
                    if ($code_setting['is_final_update_' . $variable] == 1) {
                        $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                        $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
                    } else {
                        $code_setting['is_code_auto_' . $variable]     = "1";
                        $code_setting['prefix_code_' . $variable]      = "DELV0";
                        $code_setting['start_code_' . $variable]       = "00001";
                        $code_setting['next_coming_number_' . $variable] = "DELV000001";
                        $code_setting['is_final_update_' . $variable]  = "1";
                        $code_setting->save();

                        $nextComingNumber = "DELV000001";
                    }
                }

                $code = $nextComingNumber;

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

                    $delivery = new Delivery();
                    $delivery->delivery_number          = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $code);
                    $delivery->order_id                 = $order->id;
                    $delivery->customer_id              = $order->customer_id;
                    $delivery->salesman_id              = null;
                    $delivery->reason_id                = null;
                    $delivery->route_id                 = null;
                    $delivery->storage_location_id      = (!empty($order->storage_location_id)) ? $order->storage_location_id : null;
                    $delivery->warehouse_id             = (!empty($request->warehouse_id)) ? $request->warehouse_id : 0;
                    $delivery->delivery_type            = $order->order_type_id;
                    $delivery->delivery_type_source     = 2;
                    $delivery->delivery_date            = $order->delivery_date;
                    $delivery->delivery_time            = (isset($order->delivery_time)) ? $order->delivery_time : date('H:m:s');
                    $delivery->delivery_weight          = $order->delivery_weight;
                    $delivery->payment_term_id          = $order->payment_term_id;
                    $delivery->total_qty                = $order->total_qty;
                    $delivery->total_gross              = $order->total_gross;
                    $delivery->total_discount_amount    = $order->total_discount_amount;
                    $delivery->total_net                = $order->total_net;
                    $delivery->total_vat                = $order->total_vat;
                    $delivery->total_excise             = $order->total_excise;
                    $delivery->grand_total              = $order->grand_total;
                    $delivery->current_stage_comment    = $order->current_stage_comment;
                    $delivery->delivery_due_date        = $order->due_date;
                    $delivery->source                   = $order->source;
                    $delivery->status                   = $status;
                    $delivery->current_stage            = $current_stage;
                    $delivery->approval_status          = "Created";
                    $delivery->lob_id                   = (!empty($order->lob_id)) ? $order->lob_id : null;
                    $delivery->save();

                    if (count($order->orderDetailsWithoutDelete)) {
                        foreach ($order->orderDetailsWithoutDelete as $od) {
                            //save DeliveryDetail

                            $deliveryDetail = new DeliveryDetail();
                            $deliveryDetail->id                     = $od->id;
                            $deliveryDetail->delivery_id            = $delivery->id;
                            $deliveryDetail->salesman_id            = null;
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
                            $deliveryDetail->save();
                        }
                    }

                    if ($isActivate = checkWorkFlowRule('Delivery', 'create', $current_organisation_id)) {
                        $this->createWorkFlowObject($isActivate, 'Delivery', $order, $delivery);
                    }

                    DB::commit();

                    $order->sync_status     = NULL;
                    $order->approval_status = "Picking Confirmed";
                    $order->save();

                    updateNextComingNumber('App\Model\Delivery', 'delivery');

                    // return prepareResult(true, $delivery, [], "Delivery added successfully.", $this->success);
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

        return prepareResult(true, [], [], "Delivery created successfully.", $this->success);
    }

    /**
     * This function is generate the delivery on order id
     * @param array $order_id
     */

    public function orderToPicking(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->order_ids)) {
            return prepareResult(false, [], ["error" => "Order id is not added."], "Order id is not added.", $this->unauthorized);
        }

        $orders = Order::whereIn('id', $request->order_ids)
            ->where('current_stage', "Approved")
            ->whereIn('approval_status', ["Created", "Updated", "Partial-Delivered", "Delivered"])
            ->get();

        if (count($orders)) {
            foreach ($orders as $order) {
                $variable = "delivery";
                $nextComingNumber['number_is'] = null;
                $nextComingNumber['prefix_is'] = null;
                if (CodeSetting::count() > 0) {
                    $code_setting = CodeSetting::first();
                    if ($code_setting['is_final_update_' . $variable] == 1) {
                        $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                        $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
                    } else {
                        $code_setting['is_code_auto_' . $variable]     = "1";
                        $code_setting['prefix_code_' . $variable]      = "DELV0";
                        $code_setting['start_code_' . $variable]       = "00001";
                        $code_setting['next_coming_number_' . $variable] = "DELV000001";
                        $code_setting['is_final_update_' . $variable]  = "1";
                        $code_setting->save();

                        $nextComingNumber = "DELV000001";
                    }
                }

                $code = $nextComingNumber;

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

                    $delivery = new Delivery();
                    $delivery->delivery_number          = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $code);
                    $delivery->order_id                 = $order->id;
                    $delivery->customer_id              = $order->customer_id;
                    $delivery->salesman_id              = null;
                    $delivery->reason_id                = null;
                    $delivery->route_id                 = null;
                    $delivery->storage_location_id      = (!empty($order->storage_location_id)) ? $order->storage_location_id : null;
                    $delivery->warehouse_id             = (!empty($request->warehouse_id)) ? $request->warehouse_id : 0;
                    $delivery->delivery_type            = $order->order_type_id;
                    $delivery->delivery_type_source     = 2;
                    $delivery->delivery_date            = $order->delivery_date;
                    $delivery->delivery_time            = (isset($order->delivery_time)) ? $order->delivery_time : date('H:m:s');
                    $delivery->delivery_weight          = $order->delivery_weight;
                    $delivery->payment_term_id          = $order->payment_term_id;
                    $delivery->total_qty                = $order->total_qty;
                    $delivery->total_gross              = $order->total_gross;
                    $delivery->total_discount_amount    = $order->total_discount_amount;
                    $delivery->total_net                = $order->total_net;
                    $delivery->total_vat                = $order->total_vat;
                    $delivery->total_excise             = $order->total_excise;
                    $delivery->grand_total              = $order->grand_total;
                    $delivery->current_stage_comment    = $order->current_stage_comment;
                    $delivery->delivery_due_date        = $order->due_date;
                    $delivery->source                   = $order->source;
                    $delivery->status                   = $status;
                    $delivery->current_stage            = $current_stage;
                    $delivery->approval_status          = "Created";
                    $delivery->lob_id                   = (!empty($order->lob_id)) ? $order->lob_id : null;
                    $delivery->save();

                    if (count($order->orderDetailsWithoutDelete)) {
                        foreach ($order->orderDetailsWithoutDelete as $od) {
                            //save DeliveryDetail

                            $deliveryDetail = new DeliveryDetail();
                            $deliveryDetail->id                     = $od->id;
                            $deliveryDetail->delivery_id            = $delivery->id;
                            $deliveryDetail->salesman_id            = null;
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
                            $deliveryDetail->save();
                        }
                    }

                    if ($isActivate = checkWorkFlowRule('Delivery', 'create', $current_organisation_id)) {
                        $this->createWorkFlowObject($isActivate, 'Delivery', $order, $delivery);
                    }

                    DB::commit();

                    $order->sync_status     = NULL;
                    $order->approval_status = "Picking Confirmed";
                    $order->save();

                    updateNextComingNumber('App\Model\Delivery', 'delivery');

                    // return prepareResult(true, $delivery, [], "Delivery added successfully.", $this->success);
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

        return prepareResult(true, [], [], "Delivery created successfully.", $this->success);
    }
}
