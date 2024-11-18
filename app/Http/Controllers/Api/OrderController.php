<?php

namespace App\Http\Controllers\Api;

use URL;
use App\User;
use DateTime;
use stdClass;
use App\Model\Lob;
use App\Model\Van;
use Carbon\Carbon;
use App\Model\Item;
use App\Model\Order;
use App\Model\Route;
use App\Model\Region;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
use App\Model\ItemUom;
use App\Model\OCRLogs;
use App\Model\PDPItem;
use App\Model\Delivery;
use App\Model\OrderLog;
use App\Model\OrderType;
use App\Model\rfGenView;
use Carbon\CarbonPeriod;
use App\Model\CodeSetting;
use App\Model\CustomerLob;
use App\Model\OrderDetail;
use App\Model\PaymentTerm;
use App\Model\CustomerInfo;
use App\Model\DeliveryNote;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Imports\OrderImport;
use App\Model\ItemBasePrice;
use App\Model\ItemMainPrice;
use Illuminate\Http\Request;
use App\Model\DeliveryDetail;
use App\Model\DeliveryReport;
use App\Model\SalesmanUnload;
use App\Model\WorkFlowObject;
use Ixudra\Curl\Facades\Curl;
use App\Model\PDPDiscountSlab;
use App\Model\Storagelocation;
use RecursiveIteratorIterator;
use App\Model\OrganisationRole;
use App\Model\PDPPromotionItem;
use App\Model\PortfolioManagement;
use App\Model\PriceDiscoPromoPlan;
use App\Model\SalesmanLoadDetails;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Model\CustomerBasedPricing;
use App\Model\PickingSlipGenerator;
use App\Model\SalesmanUnloadDetail;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DeliveryReportExport;
use App\Model\DeliveryAssignTemplate;
use App\Model\PortfolioManagementItem;
use App\Model\CustomerWarehouseMapping;
use App\Model\WorkFlowRuleApprovalUser;
use App\Model\CustomerGroupBasedPricing;
use Illuminate\Support\Facades\Validator;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function order_for_report(Request $request)
    {
        // dd($request);
        // $date=$request->date;
        $data = Delivery::with(['salesmanInfo2', 'salesman', 'order'])->where('delivery_date', $request->date)->get();
        // dd($data);
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $delivery_assign = DeliveryAssignTemplate::with(['deliveryDriver', 'deliveryDriverInfo'])->where('delivery_id', $value->id)->first();
                $firstname = "";
                if (!empty($delivery_assign->deliveryDriver->firstname)) {
                    $firstname = $delivery_assign->deliveryDriver->firstname;
                }
                $lastname = "";
                if (!empty($delivery_assign->deliveryDriver->lastname)) {
                    $lastname = $delivery_assign->deliveryDriver->lastname;
                }

                DB::table('deliveries_report')->insert([
                    'order_id' => $value->order_id,
                    'region' => "",
                    'order_number' => $value->order->order_number,
                    'salesman_id' => !empty($delivery_assign->delivery_driver_id) ? $delivery_assign->delivery_driver_id : "",
                    'salesman_name' => $firstname . ' ' . $lastname,
                    'salesman_code' => !empty($delivery_assign->deliveryDriverInfo->salesman_code) ? $delivery_assign->deliveryDriverInfo->salesman_code : "",
                    'date' => $request->date,
                ]);
            }
            return prepareResult(true, $data, [], "Orders listing", $this->success);
        }
        return prepareResult(false, [], [], [], "this date not exist on Orders listing");

    }
    // public function live_tracking(Request $request)
    // {
    //     $start_date = $request->start_date;
    //     $end_date = $request->end_date;
    //     $request_salesman_id = $request->salesman_id;
    //     $request_branch_plant_code = $request->branch_plant_code;
        

    //     $data = array();

        
    //     $load_get = Delivery::with(['salesmanInfo', 'salesman', 'salesmanInfo2','storageocation']);

       

    //     if ($start_date != '' && $end_date != '') {
    //         if ($start_date == $end_date) {
    //             $load_get->whereDate('delivery_date', $end_date);
    //         } else {
    //             $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
    //             $load_get->whereBetween('delivery_date', [$start_date, $endDate]);
    //         }
    //     }

    //     if (count($request_branch_plant_code) > 0) {
    //         $load_get->whereHas('storageocation', function ($q) use ($request_branch_plant_code) {
    //             $q->whereIn('code', $request_branch_plant_code);
    //         });
    //     }

    //     if (count($request_salesman_id) > 0) {
    //         $load_get->whereIn('salesman_id', $request_salesman_id);
    //     }

    //     $data=$load_get->get();

    //         $orderCollection = new Collection();
    //     if (!empty($data)) {
    //         $item_cancel_count = 0;
    //         $delever_order_count = 0;
    //         $cancel_order_count = 0;
    //         $total_order = 0;
    //         $item_count = 0;
    //         $total_delivery = 0;
    //         foreach ($data as $key => $value) {
    //             $get_total_delivery = DeliveryAssignTemplate::where('delivery_id', $value->id)->where('delivery_driver_id', $value->salesman_id)->groupBy('delivery_id')->get();
    //             $total_order += count($get_total_delivery);
    //             $total_delivery = "";
    //             $firstname = "";
    //             if (!empty($value->salesman->firstname)) {
    //                 $firstname = $value->salesman->firstname;
    //             }
    //             $lastname = "";
    //             if (!empty($value->salesman->lastname)) {
    //                 $lastname = $value->salesman->lastname;
    //             }

    //             $delivery_notes_data = DeliveryNote::where('delivery_id', $value->id)->where('salesman_id', $value->salesman_id)->get();
    //             $delivery_notes = count($delivery_notes_data);

    //             foreach ($delivery_notes_data as $key => $valu) {
    //                 if ($valu->is_cancel == 1) {
    //                     $item_cancel_count++;
    //                 }
    //             }
    //             if (!empty($item_cancel_count)) {

    //                 if ($item_cancel_count == $delivery_notes) {
    //                     $cancel_order_count += 1;
    //                 }
    //             } else {
    //                 $delever_order_count += 1;
    //             }
    //             $pending = intval($total_order) - intval($delever_order_count) - intval($cancel_order_count);


    //             $orderCollection->push((object) [
    //                 'date' => $value->delivery_date,
    //                 'driver_name' => $firstname . ' ' . $lastname,
    //                 'order_number' => $value->delivery_number,
    //                 'driver_code' => !empty($value->salesmanInfo2->salesman_code) ? $value->salesmanInfo2->salesman_code : "",
    //                 'salesman_id' => $value->salesman_id,
    //                 'total_order' => $total_order,
    //                 'delivered_order' => $delever_order_count,
    //                 'cancel_order' => $cancel_order_count,
    //                 'pending_order' => $pending,
    //                 'branch_plant' => !empty($value->storageocation->code) ? $value->storageocation->code :"",
    //             ]);

    //         }
    //         if ($request->export == 0) {
    //             return prepareResult(true, $orderCollection, [], "Orders listing", $this->success);

    //         } else {
    
    //             $columns = array(
    //                 'Date',
    //                 'Driver Name',
    //                 'Order Number',
    //                 'Driver Code',
    //                 'Salesman Id',
    //                 'Total Order',
    //                 'Delivered Order',
    //                 'Cancel Order',
    //                 'Pending Order',
    //                 'Branch Plant'
    //             );
    
    //             $orderCollection = collect($orderCollection);
    //             // $file_name = "delivery_report_" . time() . "." . $request->export_type;
    //             Excel::store(new DeliveryReportExport($orderCollection, $columns), "delivery_report.csv");
    //             $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/delivery_report.csv'));
    //             return prepareResult(true, $result, [], "Data successfully exported", $this->success);
    
    //         }
    //     }
    //     return prepareResult(false, [], [], [], "this date not exist on Orders listing");

    // }

    public function deliveryReport(Request $request) {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => 'User not authenticate'], 'User not authenticate.', $this->unauthorized);
        }
    
        $input = $request->json()->all();
        $validates = $this->validations($input, 'delivery_report');
    
        if ($validates['error']) {
            return prepareResult(false, [], ['error' => $validates['errors']->first()], 'Error while validating', $this->unprocessableEntity);
        }
    
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $request_branch_plant_code = $request->branch_plant_code;
        $export = $request->export;
    
        $load_get = Delivery::with(['storageocation']);
    
        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $load_get->whereDate('delivery_date', $end_date);
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $load_get->whereBetween('delivery_date', [$start_date, $endDate]);
            }
        }
    
        if (count($request_branch_plant_code) > 0) {
            $load_get->whereHas('storageocation', function ($q) use ($request_branch_plant_code) {
                $q->whereIn('code', $request_branch_plant_code);
            });
        }

        if($request->salesman_id){
            $load_get->whereIn('salesman_id', $request->salesman_id);
        }
    
        $allLoad = $load_get->get();
        $salesmanIDs = $load_get->pluck('salesman_id')->toArray();
        // Remove null values and duplicates
        $salesmanIDs = array_filter($salesmanIDs, function ($id) {
            return !is_null($id);
        });
        $salesmanIDs = array_unique($salesmanIDs);

        // dd($salesmanIDs);

        //  // Fetch the deliveries based on filtered salesman IDs
        //     $allLoads = Delivery::with(['storageocation'])
        //     ->whereIn('salesman_id', $salesmanIDs)
        //     ->count();

        // // Continue with your processing using $allLoad
        // dd($allLoads);
    
        $data = [];
        if($request->type == 1){
            foreach ($allLoad as $value) {
                if($value->salesman_id != null || !empty($value->salesman_id) || $value->salesman_name != 0) {

                    $order_id = $value->order_id;
                    $salesman_id = $value->salesman_id;
                    // dd($salesman_id);
                    $delivery_id = $value->id;
                    $customer_id = $value->customer_id;
        
                    $userTableCustomer = User::where('id', $customer_id)->first();
                    $firstname = $userTableCustomer->firstname ?? ' ';
                    $lastname = $userTableCustomer->lastname ?? ' ';
                    $customer_name = $firstname .' '. $lastname;
                    $customerInfoTable = CustomerInfo::where('user_id', $customer_id)->first();
                    $customer_code = $customerInfoTable->customer_code;
            
                    $orderTable = Order::where('id', $order_id)->first();
                    $order_type_id = $orderTable->order_type_id;
                    $order_type = $order_type_id == 1 ? "Cash" : ($order_type_id == 2 ? "Credit" : ($order_type_id == 3 ? "Depot" : $orderTable->order_type_id));
        
                    $orderSalesmanLoad = SalesmanLoad::where('order_id', $order_id)->value('id');
                    $orderSalesmanLoadDeatils = SalesmanLoadDetails::where('salesman_load_id', $orderSalesmanLoad)->sum('load_qty');
        
                    $orderInvoice = Invoice::where('order_id', $order_id)->value('id');
                    $orderInvoiceDeatils = InvoiceDetail::where('invoice_id', $orderInvoice)->sum('item_qty');
                    
                    $salesmanInfoTable = SalesmanInfo::where('user_id', $salesman_id)->first();
                    $userTable = User::where('id', $salesman_id)->first();
                    $firstname = $userTable->firstname ?? ' ';
                    $lastname = $userTable->lastname ?? ' ';
                    $driver_name = $firstname .' '. $lastname;
                    $driver_code = $salesmanInfoTable->salesman_code ?? 0;
            
                    // $salesmanLoad = SalesmanLoad::where('delivery_id', $delivery_id)->count();
                    // $deliveryNoteTable = DeliveryNote::where('delivery_id', $delivery_id)->first();
                    // $item_id = $deliveryNoteTable->item_id ?? 0;
                    // $invoiced = $deliveryNoteTable->qty ?? '0';
        
                    // if(!empty($item_id) || !is_null($item_id) || $item_id != 0){
                    //     $unload_sum = SalesmanUnloadDetail::where('item_id', $item_id)->sum('unload_qty');
                    //     $load_sum = SalesmanLoadDetails::where('item_id', $item_id)->sum('load_qty');
                    //     $items = Item::select('id','item_name','item_code')->where("id", $item_id)->first();
                    //     $item_name = Item::where("id", $item_id)->value('item_name');
                    //     $item_code = Item::where("id", $item_id)->value('item_code');
                    // } else {
                    //     $unload_sum = 0;
                    //     $load_sum = 0;
                    //     $item_name = null;
                    //     $item_code = null;
                    // }            
            // dd($items);
                    $data[] = [
                        // 'delivery_date' => $value->delivery_date,
                        'order_number' => $orderTable->order_number,
                        'order_type' => $order_type,
                        'customer_code' => $customer_code,
                        'customer_name' => $customer_name,
                        'load_qty_sum' => $orderSalesmanLoadDeatils,
                        'invoiced' => $orderInvoiceDeatils,
                        // 'load_qty_sum' => $load_sum,
                        // 'load_count' => $salesmanLoad,
                        // 'invoiced' => $invoiced,
                        'unload_qty_sum' => ($orderSalesmanLoadDeatils - $orderInvoiceDeatils),
                        'driver_name' => $driver_name,
                        'driver_code' => $driver_code,
                        // 'item_id' => $item_id,
                        // 'item_name'=> $item_name,
                        // 'item_code'=> $item_code,
                    ];
                }
            }
        
            if ($export == 1) {
                $columns = array(
                    'Order Number',
                    'Order Type',
                    'Customer Code',
                    'Customer Name',
                    'Loaded',
                    'Invoiced',
                    'Unloaded',
                    'Driver Name',
                    'Driver Code',
                );
    
                $orderCollection = collect($data);
                // dd($orderCollection);
                Excel::store(new DeliveryReportExport($orderCollection, $columns), "deliveryreport.csv");
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/deliveryreport.csv'));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                return prepareResult(true, $data, [], 'Data retrieved successfully', $this->success);
            }
        }
        else {
            foreach ($salesmanIDs as $salesmanID) {
                // $order_id = $value->order_id;
                // $salesman_id = $value->salesman_id;
                // $delivery_id = $value->id;
                // $customer_id = $value->customer_id;
    
                // $userTableCustomer = User::where('id', $customer_id)->first();
                // $firstname = $userTableCustomer->firstname ?? ' ';
                // $lastname = $userTableCustomer->lastname ?? ' ';
                // $customer_name = $firstname .' '. $lastname;
                // $customerInfoTable = CustomerInfo::where('user_id', $customer_id)->first();
                // $customer_code = $customerInfoTable->customer_code;
        
                // $orderTable = Order::where('id', $order_id)->first();
                // $order_type_id = $orderTable->order_type_id;
                // $order_type = $order_type_id == 1 ? "Cash" : ($order_type_id == 2 ? "Credit" : ($order_type_id == 3 ? "Depot" : $orderTable->order_type_id));
    
                $orderSalesmanLoad = SalesmanLoad::where('salesman_id', $salesmanID)->whereBetween('load_date', [$start_date, $end_date])->pluck('id')->toArray();
                $orderSalesmanLoadDeatils = SalesmanLoadDetails::whereIn('salesman_load_id', $orderSalesmanLoad)->sum('load_qty');

                $orderSalesmanUnload = SalesmanUnload::where('salesman_id', $salesmanID)->whereBetween('transaction_date', [$start_date, $end_date])->pluck('id')->toArray();
                $orderSalesmanUnloadDeatils = SalesmanUnloadDetail::whereIn('salesman_unload_id', $orderSalesmanUnload)->sum('unload_qty');
    
                $orderInvoice = Invoice::where('salesman_id', $salesmanID)->whereBetween('invoice_date', [$start_date, $end_date])->pluck('id')->toArray();
                $orderInvoiceDeatils = InvoiceDetail::whereIn('invoice_id', $orderInvoice)->sum('item_qty');
                
                $salesmanInfoTable = SalesmanInfo::where('user_id', $salesmanID)->first();
                $userTable = User::where('id', $salesmanID)->first();
                $firstname = $userTable->firstname ?? ' ';
                $lastname = $userTable->lastname ?? ' ';
                $driver_name = $firstname .' '. $lastname;
                $driver_code = $salesmanInfoTable->salesman_code ?? 0;
        
                // $salesmanLoad = SalesmanLoad::where('delivery_id', $delivery_id)->count();
                // $deliveryNoteTable = DeliveryNote::where('delivery_id', $delivery_id)->first();
                // $item_id = $deliveryNoteTable->item_id ?? 0;
                // $invoiced = $deliveryNoteTable->qty ?? '0';
    
                // if(!empty($item_id) || !is_null($item_id) || $item_id != 0){
                //     $unload_sum = SalesmanUnloadDetail::where('item_id', $item_id)->sum('unload_qty');
                //     $load_sum = SalesmanLoadDetails::where('item_id', $item_id)->sum('load_qty');
                //     $items = Item::select('id','item_name','item_code')->where("id", $item_id)->first();
                //     $item_name = Item::where("id", $item_id)->value('item_name');
                //     $item_code = Item::where("id", $item_id)->value('item_code');
                // } else {
                //     $unload_sum = 0;
                //     $load_sum = 0;
                //     $item_name = null;
                //     $item_code = null;
                // }   
                
                $variance_qty = ($orderSalesmanLoadDeatils - $orderInvoiceDeatils);
                $difference = $variance_qty - $orderSalesmanUnloadDeatils;
        
                $data[] = [
                    'driver_name' => $driver_name,
                    'driver_code' => $driver_code,
                    'load_qty_sum' => number_format((float)$orderSalesmanLoadDeatils, 2, '.', ''),
                    'invoiced' => number_format((float)$orderInvoiceDeatils, 2, '.', ''),
                    'unload_qty_sum' => number_format((float)$orderSalesmanUnloadDeatils, 2, '.', ''),
                    'variance_qty' => number_format((float)$variance_qty, 2, '.', ''),
                    'difference' => number_format((float)$difference, 2, '.', '')
                ];
                
            }
            if ($export == 1) {
                $columns = array(
                    // 'Order Number',
                    // 'Order Type',
                    // 'Customer Code',
                    // 'Customer Name',
                    'Driver Name',
                    'Driver Code',
                    'Loaded',
                    'Invoiced',
                    'Unloaded',
                    'Variance Qty',
                    'Difference',
                );
    
                $orderCollection = collect($data);
                // dd($orderCollection);
                Excel::store(new DeliveryReportExport($orderCollection, $columns), "deliveryreport.csv");
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/deliveryreport.csv'));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                return prepareResult(true, $data, [], 'Data retrieved successfully', $this->success);
            }
        }
    }
    public function delivery_report(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => 'User not authenticate'], 'User not authenticate.', $this->unauthorized);
        }

        $input = $request->json()->all();
        $validates = $this->validations($input, 'dashboard');
        if ($validates['error']) {
            return prepareResult(false, [], ['error' => $validates['errors']->first()], 'Error while validating', $this->unprocessableEntity);
        }

        if (!isset($request->salesman_id) && !is_array($request->salesman_id)) {
            return prepareResult(false, [], ['error' => "The salesman_id field is required."], 'Error while validating', $this->unprocessableEntity);
        }

        if (!isset($request->branch_plant_code) && !is_array($request->branch_plant_code)) {
            return prepareResult(false, [], ['error' => "The branch_plant_code field is required."], 'Error while validating', $this->unprocessableEntity);
        }
       
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $request_salesman_id = $request->salesman_id;
        $request_branch_plant_code = $request->branch_plant_code;
        

        $data = array();

        
        $load_get = Delivery::with(['salesmanInfo', 'salesman', 'salesmanInfo2','storageocation']);

       

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $load_get->whereDate('delivery_date', $end_date);
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $load_get->whereBetween('delivery_date', [$start_date, $endDate]);
            }
        }

        if (count($request_branch_plant_code) > 0) {
            $load_get->whereHas('storageocation', function ($q) use ($request_branch_plant_code) {
                $q->whereIn('code', $request_branch_plant_code);
            });
        }

        if (count($request_salesman_id) > 0) {
            $load_get->whereIn('salesman_id', $request_salesman_id);
        }
        $allLoad = $load_get->get();
        // echo $allLoad;
        // die;
        // dd($allLoad);
        $request_branch_plant_code_wise_array = array();
        $i = 0;
    
        foreach ($allLoad as $key => $load_value) {
            $load_region_id = !empty($load_value->storageocation->code)?$load_value->storageocation->code:"" ;
            // echo $load_region_id."-";
            if (!in_array($load_region_id, array_column($request_branch_plant_code_wise_array, 'code'))) {
                
                $request_branch_plant_code_wise_array[$i]['code'] = $load_region_id;

                $i++;
            } else {
                $key_get = array_search($load_region_id, array_column($request_branch_plant_code_wise_array, 'code'), true);
                
                
            }

        }
        // die;
        // print_r($load_region_id);
        // exit();
        $data['volume_loaded'] = $request_branch_plant_code_wise_array;
        
        $all_branch_plant_code_get_load = array_column($request_branch_plant_code_wise_array, 'code');
        // //End Load Qty
        // return response()->json( $all_branch_plant_code_get_load);
        
        // //delivered cancel
       
        
        // //end delivered cancel
        $tot_delivery = Delivery::with(['salesmanInfo', 'salesman', 'salesmanInfo2','storageocation']);

        
        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $tot_delivery->whereDate('delivery_date', $end_date);
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $tot_delivery->whereBetween('delivery_date', [$start_date, $endDate]);
            }
        }

        // if (count($request_branch_plant_code) > 0) {

        $tot_delivery->whereHas('storageocation', function ($q) use ($all_branch_plant_code_get_load) {
            $q->whereIn('code', $all_branch_plant_code_get_load);
        });
        // }

        if (count($request_salesman_id) > 0) {
            $tot_delivery->whereIn('salesman_id', $request_salesman_id);
        }
        $tot_delivery_data = $tot_delivery->get();
        $tot_completed_delivery_data = $tot_delivery->where('approval_status', 'Completed')->count();
        $tot_cancelled_delivery_data = $tot_delivery->where('approval_status', 'Cancel')->count();
        $tot_pending_delivery_data = $tot_completed_delivery_data - $tot_cancelled_delivery_data;
        $tot_delivery_data_count = $tot_delivery_data->count();

        // return response()->json($tot_pending_delivery_data);

        $total_branch_plant_code_wise_array = array();
        $i = 0;
        $total_order = 0;
        $item_cancel_count = 0;
        $cancel_order_count = 0;
        $delever_order_count = 0;
        foreach ($tot_delivery_data as $key => $delivery_value) {
            $get_total_delivery = DeliveryAssignTemplate::where('delivery_id', $delivery_value->id)->where('delivery_driver_id', $delivery_value->salesman_id)->groupBy('delivery_id')->get();
            $total_order += count($get_total_delivery);
    
            $total_delivery = "";
                $firstname = "";
                if (!empty($delivery_value->salesman->firstname)) {
                    $firstname = $delivery_value->salesman->firstname;
                }
                $lastname = "";
                if (!empty($delivery_value->salesman->lastname)) {
                    $lastname = $delivery_value->salesman->lastname;
                }

                $delivery_notes_data = DeliveryNote::where('delivery_id', $delivery_value->id)->where('salesman_id', $delivery_value->salesman_id)->get();
                $delivery_notes = count($delivery_notes_data);

                foreach ($delivery_notes_data as $key => $valu) {
                    if ($valu->is_cancel == 1) {
                        $item_cancel_count++;
                    }
                }
                if (!empty($item_cancel_count)) {

                    if ($item_cancel_count == $delivery_notes) {
                        $cancel_order_count += 1;
                    }
                } else {
                    $delever_order_count += 1;
                }       
            if ($delivery_value->storageocation) {
                $load_region_id = $delivery_value->storageocation->code;
                // echo $load_region_id;

                if (!in_array($load_region_id, array_column($total_branch_plant_code_wise_array, 'branch_plant_code'))) {
                    
                    $total_branch_plant_code_wise_array[$i]['date'] = $delivery_value->delivery_date;
                    $total_branch_plant_code_wise_array[$i]['driver_name'] = $firstname . ' ' . $lastname;
                    $total_branch_plant_code_wise_array[$i]['order_number'] = !empty($delivery_value->delivery_number) ? $delivery_value->delivery_number:"";
                    $total_branch_plant_code_wise_array[$i]['driver_code'] = !empty($delivery_value->salesmanInfo2->salesman_code) ? $delivery_value->salesmanInfo2->salesman_code:"";
                    $total_branch_plant_code_wise_array[$i]['salesman_id'] = !empty($delivery_value->salesman_id) ? $delivery_value->salesman_id:"";
                    $total_branch_plant_code_wise_array[$i]['total_order'] = $total_order;
                    $total_branch_plant_code_wise_array[$i]['delivered_order'] = $delever_order_count;
                    $total_branch_plant_code_wise_array[$i]['cancel_order'] = $cancel_order_count;
                    $total_branch_plant_code_wise_array[$i]['pending_order'] = $item_cancel_count;
                    $total_branch_plant_code_wise_array[$i]['branch_plant_code'] = !empty($delivery_value->storageocation->code) ? $delivery_value->storageocation->code:"";
                    $i++;
                } else {
                    $key_get = array_search($load_region_id, array_column($total_branch_plant_code_wise_array, 'branch_plant_code'), true);
                }

            }
        }

        if (!in_array($all_branch_plant_code_get_load, array_column($total_branch_plant_code_wise_array, 'branch_plant_code'))) {
            $check_other = array_diff($all_branch_plant_code_get_load, array_column($total_branch_plant_code_wise_array, 'branch_plant_code'));
            $new_other_region = array();
            foreach ($check_other as $key2 => $other_region) {
                $region = Storagelocation::where('code', $other_region)->first();
                $new_array = array(
                    'branch_plant_code' => $other_region,
                    'total_order' => 0,
                );
                array_push($total_branch_plant_code_wise_array, $new_array);
            }

        }
        $data['total_order'] = $total_branch_plant_code_wise_array;
        // //Pending
        $pending_branch_plant_code_wise_array = array();
        

        foreach ($request_branch_plant_code_wise_array as $fkey => $value) {
            // if()
            // $total_branch_plant_code_wise_array[$fkey]['total_order'] - $total_branch_plant_code_wise_array[$fkey]['cancel_order']-$total_branch_plant_code_wise_array[$fkey]['delivered_order'];
            $pending_branch_plant_code_wise_array[$fkey]['driver_name'] = !empty($total_branch_plant_code_wise_array[$fkey]['driver_name']) ? $total_branch_plant_code_wise_array[$fkey]['driver_name'] : "";
            $pending_branch_plant_code_wise_array[$fkey]['order_number'] = !empty($total_branch_plant_code_wise_array[$fkey]['order_number']) ? $total_branch_plant_code_wise_array[$fkey]['order_number'] : "";
            $pending_branch_plant_code_wise_array[$fkey]['driver_code'] = !empty($total_branch_plant_code_wise_array[$fkey]['driver_code']) ? $total_branch_plant_code_wise_array[$fkey]['driver_code'] : "";
            $pending_branch_plant_code_wise_array[$fkey]['salesman_id'] = !empty($total_branch_plant_code_wise_array[$fkey]['salesman_id']) ? $total_branch_plant_code_wise_array[$fkey]['salesman_id'] : "";
            $pending_branch_plant_code_wise_array[$fkey]['total_order'] = !empty($total_branch_plant_code_wise_array[$fkey]['total_order']) ? $total_branch_plant_code_wise_array[$fkey]['total_order'] :0;

            $pending_branch_plant_code_wise_array[$fkey]['delivered_order'] = !empty($total_branch_plant_code_wise_array[$fkey]['delivered_order']) ? $total_branch_plant_code_wise_array[$fkey]['delivered_order'] :0;

            $pending_branch_plant_code_wise_array[$fkey]['cancel_order'] = !empty($total_branch_plant_code_wise_array[$fkey]['cancel_order']) ? $total_branch_plant_code_wise_array[$fkey]['cancel_order'] : 0;
            // $pending_branch_plant_code_wise_array[$fkey]['pending_order'] = 0;
            // Calculate pending order
            $pending_branch_plant_code_wise_array[$fkey]['pending_order'] = $pending_branch_plant_code_wise_array[$fkey]['total_order'] - 
            $pending_branch_plant_code_wise_array[$fkey]['delivered_order'] - 
            $pending_branch_plant_code_wise_array[$fkey]['cancel_order'];

            // Accumulate totals
            // $total_delivered_count += $pending_branch_plant_code_wise_array[$fkey]['delivered_order'];
            // $total_cancel_count += $pending_branch_plant_code_wise_array[$fkey]['cancel_order'];
            // $total_pending_count += $pending_branch_plant_code_wise_array[$fkey]['pending_order'];

            $pending_branch_plant_code_wise_array[$fkey]['branch_plant_code'] = !empty($total_branch_plant_code_wise_array[$fkey]['branch_plant_code']) ? $total_branch_plant_code_wise_array[$fkey]['branch_plant_code'] : "";
            // round(($request_branch_plant_code_wise_array[$fkey]['volume_loaded_qty'] - $invoice_region_wise_array[$fkey]['delivered_qty']) - $cancel_region_wise_array[$fkey]['cancel_qty'], 2);
        }

        $newArray = array();
        foreach ($pending_branch_plant_code_wise_array as $item) {
            $newArray[$item['branch_plant_code']] = $item;
        }
        $data['pending'] = $newArray;     

        $salesman_wise_array = array();
        $s_key = 0;
        $total_order = 0;
        $delever_order_count = 0;
        $cancel_order_count = 0;
        $item_cancel_count = 0;

        $salesman_wise_array = [];

        // Loop through each item in the allLoad collection.
        foreach ($allLoad as $salesman_load_value) {
            // Find an existing entry by salesman_id and possibly other criteria
            if (empty($salesman_load_value->salesman->firstname) || empty($salesman_load_value->salesmanInfo2->salesman_code)) {
                continue; // Skip this iteration if critical information is missing
            }
            $existingIndex = null;
            foreach ($salesman_wise_array as $index => $entry) {
                if ($entry['salesman_id'] == $salesman_load_value->salesman_id && $entry['date'] == $salesman_load_value->delivery_date) {
                    $existingIndex = $index;
                    break;
                }
            }
        
            if (is_null($existingIndex)) {
                // Initialize a new entry if not found
                $salesman_wise_array[] = [
                    'date' => $salesman_load_value->delivery_date,
                    'driver_name' => optional($salesman_load_value->salesman)->firstname . ' ' . optional($salesman_load_value->salesman)->lastname,
                    'order_number' => $salesman_load_value->delivery_number,
                    'driver_code' => $salesman_load_value->salesmanInfo2->salesman_code ?? "",
                    'salesman_id' => $salesman_load_value->salesman_id,
                    'total_order' => 0,
                    'delivered_order' => 0,
                    'cancel_order' => 0,
                    'pending_order' => 0,
                    'region_code' => $salesman_load_value->storageocation->code
                ];
                $existingIndex = count($salesman_wise_array) - 1;  // Get the index of the newly added item
            }
        
            // Update counts
            $salesman_wise_array[$existingIndex]['total_order']++;
            if ($salesman_load_value->approval_status === 'Completed') {
                $salesman_wise_array[$existingIndex]['delivered_order']++;
            } elseif ($salesman_load_value->approval_status === 'Cancel') {
                $salesman_wise_array[$existingIndex]['cancel_order']++;
            }
        }
        
        // Update pending orders
        foreach ($salesman_wise_array as $index => $data) {
            $salesman_wise_array[$index]['pending_order'] = $data['total_order'] - $data['delivered_order'] - $data['cancel_order'];
        }

        $pai_chart = [
            "delivered_per" => 0,
            "cancel_per" => 0,
            "pending_per" => 0,
            // 'total_order' => 0
        ];
        
        // Iterate over the salesman_wise_array to calculate counts
        foreach ($salesman_wise_array as $entry) {
            $pai_chart['delivered_per'] += $entry['delivered_order'];
            $pai_chart['cancel_per'] += $entry['cancel_order'];
            // $pai_chart['total_order'] += $entry['total_order'];
            // Calculate pending orders by subtracting delivered and canceled from total orders
            $pai_chart['pending_per'] += $entry['total_order'] - $entry['delivered_order'] - $entry['cancel_order'];
        }

        // return response()->json($salesmanAggregates);
        // foreach ($allLoad as $key => $salesman_load_value) {
        //     $salesman_id = $salesman_load_value->salesman_id;
        
        //     if (!in_array($salesman_id, array_column($salesman_wise_array, 'salesman_id'))) {
        //         // dd($salesman_load_value->id);
        //         $get_total_delivery = DeliveryAssignTemplate::where('delivery_id', $salesman_load_value->id)->where('delivery_driver_id', $salesman_load_value->salesman_id)->groupBy('delivery_id')->get();
        //         // return response()->json($get_total_delivery);
        //         $total_order += count($get_total_delivery);
        //         $total_delivery = "";
        //         $firstname = "";

        //         if (!empty($salesman_load_value->salesman->firstname)) {
        //             $firstname = $salesman_load_value->salesman->firstname;
        //         }

        //         $lastname = "";

        //         if (!empty($salesman_load_value->salesman->lastname)) {
        //             $lastname = $salesman_load_value->salesman->lastname;
        //         }

        //         $delivery_notes_data = DeliveryNote::where('delivery_id', $salesman_load_value->id)->where('salesman_id', $salesman_load_value->salesman_id)->get();
        //         $delivery_notes = count($delivery_notes_data);

        //         foreach ($delivery_notes_data as $key => $valu) {
        //             if ($valu->is_cancel == 1) {
        //                 $item_cancel_count++;
        //             }
        //         }

        //         if (!empty($item_cancel_count)) {

        //             if ($item_cancel_count == $delivery_notes) {
        //                 $cancel_order_count += 1;
        //             }
        //         } else {
        //             $delever_order_count += 1;
        //         }
                
        //         $pending = intval($total_order) - intval($delever_order_count) - intval($cancel_order_count);
        //         // $pending = $total_order - $delever_order_count - $cancel_order_count;
        //         $salesman_wise_array[$s_key]['date'] = $salesman_load_value->delivery_date;
        //         $salesman_wise_array[$s_key]['driver_name'] = $firstname . ' ' . $lastname;
        //         $salesman_wise_array[$s_key]['order_number'] = $salesman_load_value->delivery_number;
        //         $salesman_wise_array[$s_key]['driver_code'] = !empty($salesman_load_value->salesmanInfo2->salesman_code) ? $salesman_load_value->salesmanInfo2->salesman_code:"";
        //         $salesman_wise_array[$s_key]['salesman_id'] = $salesman_load_value->salesman_id;
        //         $salesman_wise_array[$s_key]['total_order'] = $total_order;
        //         $salesman_wise_array[$s_key]['delivered_order'] = $delever_order_count;
        //         $salesman_wise_array[$s_key]['cancel_order'] = $cancel_order_count;
        //         $salesman_wise_array[$s_key]['pending_order'] = $pending;
        //         $salesman_wise_array[$s_key]['branch_plant_code'] = $salesman_load_value->storageocation->code;
        //         // $salesman_wise_array[$s_key]['region_code'] = $salesman_load_value->salesman_infos->region->region_code;
                
        //         $s_key++;
        //     } else {
        //         $key_get = array_search($salesman_id, array_column($salesman_wise_array, 'salesman_id'), true);
        //     }
        // }

        // foreach ($salesman_wise_array as $f_key => $s_value) {
        //     $region_ids = $s_value['branch_plant_code'];
        //     $salesman_customer_count = Delivery::where('salesman_id', $s_value['salesman_id'])->count();

        //     // $salesman_wise_array[$f_key]['customer_count'] = $salesman_customer_count;
            
        // }
        // dd($newArray);
        $final_array = array();
        $final_array['table_date'] = $salesman_wise_array;
        $final_array['chart_2'] = $newArray;
        $final_array['pai_chart'] = $pai_chart;
        if ($request->export == 0) {
            return prepareResult(true, $final_array, [], 'Record found', $this->success);

        } else {
    
                $columns = array(
                    'Date',
                    'Driver Name',
                    'Order Number',
                    'Driver Code',
                    'Salesman Id',
                    'Total Order',
                    'Delivered Order',
                    'Cancel Order',
                    'Pending Order',
                    'Branch Plant'
                );
    
                $orderCollection = collect($final_array['table_date']);
                // $file_name = "delivery_report_" . time() . "." . $request->export_type;
                Excel::store(new DeliveryReportExport($orderCollection, $columns), "delivery_report.csv");
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/delivery_report.csv'));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
    
            }
        return prepareResult(false, [], [], [], "this date not exist on Orders listing");
        // return prepareResult(true, $final_array, [], 'Record found', $this->success);
        
    }
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $orders_query = Order::with(
            array(
                'customer' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
                }
            )
        )
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code,customer_address_1,customer_address_2,customer_address_3',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name,type,code',
                'orderDetails',
                'orderDetails.reason:id,name,type,code',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.item.itemMainPrice:id,item_id,item_uom_id,item_price,item_uom_id',
                'orderDetails.item.itemMainPrice.itemUom:id,name,code',
                'orderDetails.item.itemUomLowerUnit:id,name,code',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.itemMainPrice.itemUom:id,name,code',
                'depot:id,depot_name',
                'lob:id,user_id,name,lob_code',
                'storageocation:id,code,name',
                'pickingSlipGenerator:id,order_id,picking_slip_generator_id,date,time,date_time',
                'invoice:id,invoice_number'
            );
        // ->where('order_date', date('Y-m-d'));

        if ($request->date) {
            $orders_query->whereDate('created_at', $request->date);
        }

        if ($request->order_number) {
            $orders_query->where('order_number', 'like', '%' . $request->order_number . '%');
        }

        if ($request->delivery_date) {
            $orders_query->where('delivery_date', $request->delivery_date);
        }

        if ($request->due_date) {
            $orders_query->where('due_date', date('Y-m-d', strtotime($request->due_date)));
        }

        if ($request->branch_plant_code) {
            $branch_plant_code = $request->branch_plant_code;
            $orders_query->whereHas('storageocation', function ($q) use ($branch_plant_code) {
                $q->where('code', 'like', '%' . $branch_plant_code . '%');
            });
        }

        if ($request->current_stage) {
            $orders_query->where('current_stage', 'like', '%' . $request->current_stage . '%');
        }

        if ($request->approval_status) {
            $orders_query->where('approval_status', 'like', '%' . $request->approval_status . '%');
        }

        if ($request->customer_lpo) {
            $orders_query->where('customer_lop', 'like', '%' . $request->customer_lpo . '%');
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

        if ($request->invoice_number) {
            $invoice_number = $request->invoice_number;
            $orders_query->whereHas('invoice', function ($q) use ($invoice_number) {
                $q->where('invoice_number', 'like', '%' . $invoice_number . '%');
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

        if ($request->item_id) {
            $item_id = $request->item_id;
            $orders_query->whereHas('orderDetails', function ($q) use ($item_id) {
                $q->where('item_id', $item_id);
            });
        }

        if ($request->storage_location_id) {
            $orders_query->where('storage_location_id', $request->storage_location_id);
        }

        if ($request->user_created) {
            $orders_query->where('order_created_user_id', $request->user_created);
        }

        if (config('app.current_domain') == "presales") {
            $user_branch_plant = $request->user()->userBranchPlantAssing;
            if (count($user_branch_plant)) {
                $storage_id = $user_branch_plant->pluck('storage_location_id')->toArray();
                $orders_query->whereIn('storage_location_id', $storage_id);
            }
        }

        if (config('app.current_domain') == "presales") {
            $orders_query->where('is_presale_order', 1);
        } else {
            $orders_query->where('is_presale_order', '<', 1);
        }

        if (config('app.current_domain') == "presales" && ($request->user()->role_id != 11 && $request->user()->usertype != 1)) {
            $orders_query->where('current_stage', 'Approved');
        }

        // $orders = $orders_query->orderBy('id', 'desc')
        //     ->get();

        $all_orders = $orders_query->orderBy('id', 'desc')
            ->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $orders = $all_orders->items();

        $pagination = array();
        $pagination['total_pages'] = $all_orders->lastPage();
        $pagination['current_page'] = (int) $all_orders->perPage();
        $pagination['total_records'] = $all_orders->total();

        // this chck for all user exclude sc user and admin user
        if (config('app.current_domain') == "presales" && ($request->user()->role_id != 11 && $request->user()->usertype != 1)) {
            return prepareResult(true, $orders, [], "Todays Orders listing", $this->success, $pagination);
        }

        // approval

        $results = GetWorkFlowRuleObject('Order', $all_orders->pluck('id')->toArray());
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
        if (count($orders)) {
            foreach ($orders as $key => $order1) {
                if (in_array($orders[$key]->id, $approve_need_order)) {
                    if ($orders[$key]->current_stage == 'Cancelled') {
                        $orders[$key]->need_to_approve = 'no';
                        $orders[$key]->objectid = '';
                    } else {
                        $orders[$key]->need_to_approve = 'yes';
                        if (isset($approve_need_order_object_id[$orders[$key]->id])) {
                            $orders[$key]->objectid = $approve_need_order_object_id[$orders[$key]->id];
                        } else {
                            $orders[$key]->objectid = '';
                        }
                    }
                } else {
                    $orders[$key]->need_to_approve = 'no';
                    $orders[$key]->objectid = '';
                }

                if ($orders[$key]->current_stage == 'Approved' || $orders[$key]->current_stage == 'Cancelled' || request()->user()->usertype == 1 || in_array($orders[$key]->id, $approve_need_order)) {
                    $orders_array[] = $orders[$key];
                }
            }
        }

        return prepareResult(true, $orders_array, [], "Todays Orders listing", $this->success, $pagination);

        $orders = $orders_query->orderBy('id', 'desc')
            ->get();

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
        if (is_object($orders)) {
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

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($orders_array[$offset])) {
                    $data_array[] = $orders_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($orders_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($orders_array);
        } else {
            $data_array = $orders_array;
        }
        return prepareResult(true, $data_array, [], "Todays Orders listing", $this->success, $pagination);
    }

    public function orderList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $orders_query = Order::with(
            array(
                'customer' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
                }
            )
        )
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code,customer_address_1,customer_address_2,customer_address_3',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name,type,code',
                'orderDetails',
                'orderDetails.reason:id,name,type,code',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.item.itemMainPrice:id,item_id,item_uom_id,item_price,item_uom_id',
                'orderDetails.item.itemMainPrice.itemUom:id,name,code',
                'orderDetails.item.itemUomLowerUnit:id,name,code',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.itemMainPrice.itemUom:id,name,code',
                'depot:id,depot_name',
                'lob:id,user_id,name,lob_code',
                'storageocation:id,code,name',
                'pickingSlipGenerator:id,order_id,picking_slip_generator_id,date,time,date_time',
                'invoice:id,invoice_number'
            );
        // ->where('order_date', date('Y-m-d'));

        if ($request->date) {
            $orders_query->whereDate('created_at', $request->date);
        }

        if ($request->order_number) {
            $orders_query->where('order_number', 'like', '%' . $request->order_number . '%');
        }

        if ($request->delivery_date) {
            $orders_query->where('delivery_date', $request->delivery_date);
        }

        if ($request->due_date) {
            $orders_query->where('due_date', date('Y-m-d', strtotime($request->due_date)));
        }

        if ($request->branch_plant_code) {
            $branch_plant_code = $request->branch_plant_code;
            $orders_query->whereHas('storageocation', function ($q) use ($branch_plant_code) {
                $q->where('code', 'like', '%' . $branch_plant_code . '%');
            });
        }

        if ($request->current_stage) {
            $orders_query->where('current_stage', 'like', '%' . $request->current_stage . '%');
        }

        if ($request->approval_status) {
            $orders_query->where('approval_status', 'like', '%' . $request->approval_status . '%');
        }

        if ($request->customer_lpo) {
            $orders_query->where('customer_lop', 'like', '%' . $request->customer_lpo . '%');
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

        if ($request->invoice_number) {
            $invoice_number = $request->invoice_number;
            $orders_query->whereHas('invoice', function ($q) use ($invoice_number) {
                $q->where('invoice_number', 'like', '%' . $invoice_number . '%');
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

        if ($request->item_id) {
            $item_id = $request->item_id;
            $orders_query->whereHas('orderDetails', function ($q) use ($item_id) {
                $q->where('item_id', $item_id);
            });
        }

        if ($request->storage_location_id) {
            $orders_query->where('storage_location_id', $request->storage_location_id);
        }

        if ($request->user_created) {
            $orders_query->where('order_created_user_id', $request->user_created);
        }

        if (config('app.current_domain') == "presales") {
            $user_branch_plant = $request->user()->userBranchPlantAssing;
            if (count($user_branch_plant)) {
                $storage_id = $user_branch_plant->pluck('storage_location_id')->toArray();
                $orders_query->whereIn('storage_location_id', $storage_id);
            }
        }

        if (config('app.current_domain') == "presales") {
            $orders_query->where('is_presale_order', 1);
        } else {
            $orders_query->where('is_presale_order', '<', 1);
        }

        if (config('app.current_domain') == "presales" && ($request->user()->role_id != 11 && $request->user()->usertype != 1)) {
            $orders_query->where('current_stage', 'Approved');
        }

        // $orders = $orders_query->orderBy('id', 'desc')
        //     ->get();

        $all_orders = $orders_query->orderBy('id', 'desc')
            ->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $orders = $all_orders->items();

        /*  $pagination = array();
         $pagination['total_pages'] = $all_orders->lastPage();
         $pagination['current_page'] = (int) $all_orders->perPage();
         $pagination['total_records'] = $all_orders->total(); */

        // this chck for all user exclude sc user and admin user
        if (config('app.current_domain') == "presales" && ($request->user()->role_id != 11 && $request->user()->usertype != 1)) {
            return prepareResult(true, $orders, [], "Todays Orders listing", $this->success, []);
        }

        // approval

        $results = GetWorkFlowRuleObject('Order', $all_orders->pluck('id')->toArray());
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
        if (count($orders)) {
            foreach ($orders as $key => $order1) {
                if (in_array($orders[$key]->id, $approve_need_order)) {
                    if ($orders[$key]->current_stage == 'Cancelled') {
                        $orders[$key]->need_to_approve = 'no';
                        $orders[$key]->objectid = '';
                    } else {
                        $orders[$key]->need_to_approve = 'yes';
                        if (isset($approve_need_order_object_id[$orders[$key]->id])) {
                            $orders[$key]->objectid = $approve_need_order_object_id[$orders[$key]->id];
                        } else {
                            $orders[$key]->objectid = '';
                        }
                    }
                } else {
                    $orders[$key]->need_to_approve = 'no';
                    $orders[$key]->objectid = '';
                }

                if ($orders[$key]->current_stage == 'Approved' || $orders[$key]->current_stage == 'Cancelled' || request()->user()->usertype == 1 || in_array($orders[$key]->id, $approve_need_order)) {
                    $orders_array[] = $orders[$key];
                }
            }
        }

        return prepareResult(true, $orders, [], "Todays Orders listing", $this->success, []);

        $orders = $orders_query->orderBy('id', 'desc')
            ->get();

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
        if (is_object($orders)) {
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

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($orders_array[$offset])) {
                    $data_array[] = $orders_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($orders_array) / $limit);
            $pagination['current_page'] = (int) $page;
            $pagination['total_records'] = count($orders_array);
        } else {
            $data_array = $orders_array;
        }
        return prepareResult(true, $orders, [], "Todays Orders listing", $this->success, $pagination);
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

       // dd($request->customer_id)
        if (!empty($request->customer_id)) {
            $custInfo = CustomerInfo::where('user_id', $request->customer_id)->where("status",'1')->first();
            if (!is_object($custInfo)) {
                //return prepareResult(false, [], 'Customer is inactive.', "Customer is inactive.You can not book the order.", $this->internal_server_error);
                return prepareResult(false, [], ["error" => "Customer is inactive."], "Customer is inactive.You can not book the order.", $this->unprocessableEntity);
                   
            }
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } elseif (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        if ($request->delivery_date < now()->format('Y-m-d')) {
            return prepareResult(false, [], [], "Delivery date passed away.", $this->unprocessableEntity);
        }

        if (config('app.current_domain') !== "merchandising" && empty($request->storage_location_id)) {
            return prepareResult(false, [], ['error' => "Customer branch plant required"], "Customer branch plant required.", $this->unprocessableEntity);
        }


        \DB::beginTransaction();
        try {

            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Order',$request);
            }

            $order = new Order;

            if ($request->source == 1) {
                $repeat_number = codeCheck('Order', 'order_number', $request->order_number, 'order_date');
                if (is_object($repeat_number)) {
                    return prepareResult(true, $repeat_number, [], 'The Order Number already exists.', $this->success);
                } else {
                    $repeat_number = codeCheck('Order', 'order_number', $request->order_number);
                    if (is_object($repeat_number)) {
                        return prepareResult(false, [], ["error" => "This Order Number " . $request->order_number . " is already added."], "This Order Number is already added.", $this->unprocessableEntity);
                    }
                }

                $order->order_number = $request->order_number;
            } else {
                $order->order_number = nextComingNumber('App\Model\Order', 'order', 'order_number', $request->order_number);
            }

            $order->customer_id = (!empty($request->customer_id)) ? $request->customer_id : null;
            $order->depot_id = (!empty($request->depot_id)) ? $request->depot_id : null;
            $order->order_type_id = $request->order_type_id;
            $order->order_date = date('Y-m-d');
            $order->delivery_date = $request->delivery_date;
            $order->salesman_id = $request->salesman_id;
            $order->route_id = (!empty($route_id)) ? $route_id : null;
            $order->reason_id = (!empty($request->reason_id)) ? $request->reason_id : null;
            $order->customer_lop = (!empty($request->customer_lop)) ? $request->customer_lop : null;
            $order->payment_term_id = $request->payment_term_id;
            $order->due_date = $request->due_date;
            $order->total_qty = 0;
            $order->total_gross = $request->total_gross;
            $order->total_discount_amount = $request->total_discount_amount;
            $order->total_net = $request->total_net;
            $order->total_vat = $request->total_vat;
            $order->total_excise = $request->total_excise;
            $order->grand_total = $request->grand_total;
            $order->any_comment = $request->any_comment;
            $order->source = $request->source;
            $order->status = $status;
            $order->current_stage = $current_stage;
            if (config('app.current_domain') === "merchandising" && $request->source == 1) {
                $order->status = 1;
                $order->current_stage = "Approved";
            }
            $order->current_stage_comment = $request->current_stage_comment;
            $order->approval_status = "Created";
            $order->warehouse_id = getWarehuseBasedOnStorageLoacation($request->storage_location_id, false);
            $order->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            $order->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $order->order_created_user_id = $request->user()->id;
            $order->is_user_updated = 0;
            $order->user_updated = null;
            if (config('app.current_domain') == "presales") {
                $order->is_presale_order = 1;
            } else {
                $order->is_presale_order = 0;
            }
            $order->save();

            $data = [
                'created_user' => $request->user()->id,
                'order_id' => $order->id,
                'delviery_id' => NULL,
                'updated_user' => NULL,
                'previous_request_body' => NULL,
                'request_body' => $order,
                'action' => 'Order',
                'status' => 'Created',
            ];

            saveOrderDeliveryLog($data);

            $t_qty = 0;

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    $orderDetail = new OrderDetail;
                    $orderDetail->order_id = $order->id;
                    $orderDetail->item_id = $item['item_id'];
                    $orderDetail->item_uom_id = $item['item_uom_id'];
                    $orderDetail->original_item_uom_id = $item['item_uom_id'];
                    $orderDetail->discount_id = (!empty($item['discount_id'])) ? $item['discount_id'] : null;
                    $orderDetail->is_free = (!empty($item['is_free'])) ? $item['is_free'] : 0;
                    $orderDetail->is_item_poi = (!empty($item['is_item_poi'])) ? $item['is_item_poi'] : 0;
                    $orderDetail->promotion_id = (!empty($item['promotion_id'])) ? $item['promotion_id'] : 0;
                    $orderDetail->reason_id = (!empty($item['reason_id'])) ? $item['reason_id'] : null;
                    $orderDetail->is_deleted = (!empty($item['is_deleted'])) ? $item['is_deleted'] : 0;
                    $orderDetail->item_qty = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->item_weight = (!empty($item['item_weight'])) ? $item['item_weight'] : 0;
                    $orderDetail->item_price = (!empty($item['item_price'])) ? $item['item_price'] : 0;
                    $orderDetail->item_gross = (!empty($item['item_gross'])) ? $item['item_gross'] : 0;
                    $orderDetail->item_discount_amount = (!empty($item['item_discount_amount'])) ? $item['item_discount_amount'] : 0;
                    $orderDetail->item_net = (!empty($item['item_net'])) ? $item['item_net'] : 0;
                    $orderDetail->item_vat = (!empty($item['item_vat'])) ? $item['item_vat'] : 0;
                    $orderDetail->item_excise = (!empty($item['item_excise'])) ? $item['item_excise'] : 0;
                    $orderDetail->item_grand_total = (!empty($item['item_grand_total'])) ? $item['item_grand_total'] : 0;
                    $orderDetail->original_item_qty = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->original_item_price = (!empty($item['item_price'])) ? $item['item_price'] : 0;
                    $orderDetail->save();

                    $data = [
                        'created_user' => $request->user()->id,
                        'order_id' => $orderDetail->id,
                        'delviery_id' => NULL,
                        'updated_user' => NULL,
                        'previous_request_body' => NULL,
                        'request_body' => $orderDetail,
                        'action' => 'Order Details',
                        'status' => 'Created'
                    ];

                    saveOrderDeliveryLog($data);

                    $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                    $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                }
            }

            // update the total qty to order header table
            if (is_object($order) && $order->source != 1) {
                $order->update(
                    [
                        'total_qty' => $t_qty,
                    ]
                );
            } else {
                $order->update(
                    [
                        'total_qty' => $request->t_qty,
                    ]
                );
            }

            if (config('app.current_domain') !== "merchandising" && $request->source !== 1) {
                if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                    $this->createWorkFlowObject($isActivate, 'Order', $request, $order);
                }
            }

            // if mobile order
            if (is_object($order) && $order->source == 1) {
                $user = User::find($request->user()->id);
                if (is_object($user)) {
                    $salesmanInfo = $user->salesmanInfo;
                    updateMobileNumberRange($salesmanInfo, 'order_from', $request->order_number);
                }
            }

            create_action_history("Order", $order->id, auth()->user()->id, "create", "Customer created by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            // backend
            if ($request->source != 1) {
                updateNextComingNumber('App\Model\Order', 'order');
            }

            \DB::commit();
            $order->getSaveData();
            return prepareResult(true, $order, [], "Order added successfully", $this->success);
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

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating order.", $this->unauthorized);
        }

        $order = Order::with(
            array(
                'customer' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
                }
            )
        )
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name,type,code',
                'orderDetails',
                'orderDetails.reason:id,name,type,code',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.item.itemMainPrice:id,item_id,item_uom_id,item_price,item_uom_id',
                'orderDetails.item.itemMainPrice.itemUom:id,name,code',
                'orderDetails.item.itemUomLowerUnit:id,name,code',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.itemMainPrice.itemUom:id,name,code',
                'depot:id,depot_name',
                'lob:id,user_id,name,lob_code',
                'storageocation:id,code,name',
                'pickingSlipGenerator:id,order_id,picking_slip_generator_id,date,time,date_time',
                'invoice:id,invoice_number',
            )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($order)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        foreach ($order->orderDetails as $detail) {
            $detail->item_update = $detail->item_qty;
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "Error Please add Salesman"], "Error while validating salesman.", $this->unprocessableEntity);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating salesman.", $this->unprocessableEntity);
        }

        if ($request->source == 1 && $request->payment_term_id != "") {
            $validate = $this->validations($input, "addPayment");
            if ($validate["error"]) {
                return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating salesman.", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items"], "Error while validating salesman.", $this->unprocessableEntity);
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } elseif (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
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

            $previous_body = $order;

            //Delete old record
            OrderDetail::where('order_id', $order->id)->delete();

            $order->customer_id = (!empty($request->customer_id)) ? $request->customer_id : null;
            $order->depot_id = (!empty($request->depot_id)) ? $request->depot_id : null;
            $order->order_type_id = $request->order_type_id;
            $order->order_number = $request->order_number;
            $order->delivery_date = $request->delivery_date;
            $order->salesman_id = $request->salesman_id;
            $order->route_id = (!empty($route_id)) ? $route_id : null;
            $order->customer_lop = (!empty($request->customer_lop)) ? $request->customer_lop : null;
            $order->payment_term_id = $request->payment_term_id;
            $order->reason_id = (!empty($request->reason_id)) ? $request->reason_id : null;
            $order->due_date = $request->due_date;
            $order->total_qty = 0;
            $order->total_gross = $request->total_gross;
            $order->total_discount_amount = $request->total_discount_amount;
            $order->total_net = $request->total_net;
            $order->total_vat = $request->total_vat;
            $order->total_excise = $request->total_excise;
            $order->grand_total = $request->grand_total;
            $order->any_comment = $request->any_comment;
            $order->source = $request->source;
            $order->order_created_user_id = $order->order_created_user_id;
            $order->status = $status;
            if ($order->current_stage != "Approved") {
                $order->current_stage = $current_stage;
            }
            $order->warehouse_id = $request->warehouse_id;
            $order->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            $order->storage_location_id = (!empty($request->storage_location_id)) ? $request->storage_location_id : 0;
            $order->approval_status = "Updated";
            $order->is_user_updated = 1;
            $order->module_updated = "Order";
            $order->user_updated = $request->user()->id;
            if (config('app.current_domain') == "presales") {
                $order->is_presale_order = 1;
            } else {
                $order->is_presale_order = 0;
            }
            $order->save();

            $data = [
                'created_user' => $request->user()->id,
                'order_id' => $order->id,
                'delviery_id' => NULL,
                'updated_user' => $request->user()->id,
                'previous_request_body' => $previous_body,
                'request_body' => $order,
                'action' => 'Order Edit',
                'status' => 'Updated',
            ];

            saveOrderDeliveryLog($data);

            $t_qty = 0;
            $tc_qty = 0;

            if (is_array($request->items)) {


                foreach ($request->items as $item) {
                    $orderDetail = new OrderDetail;
                    $orderDetail->order_id = $order->id;
                    $orderDetail->item_id = $item['item_id'];
                    $orderDetail->item_uom_id = $item['item_uom_id'];
                    $orderDetail->original_item_uom_id = (!empty($item['original_item_uom_id'])) ? $item['original_item_uom_id'] : $item['item_uom_id'];
                    $orderDetail->discount_id = (!empty($item['discount_id'])) ? $item['discount_id'] : null;
                    $orderDetail->is_free = (!empty($item['is_free'])) ? $item['is_free'] : 0;
                    $orderDetail->is_item_poi = (!empty($item['is_item_poi'])) ? $item['is_item_poi'] : 0;
                    $orderDetail->promotion_id = (!empty($item['promotion_id'])) ? $item['promotion_id'] : 0;
                    $orderDetail->reason_id = ($item['reason_id'] > 0) ? $item['reason_id'] : null;
                    $orderDetail->is_deleted = (!empty($item['is_deleted'])) ? $item['is_deleted'] : 0;
                    $orderDetail->item_qty = (!empty($item['item_qty'])) ? $item['item_qty'] : 0;
                    $orderDetail->item_weight = (!empty($item['item_weight'])) ? $item['item_weight'] : 0;
                    $orderDetail->item_price = (!empty($item['item_price'])) ? $item['item_price'] : 0;
                    $orderDetail->item_gross = (!empty($item['item_gross'])) ? $item['item_gross'] : 0;
                    $orderDetail->item_discount_amount = (!empty($item['item_discount_amount'])) ? $item['item_discount_amount'] : 0;
                    $orderDetail->item_net = (!empty($item['item_net'])) ? $item['item_net'] : 0;
                    $orderDetail->item_vat = (!empty($item['item_vat'])) ? $item['item_vat'] : 0;
                    $orderDetail->item_excise = (!empty($item['item_excise'])) ? $item['item_excise'] : 0;
                    $orderDetail->item_grand_total = (!empty($item['item_grand_total'])) ? $item['item_grand_total'] : 0;
                    $orderDetail->original_item_qty = (!empty($item['original_item_qty'])) ? $item['original_item_qty'] : 0;

                    if (!empty($item['original_item_qty']) && $item['original_item_qty'] > 0) {
                        $orderDetail->original_item_price = $item['original_item_price'];
                    } else {
                        $orderDetail->original_item_price = $item['item_price'];
                    }

                    // If item already added
                    if (isset($item['original_item_qty'])) {
                        if ($item['original_item_qty'] > $item['item_qty']) {

                            $cancel_qty = $item['original_item_qty'] - $item['item_qty'];
                            $cancel_qty_convert = qtyConversion($item['item_id'], $item['item_uom_id'], $cancel_qty);
                            $tc_qty = $tc_qty + $cancel_qty_convert['Qty'];

                            // Convert the original item qty not item_qty
                            $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['original_item_qty']);
                        } else {
                            // Convert the original item qty not item_qty
                            $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);
                        }

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    } else {
                        // New Item added
                        $orderDetail->original_item_qty = $item['item_qty'];
                        $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                    }

                    $orderDetail->save();

                    $data = [
                        'created_user' => $request->user()->id,
                        'order_id' => $orderDetail->id,
                        'delviery_id' => NULL,
                        'updated_user' => $request->user()->id,
                        'previous_request_body' => NULL,
                        'request_body' => $orderDetail,
                        'action' => 'Order Detail Edit',
                        'status' => 'Updated',
                    ];

                    saveOrderDeliveryLog($data);

                    // if (isset($orderDetail->reason_id) && config('app.current_domain') == "presale") {
                    if (isset($orderDetail->reason_id)) {
                        $this->orderLogs($order, $orderDetail);
                    }

                    //$this->updateRFGenView($orderDetail);
                }
            }

            // update the total qty to order header table
            if (is_object($order) && $order->source != 1) {
                $order->update(
                    [
                        'total_qty' => $t_qty,
                        'total_cancel_qty' => $tc_qty,
                    ]
                );
            } else {
                $order->update(
                    [
                        'total_qty' => $request->t_qty,
                        'total_cancel_qty' => $request->tc_qty,
                    ]
                );
            }

            if ($order->current_stage != "Approved") {
                if ($isActivate = checkWorkFlowRule('Order', 'Edited', $current_organisation_id)) {
                    $this->createWorkFlowObject($isActivate, 'Order', $request, $order);
                }
            }

            $this->updateDelivery($order);

            // if ($isActivate = checkWorkFlowRule('Order', 'edit', $current_organisation_id)) {
            //     $this->createWorkFlowObject($isActivate, 'Order', $request, $order->id);
            // }

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
        $orderLog->changed_user_id = request()->user()->id;
        $orderLog->order_id = $order->id;
        $orderLog->order_detail_id = $orderDetail->id;
        $orderLog->customer_id = $order->customer_id;
        $orderLog->salesman_id = $order->salesman_id;
        $orderLog->item_id = $orderDetail->item_id;
        $orderLog->item_uom_id = $orderDetail->item_uom_id;
        $orderLog->reason_id = $orderDetail->reason_id;
        $orderLog->customer_code = model($order->customerInfo, 'customer_code');
        $orderLog->customer_name = model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname');
        $orderLog->salesman_code = model($order->salesmanInfo, 'salesman_code');
        $orderLog->salesman_name = model($order->salesman, 'firstname') . ' ' . model($order->salesman, 'lastname');
        $orderLog->item_name = model($orderDetail->item, 'item_name');
        $orderLog->item_code = model($orderDetail->item, 'item_code');
        $orderLog->item_uom = model($orderDetail->itemUom, 'name');
        $orderLog->item_qty = $orderDetail->item_qty;
        $orderLog->original_item_qty = $orderDetail->original_item_qty ?? 0;
        $orderLog->action = ($orderDetail->is_deleted == 1) ? "deleted" : "change qty";
        $orderLog->reason = model($orderDetail->reasonType, 'name');
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        // if ($by_pass == false) {
        $input = $request->json()->all();
        $validate = $this->validations($input, "item-apply-price");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating order", $this->unprocessableEntity);
        }
        //     $request = $by_pass_obj;
        // }

        DB::beginTransaction();
        try {
            $itemPriceInfo = item_apply_price($request);

            return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }
    }

    /**
     * Get price specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function itemApplyPrice2(Request $request)
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
                $retData = $this->singleItemApplyPrice((object) $input[$i]);
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
            $validator = Validator::make($input, [
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
        if ($type == 'dashboard') {
            $validator = Validator::make($input, [
                'start_date' => 'required',
                'end_date' => 'required',
            ]);
        }

        if ($type == 'delivery_report') {
            $validator = Validator::make($input, [
                'start_date' => 'required',
                'end_date' => 'required',
                'branch_plant_code' => 'required'
            ]);
        }
        if ($type == "addPayment") {
            $validator = Validator::make($input, [
                'payment_term_id' => 'required|integer|exists:payment_terms,id'
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = Validator::make($input, [
                'action' => 'required',
                'order_ids' => 'required'
            ]);
        }

        if ($type == 'item-apply-price') {
            $validator = Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'normal-item-apply-price') {
            $validator = Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'applyPDP') {
            $validator = Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id'
                // 'item_uom_id'   => 'required|integer|exists:item_uoms,id',
                // 'item_qty'      => 'required|numeric',
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

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {

        if (isset($obj->id)) {
            $module_path = 'App\\Model\\' . $module_name;
            $module = $module_path::where('id', $obj->id)
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
        // $wfrau = WorkFlowRuleApprovalUser::where('work_flow_rule_id', $work_flow_rule_id)->first();

        // send notification to Supply change and warehouse user

        /**
         * $obj = Order Objecct
         * $wfrau = WorkFlowRuleApprovalUser object
         */
        // $this->sendNotificationToUser($obj, $wfrau);
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

        // $input = $request->json()->all();
        // $validate = $this->validations($input, "cancel");
        // if ($validate["error"]) {
        //     return prepareResult(false, [], $validate['errors']->first(), "Error while validating order cancel", $this->unprocessableEntity);
        // }

        $order = Order::find($request->order_id);
        if ($order) {
            $order->reason_id = $request->reason_id;
            //$order->current_stage           = "Cancelled";
            $order->approval_status = "Cancelled";
            $order->total_cancel_qty = 0;
            $order->total_qty = 0;
            $order->total_gross = 0;
            $order->total_discount_amount = 0;
            $order->total_net = 0;
            $order->total_vat = 0;
            $order->total_excise = 0;
            $order->grand_total = 0;
            $order->is_user_updated = 1;
            $order->module_updated = "Order";
            $order->user_updated = $request->user()->id;
            $order->save();

            OrderDetail::where('order_id', $request->order_id)
                ->update([
                    'reason_id' => $request->reason_id,
                    'is_deleted' => 1,
                    'item_qty' => 0,
                    'item_price' => 0,
                    'item_gross' => 0,
                    'item_discount_amount' => 0,
                    'item_net' => 0,
                    'item_vat' => 0,
                    'item_excise' => 0,
                    'item_grand_total' => 0,
                    'delivered_qty' => 0,
                    'open_qty' => 0,
                ]);

            $delivery = Delivery::where('order_id', $request->order_id)->first();

            if ($delivery) {
                $delivery->reason_id = $request->reason_id;
                //$delivery->current_stage = "Cancelled";
                $delivery->approval_status = "Cancel";
                $delivery->total_cancel_qty = 0;
                $delivery->total_qty = 0;
                $delivery->total_gross = 0;
                $delivery->total_discount_amount = 0;
                $delivery->total_net = 0;
                $delivery->total_vat = 0;
                $delivery->total_excise = 0;
                $delivery->grand_total = 0;
                $order->is_user_updated = 1;
                $order->module_updated = "Order";
                $order->user_updated = $request->user()->id;
                $delivery->save();

                DeliveryDetail::where('delivery_id', $delivery->id)
                    ->update([
                        'is_deleted' => 1,
                        'reason_id' => $request->reason_id,
                        'item_qty' => 0,
                        'item_price' => 0,
                        'item_gross' => 0,
                        'item_discount_amount' => 0,
                        'item_net' => 0,
                        'item_vat' => 0,
                        'item_excise' => 0,
                        'item_grand_total' => 0,
                        // 'delivered_qty' => 0,
                        'open_qty' => 0,
                        'original_item_qty' => 0,
                    ]);

                $deliveryASS = DeliveryAssignTemplate::where('delivery_id', $delivery->id)->first();

                if ($deliveryASS) {
                    DeliveryAssignTemplate::where('delivery_id', $delivery->id)
                        ->update([
                            'is_deleted' => 1,
                            'qty' => 0,
                            'trip' => 0,
                        ]);
                }
            }

            return prepareResult(true, $order, [], "Order cancelled", $this->success);
        }
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


        Order::whereIn('id', $request->order_ids)
            ->where('current_stage', "Approved")
            ->whereIn('approval_status', ["Created", "Updated", "Partial-Delivered", "Delivered"])
            ->update([
                'sync_status' => null,
                'approval_status' => "Picking Confirmed",
                'order_generate_picking' => 1,
                'picking_status' => 'full',
            ]);

        OrderDetail::whereIn('order_id', $request->order_ids)
            ->update([
                'picking_status' => 'full'
            ]);

        if (count($orders)) {
            foreach ($orders as $order) {
                PickingSlipGenerator::create([
                    'order_id' => $order->id,
                    'date' => now()->format('Y-m-d'),
                    'time' => now()->format('H-i-s'),
                    'date_time' => now(),
                    'picking_slip_generator_id' => request()->user()->id,
                ]);

                // if get Delivery
                $d = Delivery::where('order_id', $order->id)->first();

                if (!$d) {

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
                        $delivery->delivery_number = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $code);
                        $delivery->order_id = $order->id;
                        $delivery->customer_id = $order->customer_id;
                        $delivery->salesman_id = null;
                        $delivery->reason_id = null;
                        $delivery->route_id = null;
                        $delivery->storage_location_id = (!empty($order->storage_location_id)) ? $order->storage_location_id : null;
                        $delivery->warehouse_id = (!empty($order->warehouse_id)) ? $order->warehouse_id : 0;
                        $delivery->delivery_type = $order->order_type_id;
                        $delivery->delivery_type_source = 2;
                        $delivery->delivery_date = $order->delivery_date;
                        $delivery->delivery_time = (isset($order->delivery_time)) ? $order->delivery_time : date('H:m:s');
                        $delivery->delivery_weight = $order->delivery_weight;
                        $delivery->payment_term_id = $order->payment_term_id;
                        // $delivery->total_qty                = $order->total_qty;
                        $delivery->total_qty = 0;
                        $delivery->total_gross = $order->total_gross;
                        $delivery->total_discount_amount = $order->total_discount_amount;
                        $delivery->total_net = $order->total_net;
                        $delivery->total_vat = $order->total_vat;
                        $delivery->total_excise = $order->total_excise;
                        $delivery->grand_total = $order->grand_total;
                        $delivery->current_stage_comment = $order->current_stage_comment;
                        $delivery->delivery_due_date = $order->due_date;
                        $delivery->source = $order->source;
                        $delivery->status = $status;
                        $delivery->current_stage = $current_stage;
                        $delivery->approval_status = "Created";
                        $delivery->lob_id = (!empty($order->lob_id)) ? $order->lob_id : null;
                        $delivery->is_user_updated = 0;
                        $delivery->module_updated = NULL;
                        $delivery->user_updated = "Pickup";
                        $delivery->save();

                        $data = [
                            'created_user' => request()->user()->id,
                            'order_id' => $delivery->order_id,
                            'delviery_id' => $delivery->id,
                            'updated_user' => NULL,
                            'previous_request_body' => NULL,
                            'request_body' => $delivery,
                            'action' => 'Delivery Created Picking',
                            'status' => 'Created',
                        ];

                        saveOrderDeliveryLog($data);

                        $t_qty = 0;

                        if (count($order->orderDetailsWithoutDelete)) {
                            $rf_gen = rfGenView::where('Order_Number', $order->order_number)->delete();

                            foreach ($order->orderDetailsWithoutDelete as $od) {
                                //save DeliveryDetail
                                $deliveryDetail = new DeliveryDetail();
                                $deliveryDetail->uuid = $od->uuid;
                                $deliveryDetail->delivery_id = $delivery->id;
                                $deliveryDetail->item_id = $od->item_id;
                                $deliveryDetail->item_uom_id = $od->item_uom_id;
                                $deliveryDetail->original_item_uom_id = $od->item_uom_id;
                                $deliveryDetail->discount_id = $od->discount_id;
                                $deliveryDetail->is_free = $od->is_free;
                                $deliveryDetail->is_item_poi = $od->is_item_poi;
                                $deliveryDetail->promotion_id = $od->promotion_id;
                                $deliveryDetail->reason_id = null;
                                $deliveryDetail->is_deleted = 0;
                                $deliveryDetail->item_qty = $od->item_qty;
                                $deliveryDetail->original_item_qty = $od->item_qty;
                                $deliveryDetail->open_qty = $od->item_qty;
                                $deliveryDetail->item_price = $od->item_price;
                                $deliveryDetail->item_gross = $od->item_gross;
                                $deliveryDetail->item_discount_amount = $od->item_discount_amount;
                                $deliveryDetail->item_net = $od->item_net;
                                $deliveryDetail->item_vat = $od->item_vat;
                                $deliveryDetail->item_excise = $od->item_excise;
                                $deliveryDetail->item_grand_total = $od->item_grand_total;
                                $deliveryDetail->batch_number = $od->batch_number;
                                $deliveryDetail->transportation_status = "No";
                                $deliveryDetail->save();

                                $data = [
                                    'created_user' => request()->user()->id,
                                    'order_id' => $delivery->order_id,
                                    'delviery_id' => $deliveryDetail->id,
                                    'updated_user' => NULL,
                                    'previous_request_body' => NULL,
                                    'request_body' => $deliveryDetail,
                                    'action' => 'Delivery Detail Created Picking',
                                    'status' => 'Created',
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

                        if ($isActivate = checkWorkFlowRule('Delivery', 'create', $current_organisation_id)) {
                            $this->createWorkFlowObject($isActivate, 'Delivery', $order, $delivery);
                        }

                        DB::commit();

                        updateNextComingNumber('App\Model\Delivery', 'delivery');

                        // return prepareResult(true, $delivery, [], "Delivery added successfully.", $this->success);
                    } catch (\Exception $exception) {
                        DB::rollback();
                        $order->sync_status = $exception;
                        $order->save();
                        return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                    } catch (\Throwable $exception) {
                        DB::rollback();
                        $order->sync_status = $exception;
                        $order->save();
                        return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                    }
                }
            }

            $order = Order::with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code,customer_address_1,customer_address_2,customer_address_3',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'route:id,route_name,route_code',
                'reason:id,name,type,code',
                'orderDetails',
                'orderDetails.reason:id,name,type,code',
                'orderDetails.item:id,item_name,item_code,lower_unit_uom_id',
                'orderDetails.item.itemMainPrice:id,item_id,item_uom_id,item_price,item_uom_id',
                'orderDetails.item.itemMainPrice.itemUom:id,name,code',
                'orderDetails.item.itemUomLowerUnit:id,name,code',
                'orderDetails.itemUom:id,name,code',
                'orderDetails.itemMainPrice',
                'orderDetails.itemMainPrice.itemUom:id,name,code',
                'depot:id,depot_name',
                'lob:id,user_id,name,lob_code',
                'storageocation:id,code,name',
                'pickingSlipGenerator:id,order_id,picking_slip_generator_id,date,time,date_time',
                'invoice:id,invoice_number'
            )
                ->whereIn('id', $request->order_ids)
                ->get();

            return prepareResult(true, $order, [], "Picking confirmed successfully.", $this->success);
        }

        return prepareResult(false, [], [], "Picking not found.", $this->unprocessableEntity);
    }

    /**
     * This function save the RF gen orders
     *
     * @param [object] $deliveryDetail
     * @return void
     */
    public function saverfGen($deliveryDetail, $order_detail, $order)
    {
        $rf_gen = new rfGenView();
        $rf_gen->GLDate = Carbon::parse($order->delivery_date)->format('Y-m-d');
        $rf_gen->item_id = $order_detail->item_id;
        $rf_gen->ITM_CODE = model($order_detail->item, 'item_code');
        $rf_gen->ITM_NAME = model($order_detail->item, 'item_name');
        $rf_gen->TranDate = model($order, 'order_date');
        $rf_gen->Order_Number = model($order, 'order_number');
        $rf_gen->LOAD_NUMBER = $deliveryDetail->delivery_id;
        $rf_gen->MCU_CODE = model($order->storageocation, 'code');
        $rf_gen->DemandPUOM = ($order_detail->item_uom_id == model($order_detail->item, 'lower_unit_uom_id')) ? $order_detail->item_qty : 0;
        $rf_gen->DemandSUOM = ($order_detail->item_uom_id != model($order_detail->item, 'lower_unit_uom_id')) ? conevertQtyForRFGen($order_detail->item_id, $order_detail->item_qty, $order_detail->item_uom_id, true) : 0;
        $rf_gen->mobiato_order_picked = 0;
        $rf_gen->order_detail_id = $order_detail->id;
        $rf_gen->REM_QTY = 0;
        $rf_gen->RTE_CODE = "MT1";
        $rf_gen->RFDate = $order->delivery_date;
        if ($order_detail->item_qty > 0) {
            $rf_gen->save();
        }

    }

    /**
     * OCR Order Store
     */
    public function storeOcrOrder(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->all();
        $validate = $this->validations($input, "ocr-add");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating order.", $this->unprocessableEntity);
        }

        $file = $request->file('file');
        $og_name = $request->file('file')->getClientOriginalName();
        $mimeType = $request->file('file')->getMimeType();

        // Store the file in the server
        $path = $request->file('file')->store(
            'ocr',
            'public'
        );

        // find the file type base on customer id to customer group
        $user = User::find($request->customer_id);
        if (!$user) {
            return prepareResult(false, [], ['error' => "Customer not found"], "Error while validating order.", $this->not_found);
        }

        $file_type = "";
        $customer_info = $user->customerInfo;
        if (is_object($customer_info->customerGroup)) {
            $file_type = $customer_info->customerGroup->type;
        }

        if (!$file_type) {
            return prepareResult(false, [], ['error' => "OCR file type is missing."], "Error while validating ocr order.", $this->not_found);
        }

        // path of the store file
        $location = storage_path('app/public/' . $path);

        // API Hint JsonRepsponse we are getting
        $response = Curl::to('http://15.184.80.189:8080/file-upload')
            ->withContentType('multipart/form-data; boundary=' . hash('sha256', uniqid('', true)))
            ->withData(array('file_type' => $file_type))
            ->withFile('file', $location, $mimeType, $og_name)
            ->returnResponseObject()
            ->post();

        if ($response->status == 0) {
            return prepareResult(false, [], ["error" => $response->error], "Error while validating order.", $this->unprocessableEntity);
        }

        $ord = 0;
        if ($user) {
            $customer_info = $user->customerInfo;

            // Convert to Json to Array
            $json_data = json_decode($response->content);

            $table_data = $json_data->TableData;
            if (count($table_data)) {
                $count_row = count($table_data);
                foreach ($table_data as $key => $td) {
                    $d = (array) $td;

                    $user_id = $user->id;

                    if ($file_type == "type-1") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->BOF_NO;
                        $item_no = $d['Item No'];
                        $qty = $d['QTY'];
                    } elseif ($file_type == "type-2") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['ITEM #'];
                        $qty = $d['QTY.'];
                    } else if ($file_type == "type-3") {
                        //we are getting LOP No from Json
                        $explode = explode(' ', $json_data->AttributeData[0]->PO);
                        $customer_lop = (isset($explode[2]) ? $explode[2] : '0');
                        $item_no = $d['ITEM CODE'];
                        $qty = $d['QUANTITY'];
                    } elseif ($file_type == "type-4") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->Order_NO;
                        $explode = explode('-', $d['Neg - Vend. ItNo.']);
                        $item_no = preg_replace("/[^0-9\.]/", "", (isset($explode[1]) ? $explode[1] : '0'));
                        $qty = $d['Quantity'];
                    } else if ($file_type == "type-5") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['Item No.'];
                        $qty = $d['Qty'];
                    } else if ($file_type == "type-6") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['BAR CODE'];
                        $qty = $d['QTY UC'];
                    } else if ($file_type == "type-7") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = preg_replace("/[^0-9\.]/", "", $d['Item code']);
                        $qty = preg_replace("/[^0-9\.]/", "", $d['Qty']);
                    } else if ($file_type == "type-8") {
                    } else if ($file_type == "type-9") {
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $explode = explode(' ', $d['Article']);
                        $item_no = (isset($explode[0]) ? $explode[0] : '0');
                        $qty = $d['Quantity'];
                    } else if ($file_type == "type-10") {
                    } else if ($file_type == "type-11") {
                    } else if ($file_type == "type-12") {
                        //we are getting LOP No from Json
                        $explode = explode(' ', $json_data->AttributeData[0]->PO);
                        $customer_lop = (isset($explode[2]) ? $explode[2] : '0');
                        $item_no = $d['Item Code'];
                        $qty = $d['Qty.'];
                    } else if ($file_type == "type-13") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['Article'];
                        $ord_qty = explode('  ', $d['Ord.Qty']);
                        $qty = $ord_qty[0];
                    } else if ($file_type == "type-14") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['IPN No.'];
                        $qty = $d['Carton Qty'];
                    } else if ($file_type == "type-15") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->Order_NO;
                        $item_no = $d['Item'];
                        $qty = $d['Qty'];
                    } else if ($file_type == "type-17") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->Order_NO;
                        $explode = explode(' ', $d['Sl.No']);
                        $item_no = (isset($explode[2]) ? $explode[2] : '0');
                        $qty = $d['QTY'];
                    } else if ($file_type == "type-18") {
                    } else if ($file_type == "type-19") {
                        //we are getting LOP No from Json
                        $explode = explode(' ', $json_data->AttributeData[0]->PO);
                        $customer_lop = (isset($explode[0]) ? $explode[0] : '0');
                        $item_no = $d[' Art. No.'];
                        $qty = $d['Order Unit Qty'];
                    } else if ($file_type == "type-20") {
                    } else if ($file_type == "type-21") {
                        //we are getting LOP No from Json
                        $customer_lop = $json_data->AttributeData[0]->PO;
                        $item_no = $d['Item no.'];
                        $qty = $d['Qty Ordered'];
                    }

                    $order_exist = Order::where('customer_lop', $customer_lop)
                        ->whereIn('approval_status', ['Pending', 'Approved', 'Rejected', 'In-Process', 'Partial-Deliver', 'Completed', 'Shipping', 'Picking', 'Updated'])
                        ->where('source', 5)
                        ->first();

                    if ($order_exist) {
                        return prepareResult(false, [], ['error' => 'Cusotmer LOP order is already exist.'], "Cusotmer LOP order is already exist.", $this->unprocessableEntity);
                    }

                    $count_row--;

                    $port = PortfolioManagement::with(
                        'portfolioManagementCustomer'
                    )
                        ->whereHas('portfolioManagementCustomer', function ($q) use ($user_id) {
                            $q->where('user_id', $user_id);
                        })->first();

                    if ($port) {
                        $pct = PortfolioManagementItem::where('portfolio_management_id', $port->id)
                            ->where('vendor_item_code', $item_no)
                            ->first();

                        if ($pct) {

                            $item = Item::find($pct->item_id);
                            $item_uom = ItemUom::find($pct->vendor_item_uom_id);
                            if (!$item) {

                                OCRLogs::create([
                                    'customer_lpo' => $customer_lop,
                                    'item_no' => $item_no,
                                    'qty' => $qty,
                                ]);

                                continue;
                            }

                            $order = Order::where('customer_lop', $customer_lop)
                                ->where('order_date', date('Y-m-d'))
                                ->where('approval_status', '!=', 'Cancelled')
                                ->first();

                            DB::beginTransaction();
                            try {

                                if (!$order) {

                                    $variable = "order";
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
                                        $order_number = $nextComingNumber['number_is'];
                                    } else {
                                        $order_number = "0000001";
                                    }

                                    $ot = OrderType::where('name', 'Credit')->first();

                                    $ord = $this->OrderAdd($order_number, $request, $ot->id, $customer_lop);
                                }

                                if ($ord) {

                                    $stdObject = new stdClass();
                                    $stdObject->item_id = $item->id;
                                    $stdObject->customer_id = $customer_info->id;
                                    $stdObject->item_qty = $qty;
                                    $stdObject->item_uom_id = $item_uom->id;
                                    $stdObject->lob_id = '';
                                    $stdObject->delivery_date = date('Y-m-d');

                                    $item_apply = item_apply_price($stdObject);
                                    $original = $item_apply;

                                    $this->OrderDetailAdd($ord, $original, $item, $item_uom, $qty, $item_no);

                                    $od = OrderDetail::where('order_id', $ord->id)->get();
                                    if (count($od)) {
                                        // $item_qty_array             = $od->pluck('item_qty')->toArray();
                                        $item_price_array = $od->pluck('item_price')->toArray();
                                        $item_gross_array = $od->pluck('item_gross')->toArray();
                                        $item_discount_amount_array = $od->pluck('item_discount_amount')->toArray();
                                        $item_net_array = $od->pluck('item_net')->toArray();
                                        $item_vat_array = $od->pluck('item_vat')->toArray();
                                        $item_grand_total_array = $od->pluck('item_grand_total')->toArray();
                                        $item_excise_array = $od->pluck('item_excise')->toArray();

                                        $ord->update([
                                            'total_gross' => array_sum($item_gross_array),
                                            'total_discount_amount' => array_sum($item_discount_amount_array),
                                            'total_net' => array_sum($item_net_array),
                                            'total_vat' => array_sum($item_vat_array),
                                            'total_excise' => array_sum($item_excise_array),
                                            'grand_total' => array_sum($item_grand_total_array),
                                        ]);
                                    }
                                }
                                if ($count_row < 1) {
                                    DB::commit();
                                }
                            } catch (\Exception $exception) {
                                DB::rollback();
                                OCRLogs::create([
                                    'customer_lpo' => $customer_lop,
                                    'item_no' => $item_no,
                                    'qty' => $qty,
                                    'file_type' => $file_type,
                                ]);
                                return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
                            } catch (\Throwable $exception) {
                                DB::rollback();
                                OCRLogs::create([
                                    'customer_lpo' => $customer_lop,
                                    'item_no' => $item_no,
                                    'qty' => $qty,
                                    'file_type' => $file_type,
                                ]);
                                return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
                            }
                        }
                    } else {
                        OCRLogs::create([
                            'customer_lpo' => $customer_lop,
                            'item_no' => $item_no,
                            'qty' => $qty,
                            'file_type' => $file_type,
                        ]);
                    }
                }
            }
        }

        if (!is_object($ord)) {
            return prepareResult(false, [], ['error' => 'Oops!!!, something went wrong, please try again.'], "Oops!!!, order not added.", $this->unprocessableEntity);
        }

        return prepareResult(true, [], [], "Order added.", $this->success);
    }

    /**
     * This function is calculate the price base on customer and item base price
     *
     */
    private function item_apply_price2($request)
    {
        $cusotmer = CustomerInfo::find($request->customer_id);

        $item = Item::find($request->item_id);
        $qty = $request->item_qty;

        // first find the price based on item and customer
        $item_price_objs = CustomerBasedPricing::where('customer_id', $cusotmer->user_id)
            ->where('item_id', $request->item_id)
            ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->get();

        if (count($item_price_objs)) {
            $item_price_obj = CustomerBasedPricing::where('customer_id', $cusotmer->user_id)
                ->where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->orderBy('updated_at', 'desc')
                ->first();

            // cusotmer price with same requested uom
            if ($item_price_obj) {
                $price = $item_price_obj->price;
                return itemPriceSet($qty, $price, $item, $request);
            }

            if (!$item_price_obj) {
                $item_price_obj = $item_price_objs->first();

                // customer base price
                $cusotmer_price = $item_price_obj->price;
                $cusotmer_lower_price = 0;

                if ($item_price_obj->item_uom_id == $item->lower_unit_uom_id) {
                    $cusotmer_lower_price = $cusotmer_price;
                } else {
                    $item_main_price = ItemMainPrice::where('item_id', $item_price_obj->item_id)
                        ->where('item_uom_id', $item_price_obj->item_uom_id)
                        ->first();
                    // pre($item_main_price);
                    if ($item_main_price) {
                        $upc = $item_main_price->item_upc;
                        if ($upc < 1) {
                            $cusotmer_lower_price = $cusotmer_price / 1;
                        } else {
                            $cusotmer_lower_price = $cusotmer_price / $upc;
                        }
                    } else {
                        return customerGroupBasePrice($request, $qty, $item, $item_price_objs);
                        $item_price_objs = ItemBasePrice::where('item_id', $request->item_id)
                            // ->where('item_uom_id', $request->item_uom_id)
                            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            ->orderBy('updated_at', 'desc')
                            ->get();

                        if (count($item_price_objs)) {
                            return itemBasePrice($request, $qty, $item, $item_price_objs);
                        }
                    }
                }

                $price = 0;
                if ($request->item_uom_id == $item->lower_unit_uom_id) {
                    $price = $cusotmer_lower_price;
                } else {
                    $item_main_price = ItemMainPrice::where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->first();

                    if ($item_main_price) {
                        $upc = $item_main_price->item_upc;
                        if ($upc < 1) {
                            $price = $cusotmer_lower_price * 1;
                        } else {
                            $price = $cusotmer_lower_price * $upc;
                        }
                    } else {
                        return customerGroupBasePrice($request, $qty, $item, $item_price_objs);
                        $item_price_objs = ItemBasePrice::where('item_id', $request->item_id)
                            // ->where('item_uom_id', $request->item_uom_id)
                            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            ->orderBy('updated_at', 'desc')
                            ->get();

                        if (count($item_price_objs)) {
                            return itemBasePrice($request, $qty, $item, $item_price_objs);
                        }
                    }
                }

                return itemPriceSet($qty, $price, $item, $request);
            }

            // return itemPriceSet($qty, $price, $item, $request);
        }

        if (count($item_price_objs) < 1) {
            return customerGroupBasePrice($request, $qty, $item, $item_price_objs);

            $item_price_objs = ItemBasePrice::where('item_id', $request->item_id)
                // ->where('item_uom_id', $request->item_uom_id)
                // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->orderBy('updated_at', 'desc')
                ->get();

            if (count($item_price_objs)) {
                return itemBasePrice($request, $qty, $item, $item_price_objs);
            }
        }

        if (count($item_price_objs) < 1) {
            $std_object = new stdClass;
            $std_object->item_qty = $request->item_qty;
            $std_object->item_price = 0;
            $std_object->totla_price = 0;
            $std_object->item_gross = 0;
            $std_object->net_gross = 0;
            $std_object->net_excise = 0;
            $std_object->discount = 0;
            $std_object->discount_percentage = 0;
            $std_object->discount_id = 0;
            $std_object->total_net = 0;
            $std_object->is_free = false;
            $std_object->is_item_poi = false;
            $std_object->promotion_id = null;
            $std_object->total_excise = 0;
            $std_object->total_vat = 0;
            $std_object->total = 0;

            return $std_object;
        }
    }

    private function customerGroupBasePrice2($request, $qty, $item, $item_price_objs)
    {
        $cgbp = CustomerGroupBasedPricing::where('item_id', $item->id)
            ->where('item_uom_id', $request->item_uom_id)
            ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->first();

        // requested uom and item
        if ($cgbp) {
            $price = $cgbp->price;
            return itemPriceSet($qty, $price, $item, $request);
        }

        $cgbps = CustomerGroupBasedPricing::where('item_id', $item->id)
            ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->get();

        if (count($cgbps)) {
            $cgbp = $cgbps->first();

            $cusotmer_price = $cgbp->price;
            $cusotmer_lower_price = 0;

            if ($cgbp->item_uom_id == $item->lower_unit_uom_id) {
                $cusotmer_lower_price = $cusotmer_price;
            } else {
                $item_main_price = ItemMainPrice::where('item_id', $cgbp->item_id)
                    ->where('item_uom_id', $cgbp->item_uom_id)
                    ->first();

                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $cusotmer_lower_price = $cusotmer_price / 1;
                    } else {
                        $cusotmer_lower_price = $cusotmer_price / $upc;
                    }
                } else {
                    $item_price_objs = ItemBasePrice::where('item_id', $request->item_id)
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    if (count($item_price_objs)) {
                        return itemBasePrice($request, $qty, $item, $item_price_objs);
                    }
                }
            }

            $price = 0;
            if ($request->item_uom_id == $item->lower_unit_uom_id) {
                $price = $cusotmer_lower_price;
            } else {
                $item_main_price = ItemMainPrice::where('item_id', $request->item_id)
                    ->where('item_uom_id', $request->item_uom_id)
                    ->first();

                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $price = $cusotmer_lower_price * 1;
                    } else {
                        $price = $cusotmer_lower_price * $upc;
                    }
                } else {
                    $item_price_objs = ItemBasePrice::where('item_id', $request->item_id)
                        ->orderBy('updated_at', 'desc')
                        ->get();

                    if (count($item_price_objs)) {
                        return itemBasePrice($request, $qty, $item, $item_price_objs);
                    }
                }
            }

            return itemPriceSet($qty, $price, $item, $request);
        }
    }


    private function itemBasePrice2($request, $qty, $item, $item_price_objs)
    {
        $item_price_obj = ItemBasePrice::where('item_id', $request->item_id)
            ->where('item_uom_id', $request->item_uom_id)
            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($item_price_obj) {
            $price = $item_price_obj->price;
            return itemPriceSet($qty, $price, $item, $request);
        }

        if (!$item_price_obj) {
            $item_price_obj = $item_price_objs->first();
            $cusotmer_price = $item_price_obj->price;

            $cusotmer_lower_price = 0;

            if ($item_price_obj->item_uom_id == $item->lower_unit_uom_id) {
                $cusotmer_lower_price = $cusotmer_price;
            } else {
                $item_main_price = ItemMainPrice::where('item_id', $request->item_id)
                    ->where('item_uom_id', $item_price_obj->item_uom_id)
                    ->first();
                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $cusotmer_lower_price = $cusotmer_price / 1;
                    } else {
                        $cusotmer_lower_price = $cusotmer_price / $upc;
                    }
                }
            }

            $price = 0;
            if ($request->item_uom_id == $item->lower_unit_uom_id) {
                $price = $cusotmer_lower_price;
            } else {
                $item_main_price = ItemMainPrice::where('item_id', $request->item_id)
                    ->where('item_uom_id', $request->item_uom_id)
                    ->first();

                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $price = $cusotmer_lower_price * 1;
                    } else {
                        $price = $cusotmer_lower_price * $upc;
                    }
                }
            }
        }
        return itemPriceSet($qty, $price, $item, $request);
    }

    private function itemPriceSet2($qty, $price, $item, $request)
    {
        $item_price = $price;

        $total_price = $item_price + (($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0);

        $net_gross = $qty * $item_price;
        $item_gross = $qty * $total_price;

        $item_excise = ($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0;
        $net_excise = $qty * ($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0;

        $total_net = $item_gross - 0;

        $vat = 5;
        if ($item->item_vat_percentage > 0) {
            $vat = $item->item_vat_percentage;
        }
        $item_vat = ($total_net * $vat) / 100;
        $total = $total_net + $item_vat;

        $std_object = new stdClass;
        $std_object->item_qty = $qty;
        $std_object->item_price = number_format(round($item_price, 2), 2);
        $std_object->totla_price = number_format(round($total_price, 2), 2);
        $std_object->item_gross = number_format($item_gross, 2);
        $std_object->net_gross = number_format($net_gross, 2);
        $std_object->net_excise = number_format($net_excise, 2);
        $std_object->discount = 0;
        $std_object->discount_percentage = 0;
        $std_object->discount_id = 0;
        $std_object->is_free = false;
        $std_object->is_item_poi = false;
        $std_object->promotion_id = null;
        $std_object->total_net = number_format($total_net, 2);
        $std_object->total_excise = number_format($item_excise, 2);
        $std_object->total_vat = number_format($item_vat, 2);
        $std_object->total = number_format($total, 2);

        return $std_object;
    }

    private function OrderAdd($order_number, $request, $order_type_id, $customer_lop)
    {
        $status = 1;
        $current_stage = 'Approved';
        $current_organisation_id = request()->user()->organisation_id;
        if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
            $status = 0;
            $current_stage = 'Pending';
        }

        $customer_info = CustomerInfo::where('user_id', $request->customer_id)->first();
        $lob = Lob::where('lob_code', "00010")->first();

        $lob_id = 1;
        if ($lob) {
            $lob_id = $lob->id;
        }

        $payment_term_id = null;
        if ($customer_info) {

            $customer_lob = CustomerLob::where('customer_info_id', $customer_info->id)
                ->where('lob_id', $lob_id)
                ->first();

            if ($customer_lob) {
                $payment_term_id = $customer_lob->payment_term_id;
            }
        }

        $cwm = CustomerWarehouseMapping::where('lob_id', $lob_id)->where('customer_id', $request->customer_id)->first();

        if ($cwm) {
            $storage_location_id = $cwm->storage_location_id;
        } else {
            $storage_location_id = null;
        }

        $order = new Order();
        $order->customer_id = (!empty($request->customer_id)) ? $request->customer_id : null;
        $order->order_number = nextComingNumber('App\Model\Order', 'order', 'order_number', $order_number);
        $order->depot_id = null;
        $order->order_type_id = $order_type_id; // Credit
        $order->order_date = date('Y-m-d');
        $order->delivery_date = date('Y-m-d');
        $order->salesman_id = null;
        $order->route_id = null;
        $order->reason_id = null;
        $order->customer_lop = $customer_lop;
        $order->payment_term_id = $payment_term_id;
        $order->due_date = date('Y-m-d');
        $order->total_qty = 0;
        $order->total_gross = 0;
        $order->total_discount_amount = 0;
        $order->total_net = 0;
        $order->total_vat = 0;
        $order->total_excise = 0;
        $order->grand_total = 0;
        $order->any_comment = null;
        $order->source = 5;
        $order->status = $status;
        $order->current_stage = $current_stage;
        $order->current_stage_comment = null;
        $order->warehouse_id = ($storage_location_id) ? getWarehuseBasedOnStorageLoacation($storage_location_id, false) : null;
        $order->lob_id = $lob_id;
        $order->storage_location_id = $storage_location_id;
        $order->order_created_user_id = $request->user()->id;
        $order->approval_status = "Created";
        $order->is_user_updated = 0;
        $order->module_updated = NULL;
        $order->user_updated = NULL;
        $order->save();

        if ($isActivate = checkWorkFlowRule('Order', 'Create', $current_organisation_id)) {
            $this->createWorkFlowObject($isActivate, 'Order', $request, $order);
        }

        updateNextComingNumber('App\Model\Order', 'order');

        return $order;
    }

    private function orderDetailAdd($order, $original, $item, $item_uom, $qty, $item_no)
    {
        $orderDetail = OrderDetail::where('order_id', $order->id)
            ->where('item_id', $item->id)
            ->where('item_uom_id', $item_uom->id)
            ->first();

        if (!$orderDetail) {
            $orderDetail = new OrderDetail;
        }

        $orderDetail->order_id = $order->id;
        $orderDetail->item_id = $item->id;
        $orderDetail->item_uom_id = $item_uom->id;
        $orderDetail->original_item_uom_id = $item_uom->id;
        $orderDetail->discount_id = (isset($original['discount_id']) ? $original['discount_id'] : 0);
        $orderDetail->is_free = (isset($original['is_free']) ? $original['is_free'] : 0);
        $orderDetail->is_item_poi = (isset($original['is_item_poi']) ? $original['is_item_poi'] : 0);
        $orderDetail->promotion_id = (isset($original['promotion_id']) ? $original['promotion_id'] : 0);
        $orderDetail->reason_id = null;
        $orderDetail->is_deleted = 0;
        $orderDetail->item_qty = (!empty($qty)) ? $qty : 0;
        $orderDetail->original_item_qty = (!empty($qty)) ? $qty : 0;
        $orderDetail->item_vendor_code = $item_no;
        $orderDetail->item_weight = 0;
        $orderDetail->item_price = (!empty($original['item_price'])) ? $original['item_price'] : 0;
        $orderDetail->item_gross = (!empty($original['item_gross'])) ? $original['item_gross'] : 0;
        $orderDetail->item_discount_amount = (!empty($original['discount_id'])) ? $original['discount_id'] : 0;
        $orderDetail->item_net = (!empty($original['total_net'])) ? $original['total_net'] : 0;
        $orderDetail->item_vat = (!empty($original['total_vat'])) ? $original['total_vat'] : 0;
        $orderDetail->item_excise = (!empty($original['total_excise'])) ? $original['total_excise'] : 0;
        $orderDetail->item_grand_total = (!empty($original['total'])) ? $original['total'] : 0;
        $orderDetail->save();

        $getItemQtyByUom = qtyConversion($item['item_id'], $item['item_uom_id'], $item['item_qty']);

        $order->update([
            'total_qty' => ($order->total_qty + $getItemQtyByUom['Qty']),
        ]);

        return $orderDetail;
    }

    public function show(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $order = OrderDetail::select(
            'order_number',
            'orders.uuid as uuid',
            'items.item_code',
            'items.item_name as item_name',
            'item_uoms.name as item_uom',
            'item_qty',
            DB::raw("CONCAT(reason_types.code, '-',reason_types.name) as reason"),
            DB::raw('(CASE WHEN is_deleted = 1 THEN "Yes" ELSE "No" END) AS Is_deleted'),
        )
            ->leftJoin('orders', function ($join) {
                $join->on('order_details.order_id', '=', 'orders.id');
            })
            ->leftJoin('items', function ($join) {
                $join->on('order_details.item_id', '=', 'items.id');
            })
            ->leftJoin('item_uoms', function ($join) {
                $join->on('order_details.item_uom_id', '=', 'item_uoms.id');
            })
            ->leftJoin('reason_types', function ($join) {
                $join->on('order_details.reason_id', '=', 'reason_types.id');
            });

        if ($request->order_number) {
            $order->where('order_number', $request->order_number);
        }

        if ($request->delivery_date) {
            $order->where('delivery_date', $request->delivery_date);
        }

        $orders = $order->get();

        return prepareResult(true, $orders, [], "Order show.", $this->success);
    }

    public function OrderTemplateImport(Request $request)
    {
        $oldlpo = 0;
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        //$oldlpo=0;

        $validator = Validator::make($request->all(), [
            'order_update_file' => 'required|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Order import", $this->unauthorized);
        }

        $file = request()->file('order_update_file');
        $errors = array();

        $fileName = $_FILES["order_update_file"]["tmp_name"];
        $old_customer_lpo = '';

        if ($_FILES["order_update_file"]["size"] > 0) {

            $file = fopen($fileName, "r");
            while (($row = fgetcsv($file, 10000, ",")) !== false) {
                if (isset($row[0]) && $row[0] != 'Customer Code') {

                    if ($row[0] == '' && $row[1] == '' && $row[3] == '' && $row[4] == '' && $row[5] == '' && $row[6] == '') {
                        continue;
                    }

                    if ($row[0] == '') {
                        $errors[] = "Customer Code is not added.";
                    }

                    if ($row[1] == '') {
                        $errors[] = "Company Code is not added.";
                    }

                    // if ($row[2] == '') {
                    //     $errors[] = "Customer LPO is not added.";
                    // }

                    if ($row[3] == '') {
                        $errors[] = "Order Request Date is not added.";
                    }

                    if ($row[4] == '') {
                        $errors[] = "Item Code is not added.";
                    }

                    if ($row[5] == '') {
                        $errors[] = "Item Uom is not added.";
                    }

                    if ($row[6] == '') {
                        $errors[] = "Qty is not added.";
                    }

                    if (count($errors) > 0) {
                        return prepareResult(false, [], $errors, "Order not imported", $this->unprocessableEntity);
                    }

                    // if item qty is 0 then break loop and continue
                    if ($row[6] == 0) {
                        continue;
                    }

                    $customerInfo = CustomerInfo::where('customer_code', 'like', "%$row[0]%")->first();
                    $Customerlob = Lob::where('lob_code', 'like', "%$row[1]%")->first();
                    $item = Item::where('item_code', $row[4])->first();
                    //$itemUom = ItemUom::where('name', $row[6])->first();

                    $item_code_array = array();
                    $order_error = array();
                    $lob_code_array = array();
                    $item_uom_array = array();

                    if (!$customerInfo) {
                        if (!in_array($row[0], $order_error)) {
                            $order_error = $row[0];
                            $errors[] = "Customer Code does not exist " . $row[0];
                        }
                    }else{
                        if($customerInfo->status == "0")
                        {
                            $errors[] = "Customer ". $row[0] . " is inactive.You can not book the order. ";
                        }
                    }

                    if (!$Customerlob) {
                        if (!in_array($row[1], $errors)) {
                            $lob_code_array[] = $row[1];
                            $errors[] = "Company/Lob does not exist " . $row[1];
                        }
                    }

                    if (!$item) {
                        if (!in_array($row[4], $item_code_array)) {
                            $item_code_array[] = $row[4];
                            $errors[] = "Item code does not exist " . $row[4];
                        }
                    }

                    $delivery_date = Carbon::createFromFormat('d/m/Y', $row[3])->format('Y-m-d');
                    // $delivery_date = date("Y-m-d", strtotime($row[3]));

                    if ($row[7] == 1) {
                        $order = new Order;
                        $order_exist = "";
                    } else {
                        $order_exist = Order::where('delivery_date', $delivery_date)
                            ->where('approval_status', '!=', "Cancelled")
                            ->where('customer_id', $customerInfo->user_id)
                            ->orderBy('id', 'desc')
                            ->first();
                    }

                    if (count($errors) <= 0) {
                        $orderid;
                        //-----------
                        $status = 1;
                        $current_stage = 'Approved';
                        $current_organisation_id = request()->user()->organisation_id;
                        if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                            $status = 0;
                            $current_stage = 'Pending';
                            //$this->createWorkFlowObject($isActivate, 'Order);
                        }

                        $order = new Order();

                        $deldate = Carbon::createFromFormat('d/m/Y', $row[3])->format('Y-m-d');

                        // get Cusotmer lob
                        $getpaymenttermid = CustomerLob::select('payment_term_id')
                            ->where('lob_id', $Customerlob->id)
                            ->where('customer_info_id', $customerInfo->user_id)
                            ->first();

                        $duedate = $deldate;
                        // payment term set
                        if (is_object($getpaymenttermid)) {
                            $payment_term = PaymentTerm::select('number_of_days')
                                ->where('id', $getpaymenttermid->payment_term_id)
                                ->first();
                            if ($payment_term->number_of_days != '0') {
                                $duedate = Carbon::parse($deldate)->addDays($payment_term->number_of_days);
                            } else {
                                $duedate = $deldate;
                            }
                        }

                        // if customer branch plant empty
                        $getstorgeloctionid = CustomerWarehouseMapping::select('storage_location_id')
                            ->where('customer_id', $customerInfo->user_id)
                            ->first();

                        $getstoreloc = "";

                        if ($getstorgeloctionid) {
                            $getstoreloc = $getstorgeloctionid->storage_location_id;
                        }

                        if (is_object($order_exist)) {
                            $order->order_number = $order_exist->order_number;
                            $orderid = $order_exist->id;
                        } else {
                            $t_qty = 0;
                            $variable = "order";
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
                                $order_number = $nextComingNumber['number_is'];
                            } else {
                                $order_number = "0000001";
                            }

                            $order->order_number = nextComingNumber('App\Model\Order', 'order', 'order_number', $order_number);
                            $order->customer_id = (!empty($customerInfo->user_id)) ? $customerInfo->user_id : null;
                            $order->depot_id = null;
                            $order->order_type_id = 2;
                            $order->order_date = date('Y-m-d');
                            $order->delivery_date = $deldate;
                            $order->salesman_id = null;
                            $order->route_id = null;
                            $order->reason_id = null;
                            $order->customer_lop = (!empty($row[2])) ? $row[2] : null;
                            $order->payment_term_id = $customerInfo->payment_term_id;
                            $order->due_date = $duedate;
                            $order->total_qty = 0;
                            $order->total_gross = 0;
                            $order->total_discount_amount = 0;
                            $order->total_net = 0;
                            $order->total_vat = 0;
                            $order->total_excise = 0;
                            $order->grand_total = 0;
                            $order->any_comment = 0;
                            $order->source = 6;
                            $order->status = $status;
                            $order->current_stage = $current_stage;
                            $order->current_stage_comment = '';
                            $order->approval_status = "Created";
                            $order->is_presale_order = 1;
                            if ($getstoreloc != "") {
                                $order->warehouse_id = getWarehuseBasedOnStorageLoacation($getstoreloc, false);
                                $order->storage_location_id = $getstoreloc;
                            }
                            $order->lob_id = (!empty($Customerlob->id)) ? $Customerlob->id : null;
                            $order->order_created_user_id = request()->user()->id;
                            $order->is_user_updated = 0;
                            $order->user_updated = null;
                            $order->module_updated = NULL;
                            $order->save();

                            $data = [
                                'created_user' => request()->user()->id,
                                'order_id' => $order->id,
                                'delviery_id' => NULL,
                                'updated_user' => NULL,
                                'previous_request_body' => NULL,
                                'request_body' => $order,
                                'action' => 'Order Import',
                                'status' => 'Created',
                            ];

                            saveOrderDeliveryLog($data);

                            if ($isActivate = checkWorkFlowRule('Order', 'create', $current_organisation_id)) {
                                $this->createWorkFlowObject($isActivate, 'Order', $request, $order);
                            }

                            $orderid = $order->id;
                            updateNextComingNumber('App\Model\Order', 'order');
                        }

                        //-----Details

                        // find item
                        $getitemid = Item::select('id', 'lower_unit_uom_id')->where('item_code', 'like', $row[4])->first();
                        // find item uom
                        $item_uom_id = $this->getItemUomID($getitemid, $row[5]);

                        //----------------
                        $stdObject = new stdClass();
                        $stdObject->item_id = $getitemid->id;
                        $stdObject->customer_id = $customerInfo->id;
                        $stdObject->item_qty = $row[6];
                        $stdObject->item_uom_id = $item_uom_id;
                        $stdObject->lob_id = $Customerlob->id;
                        $stdObject->delivery_date = $deldate;

                        $item_apply = item_apply_price($stdObject);

                        $item_apply_import = (array) $item_apply;
                        $orderDetail = new OrderDetail;
                        $orderDetail->order_id = $orderid;
                        $orderDetail->item_id = (is_object($getitemid)) ? $getitemid->id : 1;
                        $orderDetail->item_uom_id = $item_uom_id;
                        $orderDetail->original_item_uom_id = $item_uom_id;
                        $orderDetail->discount_id = (isset($item_apply_import['discount_id']) ? $item_apply_import['discount_id'] : 0);
                        $orderDetail->is_free = (isset($item_apply_import['is_free']) ? $item_apply_import['is_free'] : 0);
                        $orderDetail->is_item_poi = (isset($item_apply_import['is_item_poi']) ? $item_apply_import['is_item_poi'] : 0);
                        $orderDetail->promotion_id = (isset($item_apply_import['promotion_id']) ? $item_apply_import['promotion_id'] : 0);
                        $orderDetail->reason_id = null;
                        $orderDetail->is_deleted = 0;
                        $orderDetail->item_qty = $row[6];
                        $orderDetail->item_weight = 0;
                        $orderDetail->item_price = (!empty($item_apply_import['item_price'])) ? (float) str_replace(',', '', $item_apply_import['item_price']) : 0;
                        $orderDetail->item_gross = (!empty($item_apply_import['item_gross'])) ? (float) str_replace(',', '', $item_apply_import['item_gross']) : 0;
                        $orderDetail->item_discount_amount = (!empty($item_apply_import['discount_id'])) ? $item_apply_import['discount_id'] : 0;
                        $orderDetail->item_net = (!empty($item_apply_import['total_net'])) ? (float) str_replace(',', '', $item_apply_import['total_net']) : 0;
                        $orderDetail->item_vat = (!empty($item_apply_import['total_vat'])) ? (float) str_replace(',', '', $item_apply_import['total_vat']) : 0;
                        $orderDetail->item_excise = (!empty($item_apply_import['total_excise'])) ? (float) str_replace(',', '', $item_apply_import['total_excise']) : 0;
                        $orderDetail->item_grand_total = (!empty($item_apply_import['total'])) ? (float) str_replace(',', '', $item_apply_import['total']) : 0;
                        $orderDetail->original_item_qty = (!empty($row[6])) ? $row[6] : 0;
                        $orderDetail->original_item_price = (!empty($item_apply_import['item_price'])) ? (float) str_replace(',', '', $item_apply_import['item_price']) : 0;

                        $orderDetail->save();
                        if ($orderDetail->item_price > 0) {
                            $data = [
                                'created_user' => request()->user()->id,
                                'order_id' => $orderDetail->id,
                                'delviery_id' => NULL,
                                'updated_user' => NULL,
                                'previous_request_body' => NULL,
                                'request_body' => $orderDetail,
                                'action' => 'Order Detail Import',
                                'status' => 'Created',
                            ];

                            saveOrderDeliveryLog($data);
                        }

                        $getItemQtyByUom = qtyConversion(
                            $orderDetail->item_id,
                            $orderDetail->item_uom_id,
                            $row[6]
                        );

                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];

                        $net_excise = (!empty($item_apply_import['net_excise'])) ? (float) str_replace(',', '', $item_apply_import['net_excise']) : 0;

                        $ordergrosssum = OrderDetail::where('order_id', '=', $orderid)->sum('item_gross');
                        $ordervatum = OrderDetail::where('order_id', '=', $orderid)->sum('item_vat');
                        // $orderitemexicise = OrderDetail::where('order_id', '=', $orderid)->sum('item_excise');
                        $ordergtotal = OrderDetail::where('order_id', '=', $orderid)->sum('item_grand_total');
                        $ordernettotal = OrderDetail::where('order_id', '=', $orderid)->sum('item_net');

                        if ($ordergrosssum) {
                            $order_update = Order::find($orderid);
                            $order_update->total_gross = $ordergrosssum;
                            $order_update->total_vat = $ordervatum;
                            $order_update->total_excise = $order_update->total_excise + ($net_excise * $getItemQtyByUom['Qty']);
                            $order_update->grand_total = $ordergtotal;
                            $order_update->total_net = $ordernettotal;
                            $order_update->total_qty = $t_qty;
                            $order_update->save();
                        }
                        $oldlpo = $row[2];
                    }
                }
            }

            if (count($errors)) {
                return prepareResult(false, [], $errors, "Order not imported", $this->unprocessableEntity);
            } else {
                return prepareResult(true, [], [], "Order imported", $this->success);
            }
        }
    }

    private function getItemUomID($item, $uom)
    {
        $uom = ItemUom::where('name', $uom)->first();

        if ($item->lower_unit_uom_id == $uom->id) {
            return $uom->id;
        }

        $itemMainPrice = ItemMainPrice::where('item_id', $item->id)
            ->where('item_uom_id', $uom->id)
            ->first();

        if ($itemMainPrice) {
            return $uom->id;
        }

        $itemMainPrice = ItemMainPrice::where('item_id', $item->id)
            ->where('is_secondary', 1)
            ->first();

        if ($itemMainPrice) {
            return $itemMainPrice->item_uom_id;
        }
    }

    public function smallItemApplyPrice(Request $request)
    {
        $customer_id = $request->customer_id;
        $item_id = $request->item_id;
        $item_uom_id = $request->item_uom_id;
        $qty = $request->item_qty;

        $item = smallItemApplyPrice($customer_id, $item_id, $item_uom_id, $qty);
        if (isset($item['status']) && !$item['status']) {
            return prepareResult(false, [], ['error' => $item['error']], $item['error'], 422);
        }

        return prepareResult(true, $item['item'], [], "Item price.", 201);
    }

    public function updateDelivery($order)
    {
        $delivery = Delivery::where('order_id', $order->id)->first();

        if ($delivery) {
            $previous = $delivery;
            $delivery_number = $delivery->delivery_number;

            $delivery->customer_id = $order->customer_id;
            $delivery->delivery_number = $delivery_number;
            $delivery->order_id = $order->id;
            $delivery->delivery_date = $order->delivery_date;
            $delivery->storage_location_id = (!empty($order->storage_location_id)) ? $order->storage_location_id : 0;
            $delivery->warehouse_id = getWarehuseBasedOnStorageLoacation($order->storage_location_id, false);
            $delivery->delivery_type_source = $order->delivery_type_source;
            $delivery->delivery_due_date = $order->delivery_due_date;
            $delivery->delivery_weight = $order->delivery_weight;
            $delivery->payment_term_id = $order->payment_term_id;
            $delivery->total_qty = $order->total_qty;
            $delivery->total_gross = $order->total_gross;
            $delivery->total_discount_amount = $order->total_discount_amount;
            $delivery->total_net = $order->total_net;
            $delivery->total_vat = $order->total_vat;
            $delivery->total_excise = $order->total_excise;
            $delivery->grand_total = $order->grand_total;
            $delivery->lob_id = (!empty($order->lob_id)) ? $order->lob_id : null;
            $delivery->is_user_updated = 1;
            $delivery->user_updated = request()->user()->id;
            $delivery->module_updated = "Order To Delivery Update";
            $delivery->save();

            $data = [
                'created_user' => request()->user()->id,
                'order_id' => $delivery->order_id,
                'delviery_id' => $delivery->id,
                'updated_user' => request()->user()->id,
                'previous_request_body' => $previous,
                'request_body' => $delivery,
                'action' => 'Order To Delivery Update',
                'status' => 'Updated',
            ];

            saveOrderDeliveryLog($data);

            DeliveryDetail::where('delivery_id', $delivery->id)->delete();

            if (count($order->orderDetails)) {

                rfGenView::where('Order_Number', $order->order_number)->delete();

                foreach ($order->orderDetails as $details) {

                    $deliveryDetail = DeliveryDetail::where('uuid', $details->uuid)->first();
                    if (!$deliveryDetail) {
                        $deliveryDetail = new DeliveryDetail;
                    }

                    $prevous_body = $deliveryDetail;

                    $deliveryDetail->uuid = $details->uuid;
                    $deliveryDetail->delivery_id = $delivery->id;
                    $deliveryDetail->item_id = $details->item_id;
                    $deliveryDetail->item_uom_id = $details->item_uom_id;
                    $deliveryDetail->discount_id = $details->discount_id;
                    $deliveryDetail->is_free = $details->is_free;
                    $deliveryDetail->is_item_poi = $details->is_item_poi;
                    $deliveryDetail->promotion_id = $details->promotion_id;
                    $deliveryDetail->item_qty = $details->item_qty;
                    $deliveryDetail->item_price = $details->item_price;
                    $deliveryDetail->item_gross = $details->item_gross;
                    $deliveryDetail->item_discount_amount = $details->item_discount_amount;
                    $deliveryDetail->item_net = $details->item_net;
                    $deliveryDetail->item_vat = $details->item_vat;
                    $deliveryDetail->item_excise = $details->item_excise;
                    $deliveryDetail->item_grand_total = $details->item_grand_total;
                    $deliveryDetail->original_item_uom_id = $details->item_uom_id;
                    $deliveryDetail->transportation_status = "No";
                    $deliveryDetail->original_item_qty = $details->original_item_qty;
                    $deliveryDetail->reason_id = $details->reason_id;
                    $deliveryDetail->is_deleted = $details->is_deleted;
                    $deliveryDetail->save();
                    // $deliveryDetail->original_item_price    = $details->item_price; // this column is not exist in dleiveyr detail table
                    // $deliveryDetail->original_item_id       = $details->item_id;

                    $data = [
                        'created_user' => request()->user()->id,
                        'order_id' => $details->id,
                        'delviery_id' => $deliveryDetail->id,
                        'updated_user' => request()->user()->id,
                        'previous_request_body' => $prevous_body,
                        'request_body' => $deliveryDetail,
                        'action' => 'Order To Delivery Deail Update',
                        'status' => 'Updated',
                    ];

                    saveOrderDeliveryLog($data);

                    DeliveryDetail::where('id', $deliveryDetail->id)
                        ->update([
                            'uuid' => $details->uuid
                        ]);

                    $this->saverfDGen($deliveryDetail, $order);
                }
            }
        }
    }

    public function orderLPOCheck(Request $request)
    {
        $oc = Order::where('customer_lop', $request->customer_lop)
            ->where('approval_status', '!=', 'Cancelled')
            ->where('customer_id', $request->customer_id)
            ->first();

        if ($oc) {
            return prepareResult(false, [], ["error" => "Customer LPO already attached with the cusotmer."], "Customer LPO already attached with the cusotmer.", $this->unprocessableEntity);
        }
    }

    private function updateRFGenView($orderDetail)
    {
        $rf_view = rfGenView::where('order_detail_id', $orderDetail->id)->first();
        if ($rf_view) {
            $rf_view->DemandPUOM = ($orderDetail->item_uom_id == model($orderDetail->item, 'lower_unit_uom_id')) ? $orderDetail->item_qty : 0;
            $rf_view->DemandSUOM = ($orderDetail->item_uom_id != model($orderDetail->item, 'lower_unit_uom_id')) ? conevertQtyForRFGen($orderDetail->item_id, $orderDetail->item_qty, $orderDetail->item_uom_id, true) : 0;
            $rf_view->save();
        }
    }

    /**
     * This function is update the qty of the rfgen
     *
     * @return void
     */
    public function saverfDGen($deliveryDetail, $order)
    {
        $od = OrderDetail::where('uuid', $deliveryDetail->uuid)->first();

        $rf_gen = new rfGenView();
        $rf_gen->GLDate = Carbon::parse($order->delivery_date)->format('Y-m-d');
        $rf_gen->item_id = $deliveryDetail->item_id;
        $rf_gen->ITM_CODE = model($deliveryDetail->item, 'item_code');
        $rf_gen->ITM_NAME = model($deliveryDetail->item, 'item_name');
        $rf_gen->TranDate = model($order, 'order_date');
        $rf_gen->Order_Number = model($order, 'order_number');
        $rf_gen->LOAD_NUMBER = $deliveryDetail->delivery_id;
        $rf_gen->MCU_CODE = model($order->storageocation, 'code');
        $rf_gen->DemandPUOM = ($deliveryDetail->item_uom_id == model($deliveryDetail->item, 'lower_unit_uom_id')) ? $deliveryDetail->item_qty : 0;
        $rf_gen->DemandSUOM = ($deliveryDetail->item_uom_id != model($deliveryDetail->item, 'lower_unit_uom_id')) ? conevertQtyForRFGen($deliveryDetail->item_id, $deliveryDetail->item_qty, $deliveryDetail->item_uom_id, true) : 0;
        $rf_gen->mobiato_order_picked = 0;
        $rf_gen->order_detail_id = ($od) ? $od->id : $deliveryDetail->id;
        $rf_gen->REM_QTY = 0;
        $rf_gen->RTE_CODE = "MT1";
        $rf_gen->RFDate = $order->delivery_date;
        if ($deliveryDetail->item_qty > 0) {
            $rf_gen->save();
        }

    }

    public function testRoutific()
    {
        $orders = Order::orderBy('id', 'desc')->take(10)->get();
        $vans = Van::orderBy('id', 'desc')->take(10)->get();
        $path = 'routific.json';
        $content = json_decode(file_get_contents($path), true);
        $request_headers = [
            'Content-Type:application/json',
            'Authorization:bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJfaWQiOiI1MzEzZDZiYTNiMDBkMzA4MDA2ZTliOGEiLCJpYXQiOjEzOTM4MDkwODJ9.PR5qTHsqPogeIIe0NyH2oheaGR-SJXDsxPTcUQNq90E'
        ];
        $url = 'http://api.routific.com/v1/vrp';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content));
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        //dd($response);
        return $response;
    }

}
