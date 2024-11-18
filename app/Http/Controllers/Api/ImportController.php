<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Brand;
use App\Model\BrandChannel;
use App\Model\DeliveryDetail;
use App\Model\Channel;
use App\Model\SalesmanInfo;
use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\CustomerInfo;
use App\Model\CustomerKamMapping;
use App\Model\CustomerLob;
use App\Model\CustomerRegion;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\DeliveryNote;
use App\Model\Distribution;
use App\Model\DistributionCustomer;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\Goodreceiptnote;
use App\Model\Goodreceiptnotedetail;
use App\Model\Invoice;
use App\Model\Item;
use App\Model\InvoiceDetail;
use App\Model\ItemMainPrice;
use App\Model\ItemMajorCategory;
use App\Model\ItemUom;
use App\Model\JourneyPlanCustomer;
use App\Model\LoadItem;
use App\Model\OdoMeter;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrderView;
use App\Model\PDPCustomer;
use App\Model\PDPItem;
use App\Model\PickingSlipGenerator;
use App\Model\PriceDiscoPromoPlan;
use App\Model\rfGenView;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\SalesmanUnload;
use App\Model\SalesmanUnloadDetail;
use App\Model\SalesmanVehicle;
use App\Model\SalesVsGrv;
use App\Model\UserChannel;
use App\Model\OrderReport;
use App\Model\OrderReportClone;
use App\Model\UserChannelAttached;
use App\Model\VehicleUtilisation;
use App\Model\ReasonType;
use App\Model\SpotReport;
use App\User;
use App\Model\Van;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function customerGeoImport()
    {
        $fileName = $_FILES["customer"]["tmp_name"];

        if ($_FILES["customer"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($column[1] == "Longitude") {
                    continue;
                }

                $customerInfo = CustomerInfo::where('customer_code', $column[0])->first();
                if ($customerInfo) {
                    $customerInfo->customer_address_1_lat   = $column[1];
                    $customerInfo->customer_address_1_lang  = $column[2];
                    $customerInfo->customer_address_2_lat   = $column[1];
                    $customerInfo->customer_address_2_lang  = $column[2];
                    $customerInfo->save();
                }
            }
        }
        return "done";
    }

    public function orderSpotReportClone()
    {
    
       
        $date = "2023-12-14";
        //$edate = "2023-03-22";
        $ischange = '0';
        $order_query = Order::query('*');
        //$order_query->where('order_number', 'like', "%10193634%");
        //$order_query->whereBetween('delivery_date', [$date, $edate]);
        $order_query->where('delivery_date',$date);
        //$order_query->where('change_date',$date);
        $order_query->where('is_presale_order', 1);
        $order_query->where('current_stage', '!=', "Rejected");
        $orderHeader = $order_query->orderBy('order_date', 'desc')
        ->orderBy('order_number', 'desc')
        ->get();

        if (count($orderHeader)) {
            foreach ($orderHeader as $k => $detail) {
                $line_numbber = 0;
                foreach ($detail->orderDetails as $key => $order_detail) {

                    $qty_cancelled = "0";
                    $quantity_invoice = '0';
                    $invoice_number = "";
                    $invoice_date = "";
                    $load_date  = "";
                    $reason = '';
                    $code = '';
                    $quantity_delivery = '0';
                    $quantity_shipment = '0';
                    $cancel_date = '';
                    $delivery_note = '';
                    $salesman_code = "";
                    $salesman_name = "";
                    $driverreason = '';
                    $onHold = 'No';
                    $trip = "0";
                    $van = "";
                    $helper1 = "";
                    $helper2 = "";
                    $storagelocation = "";
                    // ship - invoice
                    $quantity_invoice_vs_shipped = '0';

                    if ($detail->approval_status == 'Cancelled') {
                        $qty_cancelled = $order_detail->original_item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    } else if ($order_detail->is_deleted == 1) {
                        $qty_cancelled = $order_detail->original_item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    } else if ($order_detail->reason_id && $order_detail->is_deleted != 1) {
                        $qty_cancelled = $order_detail->original_item_qty - $order_detail->item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    }

                    $line_numbber = $line_numbber + 1;
                    $extended_amount    = ($order_detail->reason) ? '0' : $order_detail->item_gross;
                    // $Invoice_date = ($Invoice_query ? $Invoice_query->load_date : '');
                    $business_unit      = ($detail->storageocation) ? $detail->storageocation->code : '';
                    $storagelocation = ($detail->storageocation) ? $detail->storageocation->id : '';
                    // ship qty means load qty
                    // invoice qty

                    $delivery = Delivery::where('order_id', $detail->id)->first();

                    // 1: find in order detail
                    if (is_object($order_detail->reason)) {
                        $reason = ($order_detail->reason) ? $order_detail->reason->name : NULL;
                        if ($code == "") {
                            $code = ($order_detail->reason) ? $order_detail->reason->code : NULL;
                        }
                    }

                    if ($detail->approval_status == 'Cancelled') {
                        if ($code == "") {
                            $code = model($detail->reason, 'code');
                        }
                    }
                   

                    if ($delivery) {
                        $salesmanLoad_query = SalesmanLoadDetails::select('load_date')
                            ->whereHas('salesmanLoad', function ($q) use ($delivery) {
                                $q->where('delivery_id', $delivery->id);
                            })
                            ->where('item_id', $order_detail->item_id)
                            ->first();

                        $load_date  = ($salesmanLoad_query) ? Carbon::parse($salesmanLoad_query->load_date)->format('d-m-Y') : '';

                        // 2: find in delivery detail
                        $dds = DeliveryDetail::where('delivery_id', $delivery->id)
                            ->where('uuid', $order_detail->uuid)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        $deliver_detail_id = '';
                        if ($dds) {
                            $deliver_detail_id = $dds->id;
                            if ($dds->reason_id) {
                                $reason = ($dds->reason) ? $dds->reason->name : NULL;
                                if ($code == "") {
                                    $code = ($dds->reason) ? $dds->reason->code : NULL;
                                }
                            }

                            if ($dds->original_item_qty !== $dds->item_qty) {
                                $qty_cancelled = $dds->original_item_qty - $dds->item_qty;
                            }

                            if ($delivery->approval_status == 'Shipment' || $delivery->approval_status == 'Completed') {
                                if ($dds->item_qty > 0 && $dds->item_price > 0) {
                                    $quantity_shipment = $dds->item_qty;
                                }
                            }

                            if ($dds->is_deleted == 1) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }
                            // delivery Details cancel qty > 0

                            if ($dds->cancel_qty > 0) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }

                            if ($dds->item_qty != $dds->original_item_qty) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }
                        }

                        $delivery_note = DeliveryNote::where('delivery_id', $delivery->id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        // 3: find in delivery detail
                        if ($delivery_note) {
                            if ($delivery_note->reason_id != '' || $delivery_note->is_cancel > 0) {
                                $reason = ($delivery_note->reason) ? $delivery_note->reason->name : NULL;
                                $driverreason = model($delivery_note->reason, 'code');
                            }
                        }
                    }

                    $invoice = Invoice::where('order_id', $detail->id)->first();

                    // order cancel
                    if ($detail->approval_status == "Cancelled") {
                        $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                    }

                    // delivery cancel
                    if (is_object($delivery) && $delivery->approval_status == "Cancel") {
                        $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                        if ($code == "") {
                            $code = model($delivery->reason, 'code');
                        }
                    }

                    if (is_object($delivery)) {
                        if ($delivery->change_date != null || $delivery->change_date != "") {
                            $onHold = "Yes";
                            if($detail->hold_reason)
                            {
                                $rr = ReasonType::where('id', $detail->hold_reason)->first();
                                //$hReason = ReasonType::where('id', $detail->hold_reason)->first();
                                $driverreason = $rr->code;
                            }else{
                                $driverreason = "RBC";
                            }

                            if($ischange == '1')
                            {
                                $onHold = "No";
                                if($delivery->approval_status == "Cancel")
                                {
                                    $driverreason =  $code;
                                }else{
                                    $driverreason = '';
                                }
                                
                            }

                        } else {
                            $onHold = "No";
                        }
                    }

                    // last delivery note qty
                    if (is_object($delivery_note)) {
                        if ($cancel_date == "") {
                            $cancel_date = ($delivery_note->is_cancel == 1) ? Carbon::parse($detail->updated_at)->format('d-m-Y') : "";
                        }
                    }

                    if ($invoice && $delivery) {
                            
                        $delivery_d = DeliveryDetail::where('delivery_id', $delivery->id)
                            ->where('uuid', $order_detail->uuid)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();
                        
                            
                        if ($delivery_d) {
                            
                            $quantity_delivery = $delivery_d->item_qty;
                            if ($delivery->approval_status == 'Shipment' || $delivery->approval_status == 'Completed') {
                                if ($delivery_d->item_qty > 0 && $delivery_d->item_price > 0) {
                                    $quantity_shipment = $delivery_d->item_qty;
                                }
                            }

                            $dn = DeliveryNote::where('delivery_id', $delivery->id)
                                ->where('delivery_detail_id', $deliver_detail_id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->get();

                            if (count($dn)) {
                                $qty = $dn->pluck('qty')->toArray();
                                $qty_cancelled = $qty_cancelled + ($delivery_d->item_qty - array_sum($qty));
                            }

                            $dassign = DeliveryAssignTemplate::where('delivery_id', $delivery->id)
                            ->where('delivery_details_id', $delivery_d->id)
                            ->first();
                            
                            if($dassign)
                            {
                                $trip = $dassign->trip;

                                if ($dassign->delivery_driver_id != null || $dassign->delivery_driver_id != "") {
                                    $mSalesman = SalesmanInfo::where('user_id', $dassign->delivery_driver_id)->first();
                                    $salesman_code = $mSalesman->salesman_code;
                                    $mSalesUser = User::find($dassign->delivery_driver_id);
                                    $salesman_name = $mSalesUser->firstname . ' ' . $mSalesUser->lastname;

                                    $dodo = SalesmanVehicle::where('salesman_id', $dassign->delivery_driver_id)
                                    ->where('date', $detail->delivery_date)
                                    ->first();

                                    if($dodo)
                                    {
                                        $mVan = Van::where('id', $dodo->van_id)->first();
                                        if($mVan)
                                        {
                                            $van = $mVan->van_code;
                                        }

                                        if($dodo->helper1_id)
                                        {
                                            $mH1Salesman = SalesmanInfo::where('user_id', $dodo->helper1_id)->first();
                                            $helper1 = $mH1Salesman->salesman_code;
                                        }

                                        if($dodo->helper2_id)
                                        {
                                            $mH2Salesman = SalesmanInfo::where('user_id', $dodo->helper2_id)->first();
                                            $helper2 = $mH2Salesman->salesman_code;
                                        }
                                    }
                                }
                            }
                        }

                        $invoice_number = $invoice->invoice_number;
                        $invoice_date   = $invoice->invoice_date;


                        $quantity_invoice = '0';
                        $quantity_invoice_vs_shipped = '0';

                        $invoice_qty = DeliveryNote::select(
                            DB::raw("SUM(qty) AS invoices")
                        )
                            ->where('delivery_id', $delivery->id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->where('is_cancel', 0)
                            ->first();
                            
                        if ($invoice_qty->invoices != '') {
                            $quantity_invoice = $invoice_qty->invoices;
                            
                            $invoices_qty = InvoiceDetail::select(
                                DB::raw("SUM(item_qty) AS invoices")
                            )
                                ->where('invoice_id', $invoice->id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->where('delv_id', $deliver_detail_id)
                                ->first();

                                if($invoices_qty->invoices != '')
                                {
                                   
                                    $quantity_invoice = $invoices_qty->invoices;
                                    if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                        $invoice_number = '';
                                        $invoice_date = '';
                                    }
                                }else{
                                    $invoices_qty = InvoiceDetail::select(
                                        DB::raw("SUM(item_qty) AS invoices")
                                    )
                                        ->where('invoice_id', $invoice->id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->first();

                                         // if delivery item_qty more then invoice qty
                                if ($delivery_d) {
                                    if ($delivery_d->item_qty > $quantity_invoice) {

                                        $invoices_qty = InvoiceDetail::select(
                                            DB::raw("SUM(item_qty) AS invoices")
                                        )
                                            ->where('invoice_id', $invoice->id)
                                            ->where('item_id', $order_detail->item_id)
                                            ->where('item_uom_id', $order_detail->item_uom_id)
                                            ->first();
                                        $quantityFinvoice = $invoices_qty->invoices;
                                        if($delivery_d->item_qty > $quantityFinvoice)
                                        {
                                            $cancel_date = Carbon::parse($delivery_d->updated_at)->format('d-m-Y');
                                        }else{
                                            $quantity_invoice =  $quantityFinvoice;
                                        }
                                        
                                    }

                                    if ($delivery_d->is_deleted == 1) {
                                        $qty_cancelled = $delivery_d->original_item_qty;
                                    }
                                }

                                // if invoice qty less then 0
                                if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                
                                    $invoiceC_qty = DeliveryNote::select(
                                        DB::raw("SUM(qty) AS invoices")
                                    )
                                        ->where('delivery_id', $delivery->id)
                                        ->where('delivery_detail_id', $deliver_detail_id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->where('is_cancel', 1)
                                        ->first();

                                        if($invoiceC_qty)
                                        {
                                        
                                            if($invoiceC_qty->invoices == $quantity_shipment)
                                            {
                                                $quantity_invoice = $quantity_shipment - $invoiceC_qty->invoices;
                                            }else{
                                                $quantity_invoice = $invoiceC_qty->invoices;
                                            }
                                        }else{
                                            $invoices_qty = InvoiceDetail::select(
                                                DB::raw("SUM(item_qty) AS invoices")
                                            )
                                                ->where('invoice_id', $invoice->id)
                                                ->where('item_id', $order_detail->item_id)
                                                ->where('item_uom_id', $order_detail->item_uom_id)
                                                ->first();
                                            $quantity_invoice = $invoices_qty->invoices;
                                            if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                                $invoice_number = '';
                                                $invoice_date = '';
                                            }
                                        }
                                    
                                }
                         }
                           
                        }else{

                           
                            $invoiceC_qty = DeliveryNote::select(
                                DB::raw("SUM(qty) AS invoices")
                            )
                                ->where('delivery_id', $delivery->id)
                                ->where('delivery_detail_id', $deliver_detail_id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->where('is_cancel', 1)
                                ->first();
                               
                               // pre($invoiceC_qty);
                                if($invoiceC_qty)
                                {
                                    if($invoiceC_qty->invoices != $quantity_shipment)
                                    {
                                        $invoices_qty = InvoiceDetail::select(
                                            DB::raw("SUM(item_qty) AS invoices")
                                        )
                                            ->where('invoice_id', $invoice->id)
                                            ->where('item_id', $order_detail->item_id)
                                            ->where('item_uom_id', $order_detail->item_uom_id)
                                            ->where('delv_id', $deliver_detail_id)
                                            ->first();
                                        
                                            // if($invoices_qty->delv_id != '')
                                            // {
                                                $quantity_invoice = $invoices_qty->invoices;
                                                
                                            // }else{
                                            //     $invoicess_qty = InvoiceDetail::select(
                                            //         DB::raw("SUM(item_qty) AS invoices")
                                            //     )
                                            //         ->where('invoice_id', $invoice->id)
                                            //         ->where('item_id', $order_detail->item_id)
                                            //         ->where('item_uom_id', $order_detail->item_uom_id)
                                            //         ->first();

                                            //     $quantity_invoice = $invoicess_qty->invoices;
                                            // }
                                            
                                    }
                                }else{
                                    $invoices_qty = InvoiceDetail::select(
                                        DB::raw("SUM(item_qty) AS invoices")
                                    )
                                        ->where('invoice_id', $invoice->id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->where('delv_id', $deliver_detail_id)
                                        ->first();
                                        
                                        // if($invoices_qty->delv_id != '')
                                        // {
                                            $quantity_invoice = $invoices_qty->invoices;
                                        // }else{
                                        //     $invoicess_qty = InvoiceDetail::select(
                                        //         DB::raw("SUM(item_qty) AS invoices")
                                        //     )
                                        //         ->where('invoice_id', $invoice->id)
                                        //         ->where('item_id', $order_detail->item_id)
                                        //         ->where('item_uom_id', $order_detail->item_uom_id)
                                        //         ->first();
                                        //     $quantity_invoice = $invoicess_qty->invoices;
                                        // }
                                        
                                }
                            
                            
                        }

                        $cancel_qty = DeliveryNote::select(
                            DB::raw("SUM(qty) AS cancel"),
                            'reason_id',
                            'created_at'
                        )
                            ->with('reason')
                            ->where('is_cancel', 1)
                            ->where('delivery_id', $delivery->id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        if ($cancel_qty->cancel && $cancel_qty->cancel > 0 && $cancel_qty->cancel != '') {
                            $qty_cancelled = $cancel_qty->cancel;

                            // if ($code == "") {
                            //     $code = model($cancel_qty->reason, 'code');
                            // }

                            $driverreason = model($cancel_qty->reason, 'code');
                            //pre($driverreason);
                            // if delivery item qty and invoice qty + cancel qty is same then invoice number and date visible
                            // if ($delivery_d->item_qty != ($quantity_invoice + $qty_cancelled)) {
                            //     $invoice_number = '';
                            //     $invoice_date = '';
                            // }

                            if($quantity_invoice == 0)
                            {
                                $invoice_number = '';
                                $invoice_date = '';
                            }

                            if ($qty_cancelled) {
                                if ($cancel_date == "") {
                                    $cancel_date = Carbon::parse($cancel_qty->created_at)->format('d-m-Y');
                                }
                            }
                        }

                        // if (count($deliver_notes)) {
                        //     foreach ($deliver_notes as $deliver_note) {
                        //         // Delivery note cancelled qty
                        //         if ($deliver_note) {
                        //             // invoice qty base on delivery note
                        //             if ($deliver_note->is_cancel != 1) {
                        //                 $qty_cancelled = $qty_cancelled + ($order_detail->item_qty - $quantity_delivery) + ($quantity_delivery - $deliver_note->qty);
                        //                 $quantity_invoice   = $quantity_invoice + $deliver_note->qty;
                        //             } else {
                        //                 $qty_cancelled = $qty_cancelled + $deliver_note->qty;
                        //                 $invoice_number = '';
                        //                 $invoice_date = '';
                        //             }

                        //             $quantity_invoice_vs_shipped = $quantity_invoice_vs_shipped + ($quantity_delivery - $quantity_invoice);

                        //             if ($qty_cancelled > 0) {
                        //                 $cancel_date = Carbon::parse($deliver_note->created_at)->format('d-m-Y');
                        //             }
                        //         }
                        //     }
                        // }
                    } else {
                        if ($delivery) {
                            $delivery_d = DeliveryDetail::where('delivery_id', $delivery->id)
                                ->where('uuid', $order_detail->uuid)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->first();

                            if ($delivery_d) {
                                if ($delivery->approval_status == 'Cancel') {
                                    $quantity_delivery  = $delivery_d->item_qty;
                                    $quantity_shipment  = $delivery_d->item_qty;
                                    $quantity_invoice_vs_shipped    = $quantity_invoice_vs_shipped + ($quantity_delivery - 0);
                                }

                            $dassign = DeliveryAssignTemplate::where('delivery_id', $delivery->id)
                            ->where('delivery_details_id', $delivery_d->id)
                            ->first();

                            if($dassign)
                            {
                                $trip = $dassign->trip;
                                if ($dassign->delivery_driver_id != null || $dassign->delivery_driver_id != "") {
                                    $mSalesman = SalesmanInfo::where('user_id', $dassign->delivery_driver_id)->first();
                                    $salesman_code = $mSalesman->salesman_code;
                                    $mSalesUser = User::find($dassign->delivery_driver_id);
                                    $salesman_name = $mSalesUser->firstname . ' ' . $mSalesUser->lastname;

                                    $dodo = SalesmanVehicle::where('salesman_id', $dassign->delivery_driver_id)
                                    ->where('date', $detail->delivery_date)
                                    ->first();

                                    if($dodo)
                                    {
                                        $mVan = Van::where('id', $dodo->van_id)->first();
                                        if($mVan)
                                        {
                                            $van = $mVan->van_code;
                                        }

                                        if($dodo->helper1_id)
                                        {
                                            $mH1Salesman = SalesmanInfo::where('user_id', $dodo->helper1_id)->first();
                                            $helper1 = $mH1Salesman->salesman_code;
                                        }

                                        if($dodo->helper2_id)
                                        {
                                            $mH2Salesman = SalesmanInfo::where('user_id', $dodo->helper2_id)->first();
                                            $helper2 = $mH2Salesman->salesman_code;
                                        }
                                    }
                                }
                            }
                            }
                            
                        }
                    }

                    $total_cancel = $qty_cancelled * $order_detail->original_item_price;
                    $total_cancel_aed = ($total_cancel ? $total_cancel : '0');

                    $pg = PickingSlipGenerator::where('order_id', $detail->id)->first();

                    $load = SalesmanLoad::where('order_id', $detail->id)->first();

                    if (!$load && !$pg) {
                        if ($detail->approval_status == "Cancelled") {
                            $quantity_shipment = "0";
                        }
                    }

                    if($quantity_shipment > 0)
                    {
                        
                    }else{
                        if($ischange == '1')
                        {
                            $driverreason = '';
                        }
                    }
                    
                    if($code == $driverreason)
                    {
                        $code = "";  
                    }

                    if ($order_detail->is_deleted === 1 || $quantity_shipment < 1) {
                        $invoice_number = "";
                    }

                    $shipVSInv = ((($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') !== 0) ? (($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') : '0';
                    $unit_price = ($order_detail->original_item_price > 0) ? $order_detail->original_item_price : $order_detail->item_price;
                    $total_amtItem = ($order_detail->item_grand_total > 0) ? $order_detail->item_grand_total : $order_detail->item_grand_total;
                    if($unit_price)
                    {
                        $totalUnitPrice = ($unit_price + $order_detail->item_excise);
                    }else{
                        $unit_price = '0.0';
                        $totalUnitPrice = ($unit_price + $order_detail->item_excise);
                    }
                    
                    $vu = new OrderReportClone();
                    $vu->organisation_id     = '1';
                    $vu->order_no      = $detail->order_number;
                    $vu->customer_code    = model($detail->customerInfo, 'customer_code');
                    $vu->customer_name     = model($detail->customer, 'firstname') . ' ' . model($detail->customer, 'lastname');
                    $vu->item_code      = model($order_detail->item, 'item_code');
                    $vu->item_name      = model($order_detail->item, 'item_name');
                    $vu->item_uom      = model($order_detail->itemUom, 'name');
                    
                    
                    $orQty = ($detail->approval_status == "Cancelled") ? $order_detail->original_item_qty : (($order_detail->item_qty == "0.00") ? $order_detail->original_item_qty : $order_detail->item_qty);
                    $spQty = ($quantity_shipment > 0) ? $quantity_shipment : '0';
                    $ipQty = ($quantity_invoice > 0) ? $quantity_invoice : '0';
                   
                    if($orQty == $spQty)
                    {
                        $code = '';
                    }
                    $vu->order_qty      = $orQty;
                    $vu->load_qty      = $spQty;
                    $vu->cancel_qty      = ($orQty - $spQty);

                    if($spQty > 0)
                    {
                        $vu->invoice_qty      = $ipQty;
                        $vu->spot_return      = ($spQty - $ipQty);
                        
                    }else{
                        $vu->invoice_qty      = "0";
                        $vu->spot_return      = "0";
                        
                    }
                    
                    
                    $vu->delivery_date      = $detail->delivery_date;
                    $vu->invoice_date      = ($invoice_number !== "") ? $invoice_date : "";
                    $vu->invoice_no      = $invoice_number;
                    $vu->customer_lpo      = $detail->customer_lop;
                    $vu->storage_location_id      = $storagelocation;
                    $vu->branch_plant      = $business_unit;
                    $vu->on_hold      = $onHold;
                    $vu->driver      = $salesman_code;
                    $vu->cancel_reason      = $code;
                    $vu->driver_reason      = $driverreason;
                    $vu->extend_amt      = ($quantity_shipment > 0) ? round($quantity_shipment * $order_detail->item_price, 2) : "0";
                    $vu->trip      = $trip;
                    $vu->vehicle      = $van;
                    $vu->helper1      = $helper1;
                    $vu->helper2      = $helper2;
                    $vu->save();
                }
            }
        }
        
    }

    public function distributionImport(Request $request)
    {

        $fileName = $_FILES["distribution"]["tmp_name"];

        if ($_FILES["distribution"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($row[0] == "Name") {
                    continue;
                }
                $current_organisation_id = 1;

                $customer = CustomerInfo::where('customer_code', $row[7])->first();

                $channel = Channel::where('name', 'like', "%" . $row[12] . "%")->first();

                $customer_ids = array();
                $customer_info_ids = array();
                $customer_lob_ids = array();
                $customer_lob_info_id = array();
                if ($channel) {
                    $customer = CustomerInfo::where('channel_id', $channel->id)->get();
                    if ($customer->count()) {
                        $customer_info_ids = $customer->pluck('id')->toArray();
                        $customer_ids = $customer->pluck('id')->toArray();
                    }
                    if (count($customer_ids)) {
                        $customerlob = CustomerLob::where('channel_id', $channel->id)
                            ->whereNotIn('customer_info_id', $customer_info_ids)
                            ->get();
                        if (count($customerlob)) {
                            $customer_lob_info_id = $customerlob->pluck('customer_info_id')->toArray();
                            $customer_infos = CustomerInfo::whereIn('id', $customer_lob_info_id)->get();
                            if (count($customer_infos)) {
                                $customer_lob_ids = $customer_infos->pluck('user_id')->toArray();
                            }
                        }
                    } else {
                        $customerlob = CustomerLob::where('channel_id', $channel->id)->get();
                        if (count($customerlob)) {
                            $customer_lob_info_id = $customerlob->pluck('customer_info_id')->toArray();
                            $customer_infos = CustomerInfo::whereIn('id', $customer_lob_info_id)->get();
                            if (count($customer_infos)) {
                                $customer_lob_ids = $customer_infos->pluck('user_id')->toArray();
                            }
                        }
                    }
                }

                $customer_ids = array_unique(array_merge($customer_ids, $customer_lob_ids));

                $jp_customer_info_user_ids = array();
                $jp_customer = JourneyPlanCustomer::get();
                if ($jp_customer) {
                    $jp_customer_ids = $jp_customer->pluck('customer_id')->toArray();
                    $jp_customer_info_ids = CustomerInfo::whereIn('id', $jp_customer_ids)->get();
                    if (count($jp_customer_info_ids)) {
                        $jp_customer_info_user_ids = $jp_customer_info_ids->pluck('user_id')->toArray();
                    }
                }

                $array_intersect = array_intersect($jp_customer_info_user_ids, $customer_ids);
                $final_customer_ids = array_values($array_intersect);

                $s_date = Carbon::parse($row[1])->format('Y-m-d');
                $d_date = Carbon::parse($row[2])->format('Y-m-d');

                $distribution = Distribution::where('name', trim($row[0]))
                    ->where('start_date', $s_date)
                    ->where('end_date', $d_date)
                    ->first();

                // $item       = Item::where('item_code', $row[8])->first();
                // $item_uom   = ItemUom::where('name', $row[9])->first();

                if (!is_object($distribution)) {
                    $distribution = new Distribution();
                    $distribution->name = $row[0];
                    $distribution->start_date  = $s_date;
                    $distribution->end_date = $d_date;
                    $distribution->height = $row[3];
                    $distribution->width = $row[4];
                    $distribution->depth = $row[5];
                    if (isset($row[6]) && $row[6] == "Yes") {
                        $distribution->status = 1;
                    } else {
                        $distribution->status = 0;
                    }
                    $distribution->save();
                }

                if (is_array($final_customer_ids)) {

                    foreach ($final_customer_ids as $customer_id) {
                        $distribution_customers = DistributionCustomer::where('distribution_id', $distribution->id)
                            ->where('customer_id', $customer_id)
                            ->first();

                        if (!$distribution_customers) {
                            $distribution_customers = new DistributionCustomer;
                        }

                        $distribution_customers->distribution_id = $distribution->id;
                        $distribution_customers->customer_id = $customer_id;
                        $distribution_customers->save();

                        $distribution_model_stocks = DistributionModelStock::where('distribution_id', $distribution->id)
                            ->where('customer_id', $customer_id)
                            ->first();

                        if (!is_object($distribution_model_stocks)) {
                            $distribution_model_stocks = new DistributionModelStock;
                        }

                        $distribution_model_stocks->distribution_id = $distribution->id;
                        $distribution_model_stocks->customer_id = $customer_id;
                        $distribution_model_stocks->save();

                        foreach (explode(',', $row[8]) as $item_code) {
                            $item       = Item::where('item_code', $item_code)->first();

                            if ($item) {
                                $distribution_model_stock_details = DistributionModelStockDetails::where('distribution_id', $distribution->id)
                                    ->where('item_id', $item->id)
                                    ->where('distribution_model_stock_id', $distribution_model_stocks->id)
                                    ->where('item_uom_id', $item->lower_unit_uom_id)
                                    ->first();

                                if (!$distribution_model_stock_details) {
                                    $distribution_model_stock_details = new DistributionModelStockDetails;
                                }

                                $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                                $distribution_model_stock_details->distribution_id = $distribution->id;
                                $distribution_model_stock_details->item_id = (is_object($item)) ? $item->id : null;
                                $distribution_model_stock_details->item_uom_id = $item->lower_unit_uom_id ?? null;
                                $distribution_model_stock_details->capacity = $row[10];
                                $distribution_model_stock_details->total_number_of_facing = $row[11];
                                $distribution_model_stock_details->save();
                            }
                        }
                    }
                }
            }
        }
    }

    public function channelToRadius()
    {
        $fileName = $_FILES["channel"]["tmp_name"];

        if ($_FILES["channel"]["size"] > 0) {

            $file = fopen($fileName, "r");
            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($column[1] == "Radius") {
                    continue;
                }

                $channel = Channel::where('name', 'like', "%$column[0]%")->first();
                pre($channel->name, false);
                if ($channel) {
                    $customer_lob = CustomerLob::select('customer_info_id')->where('channel_id', $channel->id)->get();
                    $customer_lob_ids = array();
                    if ($customer_lob->count()) {
                        $customer_lob_ids = $customer_lob->pluck('customer_info_id')->toArray();
                    }

                    $customer_info = CustomerInfo::whereIn('id', $customer_lob_ids)
                        ->whereNull('radius')
                        ->update(['radius' => $column[1]]);

                    $customer_info1 = CustomerInfo::where('channel_id', $channel->id)
                        ->whereNull('radius')
                        ->update(['radius' => $column[1]]);
                }
            }
        }
        return "done";
    }

    public function ItemsChannel()
    {
        $userChannel = array('tt-fresh', 'tt-ambient', 'mt');
        foreach ($userChannel as $userc) {
            $user_channel = new UserChannel();
            $user_channel->organisation_id = 1;
            $user_channel->name = $userc;
            $user_channel->status = 1;
            $user_channel->save();
        }


        $tt_fresh_brand_id = array("2", "4", "5", "6", "29", "30", "32", "39", "41", "43", "59", "84", "8", "31", "34");
        $tt_ambient_brand_id = array("9", "21", "23", "33", '3', '16', '17', '27', '38', '42', '19', '13', '14', '15', '22', '24', '26', '40', '44', '62', '95', '10');
        $mt_brand = Brand::get();

        // $tt_items = Item::whereIn('item_id', $tt_fresh_brand_id)->get();
        foreach ($tt_fresh_brand_id as $item) {
            $b = Brand::find($item);
            if ($b) {
                $brand_channel = new BrandChannel();
                $brand_channel->user_channel_id = 1;
                $brand_channel->brand_id = $item;
                $brand_channel->save();
            }
        }

        foreach ($tt_ambient_brand_id as $item) {
            $b = Brand::find($item);
            if ($b) {
                $brand_channel = new BrandChannel();
                $brand_channel->user_channel_id = 2;
                $brand_channel->brand_id = $item;
                $brand_channel->save();
            }
        }

        foreach ($mt_brand as $item) {
            $b = Brand::find($item);
            if ($b) {
                $brand_channel = new BrandChannel();
                $brand_channel->user_channel_id = 3;
                $brand_channel->brand_id = $item->id;
                $brand_channel->save();
            }
        }

        $user_channel_attached = new UserChannelAttached();
        $user_channel_attached->user_id = 1624;
        $user_channel_attached->user_channel_id = 1;
        $user_channel_attached->save();

        $user_channel_attached = new UserChannelAttached();
        $user_channel_attached->user_id = 1626;
        $user_channel_attached->user_channel_id = 2;
        $user_channel_attached->save();

        $user_channel_attached = new UserChannelAttached();
        $user_channel_attached->user_id = 1625;
        $user_channel_attached->user_channel_id = 3;
        $user_channel_attached->save();
    }

    public function itemCategory()
    {
        $fileName = $_FILES["itemCategory"]["tmp_name"];

        if ($_FILES["itemCategory"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($column[1] == "category") {
                    continue;
                }

                $item = Item::where('item_code', $column[0])->first();
                $category = ItemMajorCategory::where('name', $column[1])->first();
                if (!$category) {
                    $category = new ItemMajorCategory;
                    $category->name = $column[1];
                    $category->status = 1;
                    $category->node_level = 0;
                    $category->save();
                }
                if ($item && $category) {
                    $item->item_major_category_id = $category->id;
                    $item->save();
                } else {
                    pre($column);
                }
            }
        }
    }

    /**
     * This function is use for import the customer price
     * PDPItem and PDPCustomer
     * [Key - 0] : Pricing Plan Name
     * [Key - 1] : Item Code
     * [Key - 2] : Item UOM Name
     * [Key - 3] : Item Pricing
     * [Key - 4] : Customer COde
     */
    public function customerItemPrice()
    {
        $fileName = $_FILES["customer_price"]["tmp_name"];

        if ($_FILES["customer_price"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($column[0] == "Pricing Plan") {
                    continue;
                }

                $pricing_plan = PriceDiscoPromoPlan::where('name', 'like', '%' . $column[0] . '%')->first();
                $item = Item::where('item_code', $column[1])->first();
                $uom = ItemUom::where('name', 'like', '%' . $column[2] . '%')->first();

                if ($pricing_plan && $item && $uom) {
                    $pdp_item = PDPItem::where('price_disco_promo_plan_id', $pricing_plan->id)
                        ->where('item_id', $item->id)
                        ->where('item_uom_id', $uom->id)
                        ->first();

                    if (!$pdp_item) {
                        $pdp_item = new PDPItem();
                    }

                    $pdp_item->price_disco_promo_plan_id  = $pricing_plan->id;
                    $pdp_item->item_id      = $item->id;
                    $pdp_item->item_uom_id  = $uom->id;
                    $pdp_item->price        = $column[3];
                    $pdp_item->lob_id       = NULL;
                    $pdp_item->save();
                }

                $customerInfo = CustomerInfo::where('customer_code', 'like', '%' . $column[4] . '%')->first();
                if ($customerInfo && $pricing_plan) {
                    $pdp_customer = PDPCustomer::where('price_disco_promo_plan_id', $pricing_plan->id)
                        ->where('customer_id', $customerInfo->id)
                        ->first();

                    if (!$pdp_customer) {
                        $pdp_customer = new PDPCustomer();
                    }
                    $pdp_customer->price_disco_promo_plan_id  = $pricing_plan->id;
                    $pdp_customer->customer_id  = $customerInfo->id;
                    $pdp_customer->save();
                }
            }
        }
        return 'done';
    }

    public function qty($item_id, $uom, $qty)
    {
        $data = qtyConversion($item_id, $uom, $qty);
        pre($data);
    }

    public function exciseImport(Request $request)
    {
        $fileName = $_FILES["item_excise"]["tmp_name"];

        if ($_FILES["item_excise"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                $item_uom_id = 0;
                $item = Item::where('item_code', $column[0])->first();
                if ($column[3] == 1) {
                    $item_mp = ItemMainPrice::where('item_id', $item->id)
                        ->where('is_secondary', 1)
                        ->first();
                    if ($item_mp) {
                        $item_uom_id = model($item_mp->itemUom, 'id');
                    }
                } else {
                    $itemUom = ItemUom::where('name', 'like', '%' . $column[1] . '%')->first();
                    $item_uom_id = $itemUom->id;
                }

                if ($item && $item_uom_id > 0) {
                    $item->is_item_excise       = 1;
                    $item->item_excise_uom_id   = $item_uom_id;
                    $item->item_excise          = $column[2];
                    $item->save();
                }

                // Item::where('item_name', 'like', '%' . $column[0] . '%')
                //     ->update(['item_excise' => $column[1]]);
            }
        }
    }

    public function rfGenOrderPicking()
    {
        $rfGenView = rfGenView::where('mobiato_order_picked', '!=', 1)
            ->where('OrderPicked', "Yes")
            ->get();

        $rfGenView->each(function ($o, $key) {
            $od = OrderDetail::find($o->order_detail_id);

            if ($od) {
                // status updated in mobiato order_details table
                $od->update([
                    'is_rfgen_sync' => ($o->OrderPicked == "Yes") ? 1 : 0,
                ]);

                // status updated rg_gen_view table
                $o->update([
                    'mobiato_order_picked' => 1
                ]);

                // status updated order table
                $order = Order::where('order_number', $o->Order_Number)
                    ->where('approval_status', '!=', 'Picking Confirmed')
                    ->first();

                if ($order) {
                    $order->update([
                        'approval_status' => 'Picking Confirmed'
                    ]);
                }
            }
        });
    }

    public function orderView()
    {
        // DATE_FORMAT(`d`.`created_at`, '%d/%m/%Y') AS `DATE`,
        // `items`.`item_code` AS `ITM_CODE`,
        // `customer_infos`.`customer_code` AS `CUSTOMER_CODE`,
        // `orders`.`order_date` AS `ORDER_DATE`,
        // `orders`.`delivery_date` AS `REQUIRED_DATE`,
        // `orders`.`order_number` AS `ORDER_NUMBER`,

        $ov = Order::select(
            'items.item_code as ITM_CODE',
            'customer_infos.customer_code as CUSTOMER_CODE',
            DB::raw("order_date AS ORDER_DATE"),
            'delivery_date as REQUIRED_DATE',
            'salesman_infos.salesman_code as USER_CODE',
            DB::raw("CASE WHEN order_details.item_uom_id = items.lower_unit_uom_id THEN order_details.item_qty ELSE items.lower_unit_item_upc * order_details.item_qty END as qty")
        )
            ->withoutGlobalScope('organisation_id')
            ->leftJoin('order_details', function ($join) {
                $join->on('order_details.order_id', '=', 'orders.id');
            })
            ->leftJoin('items', function ($join) {
                $join->on('items.id', '=', 'order_details.item_id');
            })
            ->leftJoin('item_uoms', function ($join) {
                $join->on('item_uoms.id', '=', 'order_details.item_uom_id');
            })
            ->leftJoin('salesman_infos', function ($join) {
                $join->on('salesman_infos.user_id', '=', 'orders.salesman_id');
            })
            ->leftJoin('customer_infos', function ($join) {
                $join->on('customer_infos.user_id', '=', 'orders.customer_id');
            })
            ->where('orders.organisation_id', 1)
            // ->where('orders.date', date('Y-m-d'))
            ->get();

        pre($ov);
    }

    public function returnAssingSalesman()
    {
        $creditNote = CreditNote::select('id', 'credit_note_number', 'salesman_id', 'customer_id')
            ->where('approval_status', 'Requested')
            ->where('current_stage', 'Approved')
            ->get();

        if (count($creditNote)) {
            $creditNote->each(function ($cr, $key) {
                $dat = DeliveryAssignTemplate::where('customer_id', $cr->customer_id)
                    ->whereDate('created_at', now()->subDay()->format('Y-m-d'))
                    ->where('is_last_trips', 1)
                    ->first();

                if ($dat) {
                    $cr->salesman_id = $dat->delivery_driver_id;
                    $cr->save();
                }
            });
        }
    }

    public function orderSpotReport()
    {
        $date = "2024-01-17";
        //$edate = "2023-03-22";
        $ischange = '0';
        $order_query = Order::query('*');
        //$order_query->where('order_number', 'like', "%10205178%");
        //$order_query->whereBetween('delivery_date', [$date, $edate]);
        $order_query->where('delivery_date',$date);
        //$order_query->where('change_date',$date);
        $order_query->where('is_presale_order', 1);
        $order_query->where('current_stage', '!=', "Rejected");
        $orderHeader = $order_query->orderBy('order_date', 'desc')
        ->orderBy('order_number', 'desc')
        ->get();

        if (count($orderHeader)) {
            foreach ($orderHeader as $k => $detail) {
                $line_numbber = 0;
                foreach ($detail->orderDetails as $key => $order_detail) {

                    $qty_cancelled = "0";
                    $quantity_invoice = '0';
                    $invoice_number = "";
                    $invoice_date = "";
                    $load_date  = "";
                    $reason = '';
                    $code = '';
                    $quantity_delivery = '0';
                    $quantity_shipment = '0';
                    $cancel_date = '';
                    $delivery_note = '';
                    $salesman_code = "";
                    $salesman_name = "";
                    $driverreason = '';
                    $onHold = 'No';
                    $trip = "0";
                    $ac_trip = "0";
                    $van = "";
                    $helper1 = "";
                    $helper2 = "";
                    $storagelocation = "";
                    $shipmentTime = "";
                    $loaddate = "";
                    $orderdate = "";
                    // ship - invoice
                    $quantity_invoice_vs_shipped = '0';

                    if ($detail->approval_status == 'Cancelled') {
                        $qty_cancelled = $order_detail->original_item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    } else if ($order_detail->is_deleted == 1) {
                        $qty_cancelled = $order_detail->original_item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    } else if ($order_detail->reason_id && $order_detail->is_deleted != 1) {
                        $qty_cancelled = $order_detail->original_item_qty - $order_detail->item_qty;
                        $cancel_date = Carbon::parse($order_detail->updated_at)->format('d-m-Y');
                    }

                    $line_numbber = $line_numbber + 1;
                    $extended_amount    = ($order_detail->reason) ? '0' : $order_detail->item_gross;
                    // $Invoice_date = ($Invoice_query ? $Invoice_query->load_date : '');
                    $business_unit      = ($detail->storageocation) ? $detail->storageocation->code : '';
                    $storagelocation = ($detail->storageocation) ? $detail->storageocation->id : '';
                    // ship qty means load qty
                    // invoice qty

                    $delivery = Delivery::where('order_id', $detail->id)->first();

                    // 1: find in order detail
                    if (is_object($order_detail->reason)) {
                        $reason = ($order_detail->reason) ? $order_detail->reason->name : NULL;
                        if ($code == "") {
                            $code = ($order_detail->reason) ? $order_detail->reason->code : NULL;
                        }
                    }

                    if ($detail->approval_status == 'Cancelled') {
                        if ($code == "") {
                            $code = model($detail->reason, 'code');
                        }
                    }
                   

                    if ($delivery) {
                        $salesmanLoad_query = SalesmanLoadDetails::select('load_date')
                            ->whereHas('salesmanLoad', function ($q) use ($delivery) {
                                $q->where('delivery_id', $delivery->id);
                            })
                            ->where('item_id', $order_detail->item_id)
                            ->first();

                        $load_date  = ($salesmanLoad_query) ? Carbon::parse($salesmanLoad_query->load_date)->format('d-m-Y') : '';

                        // 2: find in delivery detail
                        $dds = DeliveryDetail::where('delivery_id', $delivery->id)
                            ->where('uuid', $order_detail->uuid)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        $deliver_detail_id = '';
                        if ($dds) {
                            $deliver_detail_id = $dds->id;
                            if ($dds->reason_id) {
                                $reason = ($dds->reason) ? $dds->reason->name : NULL;
                                if ($code == "") {
                                    $code = ($dds->reason) ? $dds->reason->code : NULL;
                                }
                            }

                            if ($dds->original_item_qty !== $dds->item_qty) {
                                $qty_cancelled = $dds->original_item_qty - $dds->item_qty;
                            }

                            if ($delivery->approval_status == 'Shipment' || $delivery->approval_status == 'Completed') {
                                if ($dds->item_qty > 0 && $dds->item_price > 0) {
                                    $quantity_shipment = $dds->item_qty;
                                }
                            }

                            if ($dds->is_deleted == 1) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }
                            // delivery Details cancel qty > 0

                            if ($dds->cancel_qty > 0) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }

                            if ($dds->item_qty != $dds->original_item_qty) {
                                $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                            }
                        }

                        $delivery_note = DeliveryNote::where('delivery_id', $delivery->id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        // 3: find in delivery detail
                        if ($delivery_note) {
                            if ($delivery_note->reason_id != '' || $delivery_note->is_cancel > 0) {
                                $reason = ($delivery_note->reason) ? $delivery_note->reason->name : NULL;
                                $driverreason = model($delivery_note->reason, 'code');
                            }
                        }
                    }

                    $invoice = Invoice::where('order_id', $detail->id)->first();

                    // order cancel
                    if ($detail->approval_status == "Cancelled") {
                        $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                    }

                    // delivery cancel
                    if (is_object($delivery) && $delivery->approval_status == "Cancel") {
                        $cancel_date = Carbon::parse($detail->updated_at)->format('d-m-Y');
                        if ($code == "") {
                            $code = model($delivery->reason, 'code');
                        }
                    }

                    if (is_object($delivery)) {
                        if ($delivery->change_date != null || $delivery->change_date != "") {
                            $onHold = "Yes";
                            if($detail->hold_reason)
                            {
                                $rr = ReasonType::where('id', $detail->hold_reason)->first();
                                //$hReason = ReasonType::where('id', $detail->hold_reason)->first();
                                $driverreason = $rr->code;
                            }else{
                                $driverreason = "RBC";
                            }

                            if($ischange == '1')
                            {
                                $onHold = "No";
                                if($delivery->approval_status == "Cancel")
                                {
                                    $driverreason =  $code;
                                }else{
                                    $driverreason = '';
                                }
                                
                            }

                        } else {
                            $onHold = "No";
                        }
                    }

                    // last delivery note qty
                    if (is_object($delivery_note)) {
                        if ($cancel_date == "") {
                            $cancel_date = ($delivery_note->is_cancel == 1) ? Carbon::parse($detail->updated_at)->format('d-m-Y') : "";
                        }
                    }

                    if ($invoice && $delivery) {
                            
                        $delivery_d = DeliveryDetail::where('delivery_id', $delivery->id)
                            ->where('uuid', $order_detail->uuid)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();
                        
                            
                        if ($delivery_d) {
                            
                            $quantity_delivery = $delivery_d->item_qty;
                            if ($delivery->approval_status == 'Shipment' || $delivery->approval_status == 'Completed') {
                                if ($delivery_d->item_qty > 0 && $delivery_d->item_price > 0) {
                                    $quantity_shipment = $delivery_d->item_qty;
                                }
                            }

                            $dn = DeliveryNote::where('delivery_id', $delivery->id)
                                ->where('delivery_detail_id', $deliver_detail_id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->get();

                            if (count($dn)) {
                                $qty = $dn->pluck('qty')->toArray();
                                $qty_cancelled = $qty_cancelled + ($delivery_d->item_qty - array_sum($qty));
                            }

                            $dassign = DeliveryAssignTemplate::where('delivery_id', $delivery->id)
                            ->where('delivery_details_id', $delivery_d->id)
                            ->first();
                            
                            if($dassign)
                            {
                                $trip = $dassign->trip;
                                $ac_trip = $dassign->actual_trip;

                                if ($dassign->delivery_driver_id != null || $dassign->delivery_driver_id != "") {
                                    $mSalesman = SalesmanInfo::where('user_id', $dassign->delivery_driver_id)->first();
                                    $salesman_code = $mSalesman->salesman_code;
                                    $mSalesUser = User::find($dassign->delivery_driver_id);
                                    $salesman_name = $mSalesUser->firstname . ' ' . $mSalesUser->lastname;

                                    $dodo = SalesmanVehicle::where('salesman_id', $dassign->delivery_driver_id)
                                    ->where('date', $detail->delivery_date)
                                    ->first();

                                    if($dodo)
                                    {
                                        $mVan = Van::where('id', $dodo->van_id)->first();
                                        if($mVan)
                                        {
                                            $van = $mVan->van_code;
                                        }

                                        if($dodo->helper1_id)
                                        {
                                            $mH1Salesman = SalesmanInfo::where('user_id', $dodo->helper1_id)->first();
                                            $helper1 = $mH1Salesman->salesman_code;
                                        }

                                        if($dodo->helper2_id)
                                        {
                                            $mH2Salesman = SalesmanInfo::where('user_id', $dodo->helper2_id)->first();
                                            $helper2 = $mH2Salesman->salesman_code;
                                        }
                                    }
                                }
                            }
                        }

                        $invoice_number = $invoice->invoice_number;
                        $invoice_date   = $invoice->invoice_date;


                        $quantity_invoice = '0';
                        $quantity_invoice_vs_shipped = '0';

                        $invoice_qty = DeliveryNote::select(
                            DB::raw("SUM(qty) AS invoices")
                        )
                            ->where('delivery_id', $delivery->id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->where('is_cancel', 0)
                            ->first();
                            
                        if ($invoice_qty->invoices != '') {
                            $quantity_invoice = $invoice_qty->invoices;
                            
                            $invoices_qty = InvoiceDetail::select(
                                DB::raw("SUM(item_qty) AS invoices")
                            )
                                ->where('invoice_id', $invoice->id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->where('delv_id', $deliver_detail_id)
                                ->first();

                                if($invoices_qty->invoices != '')
                                {
                                   
                                    $quantity_invoice = $invoices_qty->invoices;
                                    if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                        $invoice_number = '';
                                        $invoice_date = '';
                                    }
                                }else{
                                    $invoices_qty = InvoiceDetail::select(
                                        DB::raw("SUM(item_qty) AS invoices")
                                    )
                                        ->where('invoice_id', $invoice->id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->first();

                                         // if delivery item_qty more then invoice qty
                                if ($delivery_d) {
                                    if ($delivery_d->item_qty > $quantity_invoice) {

                                        $invoices_qty = InvoiceDetail::select(
                                            DB::raw("SUM(item_qty) AS invoices")
                                        )
                                            ->where('invoice_id', $invoice->id)
                                            ->where('item_id', $order_detail->item_id)
                                            ->where('item_uom_id', $order_detail->item_uom_id)
                                            ->first();
                                        $quantityFinvoice = $invoices_qty->invoices;
                                        if($delivery_d->item_qty > $quantityFinvoice)
                                        {
                                            $cancel_date = Carbon::parse($delivery_d->updated_at)->format('d-m-Y');
                                        }else{
                                            $quantity_invoice =  $quantityFinvoice;
                                        }
                                        
                                    }

                                    if ($delivery_d->is_deleted == 1) {
                                        $qty_cancelled = $delivery_d->original_item_qty;
                                    }
                                }

                                // if invoice qty less then 0
                                if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                
                                    $invoiceC_qty = DeliveryNote::select(
                                        DB::raw("SUM(qty) AS invoices")
                                    )
                                        ->where('delivery_id', $delivery->id)
                                        ->where('delivery_detail_id', $deliver_detail_id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->where('is_cancel', 1)
                                        ->first();

                                        if($invoiceC_qty)
                                        {
                                        
                                            if($invoiceC_qty->invoices == $quantity_shipment)
                                            {
                                                $quantity_invoice = $quantity_shipment - $invoiceC_qty->invoices;
                                            }else{
                                                $quantity_invoice = $invoiceC_qty->invoices;
                                            }
                                        }else{
                                            $invoices_qty = InvoiceDetail::select(
                                                DB::raw("SUM(item_qty) AS invoices")
                                            )
                                                ->where('invoice_id', $invoice->id)
                                                ->where('item_id', $order_detail->item_id)
                                                ->where('item_uom_id', $order_detail->item_uom_id)
                                                ->first();
                                            $quantity_invoice = $invoices_qty->invoices;
                                            if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                                $invoice_number = '';
                                                $invoice_date = '';
                                            }
                                        }
                                    
                                }
                         }
                           
                        }else{

                           
                            $invoiceC_qty = DeliveryNote::select(
                                DB::raw("SUM(qty) AS invoices")
                            )
                                ->where('delivery_id', $delivery->id)
                                ->where('delivery_detail_id', $deliver_detail_id)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->where('is_cancel', 1)
                                ->first();
                               
                               // pre($invoiceC_qty);
                                if($invoiceC_qty)
                                {
                                    if($invoiceC_qty->invoices != $quantity_shipment)
                                    {
                                        $invoices_qty = InvoiceDetail::select(
                                            DB::raw("SUM(item_qty) AS invoices")
                                        )
                                            ->where('invoice_id', $invoice->id)
                                            ->where('item_id', $order_detail->item_id)
                                            ->where('item_uom_id', $order_detail->item_uom_id)
                                            ->where('delv_id', $deliver_detail_id)
                                            ->first();
                                        
                                            // if($invoices_qty->delv_id != '')
                                            // {
                                                $quantity_invoice = $invoices_qty->invoices;
                                                
                                            // }else{
                                            //     $invoicess_qty = InvoiceDetail::select(
                                            //         DB::raw("SUM(item_qty) AS invoices")
                                            //     )
                                            //         ->where('invoice_id', $invoice->id)
                                            //         ->where('item_id', $order_detail->item_id)
                                            //         ->where('item_uom_id', $order_detail->item_uom_id)
                                            //         ->first();

                                            //     $quantity_invoice = $invoicess_qty->invoices;
                                            // }
                                            
                                    }
                                }else{
                                    $invoices_qty = InvoiceDetail::select(
                                        DB::raw("SUM(item_qty) AS invoices")
                                    )
                                        ->where('invoice_id', $invoice->id)
                                        ->where('item_id', $order_detail->item_id)
                                        ->where('item_uom_id', $order_detail->item_uom_id)
                                        ->where('delv_id', $deliver_detail_id)
                                        ->first();
                                        
                                        // if($invoices_qty->delv_id != '')
                                        // {
                                            $quantity_invoice = $invoices_qty->invoices;
                                        // }else{
                                        //     $invoicess_qty = InvoiceDetail::select(
                                        //         DB::raw("SUM(item_qty) AS invoices")
                                        //     )
                                        //         ->where('invoice_id', $invoice->id)
                                        //         ->where('item_id', $order_detail->item_id)
                                        //         ->where('item_uom_id', $order_detail->item_uom_id)
                                        //         ->first();
                                        //     $quantity_invoice = $invoicess_qty->invoices;
                                        // }
                                        
                                }
                            
                            
                        }

                        $cancel_qty = DeliveryNote::select(
                            DB::raw("SUM(qty) AS cancel"),
                            'reason_id',
                            'created_at'
                        )
                            ->with('reason')
                            ->where('is_cancel', 1)
                            ->where('delivery_id', $delivery->id)
                            ->where('delivery_detail_id', $deliver_detail_id)
                            ->where('item_id', $order_detail->item_id)
                            ->where('item_uom_id', $order_detail->item_uom_id)
                            ->first();

                        if ($cancel_qty->cancel && $cancel_qty->cancel > 0 && $cancel_qty->cancel != '') {
                            $qty_cancelled = $cancel_qty->cancel;

                            // if ($code == "") {
                            //     $code = model($cancel_qty->reason, 'code');
                            // }

                            $driverreason = model($cancel_qty->reason, 'code');
                            //pre($driverreason);
                            // if delivery item qty and invoice qty + cancel qty is same then invoice number and date visible
                            // if ($delivery_d->item_qty != ($quantity_invoice + $qty_cancelled)) {
                            //     $invoice_number = '';
                            //     $invoice_date = '';
                            // }

                            if($quantity_invoice == 0)
                            {
                                $invoice_number = '';
                                $invoice_date = '';
                            }

                            if ($qty_cancelled) {
                                if ($cancel_date == "") {
                                    $cancel_date = Carbon::parse($cancel_qty->created_at)->format('d-m-Y');
                                }
                            }
                        }

                        // if (count($deliver_notes)) {
                        //     foreach ($deliver_notes as $deliver_note) {
                        //         // Delivery note cancelled qty
                        //         if ($deliver_note) {
                        //             // invoice qty base on delivery note
                        //             if ($deliver_note->is_cancel != 1) {
                        //                 $qty_cancelled = $qty_cancelled + ($order_detail->item_qty - $quantity_delivery) + ($quantity_delivery - $deliver_note->qty);
                        //                 $quantity_invoice   = $quantity_invoice + $deliver_note->qty;
                        //             } else {
                        //                 $qty_cancelled = $qty_cancelled + $deliver_note->qty;
                        //                 $invoice_number = '';
                        //                 $invoice_date = '';
                        //             }

                        //             $quantity_invoice_vs_shipped = $quantity_invoice_vs_shipped + ($quantity_delivery - $quantity_invoice);

                        //             if ($qty_cancelled > 0) {
                        //                 $cancel_date = Carbon::parse($deliver_note->created_at)->format('d-m-Y');
                        //             }
                        //         }
                        //     }
                        // }
                    } else {
                        if ($delivery) {
                            $delivery_d = DeliveryDetail::where('delivery_id', $delivery->id)
                                ->where('uuid', $order_detail->uuid)
                                ->where('item_id', $order_detail->item_id)
                                ->where('item_uom_id', $order_detail->item_uom_id)
                                ->first();

                            if ($delivery_d) {
                                if ($delivery->approval_status == 'Cancel') {
                                    $quantity_delivery  = $delivery_d->item_qty;
                                    $quantity_shipment  = $delivery_d->item_qty;
                                    $quantity_invoice_vs_shipped    = $quantity_invoice_vs_shipped + ($quantity_delivery - 0);
                                }

                            $dassign = DeliveryAssignTemplate::where('delivery_id', $delivery->id)
                            ->where('delivery_details_id', $delivery_d->id)
                            ->first();

                            if($dassign)
                            {
                                $trip = $dassign->trip;
                                $ac_trip = $dassign->actual_trip;
                                if ($dassign->delivery_driver_id != null || $dassign->delivery_driver_id != "") {
                                    $mSalesman = SalesmanInfo::where('user_id', $dassign->delivery_driver_id)->first();
                                    $salesman_code = $mSalesman->salesman_code;
                                    $mSalesUser = User::find($dassign->delivery_driver_id);
                                    $salesman_name = $mSalesUser->firstname . ' ' . $mSalesUser->lastname;

                                    $dodo = SalesmanVehicle::where('salesman_id', $dassign->delivery_driver_id)
                                    ->where('date', $detail->delivery_date)
                                    ->first();

                                    if($dodo)
                                    {
                                        $mVan = Van::where('id', $dodo->van_id)->first();
                                        if($mVan)
                                        {
                                            $van = $mVan->van_code;
                                        }

                                        if($dodo->helper1_id)
                                        {
                                            $mH1Salesman = SalesmanInfo::where('user_id', $dodo->helper1_id)->first();
                                            $helper1 = $mH1Salesman->salesman_code;
                                        }

                                        if($dodo->helper2_id)
                                        {
                                            $mH2Salesman = SalesmanInfo::where('user_id', $dodo->helper2_id)->first();
                                            $helper2 = $mH2Salesman->salesman_code;
                                        }
                                    }
                                }
                            }
                            }
                            
                        }
                    }

                    $total_cancel = $qty_cancelled * $order_detail->original_item_price;
                    $total_cancel_aed = ($total_cancel ? $total_cancel : '0');

                    $pg = PickingSlipGenerator::where('order_id', $detail->id)->first();

                    $load = SalesmanLoad::where('order_id', $detail->id)->first();

                    if (!$load && !$pg) {
                        if ($detail->approval_status == "Cancelled") {
                            $quantity_shipment = "0";
                        }
                    }

                    if($quantity_shipment > 0)
                    {
                        $shipmentTime = $load->created_at;
                        $loaddate = $load->created_at->toDateString();
                    }else{
                        if($ischange == '1')
                        {
                            $driverreason = '';
                        }
                    }
                    
                    if($code == $driverreason)
                    {
                        $code = "";  
                    }

                    if ($order_detail->is_deleted === 1 || $quantity_shipment < 1) {
                        $invoice_number = "";
                    }

                    $shipVSInv = ((($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') !== 0) ? (($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') : '0';
                    $unit_price = ($order_detail->original_item_price > 0) ? $order_detail->original_item_price : $order_detail->item_price;
                    $total_amtItem = ($order_detail->item_grand_total > 0) ? $order_detail->item_grand_total : $order_detail->item_grand_total;
                    if($unit_price)
                    {
                        $totalUnitPrice = ($unit_price + $order_detail->item_excise);
                    }else{
                        $unit_price = '0.0';
                        $totalUnitPrice = ($unit_price + $order_detail->item_excise);
                    }
                    
                    $vu = new OrderReport();
                    $vu->organisation_id     = '1';
                    $vu->order_no      = $detail->order_number;
                    $vu->customer_code    = model($detail->customerInfo, 'customer_code');
                    $vu->customer_name     = model($detail->customer, 'firstname') . ' ' . model($detail->customer, 'lastname');
                    $vu->item_code      = model($order_detail->item, 'item_code');
                    $vu->item_name      = model($order_detail->item, 'item_name');
                    $vu->item_uom      = model($order_detail->itemUom, 'name');
                    
                    
                    $orQty = ($detail->approval_status == "Cancelled") ? $order_detail->original_item_qty : (($order_detail->item_qty == "0.00") ? $order_detail->original_item_qty : $order_detail->item_qty);
                    $spQty = ($quantity_shipment > 0) ? $quantity_shipment : '0';
                    $ipQty = ($quantity_invoice > 0) ? $quantity_invoice : '0';
                   
                    if($orQty == $spQty)
                    {
                        $code = '';
                    }
                    $vu->order_qty      = $orQty;
                    $vu->load_qty      = $spQty;
                    $vu->cancel_qty      = ($orQty - $spQty);

                    if($spQty > 0)
                    {
                        $vu->invoice_qty      = $ipQty;
                        $vu->spot_return      = ($spQty - $ipQty);
                        
                    }else{
                        $vu->invoice_qty      = "0";
                        $vu->spot_return      = "0";
                        
                    }
                    
                    $vu->order_date      = $delivery->created_at;
                    $vu->delivery_date      = $detail->delivery_date;
                    $vu->invoice_date      = ($invoice_number !== "") ? $invoice_date : "";
                    $vu->invoice_no      = $invoice_number;
                    $vu->customer_lpo      = $detail->customer_lop;
                    $vu->shipment_time      =  $shipmentTime;
                    $vu->load_date          = $loaddate;
                    $vu->storage_location_id      = $storagelocation;
                    $vu->branch_plant      = $business_unit;
                    $vu->on_hold      = $onHold;
                    $vu->driver      = $salesman_code;
                    $vu->cancel_reason      = $code;
                    $vu->driver_reason      = $driverreason;
                    $vu->extend_amt      = ($quantity_shipment > 0) ? round($quantity_shipment * $order_detail->item_price, 2) : "0";
                    $vu->trip      = $trip;
                    $vu->actual_trip  = $ac_trip;
                    $vu->vehicle      = $van;
                    $vu->helper1      = $helper1;
                    $vu->helper2      = $helper2;
                    $vu->save();
                }
            }
        }
        
    }

    public function spotReturnReport()
    {
        $date = "2023-03-14";

        $cancle_count = 0;
        $customer_count = 0;
        $less_delivery_count = 0;
        $order_count = 0;

        $svs = Delivery::where('delivery_date', $date)
            ->get();

            foreach ($svs as $sv) {

                $ddT = DeliveryAssignTemplate::select(DB::raw('SUM(qty) as qty'), 'delivery_details_id', 'customer_id','delivery_id','item_id','amount')
                ->where('delivery_id', $sv->id)
                ->groupBy('delivery_details_id')
                ->get();
                
               
                foreach($ddT as $detail)
                {
                   
                    if($sv->change_date != null)
                    {
                                        $diff = $detail->qty;
                                        $amount = $diff * $detail->amount;

                                        $cr = CustomerRegion::where('customer_id', $detail->customer_id)->first();
                                        $ksm = CustomerKamMapping::where('customer_id', $detail->customer_id)->first();
                                        $rr = ReasonType::where('id', '48')->first();

                                        $vu = new SpotReport();
                                        $vu->organisation_id     = '1';
                                        $vu->tran_date     = $date;
                                        $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                                        $vu->zone_name     = ($cr) ? model($cr->zone, 'name') : NULL;
                                        $vu->ksm_id        = $ksm->kam_id;
                                        $vu->ksm_name      = model($ksm->kam, 'firstname');
                                        $vu->qty     = $diff;
                                        $vu->reason     = $rr->name;
                                        $vu->amount     = $amount;
                                        $vu->save();
                    }else{
                        $delivery_note = DeliveryNote::select(DB::raw('SUM(qty) as qty'),'reason_id','is_cancel')
                        ->where('delivery_id', $detail->delivery_id)
                        ->where('item_id', $detail->item_id)
                        ->where('delivery_detail_id', $detail->delivery_details_id)
                        ->first();
                    
                        if ($delivery_note) {

                            if ($delivery_note->qty > 0) {
                                $order_qty = $delivery_note->qty;
                                if($delivery_note->qty < $detail->qty)
                                {
                                    $diff = $detail->qty - $delivery_note->qty;

                                    $cr = CustomerRegion::where('customer_id', $detail->customer_id)->first();
                                    $ksm = CustomerKamMapping::where('customer_id', $detail->customer_id)->first();
                                    $rr = ReasonType::where('id', $delivery_note->reason_id)->first();
                                    $amount = $diff * $detail->amount;
                                        
                                    if($ksm == NULL)
                                    {
                                        pre($detail->customer_id,false);
                                    }else{
                                        $vu = new SpotReport();
                                        $vu->organisation_id     = '1';
                                        $vu->tran_date     = $date;
                                        $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                                        $vu->zone_name     = ($cr) ? model($cr->zone, 'name') : NULL;
                                        $vu->ksm_id        = ($ksm) ? $ksm->kam_id : NULL;
                                        $vu->ksm_name      = ($ksm) ? model($ksm->kam, 'firstname') : NULL;
                                        $vu->qty     = $diff;
                                        $vu->reason     = $rr->name;
                                        $vu->amount     = $amount;
                                        $vu->save();
                                    }
                                    
                                }else{
                                    if($delivery_note->is_cancel == '1')
                                    {
                                        $diff = $delivery_note->qty;

                                        $cr = CustomerRegion::where('customer_id', $detail->customer_id)->first();
                                        $ksm = CustomerKamMapping::where('customer_id', $detail->customer_id)->first();
                                        $rr = ReasonType::where('id', $delivery_note->reason_id)->first();
                                        $amount = $diff * $detail->amount;

                                        if($ksm == NULL)
                                    {
                                        pre($detail->customer_id,false);
                                    }else{
                                        $vu = new SpotReport();
                                        $vu->organisation_id     = '1';
                                        $vu->tran_date     = $date;
                                        $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                                        $vu->zone_name     = ($cr) ? model($cr->zone, 'name') : NULL;
                                        $vu->ksm_id        = ($ksm) ? $ksm->kam_id : NULL;
                                        $vu->ksm_name      = ($ksm) ? model($ksm->kam, 'firstname') : NULL;
                                        $vu->qty     = $diff;
                                        $vu->reason     = $rr->name;
                                        $vu->amount     = $amount;
                                        $vu->save();
                                    }
    
                                    }
                                }
                            }
                        }
                    }

                }
            }

    }

    public function VehicleUtilisationReport()
    {
        $date = "2023-03-14";

        $cancle_count = 0;
        $customer_count = 0;
        $less_delivery_count = 0;
        $order_count = 0;

        $svs = SalesmanVehicle::where('date', $date)
            // ->where('salesman_id', 51364)
            ->get();

        foreach ($svs as $sv) {

            $order_count = 0;

            $dtas  = DeliveryAssignTemplate::select(DB::raw('SUM(qty) as qty'), 'delivery_driver_id', 'trip')
                ->whereHas('delivery', function ($q) use ($date) {
                    $q->where('delivery_date', '=', $date)
                          ->orWhere('change_date', '=', $date);
                })
                ->where('delivery_driver_id', $sv->salesman_id)
                ->groupBy('delivery_driver_id')
                ->groupBy('trip')
                ->get();

            foreach ($dtas as $dta) {

                $dat_delivery_id  = DeliveryAssignTemplate::select('delivery_id')
                    ->whereHas('delivery', function ($q) use ($date) {
                        $q->where('delivery_date', '=', $date)
                          ->orWhere('change_date', '=', $date);
                    })
                    ->where('delivery_driver_id', $dta->delivery_driver_id)
                    ->where('trip', $dta->trip)
                    ->groupBy('delivery_id')
                    ->get();


                $delivery_ids = array();
                $invoice_qty = 0;
                $invoice_count = 0;
                $load_qty = 0;
                $order_qty = 0;
                $cr = "";

                if (count($dat_delivery_id)) {
                    $delivery_ids = $dat_delivery_id->pluck('delivery_id')->toArray();

                    $delivery_note = DeliveryNote::select(DB::raw('SUM(qty) as qty'))
                        ->whereIn('delivery_id', $delivery_ids)
                        ->where('salesman_id', $dta->delivery_driver_id)
                        ->where('is_cancel', 0)
                        ->first();

                    $delivery_can = Delivery::select(DB::raw('count(id) as cancel_delivery'))
                        ->whereIn('id', $delivery_ids)
                        ->where('approval_status', 'Cancel')
                        ->first();

                    $co_count = Delivery::select(
                        DB::raw('count(Distinct customer_id) as customer_ids'),
                        DB::raw('count(id) as order_count'),
                        DB::raw('group_concat(customer_id) as customer_id')
                    )
                        ->whereIn('id', $delivery_ids)
                        ->first();

                    $deliveries = Delivery::select('order_id')
                        ->whereIn('id', $delivery_ids)
                        ->get();

                    if (count($deliveries)) {
                        $ids = $deliveries->pluck('order_id')->toArray();

                        $od = OrderDetail::selectRaw('sum(original_item_qty) as qty')
                            ->whereIn('order_id', $ids)
                            ->first();

                        if ($od->qty > 0) {
                            $order_qty = $od->qty;
                        }
                    }

                    $less_delivery = Delivery::select(
                        DB::raw('count(id) as count_delivery')
                    )
                        ->whereIn('id', $delivery_ids)
                        ->where('total_qty', '<', '11')
                        ->first();

                    $inv_count = Invoice::select(DB::raw('distinct customer_id'))
                        ->whereIn('delivery_id', $delivery_ids)
                        // ->groupBy('customer_id')
                        ->get();

                    $invoice_count = count($inv_count);

                    $invsm_count = Invoice::select(DB::raw('distinct customer_id'))
                        ->whereIn('delivery_id', $delivery_ids)
                        ->where('is_submitted','1')
                        // ->groupBy('customer_id')
                        ->get();

                    $invoicesm_count = count($invsm_count);

                    $less_delivery_count = $less_delivery->count_delivery;

                    $customer_count = $co_count->customer_ids;
                    $order_count    = $co_count->order_count;

                    $cancle_count = $delivery_can->cancel_delivery;

                    $invoice_qty = ($delivery_note->qty > 0) ? $delivery_note->qty : 0;

                    $c_id_exploed = explode(',', $co_count->customer_id);

                    $cr = CustomerRegion::whereIn('customer_id', $c_id_exploed)->first();

                    $salesmanLoad = SalesmanLoad::select('id')
                        ->whereIn('delivery_id', $delivery_ids)
                        ->where('salesman_id', $dta->delivery_driver_id)
                        ->get();

                    if (count($salesmanLoad)) {
                        $ids = $salesmanLoad->pluck('id')->toArray();
                        $load_d = SalesmanLoadDetails::select(DB::raw('SUM(load_qty) as load_qty'))
                            ->whereIn('salesman_load_id', $ids)
                            ->first();
                        if ($load_d && $load_d->load_qty > 0) {
                            $load_qty = $load_d->load_qty;
                        }
                    }
                }

                $om = OdoMeter::where('salesman_id', $dta->delivery_driver_id)
                    ->where('van_id', $sv->van_id)
                    ->where('status', 'end')
                    ->where('date', $date)
                    ->get()
                    ->toArray();

                $s_km   = '0';
                $e_km   = '0';
                $diesel = '0';

                if (count($om)) {
                    $key = $dta->trip - 1;
                    $omObj = (object) $om[$key];

                    if ($omObj) {
                        $s_km   = $omObj->start_fuel;
                        $e_km   = $omObj->end_fuel;
                        $diesel = $omObj->diesel;
                    }
                }

                $vu = new VehicleUtilisation();
                $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                $vu->zone_name    = ($cr) ? model($cr->zone, 'name') : NULL;
                $vu->zone_code     = ($cr) ? model($cr->zone, 'no_truck') : NULL;

                $vu->vehicle_id     = $sv->van_id;
                $vu->vehicle_code   = model($sv->van, 'van_code');

                $vu->salesman_id     = $dta->delivery_driver_id;
                $vu->salesman_code   = model($dta->deliveryDriverInfo, 'salesman_code');
                $vu->salesman_name   = model($dta->deliveryDriver, 'firstname') . ' ' . model($dta->deliveryDriver, 'lastname');
                $vu->trip_number     = $dta->trip;

                $vu->invoice_count  = $invoice_count;
                $vu->invoice_qty    = $invoice_qty;

                $vu->customer_count = $customer_count;
                $vu->delivery_qty   = $dta->qty;
                $vu->cancle_count   = $cancle_count;
                $vu->cancel_qty     = $dta->qty - $invoice_qty;
                // $vu->transcation_date = $date;
                $vu->transcation_date = $date;
                $vu->less_delivery_count = $less_delivery_count;
                $vu->order_count    = $order_count;
                $vu->order_qty      = $order_qty;
                $vu->load_qty       = $load_qty;
                $vu->vehicle_capacity = model($sv->van, 'capacity');

                $vu->start_km       = $s_km;
                $vu->end_km         = $e_km;
                $vu->diesel         = $diesel;
                $vu->pod_submit         = $invoicesm_count;
                $vu->salesman_type         = '1';
                $vu->save();

                if($sv->helper1_id)
                {
                    $vu = new VehicleUtilisation();
                    $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                    $vu->zone_name    = ($cr) ? model($cr->zone, 'name') : NULL;
                    $vu->zone_code     = ($cr) ? model($cr->zone, 'no_truck') : NULL;

                    $vu->vehicle_id     = $sv->van_id;
                    $vu->vehicle_code   = model($sv->van, 'van_code');

                    $salesmanh1 = SalesmanInfo::where('user_id', $sv->helper1_id)->first();
                    $super = User::where('id', $salesmanh1->user_id)->first();

                    $vu->salesman_id     = $sv->helper1_id;
                    $vu->salesman_code   = $salesmanh1->salesman_code;
                    $vu->salesman_name   = $super->firstname . ' ' . $super->lastname;
                    $vu->trip_number     = $dta->trip;

                    $vu->invoice_count  = $invoice_count;
                    $vu->invoice_qty    = $invoice_qty;

                    $vu->customer_count = $customer_count;
                    $vu->delivery_qty   = $dta->qty;
                    $vu->cancle_count   = $cancle_count;
                    $vu->cancel_qty     = $dta->qty - $invoice_qty;
                    // $vu->transcation_date = $date;
                    $vu->transcation_date = $date;
                    $vu->less_delivery_count = $less_delivery_count;
                    $vu->order_count    = $order_count;
                    $vu->order_qty      = $order_qty;
                    $vu->load_qty       = $load_qty;
                    $vu->vehicle_capacity = model($sv->van, 'capacity');

                    $vu->start_km       = $s_km;
                    $vu->end_km         = $e_km;
                    $vu->diesel         = $diesel;
                    $vu->pod_submit         = $invoicesm_count;
                    $vu->salesman_type         = '2';
                    $vu->save();
                }

                if($sv->helper2_id)
                {
                    $vu = new VehicleUtilisation();
                    $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                    $vu->zone_name    = ($cr) ? model($cr->zone, 'name') : NULL;
                    $vu->zone_code     = ($cr) ? model($cr->zone, 'no_truck') : NULL;

                    $vu->vehicle_id     = $sv->van_id;
                    $vu->vehicle_code   = model($sv->van, 'van_code');

                    $salesmanh1 = SalesmanInfo::where('user_id', $sv->helper2_id)->first();
                    $super = User::where('id', $salesmanh1->user_id)->first();

                    $vu->salesman_id     = $sv->helper2_id;
                    $vu->salesman_code   = $salesmanh1->salesman_code;
                    $vu->salesman_name   = $super->firstname . ' ' . $super->lastname;
                    $vu->trip_number     = $dta->trip;

                    $vu->invoice_count  = $invoice_count;
                    $vu->invoice_qty    = $invoice_qty;

                    $vu->customer_count = $customer_count;
                    $vu->delivery_qty   = $dta->qty;
                    $vu->cancle_count   = $cancle_count;
                    $vu->cancel_qty     = $dta->qty - $invoice_qty;
                    // $vu->transcation_date = $date;
                    $vu->transcation_date = $date;
                    $vu->less_delivery_count = $less_delivery_count;
                    $vu->order_count    = $order_count;
                    $vu->order_qty      = $order_qty;
                    $vu->load_qty       = $load_qty;
                    $vu->vehicle_capacity = model($sv->van, 'capacity');

                    $vu->start_km       = $s_km;
                    $vu->end_km         = $e_km;
                    $vu->diesel         = $diesel;
                    $vu->pod_submit         = $invoicesm_count;
                    $vu->salesman_type         = '2';
                    $vu->save();
                }
            }
        }
    }

    public function odUpdate()
    {
        $dd = DeliveryDetail::whereIn('id', [
            "9067",
            "9068",
            "9069",
            "9070",
            "9071",
            "9072",
            "9073",
            "9074",
            "9075",
            "9076",
            "9077",
            "9078",
            "9079",
            "9080",
            "9102",
            "9103",
            "9104",
            "9105",
            "9106",
            "9107"
        ])
            ->get();
        pre($dd);
        if (count($dd)) {
            foreach ($dd as $d) {
                $del = Delivery::find($d->delivery_id);
                // $order = Order::find($del->order_id);
                $od = OrderDetail::where('order_id', $del->order_id)
                    ->where('item_id', $d->item_id)
                    ->where('item_uom_id', $d->item_uom_id)
                    ->first();
                if ($od) {
                    $d->update([
                        'uuid' => $od->uuid,
                        'original_item_qty' => $od->original_item_qty,
                        'original_item_uom_id' => $od->original_item_uom_id,
                        'discount_id' => 0
                    ]);
                }
            }
        }
    }

    public function salesVsGrv()
    {
        $invoices = Invoice::select('id', 'invoice_date', 'total_qty', 'total_gross', 'customer_id')
            ->where('invoice_date', now()->subDay()->format('Y-m-d'))
            ->get();

        if ($invoices) {
            foreach ($invoices as $invoice) {
                $ksm = CustomerKamMapping::where('customer_id', $invoice->customer_id)->first();
                $cr = CustomerRegion::where('customer_id', $invoice->customer_id)->first();
                if ($ksm && $cr) {

                    $SalesVsGrv = SalesVsGrv::where('date', $invoice->invoice_date)
                        ->where('kam_id', $ksm->kam_id)
                        ->where('zone_id', $cr->zone_id)
                        ->first();

                    if ($SalesVsGrv) {
                        $SalesVsGrv->update([
                            'invoice_qty' => $SalesVsGrv->invoice_qty + $invoice->total_qty,
                            'invoice_amount' => $SalesVsGrv->invoice_amount + $invoice->total_gross,
                        ]);
                    } else {
                        SalesVsGrv::create([
                            'date'          => $invoice->invoice_date,
                            'zone_id'       => $cr->zone_id,
                            'zone_name'     => model($cr->zone, 'name'),
                            'kam_id'        => $ksm->kam_id,
                            'kam_name'      => model($ksm->kam, 'firstname') . ' ' . model($ksm->kam, 'lastname'),
                            'invoice_qty'   => $invoice->total_qty,
                            'invoice_amount' => $invoice->total_gross,
                            'grv_qty'       => 0,
                            'grv_amount'    => 0,
                        ]);
                    }
                }
            }
        }

        $creditNotes = CreditNote::select('id', 'total_qty', 'total_gross', 'picking_date', 'customer_id', 'credit_note_date')
            ->where('picking_date', now()->subDay()->format('Y-m-d'))
            ->get();

        if ($creditNotes) {
            foreach ($creditNotes as $creditNote) {
                $ksm = CustomerKamMapping::where('customer_id', $creditNote->customer_id)->first();
                $cr = CustomerRegion::where('customer_id', $creditNote->customer_id)->first();

                if ($ksm && $cr) {

                    $SalesVsGrv = SalesVsGrv::where('date', $creditNote->picking_date)
                        ->where('kam_id', $ksm->kam_id)
                        ->where('zone_id', $cr->zone_id)
                        ->first();

                    $cd = CreditNoteDetail::selectRaw("sum(item_qty) as qty")
                        ->where('credit_note_id', $creditNote->id)
                        ->first();

                    if ($SalesVsGrv) {
                        $SalesVsGrv->update([
                            'grv_qty' => $SalesVsGrv->grv_qty + $creditNote->total_qty,
                            'grv_amount' => $SalesVsGrv->grv_amount + $creditNote->total_gross,
                        ]);
                    } else {

                        SalesVsGrv::create([
                            'date'          => ($creditNote->picking_date === NULL) ? $creditNote->credit_note_date : $creditNote->picking_date,
                            'zone_id'       => $cr->zone_id,
                            'zone_name'     => model($cr->zone, 'name'),
                            'kam_id'        => $ksm->kam_id,
                            'kam_name'      => model($ksm->kam, 'firstname') . ' ' . model($ksm->kam, 'lastname'),
                            'invoice_qty'   => 0,
                            'invoice_amount' => 0,
                            'grv_qty'       => ($cd) ? $cd->qty : 0,
                            'grv_amount'    => $creditNote->total_gross,
                        ]);
                    }
                }
            }
        }
    }

    public function saleunlo()
    {
        $li = LoadItem::get();

        foreach ($li as $l) {
            $sul = Goodreceiptnote::where('grn_number', $l->load_number)->first();
            // pre($sul);
            if ($sul) {
                $sld = Goodreceiptnotedetail::where('good_receipt_note_id', $sul->id)
                    ->where('item_id', $l->item_id)
                    ->where('item_uom_id', $l->item_uom_id)
                    ->first();
                if ($sld) {
                    $eqty = 0;
                    $dqty = 0;
                    if ($sld->reason === "Expiry Return") {
                        $eqty = $sld->qty;
                    }
                    if ($sld->reason === "Damage Return") {
                        $dqty = $sld->qty;
                    }
                    $l->update([
                        'damage_qty' => $dqty,
                        'expiry_qty' => $eqty
                    ]);
                }
            }
        }
    }

    public function merchandiserSupervisorASMUpdate()
    {
        $fileName = $_FILES["merchandiser_super_asm"]["tmp_name"];

        if ($_FILES["merchandiser_super_asm"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($column[1] !== "Supervisor Code") {
                    $salesmanCode = $column[0];
                    $supervisor = $column[1];
                    $asm = $column[2];
                    $nsm = $column[3];

                    $salesman = SalesmanInfo::where('salesman_code', $salesmanCode)->first();
                    $super = User::where('email', $supervisor)->first();
                    $asm = User::where('email', $asm)->first();
                    $nsm = User::where('email', $nsm)->first();

                    if ($salesman && $super && $asm && $nsm) {

                        $salesman->salesman_supervisor = $super->id;
                        $salesman->salesman_supervisor = $asm->id;
                        $salesman->salesman_supervisor = $nsm->id;
                        $salesman->save();
                    }
                }
            }
            return 'done';
        }
    }
}
