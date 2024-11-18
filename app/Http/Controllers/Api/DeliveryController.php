<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\DeliveryImport;
use App\Model\PickingSlipGenerator;
use App\Model\CustomerInfo;
use App\Model\SalesmanUnload;
use App\Exports\TotalDeliveryReportExport;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\DeliveryDetail;
use App\Model\DeliveryDriverJourneyPlan;
use App\Model\DeliveryLog;
use App\Model\DeliveryNote;
use App\Model\DeviceDetail;
use App\Model\CustomerRegion;
use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\ItemUom;
use App\Model\LoadItem;
use App\Model\Group;
use App\Model\GroupCustomer;
use App\Model\CustomerGroupMail;
use App\Model\Notifications;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrganisationRole;
use App\Model\rfGenView;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Model\Storagelocation;
use App\Model\SalesmanLoadDetails;
use App\Model\SalesmanVehicle;
use Carbon\Carbon;
use App\Model\VehicleUtilisation;
use App\Model\Warehouse;
use App\Model\WarehouseDetail;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowRuleApprovalUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\DeliveryUpdateImportJob;
use App\Model\ReasonType;
use App\Model\OrderReport;
use Illuminate\Support\Collection;
use URL;

class DeliveryController extends Controller
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

        $delivery = Delivery::with(
            'order',
            'deliveryType:id,name',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'route:id,route_name,route_code',
            'invoice',
            'warehouse',
            'paymentTerm',
            'deliveryDetails',
            'deliveryDetails.reason:id,name,type,code',
            'deliveryDetails.item',
            'deliveryDetails.itemUom',
            'lob',
            'storageocation:id,code,name'
        );

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $delivery->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $delivery->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $customer_code = $request->customer_code;
            $delivery->whereHas('customer.customerInfo', function ($q) use ($customer_code) {
                $q->where('customer_code', 'like', '%' . $customer_code . '%');
            });
        }

        if ($request->branch_plant_code) {
            $branch_plant_code = $request->branch_plant_code;
            $delivery->whereHas('storageocation', function ($q) use ($branch_plant_code) {
                $q->where('code', 'like', '%' . $branch_plant_code . '%');
            });
        }

        if ($request->salesman_name) {
            $salesman_name = $request->salesman_name;
            $exploded_name = explode(" ", $salesman_name);
            if (count($exploded_name) < 2) {
                $delivery->whereHas('salesman', function ($q) use ($salesman_name) {
                    $q->where('firstname', 'like', '%' . $salesman_name . '%')
                        ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $delivery->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $delivery->whereHas('salesman.salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', '%' . $salesman_code . '%');
            });
        }

        if ($request->order_code) {
            $order_number = $request->order_code;
            $delivery->whereHas('order', function ($q) use ($order_number) {
                $q->where('order_number', 'like', '%' . $order_number . '%');
            });
        }

        if ($request->route_name) {
            $route_name = $request->route_name;
            $delivery->whereHas('route', function ($q) use ($route_name) {
                $q->where('route_name', 'like', '%' . $route_name . '%');
            });
        }

        if ($request->route_code) {
            $route_code = $request->route_code;
            $delivery->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', '%' . $route_code . '%');
            });
        }

        if ($request->date) {
            $delivery->whereDate('created_at', $request->date);
        }

        if ($request->delivery_date) {
            $delivery->whereDate('delivery_date', $request->delivery_date);
        }

        if ($request->status) {
            $delivery->where('current_stage', 'like', '%' . $request->status . '%');
        }

        if ($request->approval_status) {
            $delivery->where('approval_status', 'like', '%' . $request->approval_status . '%');
        }

        if (isset($request->approval)) {
            $delivery->where('status', $request->approval);
        }

        if ($request->code) {
            $delivery->where('delivery_number', 'like', '%' . $request->code . '%');
        }

        if (config('app.current_domain') == "presales") {
            $user_branch_plant = $request->user()->userBranchPlantAssing;
            if (count($user_branch_plant)) {
                $storage_id = $user_branch_plant->pluck('storage_location_id')->toArray();
                $delivery->whereIn('storage_location_id', $storage_id);
            }
        }

        $all_user = $delivery->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $delivery_detail = $all_user->items();

        $pagination = array();
        $pagination['total_pages'] = $all_user->lastPage();
        $pagination['current_page'] = (int) $all_user->perPage();
        $pagination['total_records'] = $all_user->total();

        // approval
        $results = GetWorkFlowRuleObject('Deliviery', $all_user->pluck('id')->toArray());
        $approve_need_delivery_detail = array();
        $approve_need_delivery_detail_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_delivery_detail[] = $raw['object']->raw_id;
                $approve_need_delivery_detail_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        $delivery_detail = collect($delivery_detail);

        // approval
        $delivery_detail_array = array();
        if (is_object($delivery_detail)) {
            foreach ($delivery_detail as $key => $delivery_detail1) {
                if (in_array($delivery_detail[$key]->id, $approve_need_delivery_detail)) {
                    $delivery_detail[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_delivery_detail_object_id[$delivery_detail[$key]->id])) {
                        $delivery_detail[$key]->objectid = $approve_need_delivery_detail_object_id[$delivery_detail[$key]->id];
                    } else {
                        $delivery_detail[$key]->objectid = '';
                    }
                } else {
                    $delivery_detail[$key]->need_to_approve = 'no';
                    $delivery_detail[$key]->objectid = '';
                }

                if ($delivery_detail[$key]->current_stage == 'Approved'  || request()->user()->usertype == 1 || in_array($delivery_detail[$key]->id, $approve_need_delivery_detail)) {
                    $delivery_detail_array[] = $delivery_detail[$key];
                }
            }
        }

        return prepareResult(true, $delivery_detail_array, [], "Delivery listing", $this->success, $pagination);
    }
    public function total_delivery_report(Request $request)
    {

        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $delivery_arr = Storagelocation::all();
        $delivery = Delivery::with(
            'order',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'storageocation:id,code,name'
        );
        // ->select(['delivery_date','total_qty','approval_status','delivery_number','order.order_number','customerInfo:customer_code','customer:firstname','customer:lastname','order:order_date']);
        // if ($request->branch_plant_code) {
        //     $branch_plant_code = $request->branch_plant_code;
        //     $delivery->whereHas('storageocation', function ($q) use ($branch_plant_code) {
        //         $q->where('code', 'like', '%' . $branch_plant_code . '%');
        //     });
        // }

        // if(in_array($request->branch_plant_code, $delivery_arr))
        if (is_array($request->branch_plant_code)) {
            $branch_plant_code = $request->branch_plant_code;
            $delivery->whereHas('storageocation', function ($q) use ($branch_plant_code) {
                $q->where('code', $branch_plant_code);
            });
        }
        if ($start_date != '' && $end_date != '') {
            $delivery = $delivery->whereBetween('delivery_date', [$start_date, $end_date]);
        } elseif ($start_date != '' && $end_date == '') {
            $delivery = $delivery->whereDate('delivery_date', $start_date);
        }
        $delivery = $delivery->get();

        $orderCollection = new Collection();
        if (!empty($delivery)) {
            foreach ($delivery as $key => $value) {

                $orderCollection->push((object) [
                    'date'      => $value->delivery_date,
                    'delivery_number'      => $value->delivery_number,
                    'order_number'      => model($value->order, 'order_number'),
                    'customer_code'     => model($value->customerInfo, 'customer_code'),
                    'customer_name'     => model($value->customer, 'firstname') . ' ' . model($value->customer, 'lastname'),
                    'order_date'        => model($value->order, 'order_date'),
                    'delivery_date'      => Carbon::parse($value->delivery_date)->format('Y-m-d'),
                    'total_qty'      => $value->total_qty,
                    'status'       => $value->approval_status,
                    // 'branch_plant'       => $value->storageocation->code,
                ]);
            }
            if ($request->export == 0) {
                return prepareResult(true, $orderCollection, [], "Orders listing", $this->success);
            } else {

                $columns = array(
                    'Date',
                    'Delivery Numner',
                    'Order Number',
                    'Customer Code',
                    'Customer Name',
                    'Order Date',
                    'Delivery Date',
                    'Total Qty',
                    'Status',
                );

                $orderCollection = collect($orderCollection);
                // $file_name = "delivery_report_" . time() . "." . $request->export_type;
                Excel::store(new TotalDeliveryReportExport($orderCollection, $columns), "total_delivery_report.csv");
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/total_delivery_report.csv'));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
        return prepareResult(false, [], [], [], "this date not exist on Orders listing");
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request);
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating delivery", $this->unprocessableEntity);
        }

        $direct_delivery = $request->delivery_type_source;
        if (isset($direct_delivery) && $direct_delivery == 1) {
            if (isset($request->order_id) && $request->order_id) {
                return prepareResult(false, [], 'Order Id is required', "Error while validating delivery", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        $today = now()->format('Y-m-d');

        if ($request->delivery_date < $today) {
            return prepareResult(false, [], [], "Delivery date passed away.", $this->unprocessableEntity);
        }

        //
        // if (config('app.current_domain') != "presales" && !$request->salesman_id) {
        //     return prepareResult(false, [], ['error' => "salesman id required"], "Error while validating delivery", $this->unprocessableEntity);
        // }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } elseif (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        DB::beginTransaction();
        try {
            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Deliviery', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Deliviery);
            }

            if (config('app.current_domain') != "presales") {
                //item check before deduct from warehouse
                if (is_array($request->items)) {
                    foreach ($request->items as $deliveryItem) {
                        $customerInfo = CustomerInfo::where('id', $request->customer_id)->first();

                        if (is_object($customerInfo)) {
                            $route_id = $customerInfo->route_id;
                            $routes = Route::find($route_id);

                            if (is_object($routes)) {
                                $depot_id = $routes->depot_id;
                                $Warehouse = Warehouse::where('depot_id', $depot_id)->first();

                                if (is_object($Warehouse)) {
                                    $warehouse_id = $Warehouse->id;
                                    // only Item Check
                                    $warehouse_detail = WarehouseDetail::where('warehouse_id', $warehouse_id)
                                        ->where('item_id', $deliveryItem['item_id'])
                                        ->first();

                                    if (!is_object($warehouse_detail)) {
                                        $item_detail = Item::where('id', $deliveryItem['item_id'])->first();
                                        return prepareResult(false, [], ["error" => "Item not available! the item name is $item_detail->item_name"], " Item not available! the item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                    }

                                    // if get item in warehouse
                                    $warehouse_detail = WarehouseDetail::where('warehouse_id', $warehouse_id)
                                        ->where('item_id', $deliveryItem['item_id'])
                                        ->where('item_uom_id', $deliveryItem['item_uom_id'])
                                        ->first();

                                    if (is_object($warehouse_detail)) {
                                        if (!($warehouse_detail->qty > $deliveryItem['item_qty'])) {
                                            $item_detail = Item::where('id', $deliveryItem['item_id'])->first();
                                            return prepareResult(false, [], ["error" => "Item is out of stock! item name is $item_detail->item_name"], "Item is out of stock! item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                        }
                                    } else {
                                        // lower unit
                                        $item_table = Item::where('id', $deliveryItem['item_id'])->where('lower_unit_uom_id', $deliveryItem['item_uom'])->first();

                                        if (is_object($item_table)) {
                                            $upc = $item_table->lower_unit_item_upc;
                                            $total_qty = $deliveryItem['item_qty'] / $upc;

                                            $warehouse_detail = WarehouseDetail::where('warehouse_id', $warehouse_id)
                                                ->where('item_id', $deliveryItem['item_id'])
                                                ->first();

                                            if (!($warehouse_detail->qty > $total_qty)) {
                                                $item_detail = Item::where('id', $deliveryItem['item_id'])->first();
                                                return prepareResult(false, [], ["error" => "Item is out of stock! item name is $item_detail->item_name"], " Item is out of stock! item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                            }
                                        } else {
                                            // main price - secondry uom
                                            $ItemMainPrice_result = ItemMainPrice::where('item_id', $deliveryItem['item_id'])->where('item_uom_id', $deliveryItem['item_uom'])->first();
                                            $upc = $ItemMainPrice_result->item_upc;
                                            $total_qty = $deliveryItem['item_qty'] / $upc;

                                            $warehouse_detail = WarehouseDetail::where('warehouse_id', $warehouse_id)
                                                ->where('item_id', $deliveryItem['item_id'])
                                                ->first();

                                            if ($warehouse_detail->qty > $total_qty) {
                                                $item_detail = Item::where('id', $deliveryItem['item_id'])->first();
                                                return prepareResult(false, [], ["error" => "Item is out of stock! item name is $item_detail->item_name"], "Item is out of stock! item name is $item_detail->item_name Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                            }
                                        }
                                    }
                                } else {
                                    return prepareResult(false, [], ["error" => "Wherehouse not available!"], " Wherehouse not available! Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                                }
                            }
                        }
                    } //for loop end
                } else {
                    return prepareResult(false, [], ["error" => "There is no item for delivery"], "There is no item for delivery Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                } //item check before deduct from warehouse end
            }

            $delivery = new Delivery;
            // dd($request->source);

            if ($request->source == 1) {
                $repeat_number = codeCheck('Delivery', 'delivery_number', $request->delivery_number, 'delivery_date');
                if (is_object($repeat_number)) {
                    return prepareResult(true, $repeat_number, [], 'Record saved', $this->success);
                } else {
                    $repeat_number = codeCheck('Delivery', 'delivery_number', $request->delivery_number);
                    if (is_object($repeat_number)) {


                        return prepareResult(false, [], ["error" => "This delivery number " . $request->delivery_number . " is already added."], "This Order Number is already added.", $this->unprocessableEntity);
                    }
                }

                $delivery->delivery_number = $request->delivery_number;
            } else {
                $delivery->delivery_number = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $request->delivery_number);
            }


            $delivery->order_id             = $request->order_id;
            $delivery->customer_id          = $request->customer_id;
            $delivery->salesman_id          = null;
            $delivery->reason_id            = $request->reason_id;
            $delivery->route_id             = null;
            $delivery->storage_location_id  = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $delivery->warehouse_id         = getWarehuseBasedOnStorageLoacation($request->storage_location_id, false);
            $delivery->delivery_type        = $request->delivery_type;
            $delivery->delivery_type_source = $request->delivery_type_source;
            $delivery->delivery_number      = $request->delivery_number;
            $delivery->delivery_date        = $request->delivery_date;
            $delivery->delivery_time        = (isset($request->delivery_time)) ? $request->delivery_time : date('H:m:s');
            $delivery->delivery_due_date    = $request->delivery_due_date;
            $delivery->delivery_weight      = $request->delivery_weight;
            $delivery->payment_term_id      = $request->payment_term_id;
            $delivery->total_qty            = $request->total_qty;
            $delivery->total_gross          = $request->total_gross;
            $delivery->total_discount_amount = $request->total_discount_amount;
            $delivery->total_net            = $request->total_net;
            $delivery->total_vat            = $request->total_vat;
            $delivery->total_excise         = $request->total_excise;
            $delivery->grand_total          = $request->grand_total;
            $delivery->current_stage        = $current_stage;
            $delivery->current_stage_comment = $request->current_stage_comment;
            $delivery->approval_status      = "Created";
            $delivery->lob_id               = (!empty($request->lob_id)) ? $request->lob_id : null;
            $delivery->save();
            // dd($delivery);

            $data = [
                'created_user'          => request()->user()->id,
                'order_id'              => $request->order_id,
                'delviery_id'           => $delivery->id,
                'updated_user'          => NULL,
                'previous_request_body' => NULL,
                'request_body'          => $delivery,
                'action'                => 'Delivery Store',
                'status'                => 'Created',
            ];

            saveOrderDeliveryLog($data);

            if (is_array($request->items)) {
                foreach ($request->items as $deliveryItem) {
                    //save DeliveryDetail

                    if (!empty($request->order_id)) {
                        $order_details = OrderDetail::find($deliveryItem['id']);
                    }

                    $deliveryDetail = new DeliveryDetail;
                    $deliveryDetail->delivery_id            = $delivery->id;
                    $deliveryDetail->item_id                = $deliveryItem['item_id'];
                    $deliveryDetail->item_uom_id            = $deliveryItem['item_uom_id'];
                    $deliveryDetail->discount_id            = $deliveryItem['discount_id'];
                    $deliveryDetail->is_free                = $deliveryItem['is_free'];
                    $deliveryDetail->is_item_poi            = $deliveryItem['is_item_poi'];
                    $deliveryDetail->promotion_id           = $deliveryItem['promotion_id'];
                    $deliveryDetail->item_qty               = $deliveryItem['item_qty'];
                    $deliveryDetail->item_price             = $deliveryItem['item_price'];
                    $deliveryDetail->item_gross             = $deliveryItem['item_gross'];
                    $deliveryDetail->item_discount_amount   = $deliveryItem['item_discount_amount'];
                    $deliveryDetail->item_net               = $deliveryItem['item_net'];
                    $deliveryDetail->item_vat               = $deliveryItem['item_vat'];
                    $deliveryDetail->item_excise            = $deliveryItem['item_excise'];
                    $deliveryDetail->item_grand_total       = $deliveryItem['item_grand_total'];
                    $deliveryDetail->batch_number           = $deliveryItem['batch_number'];
                    $deliveryDetail->save();

                    if ($request->order_id != null) {
                        if ($order_details->item_qty == $deliveryItem['item_qty']) {
                            $open_qty = 0.00;
                            $item_qty = $deliveryItem['item_qty'];
                            if (
                                $order_details->order_status == "Pending" ||
                                $order_details->order_status == "Approved"
                            ) {
                                $order_details->order_status    = "Delivered";
                                $order_details->open_qty        = $open_qty;
                                $order_details->delivered_qty   = $item_qty;
                                $order_details->item_qty        = $item_qty;
                                $order_details->save();
                            }
                        } else {
                            // if ($order_details->open_qty != 0) {
                            //     $order_status = 'Partial-Picking';
                            //     $open_qty = $order_details->open_qty - $deliveryItem['item_qty'];
                            //     $delivered_qty = $order_details->delivered_qty + $deliveryItem['item_qty'];
                            //     $item_qty = $deliveryItem['item_qty'] + $order_details->item_qty;
                            // } else {
                            //     $open_qty = $order_details->item_qty - $deliveryItem['item_qty'];
                            //     $delivered_qty = $order_details->delivered_qty + $deliveryItem['item_qty'];
                            //     $item_qty = $deliveryItem['item_qty'];
                            //     $order_status = 'Partial-Picking';
                            // }

                            if ($order_details->open_qty == 0) {
                                $order_details->open_qty = $order_details->item_qty - $deliveryItem['item_qty'];
                            } else {
                                $order_details->open_qty = $order_details->open_qty - $deliveryItem['item_qty'];
                            }

                            if ($order_details->delivered_qty == 0) {
                                $order_details->delivered_qty = $deliveryItem['item_qty'];
                            } else {
                                $order_details->delivered_qty = $order_details->delivered_qty + $deliveryItem['item_qty'];
                            }

                            if ($order_details->delivered_qty == $order_details->item_qty) {
                                $order_details->order_status = 'Delivered';
                            } else {
                                $order_details->order_status = 'Partial-Picking';
                            }

                            // update th qty id 0

                            $order_details->save();
                        }
                    }

                    $data = [
                        'created_user'          => request()->user()->id,
                        'order_id'              => $request->order_id,
                        'delviery_id'           => $delivery->id,
                        'updated_user'          => NULL,
                        'previous_request_body' => NULL,
                        'request_body'          => $deliveryDetail,
                        'action'                => 'Delivery Store',
                        'status'                => 'Created',
                    ];

                    saveOrderDeliveryLog($data);
                }

                if ($request->order_id != null) {
                    $order = Order::find($request->order_id);

                    $orderDetails = OrderDetail::where('order_id', $order->id)
                        ->whereIn('order_status', ['Partial-Picking', 'Pending'])
                        ->where('open_qty', '!=', 0)
                        ->get();

                    if (!count($orderDetails)) {
                        $order->approval_status = "Delivered";
                    } else {
                        $order->approval_status = "Partial-Picking";
                    }

                    $order->save();
                }
            }

            if ($isActivate = checkWorkFlowRule('Deliviery', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Deliviery', $request, $delivery);
            }

            DB::commit();

            updateNextComingNumber('App\Model\Delivery', 'delivery');

            $delivery->getSaveData();

            return prepareResult(true, $delivery, [], "Delivery added successfully.", $this->success);
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

        $delivery = Delivery::with(
            'order',
            'deliveryType:id,name',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'invoice',
            'warehouse',
            'paymentTerm',
            'deliveryDetails.reason:id,name,type,code',
            'deliveryDetails.item:id,item_name,item_code,lower_unit_uom_id',
            'deliveryDetails.itemUom:id,name,code',
            'deliveryDetails.item.itemMainPrice',
            'deliveryDetails.item.itemMainPrice.itemUom:id,name',
            'deliveryDetails.item.itemUomLowerUnit:id,name',
            'lob',
            'storageocation:id,code,name'
        )->where('uuid', $uuid)
            ->first();

        if (!is_object($delivery)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        foreach ($delivery->deliveryDetails as $detail) {
            $detail->item_update = $detail->item_qty;
        }

        return prepareResult(true, $delivery, [], "Delivery Edit", $this->success);
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating depots", $this->unprocessableEntity);
        }

        // if (!empty($request->route_id)) {
        //     $route_id = $request->route_id;
        // } else if (!empty($request->salesman_id)) {
        //     $route_id = getRouteBySalesman($request->salesman_id);
        // }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Delivery', 'create', $current_organisation_id)) {
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Deliviery);
            }

            $delivery = Delivery::where('uuid', $uuid)->first();
            $salesman_id = NULL;
            if ($delivery->salesman_id) {
                $salesman_id = $delivery->salesman_id;
            }

            if (!is_object($delivery)) {
                return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
            }
            $previous = $delivery;

            $delivery->order_id                 = $request->order_id;
            $delivery->customer_id              = $request->customer_id;
            $delivery->salesman_id              = $salesman_id;
            $delivery->reason_id                = $request->reason_id;
            $delivery->route_id                 = null;
            $delivery->storage_location_id      = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $delivery->warehouse_id             = (!empty($request->warehouse_id)) ? $request->warehouse_id : 0;
            $delivery->delivery_type            = $request->delivery_type;
            $delivery->delivery_type_source     = $request->delivery_type_source;
            $delivery->delivery_number          = $request->delivery_number;
            $delivery->delivery_date            = $request->delivery_date;
            $delivery->delivery_time            = (isset($request->delivery_time)) ? $request->delivery_time : date('H:m:s');
            $delivery->delivery_due_date        = $request->delivery_due_date;
            $delivery->delivery_weight          = $request->delivery_weight;
            $delivery->payment_term_id          = $request->payment_term_id;
            $delivery->total_qty                = $request->total_qty;
            $delivery->total_gross              = $request->total_gross;
            $delivery->total_discount_amount    = $request->total_discount_amount;
            $delivery->total_net                = $request->total_net;
            $delivery->total_vat                = $request->total_vat;
            $delivery->total_excise             = $request->total_excise;
            $delivery->grand_total              = $request->grand_total;
            $delivery->current_stage            = $current_stage;
            $delivery->current_stage_comment    = $request->current_stage_comment;
            // $delivery->approval_status          = "Updated";
            $delivery->source = $request->source;
            $delivery->status = $status;
            $delivery->is_approved = 0;
            $delivery->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            $delivery->is_user_updated          = 1;
            $delivery->user_updated             = request()->user()->id;
            $delivery->module_updated           = "Delivery";
            $delivery->save();

            $data = [
                'created_user'          => request()->user()->id,
                'order_id'              => $request->order_id,
                'delviery_id'           => $delivery->id,
                'updated_user'          => request()->user()->id,
                'previous_request_body' => $previous,
                'request_body'          => $delivery,
                'action'                => 'Delivery Update',
                'status'                => 'Updated',
            ];

            saveOrderDeliveryLog($data);

            $t_qty = 0;
            $tc_qty = 0;

            if (is_array($request->items)) {
                foreach ($request->items as $deliveryItem) {
                    $is_changed = false;
                    $order_uuid = '';
                    // if new item
                    if (!isset($deliveryItem['uuid'])) {
                        // if ($deliveryItem['is_changed']) {
                        $order_detail = $this->saveOrderDetail($deliveryItem, $delivery->order_id);
                        $deliveryItem['id']     = $order_detail->id;
                        $deliveryItem['uuid']   = $order_detail->uuid;
                        $order_uuid = $order_detail->uuid;
                        $is_changed = true;
                        // }
                    }
                    $previous_body = "";
                    if (isset($deliveryItem['uuid'])) {
                        $deliveryDetail = DeliveryDetail::where('uuid', $deliveryItem['uuid'])->first();
                        $previous_body = $deliveryDetail;
                        $order_details = OrderDetail::where('uuid', $deliveryItem['uuid'])->first();

                        if ($order_details) {
                            if ($order_details->order_status == "Pending") {
                                $order_details->order_status = "Delivered";
                            }

                            if ($deliveryItem['original_item_qty'] != $deliveryItem['item_qty']) {
                                $order_details->delivered_qty = $deliveryItem['item_qty'];
                                $order_details->open_qty = $deliveryItem['original_item_qty'] - $deliveryItem['item_qty'];
                            }

                            if (isset($deliveryItem['is_changed']) && !$is_changed) {
                                $order_details->item_qty = $deliveryItem['item_qty'];
                                $order_details->reason_id = ($deliveryItem['reason_id'] > 0) ? $deliveryItem['reason_id'] : null;
                            }

                            $order_details->save();

                            if ($is_changed) {
                                $order = Order::find($order_details->order_id);
                                $this->sendNotificationToDCUser($order, $order_details);
                            }
                        }
                    }

                    if ($is_changed) {
                        $deliveryDetail = new DeliveryDetail;
                        // $deliveryDetail->id             = $deliveryItem['id'];
                        $deliveryDetail->uuid               = $deliveryItem['uuid'];
                        $deliveryDetail->original_item_uom_id = $deliveryItem['item_uom_id'];
                    }

                    $deliveryDetail->delivery_id            = $delivery->id;
                    $deliveryDetail->item_id                = $deliveryItem['item_id'];
                    $deliveryDetail->item_uom_id            = $deliveryItem['item_uom_id'];
                    $deliveryDetail->discount_id            = $deliveryItem['discount_id'];
                    $deliveryDetail->is_free                = $deliveryItem['is_free'];
                    $deliveryDetail->is_item_poi            = $deliveryItem['is_item_poi'];
                    $deliveryDetail->promotion_id           = $deliveryItem['promotion_id'];
                    $deliveryDetail->item_qty               = $deliveryItem['item_qty'];
                    $deliveryDetail->item_price             = $deliveryItem['item_price'];
                    $deliveryDetail->item_gross             = $deliveryItem['item_gross'];
                    $deliveryDetail->item_discount_amount   = $deliveryItem['item_discount_amount'];
                    $deliveryDetail->item_net               = $deliveryItem['item_net'];
                    $deliveryDetail->item_vat               = $deliveryItem['item_vat'];
                    $deliveryDetail->item_excise            = $deliveryItem['item_excise'];
                    $deliveryDetail->item_grand_total       = $deliveryItem['item_grand_total'];
                    $deliveryDetail->batch_number           = $deliveryItem['batch_number'];
                    $deliveryDetail->reason_id              = ($deliveryItem['reason_id'] > 0) ? $deliveryItem['reason_id'] : null;
                    $deliveryDetail->is_deleted             = ($deliveryItem['is_deleted']) ? 1 : 0;

                    // If item already added in delivery details
                    if (isset($deliveryItem['original_item_qty']) && $deliveryItem['original_item_qty']) {
                        if ($deliveryItem['original_item_qty'] > $deliveryItem['item_qty']) {
                            // Convert the original item qty not item_qty
                            $cancel_qty = $deliveryItem['original_item_qty'] - $deliveryItem['item_qty'];
                            $cancel_qty_convert = qtyConversion($deliveryItem['item_id'], $deliveryItem['item_uom_id'], $cancel_qty);
                            $tc_qty = $tc_qty + $cancel_qty_convert['Qty'];

                            $getItemQtyByUom = qtyConversion($deliveryItem['item_id'], $deliveryItem['item_uom_id'], $deliveryItem['original_item_qty']);
                        } else {
                            // Convert the original item qty not item_qty
                            $getItemQtyByUom = qtyConversion($deliveryItem['item_id'], $deliveryItem['item_uom_id'], $deliveryItem['item_qty']);
                        }

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    } else {
                        // New Item added
                        $deliveryDetail->original_item_qty = $deliveryItem['item_qty'];
                        $getItemQtyByUom = qtyConversion($deliveryItem['item_id'], $deliveryItem['item_uom_id'], $deliveryItem['item_qty']);

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    }

                    $deliveryDetail->is_picking = (isset($deliveryItem['is_picking'])) ? $deliveryItem['is_picking'] : 0;
                    $deliveryDetail->save();

                    if ($is_changed) {
                        $deliveryDetail->uuid = $order_uuid;
                        $deliveryDetail->save();
                    }

                    $data = [
                        'created_user'          => request()->user()->id,
                        'order_id'              => $request->order_id,
                        'delviery_id'           => $deliveryDetail->id,
                        'updated_user'          => $request->user()->id,
                        'previous_request_body' => $previous_body,
                        'request_body'          => $deliveryDetail,
                        'action'                => 'Delivery Details Update',
                        'status'                => 'Updated',
                    ];

                    saveOrderDeliveryLog($data);

                    // update template
                    // $this->updateDeliveryTemplate($deliveryDetail);
                    // update RFgen
                    $this->updaterfGen($deliveryDetail);

                    if ($deliveryDetail->is_picking == 1) {
                        $od = OrderDetail::where('uuid', $deliveryItem['uuid'])->first();
                        if ($od) {
                            $od->picking_status = ($od->original_item_qty == $deliveryItem['item_qty']) ? "full" : "partial";
                            $od->save();

                            $deliveryDetail->picking_status = ($deliveryDetail->original_item_qty == $deliveryItem['item_qty']) ? "full" : "partial";
                            $deliveryDetail->save();
                        }
                    }

                    // if (isset($orderDetail->reason_id) && config('app.current_domain') == "presales") {
                    if (isset($deliveryDetail->reason_id)) {
                        $this->deliveryLogs($delivery, $deliveryDetail);
                    }
                }

                $order = Order::find($request->order_id);

                $orderDetails = OrderDetail::where('order_id', $order->id)
                    ->where('picking_status', '!=', 'full')
                    ->first();

                if (!$orderDetails) {

                    $deliveryPicking = DeliveryDetail::where('delivery_id', $delivery->id)
                        ->where('is_picking', 1)
                        ->first();

                    if ($deliveryPicking) {
                        $order->picking_status = "full";
                        $order->approval_status = "Picked";
                        $order->save();

                        Delivery::where('id', $delivery->id)
                            ->where('approval_status', '!=', "Truck Allocated")
                            ->update([
                                'picking_status' => "full",
                                'approval_status' => 'Picked',
                            ]);
                    }
                } else {
                    $deliveryPicking = DeliveryDetail::where('delivery_id', $delivery->id)
                        ->where('is_picking', 1)
                        ->first();

                    if ($deliveryPicking) {

                        $ddd = DeliveryDetail::where('delivery_id', $delivery->id)
                            ->where('picking_status', '!=', 'full')
                            ->first();

                        if (!$ddd) {

                            $delv = Delivery::where('id', $delivery->id)->first();
                            if ($delv) {
                                if ($delv->approval_status != "Truck Allocated") {
                                    // Delivery::where('id', $delivery->id)
                                    $delv->update([
                                        'picking_status' => "full",
                                        "approval_status" => 'Picked',
                                    ]);
                                }
                            }
                        } else {
                            $delv = Delivery::where('id', $delivery->id)->first();
                            if ($delv) {
                                if ($delv->approval_status != "Truck Allocated") {

                                    // Delivery::where('id', $delivery->id)
                                    $delv->update([
                                        'picking_status' => "partial",
                                        "approval_status" => 'Picked',
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // update the total qty to delivery header table
            if (is_object($delivery) && $delivery->source != 1) {
                $delivery->update(
                    [
                        'total_qty' => $t_qty,
                        'total_cancel_qty' => $tc_qty,
                    ]
                );
            } else {
                $delivery->update(
                    [
                        'total_qty' => $request->t_qty,
                        'total_cancel_qty' => $request->tc_qty,
                    ]
                );
            }

            if (isset($request->is_changed)) {
                Order::where('id', $request->order_id)
                    ->update([
                        'total_qty' => $request->total_qty,
                        'total_gross' => $request->total_gross,
                        'total_discount_amount' => $request->total_discount_amount,
                        'total_net' => $request->total_net,
                        'total_vat' => $request->total_vat,
                        'total_excise' => $request->total_excise,
                        'grand_total' => $request->grand_total,
                    ]);
            }

            DB::commit();

            $delivery->getSaveData();

            return prepareResult(true, $delivery, [], "Delivery update successfully.", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * This function is update the qty of the rfgen
     *
     * @return void
     */
    public function updaterfGen($details)
    {
        $rf = rfGenView::where('LOAD_NUMBER', $details->delivery_id)
            ->where('item_id', $details->item_id)
            ->first();

        if ($rf) {
            $rf->DemandPUOM = ($details->item_uom_id == model($details->item, 'lower_unit_uom_id')) ? $details->item_qty : 0;
            $rf->DemandSUOM = ($details->item_uom_id != model($details->item, 'lower_unit_uom_id')) ? $details->item_qty : 0;
            $rf->save();
        }
    }

    /**
     * this function is use for the store the order change and delete the qty
     */
    public function deliveryLogs($delivery, $deliveryDetail)
    {
        $orderLog = new DeliveryLog();
        $orderLog->changed_user_id = request()->user()->id;
        $orderLog->order_id = $delivery->order_id;
        $orderLog->delivery_id = $delivery->id;
        $orderLog->delivery_detail_id = $deliveryDetail->id;
        $orderLog->customer_id = $delivery->customer_id;
        $orderLog->salesman_id = $deliveryDetail->salesman_id;
        $orderLog->item_id = $deliveryDetail->item_id;
        $orderLog->item_uom_id = $deliveryDetail->item_uom_id;
        $orderLog->reason_id = $deliveryDetail->reason_id;
        $orderLog->customer_code = model($delivery->customerInfo, 'customer_code');
        $orderLog->customer_name = model($delivery->customerInfo, 'firstname') . ' ' . model($delivery->customerInfo, 'lastname');
        $orderLog->salesman_code = model($deliveryDetail->salesmanInfo, 'salesman_code');
        $orderLog->salesman_name = model($deliveryDetail->salesman, 'firstname') . ' ' . model($deliveryDetail->salesman, 'lastname');
        $orderLog->item_name = model($deliveryDetail->item, 'item_name');
        $orderLog->item_code = model($deliveryDetail->item, 'item_code');
        $orderLog->item_uom = model($deliveryDetail->itemUom, 'name');
        $orderLog->item_qty = $deliveryDetail->item_qty;
        $orderLog->original_item_qty = $deliveryDetail->original_item_qty;
        $orderLog->action = ($deliveryDetail->is_deleted == 1) ? "deleted" : "change qty";
        $orderLog->reason = model($deliveryDetail->reasonType, 'name');
        $orderLog->save();
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

        $delivery = Delivery::where('uuid', $uuid)->first();

        if (is_object($delivery)) {
            $deliveryID = $delivery->id;
            $delivery->delete();
            $delivery_detail = DeliveryDetail::where('delivery_id', $deliveryID)->get();
            if (is_object($delivery_detail)) {
                foreach ($delivery_detail as $raw) {
                    //update in warehouse
                    $customerInfo = CustomerInfo::find($delivery->customer_id);
                    if (is_object($customerInfo)) {
                        $route_id = $customerInfo->route_id;
                        $routes = Route::find($route_id);
                        if (is_object($routes)) {
                            $depot_id = $routes->depot_id;
                            $Warehouse = Warehouse::where('depot_id', $depot_id)->where('route_id', $route_id)->first();
                            if (is_object($Warehouse)) {
                                $warehouse_id = $Warehouse->id;
                                $warehouse_detail = WarehouseDetail::where('warehouse_id', $warehouse_id)
                                    ->where('item_id', $raw->item_id)
                                    ->where('item_uom_id', $raw->item_uom_id)->first();
                                if (is_object($warehouse_detail)) {
                                    $warehouse_detail->qty = ($warehouse_detail->qty + $raw->item_qty);
                                    $warehouse_detail->save();
                                }
                            }
                        }
                    }
                    $delivery_detail_delete = DeliveryDetail::find($raw->id);
                    //update in warehouse
                }
            }

            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    /**
     * Validations
     **/
    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'customer_id' => 'required|numeric',
                // 'salesman_id' => 'required|numeric',
                'delivery_number' => 'required',
                'delivery_weight' => 'required',
                'payment_term_id' => 'required|numeric',
                'total_qty' => 'required|numeric',
                'total_gross' => 'required|numeric',
                'total_discount_amount' => 'required|numeric',
                'total_net' => 'required|numeric',
                'total_vat' => 'required|numeric',
                'total_excise' => 'required|numeric',
                'grand_total' => 'required|numeric',
                'total_qty' => 'required|numeric',
                'current_stage_comment' => 'required',
                'source' => 'required',
                'status' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "notes") {
            $validator = Validator::make($input, [
                'delivery_id' => 'required|integer|exists:deliveries,id',
                'salesman_id' => 'required|integer|exists:users,id',
                'delivery_note_number' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "invoice_number") {
            $validator = Validator::make($input, [
                'delivery_id' => 'required',
                'invoice_number' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
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

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     *
     * This funciton is only for mobile , in this api salesman get the delivery for every day
     */
    public function getdeliveries(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$request->salesman_id) {
            return prepareResult(false, [], [], "Error while validating deliveries", $this->unauthorized);
        }

        $salesman_id = $request->salesman_id;
        $date = ($request->date) ? Carbon::parse($request->date)->format('Y-m-d') : now()->format('Y-m-d');

        $deliveries = Delivery::with(array('salesman' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'deliveryDetails',
                'deliveryDetails.item:id,item_name,item_code',
                'deliveryDetails.itemUom:id,name,code',
                'deliveryAssignTemplate',
                'deliveryAssignTemplate.customer:id,firstname,lastname',
                'deliveryAssignTemplate.customerInfo:id,user_id,customer_code',
                'deliveryAssignTemplate.deliveryDriver:id,firstname,lastname',
                'deliveryAssignTemplate.deliveryDriverInfo:id,salesman_code',
                'order:id,customer_lop,order_date,order_number',
                'lob',
                'storageocation:id,code,name'
            )
            ->whereHas('deliveryAssignTemplate', function ($q) use ($salesman_id) {
                $q->where('delivery_driver_id', $salesman_id);
            })
            // ->whereIn('current_stage', ['Pending', 'Partial-Invoiced', 'Approved'])
            // change on 15 aug by sugnesh
            ->where('approval_status', "Shipment")
            ->where('delivery_date', $date)
            ->orWhere('change_date', $date)
            ->get();

        return prepareResult(true, $deliveries, [], "Delivery plan listing", $this->success);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     *
     * This funciton is only for mobile , in this api salesman get the delivery for every day
     */
    public function getDeliveriesDetails(Request $request)
    {
        //dd($request->all());

        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$request->salesman_id) {
            return prepareResult(false, [], [], "Error while validating deliveries", $this->unauthorized);
        }

        $salesman_id = $request->salesman_id;
        $date = ($request->date) ? Carbon::parse($request->date)->format('Y-m-d') : now()->format('Y-m-d');

        $deliveries = Delivery::with(array('salesman' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'deliveryDetails',
                'deliveryDetails.item:id,item_name,item_code',
                'deliveryDetails.itemUom:id,name,code',
                'deliveryAssignTemplate',
                'deliveryAssignTemplate.customer:id,firstname,lastname',
                'deliveryAssignTemplate.customerInfo:id,user_id,customer_code',
                'deliveryAssignTemplate.deliveryDriver:id,firstname,lastname',
                'deliveryAssignTemplate.deliveryDriverInfo:id,salesman_code',
                'order:id,customer_lop,order_date,order_number',
                'lob',
                'storageocation:id,code,name'
            )
            // ->whereHas('deliveryAssignTemplate', function ($q) use ($salesman_id) {
            //     $q->where('delivery_driver_id', $salesman_id);
            // })


            // ->whereIn('current_stage', ['Pending', 'Partial-Invoiced', 'Approved'])
            // change on 15 aug by sugnesh
            ->where('delivery_date', $date)
            ->orWhere('change_date', $date)
            ->get();

        return prepareResult(true, $deliveries, [], "Delivery listing By Salesman", $this->success);
    }

    public function cancel(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$request->uuid) {
            return prepareResult(false, [], [], "Error while validating delivery", $this->unauthorized);
        }

        $delivery = Delivery::where('uuid', $request->uuid)->first();
        $old_delivery_id = '';
        if (is_object($delivery)) {
            $delivery->reason_id = $request->reason_id;
            $delivery->current_stage_comment = $request->comment;
            $delivery->approval_status = "Cancel";

            if (count($delivery->deliveryDetails)) {
                foreach ($delivery->deliveryDetails as $detail) {
                    $cancel_qty_convert = qtyConversion($detail->item_id, $detail->item_uom_id, $detail->item_qty);
                    $tc_qty = $cancel_qty_convert['Qty'];
                    $delivery->total_cancel_qty = $delivery->total_cancel_qty + $tc_qty;
                    $delivery->save();
                }
            }

            $delivery->is_user_updated          = 1;
            $delivery->user_updated             = request()->user()->id;
            $delivery->module_updated           = "Delivery";

            $delivery->save();

            $data = [
                'created_user'          => request()->user()->id,
                'order_id'              => $delivery->order_id,
                'delviery_id'           => $delivery->id,
                'updated_user'          => $request->user()->id,
                'previous_request_body' => NULL,
                'request_body'          => $delivery,
                'action'                => 'Delivery Cancel',
                'status'                => 'Updated',
            ];

            saveOrderDeliveryLog($data);

            Order::where('id', $delivery->order_id)
                ->update([
                    'approval_status'   => 'Cancelled',
                    'is_user_updated'   => 1,
                    'user_updated'      => request()->user()->id,
                    'module_updated'    => "Delivery"
                ]);

            $this->sendWarehouseAndScNotification($delivery);

            return prepareResult(true, [], [], "Delivery Canceled", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function sendWarehouseAndScNotification($obj)
    {
        $orgRole = OrganisationRole::whereIn('name', ['Storekeeper', 'SC'])->get();
        if (count($orgRole)) {
            $role_id = $orgRole->pluck('id')->toArray();
            if (count($role_id)) {
                $users = User::whereIn('role_id', $role_id)->get();
                if (count($users)) {
                    foreach ($users as $u) {
                        $data = array(
                            'uuid' => (is_object($obj)) ? $obj->uuid : 0,
                            'user_id' => $u->id,
                            'type' => "Delivery Cancelled",
                            'message' => "Delivery " . $obj->delivery_number . " is cancelled due to " . model($obj->reason, 'code') . '-' . model($obj->reason, 'name'),
                            'status' => 1,
                        );
                        saveNotificaiton($data);
                    }
                }
            }
        }
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'delivery_file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate delivery import", $this->unauthorized);
        }

        Excel::import(new DeliveryImport, request()->file('delivery_file'));
        return prepareResult(true, [], [], "delivery successfully imported", $this->success);
    }

    /*
     *   This function used only delivey update
     *   Created By Hardik Solanki
     */


    public function deliveryTemplateImportNew(Request $request)
    {
        $user = getUser();
        if (!$this->isAuthorized) {
            return prepareResult(
                false,
                [],
                ["error" => "User not authenticate"],
                "User not authenticate.",
                $this->unauthorized
            );
        }

        $validator = Validator::make($request->all(), [
            "delivery_update_file" => "required|mimes:csv,txt",
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(
                false,
                [],
                $error,
                "Failed to validate delivery import",
                $this->unauthorized
            );
        }

        $file = request()->file("delivery_update_file");
        $is_header_level = $request->is_header_level;

        $errors = [];
        // old_delivery_id is previous delivery id
        $old_delivery_id = "";

        $fileName = $_FILES["delivery_update_file"]["tmp_name"];
        $delivery_ids = [];

        $mediaFiles = $request->delivery_update_file;
        $extension = $mediaFiles->getClientOriginalExtension();
        $media_ext = $mediaFiles->getClientOriginalName();
        $media_no_ext = pathinfo($media_ext, PATHINFO_FILENAME);
        $mFiles = 'delivery_import-' . time() . '.' . $extension;
        $mediaFiles->move(public_path() . '/uploads/delivery_import/', $mFiles);

        $file_location = public_path() . '/uploads/delivery_import/' . $mFiles;

        if ($_FILES["delivery_update_file"]["size"] > 0) {

            $this->dispatch(new DeliveryUpdateImportJob($file_location, $is_header_level, $user->firstname, $user->email, $user->id));

            $item_array = [];
            $customer_code_array = [];
            $van_array = [];



            return prepareResult(
                true,
                [],
                $errors,
                "Delivery Import Process Started Will Notify You Throw Mail Once It Will Be Done",
                $this->unprocessableEntity
            );
        }
    }


    public function deliveryTemplateImport_old(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(
                false,
                [],
                ["error" => "User not authenticate"],
                "User not authenticate.",
                $this->unauthorized
            );
        }

        $validator = Validator::make($request->all(), [
            "delivery_update_file" => "required|mimes:csv,txt",
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(
                false,
                [],
                $error,
                "Failed to validate delivery import",
                $this->unauthorized
            );
        }

        $file = request()->file("delivery_update_file");
        $is_header_level = $request->is_header_level;

        $errors = [];
        // old_delivery_id is previous delivery id
        $old_delivery_id = "";

        $fileName = $_FILES["delivery_update_file"]["tmp_name"];
        $delivery_ids = [];
        $skippedRecords = [];

        if ($_FILES["delivery_update_file"]["size"] > 0) {
            $item_array = [];
            $customer_code_array = [];
            $van_array = [];

            $rows = $this->_csv_row_count($fileName);
            $items_per_run = 10;

            for ($i = 0; $i <= $rows; $i = $i + $items_per_run) {
                $chunk = $this->_csv_slice($fileName, $i, $items_per_run);
                foreach ($chunk as $row) {
                    // print_r($row);
                    // echo '<br/>';
                    // $count++;
                    if ($is_header_level == 0) {
                        if (isset($row[0]) && $row[0] != "Order No") {
                            // if (isset($row[13]) && $row[13]) {
                            //     return prepareResult(false, [], ['error' => 'You template file is sku level and you choose header level format.'], "You template file is sku level and you choose header level format.", $this->unprocessableEntity);
                            // }

                            if ($row[0] == "") {
                                $errors[] = "Order Number is not added.";
                            }

                            if ($row[1] == "") {
                                $errors[] = "Cusotmer is not added.";
                            }

                            if ($row[3] == "") {
                                $errors[] = "LPO Raised Date is not added.";
                            }

                            if ($row[4] == "") {
                                $errors[] = "LPO Request Date is not added.";
                            }

                            // if ($row[5] == '') {
                            //     $errors[] = "Customer LPO No is not added.";
                            // }

                            if ($row[7] == "") {
                                $errors[] = "Extended Amount is not added.";
                            }

                            if ($row[8] == "") {
                                $errors[] = "Delivery Sequence is not added.";
                            }

                            if ($row[9] == "") {
                                $errors[] = "Trip is not added.";
                            }

                            if ($row[10] == "") {
                                $errors[] = "Driver code is not added.";
                            }

                            if ($row[11] == "") {
                                $errors[] = "Last trip is not added.";
                            }

                            if ($row[13] == "") {
                                $errors[] = "On Hold is not added.";
                            }

                            if (count($errors) > 0) {
                                return prepareResult(
                                    false,
                                    [],
                                    $errors,
                                    "Delivery not imported",
                                    $this->unprocessableEntity
                                );
                            }

                            $onHold = $row[13];
                            $order = Order::where(
                                "order_number",
                                "like",
                                "%$row[0]%"
                            )->first();
                            $customerInfo = CustomerInfo::where(
                                "customer_code",
                                $row[1]
                            )->first();
                            // $van = Van::where('van_code', 'like', "%$row[10]%")->first();
                            $salesmanInfo = SalesmanInfo::where(
                                "salesman_code",
                                $row[10]
                            )->first();

                            $order_error = [];
                            if (!$order) {
                                if (!in_array($row[0], $order_error)) {
                                    $order_error = $row[0];
                                    $errors[] =
                                        "Order Number does not exist " . $row[0];
                                }
                            }

                            if ($order->approval_status == "Cancelled") {
                                return prepareResult(
                                    false,
                                    [],
                                    [
                                        "error" =>
                                        "The order has been cancelled " .
                                            $order->order_number,
                                    ],
                                    "The order has been cancelled " .
                                        $order->order_number,
                                    $this->unprocessableEntity
                                );
                            }

                            if (
                                is_object($order) &&
                                is_object($order->cusotmerInfo)
                            ) {
                                if (
                                    $order->cusotmerInfo->cusotmer_code != $row[1]
                                ) {
                                    if (!in_array($row[1], $customer_code_array)) {
                                        $customer_code_array[] = $row[1];
                                        $errors[] =
                                            "Cusotmer is not match with order " .
                                            $row[1];
                                    }
                                } else {
                                    if (!$customerInfo) {
                                        if (
                                            !in_array($row[1], $customer_code_array)
                                        ) {
                                            $customer_code_array[] = $row[1];
                                            $errors[] =
                                                "Cusotmer does not match " .
                                                $row[1];
                                        }
                                    }
                                }
                            }

                            $salesman_code_array = [];

                            if (!$salesmanInfo) {
                                if (!in_array($row[10], $errors)) {
                                    $salesman_code_array[] = $row[10];
                                    $errors[] =
                                        "Salesman does not exist " . $row[10];
                                }
                            }

                            // if (!$van) {
                            //     if (!in_array($row[10], $van_array)) {
                            //         $van_array[] = $row[10];
                            //         $errors[] = "Vehicle does not exist " . $row[10];
                            //     }
                            // }

                            if (count($errors) <= 0) {
                                if (is_object($order)) {
                                    if ($onHold == "Yes") {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            ->where(
                                                "approval_status",
                                                "=",
                                                "Shipment"
                                            )
                                            ->first();

                                        if (is_object($delivery_exist)) {
                                            if (is_object($salesmanInfo)) {
                                                $delivery_exist->salesman_id =
                                                    $salesmanInfo->user_id;
                                                $delivery_exist->save();

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "delivery_driver_id" =>
                                                    $salesmanInfo->user_id,
                                                ]);

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "delivery_sequence" => $row[8],
                                                ]);

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "trip" => $row[9],
                                                ]);

                                                SalesmanLoad::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "salesman_id" =>
                                                    $salesmanInfo->user_id,
                                                ]);

                                                SalesmanLoad::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "trip_number" => $row[9],
                                                ]);
                                            }
                                        } else {
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is already completed",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    } else {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            ->where(
                                                "approval_status",
                                                "!=",
                                                "Shipment"
                                            )
                                            ->first();

                                        if (is_object($delivery_exist)) {

                                            // check is shipment is generated
                                            $slCheck = SalesmanLoad::where('delivery_id', $delivery_exist->id)->first();
                                            if ($slCheck) {
                                                continue;
                                            }
                                            // delete assign record is second time upload
                                            DeliveryAssignTemplate::where(
                                                "delivery_id",
                                                $delivery_exist->id
                                            )->delete();

                                            DeliveryDetail::where(
                                                "delivery_id",
                                                $delivery_exist->id
                                            )->update([
                                                "transportation_status" => "No",
                                            ]);

                                            $delivery_exist->update([
                                                "approval_status" => "Created",
                                            ]);

                                            if (
                                                is_object($customerInfo) &&
                                                is_object($salesmanInfo)
                                            ) {
                                                $delivery_exist->customer_id =
                                                    $customerInfo->user_id;
                                                $delivery_exist->salesman_id =
                                                    $salesmanInfo->user_id;
                                                $delivery_exist->save();

                                                $dd = DeliveryDetail::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->get();

                                                if (count($dd)) {
                                                    foreach ($dd as $d) {
                                                        $this->saveHeaderDeliveryAssignTemplate(
                                                            $delivery_exist,
                                                            $d,
                                                            $salesmanInfo,
                                                            $row
                                                        );
                                                    }
                                                }
                                            }

                                            $delivery_exist->transportation_status =
                                                "Delegated";
                                            $delivery_exist->approval_status =
                                                "Truck Allocated";
                                            $delivery_exist->is_truck_allocated = 1;
                                            $delivery_exist->save();

                                            $data = [
                                                "created_user" => request()->user()
                                                    ->id,
                                                "order_id" =>
                                                $delivery_exist->order_id,
                                                "delviery_id" =>
                                                $delivery_exist->id,
                                                "updated_user" => $request->user()
                                                    ->id,
                                                "previous_request_body" => null,
                                                "request_body" => $delivery_exist,
                                                "action" => "Delivery TEMPLATE",
                                                "status" => "Created",
                                            ];

                                            saveOrderDeliveryLog($data);

                                            $checkOrder = Order::find(
                                                $delivery_exist->order_id
                                            );

                                            if (
                                                $checkOrder->order_generate_picking ===
                                                1
                                            ) {
                                                Order::where(
                                                    "id",
                                                    $delivery_exist->order_id
                                                )->update([
                                                    "transportation_status" =>
                                                    "Delegated",
                                                    "approval_status" =>
                                                    "Truck Allocated",
                                                ]);
                                            } else {
                                                Order::where(
                                                    "id",
                                                    $delivery_exist->order_id
                                                )->update([
                                                    "transportation_status" =>
                                                    "Delegated",
                                                ]);
                                            }

                                            if (
                                                !in_array(
                                                    $delivery_exist->id,
                                                    $delivery_ids
                                                )
                                            ) {
                                                $delivery_ids[] =
                                                    $delivery_exist->id;
                                            }
                                        } else {
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        if (isset($row[0]) && $row[0] != "Order No") {
                            // if (!isset($row[13]) && !$row[13]) {
                            //     return prepareResult(false, [], ['error' => 'You template file is header level and you choose sku level format.'], "You template file is header level and you choose sku level format.", $this->unprocessableEntity);
                            // }

                            if ($row[0] == "") {
                                $errors[] = "Order Number is not added.";
                            }

                            if ($row[1] == "") {
                                $errors[] = "Cusotmer is not added.";
                            }

                            if ($row[6] == "") {
                                $errors[] = "Item code is not added.";
                            }

                            if ($row[14] == "") {
                                $errors[] = "Item Uom is not added.";
                            }

                            // if ($row[12] == '') {
                            //     $errors[] = "Vehicel is not added.";
                            // }

                            if ($row[12] == "") {
                                $errors[] = "Delivery Driver is not added.";
                            }

                            if ($row[17] == "") {
                                $errors[] = "On Hold is not added.";
                            }

                            if ($row[18] == "") {
                                $errors[] = "order id is not added.";
                            }

                            if ($row[19] == "") {
                                $errors[] = "customer id is not added.";
                            }

                            if (count($errors) > 0) {
                                return prepareResult(
                                    false,
                                    [],
                                    $errors,
                                    "Delivery not imported",
                                    $this->unprocessableEntity
                                );
                            }

                            $onHold = $row[17];
                            // $order = Order::where(
                            //     "order_number",
                            //     "like",
                            //     "%$row[0]%"
                            // )->first();

                            $order = Order::where("id", $row[18])->first();

                            $order_error = [];
                            if (!$order) {
                                if (!in_array($row[0], $order_error)) {
                                    $order_error = $row[0];
                                    $errors[] =
                                        "Order Number does not exist " . $row[0];
                                }
                            }

                            if ($order->approval_status == "Cancelled") {
                                return prepareResult(
                                    false,
                                    [],
                                    [
                                        "error" =>
                                        "The order has been cancelled " .
                                            $order->order_number,
                                    ],
                                    "The order has been cancelled " .
                                        $order->order_number,
                                    $this->unprocessableEntity
                                );
                            }

                            $salesmanInfo = SalesmanInfo::where("salesman_code", $row[12])->first();

                            $salesman_code_array = [];

                            if (!$salesmanInfo) {
                                if (!in_array($row[12], $errors)) {
                                    $salesman_code_array[] = $row[12];
                                    $errors[] =
                                        "Salesman does not exist " . $row[12];
                                }
                            }

                            // $van = Van::where('van_code', 'like', "%$row[12]%")
                            //     ->first();

                            // if (!$van) {
                            //     if (!in_array($row[12], $van_array)) {
                            //         $van_array[] = $row[12];
                            //         $errors[] = "Vehicle does not exist " . $row[12];
                            //     }
                            // }

                            // $customerInfo = CustomerInfo::where("customer_code", $row[1])->first(); //old 
                            $customerInfo = CustomerInfo::where("user_id", $row[19])->first();

                            // if (is_object($order) && is_object($order->cusotmerInfo)) {
                            //     //old one
                            //     // if ($order->cusotmerInfo->cusotmer_code != $row[1]) {
                            //     if ($order->customer_id != $row[19]) {
                            //         if (!in_array($row[19], $customer_code_array)) {
                            //             $customer_code_array[] = $row[1];
                            //             $errors[] =
                            //                 "Cusotmer is not match with order " .
                            //                 $row[1];
                            //         }
                            //     } else {
                            //         if (!$customerInfo) {
                            //             if (
                            //                 !in_array($row[1], $customer_code_array)
                            //             ) {
                            //                 $customer_code_array[] = $row[1];
                            //                 $errors[] =
                            //                     "Cusotmer does not match " .
                            //                     $row[1];
                            //             }
                            //         }
                            //     }
                            // }

                            $item = Item::where("item_code", $row[6])->first();

                            if (is_object($order)) {
                                if (!$item) {
                                    $errors[] =
                                        "Entered item is not in the order " .
                                        $row[6];
                                    continue;
                                }

                                $order_details_array = $order->orderDetails
                                    ->pluck("item_id")
                                    ->toArray();
                                if (!in_array($item->id, $order_details_array)) {
                                    $item_array[] = $row[6];
                                    $errors[] =
                                        "Entered item is not in the order " .
                                        $row[6];
                                }
                            } else {
                                if (!$item) {
                                    $item_array[] = $row[6];
                                    $errors[] =
                                        "Entered item does not exitst " . $row[6];
                                }
                            }

                            if (count($errors) <= 0) {
                                if (is_object($order)) {
                                    if ($onHold == "Yes") {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            // ->where('approval_status', '!=', 'Shipment')
                                            ->first();
                                        if (is_object($delivery_exist)) {
                                            if (
                                                is_object($customerInfo) &&
                                                is_object($salesmanInfo)
                                            ) {
                                                $delivery_exist->salesman_id =
                                                    $salesmanInfo->user_id;
                                                $delivery_exist->save();
                                                if ($item) {
                                                    $delivery_details = DeliveryDetail::where(
                                                        "delivery_id",
                                                        $delivery_exist->id
                                                    )
                                                        ->where(
                                                            "item_id",
                                                            $item->id
                                                        )
                                                        ->where("item_qty", "!=", 0)
                                                        ->where(
                                                            "item_price",
                                                            "!=",
                                                            0
                                                        )
                                                        ->where("is_deleted", 0)
                                                        // ->where('transportation_status', "No")
                                                        ->get();

                                                    if (
                                                        count($delivery_details) > 1
                                                    ) {
                                                        $delivery_details = DeliveryDetail::where(
                                                            "delivery_id",
                                                            $delivery_exist->id
                                                        )
                                                            ->where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                            ->where(
                                                                "item_qty",
                                                                "!=",
                                                                0
                                                            )
                                                            ->where(
                                                                "item_price",
                                                                "!=",
                                                                0
                                                            )
                                                            ->where("is_deleted", 0)
                                                            ->where(
                                                                "transportation_status",
                                                                "No"
                                                            )
                                                            ->first();
                                                    } else {
                                                        $delivery_details = $delivery_details->first();
                                                    }

                                                    if ($delivery_details) {
                                                        $uom = ItemUom::where(
                                                            "name",
                                                            "like",
                                                            "%$row[14]%"
                                                        )->first();

                                                        DeliveryAssignTemplate::where(
                                                            "delivery_id",
                                                            $delivery_exist->id
                                                        )
                                                            ->where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                            ->where(
                                                                "item_uom_id",
                                                                $uom->id
                                                            )
                                                            ->where("qty", $row[8])
                                                            ->update([
                                                                "delivery_driver_id" =>
                                                                $salesmanInfo->user_id,
                                                                "delivery_sequence" =>
                                                                $row[10],
                                                                "trip" => $row[11],
                                                            ]);

                                                        $dsaas = DeliveryAssignTemplate::where(
                                                            "delivery_id",
                                                            $delivery_exist->id
                                                        )
                                                            ->where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                            ->where(
                                                                "item_uom_id",
                                                                $uom->id
                                                            )
                                                            ->where("qty", $row[8])
                                                            ->first();

                                                        $loaddetail = SalesmanLoadDetails::where(
                                                            "item_id",
                                                            $item->id
                                                        )
                                                            ->where(
                                                                "load_qty",
                                                                number_format(
                                                                    $row[8],
                                                                    2
                                                                )
                                                            )
                                                            ->where(
                                                                "dat_id",
                                                                $dsaas->id
                                                            )
                                                            ->get();
                                                        if ($loaddetail) {
                                                            $loaddetail = $loaddetail->first();

                                                            SalesmanLoad::where(
                                                                "id",
                                                                $loaddetail->salesman_load_id
                                                            )->update([
                                                                "salesman_id" =>
                                                                $salesmanInfo->user_id,
                                                            ]);

                                                            SalesmanLoad::where(
                                                                "id",
                                                                $loaddetail->salesman_load_id
                                                            )->update([
                                                                "trip_number" =>
                                                                $row[11],
                                                            ]);

                                                            SalesmanLoadDetails::where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                                ->where(
                                                                    "load_qty",
                                                                    number_format(
                                                                        $row[8],
                                                                        2
                                                                    )
                                                                )
                                                                ->where(
                                                                    "dat_id",
                                                                    $dsaas->id
                                                                )
                                                                ->update([
                                                                    "salesman_id" =>
                                                                    $salesmanInfo->user_id,
                                                                ]);
                                                        }
                                                        // $skippedRecords[] = [
                                                        //     'row_data' => $delivery_exist->id.'-delivery_id',
                                                        //     'error' => $delivery_exist->approval_status,
                                                        // ];
                                                        // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');
                                                    }
                                                }
                                            }
                                        } else {
                                            // $skippedRecords[] = [
                                            //     'row_data' => $order->order_number.'-or',
                                            //     'error' => "delivery is not generated either its shipped",
                                            // ];
                                            // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    } else {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            // ->where('approval_status', '!=', 'Shipment')
                                            ->first();

                                        // new_delivery_id is current delivery id

                                        if (is_object($delivery_exist)) {
                                            // check if shipment is generated means salesmanLoad created
                                            $slCheck = SalesmanLoad::where('delivery_id', $delivery_exist->id)->first();
                                            if ($slCheck) {
                                                continue;
                                            }

                                            $new_delivery_id = $delivery_exist->id;
                                            if ($old_delivery_id != $new_delivery_id) {
                                                $old_delivery_id = $delivery_exist->id;
                                                $total_qty = 0;
                                                $delivery_exist->update([
                                                    "salesman_id" => null,
                                                ]);
                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->delete();
                                                DeliveryDetail::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "transportation_status" => "No",
                                                ]);
                                            }

                                            if (is_object($customerInfo) && is_object($salesmanInfo)) {
                                                if ($item) {
                                                    $delivery_details = DeliveryDetail::where(
                                                        "delivery_id",
                                                        $delivery_exist->id
                                                    )
                                                        ->where(
                                                            "item_id",
                                                            $item->id
                                                        )
                                                        ->where("item_qty", "!=", 0)
                                                        ->where(
                                                            "item_price",
                                                            "!=",
                                                            0
                                                        )
                                                        ->where("is_deleted", 0)
                                                        // ->where('transportation_status', "No")
                                                        ->get();

                                                    if (count($delivery_details) > 1) {
                                                        $delivery_details = DeliveryDetail::where(
                                                            "delivery_id",
                                                            $delivery_exist->id
                                                        )
                                                            ->where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                            ->where(
                                                                "item_qty",
                                                                "!=",
                                                                0
                                                            )
                                                            ->where(
                                                                "item_price",
                                                                "!=",
                                                                0
                                                            )
                                                            ->where("is_deleted", 0)
                                                            ->where(
                                                                "transportation_status",
                                                                "No"
                                                            )
                                                            ->first();
                                                    } else {
                                                        $delivery_details = $delivery_details->first();
                                                    }

                                                    if ($delivery_details) {
                                                        // if trip sequence 1 then add salesman in header and details both table otherwise only details
                                                        // if ($row[12] == 1) {
                                                        //     $delivery_exist->salesman_id = $salesmanInfo->user_id;
                                                        //     $delivery_exist->save();
                                                        // }
                                                        $uom = ItemUom::where(
                                                            "name",
                                                            "like",
                                                            "%$row[14]%"
                                                        )->first();
                                                        // $total_qty = $total_qty + $row[8];
                                                        $this->saveSKUDeliveryAssignTemplate(
                                                            $delivery_exist,
                                                            $delivery_details,
                                                            $salesmanInfo,
                                                            $item,
                                                            $uom,
                                                            $row
                                                        );
                                                    }
                                                }
                                            }

                                            $delivery_details = DeliveryDetail::where(
                                                "delivery_id",
                                                $delivery_exist->id
                                            )
                                                ->where(
                                                    "transportation_status",
                                                    "No"
                                                )
                                                ->first();

                                            if (!$delivery_details) {

                                                $delivery_exist->transportation_status =
                                                    "Delegated";
                                                $delivery_exist->approval_status =
                                                    "Truck Allocated";
                                                $delivery_exist->is_truck_allocated = 1;
                                                $delivery_exist->save();

                                                $checkOrder = Order::find(
                                                    $delivery_exist->order_id
                                                );
                                                if (
                                                    $checkOrder->order_generate_picking ===
                                                    1
                                                ) {
                                                    Order::where(
                                                        "id",
                                                        $delivery_exist->order_id
                                                    )->update([
                                                        "transportation_status" =>
                                                        "Delegated",
                                                        "approval_status" =>
                                                        "Truck Allocated",
                                                    ]);
                                                } else {
                                                    Order::where(
                                                        "id",
                                                        $delivery_exist->order_id
                                                    )->update([
                                                        "transportation_status" =>
                                                        "Delegated",
                                                    ]);
                                                }

                                                // $skippedRecords[] = [
                                                //     'row_data' => $delivery_exist->id.'-deliery_id',
                                                //     'error' => $delivery_exist->approval_status,
                                                // ]; 
                                            }
                                            // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');

                                            if (
                                                !in_array(
                                                    $delivery_exist->id,
                                                    $delivery_ids
                                                )
                                            ) {
                                                $delivery_ids[] =
                                                    $delivery_exist->id;
                                            }
                                        } else {
                                            // $skippedRecords[] = [
                                            //     'row_data' => $order->order_number.'-order_nu',
                                            //     'error' => "delivery is not generated either its shipped",
                                            // ]; 
                                            // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // echo 'total_count:-'.$count;

            if (count($errors)) {
                return prepareResult(
                    false,
                    [],
                    $errors,
                    "Delivery not imported",
                    $this->unprocessableEntity
                );
            } else {
                return prepareResult(
                    true,
                    [],
                    [],
                    "Delivery imported",
                    $this->success
                );
            }
        }
    }

    public function deliveryTemplateImport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(
                false,
                [],
                ["error" => "User not authenticate"],
                "User not authenticate.",
                $this->unauthorized
            );
        }

        $validator = Validator::make($request->all(), [
            "delivery_update_file" => "required|mimes:csv,txt",
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(
                false,
                [],
                $error,
                "Failed to validate delivery import",
                $this->unauthorized
            );
        }

        $file = request()->file("delivery_update_file");
        $is_header_level = $request->is_header_level;

        $errors = [];
        // old_delivery_id is previous delivery id
        $old_delivery_id = "";

        $fileName = $_FILES["delivery_update_file"]["tmp_name"];
        $delivery_ids = [];
        $skippedRecords = [];

        if ($_FILES["delivery_update_file"]["size"] > 0) {
            $item_array = [];
            $customer_code_array = [];
            $van_array = [];

            $count = 0;

            $rows = $this->_csv_row_count($fileName);

            $items_per_run = 100;

            for ($i = 0; $i <= $rows; $i = $i + $items_per_run) {
                $chunk = $this->_csv_slice($fileName, $i, $items_per_run);
                // exit;
                foreach ($chunk as $row) {
                    if ($is_header_level == 0) {
                        if (isset($row[0]) && $row[0] != "Order No") {
                            // if (isset($row[13]) && $row[13]) {
                            //     return prepareResult(false, [], ['error' => 'You template file is sku level and you choose header level format.'], "You template file is sku level and you choose header level format.", $this->unprocessableEntity);
                            // }

                            if ($row[0] == "") {
                                $errors[] = "Order Number is not added.";
                            }

                            if ($row[1] == "") {
                                $errors[] = "Cusotmer is not added.";
                            }

                            if ($row[3] == "") {
                                $errors[] = "LPO Raised Date is not added.";
                            }

                            if ($row[4] == "") {
                                $errors[] = "LPO Request Date is not added.";
                            }

                            // if ($row[5] == '') {
                            //     $errors[] = "Customer LPO No is not added.";
                            // }

                            if ($row[7] == "") {
                                $errors[] = "Extended Amount is not added.";
                            }

                            if ($row[8] == "") {
                                $errors[] = "Delivery Sequence is not added.";
                            }

                            if ($row[9] == "") {
                                $errors[] = "Trip is not added.";
                            }

                            if ($row[10] == "") {
                                $errors[] = "Driver code is not added.";
                            }

                            if ($row[11] == "") {
                                $errors[] = "Last trip is not added.";
                            }

                            if ($row[13] == "") {
                                $errors[] = "On Hold is not added.";
                            }

                            if (count($errors) > 0) {
                                return prepareResult(
                                    false,
                                    [],
                                    $errors,
                                    "Delivery not imported",
                                    $this->unprocessableEntity
                                );
                            }

                            $onHold = $row[13];
                            $order = Order::where(
                                "order_number",
                                "like",
                                "%$row[0]%"
                            )->first();
                            $customerInfo = CustomerInfo::where(
                                "customer_code",
                                $row[1]
                            )->first();
                            // $van = Van::where('van_code', 'like', "%$row[10]%")->first();
                            $salesmanInfo = SalesmanInfo::where(
                                "salesman_code",
                                $row[10]
                            )->first();

                            $order_error = [];
                            if (!$order) {
                                if (!in_array($row[0], $order_error)) {
                                    $order_error = $row[0];
                                    $errors[] =
                                        "Order Number does not exist " . $row[0];
                                }
                            }

                            if ($order->approval_status == "Cancelled") {
                                return prepareResult(
                                    false,
                                    [],
                                    [
                                        "error" =>
                                        "The order has been cancelled " .
                                            $order->order_number,
                                    ],
                                    "The order has been cancelled " .
                                        $order->order_number,
                                    $this->unprocessableEntity
                                );
                            }

                            if (
                                is_object($order) &&
                                is_object($order->cusotmerInfo)
                            ) {
                                if (
                                    $order->cusotmerInfo->cusotmer_code != $row[1]
                                ) {
                                    if (!in_array($row[1], $customer_code_array)) {
                                        $customer_code_array[] = $row[1];
                                        $errors[] =
                                            "Cusotmer is not match with order " .
                                            $row[1];
                                    }
                                } else {
                                    if (!$customerInfo) {
                                        if (
                                            !in_array($row[1], $customer_code_array)
                                        ) {
                                            $customer_code_array[] = $row[1];
                                            $errors[] =
                                                "Cusotmer does not match " .
                                                $row[1];
                                        }
                                    }
                                }
                            }

                            $salesman_code_array = [];

                            if (!$salesmanInfo) {
                                if (!in_array($row[10], $errors)) {
                                    $salesman_code_array[] = $row[10];
                                    $errors[] =
                                        "Salesman does not exist " . $row[10];
                                }
                            }

                            // if (!$van) {
                            //     if (!in_array($row[10], $van_array)) {
                            //         $van_array[] = $row[10];
                            //         $errors[] = "Vehicle does not exist " . $row[10];
                            //     }
                            // }

                            if (count($errors) <= 0) {
                                if (is_object($order)) {
                                    if ($onHold == "Yes") {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            ->where(
                                                "approval_status",
                                                "=",
                                                "Shipment"
                                            )
                                            ->first();

                                        if (is_object($delivery_exist)) {
                                            if (is_object($salesmanInfo)) {
                                                $delivery_exist->salesman_id =
                                                    $salesmanInfo->user_id;
                                                $delivery_exist->save();

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "delivery_driver_id" =>
                                                    $salesmanInfo->user_id,
                                                ]);

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "delivery_sequence" => $row[8],
                                                ]);

                                                DeliveryAssignTemplate::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "trip" => $row[9],
                                                ]);

                                                SalesmanLoad::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "salesman_id" =>
                                                    $salesmanInfo->user_id,
                                                ]);

                                                SalesmanLoad::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->update([
                                                    "trip_number" => $row[9],
                                                ]);
                                            }
                                        } else {
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is already completed",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    } else {
                                        $delivery_exist = Delivery::where(
                                            "order_id",
                                            $order->id
                                        )
                                            ->where(
                                                "approval_status",
                                                "!=",
                                                "Shipment"
                                            )
                                            ->first();

                                        if (is_object($delivery_exist)) {

                                            // check is shipment is generated
                                            $slCheck = SalesmanLoad::where('delivery_id', $delivery_exist->id)->first();
                                            if ($slCheck) {
                                                continue;
                                            }
                                            // delete assign record is second time upload
                                            DeliveryAssignTemplate::where(
                                                "delivery_id",
                                                $delivery_exist->id
                                            )->delete();

                                            DeliveryDetail::where(
                                                "delivery_id",
                                                $delivery_exist->id
                                            )->update([
                                                "transportation_status" => "No",
                                            ]);

                                            $delivery_exist->update([
                                                "approval_status" => "Created",
                                            ]);

                                            if (
                                                is_object($customerInfo) &&
                                                is_object($salesmanInfo)
                                            ) {
                                                $delivery_exist->customer_id =
                                                    $customerInfo->user_id;
                                                $delivery_exist->salesman_id =
                                                    $salesmanInfo->user_id;
                                                $delivery_exist->save();

                                                $dd = DeliveryDetail::where(
                                                    "delivery_id",
                                                    $delivery_exist->id
                                                )->get();

                                                if (count($dd)) {
                                                    foreach ($dd as $d) {
                                                        $this->saveHeaderDeliveryAssignTemplate(
                                                            $delivery_exist,
                                                            $d,
                                                            $salesmanInfo,
                                                            $row
                                                        );
                                                    }
                                                }
                                            }

                                            $delivery_exist->transportation_status =
                                                "Delegated";
                                            $delivery_exist->approval_status =
                                                "Truck Allocated";
                                            $delivery_exist->is_truck_allocated = 1;
                                            $delivery_exist->save();

                                            $data = [
                                                "created_user" => request()->user()
                                                    ->id,
                                                "order_id" =>
                                                $delivery_exist->order_id,
                                                "delviery_id" =>
                                                $delivery_exist->id,
                                                "updated_user" => $request->user()
                                                    ->id,
                                                "previous_request_body" => null,
                                                "request_body" => $delivery_exist,
                                                "action" => "Delivery TEMPLATE",
                                                "status" => "Created",
                                            ];

                                            saveOrderDeliveryLog($data);

                                            $checkOrder = Order::find(
                                                $delivery_exist->order_id
                                            );

                                            if (
                                                $checkOrder->order_generate_picking ===
                                                1
                                            ) {
                                                Order::where(
                                                    "id",
                                                    $delivery_exist->order_id
                                                )->update([
                                                    "transportation_status" =>
                                                    "Delegated",
                                                    "approval_status" =>
                                                    "Truck Allocated",
                                                ]);
                                            } else {
                                                Order::where(
                                                    "id",
                                                    $delivery_exist->order_id
                                                )->update([
                                                    "transportation_status" =>
                                                    "Delegated",
                                                ]);
                                            }

                                            if (
                                                !in_array(
                                                    $delivery_exist->id,
                                                    $delivery_ids
                                                )
                                            ) {
                                                $delivery_ids[] =
                                                    $delivery_exist->id;
                                            }
                                        } else {
                                            return prepareResult(
                                                false,
                                                [],
                                                [
                                                    "error" =>
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped",
                                                ],
                                                "The order " .
                                                    $order->order_number .
                                                    " delivery is not generated either its shipped.",
                                                $this->unprocessableEntity
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        if (isset($row[0]) && $row[0] != "Order No") {
                            // if (!isset($row[13]) && !$row[13]) {
                            //     return prepareResult(false, [], ['error' => 'You template file is header level and you choose sku level format.'], "You template file is header level and you choose sku level format.", $this->unprocessableEntity);
                            // }

                            if ($row[0] == "") {
                                $errors[] = "Order Number is not added.";
                            }

                            if ($row[1] == "") {
                                $errors[] = "Cusotmer is not added.";
                            }

                            if ($row[6] == "") {
                                $errors[] = "Item code is not added.";
                            }

                            if ($row[14] == "") {
                                $errors[] = "Item Uom is not added.";
                            }

                            // if ($row[12] == '') {
                            //     $errors[] = "Vehicel is not added.";
                            // }

                            if ($row[12] == "") {
                                $errors[] = "Delivery Driver is not added.";
                            }

                            if ($row[17] == "") {
                                $errors[] = "On Hold is not added.";
                            }

                            if ($row[18] == "") {
                                $errors[] = "order id is not added.";
                            }

                            if ($row[19] == "") {
                                $errors[] = "customer id is not added.";
                            }

                            if ($row[20] == "") {
                                $errors[] = "delivery id is not added.";
                            }

                            // if ($row[21] == "") {
                            //     $errors[] = "delivery details id is not added.";
                            // }

                            if ($row[22] == "") {
                                $errors[] = "item id is not added.";
                            }

                            if ($row[23] == "") {
                                $errors[] = "item uom id is not added.";
                            }



                            $order_id = $row[18];
                            $customer_id = $row[19];
                            $delivery_id = $row[20];
                            $delivery_details_id = $row[21];
                            $item_id = $row[22];
                            $item_uom_id = $row[23];
                            $storage_location_id = $row[24];

                            if (count($errors) > 0) {
                                return prepareResult(
                                    false,
                                    [],
                                    $errors,
                                    "Delivery not imported",
                                    $this->unprocessableEntity
                                );
                            }

                            $onHold = $row[17];
                            // $order = Order::where(
                            //     "order_number",
                            //     "like",
                            //     "%$row[0]%"
                            // )->first();

                            $order = Order::where("id", $order_id)->first();

                            $order_error = [];
                            if (!$order) {
                                if (!in_array($row[0], $order_error)) {
                                    $order_error = $row[0];
                                    $errors[] =
                                        "Order Number does not exist " . $row[0];
                                }
                            }

                            if ($order->approval_status == "Cancelled") {
                                // return prepareResult(
                                //     false,
                                //     [],
                                //     [
                                //         "error" =>
                                //         "The order has been cancelled " .
                                //             $order->order_number,
                                //     ],
                                //     "The order has been cancelled " .
                                //         $order->order_number,
                                //     $this->unprocessableEntity
                                // );
                            } else {
                                $salesmanInfo = SalesmanInfo::where("salesman_code", $row[12])->first();

                                $salesman_code_array = [];

                                if (!$salesmanInfo) {
                                    if (!in_array($row[12], $errors)) {
                                        $salesman_code_array[] = $row[12];
                                        $errors[] =
                                            "Salesman does not exist " . $row[12];
                                    }
                                }
                                $customerInfo = CustomerInfo::where("user_id", $customer_id)->first();

                                $item = Item::where("id", $item_id)->first();

                                if (count($errors) <= 0) {
                                    if (is_object($order)) {
                                        if ($onHold == "Yes") {
                                            $delivery_exist = Delivery::where(
                                                "order_id",
                                                $order->id
                                            )
                                                // ->where('approval_status', '!=', 'Shipment')
                                                ->first();
                                            if (is_object($delivery_exist)) {
                                                if (is_object($customerInfo) && is_object($salesmanInfo)) {
                                                    $delivery_exist->salesman_id =
                                                        $salesmanInfo->user_id;
                                                    $delivery_exist->save();
                                                    if ($item) {
                                                        $delivery_details = DeliveryDetail::where(
                                                            "delivery_id",
                                                            $delivery_exist->id
                                                        )
                                                            ->where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                            ->where("item_qty", "!=", 0)
                                                            ->where(
                                                                "item_price",
                                                                "!=",
                                                                0
                                                            )
                                                            ->where("is_deleted", 0)
                                                            // ->where('transportation_status', "No")
                                                            ->get();

                                                        if (
                                                            count($delivery_details) > 1
                                                        ) {
                                                            $delivery_details = DeliveryDetail::where(
                                                                "delivery_id",
                                                                $delivery_exist->id
                                                            )
                                                                ->where(
                                                                    "item_id",
                                                                    $item->id
                                                                )
                                                                ->where(
                                                                    "item_qty",
                                                                    "!=",
                                                                    0
                                                                )
                                                                ->where(
                                                                    "item_price",
                                                                    "!=",
                                                                    0
                                                                )
                                                                ->where("is_deleted", 0)
                                                                ->where(
                                                                    "transportation_status",
                                                                    "No"
                                                                )
                                                                ->first();
                                                        } else {
                                                            $delivery_details = $delivery_details->first();
                                                        }

                                                        if ($delivery_details) {
                                                            $uom = ItemUom::where(
                                                                "name",
                                                                "like",
                                                                "%$row[14]%"
                                                            )->first();

                                                            DeliveryAssignTemplate::where(
                                                                "delivery_id",
                                                                $delivery_exist->id
                                                            )
                                                                ->where(
                                                                    "item_id",
                                                                    $item->id
                                                                )
                                                                ->where(
                                                                    "item_uom_id",
                                                                    $uom->id
                                                                )
                                                                ->where("qty", $row[8])
                                                                ->update([
                                                                    "delivery_driver_id" =>
                                                                    $salesmanInfo->user_id,
                                                                    "delivery_sequence" =>
                                                                    $row[10],
                                                                    "trip" => $row[11],
                                                                ]);

                                                            $dsaas = DeliveryAssignTemplate::where(
                                                                "delivery_id",
                                                                $delivery_exist->id
                                                            )
                                                                ->where(
                                                                    "item_id",
                                                                    $item->id
                                                                )
                                                                ->where(
                                                                    "item_uom_id",
                                                                    $uom->id
                                                                )
                                                                ->where("qty", $row[8])
                                                                ->first();

                                                            $loaddetail = SalesmanLoadDetails::where(
                                                                "item_id",
                                                                $item->id
                                                            )
                                                                ->where(
                                                                    "load_qty",
                                                                    number_format(
                                                                        $row[8],
                                                                        2
                                                                    )
                                                                )
                                                                ->where(
                                                                    "dat_id",
                                                                    $dsaas->id
                                                                )
                                                                ->get();
                                                            if ($loaddetail) {
                                                                $loaddetail = $loaddetail->first();

                                                                SalesmanLoad::where(
                                                                    "id",
                                                                    $loaddetail->salesman_load_id
                                                                )->update([
                                                                    "salesman_id" =>
                                                                    $salesmanInfo->user_id,
                                                                ]);

                                                                SalesmanLoad::where(
                                                                    "id",
                                                                    $loaddetail->salesman_load_id
                                                                )->update([
                                                                    "trip_number" =>
                                                                    $row[11],
                                                                ]);

                                                                SalesmanLoadDetails::where(
                                                                    "item_id",
                                                                    $item->id
                                                                )
                                                                    ->where(
                                                                        "load_qty",
                                                                        number_format(
                                                                            $row[8],
                                                                            2
                                                                        )
                                                                    )
                                                                    ->where(
                                                                        "dat_id",
                                                                        $dsaas->id
                                                                    )
                                                                    ->update([
                                                                        "salesman_id" =>
                                                                        $salesmanInfo->user_id,
                                                                    ]);
                                                            }
                                                            // $skippedRecords[] = [
                                                            //     'row_data' => $delivery_exist->id.'-delivery_id',
                                                            //     'error' => $delivery_exist->approval_status,
                                                            // ];
                                                            // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');
                                                        }
                                                    }
                                                }
                                            } else {
                                                // $skippedRecords[] = [
                                                //     'row_data' => $order->order_number.'-or',
                                                //     'error' => "delivery is not generated either its shipped",
                                                // ];
                                                // $skippedRecordsFilePath = $this->storeSkippedRecords($skippedRecords, 'delivery');
                                                return prepareResult(
                                                    false,
                                                    [],
                                                    [
                                                        "error" =>
                                                        "The order " .
                                                            $order->order_number .
                                                            " delivery is not generated either its shipped",
                                                    ],
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped.",
                                                    $this->unprocessableEntity
                                                );
                                            }
                                        } else {
                                            if ($delivery_id != '') {
                                                // check if shipment is generated means salesmanLoad created
                                                $slCheck = SalesmanLoad::where('delivery_id', $delivery_id)->first();
                                                if ($slCheck) {
                                                    continue;
                                                }
                                               

                                                $new_delivery_id = $delivery_id;
                                                if ($old_delivery_id != $new_delivery_id) {
                                                    $old_delivery_id = $delivery_id;
                                                    $total_qty = 0;
                                                    DeliveryAssignTemplate::where(
                                                        "delivery_id",
                                                        $delivery_id
                                                    )->delete();
                                                }


                                                $delivery_details = DeliveryDetail::where("delivery_id",$delivery_id)
                                                    ->where("item_id",$item->id)
                                                    ->where("item_qty", "!=", 0)
                                                    ->where("item_price","!=",0)
                                                    ->where("is_deleted", 0)
                                                    // ->where('transportation_status', "No")
                                                    ->get();

                                                if (count($delivery_details) > 1) {
                                                    $delivery_details = DeliveryDetail::where("delivery_id",$delivery_id)
                                                        ->where("item_id",$item->id)
                                                        ->where("item_qty","=",$row[8])
                                                        ->where("item_price","!=",0)
                                                        ->where("is_deleted", 0)
                                                        ->where("transportation_status","No")
                                                        ->first();
                                                } else {
                                                    $delivery_details = $delivery_details->first();
                                                }


                                                // $DeliveryDetail = DeliveryDetail::where('delivery_id', $delivery_id)
                                                //     ->where("item_id", $item_id)
                                                //     // ->where("item_qty", "!=", 0)
                                                //     ->where("item_qty", "!=", 0)
                                                //     ->where("item_price", "!=", 0)
                                                //     ->where("is_deleted", 0)
                                                //     // ->where('transportation_status','!=','Delegated')
                                                //     ->first();

                                                   
                                                // if (!is_object($DeliveryDetail)) {
                                                //     return prepareResult(
                                                //         false,
                                                //         [],
                                                //         $errors,
                                                //         "Delivery not imported".$delivery_id."--".$item_id,
                                                //         $this->unprocessableEntity
                                                //     );
                                                // }

                                                if ($delivery_details) {
                                                    // echo '<pre>';
                                                    // print_r($DeliveryDetail);
                                                    //  exit;
                                                    $this->saveSKUDeliveryAssignTemplateNew(
                                                        // $delivery_exist,
                                                        $order_id,
                                                        $customer_id,
                                                        $delivery_id,
                                                        // $delivery_details,
                                                        $delivery_details->id,
                                                        $salesmanInfo,
                                                        $item_id,
                                                        $item_uom_id,
                                                        $storage_location_id,
                                                        $row
                                                    );

                                                    // if ($delivery_details_id != '') {
                                                    if ($delivery_details) {

                                                        $delivery_exist = Delivery::where(
                                                            "id",
                                                            $delivery_id
                                                        )
                                                            ->first();
                                                        $delivery_exist->transportation_status =
                                                            "Delegated";
                                                        $delivery_exist->approval_status =
                                                            "Truck Allocated";
                                                        $delivery_exist->is_truck_allocated = 1;
                                                        $delivery_exist->save();

                                                        $checkOrder = Order::find(
                                                            $delivery_exist->order_id
                                                        );
                                                        if (
                                                            $checkOrder->order_generate_picking ===
                                                            1
                                                        ) {
                                                            Order::where(
                                                                "id",
                                                                $delivery_exist->order_id
                                                            )->update([
                                                                "transportation_status" =>
                                                                "Delegated",
                                                                "approval_status" =>
                                                                "Truck Allocated",
                                                            ]);
                                                        } else {
                                                            Order::where(
                                                                "id",
                                                                $delivery_exist->order_id
                                                            )->update([
                                                                "transportation_status" =>
                                                                "Delegated",
                                                            ]);
                                                        }
                                                    }

                                                    if (
                                                        !in_array(
                                                            $delivery_exist->id,
                                                            $delivery_ids
                                                        )
                                                    ) {
                                                        $delivery_ids[] =
                                                            $delivery_exist->id;
                                                    }
                                                }
                                            } else {
                                                return prepareResult(
                                                    false,
                                                    [],
                                                    [
                                                        "error" =>
                                                        "The order " .
                                                            $order->order_number .
                                                            " delivery is not generated either its shipped",
                                                    ],
                                                    "The order " .
                                                        $order->order_number .
                                                        " delivery is not generated either its shipped.",
                                                    $this->unprocessableEntity
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // echo 'total_count:-'.$count;
            // exit;
            if (count($errors)) {
                return prepareResult(
                    false,
                    [],
                    $errors,
                    "Delivery not imported",
                    $this->unprocessableEntity
                );
            } else {
                return prepareResult(
                    true,
                    [],
                    [],
                    "Delivery imported",
                    $this->success
                );
            }
        }
    }

    /**
     * This funciton is generate the load on delivery
     *
     * @param Request $request
     * @return void
     */
    // public function deliveryConvertToLoad2(Request $request)
    // {
    //     if (!$this->isAuthorized) {
    //         return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
    //     }

    //     if (!is_array($request->delivery_id)) {
    //         return prepareResult(false, [], ["error" => "Deliery id is not added."], "Deliery id is not added.", $this->unauthorized);
    //     }

    //     // $deliveries = Delivery::whereIn('id', $request->delivery_id)
    //     //     ->get();

    //     $dats = DeliveryAssignTemplate::whereIn('delivery_id', $request->delivery_id)
    //         ->orderBy('delivery_driver_id', 'asc')
    //         ->get();

    //     $er = array();
    //     foreach ($dats as $key => $dat) {
    //         if ($dat) {
    //             $delivery = Delivery::find($dat->delivery_id);
    //             DB::beginTransaction();
    //             try {
    //                 $new_delivery = true;
    //                 // foreach ($delivery->deliveryDetails as $detail) {

    //                 $load_number = $dat->trip_sequence . model($dat->deliveryDriverInfo, 'id') . model($dat->delivery, 'delivery_number');
    //                 $delivery_date = model($dat->delivery, 'delivery_date');

    //                 $loadheader = SalesmanLoad::where('load_number', $load_number)
    //                     ->where('load_date', $delivery_date)
    //                     ->first();

    //                 if (!$loadheader) {
    //                     $loadheader = new SalesmanLoad;
    //                     $loadheader->load_number = $load_number;
    //                     $loadheader->route_id = getRouteByVan($dat->van_id);
    //                     $loadheader->order_id = $dat->order_id;
    //                     $loadheader->van_id = $dat->van_id;
    //                     $loadheader->delivery_id = $dat->delivery_id;
    //                     $loadheader->depot_id = null;
    //                     $loadheader->salesman_id = $dat->delivery_driver_id;
    //                     $loadheader->storage_location_id = $dat->storage_location_id;
    //                     $loadheader->warehouse_id = $dat->warehouse_id;
    //                     $loadheader->load_date = $delivery_date;
    //                     $loadheader->load_type = 1;
    //                     $loadheader->load_confirm = 0;
    //                     $loadheader->status = 0;
    //                     $loadheader->save();
    //                 }

    //                 $lower_qty = getItemDetails2($dat->item_id, $dat->item_uom_id, $dat->qty, true);

    //                 $loaddetail = new SalesmanLoadDetails;
    //                 $loaddetail->salesman_load_id = $loadheader->id;
    //                 $loaddetail->route_id = $loadheader->route_id;
    //                 $loaddetail->salesman_id = $loadheader->salesman_id;
    //                 $loaddetail->storage_location_id = $loadheader->storage_location_id;
    //                 $loaddetail->warehouse_id = $loadheader->warehouse_id;
    //                 $loaddetail->depot_id = null;
    //                 $loaddetail->van_id = $loadheader->van_id;
    //                 $loaddetail->load_date = $loadheader->load_date;
    //                 $loaddetail->item_id = $dat->item_id;
    //                 $loaddetail->item_uom = $dat->item_uom_id;
    //                 $loaddetail->load_qty = $dat->qty;
    //                 $loaddetail->lower_qty = $lower_qty['Qty'];
    //                 $loaddetail->requested_item_uom_id = $dat->item_uom_id;
    //                 $loaddetail->requested_qty = $dat->qty;
    //                 $loaddetail->save();

    //                 $ddjp = DeliveryDriverJourneyPlan::where('date', $delivery_date)
    //                     ->where('delivery_driver_id', $loadheader->salesman_id)
    //                     ->where('customer_id', $dat->customer_id)
    //                     ->first();

    //                 if (!$ddjp) {
    //                     $ddjp = new DeliveryDriverJourneyPlan;
    //                     $ddjp->date = $delivery_date;
    //                     $ddjp->delivery_driver_id = $loadheader->salesman_id;
    //                     $ddjp->customer_id = $dat->customer_id;
    //                     $ddjp->save();
    //                 }

    //                 // Comment this line 24/09
    //                 // $sv = SalesmanVehicle::where('salesman_id', $loadheader->salesman_id)
    //                 //     ->where('van_id', $loadheader->van_id)
    //                 //     ->where('date', $loadheader->load_date)
    //                 //     ->first();

    //                 // if (!$sv) {
    //                 //     $sv = new SalesmanVehicle;
    //                 //     $sv->salesman_id = $loadheader->salesman_id;
    //                 //     $sv->van_id = $loadheader->van_id;
    //                 //     $sv->route_id = $loadheader->route_id;
    //                 //     $sv->date = $loadheader->load_date;
    //                 //     $sv->save();

    //                 //     if ($loadheader->salesman_id) {
    //                 //         // find the route base on vehicle
    //                 //         $r = Route::where('van_id', $loadheader->van_id)->first();
    //                 //         if ($r) {
    //                 //             SalesmanInfo::where('user_id', $loadheader->salesman_id)
    //                 //                 ->update([
    //                 //                     'route_id' => $r->id,
    //                 //                 ]);
    //                 //         }
    //                 //     }
    //                 // }

    //                 // $vu = VehicleUtilisation::where('vehicle_id', $loadheader->van_id)
    //                 //     ->where('transcation_date', $delivery_date)
    //                 //     ->first();

    //                 // if (!$vu) {

    //                 //     $region_code = null;
    //                 //     $region_name = null;

    //                 //     if (is_object($dat->delivery->customerRegion)) {
    //                 //         if ($dat->delivery->customerRegion->region) {
    //                 //             $region_code = $dat->delivery->customerRegion->region->region_code;
    //                 //             $region_name = $dat->delivery->customerRegion->region->region_code;
    //                 //         }
    //                 //     }

    //                 //     // if record not exist then create new record
    //                 //     $vu = new VehicleUtilisation();
    //                 //     $vu->region_id = model($dat->delivery->customerRegion, 'region_id');
    //                 //     $vu->region_code = $region_code;
    //                 //     $vu->region_name = $region_name;
    //                 //     $vu->vehicle_id = $dat->van_id;
    //                 //     $vu->vehicle_code = model($dat->van, 'van_code');
    //                 //     $vu->customer_count = $this->getCustomerCount($loadheader->load_date, $dat->van_id);
    //                 //     $vu->delivery_qty = model($dat->delivery, 'total_qty');
    //                 //     $vu->cancle_count = 0;
    //                 //     $vu->cancel_qty = model($dat->delivery, 'total_cancel_qty');
    //                 //     $vu->transcation_date = $loadheader->load_date;
    //                 //     $vu->less_delivery_count = (model($dat->order, 'total_qty') <= 10) ? 1 : 0;
    //                 //     $vu->order_count = 1;
    //                 //     $vu->order_qty = model($dat->order, 'total_qty');
    //                 //     $vu->vehicle_capacity = model($dat->van, 'capacity');
    //                 //     $vu->save();
    //                 // } else {
    //                 //     if ($new_delivery) {
    //                 //         $vu->update([
    //                 //             'customer_count' => $this->getCustomerCount($loadheader->load_date, $dat->van_id),
    //                 //             'delivery_qty' => $vu->delivery_qty + $dat->delivery->total_qty,
    //                 //             'cancle_count' => $vu->cancle_count + $dat->delivery->total_cancel_qty,
    //                 //             'less_delivery_count' => (model($dat->order, 'total_qty') <= 10) ? $vu->less_delivery_count + 1 : $vu->less_delivery_count,
    //                 //             'order_count' => $vu->order_count + 1,
    //                 //             'order_qty' => $vu->order_qty + model($dat->order, 'total_qty'),
    //                 //         ]);
    //                 //     }
    //                 // }
    //                 $new_delivery = false;
    //                 // }

    //                 $dd = DeliveryDetail::where('delivery_id', $dat->deliver_id)
    //                     ->where('transportation_status', 'No')
    //                     ->first();

    //                 if (is_object($dd)) {
    //                     $ts = "Partial";
    //                 } else {
    //                     $ts = "Full";
    //                 }

    //                 $delivery->approval_status = 'Shipment';
    //                 $delivery->shipment_status = 'full';
    //                 $delivery->sync_status = null;
    //                 $delivery->transportation_status = $ts;
    //                 $delivery->save();

    //                 Order::where('id', $dat->order_id)
    //                     ->update([
    //                         'shipment_status' => 'full',
    //                         'approval_status' => 'Shipment',
    //                     ]);

    //                 $dds = DeliveryAssignTemplate::where('delivery_id', $delivery->id)
    //                     ->groupBy('delivery_driver_id')
    //                     ->get();

    //                 if (count($dds)) {
    //                     foreach ($dds as $d) {
    //                         if ($d->salesman_id) {
    //                             $data = array(
    //                                 'uuid' => $d->uuid,
    //                                 'user_id' => $d->delivery_driver_id,
    //                                 'type' => 'Delivery Driver Assign',
    //                                 'message' => "Tomorrow you have a " . $delivery->delivery_number . 'to ' . $d->customer->getName() . ' - ' . model($d->customerInfo, 'customer_code'),
    //                                 'status' => 1,
    //                             );

    //                             $dataNofi = array(
    //                                 'uuid' => $d->uuid,
    //                                 'user_id' => $d->delivery_driver_id,
    //                                 'type' => 'Delivery Driver Assign',
    //                                 'sender_id' => null,
    //                                 'message' => "Tomorrow you have a " . $delivery->delivery_number . 'to ' . $d->customer->getName() . ' - ' . model($d->customerInfo, 'customer_code'),
    //                                 'status' => 1,
    //                                 'title' => "Tomorrow Delivery",
    //                                 'noti_type' => "Delivery",
    //                                 'customer_id' => $d->customer_id,
    //                             );

    //                             $device_detail = DeviceDetail::where('user_id', $d->salesman_id)
    //                                 ->orderBy('id', 'desc')
    //                                 ->first();

    //                             if (is_object($device_detail)) {
    //                                 $t = $device_detail->device_token;
    //                                 sendNotificationAndroid($dataNofi, $t);
    //                             }

    //                             saveNotificaiton($data);
    //                         }
    //                     }
    //                 }

    //                 DB::commit();
    //             } catch (\Exception $exception) {
    //                 DB::rollback();
    //                 $delivery->sync_status = $exception;
    //                 $delivery->save();
    //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
    //             } catch (\Throwable $exception) {
    //                 DB::rollback();
    //                 $delivery->sync_status = $exception;
    //                 $delivery->save();
    //                 return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
    //             }
    //         }
    //     }

    //     if (count($er)) {
    //         return prepareResult(false, [], ['error' => 'Delivery number ' . implode(",", $er) . 'not attached deliery driver'], 'Delivery number ' . implode(",", $er) . 'not attached deliery driver', $this->unprocessableEntity);
    //     }

    //     return prepareResult(true, [], [], "Delivery Converted to Load", $this->success);
    // }

    public function deliveryConvertToLoad(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!is_array($request->delivery_id)) {
            return prepareResult(false, [], ["error" => "Deliery id is not added."], "Deliery id is not added.", $this->unauthorized);
        }

        $er = array();
        $old_salesman = '';
        $old_trip = '';
        $old_delivery_id = '';

        DeliveryDetail::whereIn('delivery_id', $request->delivery_id)
            ->where('item_price', 0)
            ->orWhere('item_qty', 0)
            ->update([
                'transportation_status' => "Delegated"
            ]);

        $dats = DeliveryAssignTemplate::whereIn('delivery_id', $request->delivery_id)
            ->orderBy('delivery_driver_id', 'asc')
            ->orderBy('delivery_id', 'asc')
            ->get();



        $odd = '';
        $is_last_set = 0;
        $is_trip_salesman = [];
        foreach ($dats as $dat) {
            $ndd = $dat->delivery_driver_id;
            if ($ndd != $odd) {
                $odd = $ndd;
                if ($dat->is_last_trip == 1) {
                    if (!in_array($dat->delivery_driver_id, $is_trip_salesman)) {
                        $is_trip_salesman[] = $dat->delivery_driver_id;
                    }

                    if ($is_last_set == 1) {
                        if (count($is_trip_salesman) > 1) {
                            return prepareResult(false, [], ['error' =>   'You have assign to generate invoice to multiple driver.'], 'You have assign to generate invoice to multiple driver.', $this->unprocessableEntity);
                            break;
                        }
                    }
                    $is_last_set = 1;
                }
            }
        }

        // echo '<pre>';
        // print_r($dats);
        // exit;

        foreach ($dats as $dat) {
            $delivery = Delivery::find($dat->delivery_id);

            // $deatemp = DeliveryAssignTemplate::select(DB::raw('sum(is_last_trip) as is_last_trip_sum'))
            //     ->where('delivery_id', $delivery->id)
            //     ->first();

            // if ($deatemp) {
            //     if ($deatemp > 1) {
            //         return prepareResult(false, [], ['error' =>   'You have assign to generate invoice to multiple driver.'], 'You have assign to generate invoice to multiple driver.', $this->unprocessableEntity);
            //                 break;
            //     }
            // }

            // $dats = DeliveryAssignTemplate::select(DB::raw("count('delivery_details_id') as total_line"))
            //     ->where('delivery_id', $request->delivery_id)
            //     ->first();

            // if (count($delivery->deliveryDetails) != $dats->total_line) {
            //     return prepareResult(false, [], ['error' =>   'Each line is not assigned to a delivery driver of delivery number is ' . $delivery->delivery_number], 'Each line is not assigned to a delivery driver of delivery number is ' . $delivery->delivery_number, $this->unprocessableEntity);
            // }

            // commnet reason is if single item and qty is 0 then msg given success but status truck allocatation show

            // if ($dat->qty == 0) {
            //     continue;
            // }

            $ddCheck = DeliveryDetail::where('delivery_id', $dat->delivery_id)
                ->where('item_id', $dat->item_id)
                ->where('item_uom_id', $dat->item_uom_id)
                ->first();

            if (is_object($ddCheck) && $ddCheck->transportation_status == "No") {
                $ddCheck->transportation_status = 'Delegated';
                $ddCheck->save();
            }

            $item = Item::find($dat->item_id);

            $delivery_d = DeliveryDetail::select(DB::raw('
                if(sum(item_qty) > 0, sum(item_qty), 0) as item_qty
            '))
                ->where('item_id', $dat->item_id)
                ->where('is_deleted', 0)
                ->where('item_price', '!=', 0)
                // ->where('item_uom_id', $dat->item_uom_id)
                ->where('delivery_id', $dat->delivery_id)
                ->first();


            $dats_qty = DeliveryAssignTemplate::select(DB::raw(
                'if(sum(qty) > 0, sum(qty), 0) as item_qty'
            ))
                ->where('item_id', $dat->item_id)
                // ->where('qty', '!=', 0)
                ->where('delivery_id', $dat->delivery_id)
                // ->where('item_uom_id', $dat->item_uom_id)
                ->first();
                 

            // if ($dats_qty->item_qty == 0 && $delivery_d->item_qty == 0) {
            //     continue;
            // }

            if ($dats_qty->item_qty != $delivery_d->item_qty) {
                $sl = SalesmanLoad::where('delivery_id', $dat->delivery_id)->first();
                if ($sl) {
                    Delivery::where('id', $delivery->id)
                        ->update([
                            'approval_status' => "Truck Allocated",
                        ]);

                    DeliveryDetail::where('delivery_id', $delivery->id)
                        ->update([
                            // 'transportation_status' => "No",
                            'shipment_status' => NULL
                        ]);

                    // SalesmanLoad::where('delivery_id', $dat->delivery_id)->forceDelete();
                }
                // return prepareResult(false, [], ['error' =>  $delivery->delivery_number . ' delivery of ' . model($item, 'item_code') . ' qty not match.'], $delivery->delivery_number . ' delivery of ' . model($item, 'item_code') . ' qty is 0.', $this->unprocessableEntity);
                // break;
            }

            if ($delivery_d->item_qty < 1 && ($dats_qty->item_qty != $delivery_d->item_qty)) {
                $sl = SalesmanLoad::where('delivery_id', $dat->delivery_id)->first();
                if ($sl) {
                    Delivery::where('id', $delivery->id)
                        ->update([
                            'approval_status' => "Truck Allocated",
                        ]);

                    DeliveryDetail::where('delivery_id', $delivery->id)
                        ->update([
                            // 'transportation_status' => "No",
                            'shipment_status' => NULL
                        ]);

                    // SalesmanLoad::where('delivery_id', $dat->delivery_id)->forceDelete();
                }
                // return prepareResult(false, [], ['error' =>  $delivery->delivery_number . ' delivery of ' . model($item, 'item_code') . ' qty is 0.'], $delivery->delivery_number . ' delivery of ' . model($item, 'item_code') . ' qty is 0.', $this->unprocessableEntity);
                // break;
            }

            if ($dats_qty->item_qty < 1 && ($dats_qty->item_qty != $delivery_d->item_qty)) {
                // continue;
                $sl = SalesmanLoad::where('delivery_id', $dat->delivery_id)->first();
                if ($sl) {
                    DeliveryDetail::where('delivery_id', $delivery->id)
                        ->update([
                            // 'transportation_status' => "No",
                            'shipment_status' => NULL
                        ]);

                    Delivery::where('id', $delivery->id)
                        ->update([
                            'approval_status' => "Truck Allocated",
                        ]);
                    SalesmanLoad::where('delivery_id', $dat->delivery_id)->forceDelete();
                }
                return prepareResult(false, [], ['error' =>  $delivery->delivery_number . ' Assign of ' . model($item, 'item_code') . ' qty is 0.'], $delivery->delivery_number . ' Assign of ' . model($item, 'item_code') . ' qty is 0.', $this->unprocessableEntity);
                break;
            }

            if ($delivery_d->item_qty < $dats_qty->item_qty) {
                $sl = SalesmanLoad::where('delivery_id', $dat->delivery_id)->first();
                if ($sl) {
                    Delivery::where('id', $delivery->id)
                        ->update([
                            'approval_status' => "Truck Allocated",
                        ]);

                    DeliveryDetail::where('delivery_id', $delivery->id)
                        ->update([
                            // 'transportation_status' => "No",
                            'shipment_status' => NULL
                        ]);
                    // SalesmanLoad::where('delivery_id', $dat->delivery_id)->forceDelete();
                }
                // return prepareResult(false, [], ['error' =>  $delivery->delivery_number . ' of delivery item qty ' . model($item, 'item_code') . ' is less than delivery qty.'], $delivery->delivery_number . ' of delivery item qty ' . model($item, 'item_code') . ' is less than delivery qty.', $this->unprocessableEntity);
                // break;
            }

            if ($delivery_d->item_qty > $dats_qty->item_qty) {
                $sl = SalesmanLoad::where('delivery_id', $dat->delivery_id)->first();
                if ($sl) {
                    Delivery::where('id', $delivery->id)
                        ->update([
                            'approval_status' => "Truck Allocated",
                        ]);
                    DeliveryDetail::where('delivery_id', $delivery->id)
                        ->update([
                            // 'transportation_status' => "No",
                            'shipment_status' => NULL
                        ]);
                    // SalesmanLoad::where('delivery_id', $dat->delivery_id)->forceDelete();
                }
                // return prepareResult(false, [], ['error' => $delivery->delivery_number . ' of delivery item qty ' . model($item, 'item_code') . ' is more than truck assign qty.'], $delivery->delivery_number . ' of delivery item qty ' . model($item, 'item_code') . ' is more than truck assign qty.', $this->unprocessableEntity);
                // break;
            }

            // pick slip generate if not exist
            if ($delivery) {
                $pic_generated = PickingSlipGenerator::where('order_id', $delivery->order_id)->first();
                if (!$pic_generated) {
                    PickingSlipGenerator::create([
                        'order_id' => $delivery->order_id,
                        'date' => now()->format('Y-m-d'),
                        'time' => now()->format('H-i-s'),
                        'date_time' => now(),
                        'picking_slip_generator_id' => request()->user()->id,
                    ]);
                }
            }

            DB::beginTransaction();
            try {

                if ($dat->qty == 0) {
                    // continue;
                }

                $new_delivery_id = $dat->delivery_id;
                if ($new_delivery_id != $old_delivery_id) {
                    $new_delivery = true;
                    $old_delivery_id = $dat->delivery_id;
                    $old_salesman = '';
                    $old_trip = '';
                }

                $new_salesman = $dat->delivery_driver_id;
                $new_trip = $dat->trip;

                if (($new_salesman == $old_salesman) && ($old_trip == $new_trip) && ($old_delivery_id == $new_delivery_id)) {
                    
                    $load_number = $dat->trip . model($dat->deliveryDriverInfo, 'id') . model($dat->delivery, 'delivery_number');
                    $delivery_date = model($dat->delivery, 'delivery_date');

                    $loadheader = SalesmanLoad::where('load_number', $load_number)
                        ->where('load_date', $delivery_date)
                        ->first();
                       
                } else {
                    $old_salesman = $dat->delivery_driver_id;
                    $old_trip = $dat->trip;

                    $load_number = $dat->trip . model($dat->deliveryDriverInfo, 'id') . model($dat->delivery, 'delivery_number');
                    $delivery_date = model($dat->delivery, 'delivery_date');

                    $loadheader = SalesmanLoad::where('salesman_id', $dat->delivery_driver_id)
                        ->where('load_date', $delivery_date)
                        ->where('delivery_id', $dat->delivery_id)
                        ->where('trip_number', $dat->trip)
                        ->first();

                    if (!$loadheader) {
                        $loadheader = new SalesmanLoad;
                    }

                    $loadheader->load_number    = $load_number;
                    $loadheader->route_id       = getRouteByVan($dat->van_id);
                    $loadheader->order_id       = $dat->order_id;
                    $loadheader->van_id         = $dat->van_id;
                    $loadheader->delivery_id    = $dat->delivery_id;
                    $loadheader->depot_id       = null;
                    $loadheader->salesman_id    = $dat->delivery_driver_id;
                    $loadheader->storage_location_id = $dat->storage_location_id;
                    $loadheader->warehouse_id   = $dat->warehouse_id;
                    $loadheader->load_date      = $delivery_date;
                    $loadheader->load_type      = 1;
                    $loadheader->load_confirm   = 0;
                    $loadheader->status         = 0;
                    $loadheader->trip_number    = $dat->trip;
                    $loadheader->save();

                    $this->saveDriverJpAndVehicle($dat, $delivery, $loadheader, $delivery_date, $new_delivery);
                }

                if ($dat->qty > 0) {
                    $lower_qty = getItemDetails2($dat->item_id, $dat->item_uom_id, $dat->qty, true);
                    $loaddetail = SalesmanLoadDetails::where('salesman_load_id', $loadheader->id)
                        ->where('item_id', $dat->item_id)
                        ->where('item_uom', $dat->item_uom_id)
                        ->where('load_qty', $dat->qty)
                        ->where('dat_id', $dat->id)
                        ->first();

                    if (!$loaddetail) {
                        $loaddetail = new SalesmanLoadDetails;
                    }

                    // print_r($loadheader);
                    // exit;
                    $loaddetail->dat_id             = $dat->id;
                    $loaddetail->salesman_load_id   = $loadheader->id;
                    $loaddetail->route_id           = $loadheader->route_id;
                    $loaddetail->salesman_id        = $loadheader->salesman_id;
                    $loaddetail->storage_location_id = $loadheader->storage_location_id;
                    $loaddetail->warehouse_id       = $loadheader->warehouse_id;
                    $loaddetail->depot_id           = null;
                    $loaddetail->van_id             = $loadheader->van_id;
                    $loaddetail->load_date          = $loadheader->load_date;
                    $loaddetail->item_id            = $dat->item_id;
                    $loaddetail->item_uom           = $dat->item_uom_id;
                    $loaddetail->load_qty           = $dat->qty;
                    $loaddetail->lower_qty          = $lower_qty['Qty'];
                    $loaddetail->requested_item_uom_id = $dat->item_uom_id;
                    $loaddetail->requested_qty      = $dat->qty;
                    $loaddetail->save();

                    $this->saveLoadItemReport($loadheader, $loaddetail);
                }


                DeliveryDetail::where('delivery_id', $delivery->id)
                    ->where('delivery_id', $dat->delivery_id)
                    ->where('item_uom_id', $dat->item_uom_id)
                    ->where('transportation_status', 'No')
                    ->update([
                        'transportation_status' => "Delegated",
                        'shipment_status' => 'full'
                    ]);

                $dd = DeliveryDetail::where('delivery_id', $delivery->id)
                    ->where('transportation_status', 'No')
                    ->where('item_price', '!=', 0)
                    ->where('item_qty', '!=', 0)
                    ->where('is_deleted', '!=', 1)
                    ->first();

                if (is_object($dd)) {
                    $ts = "Partial";
                } else {
                    $ts = "Full";
                }

                if (!is_object($dd)) {
                    $delivery->approval_status = 'Shipment';
                    $delivery->shipment_status = 'full';
                    $delivery->sync_status = null;
                    $delivery->transportation_status = $ts;
                    $delivery->save();

                    Order::where('id', $dat->order_id)
                        ->update([
                            'shipment_status' => 'full',
                            'approval_status' => 'Shipment',
                        ]);

                    $orders = Order::where('id', $dat->order_id)->get();
                    $this->sendCustomerMailFile($delivery, $orders, $dat->storage_location_id);
                }

                DB::commit();
            } catch (\Exception $exception) {
                DB::rollback();
                $delivery->sync_status = $exception;
                $delivery->save();
                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            } catch (\Throwable $exception) {
                DB::rollback();
                $delivery->sync_status = $exception;
                $delivery->save();
                return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            }
        }

        if (count($er)) {
            return prepareResult(false, [], ['error' => 'Delivery number ' . implode(",", $er) . 'not attached deliery driver'], 'Delivery number ' . implode(",", $er) . 'not attached deliery driver', $this->unprocessableEntity);
        }

        return prepareResult(true, [], [], "Delivery Converted to Load", $this->success);
    }

    private function sendCustomerMailFile($delivery_invoice, $order, $storage_id)
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
            'storage_location_id' => $storage_id,
            'file_name'     => date('Y-m-d') . '/' . "INV-" . model($order, 'customer_lop') . '-' . $delivery_invoice->delivery_number,
            'url'           => $pdfFilePath
        ]);
    }

    public function deliveryNots(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "notes");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating delivery note", $this->unprocessableEntity);
        }

        $tc_qty = 0;

        if (is_array($request->details)) {

            $del = Delivery::find($request->delivery_id);

            DeliveryDetail::where('delivery_id', $request->delivery_id)
                ->where(function ($query) {
                    $query->where('item_price', '<', 1)
                        ->orWhere('item_qty', '<', 1);
                })
                ->update([
                    'delivery_status' => 'Cancelled',
                    'is_deleted' => 1
                ]);

            // is_hold == 1 means hold and is_hold == 0 means not hold
            if ($request->is_hold == 1 && !empty($request->change_date)) {
                $this->deliveryLoadUpdateDate($request, $del);
                return prepareResult(true, [], [], "Delivery notes", $this->success);
            }

            foreach ($request->details as $detail) {
                $delivery_notes = new DeliveryNote();
                $delivery_notes->delivery_id    = $request->delivery_id;
                $delivery_notes->delivery_detail_id = ($detail['delv_item_id']) ? $detail['delv_item_id'] : null;
                $delivery_notes->salesman_id    = $request->salesman_id;
                $delivery_notes->item_id        = $detail['item_id'];
                $delivery_notes->item_uom_id    = $detail['item_uom_id'];
                $delivery_notes->qty            = $detail['qty'];
                $delivery_notes->reason_id      = $detail['reason_id'];
                $delivery_notes->is_cancel      = ($detail['is_cancel']) ? 1 : 0;
                $delivery_notes->delivery_note_number = $request->delivery_note_number;
                $delivery_notes->save();

                $laod_item = LoadItem::where('delivery_id', $request->delivery_id)
                    ->where('item_id', $detail['item_id'])
                    ->where('item_uom_id', $detail['item_uom_id'])
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($laod_item && $delivery_notes->is_cancel != 1) {
                    $laod_item->update([
                        'sales_qty' => conevertQtyForRFGen($detail['item_id'], $delivery_notes->qty, $detail['item_uom_id'])
                    ]);
                }


                if ($delivery_notes) {

                    $dd = DeliveryDetail::where('delivery_id', $delivery_notes->delivery_id)
                        ->where('item_id', $delivery_notes->item_id)
                        ->where('item_uom_id', $delivery_notes->item_uom_id)
                        ->first();

                    if ($dd) {

                        // if delivery cancel
                        if ($detail['is_cancel'] == 1) {
                            // 2000 - 500
                            $detail_cancel_qty = $dd->cancel_qty + $detail['qty'];

                            if ($dd->item_qty == $detail_cancel_qty) {
                                $dd->is_deleted = 1;
                                $dd->delivery_status = 'Cancelled';
                            }

                            $dd->reason_id          = $delivery_notes->reason_id;
                            $dd->cancel_qty         = $detail_cancel_qty;
                            $dd->delivery_note_id   = $delivery_notes->id;
                            $dd->save();

                            if ($del) {
                                $cancel_qty_convert = qtyConversion($dd->item_id, $dd->item_uom_id, $detail_cancel_qty);
                                $tc_qty = $tc_qty + $cancel_qty_convert['Qty'];

                                $del->update([
                                    'total_cancel_qty'  => $tc_qty,
                                    "is_user_updated"   => 1,
                                    "user_updated"      => request()->user()->id,
                                    "module_updated"    => "Delivery Note Cancel",
                                ]);
                            }
                        } else {

                            if ($dd->item_qty == $delivery_notes->qty) {
                                $s = "full";
                            } else {
                                $s = "partial";
                            }

                            $balance_open_qty = 0;
                            $delivery_status = "";
                            $balance_open_qty = $dd->open_qty - $delivery_notes->qty;

                            if ($balance_open_qty > 0) {
                                $delivery_status = "Completed";
                            } else {
                                $delivery_status = "Invoiced";
                            }

                            $dd->delivery_status    = $delivery_status;
                            $dd->invoiced_qty       = $delivery_notes->qty;
                            $dd->open_qty           = $balance_open_qty;
                            $dd->invoice_status     = $s;
                            $dd->delivery_note_id   = $delivery_notes->id;

                            if ($dd->item_qty != $delivery_notes->qty) {
                                if (!empty($delivery_notes->reason_id)) {
                                    $dd->reason_id = $delivery_notes->reason_id;
                                }
                            }

                            $dd->save();

                            $d = Delivery::where('id', $delivery_notes->delivery_id)
                                ->first();

                            $delivery_detail = DeliveryDetail::where('invoice_status', 'partial')
                                ->first();

                            if ($delivery_detail) {
                                $ds = "partial";
                            } else {
                                $ds = "full";
                            }

                            $d->invoice_status = $ds;
                            $d->is_user_updated          = 1;
                            $d->user_updated             = request()->user()->id;
                            $d->module_updated           = "Delivery Note Cancel";
                            $d->save();

                            Order::where('id', $d->order_id)
                                ->update([
                                    'invoice_status'    => $ds,
                                    'is_user_updated'   => 1,
                                    'user_updated'      => request()->user()->id,
                                    'module_updated'    => "Delivery Note Cancel",
                                ]);
                        }
                    }
                }
            }

            $del_detais = DeliveryDetail::where('delivery_id', $request->delivery_id)
                ->where('delivery_status', '!=', 'Cancelled')
                ->first();

            if (!$del_detais) {
                $del->update([
                    'reason_id' => $delivery_notes->reason_id,
                    'approval_status' => "Cancel",
                    'total_cancel_qty' => $tc_qty,
                ]);

                Order::where('id', $del->order_id)
                    ->update([
                        'approval_status' => 'Cancelled',
                        'reason_id' => $delivery_notes->reason_id,
                    ]);
            }
        }
        return prepareResult(true, [], [], "Delivery notes", $this->success);
    }

    public function deliveryLoadUpdateDate($request, $delivery)
    {
        if (!$delivery) {
            return true;
        }

        $lheader = SalesmanLoad::where('delivery_id', $request->delivery_id)
            ->first();
        $loadDate = $lheader->load_date;

        $delivery->change_date = $request->change_date;
        $delivery->save();

        Order::where('id', $delivery->order_id)
            ->update([
                'change_date'    => $request->change_date
            ]);

        SalesmanLoad::where('delivery_id', $delivery->id)
            ->update([
                'load_date' => $request->change_date,
                'load_confirm' => 0,
                'status' => 0
            ]);

        $header = SalesmanLoad::where('delivery_id', $request->delivery_id)
            ->first();

        SalesmanLoadDetails::where('salesman_load_id', $header->id)
            ->update([
                'change_date' => $request->change_date
            ]);

        $this->saveLoadChangeItemReport($request, $loadDate);
    }

    public function invoiceNumber(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "invoice_number");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating delivery note", $this->unprocessableEntity);
        }

        Delivery::where('id', $request->delivery_id)
            ->update([
                'invoice_number' => $request->invoice_number,
                'invoice_route_id' => $request->route_id,
            ]);

        return prepareResult(true, [], [], "Invoice Number Added IN Delivery ", $this->success);
    }

    public function getinvoiceNumber($delivery_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
        $invoice_number_from_delevery = Delivery::select('invoice_number')->where('id', $delivery_id)->get();

        return prepareResult(true, $invoice_number_from_delevery, [], "Invoice Number In Delivery", $this->success);
    }

    public function getDeliveryDetails($delivery_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
        $delivery = DeliveryDetail::with(
            'deliveryAssignTemplate',
            'item:id,item_name,item_code',
            'itemUom:id,name'
        )
            ->where('delivery_id', $delivery_id)
            ->get();

        return prepareResult(true, $delivery, [], "Delivery", $this->success);
    }

    private function getCustomerCount($load_date, $delivery_driver_id)
    {
        if (!$load_date && !$delivery_driver_id) {
            return 0;
        }

        $cusotmerCount = Delivery::where('delivery_date', $load_date)
            ->wherehas('deliveryAssignTemplate', function ($q) use ($delivery_driver_id) {
                $q->where('delivery_driver_id', $delivery_driver_id);
            })
            ->groupBy('customer_id')
            ->get();

        if (count($cusotmerCount)) {
            return count($cusotmerCount);
        } else {
            return 0;
        }
    }

    private function saveOrderDetail($delivery_detail, $order_id)
    {
        $order = Order::find($order_id);
        if ($order) {
            $orderDetail = new OrderDetail;
            $orderDetail->order_id              = $order->id;
            $orderDetail->item_id               = $delivery_detail['item_id'];
            $orderDetail->item_uom_id           = $delivery_detail['item_uom_id'];
            $orderDetail->original_item_uom_id  = $delivery_detail['item_uom_id'];
            $orderDetail->discount_id           = (!empty($delivery_detail['discount_id'])) ? $delivery_detail['discount_id'] : null;
            $orderDetail->is_free               = (!empty($delivery_detail['is_free'])) ? $delivery_detail['is_free'] : 0;
            $orderDetail->is_item_poi           = (!empty($delivery_detail['is_item_poi'])) ? $delivery_detail['is_item_poi'] : 0;
            $orderDetail->promotion_id          = (!empty($delivery_detail['promotion_id'])) ? $delivery_detail['promotion_id'] : 0;
            $orderDetail->reason_id             = (!empty($delivery_detail['reason_id'])) ? $delivery_detail['reason_id'] : null;
            $orderDetail->is_deleted            = (!empty($delivery_detail['is_deleted'])) ? $delivery_detail['is_deleted'] : 0;
            $orderDetail->item_qty              = (!empty($delivery_detail['item_qty'])) ? $delivery_detail['item_qty'] : 0;
            $orderDetail->item_weight           = (!empty($delivery_detail['item_weight'])) ? $delivery_detail['item_weight'] : 0;
            $orderDetail->item_price            = (!empty($delivery_detail['item_price'])) ? $delivery_detail['item_price'] : 0;
            $orderDetail->item_gross            = (!empty($delivery_detail['item_gross'])) ? $delivery_detail['item_gross'] : 0;
            $orderDetail->item_discount_amount  = (!empty($delivery_detail['item_discount_amount'])) ? $delivery_detail['item_discount_amount'] : 0;
            $orderDetail->item_net              = (!empty($delivery_detail['item_net'])) ? $delivery_detail['item_net'] : 0;
            $orderDetail->item_vat              = (!empty($delivery_detail['item_vat'])) ? $delivery_detail['item_vat'] : 0;
            $orderDetail->item_excise           = (!empty($delivery_detail['item_excise'])) ? $delivery_detail['item_excise'] : 0;
            $orderDetail->item_grand_total      = (!empty($delivery_detail['item_grand_total'])) ? $delivery_detail['item_grand_total'] : 0;
            $orderDetail->original_item_qty     = (!empty($delivery_detail['item_qty'])) ? $delivery_detail['item_qty'] : 0;
            $orderDetail->original_item_price   = (!empty($delivery_detail['item_price'])) ? $delivery_detail['item_price'] : 0;
            $orderDetail->save();

            $getItemQtyByUom = qtyConversion($delivery_detail['item_id'], $delivery_detail['item_uom_id'], $delivery_detail['item_qty']);

            $order->total_qty + $getItemQtyByUom['Qty'];
            $order->total_gross             = $order->total_gross + $orderDetail->item_price;
            $order->total_discount_amount   = $order->total_discount_amount + $orderDetail->item_discount_amount;
            $order->total_net               = $order->total_net + $orderDetail->item_net;
            $order->total_vat               = $order->total_vat + $orderDetail->item_vat;
            $order->total_excise            = $order->total_excise + $orderDetail->item_excise;
            $order->grand_total             = $order->grand_total + $orderDetail->item_grand_total;
            $order->save();

            $data = [
                'created_user'          => request()->user()->id,
                'order_id'              => $orderDetail->id,
                'delviery_id'           => NULL,
                'updated_user'          => request()->user()->id,
                'previous_request_body' => NULL,
                'request_body'          => $orderDetail,
                'action'                => 'Delivery Order New Item',
                'status'                => 'Created',
            ];

            saveOrderDeliveryLog($data);

            // Send Notification to sales team
            $this->sendNotificationToDCUser($order, $orderDetail);
            return $orderDetail;
        }
    }

    private function sendNotificationToDCUser($order, $orderDetail)
    {
        $org_role = OrganisationRole::select('id')->where('name', 'Storekeeper')->first();

        if ($org_role) {
            $users = User::where('role_id', $org_role->id)->get();
            $users->each(function ($user, $key) use ($order, $orderDetail) {

                $change_user = User::find(request()->user()->id);
                $message = $change_user->getName() . ' add new item ' . model($orderDetail->item, 'item_code') . ' with ' . $orderDetail->item_qty . ' and ' . model($orderDetail->itemUom, 'name');

                $dataNofi = array(
                    'uuid' => $order->uuid,
                    'user_id' => $user->id,
                    'type' => "Delivery Changed",
                    'other' => request()->user()->id,
                    'message' => $message,
                    'status' => 1,
                    'title' => "Delivery Changed",
                    'noti_type' => "Order",
                );

                saveNotificaiton($dataNofi);
            });
        }
    }

    private function saveHeaderDeliveryAssignTemplate(
        $delivery,
        $delivery_details,
        $salesmanInfo,
        $row
    ) {
        if (
            $delivery_details->item_id > 0 &&
            $delivery_details->item_price > 0
        ) {
            $dat = new DeliveryAssignTemplate();
            $dat->uuid = (string) \Uuid::generate();
            $dat->order_id = $delivery->order_id;
            $dat->delivery_id = $delivery->id;
            $dat->delivery_details_id = $delivery_details->id;
            $dat->storage_location_id = $delivery->storage_location_id;
            $dat->warehouse_id = getWarehuseBasedOnStorageLoacation(
                $delivery->storage_location_id,
                false
            );
            $dat->customer_id = $delivery->customer_id;
            $dat->delivery_driver_id = $salesmanInfo->user_id;
            $dat->item_id = $delivery_details->item_id;
            $dat->item_uom_id = $delivery_details->item_uom_id;
            $dat->qty = $delivery_details->item_qty;
            $dat->amount = $delivery_details->item_price;
            $dat->delivery_sequence = $row[8];
            $dat->trip = $row[9];
            $dat->actual_trip = $row[9];
            // $dat->trip_sequence = $row[10];
            // $dat->van_id = (!empty($van)) ? $van->id : null;
            $dat->is_last_trip = $row[11];
            $dat->save();

            DeliveryDetail::where("id", $dat->delivery_details_id)->update([
                "transportation_status" => "Delegated",
            ]);

            DeliveryDetail::where("delivery_id", $delivery->id)
                ->where("item_price", 0)
                ->orWhere("item_qty", 0)
                ->orWhere("is_deleted", 1)
                ->update([
                    "transportation_status" => "Delegated",
                ]);

            $data = [
                "created_user" => request()->user()->id,
                "order_id" => $delivery->order_id,
                "delviery_id" => $delivery->order_id,
                "updated_user" => request()->user()->id,
                "previous_request_body" => null,
                "request_body" => $dat,
                "action" => "Delivery TEMPLATE",
                "status" => "Created",
            ];

            // saveOrderDeliveryLog($data);

            $this->sendNotificationToDeliveryDriver(null, $delivery);
        }
    }

    private function saveSKUDeliveryAssignTemplate(
        $delivery,
        $delivery_details,
        $salesmanInfo,
        $item,
        $uom,
        $row
    ) {
        if (empty($delivery->salesman_id)) {
            $delivery->salesman_id = $salesmanInfo->user_id;
            $delivery->save();
        }

        $dat = new DeliveryAssignTemplate();
        $dat->uuid = (string) \Uuid::generate();
        $dat->order_id = $delivery->order_id;
        $dat->delivery_id = $delivery->id;
        $dat->delivery_details_id = $delivery_details;
        $dat->customer_id = $delivery->customer_id;
        $dat->delivery_driver_id = $salesmanInfo->user_id;
        $dat->storage_location_id = $delivery->storage_location_id;
        $dat->warehouse_id = getWarehuseBasedOnStorageLoacation(
            $delivery->storage_location_id,
            false
        );
        $dat->item_id = $item;
        // $dat->item_uom_id           = (!empty($uom)) ? $uom->id : null;
        $dat->item_uom_id = $delivery_details->item_uom_id;
        $dat->qty = $row[8];
        $dat->amount = $row[9];
        $dat->delivery_sequence = $row[10];
        $dat->trip = $row[11];
        $dat->actual_trip = $row[11];
        $dat->is_last_trip = $row[13];
        $dat->save();

        DeliveryDetail::where("id", $dat->delivery_details_id)->update([
            "transportation_status" => "Delegated",
        ]);

        DeliveryDetail::where("delivery_id", $delivery->id)
            ->where("item_price", 0)
            ->orWhere("item_qty", 0)
            ->orWhere("is_deleted", 1)
            ->update([
                "transportation_status" => "Delegated",
            ]);

        $data = [
            "created_user" => request()->user()->id,
            "order_id" => $delivery->order_id,
            "delviery_id" => $delivery->order_id,
            "updated_user" => request()->user()->id,
            "previous_request_body" => null,
            "request_body" => $dat,
            "action" => "Delivery TEMPLATE",
            "status" => "Created",
        ];

        // saveOrderDeliveryLog($data);

        $this->sendNotificationToDeliveryDriver($dat);
    }

    private function saveSKUDeliveryAssignTemplateNew(
        $order_id,
        $customer_id,
        $delivery_id,
        $delivery_details_id,
        $salesmanInfo,
        $item,
        $uom,
        $storage_location_id,
        $row
    ) {
        if ($delivery_id != '') {
            $delivery = Delivery::where('id', $delivery_id)->first();
            $delivery->salesman_id = $salesmanInfo->user_id;
            $delivery->save();
        }

        // DeliveryAssignTemplate::where('order_id',$order_id)
        // ->where('delivery_id', $delivery_id)
        // ->where('delivery_details_id',$delivery_details_id)
        // ->where('item_id',$item)
        // ->where('qty','=',$row[8])->delete();

        $dat = new DeliveryAssignTemplate();
        $dat->uuid = (string) \Uuid::generate();
        $dat->order_id = $order_id;
        $dat->delivery_id = $delivery_id;
        $dat->delivery_details_id = $delivery_details_id;
        $dat->customer_id = $customer_id;
        $dat->delivery_driver_id = $salesmanInfo->user_id;
        $dat->storage_location_id = $storage_location_id;
        $dat->warehouse_id = getWarehuseBasedOnStorageLoacation(
            $storage_location_id,
            false
        );
        $dat->item_id = $item;
        // $dat->item_uom_id           = (!empty($uom)) ? $uom->id : null;
        $dat->item_uom_id = $uom;
        $dat->qty = $row[8];
        $dat->amount = $row[9];
        $dat->delivery_sequence = $row[10];
        $dat->trip = $row[11];
        $dat->actual_trip = $row[11];
        $dat->is_last_trip = $row[13];
        $dat->save();

        DeliveryDetail::where("id", $dat->delivery_details_id)->update([
            "transportation_status" => "Delegated",
        ]);

        // DeliveryDetail::where("delivery_id", $delivery->id)
        //     ->where("item_price", 0)
        //     ->orWhere("item_qty", 0)
        //     ->orWhere("is_deleted", 1)
        //     ->update([
        //         "transportation_status" => "Delegated",
        //     ]);

        // DeliveryDetail::where("delivery_id", $delivery)
        //     ->where("item_price", 0)
        //     ->orWhere("item_qty", 0)
        //     ->orWhere("is_deleted", 1)
        //     ->update([
        //         "transportation_status" => "Delegated",
        //     ]);

        $data = [
            "created_user" => request()->user()->id,
            "order_id" => $delivery->order_id,
            "delviery_id" => $delivery->order_id,
            "updated_user" => request()->user()->id,
            "previous_request_body" => null,
            "request_body" => $dat,
            "action" => "Delivery TEMPLATE",
            "status" => "Created",
        ];

        // saveOrderDeliveryLog($data);

        //$this->sendNotificationToDeliveryDriver($dat);
    }

    private function updateDeliveryTemplate($delivery_details)
    {
        DeliveryAssignTemplate::where('delivery_details_id', $delivery_details->id)
            ->update([
                'item_id' => $delivery_details->item_id,
                'item_uom_id' => $delivery_details->item_uom_id,
                'qty' => $delivery_details->item_qty,
                'amount' => $delivery_details->item_price,
            ]);
    }

    private function sendNotificationToDeliveryDriver($delivery_detail = null, $delivery = null)
    {
        if (is_object($delivery)) {
            $nofi = Notifications::where('user_id', $delivery->salesman_id)
                ->where('sender_id', $delivery->id)
                ->first();

            if (!$nofi) {
                // $customerInfo = CustomerInfo::where('user_id', $delivery->customer_id)->first();
                if (is_object($delivery->customer)) {
                    // $message = "You have to tomorrow delivery $delivery->delivery_number to " . $delivery->customer->getName() . " - " . $delivery->customerInfo->customer_code;
                    $message = "You have to tomorrow delivery $delivery->delivery_number";

                    $dataNofi = array(
                        'uuid' => $delivery->uuid,
                        'user_id' => $delivery->salesman_id,
                        'type' => "Delivery To Customer",
                        'other' => $delivery->salesman_id,
                        'message' => $message,
                        'status' => 1,
                        'title' => "Delivery To Cusotmer",
                        'noti_type' => "Delivery",
                        'reason' => '',
                        'customer_id' => '',
                        'lat' => '',
                        'long' => '',
                    );

                    $device_detail = DeviceDetail::where('user_id', $delivery->salesman_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if (is_object($device_detail)) {
                        $t = $device_detail->device_token;
                        sendNotificationAndroid($dataNofi, $t);
                    }

                    saveNotificaiton($dataNofi);
                }
            }
        } else {
            $nofi = Notifications::where('user_id', $delivery_detail->delivery_driver_id)
                ->where('sender_id', request()->user()->id)
                ->first();

            if (!$nofi) {
                $delivery_number = model($delivery_detail->delivery, 'delivery_number');

                $message = "You have to tomorrow delivery $delivery_number to " . $delivery_detail->customer->getName() . " - " . model($delivery_detail->customerInfo, 'customer_code');

                $dataNofi = array(
                    'uuid' => $delivery_detail->uuid,
                    'user_id' => $delivery_detail->delivery_driver_id,
                    'type' => "Delivery To Customer",
                    'other' => $delivery_detail->delivery_driver_id,
                    'message' => $message,
                    'status' => 1,
                    'title' => "Delivery To Cusotmer",
                    'type' => "Delivery",
                    'noti_type' => "Delivery",
                    'status' => 1,
                );

                $device_detail = DeviceDetail::where('user_id', $delivery_detail->delivery_driver_id)
                    ->orderBy('id', 'desc')
                    ->first();

                if (is_object($device_detail)) {
                    $t = $device_detail->device_token;
                    sendNotificationAndroid($dataNofi, $t);
                }

                saveNotificaiton($dataNofi);
            }
        }
    }

    public function templateAssingDetails($delivery_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$delivery_id) {
            return prepareResult(false, [], ["error" => "delivery id is required"], "delivery id is required.", $this->unauthorized);
        }

        $dat = DeliveryAssignTemplate::with(
            'order:id,order_number',
            'delivery:id,delivery_number',
            'customerInfo:id,user_id,customer_code',
            'customer:id,firstname,lastname',
            'deliveryDriver:id,firstname,lastname',
            'deliveryDriverInfo:id,user_id,salesman_code',
            'van:id,van_code',
            'item:id,item_code,item_name',
            'itemUom:id,name'
        )
            ->where('delivery_id', $delivery_id)->get();

        if (count($dat)) {
            return prepareResult(true, $dat, [], "Listing", $this->success);
        }

        return prepareResult(false, [], [], "Delivery template not found", $this->unprocessableEntity);
    }

    /**
     * This is the funciton which use of the update the delivery template row.
     */
    public function deliveryTemplateUpdate(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "template-update");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating delivery template update", $this->unprocessableEntity);
        }

        $dta = DeliveryAssignTemplate::where('delivery_id', $request->delivery_id)
            ->first();

        if (!$dta) {
            return prepareResult(false, [], ['error' => 'Delivery Not Found.'], "Delivery Not Found.", $this->unprocessableEntity);
        }

        $errors = array();
        $van_error = array();
        $dd_error = array();
        $is_last = array();

        foreach ($request->items as $key => $item) {

            // $van = Van::where('van_code', $item['van_id'])->first();
            $ddc = SalesmanInfo::where('salesman_code', $item['delivery_driver_id'])->first();

            // if (!$van) {
            //     if (!in_array($item['van_id'], $van_error)) {
            //         $errors[] = "Vehicle number not found.";
            //         $van_error[] = $item['van_id'];
            //     }
            // }

            if (!$ddc) {
                if (!in_array($item['delivery_driver_id'], $dd_error)) {
                    $errors[] = "Delivery driver not found.";
                    $dd_error[] = $item['delivery_driver_id'];
                }
            }

            if ($item['is_last_trip'] == 1) {
                if (!in_array($ddc->user_id, $is_last)) {
                    $is_last[] = $ddc->user_id;
                }
            }

            if (count($is_last) > 1) {
                return prepareResult(false, [], ['error' => "You have given permission to generate the invoice for more than 1 driver."], "You have given permission to generate the invoice for more than 1 driver.", $this->unprocessableEntity);
            }


            if (count($errors)) {
                return prepareResult(false, [], $errors, "Delivery template not update.", $this->unprocessableEntity);
            }

            DeliveryAssignTemplate::where('id', $item['id'])
                ->update([
                    'qty' => ($item['is_deleted'] == 1) ? 0 : $item['qty'],
                    'trip' => $item['trip'],
                    'delivery_driver_id' => ($ddc) ? $ddc->user_id : 0,
                    // 'van_id' => ($van) ? $van->id : 0,
                    'is_last_trip' => $item['is_last_trip'],
                    'is_deleted' => $item['qty'] == 0 ? 1 : $item['is_deleted'],
                    'reason_id' => $item['reason_id'],
                ]);

            if ($key == 0) {
                Delivery::where('id', $request->delivery_id)
                    ->update([
                        'salesman_id' => ($ddc) ? $ddc->user_id : NULL
                    ]);
            }
        }

        return prepareResult(true, [], [], "Delivery update.", $this->success);
    }

    private function saveDriverJpAndVehicle($dat, $delivery, $loadheader, $delivery_date, $new_delivery)
    {
        $ddjp = DeliveryDriverJourneyPlan::where('date', $delivery_date)
            ->where('delivery_driver_id', $loadheader->salesman_id)
            ->where('customer_id', $dat->customer_id)
            ->first();

        if (!$ddjp) {
            $ddjp = new DeliveryDriverJourneyPlan;
            $ddjp->date = $delivery_date;
            $ddjp->delivery_driver_id = $loadheader->salesman_id;
            $ddjp->customer_id = $dat->customer_id;
            $ddjp->save();
        }

        // $sv = SalesmanVehicle::where('salesman_id', $loadheader->salesman_id)
        //     ->where('van_id', $loadheader->van_id)
        //     ->where('date', $loadheader->load_date)
        //     ->first();

        // if (!$sv) {
        //     $sv = new SalesmanVehicle;
        //     $sv->salesman_id = $loadheader->salesman_id;
        //     $sv->van_id = $loadheader->van_id;
        //     $sv->route_id = $loadheader->route_id;
        //     $sv->date = $loadheader->load_date;
        //     $sv->save();

        //     if ($loadheader->salesman_id) {
        //         // find the route base on vehicle
        //         $r = Route::where('van_id', $loadheader->van_id)->first();
        //         if ($r) {
        //             SalesmanInfo::where('user_id', $loadheader->salesman_id)
        //                 ->update([
        //                     'route_id' => $r->id,
        //                 ]);
        //         }
        //     }
        // }

        // $vu = VehicleUtilisation::where('vehicle_id', $loadheader->van_id)
        //     ->where('transcation_date', $delivery_date)
        //     ->first();

        // if (!$vu) {

        //     $region_code = null;
        //     $region_name = null;

        //     if (is_object($dat->delivery->customerRegion)) {
        //         if ($dat->delivery->customerRegion->region) {
        //             $region_code = $dat->delivery->customerRegion->region->region_code;
        //             $region_name = $dat->delivery->customerRegion->region->region_code;
        //         }
        //     }

        //     // if record not exist then create new record
        //     $vu = new VehicleUtilisation();
        //     $vu->region_id = model($dat->delivery->customerRegion, 'region_id');
        //     $vu->region_code = $region_code;
        //     $vu->region_name = $region_name;
        //     $vu->vehicle_id = $dat->van_id;
        //     $vu->vehicle_code = model($dat->van, 'van_code');
        //     $vu->customer_count = $this->getCustomerCount($loadheader->load_date, $dat->van_id);
        //     $vu->delivery_qty = model($dat->delivery, 'total_qty');
        //     $vu->cancle_count = 0;
        //     $vu->cancel_qty = model($dat->delivery, 'total_cancel_qty');
        //     $vu->transcation_date = $loadheader->load_date;
        //     $vu->less_delivery_count = (model($dat->order, 'total_qty') <= 10) ? 1 : 0;
        //     $vu->order_count = 1;
        //     $vu->order_qty = model($dat->order, 'total_qty');
        //     $vu->vehicle_capacity = model($dat->van, 'capacity');
        //     $vu->save();
        // } else {
        //     if ($new_delivery) {
        //         $vu->update([
        //             'customer_count' => $this->getCustomerCount($loadheader->load_date, $dat->van_id),
        //             'delivery_qty' => $vu->delivery_qty + $dat->delivery->total_qty,
        //             'cancle_count' => $vu->cancle_count + $dat->delivery->total_cancel_qty,
        //             'less_delivery_count' => (model($dat->order, 'total_qty') <= 10) ? $vu->less_delivery_count + 1 : $vu->less_delivery_count,
        //             'order_count' => $vu->order_count + 1,
        //             'order_qty' => $vu->order_qty + model($dat->order, 'total_qty'),
        //         ]);
        //     }
        // }
        $new_delivery = false;
        // }

        // $dds = DeliveryAssignTemplate::where('delivery_id', $dat->deliver_id)
        //     ->groupBy('delivery_driver_id')
        //     ->get();

        // if (count($dds)) {
        //     foreach ($dds as $d) {
        //         if ($d->salesman_id) {
        //             $data = array(
        //                 'uuid' => $d->uuid,
        //                 'user_id' => $d->delivery_driver_id,
        //                 'type' => 'Delivery Driver Assign',
        //                 'message' => "Tomorrow you have a " . $delivery->delivery_number . 'to ' . $d->customer->getName() . ' - ' . model($d->customerInfo, 'customer_code'),
        //                 'status' => 1,
        //             );

        //             $dataNofi = array(
        //                 'uuid' => $d->uuid,
        //                 'user_id' => $d->delivery_driver_id,
        //                 'type' => 'Delivery Driver Assign',
        //                 'sender_id' => null,
        //                 'message' => "Tomorrow you have a " . $delivery->delivery_number . 'to ' . $d->customer->getName() . ' - ' . model($d->customerInfo, 'customer_code'),
        //                 'status' => 1,
        //                 'title' => "Tomorrow Delivery",
        //                 'noti_type' => "Delivery",
        //                 'reason' => null,
        //                 'customer_id' => $d->customer_id,
        //             );

        //             $device_detail = DeviceDetail::where('user_id', $d->salesman_id)
        //                 ->orderBy('id', 'desc')
        //                 ->first();

        //             if (is_object($device_detail)) {
        //                 $t = $device_detail->device_token;
        //                 sendNotificationAndroid($dataNofi, $t);
        //             }

        //             saveNotificaiton($data);
        //         }
        //     }
        // }
    }

    private function saveLoadChangeItemReport($request, $loadDate)
    {

        $header = SalesmanLoad::where('delivery_id', $request->delivery_id)
            ->first();

        $load_detail = SalesmanLoadDetails::where('salesman_load_id', $header->id)->get();

        foreach ($load_detail as $detail) {

            $laod_item = LoadItem::where('delivery_id', $header->delivery_id)
                ->where('item_id', $detail->item_id)
                ->where('item_uom_id', $detail->item_uom)
                ->where('report_date', $request->change_date)
                ->first();

            $oldLoad_item = LoadItem::where('delivery_id', $header->delivery_id)
                ->where('item_id', $detail->item_id)
                ->where('item_uom_id', $detail->item_uom)
                ->where('report_date', $loadDate)
                ->first();

            // Converted qty add
            $main_price = ItemMainPrice::where('item_id', $detail->item_id)
                ->where('is_secondary', 1)
                ->first();

            $dmd_lower_upc = ($main_price) ? $main_price->item_upc : 1;

            if ($laod_item) {
                $laod_item->prv_load_qty = $detail->prv_load_qty + conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
                $laod_item->dmd_lower_upc = $dmd_lower_upc;
                $laod_item->save();
            } else {

                $cr = CustomerRegion::where('customer_id', $header->customer_id)->first();

                $load_item = new LoadItem;
                $load_item->delivery_id             = $header->delivery_id;
                $load_item->van_id                  = $header->van_id;
                $load_item->van_code                = model($header->van, 'van_code');
                $load_item->storage_location_id     = $header->storage_location_id;
                $load_item->storage_location_code   = model($header->storageocation, 'code');
                $load_item->zone_id                 = ($cr) ? $cr->zone_id : NULL;
                $load_item->zone_name               = ($cr) ? model($cr->zone, 'name') : NULL;
                $load_item->load_number             = $header->load_number;
                $load_item->salesman_id             = $header->salesman_id;
                $load_item->salesman_code           = model($header->salesman_infos, 'salesman_code');
                $load_item->item_id                 = $detail->item_id;
                $load_item->item_uom_id             = $detail->item_uom;
                $load_item->item_uom                = model($detail->itemUOM, 'code');
                $load_item->loadqty                 = 0;
                $load_item->prv_load_qty            = conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
                $load_item->return_qty              = 0; // 
                $load_item->sales_qty               = 0; // Invoice qty
                $load_item->unload_qty              = 0;
                $load_item->damage_qty              = 0;
                $load_item->expiry_qty              = 0;
                $load_item->report_date             = $request->change_date;
                $load_item->dmd_lower_upc           = $dmd_lower_upc;
                $load_item->save();
            }

            if ($oldLoad_item) {
                $oldLoad_item->on_hold_qty = $detail->on_hold_qty + conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
                $oldLoad_item->dmd_lower_upc = $dmd_lower_upc;
                $oldLoad_item->save();
            } else {

                $cr = CustomerRegion::where('customer_id', $header->customer_id)->first();

                $load_item = new LoadItem;
                $load_item->delivery_id             = $header->delivery_id;
                $load_item->van_id                  = $header->van_id;
                $load_item->van_code                = model($header->van, 'van_code');
                $load_item->storage_location_id     = $header->storage_location_id;
                $load_item->storage_location_code   = model($header->storageocation, 'code');
                $load_item->zone_id                 = ($cr) ? $cr->zone_id : NULL;
                $load_item->zone_name               = ($cr) ? model($cr->zone, 'name') : NULL;
                $load_item->load_number             = $header->load_number;
                $load_item->salesman_id             = $header->salesman_id;
                $load_item->salesman_code           = model($header->salesman_infos, 'salesman_code');
                $load_item->item_id                 = $detail->item_id;
                $load_item->item_uom_id             = $detail->item_uom;
                $load_item->item_uom                = model($detail->itemUOM, 'code');
                $load_item->loadqty                 = 0;
                $load_item->prv_load_qty            = 0;
                $load_item->on_hold_qty             = conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
                $load_item->return_qty              = 0; // 
                $load_item->sales_qty               = 0; // Invoice qty
                $load_item->unload_qty              = 0;
                $load_item->damage_qty              = 0;
                $load_item->expiry_qty              = 0;
                $load_item->report_date             = $loadDate;
                $load_item->dmd_lower_upc           = $dmd_lower_upc;
                $load_item->save();
            }
        }
    }
    private function saveLoadItemReport($header, $detail)
    {

        $qtyCon = conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
        $laod_item = LoadItem::where('delivery_id', $header->delivery_id)
            ->where('item_id', $detail->item_id)
            ->where('item_uom_id', $detail->item_uom)
            ->where('loadqty', $qtyCon)
            ->first();


        // Converted qty add
        $main_price = ItemMainPrice::where('item_id', $detail->item_id)
            ->where('is_secondary', 1)
            ->first();

        $dmd_lower_upc = ($main_price) ? $main_price->item_upc : 1;

        if ($laod_item) {
            // $laod_item->loadqty = $detail->load_qty + conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
            // $laod_item->dmd_lower_upc = $dmd_lower_upc;
            // $laod_item->save();
        } else {

            $cr = CustomerRegion::where('customer_id', $header->customer_id)->first();

            $load_item = new LoadItem;
            $load_item->delivery_id             = $header->delivery_id;
            $load_item->van_id                  = $header->van_id;
            $load_item->van_code                = model($header->van, 'van_code');
            $load_item->storage_location_id     = $header->storage_location_id;
            $load_item->storage_location_code   = model($header->storageocation, 'code');
            $load_item->zone_id                 = ($cr) ? $cr->zone_id : NULL;
            $load_item->zone_name               = ($cr) ? model($cr->zone, 'name') : NULL;
            $load_item->load_number             = $header->load_number;
            $load_item->salesman_id             = $header->salesman_id;
            $load_item->salesman_code           = model($header->salesman_infos, 'salesman_code');
            $load_item->item_id                 = $detail->item_id;
            $load_item->item_uom_id             = $detail->item_uom;
            $load_item->item_uom                = model($detail->itemUOM, 'code');
            $load_item->loadqty                 = conevertQtyForRFGen($detail->item_id, $detail->load_qty, $detail->item_uom);
            $load_item->return_qty              = 0; // 
            $load_item->sales_qty               = 0; // Invoice qty
            $load_item->unload_qty              = 0;
            $load_item->damage_qty              = 0;
            $load_item->expiry_qty              = 0;
            $load_item->report_date             = $header->load_date;
            $load_item->dmd_lower_upc           = $dmd_lower_upc;
            $load_item->save();
        }
    }


    public function load_item()
    {
        $salesman_load = SalesmanLoad::get();
        if (count($salesman_load)) {
            foreach ($salesman_load as $header) {
                if (count($header->salesmanLoadDetails)) {
                    foreach ($header->salesmanLoadDetails as $detail) {
                        $this->saveLoadItemReport($header, $detail);
                    }
                }
            }
        }

        $salesman_load = SalesmanUnload::get();
        if (count($salesman_load)) {
            foreach ($salesman_load as $header) {
                if (count($header->salesmanUnloadDetail)) {
                    foreach ($header->salesmanUnloadDetail as $detail) {
                        $this->saveUnloadLoadItem($header, $detail);
                    }
                }
            }
        }

        $deliveryNote = DeliveryNote::get();

        foreach ($deliveryNote as $note) {
            $laod_item = LoadItem::where('delivery_id', $note->delivery_id)
                ->where('item_id', $note->item_id)
                ->where('item_uom_id', $note->item_uom_id)
                ->first();

            if ($laod_item && $note->is_cancel != 1) {
                $laod_item->update([
                    'sales_qty' => conevertQtyForRFGen($note->item_id, $note->qty, $note->item_uom_id)
                ]);
            }
        }
    }

    private function saveUnloadLoadItem($header, $detail)
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
                'damage_qty' => ($detail->unload_type == 2) ? $laod_item->unload_qty + conevertQtyForRFGen($detail->item_id, $detail->unload_qty, $detail->item_uom) : 0,
                'expiry_qty' => ($detail->unload_type == 3) ? $laod_item->unload_qty + conevertQtyForRFGen($detail->item_id, $detail->unload_qty, $detail->item_uom) : 0
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
            $laod_item->unload_qty              = (conevertQtyForRFGen($detail->item_id, $detail->unload_qty, $detail->item_uom) > 0) ? conevertQtyForRFGen($detail->item_id, $detail->unload_qty, $detail->item_uom) : 0;
            $laod_item->damage_qty              = 0;
            $laod_item->expiry_qty              = 0;
            $laod_item->report_date             = $detail->unload_date;
            $laod_item->dmd_lower_upc           = $dmd_lower_upc;
            $laod_item->save();
        }
    }

    public function getDeliveryNoteById($id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$id) {
            return prepareResult(false, [], [], "delivery id is required", $this->unprocessableEntity);
        }

        $deliveryNote = DeliveryNote::with(
            'delivery',
            'item:id,item_name,item_code',
            'itemUom:id,name',
            'reason',
            'salesmanInfo:id,user_id,salesman_code'
        )
            ->where('delivery_id', $id)
            ->get();

        if (count($deliveryNote)) {
            return prepareResult(true, $deliveryNote, [], "Delivery Notes", $this->success);
        }

        return prepareResult(false, [], [], "delivery id not found", $this->not_found);
    }

    /**
     * Only update delivery note reason
     *
     * @param [type] $id
     * @return void
     */
    public function deliveryNoteReasonUpdate1(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->detail)) {
            return prepareResult(false, [], [], "User not authenticate", $this->unprocessableEntity);
        }

        foreach ($request->detail as $detail) {
            $deliveryNote = DeliveryNote::find($detail['id']);
            if ($deliveryNote) {
                $deliveryNote->reason_id = $detail['reason'];
                $deliveryNote->save();
            }
        }

        return prepareResult(true, [], [], "Reason updated", $this->success);
    }


    public function deliveryNoteReasonUpdate(Request $request)
    {


        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->detail)) {
            return prepareResult(false, [], [], "User not authenticate", $this->unprocessableEntity);
        }

        $delivery = Delivery::find($request->delivery_id);

        $Order = Order::where('id', $delivery->order_id)->first();

        foreach ($request->detail as $detail) {
            $deliveryNote = DeliveryNote::where('delivery_id', $request->delivery_id)->where('id', $detail['item_id'])->where('reason_id', '!=', null)->first();


            if ($deliveryNote != '') {
                $deliveryNoteUpdate = DeliveryNote::find($deliveryNote->id);
                $deliveryNoteUpdate->reason_id = $detail['reason'];
                $deliveryNoteUpdate->save();

                $DeliveryDetail = DeliveryDetail::where('delivery_id', $request->delivery_id)->where('id', $deliveryNote->delivery_detail_id)->where('reason_id', '!=', null)->first();

                if ($DeliveryDetail != '') {
                    $DeliveryDetailUpdate = DeliveryDetail::find($DeliveryDetail->id);
                    $DeliveryDetailUpdate->reason_id = $detail['reason'];
                    $DeliveryDetailUpdate->save();
                }

                if ($Order != '') {
                    $Item = Item::find($deliveryNote->item_id);
                    $ReasonType = ReasonType::find($detail['reason']);


                    if (is_object($Item) && is_object($ReasonType)) {
                        $OrderReport = OrderReport::where('order_no', $Order->order_number)->where('item_code', $Item->item_code)->where('driver_reason', '!=', '')->first();
                        if ($OrderReport != '') {

                            $OrderReportUpdate = OrderReport::find($OrderReport->id);
                            $OrderReportUpdate->driver_reason = $ReasonType->code;
                            $OrderReportUpdate->save();
                        }
                    }
                }
            }

            if ($request->full_order_cancel == '1') {

                if ($request->detail[0]['reason']) {

                    $delivery->reason_id = $request->detail[0]['reason'];

                    $delivery->save();
                }
            }
        }

        return prepareResult(true, [], [], "Reason updated", $this->success);
    }


    /**
     * This is the funciton which use of the update the delivery template row.
     */
    public function deliveryTripChange(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(
                true,
                [],
                [],
                "User not authenticate",
                $this->unauthorized
            );
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "trip-update");
        if ($validate["error"]) {
            return prepareResult(
                false,
                [],
                $validate["errors"]->first(),
                "Error while validating delivery template update",
                $this->unprocessableEntity
            );
        }

        $dta = DeliveryAssignTemplate::where(
            "delivery_id",
            $request->delivery_id
        )->first();

        if (!$dta) {
            return prepareResult(
                false,
                [],
                ["error" => "Delivery Not Found."],
                "Delivery Not Found.",
                $this->unprocessableEntity
            );
        }

        DeliveryAssignTemplate::where(
            "delivery_id",
            $request->delivery_id
        )->update([
            "trip" => $request->trip,
        ]);

        return prepareResult(true, [], [], "Delivery update.", $this->success);
    }

    public function deliveryCodeChange(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(
                true,
                [],
                [],
                "User not authenticate",
                $this->unauthorized
            );
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "code-update");
        if ($validate["error"]) {
            return prepareResult(
                false,
                [],
                $validate["errors"]->first(),
                "Error while validating delivery template update",
                $this->unprocessableEntity
            );
        }

        $dta = DeliveryAssignTemplate::where(
            "delivery_id",
            $request->delivery_id
        )->first();

        if (!$dta) {
            return prepareResult(
                false,
                [],
                ["error" => "Delivery Not Found."],
                "Delivery Not Found.",
                $this->unprocessableEntity
            );
        }

        DeliveryAssignTemplate::where(
            "delivery_id",
            $request->delivery_id
        )->update([
            "delivery_driver_id" => $request->delivery_driver_id,
        ]);
        Delivery::where(
            "id",
            $request->delivery_id
        )->update([
            "salesman_id" => $request->delivery_driver_id,
        ]);

        return prepareResult(true, [], [], "Delivery update.", $this->success);
    }
    /**
     * This is the funciton which use of the update the delivery template row.
     */
    public function deliveryBulkTripChange(Request $request)
    {

        if (!$this->isAuthorized) {
            return prepareResult(
                true,
                [],
                [],
                "User not authenticate",
                $this->unauthorized
            );
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "trip-update");
        if ($validate["error"]) {
            return prepareResult(
                false,
                [],
                $validate["errors"]->first(),
                "Error while validating delivery template update",
                $this->unprocessableEntity
            );
        }

        $dta = DeliveryAssignTemplate::whereIn(
            "delivery_id",
            $request->delivery_id
        )->first();

        if (!$dta) {
            return prepareResult(
                false,
                [],
                ["error" => "Delivery Not Found."],
                "Delivery Not Found.",
                $this->unprocessableEntity
            );
        }

        DeliveryAssignTemplate::whereIn(
            "delivery_id",
            $request->delivery_id
        )->update([
            "trip" => $request->trip_id,
        ]);

        return prepareResult(true, [], [], "Trip updated in Delivery Assign Template .", $this->success);
    }




    public function getMerchandiserDate(Request $request)
    {
        //dd($request->all());
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $deliveries = Delivery::with('deliveryDetails', 'customer')->where('salesman_id', $request->salesman_id)->whereDate('delivery_date', $request->date)->get();

        if (count($deliveries)) {
            return prepareResult(true, $deliveries, [], "Merchandiser List", $this->success);
        }
        return prepareResult(false, [], [], "Merchandiser data not found", $this->success);
    }

    function _csv_row_count($filename)
    {
        ini_set('auto_detect_line_endings', TRUE);
        $row_count = 0;
        if (($handle = fopen($filename, "r")) !== FALSE) {
            while (($row_data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                $row_count++;
            }
            fclose($handle);
            // Exclude the headings.
            $row_count--;
            return $row_count;
        }
    }
    function _csv_slice($filename, $start, $desired_count)
    {
        $row = 0;
        $count = 0;
        $rows = array();
        if (($handle = fopen($filename, "r")) === FALSE) {
            return FALSE;
        }
        while (($row_data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            // print_r($row_data);
            // Grab headings.
            if ($row == 0) {
                $headings = $row_data;
                $row++;
                continue;
            }

            // Not there yet.
            if ($row++ < $start) {
                continue;
            }

            $rows[] = $row_data;
            $count++;
            if ($count == $desired_count) {
                return $rows;
            }
        }
        return $rows;
    }

    private function storeSkippedRecords(array $skippedRecords, string $uploadedFileName)
    {
        $skippedRecordsFilePath = storage_path("app/uploads/Log_template_delivery.csv");

        $file = fopen($skippedRecordsFilePath, "w");

        // Write header to CSV
        fputcsv($file, ['Data', 'Error Message']);

        foreach ($skippedRecords as $record) {
            // Ensure $record['row_data'] is an array
            if (!is_array($record['row_data'])) {
                $record['row_data'] = [$record['row_data']];
            }

            // Write each skipped record to CSV
            fputcsv($file, array_merge($record['row_data'], [$record['error']]));
        }

        fclose($file);

        return $skippedRecordsFilePath;
    }
}
