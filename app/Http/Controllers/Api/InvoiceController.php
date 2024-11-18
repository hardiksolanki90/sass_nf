<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\InvoiceImport;
use App\Imports\InvoiceDetailImport;
use App\Model\CollectionDetails;
use App\Model\CustomerInfo;
use App\Model\CustomerRoute;
use App\Model\Delivery;
use App\Model\DeliveryDetail;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\ItemUom;
use App\Model\Order;
use App\Model\Route;
use App\Model\Storagelocation;
use App\Model\StoragelocationDetail;
use App\Model\VehicleUtilisation;
use App\Model\Warehouse;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowRuleApprovalUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\SalesmanInfo;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
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

        $invoices_query = Invoice::with(array('user' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'user:id,parent_id,firstname,lastname,email',
                'user.customerInfo:id,user_id,customer_code',
                'salesmanUser:id,parent_id,firstname,lastname,email',
                'salesmanUser.salesmanInfo:id,user_id,salesman_code',
                'route:id,route_name,route_code',
                'depot:id,depot_code,depot_name',
                'order',
                'order.orderDetails',
                'storagelocation:id,name,code',
                'invoices',
                'invoices.item:id,item_name,item_code,lower_unit_uom_id',
                'invoices.itemUom:id,name,code',
                'orderType:id,name,description',
                'invoiceReminder:id,uuid,is_automatically,message,invoice_id',
                'invoiceReminder.invoiceReminderDetails',
                'lob'
            );

        if ($request->invoice_date) {
            $invoices_query->whereDate('created_at', $request->invoice_date);
        }

        if ($request->branch_plant_code) {
            $bc = $request->branch_plant_code;
            $invoices_query->whereHas('storagelocation', function ($q) use ($bc) {
                $q->where('code', $bc);
            });
        }

        if ($request->invoice_number) {
            $invoices_query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        if ($request->approval != '') {
            $invoices_query->where('status', $request->approval);
        }

        if ($request->status) {
            $invoices_query->where('current_stage', 'like', '%' . $request->status . '%');
        }

        if ($request->invoice_due_date) {
            $invoices_query->whereDate('invoice_due_date', $request->invoice_due_date);
        }

        if ($request->customer_name) {
            $name = $request->customer_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $invoices_query->whereHas('user', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $invoices_query->whereHas('user', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $customer_code = $request->customer_code;
            $invoices_query->whereHas('user.customerInfo', function ($q) use ($customer_code) {
                $q->where('customer_code', 'like', '%' . $customer_code . '%');
            });
        }

        if ($request->order_number) {
            $order_number = $request->order_number;
            $invoices_query->whereHas('order', function ($q) use ($order_number) {
                $q->where('order_number', 'like', '%' . $order_number . '%');
            });
        }

        if ($request->customer_name) {
            $name = $request->customer_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $invoices_query->whereHas('salesmanUser', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $invoices_query->whereHas('salesmanUser', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $invoices_query->whereHas('salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', '%' . $salesman_code . '%');
            });
        }

        if ($request->route_name) {
            $route_name = $request->route_name;
            $invoices_query->whereHas('route', function ($q) use ($route_name) {
                $q->where('route_name', 'like', '%' . $route_name . '%');
            });
        }

        if ($request->route_code) {
            $route_code = $request->route_code;
            $invoices_query->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', '%' . $route_code . '%');
            });
        }

        if (config('app.current_domain') == "presales") {
            $user_branch_plant = $request->user()->userBranchPlantAssing;
            if (count($user_branch_plant)) {
                $storage_id = $user_branch_plant->pluck('storage_location_id')->toArray();
                $invoices_query->whereIn('storage_location_id', $storage_id);
            }
        }

        if ($request->erp_status) {

            if ($request->erp_status == "Not Posted") {
                $invoices_query->whereNotNull('odoo_failed_response')
                    ->where(function ($query) {
                        $query->where('oddo_post_id', 0)
                            ->orWhereNull('oddo_post_id');
                    });
            }

            if ($request->erp_status == "Failed") {
                $invoices_query->whereNotNull('oddo_post_id')
                    ->whereNotNull('odoo_failed_response');
            }

            if ($request->erp_status == "Posted") {
                $invoices_query->whereNotNull('oddo_post_id')
                    ->whereNull('odoo_failed_response');
            }
        }

        $all_user = $invoices_query->orderBy('id', 'desc')->paginate((!empty($request->page_size) ? $request->page_size : $this->paginate));
        $invoices = $all_user->items();

        $pagination = array();
        $pagination['total_pages'] = $all_user->lastPage();
        $pagination['current_page'] = (int) $all_user->perPage();
        $pagination['total_records'] = $all_user->total();

        // approval
        $results = GetWorkFlowRuleObject('Invoice', $all_user->pluck('id')->toArray());
        $approve_need_invoice = array();
        $approve_need_invoice_detail_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_invoice[] = $raw['object']->raw_id;
                $approve_need_invoice_detail_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        //  approval
        $invoices_array = array();
        if (count($invoices)) {
            foreach ($invoices as $key => $invoices1) {
                if (in_array($invoices[$key]->id, $approve_need_invoice)) {
                    $invoices[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_invoice_detail_object_id[$invoices[$key]->id])) {
                        $invoices[$key]->objectid = $approve_need_invoice_detail_object_id[$invoices[$key]->id];
                    } else {
                        $invoices[$key]->objectid = '';
                    }
                } else {
                    $invoices[$key]->need_to_approve = 'no';
                    $invoices[$key]->objectid = '';
                }

                if ($invoices[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || in_array($invoices[$key]->id, $approve_need_invoice)) {
                    $invoices_array[] = $invoices[$key];
                }
            }
        }

        return prepareResult(true, $invoices_array, [], "Invoices listing", $this->success, $pagination);
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one salesman."], "Error while validating invoice.", $this->unprocessableEntity);
            // return prepareResult(false, [], "Error Please add Salesman", "Error while validating invoice", $this->unprocessableEntity);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating invoice", $this->unprocessableEntity);
        }
        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items."], "Error while validating invoice.", $this->unprocessableEntity);
        }

        $route_id = $request->route_id;
        // if (!empty($request->route_id)) {
        // } else if (!empty($request->salesman_id)) {
        //     $route_id = getRouteBySalesman($request->salesman_id);
        // }

        DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Invoice', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Deliviery',$request);
            }

            $order_type_id = $request->order_type_id;
            $order_id = $request->order_id;
            $delivery_id = $request->delivery_id;

            // changed on 13-09 by sugnesh
            // if ($request->invoice_type == 1) {
            //     $order_id = null;
            //     $delivery_id = null;
            // }

            $invoice = new Invoice;
            if ($request->source == 1) {
                $repeat_number = codeCheck('Invoice', 'invoice_number', $request->invoice_number, 'invoice_date');
                if (is_object($repeat_number)) {
                    return prepareResult(true, $repeat_number, [], 'Record saved', $this->success);
                } else {
                    $repeat_number = codeCheck('Invoice', 'invoice_number', $request->invoice_number);
                    if (is_object($repeat_number)) {
                        return prepareResult(false, [], ["error" => "This Invoice Number " . $request->invoice_number . " is already added."], "This Invoice Number is already added.", $this->unprocessableEntity);
                    }
                }

                $invoice->invoice_number = $request->invoice_number;
            } else {
                $invoice->invoice_number = nextComingNumber('App\Model\Invoice', 'invoice', 'invoice_number', $request->invoice_number);
            }

            $invoice->customer_id = $request->customer_id;
            $invoice->order_id = $order_id;
            $invoice->order_type_id = (!empty($order_type_id)) ? $order_type_id : 2;
            $invoice->delivery_id = $delivery_id;
            $invoice->depot_id = $request->depot_id;
            $invoice->trip_id = $request->trip_id;
            $invoice->salesman_id = $request->salesman_id;
            $invoice->route_id = (!empty($route_id)) ? $route_id : null;
            $invoice->van_id = ($request->van_id > '0') ? $request->van_id : null;
            $invoice->invoice_type = $request->invoice_type;
            $invoice->invoice_date = date('Y-m-d', strtotime($request->invoice_date));
            $invoice->payment_term_id = $request->payment_term_id;
            $invoice->invoice_due_date = $request->invoice_due_date;
            $invoice->total_qty = $request->total_qty;
            $invoice->total_gross = $request->total_gross;
            $invoice->total_discount_amount = $request->total_discount_amount;
            $invoice->total_net = $request->total_net;
            $invoice->total_vat = $request->total_vat;
            $invoice->total_excise = $request->total_excise;
            $invoice->grand_total = $request->grand_total;
            $invoice->mobile_created_at = $request->mobile_created_at;
            $invoice->rounding_off_amount = (!empty($request->rounding_off_amount)) ? $request->rounding_off_amount : "0.00";

            if ($request->is_exchange == 1) {
                    $invoice->pending_credit = $request->pending_credit;
            } else {
                    $invoice->pending_credit = $request->grand_total;
             }
            
            
            $invoice->current_stage = $current_stage;
            $invoice->current_stage_comment = $request->current_stage_comment;
            $invoice->source = $request->source;
            $invoice->status = $status;
            $invoice->approval_status = "Created";
            $invoice->is_premium_invoice = (!empty($request->is_premium_invoice)) ? $request->is_premium_invoice = 1 : null;
            $invoice->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            $invoice->customer_lpo = (!empty($request->customer_lpo)) ? $request->customer_lpo : null;
            $invoice->is_exchange = (isset($request->is_exchange)) ? 1 : 0;
            $invoice->exchange_number = (isset($request->exchange_number)) ? $request->exchange_number : null;
            $invoice->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $invoice->warehouse_id = getWarehuseBasedOnStorageLoacation($request->storage_location_id, false);
            $invoice->save();

            $invoice_id = $invoice->id;

            if ($isActivate = checkWorkFlowRule('Invoice', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Invoice', $request, $invoice);
            }

            $t_qty = 0;
            $tc_qty = 0;

            $delivery_details_ids = array();
            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    //-----------------------Deduct from Route Storage Loaction
                    $conversation = getItemDetails2($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                    if ($request->source == 1) {
                        $routelocation = Storagelocation::where('route_id', $request->route_id)
                            ->where('loc_type', '1')
                            ->first();

                        if (is_object($routelocation)) {

                            $routestoragelocation_id = $routelocation->id;

                            $routelocation_detail = StoragelocationDetail::where('storage_location_id', $routestoragelocation_id)
                                ->where('item_id', $item['item_id'])
                                ->first();

                            if (is_object($routelocation_detail)) {

                                if ($routelocation_detail->qty >= $conversation['Qty']) {
                                    $routelocation_detail->qty = ($routelocation_detail->qty - $conversation['Qty']);
                                    $routelocation_detail->save();
                                }
                                // else {
                                //     $item_detail = Item::where('id', $item['item_id'])->first();
                                //     return prepareResult(false, [], ["error" => "Item is out of stock! the item name is $item_detail->item_name"], " Item is out of stock!  the item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                // }
                            }
                            // else {
                            //     //--------Item not available Error
                            //     $item_detail = Item::where('id', $item['item_id'])->first();
                            //     return prepareResult(false, [], ["error" => "Item not available!. the item name is $item_detail->item_name"], " Item not available! the item name is  $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            // }
                        } else {
                            return prepareResult(false, [], ["error" => "Route Location not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    } else {

                        $customer = CustomerInfo::where('user_id', $request->customer_id)->first();
                        $customer_id = $customer->id;

                        // if ($customer->is_lob == 1) { //lob customer
                        //     $customerlob = CustomerLob::where('customer_info_id', $customer_id)->first();
                        //     $route_id = $customerlob->route_id;
                        // } elseif ($customer->is_lob == 0) { // Central
                        //     $customer = CustomerInfo::where('user_id', $request->customer_id)->first();
                        //     $route_id = $customer->route_id;
                        // }

                        $customerRoute = CustomerRoute::where('customer_id', $customer_id)->first();
                        /*   $customerlob = CustomerLob::where('customer_info_id', $customer_id)->first();
                        $route_id = $customerlob->route_id; */

                        $routes = Route::find($customerRoute->route_id);
                        $depot_id = $routes->depot_id;

                        $Warehouse = Warehouse::where('depot_id', $depot_id)->first();

                        if (is_object($Warehouse)) {
                            $routelocation = Storagelocation::where('warehouse_id', $Warehouse->id)
                                ->where('loc_type', '1')
                                ->first();

                            if (is_object($routelocation)) {

                                $routestoragelocation_id = $routelocation->id;

                                $routelocation_detail = StoragelocationDetail::where('storage_location_id', $routestoragelocation_id)
                                    ->where('item_id', $item['item_id'])
                                    ->first();

                                if (is_object($routelocation_detail)) {

                                    if ($routelocation_detail->qty >= $conversation['Qty']) {
                                        $routelocation_detail->qty = ($routelocation_detail->qty - $conversation['Qty']);
                                        $routelocation_detail->save();
                                    } else {
                                        $item_detail = Item::where('id', $item['item_id'])->first();
                                        return prepareResult(false, [], ["error" => "Item is out of stock! the item name is $item_detail->item_name"], " Item is out of stock!  the item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                    }
                                } else {
                                    //--------Item not available Error
                                    $item_detail = Item::where('id', $item['item_id'])->first();
                                    return prepareResult(false, [], ["error" => "Item not available!. the item name is $item_detail->item_name"], " Item not available! the item name is  $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                }
                            } else {
                                return prepareResult(false, [], ["error" => "Route Location not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                            }
                        } else {

                            return prepareResult(false, [], ["error" => "Wherehouse not available!"], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    }

                    //-----------------------
                    if ($request->invoice_type == 2 || $request->invoice_type == 3) {
                        $delivery_details = DeliveryDetail::where('id', $item['id'])->first();

                        if ($delivery_details->item_qty == $item['item_qty']) {
                            $open_qty = 0.00;
                            $invoiced_qty = $item['item_qty'];
                            if ($delivery_details->delivery_status == "Pending") {
                                $delivery_details->delivery_status = "Invoiced";
                                $delivery_details->open_qty = $open_qty;
                                $delivery_details->invoiced_qty = $invoiced_qty;
                                $delivery_details->save();
                            }
                        } else {
                            if ($delivery_details->open_qty != 0) {
                                $open_qty = $delivery_details->open_qty - $item['item_qty'];
                                $invoiced_qty = $item['item_qty'] + $delivery_details->invoiced_qty;
                            } else {
                                $open_qty = $delivery_details->item_qty - $item['item_qty'];
                                $invoiced_qty = $item['item_qty'];
                            }

                            $delivery_details->open_qty = $open_qty;
                            $delivery_details->invoiced_qty = $invoiced_qty;
                            $delivery_details->delivery_status = 'Completed';
                            $delivery_details->save();
                        }

                        if ($delivery_details->item_qty == $delivery_details->invoiced_qty) {
                            $delivery_details->delivery_status = 'Invoiced';
                            $delivery_details->save();
                        }
                    }

                    $invoiceDetail = new InvoiceDetail;
                    if (isset($item['id']) && $item['id']) {
                        $delivery_details_ids[] = $item['id'];
                    }

                    $qty = getItemDetails2($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                        $invoiceDetail->invoice_id = $invoice_id;
                        $invoiceDetail->item_id = $item['item_id'];
                        $invoiceDetail->item_uom_id = $item['item_uom_id'];
                        $invoiceDetail->discount_id = $item['discount_id'];
                        $invoiceDetail->is_free = $item['is_free'];
                        $invoiceDetail->is_item_poi = $item['is_item_poi'];
                        $invoiceDetail->promotion_id = $item['promotion_id'];
                        $invoiceDetail->item_qty = $item['item_qty'];
                        $invoiceDetail->item_price = $item['item_price'];
                        $invoiceDetail->item_gross = $item['item_gross'];
                        $invoiceDetail->item_discount_amount = $item['item_discount_amount'];
                        $invoiceDetail->item_net = $item['item_net'];
                        $invoiceDetail->item_vat = $item['item_vat'];
                        $invoiceDetail->item_excise = $item['item_excise'];
                        $invoiceDetail->item_grand_total = $item['item_grand_total'];
                        $invoiceDetail->lower_unit_qty = $qty['Qty'];
                        $invoiceDetail->van_id = ($request->van_id > 0) ? $request->van_id : NULL;
                        $invoiceDetail->original_item_qty = $item['item_qty'];
                        $invoiceDetail->base_price = ($item['base_price'] > 0) ? $item['base_price'] : "0";
                        $invoiceDetail->save();
                    

                    $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                    $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                }

                if ($request->invoice_type == 2 || $request->invoice_type == 3) {
                    if (isset($delivery_id) && $delivery_id) {

                        $deliveryDetails = DeliveryDetail::where('delivery_id', $delivery_id)
                            ->whereIn('delivery_status', ['Pending', 'Partial-Invoiced'])
                            ->get();

                        $delivery = Delivery::where('id', $delivery_id)->first();
                        if (count($deliveryDetails) < 1) {
                            $delivery->approval_status = "Completed";
                        } else {
                            $delivery->approval_status = "Completed";
                        }

                        $delivery->save();

                        if ($request->invoice_type == 2) {
                            $deliveryData = Delivery::where('order_id', $order_id)
                                ->whereIn('approval_status', ['Deleted', 'Created', 'Updated', 'In-Process', 'Partial-Invoiced'])
                                ->orderBy('id', 'desc')
                                ->get();

                            if (count($deliveryData) < 1) {
                                $order = Order::find($order_id);
                                $order->approval_status = 'Completed';
                                $order->save();
                            }
                        }
                    }
                }
            }

            // update the total qty to invoice header table
            if (is_object($invoice) && $invoice->source != 1) {
                $invoice->update(
                    [
                        'total_qty' => $t_qty,
                        'total_cancel_qty' => $tc_qty,
                    ]
                );
            } else {
                $invoice->update(
                    [
                        'total_qty' => $request->t_qty,
                        'total_cancel_qty' => $request->tc_qty,
                    ]
                );
            }

            Order::where('id', $order_id)
                ->update([
                    'invoice_id' => $invoice->id,
                ]);

            create_action_history("Invoice", $invoice->id, auth()->user()->id, "create", "Invoice created by " . auth()->user()->firstname . " " . auth()->user()->lastname);
            // we change the we are using delivery number as invoice number
            // if (is_object($invoice) && $invoice->source == 1) {
            //     $user = User::find($request->user()->id);
            //     if (is_object($user)) {
            //         $salesmanInfo = $user->salesmanInfo;
            //         $delivery = Delivery::find($request->delivery_id);
            //         if ($delivery && isset($delivery->invoice_route_id)) {
            //             $salesmanInfo = SalesmanInfo::where('route_id', $delivery->invoice_route_id)->first();
            //             updateMobileNumberRange($salesmanInfo, 'invoice_from', $request->invoice_number);
            //         } else {
            //             $salesmanInfo = SalesmanInfo::where('route_id', $route_id)->first();
            //             updateMobileNumberRange($salesmanInfo, 'invoice_from', $request->invoice_number);
            //         }
            //     }

            //     // mobile data
            //     // $getOrderType = OrderType::find($request->order_type_id);
            //     // preg_match_all('!\d+!', $getOrderType->next_available_code, $newNumber);
            //     // $formattedNumber = sprintf("%0".strlen($getOrderType->end_range)."d", ($newNumber[0][0]+1));
            //     // $actualNumber =  $getOrderType->prefix_code.$formattedNumber;
            //     // $getOrderType->next_available_code = $actualNumber;
            //     // $getOrderType->save();
            // }

            if ($invoice->source != 1) {
                updateNextComingNumber('App\Model\Invoice', 'invoice');
            }

            DB::commit();

            $invoice->getSaveData();

            $this->postInvoiceInJDE($invoice->id);
            $this->invoiceUpdateOnVehicle($invoice);

            return prepareResult(true, $invoice, [], "Invoice added successfully", $this->created);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], ["error" => "Error while validating invoice."], "Error while validating invoice.", $this->unauthorized);
            // return prepareResult(false, [], [], "Error while validating invoice.", $this->unauthorized);
        }

        $invoice = Invoice::with(array('user' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'user:id,parent_id,firstname,lastname,email',
                'user.customerInfo:id,user_id,customer_code',
                'salesmanUser:id,parent_id,firstname,lastname,email',
                'salesmanUser.salesmanInfo:id,user_id,salesman_code',
                'route:id,route_name,route_code',
                'depot:id,depot_code,depot_name',
                'order',
                'van:id,van_code,plate_number,description,capacity,reading',
                'order.orderDetails',
                'invoices',
                'invoices.item:id,item_name,item_code,lower_unit_uom_id',
                'invoices.itemUom:id,name,code',
                'invoices.item.itemMainPrice',
                'invoices.item.itemMainPrice.itemUom:id,name',
                'invoices.item.itemUomLowerUnit:id,name',
                'orderType:id,name,description',
                'invoiceReminder:id,uuid,is_automatically,message,invoice_id',
                'invoiceReminder.invoiceReminderDetails',
                'lob'
            )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($invoice)) {
            return prepareResult(false, [], ["error" => "Oops!!!, something went wrong, please try again."], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        $html = view('html.invoice', compact('invoice'))->render();
        $invoice->html_string = $html;

        return prepareResult(true, $invoice, [], "Invoice Edit", $this->success);
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
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "Please add Salesman."], "Error while validating salesman.", $this->unprocessableEntity);
            // return prepareResult(false, [], "Error Please add Salesman", "Error while validating salesman", $this->unprocessableEntity);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating invoice.", $this->unprocessableEntity);
        }

        // if ($request->invoice_type == 1 && $request->depot_id == NULL) {
        //     return prepareResult(false, [], [], "Error Please add depot.", $this->unprocessableEntity);
        // }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Please add atleast one items."], "Please add atleast one items.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } else if (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        DB::beginTransaction();
        try {
            $order_type_id = $request->order_type_id;
            $order_id = $request->order_id;
            $delivery_id = $request->delivery_id;
            if ($request->invoice_type == "2") {
                $order_id = null;
                $delivery_id = null;
            }
            if ($order_id != '' || $order_id != null) {
                $order = Order::find($order_id);
                if ($order) {
                    $order_type_id = $order->order_type_id;
                }
            }

            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Invoice', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Deliviery',$request);
            }

            $invoice = Invoice::where('uuid', $uuid)->first();

            //Delete old record
            InvoiceDetail::where('invoice_id', $invoice->id)->delete();

            $invoice->customer_id = (!empty($request->customer_id)) ? $request->customer_id : null;
            $invoice->order_id = $order_id;
            $invoice->order_type_id = (!empty($order_type_id)) ? $order_type_id : 2;
            $invoice->delivery_id = $delivery_id;
            $invoice->depot_id = $request->depot_id;
            $invoice->invoice_type = $request->invoice_type;
            $invoice->invoice_number = $request->invoice_number;
            $invoice->van_id = $request->van_id;
            $invoice->route_id = (!empty($route_id)) ? $route_id : null;
            $invoice->invoice_date = date('Y-m-d', strtotime($request->invoice_date));
            $invoice->payment_term_id = $request->payment_term_id;
            $invoice->invoice_due_date = $request->invoice_due_date;
            $invoice->total_qty = $request->total_qty;
            $invoice->total_gross = $request->total_gross;
            $invoice->total_discount_amount = $request->total_discount_amount;
            $invoice->total_net = $request->total_net;
            $invoice->total_vat = $request->total_vat;
            $invoice->total_excise = $request->total_excise;
            $invoice->grand_total = $request->grand_total;
            $invoice->pending_credit = $request->grand_total;
            $invoice->current_stage = $current_stage;
            $invoice->current_stage_comment = $request->current_stage_comment;
            $invoice->source = $request->source;
            $invoice->status = $status;
            $invoice->approval_status = "Created";
            $invoice->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            $invoice->customer_lpo = (!empty($request->customer_lpo)) ? $request->customer_lpo : null;
            $invoice->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $invoice->warehouse_id = (!empty($request->warehouse_id)) ? $request->warehouse_id : 0;
            // $invoice->is_exchange            = (!empty($request->is_exchange)) ? 1 : 0;
            // $invoice->exchange_number        = (!empty($request->exchange_number)) ? $request->exchange_number : null;
            $invoice->save();

            if ($isActivate = checkWorkFlowRule('Invoice', 'edit', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Invoice', $request, $invoice);
            }

            $t_qty = 0;
            $tc_qty = 0;

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    if ($request->invoice_type == 2 || $request->invoice_type == 3) {

                        $delivery_details = DeliveryDetail::where('id', $item['id'])->first();

                        if ($delivery_details->item_qty == $item['item_qty']) {
                            $open_qty = 0.00;
                            $invoiced_qty = $item['item_qty'];
                            if ($delivery_details->delivery_status == "Pending") {
                                $delivery_details->delivery_status = "Invoiced";
                                $delivery_details->open_qty = $open_qty;
                                $delivery_details->invoiced_qty = $invoiced_qty;
                                $delivery_details->save();
                            }
                        } else {

                            if ($delivery_details->open_qty != 0) {
                                $open_qty = $delivery_details->open_qty - $item['item_qty'];
                                $invoiced_qty = $item['item_qty'] + $delivery_details->invoiced_qty;
                            } else {
                                $open_qty = $delivery_details->item_qty - $item['item_qty'];
                                $invoiced_qty = $item['item_qty'];
                            }

                            $delivery_details->open_qty = $open_qty;
                            $delivery_details->invoiced_qty = $invoiced_qty;
                            $delivery_details->delivery_status = 'Partial-Invoiced';
                            $delivery_details->save();
                        }

                        if ($delivery_details->item_qty == $delivery_details->invoiced_qty) {
                            $delivery_details->delivery_status = 'Invoiced';
                            $delivery_details->save();
                        }
                    }

                    $qty = getItemDetails2($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                    $invoiceDetail = new InvoiceDetail;
                    $invoiceDetail->invoice_id  = $invoice->id;
                    $invoiceDetail->item_id     = $item['item_id'];
                    $invoiceDetail->item_uom_id = $item['item_uom_id'];
                    $invoiceDetail->discount_id = $item['discount_id'];
                    $invoiceDetail->is_free     = $item['is_free'];
                    $invoiceDetail->is_item_poi = $item['is_item_poi'];
                    $invoiceDetail->promotion_id = $item['promotion_id'];
                    $invoiceDetail->item_qty    = $item['item_qty'];
                    $invoiceDetail->item_price  = $item['item_price'];
                    $invoiceDetail->item_gross  = $item['item_gross'];
                    $invoiceDetail->item_discount_amount = $item['item_discount_amount'];
                    $invoiceDetail->item_net    = $item['item_net'];
                    $invoiceDetail->item_vat    = $item['item_vat'];
                    $invoiceDetail->item_excise = $item['item_excise'];
                    $invoiceDetail->item_grand_total = $item['item_grand_total'];
                    $invoiceDetail->original_item_qty = $item['original_item_qty'];
                    $invoiceDetail->lower_unit_qty = $qty['Qty'];
                    $invoiceDetail->van_id      = $request->van_id;
                    $invoiceDetail->base_price  = ($item['base_price'] > 0) ? $item['base_price'] : "0";
                    $invoiceDetail->save();

                    // If item already added
                    if (isset($item['original_item_qty'])) {
                        if ($item['original_item_qty'] > $item['item_qty']) {
                            $tc_qty = $tc_qty + ($item['original_item_qty'] - $item['item_qty']);
                            // Convert the original item qty not item_qty
                            $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['original_item_qty']);
                        } else {
                            // Convert the original item qty not item_qty
                            $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);
                        }

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    } else {
                        // New Item added
                        $invoiceDetail->original_item_qty = $item['item_qty'];
                        $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    }
                }

                if ($request->invoice_type == 2 || $request->invoice_type == 3) {
                    if (isset($delivery_id) && $delivery_id) {

                        $deliveryDetails = DeliveryDetail::where('delivery_id', $delivery_id)
                            ->whereIn('delivery_status', ['Pending', 'Partial-Invoiced'])
                            ->orderBy('id', 'desc')
                            ->get();

                        $delivery = Delivery::where('id', $delivery_id)->first();
                        if (count($deliveryDetails) < 1) {
                            $delivery->approval_status = "Completed";
                        } else {
                            $delivery->approval_status = "Partial-Invoiced";
                        }

                        $delivery->save();

                        if ($request->invoice_type == 2) {
                            $deliveryData = Delivery::where('order_id', $order_id)
                                ->whereIn('approval_status', ['Deleted', 'Created', 'Updated', 'In-Process', 'Partial-Invoiced'])
                                ->orderBy('id', 'desc')
                                ->get();

                            if (count($deliveryData) < 1) {
                                $order = Order::find($order_id);
                                $order->approval_status = 'Completed';
                                $order->save();
                            }
                        }
                    }
                }
            }

            // update the total qty to invoice header table
            if (is_object($invoice) && $invoice->source != 1) {
                $invoice->update(
                    [
                        'total_qty' => $t_qty,
                    ]
                );
            } else {
                $invoice->update(
                    [
                        'total_qty' => $request->t_qty,
                    ]
                );
            }

            create_action_history("Invoice", $invoice->id, auth()->user()->id, "update", "Invoice updated by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            DB::commit();

            $invoice->getSaveData();
            return prepareResult(true, $invoice, [], "Invoice updated successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
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
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating invoice.", $this->unauthorized);
        }

        $invoice = Invoice::where('uuid', $uuid)
            ->first();

        if (is_object($invoice)) {
            $invoiceId = $invoice->id;
            $invoice->delete();
            if ($invoice) {
                InvoiceDetail::where('invoice_id', $invoiceId)->delete();
            }
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        } else {
            return prepareResult(false, [], ["error" => "Record not found."], "Record not found.", $this->not_found);
            // return prepareResult(true, [], [], "Record not found.", $this->not_found);
        }

        return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating invoice", $this->unprocessableEntity);
        }

        $action = $request->action;
        $uuids = $request->invoice_ids;

        if (empty($action)) {
            return prepareResult(false, [], ['error' => "Please provide valid action parameter value."], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            foreach ($uuids as $uuid) {
                Invoice::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0,
                ]);
            }
            $invoice = $this->index();
            return prepareResult(true, $invoice, [], "Invoice status updated", $this->success);
        } else if ($action == 'delete') {
            foreach ($uuids as $uuid) {
                $invoice = Invoice::where('uuid', $uuid)
                    ->first();
                $invoiceId = $invoice->id;
                $invoice->delete();
                if ($invoice) {
                    InvoiceDetail::where('invoice_id', $invoiceId)->delete();
                }
            }
            $invoice = $this->index();
            return prepareResult(true, $invoice, [], "Invoice deleted success", $this->success);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                // 'order_type_id' => 'required|integer|exists:order_types,id',
                // 'customer_id' => 'required',
                'invoice_date' => 'required|date',
                'invoice_due_date' => 'required|date',
                // 'payment_term_id' => 'required|integer|exists:payment_terms,id',
                'invoice_type' => 'required',
                'invoice_number' => 'required',
                'total_qty' => 'required',
                'total_vat' => 'required',
                'total_net' => 'required',
                'total_excise' => 'required',
                'grand_total' => 'required',
                'source' => 'required|integer',
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'invoice_ids' => 'required',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function pendingInvoice($route_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
        if (!$route_id) {
            return prepareResult(false, [], ["error" => "Please add atleast one route id."], "Please add atleast one route id.", $this->unauthorized);
            // return prepareResult(false, [], [], "Error while validating pending invoice.", $this->unauthorized);
        }
        $invoices_array = array();
        $customers = CustomerInfo::where('route_id', $route_id)->orderBy('id', 'desc')->get();

        if (is_object($customers)) {
            foreach ($customers as $customer) {
                $invoices = Invoice::where('payment_received', 0)
                    ->where('customer_id', $customer->user_id)
                    ->orderBy('id', 'desc')
                    ->get();

                if (is_object($invoices)) {
                    foreach ($invoices as $invoice) {
                        $collectiondetails = CollectionDetails::where('invoice_id', $invoice->id)->orderBy('id', 'desc')->get();
                        $total_paid = 0;
                        if (is_object($collectiondetails)) {
                            foreach ($collectiondetails as $collectiondetail) {
                                $total_paid = $total_paid + $collectiondetail->amount;
                            }
                        }
                        $invoice->pending_amount = ($invoice->grand_total - $total_paid);
                        $invoice->customer_name = $customer->firstname . ' ' . $customer->lastname;
                        $invoices_array[] = $invoice;
                    }
                }
            }
        }

        return prepareResult(true, $invoices_array, [], "Pending Invoices listing", $this->success);
    }

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
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

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'invoice_file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Invoice import", $this->unauthorized);
        }

        Excel::import(new InvoiceImport, request()->file('invoice_file'));
        return prepareResult(true, [], [], "Invoice successfully imported", $this->success);
    }

    public function sendinvoice(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required',
            'to_email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate send invoice", $this->unauthorized);
        }

        $subject = $request->subject;
        $to = $request->to_email;
        $from_email = 'admin@gmail.com';
        $from_name = 'Admin';
        $data['content'] = $request->message;

        Mail::send('emails.invoice', ['content' => $request->message, 'logo' => '', ' title' => '', 'branch_name' => ''], function ($message) use ($subject, $to, $from_email, $from_name) {
            $message->from($from_email, $from_name);
            $message->to($to);
            $message->subject($subject);
        });

        return prepareResult(true, [], [], "Mail sent successfully", $this->success);
    }

    public function getInvocieByID($id)
    {
        // if (!$this->isAuthorized) {
        //     return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        // }

        $invoices = Invoice::with(array('user' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'user:id,parent_id,firstname,lastname,email',
                'user.customerInfo:id,user_id,customer_code',
                'salesmanUser:id,parent_id,firstname,lastname,email',
                'salesmanUser.salesmanInfo:id,user_id,salesman_code',
                'route:id,route_name,route_code',
                'depot',
                'order',
                'order.orderDetails',
                'invoices',
                'invoices.item:id,item_name',
                'invoices.itemUom:id,name,code',
                'orderType:id,name,description',
                'invoiceReminder:id,uuid,is_automatically,message,invoice_id',
                'invoiceReminder.invoiceReminderDetails',
                'lob'
            )->find($id);

        return prepareResult(true, $invoices, [], "Invoices Show", $this->success);
    }

    public function invoiceReason($id, Request $request)
    {

        $invoice = Invoice::where('id', $id)->first();
        $invoice->reason = $request->reason;
        $invoice->save();
        return prepareResult(true, [], [], "Invoice reason Inserted", $this->success);
    }

    public function invoiceCancel($id, Request $request)
    {
        $invoice = Invoice::where('id', $id)->first();
        $invoice->current_stage = 'Canceled';
        $invoice->save();
        return prepareResult(true, [], [], "Invoice status updated", $this->success);
    }

    public function postInvoiceInJDE($id)
    {
        if (!$id) {
            return;
        }

        // $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_order_posting.php')
        $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_order_posting_prd.php')
            ->withData(array('orderid' => $id))
            ->returnResponseObject()
            ->get();

        return prepareResult(true, [], [], "Invoice posted in JDE.", $this->success);
    }

    private function invoiceUpdateOnVehicle($invoice)
    {
        $vu = VehicleUtilisation::where('vehicle_id', $invoice->van_id)
            ->where('transcation_date', $invoice->invoice_date)
            ->first();

        if ($vu) {
            $vu->update([
                'invoice_count' => $vu->invoice_count + 1,
                'invoice_qty' => $vu->invoice_qty + $invoice->total_qty,
                'cancel_qty' => $vu->cancel_qty + $invoice->total_cancel_qty,
            ]);
        }
    }

    public function invoiceDetailsImport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'invoice_details_file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Invoice Details import", $this->unauthorized);
        }

        Excel::import(new InvoiceDetailImport, request()->file('invoice_details_file'));
        return prepareResult(true, [], [], "Invoice Details successfully imported", $this->success);
    }


    // public function import3(Request $request){
    //     set_time_limit(10000);
    //     ini_set('memory_limit', '-1');
    //     $validator = Validator::make($request->all(), [
    //         'invoice_details_file' => 'required|mimes:xlsx,xls,csv',
    //     ]);

    //     if ($validator->fails()) {
    //         $error = $validator->messages()->first();
    //         return prepareResult(false, [], $error, "Failed to validate Invoice Details import", $this->unauthorized);
    //     }
    //   $data = Excel::toArray([], request()->file('invoice_details_file'));
    //   \DB::statement('SET innodb_lock_wait_timeout = 5000;');
    //   $multArray = [];
    //   $count = 0;
    //   foreach ($data as $key => $reader) {
    //     foreach ($reader as $row) {
            
    //         if ($row[0] == 'Invoice No') {
    //           continue;
    //         }
    //       $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[1])->format('Y-m-d');
    //         $invoice = Invoice::where('invoice_number', $row[0])->first();
    //         if($invoice){
    //             $item = Item::where('item_code', $row[2])->first();
    //             $itemuom = ItemUom::where('name', $row[3])->first();
    //             $itemuom = ItemMainPrice::where(['item_id' => $item->id, 'item_uom_id'=>$itemuom->id])->first();
    //             $invoiceDetail = [];
    //             $invoiceDetail['organisation_id'] = auth()->user()->organisation_id;
    //             $invoiceDetail['invoice_id'] = $invoice->id;
    //             $invoiceDetail['item_id'] = $item->id ?? 0;
    //             $invoiceDetail['item_uom_id'] = $itemuom->id ?? 0;
    //             $invoiceDetail['van_id'] = 0;
    //             $invoiceDetail['discount_id'] = 0;
    //             $invoiceDetail['is_free'] = 0;
    //             $invoiceDetail['is_item_poi'] = 0;
    //             $invoiceDetail['promotion_id'] = 0;
    //             $invoiceDetail['item_qty'] = $row[4];
    //             $invoiceDetail['lower_unit_qty'] = ($itemuom->item_upc ?? 0) * $row[4];
    //             $invoiceDetail['item_price'] = $row[5];
    //             $invoiceDetail['item_gross'] = $row[6];
    //             $invoiceDetail['item_discount_amount'] = 0;
    //             $invoiceDetail['item_net'] = $row[8];
    //             $invoiceDetail['item_vat'] = $row[7];
    //             $invoiceDetail['item_excise'] = 0;
    //             $invoiceDetail['item_grand_total'] = $row[9];
    //             $invoiceDetail['base_price'] = 0;
    //             $invoiceDetail['batch_number'] = 0;
    //             $invoiceDetail['original_item_qty'] = $row[4];
    //             \DB::table('invoice_details')->insert($invoiceDetail);
    //         }
            
    //     }
    //   }
      
     
    //   return prepareResult(true, [], [], "Invoice Details successfully imported", $this->success);
    // }


    public function import3(Request $request){
        set_time_limit(10000);
        ini_set('memory_limit', '-1');
        $validator = Validator::make($request->all(), [
            'invoice_details_file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Invoice Details import", $this->unauthorized);
        }
      $data = Excel::toArray([], request()->file('invoice_details_file'));
      \DB::statement('SET innodb_lock_wait_timeout = 5000;');
      \DB::statement('SET foreign_key_checks = 0;');
      $multArray = [];
      $count = 0;
      foreach ($data as $key => $reader) {
        foreach ($reader as $row) {
            
            if ($row[0] == 'Invoice No') {
              continue;
            }
            $invoice = Invoice::where('invoice_number', $row[0])->first();
            if($invoice){
                $item = Item::where('item_code', trim($row[2]))->first();
                $itemuom = ItemUom::where('name', trim($row[3]))->first();
                $invoiceDetail = \DB::table('invoice_details')->where(['invoice_id'=>$invoice->id, 'item_id'=> $item->id, 'item_uom_id'=> $itemuom->id, 'item_qty'=> $row[4]])->first();

                if(is_null($invoiceDetail)){

                    $itemMainPrice = ItemMainPrice::where(['item_id' => $item->id, 'item_uom_id'=>$itemuom->id])->first();
                    $invoiceDetail = [];
                    $invoiceDetail['invoice_id'] = $invoice->id;
                    $invoiceDetail['item_id'] = $item->id ?? 0;
                    $invoiceDetail['item_uom_id'] = $itemuom->id;
                    $invoiceDetail['van_id'] = 0;
                    $invoiceDetail['discount_id'] = 0;
                    $invoiceDetail['is_free'] = 0;
                    $invoiceDetail['is_item_poi'] = 0;
                    $invoiceDetail['promotion_id'] = 0;
                    $invoiceDetail['item_qty'] = $row[4];
                    $invoiceDetail['lower_unit_qty'] = ($itemMainPrice->item_upc ?? 0) * $row[4];
                    $invoiceDetail['item_price'] = $row[5];
                    $invoiceDetail['item_gross'] = $row[6];
                    $invoiceDetail['item_discount_amount'] = 0;
                    $invoiceDetail['item_net'] = $row[8];
                    $invoiceDetail['item_vat'] = $row[7];
                    $invoiceDetail['item_excise'] = 0;
                    $invoiceDetail['item_grand_total'] = $row[8]+$row[7];
                    $invoiceDetail['base_price'] = 0;
                    $invoiceDetail['batch_number'] = 0;
                    $invoiceDetail['original_item_qty'] = $row[4];
                    $invoiceDetail['deleted_import_data'] = 1;
                    InvoiceDetail::create($invoiceDetail);
                }                
            }
            
        }
      }
      
     
      return prepareResult(true, [], [], "Invoice Details successfully imported", $this->success);
    }


    public function importHarisCustomer(Request $request){
        //dd('test', $request->all());
        set_time_limit(10000);
        ini_set('memory_limit', '-1');
        // $validator = Validator::make($request->all(), [
        //     'invoice_details_file' => 'required|mimes:xlsx,xls,csv',
        // ]);

        // if ($validator->fails()) {
        //     $error = $validator->messages()->first();
        //     return prepareResult(false, [], $error, "Failed to validate Invoice Details import", $this->unauthorized);
        // }

        //dd($request->all());
      $data = Excel::toArray([], request()->file('invoice_details_file'));
      //dd('test', $data);
      \DB::statement('SET innodb_lock_wait_timeout = 5000;');
      \DB::statement('SET foreign_key_checks = 0;');
      $multArray = [];
      $count = 0;
      foreach ($data as $key => $reader) {
        foreach ($reader as $row) {
            //dd($row);
            if ($row[0] == 'ccode') {
              continue;
            }
           //ss dd($row);
            $haris_customer_infos = [];
            $haris_customer_infos['customer_code'] = $row[0]; 
            $haris_customer_infos['customer_type_id'] = $row[1]; 
            $haris_customer_infos['owner_name'] = $row[2]; 
            $haris_customer_infos['email'] = $row[3]; 
            $haris_customer_infos['contact_1'] = $row[4]; 
            $haris_customer_infos['contact_2'] = $row[5]; 
            $haris_customer_infos['barcode'] = $row[6]; 
            $haris_customer_infos['status'] = $row[7]; 
            $haris_customer_infos['full_address'] = $row[8]; 
            $haris_customer_infos['road_or_street'] = $row[9]; 
            $haris_customer_infos['latitude'] = $row[10]; 
            $haris_customer_infos['langitude'] = $row[11]; 
            $haris_customer_infos['outlet_channel_id'] = $row[12]; 
            $haris_customer_infos['customer_category_id'] = $row[13]; 
            $haris_customer_infos['bank_guarantee_name'] = $row[14]; 
            $haris_customer_infos['guarantee_amount'] = $row[15]; 
            $haris_customer_infos['guarantee_from_date'] = $row[16]; 
            $haris_customer_infos['guarantee_to_date'] = $row[17]; 
            $haris_customer_infos['credit_day'] = $row[18]; 
            $haris_customer_infos['credit_limit'] = $row[19]; 
            $haris_customer_infos['serial_number'] = $row[20]; 
            //dd($haris_customer_infos);
            \DB::table('haris_customer_infos')->insert([$haris_customer_infos]);            
        }
      }
      
     
      return prepareResult(true, [], [], "Customer successfully imported", $this->success);
    }

    public function invoiceCheck(Request $request)
{
    if (!$this->isAuthorized) {
        return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
    }

    $data = array();
    foreach ($request->invoice_numbers as $invoice) {
        $idata = Invoice::where('invoice_number', $invoice)->first();
        if (empty($idata)) {
            array_push($data, $invoice);
        }
    }
    if (count($data) > 0) {
        return prepareResult(false, $data, [], "List of Invoice Number Not Matched!", $this->success);
    } else {
        return prepareResult(true, [], [], "All Invoice Found!", $this->success);
    }
}
}
