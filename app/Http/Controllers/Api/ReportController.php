<?php

namespace App\Http\Controllers\Api;

use URL;
use App\User;
use Carbon\Carbon;
use App\Model\Item;
use App\Model\Trip;
use App\Model\Order;
use App\Model\Invoice;
use App\Model\ItemUom;
use App\Model\Delivery;
use Carbon\CarbonPeriod;
use App\Model\ReasonType;
use App\Model\SalesVsGrv;
use App\Model\DIFOTReport;
use App\Model\GeoApproval;
use App\Model\OrderReport;
use App\Model\DeliveryNote;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Model\ItemMainPrice;
use App\Model\Notifications;
use Illuminate\Http\Request;
use App\Model\DeliveryDetail;
use App\Model\ReturnGrvReport;
use App\Model\Storagelocation;
use App\Exports\DailyCSRFExport;
use App\Exports\ItemReportExport;
use App\Model\VehicleUtilisation;
use App\Model\SalesmanLoadDetails;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exports\GlobalReportExport;
use App\Model\PickingSlipGenerator;
use App\Exports\OrderSCReportExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomerReportExport;
use App\Exports\EstimateReportExport;
use App\Exports\InvoicesReportExport;
use App\Exports\SalesGrvReportExport;
use App\Exports\PalletReportExport;
use App\Exports\SalesmanReportExport;
use App\Exports\DebitnotesReportExport;
use App\Exports\CreditnotesReportExport;
use App\Exports\JourneyPlanReportExport;
use Illuminate\Support\Facades\Validator;
use App\Model\DeliveryDriverJourneyPlan;
use App\Exports\AgingSummaryReportExport;
use App\Exports\OrderDetailsReportExport;
use App\Exports\SalesInvoiceReportExport;
use App\Model\ConsolidateLoadReturnReport;
use App\Exports\PaymentreceivedReportExport;
use App\Exports\ConsolidatedLoadReportExport;
use App\Exports\TruckUtilisationReportExport;
use App\Exports\DriverUtilisationReportExport;
use App\Exports\VehicalUtilisationReportExport;
use App\Exports\ConsolidateLoadReturnReportExport;
use Meneses\LaravelMpdf\Facades\LaravelMpdf as PDF;
use App\Exports\LoadingChartByWarehouseReportExport;
use Illuminate\Support\Collection as collectionMerge;

class ReportController extends Controller
{
    public function sales_by_customer(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $salesbycustomer = DB::table('customer_infos')
            ->join('users', 'users.id', '=', 'customer_infos.user_id', 'left')
            ->join('customer_types', 'customer_types.id', '=', 'customer_infos.customer_type_id', 'left')
            ->join('customer_groups', 'customer_groups.id', '=', 'customer_infos.customer_group_id', 'left')
            ->join('invoices', 'invoices.customer_id', '=', 'customer_infos.id', 'left');

        $salesbycustomer = $salesbycustomer->select(
            'customer_infos.id',
            'users.firstname',
            'users.lastname',
            'users.email',
            'users.mobile',
            'customer_types.customer_type_name as customer_type',
            'customer_groups.group_name as customer_group'
        );
        if ($start_date != '' && $end_date != '') {
            $salesbycustomer = $salesbycustomer->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
        }
        $salesbycustomer = $salesbycustomer->groupBy('customer_infos.id')->groupBy('users.firstname')->groupBy('users.lastname')
            ->groupBy('users.email')->groupBy('users.mobile')->groupBy('customer_type')->groupBy('customer_group')->get();

        $columns = $request->columns;
        if (is_object($salesbycustomer)) {
            foreach ($salesbycustomer as $key => $val) {
                $invoice = Invoice::where('customer_id', $val->id)->get();
                $invoice_count = 0;
                $total_sale = 0;
                $total_sale_with_tax = 0;
                if (is_object($invoice)) {
                    foreach ($invoice as $inv) {
                        $invoice_count = $invoice_count + 1;
                        $total_sale = $total_sale + $inv->total_net;
                        $total_sale_with_tax = $total_sale_with_tax + $inv->grand_total;
                    }
                }
                $salesbycustomer[$key]->invoice_count = $invoice_count;
                $salesbycustomer[$key]->total_sale = $total_sale;
                $salesbycustomer[$key]->total_sale_with_tax = $total_sale_with_tax;
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($salesbycustomer[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($salesbycustomer[$key]->lastname);
                    }
                    if (!in_array('email', $columns)) {
                        unset($salesbycustomer[$key]->email);
                    }
                    if (!in_array('mobile', $columns)) {
                        unset($salesbycustomer[$key]->mobile);
                    }
                    if (!in_array('customer_type', $columns)) {
                        unset($salesbycustomer[$key]->customer_type);
                    }
                    if (!in_array('customer_group', $columns)) {
                        unset($salesbycustomer[$key]->customer_group);
                    }
                    if (!in_array('invoice_count', $columns)) {
                        unset($salesbycustomer[$key]->invoice_count);
                    }
                    if (!in_array('total_sale', $columns)) {
                        unset($salesbycustomer[$key]->total_sale);
                    }
                    if (!in_array('total_sale_with_tax', $columns)) {
                        unset($salesbycustomer[$key]->total_sale_with_tax);
                    }
                } else {
                    unset($salesbycustomer[$key]->email);
                    unset($salesbycustomer[$key]->mobile);
                    unset($salesbycustomer[$key]->customer_type);
                    unset($salesbycustomer[$key]->customer_group);
                }
                unset($salesbycustomer[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $salesbycustomer, [], "Sales by customer listing", $this->success);
        } else {

            $file_name = date('Y-m-d') . '-sales-by-customer.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Sales by cusomer",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $salesbycustomer,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new CustomerReportExport($salesbycustomer, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function sales_by_item(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $salesbyitem = DB::table('items')
            ->join('item_groups', 'item_groups.id', '=', 'items.item_group_id', 'left')
            ->join('item_major_categories', 'item_major_categories.id', '=', 'items.item_major_category_id', 'left')
            ->join('brands', 'brands.id', '=', 'items.brand_id', 'left')
            ->join('invoice_details', 'invoice_details.item_id', '=', 'items.id', 'left')
            ->join('invoices', 'invoices.id', '=', 'invoice_details.invoice_id', 'left');
        $salesbyitem = $salesbyitem->select(
            'items.id',
            'items.item_code',
            'items.item_name',
            'item_groups.name as item_group',
            'item_major_categories.name as item_major_category',
            'brands.brand_name'
        );
        if ($start_date != '' && $end_date != '') {
            $salesbyitem = $salesbyitem->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
        }
        $salesbyitem = $salesbyitem->groupBy('items.id')->groupBy('items.item_code')->groupBy('items.item_name')
            ->groupBy('item_group')->groupBy('item_major_category')->groupBy('brands.brand_name')->get();

        $columns = $request->columns;
        if (is_object($salesbyitem)) {
            foreach ($salesbyitem as $key => $val) {
                $invoice = DB::table('invoices')
                    ->join('invoice_details', 'invoice_details.invoice_id', '=', 'invoices.id', 'left')
                    ->where('invoice_details.item_id', $val->id)
                    ->get();

                $invoice_count = 0;
                $total_sale = 0;
                $total_sale_with_tax = 0;
                if (is_object($invoice)) {
                    foreach ($invoice as $inv) {
                        $invoice_count = $invoice_count + 1;
                        $total_sale = $total_sale + $inv->total_net;
                        $total_sale_with_tax = $total_sale_with_tax + $inv->grand_total;
                    }
                }
                $salesbyitem[$key]->invoice_count = $invoice_count;
                $salesbyitem[$key]->total_sale = $total_sale;
                $salesbyitem[$key]->total_sale_with_tax = $total_sale_with_tax;
                if (count($columns) > 0) {
                    if (!in_array('item_code', $columns)) {
                        unset($salesbyitem[$key]->item_code);
                    }
                    if (!in_array('item_name', $columns)) {
                        unset($salesbyitem[$key]->item_name);
                    }
                    if (!in_array('item_group', $columns)) {
                        unset($salesbyitem[$key]->item_group);
                    }
                    if (!in_array('item_major_category', $columns)) {
                        unset($salesbyitem[$key]->item_major_category);
                    }
                    if (!in_array('brand_name', $columns)) {
                        unset($salesbyitem[$key]->brand_name);
                    }
                    if (!in_array('invoice_count', $columns)) {
                        unset($salesbyitem[$key]->invoice_count);
                    }
                    if (!in_array('total_sale', $columns)) {
                        unset($salesbyitem[$key]->total_sale);
                    }
                    if (!in_array('total_sale_with_tax', $columns)) {
                        unset($salesbyitem[$key]->total_sale_with_tax);
                    }
                } else {
                    unset($salesbyitem[$key]->item_group);
                    unset($salesbyitem[$key]->item_major_category);
                    unset($salesbyitem[$key]->brand_name);
                }
                unset($salesbyitem[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $salesbyitem, [], "Sales by item listing", $this->success);
        } else {
            Excel::store(new ItemReportExport($salesbyitem, $columns), 'item_report.xlsx');
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/item_report.xlsx'));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }
    public function sales_by_salesman(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $salesbysalesman = DB::table('salesman_infos')
            ->join('users', 'users.id', '=', 'salesman_infos.user_id', 'left')
            ->join('routes', 'routes.id', '=', 'salesman_infos.route_id', 'left')
            ->join('salesman_types', 'salesman_types.id', '=', 'salesman_infos.salesman_type_id', 'left')
            ->join('trips', 'trips.salesman_id', '=', 'salesman_infos.id', 'left')
            ->join('invoices', 'invoices.trip_id', '=', 'trips.id', 'left');
        $salesbysalesman = $salesbysalesman->select(
            'salesman_infos.id',
            'users.firstname',
            'users.lastname',
            'users.email',
            'routes.route_name',
            'salesman_types.name as salesman_type'
        );
        if ($start_date != '' && $end_date != '') {
            $salesbysalesman = $salesbysalesman->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
        }
        //$salesbysalesman = $salesbysalesman->get();
        $salesbysalesman = $salesbysalesman->groupBy('salesman_infos.id')->groupBy('users.firstname')->groupBy('users.lastname')
            ->groupBy('users.email')->groupBy('routes.route_name')->groupBy('salesman_type')->get();

        $columns = $request->columns;
        if (is_object($salesbysalesman)) {
            foreach ($salesbysalesman as $key => $val) {
                $invoice_count = 0;
                $total_sale = 0;
                $total_sale_with_tax = 0;
                $trip = Trip::where('salesman_id', $val->id)->get();
                if (is_object($trip)) {
                    foreach ($trip as $trp) {
                        $invoice = Invoice::where('trip_id', $trp->id)->get();
                        if (is_object($invoice)) {
                            foreach ($invoice as $inv) {
                                $invoice_count = $invoice_count + 1;
                                $total_sale = $total_sale + $inv->total_net;
                                $total_sale_with_tax = $total_sale_with_tax + $inv->grand_total;
                            }
                        }
                    }
                }

                $salesbysalesman[$key]->invoice_count = $invoice_count;
                $salesbysalesman[$key]->total_sale = $total_sale;
                $salesbysalesman[$key]->total_sale_with_tax = $total_sale_with_tax;
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($salesbysalesman[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($salesbysalesman[$key]->lastname);
                    }
                    if (!in_array('email', $columns)) {
                        unset($salesbysalesman[$key]->email);
                    }
                    if (!in_array('route_name', $columns)) {
                        unset($salesbysalesman[$key]->route_name);
                    }
                    if (!in_array('salesman_type', $columns)) {
                        unset($salesbysalesman[$key]->salesman_type);
                    }
                    if (!in_array('invoice_count', $columns)) {
                        unset($salesbysalesman[$key]->invoice_count);
                    }
                    if (!in_array('total_sale', $columns)) {
                        unset($salesbysalesman[$key]->total_sale);
                    }
                    if (!in_array('total_sale_with_tax', $columns)) {
                        unset($salesbysalesman[$key]->total_sale_with_tax);
                    }
                } else {
                    unset($salesbysalesman[$key]->email);
                    unset($salesbysalesman[$key]->route_name);
                    unset($salesbysalesman[$key]->salesman_type);
                }
                unset($salesbysalesman[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $salesbysalesman, [], "Sales by customer listing", $this->success);
        } else {
            Excel::store(new SalesmanReportExport($salesbysalesman, $columns), 'sales_by_salesman_report.xlsx');
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/sales_by_salesman_report.xlsx'));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    public function invoice_details(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $invoices = DB::table('invoices')
            ->join('customer_infos', 'customer_infos.id', '=', 'invoices.customer_id', 'left')
            ->join('users', 'users.id', '=', 'customer_infos.user_id', 'left')
            ->join('orders', 'orders.id', '=', 'invoices.order_id', 'left')
            ->join('deliveries', 'deliveries.id', '=', 'invoices.delivery_id', 'left')
            ->join('payment_terms', 'payment_terms.id', '=', 'invoices.payment_term_id', 'left');
        $invoices = $invoices->select(
            'users.firstname',
            'users.lastname',
            'users.email',
            'orders.order_number',
            'deliveries.delivery_number',
            'payment_terms.name as payment_term',
            'invoices.invoice_number',
            'invoices.invoice_date',
            'invoices.invoice_due_date',
            'invoices.total_qty',
            'invoices.total_gross',
            'invoices.total_discount_amount',
            'invoices.total_net',
            'invoices.total_vat',
            'invoices.total_excise',
            'invoices.grand_total',
            'invoices.payment_received'
        );
        if ($start_date != '' && $end_date != '') {
            $invoices = $invoices->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
        }
        $invoices = $invoices->get();

        $columns = $request->columns;
        if (is_object($invoices)) {
            foreach ($invoices as $key => $val) {
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($invoices[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($invoices[$key]->lastname);
                    }
                    if (!in_array('email', $columns)) {
                        unset($invoices[$key]->email);
                    }
                    if (!in_array('order_number', $columns)) {
                        unset($invoices[$key]->order_number);
                    }
                    if (!in_array('delivery_number', $columns)) {
                        unset($invoices[$key]->delivery_number);
                    }
                    if (!in_array('payment_term', $columns)) {
                        unset($invoices[$key]->payment_term);
                    }
                    if (!in_array('invoice_number', $columns)) {
                        unset($invoices[$key]->invoice_number);
                    }
                    if (!in_array('invoice_date', $columns)) {
                        unset($invoices[$key]->invoice_date);
                    }
                    if (!in_array('invoice_due_date', $columns)) {
                        unset($invoices[$key]->invoice_due_date);
                    }
                    if (!in_array('total_qty', $columns)) {
                        unset($invoices[$key]->total_qty);
                    }
                    if (!in_array('total_gross', $columns)) {
                        unset($invoices[$key]->total_gross);
                    }
                    if (!in_array('total_discount_amount', $columns)) {
                        unset($invoices[$key]->total_discount_amount);
                    }
                    if (!in_array('total_net', $columns)) {
                        unset($invoices[$key]->total_net);
                    }
                    if (!in_array('total_vat', $columns)) {
                        unset($invoices[$key]->total_vat);
                    }
                    if (!in_array('total_excise', $columns)) {
                        unset($invoices[$key]->total_excise);
                    }
                    if (!in_array('grand_total', $columns)) {
                        unset($invoices[$key]->grand_total);
                    }
                    if (!in_array('payment_received', $columns)) {
                        unset($invoices[$key]->payment_received);
                    }
                } else {
                    unset($invoices[$key]->firstname);
                    unset($invoices[$key]->lastname);
                    unset($invoices[$key]->total_gross);
                    unset($invoices[$key]->total_discount_amount);
                    unset($invoices[$key]->total_net);
                    unset($invoices[$key]->total_vat);
                    unset($invoices[$key]->total_excise);
                    unset($invoices[$key]->grand_total);
                    unset($invoices[$key]->payment_received);
                }
                unset($invoices[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $invoices, [], "Sales by customer listing", $this->success);
        } else {
            $file_name = date('Y-m-d') . '-invoices_detail_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Invoice Detail",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $invoices,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new InvoicesReportExport($invoices, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function payment_received(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $collections = DB::table('collections')
            ->join('users', 'users.id', '=', 'collections.customer_id', 'left')
            ->join('collection_details', 'collection_details.collection_id', '=', 'collections.id', 'left')
            ->join('invoices', 'invoices.id', '=', 'collection_details.invoice_id', 'left');
        $collections = $collections->select(
            'users.firstname',
            'users.lastname',
            'collections.collection_number',
            'collections.payemnt_type',
            'invoices.invoice_number',
            'collection_details.amount',
            'collection_details.pending_amount',
            'collections.created_at'
        );
        if ($start_date != '' && $end_date != '') {
            $collections = $collections->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
        }
        $collections = $collections->get();

        $columns = $request->columns;
        if (is_object($collections)) {
            foreach ($collections as $key => $val) {
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($collections[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($collections[$key]->lastname);
                    }
                    if (!in_array('collection_number', $columns)) {
                        unset($collections[$key]->collection_number);
                    }
                    if (!in_array('payemnt_type', $columns)) {
                        unset($collections[$key]->payemnt_type);
                    }
                    if (!in_array('invoice_number', $columns)) {
                        unset($collections[$key]->invoice_number);
                    }
                    if (!in_array('amount', $columns)) {
                        unset($collections[$key]->amount);
                    }
                    if (!in_array('pending_amount', $columns)) {
                        unset($collections[$key]->pending_amount);
                    }
                    if (!in_array('created_at', $columns)) {
                        unset($collections[$key]->created_at);
                    }
                } else {
                    unset($collections[$key]->collection_number);
                    unset($collections[$key]->pending_amount);
                    unset($collections[$key]->created_at);
                }
                unset($collections[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $collections, [], "Payment received listing", $this->success);
        } else {

            $file_name = date('Y-m-d') . '-payment_received_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Payment Received",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $collections,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new PaymentreceivedReportExport($collections, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function creditnote_detail(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $credit_notes = DB::table('credit_notes')
            ->join('users', 'users.id', '=', 'credit_notes.customer_id', 'left')
            ->join('credit_note_details', 'credit_note_details.credit_note_id', '=', 'credit_notes.id', 'left')
            ->join('invoices', 'invoices.id', '=', 'credit_notes.invoice_id', 'left')
            ->join('items', 'items.id', '=', 'credit_note_details.item_id', 'left')
            ->join('item_uoms', 'item_uoms.id', '=', 'credit_note_details.item_uom_id', 'left');
        $credit_notes = $credit_notes->select(
            'users.firstname',
            'users.lastname',
            'credit_notes.credit_note_number',
            'credit_notes.credit_note_date',
            'invoices.invoice_number',
            'items.item_name',
            'item_uoms.name as item_uom',
            'credit_note_details.item_qty',
            'credit_note_details.item_price',
            'credit_note_details.item_gross',
            'credit_note_details.item_net',
            'credit_note_details.item_vat'
        );
        if ($start_date != '' && $end_date != '') {
            $credit_notes = $credit_notes->whereBetween('credit_notes.credit_note_date', [$start_date, $end_date]);
        }
        $credit_notes = $credit_notes->get();

        $columns = $request->columns;
        if (is_object($credit_notes)) {
            foreach ($credit_notes as $key => $val) {
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($credit_notes[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($credit_notes[$key]->lastname);
                    }
                    if (!in_array('credit_note_number', $columns)) {
                        unset($credit_notes[$key]->credit_note_number);
                    }
                    if (!in_array('credit_note_date', $columns)) {
                        unset($credit_notes[$key]->credit_note_date);
                    }
                    if (!in_array('invoice_number', $columns)) {
                        unset($credit_notes[$key]->invoice_number);
                    }
                    if (!in_array('item_name', $columns)) {
                        unset($credit_notes[$key]->item_name);
                    }
                    if (!in_array('item_uom', $columns)) {
                        unset($credit_notes[$key]->item_uom);
                    }
                    if (!in_array('item_qty', $columns)) {
                        unset($credit_notes[$key]->item_qty);
                    }
                    if (!in_array('item_price', $columns)) {
                        unset($credit_notes[$key]->item_price);
                    }
                    if (!in_array('item_gross', $columns)) {
                        unset($credit_notes[$key]->item_gross);
                    }
                    if (!in_array('item_net', $columns)) {
                        unset($credit_notes[$key]->item_net);
                    }
                    if (!in_array('item_vat', $columns)) {
                        unset($credit_notes[$key]->item_vat);
                    }
                } else {
                    unset($credit_notes[$key]->item_qty);
                    unset($credit_notes[$key]->item_price);
                    unset($credit_notes[$key]->item_gross);
                    unset($credit_notes[$key]->item_net);
                    unset($credit_notes[$key]->item_vat);
                    unset($credit_notes[$key]->credit_note_date);
                }
                unset($credit_notes[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $credit_notes, [], "Credate note detail listing", $this->success);
        } else {
            $file_name = date('Y-m-d') . '-credit_notes_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Credit Note",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $credit_notes,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new CreditnotesReportExport($credit_notes, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }
    public function debitnote_detail(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $debit_notes = DB::table('debit_notes')
            ->join('users', 'users.id', '=', 'debit_notes.customer_id', 'left')
            ->join('debit_note_details', 'debit_note_details.debit_note_id', '=', 'debit_notes.id', 'left')
            ->join('invoices', 'invoices.id', '=', 'debit_notes.invoice_id', 'left')
            ->join('items', 'items.id', '=', 'debit_note_details.item_id', 'left')
            ->join('item_uoms', 'item_uoms.id', '=', 'debit_note_details.item_uom_id', 'left');
        $debit_notes = $debit_notes->select(
            'users.firstname',
            'users.lastname',
            'debit_notes.debit_note_number',
            'debit_notes.debit_note_date',
            'invoices.invoice_number',
            'items.item_name',
            'item_uoms.name as item_uom',
            'debit_note_details.item_qty',
            'debit_note_details.item_price',
            'debit_note_details.item_gross',
            'debit_note_details.item_net',
            'debit_note_details.item_vat'
        );
        if ($start_date != '' && $end_date != '') {
            $debit_notes = $debit_notes->whereBetween('debit_notes.debit_note_date', [$start_date, $end_date]);
        }
        $debit_notes = $debit_notes->get();

        $columns = $request->columns;
        if (is_object($debit_notes)) {
            foreach ($debit_notes as $key => $val) {
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($debit_notes[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($debit_notes[$key]->lastname);
                    }
                    if (!in_array('debit_note_number', $columns)) {
                        unset($debit_notes[$key]->debit_note_number);
                    }
                    if (!in_array('credit_note_date', $columns)) {
                        unset($debit_notes[$key]->credit_note_date);
                    }
                    if (!in_array('invoice_number', $columns)) {
                        unset($debit_notes[$key]->invoice_number);
                    }
                    if (!in_array('item_name', $columns)) {
                        unset($debit_notes[$key]->item_name);
                    }
                    if (!in_array('item_uom', $columns)) {
                        unset($debit_notes[$key]->item_uom);
                    }
                    if (!in_array('item_qty', $columns)) {
                        unset($debit_notes[$key]->item_qty);
                    }
                    if (!in_array('item_price', $columns)) {
                        unset($debit_notes[$key]->item_price);
                    }
                    if (!in_array('item_gross', $columns)) {
                        unset($debit_notes[$key]->item_gross);
                    }
                    if (!in_array('item_net', $columns)) {
                        unset($debit_notes[$key]->item_net);
                    }
                    if (!in_array('item_vat', $columns)) {
                        unset($debit_notes[$key]->item_vat);
                    }
                } else {
                    unset($debit_notes[$key]->item_qty);
                    unset($debit_notes[$key]->item_price);
                    unset($debit_notes[$key]->item_gross);
                    unset($debit_notes[$key]->item_net);
                    unset($debit_notes[$key]->item_vat);
                    unset($debit_notes[$key]->credit_note_date);
                }
                unset($debit_notes[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $debit_notes, [], "Debit note detail listing", $this->success);
        } else {
            $file_name = date('Y-m-d') . '-debit_notes_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Debit Note Details",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $debit_notes,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new DebitnotesReportExport($debit_notes, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }
    public function estimate_detail(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $estimation = DB::table('estimation')
            ->join('users', 'users.id', '=', 'estimation.customer_id', 'left')
            ->join('estimation_detail', 'estimation_detail.estimation_id', '=', 'estimation.id', 'left')
            ->join('items', 'items.id', '=', 'estimation_detail.item_id', 'left')
            ->join('item_uoms', 'item_uoms.id', '=', 'estimation_detail.item_uom_id', 'left');
        $estimation = $estimation->select(
            'users.firstname',
            'users.lastname',
            'estimation.reference',
            'estimation.estimate_code',
            'estimation.estimate_date',
            'estimation.expairy_date',
            'estimation.subject',
            'items.item_name',
            'item_uoms.name as item_uom',
            'estimation_detail.item_qty',
            'estimation_detail.item_price',
            'estimation_detail.item_grand_total',
            'estimation_detail.item_net',
            'estimation_detail.item_vat'
        );
        if ($start_date != '' && $end_date != '') {
            $estimation = $estimation->whereBetween('estimation.estimate_date', [$start_date, $end_date]);
        }
        $estimation = $estimation->get();

        $columns = $request->columns;
        if (is_object($estimation)) {
            foreach ($estimation as $key => $val) {
                if (count($columns) > 0) {
                    if (!in_array('firstname', $columns)) {
                        unset($estimation[$key]->firstname);
                    }
                    if (!in_array('lastname', $columns)) {
                        unset($estimation[$key]->lastname);
                    }
                    if (!in_array('reference', $columns)) {
                        unset($estimation[$key]->reference);
                    }
                    if (!in_array('estimate_code', $columns)) {
                        unset($estimation[$key]->estimate_code);
                    }
                    if (!in_array('estimate_date', $columns)) {
                        unset($estimation[$key]->estimate_date);
                    }
                    if (!in_array('expairy_date', $columns)) {
                        unset($estimation[$key]->expairy_date);
                    }
                    if (!in_array('subject', $columns)) {
                        unset($estimation[$key]->subject);
                    }
                    if (!in_array('item_name', $columns)) {
                        unset($estimation[$key]->item_name);
                    }
                    if (!in_array('item_uom', $columns)) {
                        unset($estimation[$key]->item_uom);
                    }
                    if (!in_array('item_qty', $columns)) {
                        unset($estimation[$key]->item_qty);
                    }
                    if (!in_array('item_price', $columns)) {
                        unset($estimation[$key]->item_price);
                    }
                    if (!in_array('item_grand_total', $columns)) {
                        unset($estimation[$key]->item_grand_total);
                    }
                    if (!in_array('item_net', $columns)) {
                        unset($estimation[$key]->item_net);
                    }
                    if (!in_array('item_vat', $columns)) {
                        unset($estimation[$key]->item_vat);
                    }
                } else {
                    unset($estimation[$key]->item_qty);
                    unset($estimation[$key]->item_price);
                    unset($estimation[$key]->item_grand_total);
                    unset($estimation[$key]->item_net);
                    unset($estimation[$key]->item_vat);
                }
                unset($estimation[$key]->id);
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $estimation, [], "Estimate detail listing", $this->success);
        } else {
            $file_name = date('Y-m-d') . '-estimate_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Estimate",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $estimation,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new EstimateReportExport($estimation, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function aging_summary(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $customers = DB::table('users');
        $customers = $customers->select('users.id', 'users.firstname', 'users.lastname', 'users.email', 'users.mobile');
        $customers = $customers->where('users.usertype', 2);
        if ($start_date != '' && $end_date != '') {
            $customers = $customers->whereBetween('users.created_at', [$start_date, $end_date]);
        }
        $customers = $customers->get();

        $columns = $request->columns;
        /* foreach($columns as $k=>$v){
        $columns[$k] = str_replace('inv_1_to_15','1-15 Days',$v);
        $columns[$k] = str_replace('inv_16_to_30','16-30 Days',$v);
        $columns[$k] = str_replace('inv_31_to_45','31-45 Days',$v);
        $columns[$k] = str_replace('inv_45_up','> 45 Days',$v);
        } */
        $CustomerCollection = new Collection();
        if (is_object($customers)) {
            foreach ($customers as $key => $val) {
                $start_date_1_15 = date('Y-m-d');
                $end_date_1_15 = date('Y-m-d', strtotime(date('Y-m-d') . ' + 15 days'));
                $inv_1_to_15 = get_invoice_sum($customers[$key]->id, $start_date_1_15, $end_date_1_15);

                $start_date_16_30 = date('Y-m-d');
                $end_date_13_30 = date('Y-m-d', strtotime($start_date_16_30 . ' + 15 days'));
                $inv_16_to_30 = get_invoice_sum($customers[$key]->id, $start_date_16_30, $end_date_13_30);

                $start_date_31_45 = date('Y-m-d');
                $end_date_31_45 = date('Y-m-d', strtotime($start_date_31_45 . ' + 15 days'));
                $inv_31_to_45 = get_invoice_sum($customers[$key]->id, $start_date_31_45, $end_date_31_45);

                $start_date_45_up = date('Y-m-d', strtotime(date('Y-m-d') . ' + 45 days'));
                $inv_45_up = get_invoice_sum($customers[$key]->id, $start_date_45_up);

                $total = ($inv_1_to_15 + $inv_16_to_30 + $inv_31_to_45 + $inv_45_up);

                $CustomerCollection->push((object) [
                    'firstname' => $customers[$key]->firstname,
                    'lastname' => $customers[$key]->lastname,
                    'email' => $customers[$key]->email,
                    'mobile' => $customers[$key]->mobile,
                    'inv_1_to_15' => $inv_1_to_15,
                    'inv_16_to_30' => $inv_16_to_30,
                    'inv_31_to_45' => $inv_31_to_45,
                    'inv_45_up' => $inv_45_up,
                    'total' => $total,
                ]);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $CustomerCollection, [], "Aging summary detail listing", $this->success);
        } else {
            $file_name = date('Y-m-d') . '-aging_summary_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Aging Summary",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $CustomerCollection,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new AgingSummaryReportExport($CustomerCollection, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    /**
     * This is the Consolidated Load report based on salesman load details table
     *
     * @param Type|null $var
     * @return void
     */
    public function consolidatedLoadReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "consolidatedLoadReport");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating consolidated load", $this->unprocessableEntity);
        }

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $salesmanLoad_query = SalesmanLoadDetails::select(
            'salesman_load_details.id',
            'salesman_load_details.warehouse_id',
            'salesman_load_details.van_id',
            'salesman_load_details.item_uom',
            'salesman_load_details.load_date',
            'item_uoms.id as item_uom',
            'item_uoms.name as uom',
            'items.id as item_id',
            'items.item_code',
            'items.item_name',
            'storagelocations.code as storagelocations_code',
            'items.id as item_id',
            DB::raw('SUM(salesman_load_details.load_qty) as total_qty')
        )
            ->leftJoin('items', function ($join) {
                $join->on('items.id', '=', 'salesman_load_details.item_id');
            })
            ->leftJoin('item_uoms', function ($join) {
                $join->on('item_uoms.id', '=', 'salesman_load_details.item_uom');
            })
            ->leftJoin('storagelocations', function ($join) {
                $join->on('storagelocations.id', '=', 'salesman_load_details.storage_location_id');
            })
            ->where('storage_location_id', $request->warehouse_id);

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $salesmanLoad_query->where('load_date', $start_date)->orWhere('change_date', $start_date);
            } else {
                $salesmanLoad_query->whereBetween('load_date', [$start_date, $end_date])->orwhereBetween('change_date',[$start_date, $end_date]);
            }
        }

        $salesmanLoad = $salesmanLoad_query->groupBy('item_id')->orderBy('items.item_code')->get();

        $details = new Collection();
        $count = 0;
        if (count($salesmanLoad)) {
            foreach ($salesmanLoad as $key => $load_detail) {

                $count = $count + 1;

                $qty = 0;
                // $uom = model($load_detail->itemUom, 'name');

                if (model($load_detail, 'total_qty')) {
                    $lower = getLowerQtyUom($load_detail->item_id, $load_detail->item_uom, model($load_detail, 'total_qty'));
                    $sec = getLowerQtyBaseOnSecondryUom($load_detail->item_id, $load_detail->item_uom, model($load_detail, 'total_qty'));
                    $uom = $load_detail->uom;
                    if (isset($lower['UOM'])) {
                        $item_uom = ItemUom::find($lower['UOM']);
                        $uom = $item_uom->name;
                    }
                    $qty = round($sec['Qty'], 2);
                }

                $details->push((object) [
                    "SR_No"             => $count,
                    "Item"              => $load_detail->item_code,
                    "Item_description"  => $load_detail->item_name,
                    "qty"               => $qty,
                    "uom"               => $uom,
                    "sec_qty"           => "",
                    "sec_uom"           => "",
                    "from_location"     => "",
                    "to_location"       => "",
                    "from_lot_serial"   => "",
                    "to_lot_number"     => "",
                    "to_lot_status_code" => "",
                    "load_date"         => Carbon::parse($load_detail->load_date)->format('Y-m-d'),
                    "warehouse"         => $load_detail->storagelocations_code,
                    "is_exported"       => "NO",
                    // "salesman"          => model($load_detail->salesmanInfo, 'salesman_code'),
                ]);
            }
        }

        // if (count($details)) {
        //     $sorted = $details->sortBy("Item");
        //     $detail = $sorted->values()->all();

        //     foreach ($detail as $key => $d) {
        //         $count = $count + 1;
        //         $detail[$key]->SR_No = $count;
        //     }
        // }

        if ($request->export == 0) {
            return prepareResult(true, $details, [], "consolidatedLoad listing", $this->success);
        } else {

            $columns = [
                'SL No',
                "Item",
                "Item Description",
                "Quantity",
                "UOM",
                "SECONDARY_QTY",
                "SEC_UOM",
                "FROM_LOCATION",
                "TO_LOCATION",
                "FROM_LOT_SERIAL",
                "TO_LOT_NUMBER",
                "TO_LOT_STATUS_CODE",
                "LoadDate",
                "Warehouse",
                "IsExported",
            ];

            $file_name = 'consolidated_load.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $sl = Storagelocation::find($request->warehouse_id);

                $data = array(
                    'title' => "Consolidate Load",
                    'w_code' => model($sl, 'code'),
                    'w_name' => model($sl, 'name'),
                    'date' => $start_date,
                    'header' => $columns,
                    'rows' => $details,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                // PDF::loadView(
                //     'html.report_pdf',
                //     $data,
                //     [],
                //     [
                //         'title' => 'Certificate',
                //         'format' => 'A4-L',
                //         'orientation' => 'L'
                //     ]
                // )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new ConsolidatedLoadReportExport($details, $columns), $file_name, '', $this->extensions($request->export_type));
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    /**
     * This is the consolidate Load Return return based on salesman unload load details table
     *
     * @param Type|null $var
     * @return void
     */
    public function consolidateLoadReturn(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "consolidateLoadReturn");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating consolidate load return", $this->unprocessableEntity);
        }

        $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
        $end_date = Carbon::parse($request->end_date)->format('Y-m-d');

        $clr_query = ConsolidateLoadReturnReport::where('storage_location_id', $request->warehouse_id);

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $clr_query->where('load_date', $start_date);
            } else {
                $clr_query->whereBetween('load_date', [$start_date, $end_date]);
            }
        }

        $clrs = $clr_query->orderBy('load_date', 'desc')->get();

        $final_result = new Collection();
        if (count($clrs)) {
            foreach ($clrs as $k => $load_detail) {

                $final_result->push((object) [
                    "SR_No" => $load_detail->SR_No,
                    "Item" => $load_detail->Item,
                    "Item_description" => $load_detail->Item_description,
                    "qty" => $load_detail->qty,
                    "uom" => $load_detail->uom,
                    "sec_qty" => $load_detail->sec_qty,
                    "sec_uom" => $load_detail->sec_uom,
                    "from_location" => $load_detail->from_location,
                    "to_location" => $load_detail->to_location,
                    "from_lot_serial" => $load_detail->from_lot_serial,
                    "to_lot_number" => $load_detail->to_lot_number,
                    "to_lot_status_code" => $load_detail->to_lot_status_code,
                    "load_date" => $load_detail->load_date,
                    "warehouse" => $load_detail->warehouse,
                    "is_exported" => $load_detail->is_exported,
                    "salesman" => $load_detail->salesman,
                ]);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $final_result, [], "Consolidate Load Return listing", $this->success);
        } else {

            $columns = [
                'SL No',
                "Item",
                "Item Description",
                "Quantity",
                "UOM",
                "SECONDARY_QTY",
                "SEC_UOM",
                "FROM_LOCATION",
                "TO_LOCATION",
                "FROM_LOT_SERIAL",
                "TO_LOT_NUMBER",
                "TO_LOT_STATUS_CODE",
                "LoadDate",
                "Warehouse",
                "IsExported",
            ];

            $file_name = 'consolidate_load_return.' . $request->export_type;

            if ($request->export_type == "PDF") {
                $data = array(
                    'title' => "Consolidate Load Return",
                    'header' => $columns,
                    'rows' => $final_result,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {

                Excel::store(new ConsolidateLoadReturnReportExport($final_result, $columns), $file_name, '', $this->extensions($request->export_type));
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function loadingChartByWarehouse(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "loadingChartByWarehouse");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating consolidated load", $this->unprocessableEntity);
        }

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $storage = '';
        $salesman = '';

        // shipping uom upc

        $loaddetails_query = SalesmanLoadDetails::select(
            'salesman_load_details.id',
            'salesman_load_details.warehouse_id',
            'salesman_load_details.van_id',
            'salesman_load_details.item_uom',
            'items.id',
            'items.item_code',
            'items.item_name',
            'items.id as item_id',
            DB::raw('SUM(salesman_load_details.load_qty) as total_qty'),
            DB::raw('SUM(IF((salesman_load_details.change_date IS NULL), 0, salesman_load_details.load_qty)) as change_qty'),
            DB::raw('SUM(IF((salesman_load_details.change_date IS NOT NULL AND salesman_load_details.change_date > " '.$end_date.'"), salesman_load_details.load_qty,0)) as next_qty')
        )
            ->leftJoin('items', function ($join) {
                $join->on('items.id', '=', 'salesman_load_details.item_id');
            });

        // ->leftJoin('item_uoms', function ($join) {
        //     $join->on('item_uoms.id', '=', 'salesman_load_details.item_uom_id');
        // });

        if ($request->warehouse_id) {
            $loaddetails_query->where('storage_location_id', $request->warehouse_id);
        }

        if ($request->van_id) {
            $loaddetails_query->where('van_id', $request->van_id);
        }

        if ($request->salesman_id) {
            $loaddetails_query->where('salesman_id', $request->salesman_id);
        }

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $loaddetails_query->where('load_date', $start_date)->orWhere('change_date', $start_date);
            } else {
                $loaddetails_query->whereBetween('load_date', [$start_date, $end_date])->orWhereBetween('change_date', [$start_date, $end_date]);
            }
        }

        $salesmanLoadDetails = $loaddetails_query->groupBy('item_id')->orderBy('items.item_code')->get();
        
        $detail = new Collection();
        foreach ($salesmanLoadDetails as $key => $details) {
            $item = Item::where('id', $details->item_id)
                ->where('lower_unit_uom_id', $details->item_uom)
                ->get();

            if (count($item) > 0) {
                $finalPCS = $details->total_qty - ($details->change_qty - $details->next_qty);
                $netPCS = $finalPCS + ($details->change_qty - $details->next_qty);
                $dmd_ctn = '0';
                $dmd_pcs = $finalPCS;
                $net_ctn = '0';
                $prv_ctn = '0';
                $prv_pcs = ($details->change_qty - $details->next_qty);
                $dmd_convert_upc = '0';
                $net_pcs = $netPCS;
            } else {
                $upc = null;
                if ($details->total_qty) {
                    $imp = ItemMainPrice::where('item_id', $details->item_id)
                        ->where('item_uom_id', $details->item_uom)
                        ->where('item_shipping_uom', 1)
                        ->first();
                    if ($imp) {
                        $upc = $imp->item_upc;
                    }
                }

            
                $finalDmd = $details->total_qty - ($details->change_qty - $details->next_qty);
                $netDMD = $finalDmd + ($details->change_qty - $details->next_qty);
                $dmd_ctn = $finalDmd;
                $dmd_convert_upc = $upc;
                $dmd_pcs = '0';
                $prv_ctn = ($details->change_qty - $details->next_qty);
                $net_ctn = $netDMD;
                $net_pcs = '0';
                $prv_pcs = '0';
            }

            $detail->push((object) [
                "prod_code" => $details->item_code,
                "prod_desc" => $details->item_name,
                "dmd_lower_upc" => $dmd_convert_upc,
                "prev_rt_ctn" => $prv_ctn,
                "prev_rt_pcs" => $prv_pcs,
                "dmd_ctn" => $dmd_ctn,
                "dmd_pcs" => $dmd_pcs,
                "net_ctn" => $net_ctn,
                "net_pcs" => $net_pcs,
            ]);
        }

        if ($request->export == 0) {
            return prepareResult(true, $detail, [], "loadingchartbywarehouse listing", $this->success);
        } else {
            $columns = [
                'Prod Code',
                "Prod Desc",
                "Conversation",
                "Prv Rt-CTN",
                "Prv Rt.Pcs",
                "Dmd-CTN",
                "Dmd-Pcs",
                "Net-Iss-CTN",
                "Net-Iss-Pcs",
            ];

            $file_name = 'loadingchartbywarehouse.' . $request->export_type;

            if ($request->export_type == "PDF") {

                if ($request->warehouse_id) {
                    $storage = Storagelocation::find($request->warehouse_id);
                }

                $s_code = '';
                $s_name = '';

                if ($request->salesman_id) {
                    $s_code = SalesmanInfo::where('user_id', $request->salesman_id)->first();
                    $s_name = User::find($request->salesman_id);
                }

                $data = array(
                    'title' => "Loading Chart By Warehouse",
                    'header' => $columns,
                    'rows' => $detail,
                    'w_code' => ($storage) ? $storage->code : NULL,
                    'w_name' => ($storage) ? $storage->name : NULL,
                    's_code' => ($s_code) ? $s_code->salesman_code : NULL,
                    's_name' => ($s_name) ? $s_name->getName() : NULL,
                    'date' => $start_date,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new LoadingChartByWarehouseReportExport($detail, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }


    public function orderDetailsReportBackUp(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $order_query = Order::query('*');

        if ($request->warehouse_id) {
            $order_query->where('storage_location_id', $request->warehouse_id);
        }

        if ($request->order_number) {
            $order_query->where('order_number', 'like', "%$request->order_number%");
        }

        if ($request->customer_id) {
            $order_query->where('customer_id', $request->customer_id);
        }

        if ($request->customer_lpo) {
            $order_query->where('customer_lop', 'like', "%$request->customer_lpo%");
        }

        if (is_array($request->item_ids)) {
            $item_ids = $request->item_ids;
            $order_query->whereHas('orderDetails', function ($q) use ($item_ids) {
                $q->whereIn('item_id', $item_ids);
            });
        }

        if ($request->start_date != '' && $request->end_date != '') {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');

            if ($start_date == $end_date) {
                $order_query->where('order_date', $start_date);
            } else {
                $order_query->whereBetween('order_date', [$start_date, $end_date]);
            }
        }

        if ($request->del_start_date != '' && $request->del_end_date != '') {
            $del_start_date = Carbon::parse($request->del_start_date)->format('Y-m-d');
            $del_end_date = Carbon::parse($request->del_end_date)->format('Y-m-d');

            if ($del_start_date == $del_end_date) {
                $order_query->where('delivery_date', $del_start_date);
            } else {
                $order_query->whereBetween('delivery_date', [$del_start_date, $del_end_date]);
            }
        }

        $orders = $order_query->orderBy('order_date', 'desc')
            ->orderBy('order_number', 'desc')
            ->get();

        $details = new Collection();

        if (count($orders)) {
            foreach ($orders as $order) {
                $lineNumber =  0;
                if (count($order->orderDetails)) {
                    foreach ($order->orderDetails as $detail) {

                        if (is_array($request->item_ids)) {
                            if (!in_array($detail->item_id, $request->item_ids)) {
                                continue;
                            }
                        }
                        $lineNumber = $lineNumber + 1;

                        $quantity           = ($detail->item_qty > 0) ? $detail->item_qty : "0"; // any how show only item qty not og_qty
                        $reason_code        = $this->getReasonCode($detail);
                        $reason_name        = $this->getReasonName($reason_code);
                        $secondary_quantity = ($detail->original_item_qty > 0) ? $detail->original_item_qty : "0"; // Only and Only order detail og_original_item_qty
                        $customer_code      = model($order->customerInfo, 'customer_code');
                        $customer_name      = model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname');
                        $item_code          = model($detail->item, 'item_code');
                        $item_name          = model($detail->item, 'item_name');
                        $uom_name           = model($detail->itemUom, 'name');
                        $lob_code           = model($detail->lob, 'lob_code');
                        $pick_number        = $this->getPickUpNumber($order);
                        $unit_price         = $this->getUnitPrice($detail);
                        $unit_cost          = $this->getUnitPrice($detail);
                        $cancel_date        = $this->getCancelDate($detail);
                        $actual_ship_date   = $this->getActualShipDate($detail);
                        $quantity_invoice   = $this->getInvoiceQuantity($detail);
                        $invoice            = $this->getInvoice($detail);
                        $invoice_number     = ($quantity_invoice > 0) ? (($invoice) ? $invoice->invoice_number : "") : "";
                        $doument_type       = ($invoice_number !== "") ? "RI" : "";
                        $invoice_date       = ($quantity_invoice > 0) ? (($invoice) ? $this->generateDate($invoice->invoice_date) : "") : "";
                        $business_unit      = $lob_code;
                        $quantity_ordered   = $detail->original_item_qty;
                        $quantity_shipment  = $this->getShipQuantity($detail);
                        $qty_cancelled      = $this->getCancelQuantity($order, $detail);
                        $shipVSInv          = "0";
                        $delivery_date      = Carbon::parse($detail->delivery_date)->format('d-m-Y');
                        $total_cancel_aed   = "0";
                        $extended_amount    = $this->getExtendedAmount();

                        $d1 = array();
                        if ($request->export == 0) {
                            $d1 = [
                                'uuid' => $detail->uuid
                            ];
                        }

                        $d2 = [
                            'order_number'          => $order->order_number,
                            "order_type"            => 'SA',
                            "order_code"            => $lob_code,
                            "line_number"           => $lineNumber,
                            "sold_to"               => $customer_code,
                            "sold_to_name"          => $customer_name,
                            "2nd_item_number"       => $item_code,
                            "description1"          => $item_name,
                            "quantity"              => $quantity,
                            "uom"                   => $uom_name,
                            "revision_number"       => '0',
                            "revision_reason"       => $reason_name,
                            "secondary_quantity"    => $secondary_quantity, // Calculation
                            "secondary_uom"         => $uom_name,
                            "requested_date"        => $order->delivery_date,
                            "customer_po"           => $order->customer_lop,
                            "ship_to"               => $customer_code,
                            "ship_to_description"   => $customer_name,
                            "original_order_type"   => '',
                            "original_line_number"  => '',
                            "3rd_item_number"       => $item_code,
                            "parent_number"         => '',
                            "pick_number"           => $pick_number, // If Picking is generated
                            "unit_price"            => $unit_price, // Calculation
                            "extended_amount"       => $extended_amount, // Calculation
                            "pricing_uom"           => model($detail->itemUom, 'name'),
                            "order_date"            => Carbon::parse($order->created_at)->format('d-m-Y'),
                            "document_number"       => $invoice_number,
                            "doument_type"          => $doument_type,
                            "document_company"      => $lob_code,
                            "scheduled_pick_date"   => $delivery_date,
                            "actual_ship_date"      => $actual_ship_date,
                            "invoice_date"          => $invoice_date,
                            "cancel_date"           => $cancel_date,
                            "gl_date"               => '',
                            "promised_delivery_date" => $delivery_date,
                            "business_unit"         => $business_unit,
                            "quantity_ordered"      => $quantity_ordered,
                            "quantity_shipped"      => $quantity_shipment,
                            "quantity_invoice"      => $quantity_invoice,
                            "quantity_backordered"  => '0',
                            "quantity_canceled"     => $qty_cancelled,
                            "quantity_invoice_vs_shipped" => $shipVSInv,
                            "price_effective_date"  => $delivery_date,
                            "unit_cost"             => $unit_cost,
                            "reason_code"           => $reason_code,
                            "total_cancel_aed"      => round($total_cancel_aed, 2),
                            "user_created"          => model($detail->orderCreatedUser, 'firstname') . ' ' . model($detail->orderCreatedUser, 'lastname'),
                        ];

                        $ddd = array_merge($d1, $d2);

                        $details->push((object) $ddd);
                    }
                }
            }
        }
    }

    /**
     * Get the Cancel qty
     * 1. Order approval status qual to cancelled 
     * 2. order_detail is_deleted
     * 3. Order difference item_id < og_item_qty
     * 4. Delivery approval status qual to cancelled
     * 5. delivery detail is_deleted by warehouse
     * 6. delivery Detail differance item_qty < og_item_qty by warehouse
     * 7. Driver partial delivered (delivery_detail item_qty - delivery_note qty)
     *
     * @param object $detail
     * @return integer
     */
    protected function getCancelQuantity($order, $detail)
    {
        // 1st condition
        if ($order->approval_status == "Cancelled") {
            return $detail->item_qty;
        }

        // 2nd condition
        if ($detail->is_deleted === 1) {
            return $detail->original_item_qty;
        }

        // 3rd condition
        if ($detail->item_id < $detail->original_item_qty) {
            return $detail->original_item_qty - $detail->item_id;
        }

        $delivery = $this->getDelivery($detail->order_id);

        if ($delivery) {
            // 4th condition
            if ($delivery->approval_status == "Cancel") {
                $delivery_detail = $this->getDeliveryDetails($delivery->id, $detail->uuid);
                if ($delivery_detail) {
                    return $delivery_detail->item_qty;
                }
            }

            $delivery_detail = $this->getDeliveryDetails($delivery->id, $detail->uuid);
            if ($delivery_detail) {
                // 5th condition
                if ($delivery_detail->is_deleted === 1) {
                    return $delivery_detail->original_item_qty;
                }

                // 6th condition
                if ($delivery_detail->item_qty < $delivery_detail->original_item_qty) {
                    return $delivery_detail->original_item_qty - $delivery_detail->item_qty;
                }

                $cancel_qty = DeliveryNote::select(
                    DB::raw("SUM(qty) AS cancel"),
                    'reason_id',
                    'created_at'
                )
                    ->with('reason')
                    ->where('is_cancel', 1)
                    ->where('delivery_id', $delivery->id)
                    ->where('delivery_detail_id', $delivery_detail->id)
                    ->where('item_id', $detail->item_id)
                    ->where('item_uom_id', $detail->item_uom_id)
                    ->first();

                if ($cancel_qty->cancel && $cancel_qty->cancel > 0 && $cancel_qty->cancel != '') {
                    return $cancel_qty->cancel;
                }
            }
        }

        return "0";
    }

    protected function getShipQuantity(object $detail)
    {
        $delivery = $this->getDelivery($detail->order_id);
        if (!$delivery) {
            return "0";
        }

        $load = $this->getSalesmanLoad($delivery->id);
        if ($load) {
            $l_detail = $this->getSalesmanLoadDetail($load->id, $detail);
            if ($l_detail) {
                return $l_detail->load_qty;
            }
        }
        return  "0";
    }

    protected function getinvoiceNumber(object $detail)
    {
        $invoice = $this->getInvoice($detail);
        if ($invoice) {
            return $invoice->invoice_number;
        }
        return "";
    }

    protected function getInvoice(object $detail)
    {
        $delivery = $this->getDelivery($detail);
        if (!$delivery) {
            return "";
        }

        $inv = Invoice::where('delivery_id', $delivery->id)->first();
        if ($inv) {
            return $inv;
        }
    }


    /**
     * Get the Invoice Qty by the delivery note
     *
     * @param integer $delivery_id
     * @param integer $deliver_detail_id
     * @param integer $item_id
     * @param integer $item_uom_id
     * @return float
     */
    private function getInvoiceQuantity(object $detail)
    {

        $delivery = $this->getDelivery($detail->order_id);
        if (!$delivery) {
            return "0";
        }

        $delivery_detail = $this->getDeliveryDetails($delivery->id, $detail->uuid);

        if (!$delivery_detail) {
            return "0";
        }

        $invoice_qty = DeliveryNote::select(
            DB::raw("SUM(qty) AS invoices")
        )
            ->where('delivery_id', $delivery->id)
            ->where('delivery_detail_id', $delivery_detail->id)
            ->where('item_id', $detail->item_id)
            ->where('item_uom_id', $detail->item_uom_id)
            ->where('is_cancel', 0)
            ->first();

        if ($invoice_qty) {
            return ($invoice_qty->invoices > 0) ? $invoice_qty->invoices : "0";
        }
    }

    /**
     * This function return the create load date base on item
     *
     * @param object $detail // order detail
     * @return date
     */
    public function getActualShipDate($detail)
    {
        if ($detail->is_deleted === 1) {
            return "";
        }

        $delivery = $this->getDelivery($detail->order_id);
        if ($delivery) {
            $load = $this->getSalesmanLoad($delivery->id);
            if ($load) {
                $load_detail = $this->getSalesmanLoadDetail($load->id, $detail);
                if ($load_detail) {
                    return $this->generateDate($load->load_date);
                }
            }
        }
    }

    /**
     * This function get salesman load detail base on load id and item_id
     *
     * @param integer $load_id
     * @param object $detail
     * @return object
     */
    public function getSalesmanLoadDetail(int $load_id, object $detail)
    {
        return SalesmanLoadDetails::where('salesman_load_id', $load_id)
            ->where('item_id', $detail->item_id)
            ->where('item_uom', $detail->item_uom_id)
            ->first();
    }

    /**
     * Get the Salesman Load
     *
     * @param integer $delivery_id
     * @return object
     */
    public function getSalesmanLoad(int $delivery_id)
    {
        return SalesmanLoad::where('delivery_id', $delivery_id)->first();
    }

    /**
     * Get order invoice and delivery to cancel or change something
     *
     * @param object $detail
     * @return date
     */
    public function getCancelDate($detail)
    {
        // get date base on order detail
        if ($detail->reason_id) {
            return $this->generateDate($detail->updated_at);
        }

        $delivery = $this->getDelivery($detail->order_id);

        if ($delivery) {
            // get reason from delivery details
            $delivery_detail = $this->getDeliveryDetails($delivery->id, $detail->uuid);
            if ($delivery_detail) {
                if ($delivery_detail->reason_id) {
                    return $this->generateDate($delivery_detail->updated_at);
                }
                // get reason from delivery notes
                $delivery_note = $this->getDeliveryNoteByDeliveryDetail($delivery->id, $delivery_detail->id);
                if ($delivery_note) {
                    if ($delivery_note->reason_id) {
                        return $this->generateDate($delivery_note->updated_at);
                    }
                }
            }
        }
    }

    /**
     * Convert date to YYYY-MM-DD format
     *
     * @param date $data
     * @return date
     */
    private function generateDate($date)
    {
        return Carbon::parse($date)->format("Y-m-d");
    }


    /**
     * You will get the extended amount base on ship qty into unit price
     *
     * @param int $quantity_shipment
     * @param float $unit_price
     * @return float
     */
    public function getExtendedAmount($quantity_shipment, $unit_price)
    {
        return round($quantity_shipment * $unit_price, 2);
    }

    /**
     * Get the price from order detail
     * if we have item original price then take it otherwise take item price     
     *
     * @param object $detail
     * @return float
     */
    public function getUnitPrice($detail)
    {
        return $detail->original_item_price ?? $detail->item_price;
    }

    /**
     * Get the Picking slip number
     *
     * @param object $order
     * @return int
     */
    public function getPickUpNumber($order)
    {
        return model($order->pickingSlipGenerator, 'id');
    }

    private function getReasonName($code)
    {
        if ($code = '') {
            return '';
        }

        $reason = ReasonType::where('code', $code)->first();
        if ($reason) {
            return $reason->name;
        }
    }

    /**
     * How to get Reason from order detail and delivery note also
     *
     * @param object $detail
     * @return string
     */
    protected function getReasonCode($detail)
    {
        // order detail has reason id then return reason id 
        if ($detail->reason_id) {
            return model($detail->reason, 'code');
        }

        // get Delivery
        $delivery = $this->getDelivery($detail->order_id);

        if ($delivery) {
            // get reason from delivery details
            $delivery_detail = $this->getDeliveryDetails($delivery->id, $detail->uuid);
            if ($delivery_detail) {
                if ($delivery_detail->reason_id) {
                    return model($delivery_detail->reason, 'code');
                }
                // get reason from delivery notes
                $delivery_note = $this->getDeliveryNoteByDeliveryDetail($delivery->id, $delivery_detail->id);
                if ($delivery_note) {
                    if ($delivery_note->reason_id) {
                        return model($delivery_note->reason, 'code');
                    }
                }
            }
        }
    }

    protected function getDelivery($order_id)
    {
        if (!$order_id) {
            return "";
        }

        return Delivery::where('order_id', $order_id)->first();
    }

    protected function getDeliveryDetails($delivery_id, $uuid)
    {
        if (!$delivery_id) {
            return "";
        }

        return DeliveryDetail::where('delivery_id', $delivery_id)->where('uuid', $uuid)->first();
    }

    /**
     * get Delivery Note By Delivery Detail id
     *
     * @param [int] $delivery_id
     * @param [int] $delivery_detail_id
     * @return Object
     */
    protected function getDeliveryNoteByDeliveryDetail($delivery_id, $delivery_detail_id)
    {
        return DeliveryNote::where('delivery_id', $delivery_id)
            ->where('delivery_detail_id', $delivery_detail_id)
            ->first();
    }

    public function orderSCReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $order_query = OrderReport::query('*');

        if (is_array($request->warehouse_id)) {
            $order_query->whereIn('storage_location_id',$request->warehouse_id);
            //$order_query->where('storage_location_id', $request->warehouse_id);
        }

        if ($request->order_number) {
            $order_query->where('order_no', 'like', "%$request->order_number%");
        }

        if ($request->customer_lpo) {
            $order_query->where('customer_lop', 'like', "%$request->customer_lpo%");
        }

        if ($request->del_start_date != '' && $request->del_end_date != '') {
            
            if ($request->del_start_date == $request->del_end_date) {
                $order_query->where(function ($order_query) use ($request) {
                    $order_query->where('delivery_date', '=', $request->del_start_date);
                });
            } else {
                $order_query->where(function ($order_query) use ($request) {
                    $order_query->whereBetween('delivery_date',[$request->del_start_date, $request->del_end_date]);
                });
            }
        }
        
        $orderHeader = $order_query->orderBy('delivery_date', 'desc')
            ->orderBy('order_no', 'desc')
            ->get();

        $columns = [
                'Order Number',
                'Order Type',
                "Sold To",
                "Sold To Name",
                "2nd Item Number",
                "Description 1",
                'Line Type',
                'Cancel orders',
                'Actual orders ',
                'Loaded',
                'Cancelled',
                'Invoiced',
                'Spot returns',
                'Secondary UOM',
                'Extended Amount',
                'Order Creation Date',
                'Requested Date',
                'Customer PO',
                'Business Unit',
                'Document Number',
                'Invoice Date',
                'Load Date',
                'Shipment DateTime',
                'On Hold',
                'Driver',
                'Driver Reason',
                'Planned Trip',
                'Actual Trip',
                'Vehicle',
                'Helper1',
                'Helper2',
            ];

            $file_name = 'orderscReport.' . $request->export_type;
                
            Excel::store(new OrderSCReportExport($orderHeader, $columns), $file_name);
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);

    }

    public function orderDetailsReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $order_query = Order::query('*');

        if ($request->warehouse_id) {
            $order_query->where('storage_location_id', $request->warehouse_id);
        }

        if ($request->order_number) {
            $order_query->where('order_number', 'like', "%$request->order_number%");
        }

        if ($request->customer_id) {
            $order_query->where('customer_id', $request->customer_id);
        }

        if ($request->customer_lpo) {
            $order_query->where('customer_lop', 'like', "%$request->customer_lpo%");
        }

        if (is_array($request->item_ids)) {
            $item_ids = $request->item_ids;
            $order_query->whereHas('orderDetails', function ($q) use ($item_ids) {
                $q->whereIn('item_id', $item_ids);
            });
        }

        if ($request->start_date != '' && $request->end_date != '') {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');

            if ($start_date == $end_date) {
                $order_query->where('order_date', $start_date);
            } else {
                $order_query->whereBetween('order_date', [$start_date, $end_date]);
            }
        }

        if ($request->del_start_date != '' && $request->del_end_date != '') {
            $del_start_date = Carbon::parse($request->del_start_date)->format('Y-m-d');
            $del_end_date = Carbon::parse($request->del_end_date)->format('Y-m-d');

            if ($del_start_date == $del_end_date) {
                $order_query->where('delivery_date', $del_start_date);
            } else {
                $order_query->whereBetween('delivery_date', [$del_start_date, $del_end_date]);
            }
        }

        $orderHeader = $order_query->orderBy('order_date', 'desc')
            ->orderBy('order_number', 'desc')
            ->get();

        $details = new Collection();

        if (count($orderHeader)) {
            foreach ($orderHeader as $k => $detail) {
                $line_numbber = 0;
                foreach ($detail->orderDetails as $key => $order_detail) {

                    if (is_array($request->item_ids)) {
                        if (!in_array($order_detail->item_id, $request->item_ids)) {
                            continue;
                        }
                    }

                    $qty_cancelled = "0";
                    $quantity_invoice = '0';
                    $invoice_number = "";
                    $invoice_date = "";
                    $load_date  = "";
                    $delivery_note  = "";
                    $reason = '';
                    $code = '';
                    $quantity_delivery = '0';
                    $quantity_shipment = '0';
                    $cancel_date = '';
                    // ship - invoice
                    $quantity_invoice_vs_shipped = '0';

                    if ($detail->approval_status == 'Cancelled') {
                        $qty_cancelled = $order_detail->original_item_qty;
                        $code = model($detail->reason, 'code');
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
                                if ($code == "") {
                                    $code = ($delivery_note->reason) ? $delivery_note->reason->code : NULL;
                                }
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

                    // last delivery note qty
                    if ($delivery_note) {
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

                        if ($invoice_qty) {
                            $quantity_invoice = $invoice_qty->invoices;
                            // if delivery item_qty more then invoice qty
                            if ($delivery_d) {
                                if ($delivery_d->item_qty > $quantity_invoice) {
                                    $cancel_date = Carbon::parse($delivery_d->updated_at)->format('d-m-Y');
                                }

                                if ($delivery_d->is_deleted == 1) {
                                    $qty_cancelled = $delivery_d->original_item_qty;
                                }
                            }

                            // if invoice qty less then 0
                            if ($quantity_invoice < 1 && $quantity_invoice == "") {
                                $invoice_number = '';
                                $invoice_date = '';
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

                            if ($code == "") {
                                $code = model($cancel_qty->reason, 'code');
                            }
                            // if delivery item qty and invoice qty + cancel qty is same then invoice number and date visible
                            if ($delivery_d->item_qty != ($quantity_invoice + $qty_cancelled)) {
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


                    if ($order_detail->is_deleted === 1 || $quantity_shipment < 1) {
                        $invoice_number = "";
                    }

                    $d1 = array();
                    if ($request->export == 0) {
                        $d1 = [
                            'uuid' => $detail->uuid
                        ];
                    }

                    $shipVSInv = ((($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') !== 0) ? (($quantity_invoice > 0) ? $quantity_invoice : '0') - (($quantity_shipment > 0) ? $quantity_shipment : '0') : '0';

                    $d2 = [
                        'order_number'          => $detail->order_number,
                        "order_type"            => 'SA',
                        "order_code"            => model($detail->lob, 'lob_code'),
                        "line_number"           => $line_numbber,
                        "sold_to"               => model($detail->customerInfo, 'customer_code'),
                        "sold_to_name"          => model($detail->customer, 'firstname') . ' ' . model($detail->customer, 'lastname'),
                        "2nd_item_number"       => model($order_detail->item, 'item_code'),
                        "description1"          => model($order_detail->item, 'item_name'),
                        "quantity"              => $order_detail->item_qty,
                        // "quantity"              => ($detail->approval_status == "Cancelled") ? $order_detail->original_item_qty : (($order_detail->item_qty == "0.00") ? $order_detail->original_item_qty : $order_detail->item_qty),
                        "uom"                   => model($order_detail->itemUom, 'name'),
                        "revision_number"       => '0',
                        "revision_reason"       => $reason,
                        "secondary_quantity"    => ($detail->approval_status == "Cancelled") ? $order_detail->original_item_qty : (($order_detail->item_qty == "0.00") ? $order_detail->original_item_qty : $order_detail->item_qty),
                        "secondary_uom"         => model($order_detail->itemUom, 'name'),
                        "requested_date"        => $detail->delivery_date,
                        "customer_po"           => $detail->customer_lop,
                        "ship_to"               => model($detail->customerInfo, 'customer_code'),
                        "ship_to_description"   => model($detail->customer, 'firstname') . '' . model($detail->customer, 'lastname'),
                        "original_order_type"   => '',
                        "original_line_number"  => '',
                        "3rd_item_number"       => model($order_detail->item, 'item_code'),
                        "parent_number"         => '',
                        "pick_number"           => ($load_date != "") ? ($order_detail->is_deleted != 1) ? ($pg) ? $pg->id : "" : "" : "",
                        "unit_price"            => ($order_detail->original_item_price > 0) ? $order_detail->original_item_price : $order_detail->item_price,
                        // "extended_amount"       => round($extended_amount, 2),
                        "extended_amount"       => ($quantity_shipment > 0) ? round($quantity_shipment * $order_detail->original_item_price, 2) : "0",
                        "pricing_uom"           => model($order_detail->itemUom, 'name'),
                        "order_date"            => Carbon::parse($detail->created_at)->format('d-m-Y'),
                        "document_number"       => $invoice_number,
                        "doument_type"          => ($invoice_number != "") ? 'RI' : "",
                        "document_company"      => model($detail->lob, 'lob_code'),
                        "scheduled_pick_date"   => Carbon::parse($detail->delivery_date)->format('d-m-Y'),
                        "actual_ship_date"      => ($order_detail->is_deleted != 1) ? $load_date : "",
                        "invoice_date"          => ($invoice_number !== "") ? $invoice_date : "",
                        "cancel_date"           => $cancel_date,
                        "gl_date"               => '',
                        "promised_delivery_date" => $detail->delivery_date,
                        "business_unit"         => $business_unit,
                        "quantity_ordered"      => $order_detail->original_item_qty,
                        // "quantity_ordered"      => ($detail->approval_status == "Cancelled") ? $order_detail->original_item_qty : (($order_detail->item_qty == "0.00") ? $order_detail->original_item_qty : $order_detail->item_qty),
                        "quantity_shipped"      => ($quantity_shipment > 0) ? $quantity_shipment : '0',
                        "quantity_invoice"      => ($quantity_invoice > 0) ? $quantity_invoice : '0',
                        "quantity_backordered"  => '0',
                        "quantity_canceled"     => ($qty_cancelled > 0) ? $qty_cancelled : '0',
                        // "quantity_invoice_vs_shipped" => ($quantity_invoice_vs_shipped > 0) ? '-' . $quantity_invoice_vs_shipped : '0',
                        "quantity_invoice_vs_shipped" => ($shipVSInv !== 0) ? "$shipVSInv" : '0',
                        "price_effective_date"  => $detail->delivery_date,
                        "unit_cost"             => ($order_detail->original_item_price > 0) ? $order_detail->original_item_price : $order_detail->item_price,
                        "reason_code"           => $code,
                        "total_cancel_aed"      => round($total_cancel_aed, 2),
                        "user_created"          => model($detail->orderCreatedUser, 'firstname') . ' ' . model($detail->orderCreatedUser, 'lastname'),
                        "status"                => $detail->approval_status,
                    ];

                    $ddd = array_merge($d1, $d2);

                    $details->push((object) $ddd);
                }
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $details, [], "orderReport listing", $this->success);
        } else {

            $columns = [
                'Order Number',
                "Order Type",
                "Order Code",
                "Line Number",
                "Sold To",
                "Sold To Name",
                "2nd Item Number",
                "Description 1",
                "Quantity",
                "UOM",
                "Revision Number",
                "Revision Reason",
                "Secondary Quantity",
                "Secondary UOM",
                "Requested Date",
                "Customer PO",
                "Ship To",
                "Ship To Description",
                "Original Order Type",
                "Original Line Number",
                "3rd Item Number",
                "Parent Number",
                "Pick Number",
                "Unit Price",
                "Extended Amount",
                "Pricing UOM",
                "Order Date",
                "Document Number",
                "Doument Type",
                "Document Company",
                "Scheduled Pick Date",
                "Actual Ship Date",
                "Invoice Date",
                "Cancel Date",
                "G/L Date",
                "Promised Delivery Date",
                "Business Unit",
                "Quantity Ordered",
                "Quantity Shipped",
                "Qty Invoice",
                "Quantity Backordered",
                "Quantity Canceled",
                "Qty Invoice vs Shipped",
                "Price Effective Date",
                "Unit Cost",
                "REASON CODE",
                "Total Cancel AED",
                "Transaction Originator",
                "Status",
            ];

            $file_name = 'orderDetailsReport.' . $request->export_type;

            if ($request->export_type == "PDF") {
                $data = array(
                    'title' => "Order Details Report",
                    'header' => $columns,
                    'rows' => $details,
                    'date' => $start_date ?? date('Y-m-d'),
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new OrderDetailsReportExport($details, $columns), $file_name, '', $this->extensions($request->export_type));
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function driverUtilisation(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $vehicle_utilisation_query = VehicleUtilisation::select([
            DB::raw(
                'zone_name,
                    salesman_code,
                    salesman_name,
                    COUNT(vehicle_code) AS trucks_assigned,
                    COUNT(trip_number) AS trips_travelled,
                    SUM(order_qty) AS loaded_qty,
                    SUM(load_qty) AS invoice_qty',
            ),
            DB::raw('round((SUM(load_qty)/SUM(order_qty) * 100), 2) AS delivery_rate'),
            DB::raw('round((SUM(invoice_count)/SUM(customer_count) * 100), 2) AS on_time_delivery_rate'),
            DB::raw('round((SUM(pod_submit)/SUM(order_count) * 100), 2) AS pod_submit'),
            DB::raw('round((COUNT(trip_number) * 480),2) AS capacity_trip'),
            DB::raw('round((SUM(load_qty)/(COUNT(trip_number) * 480)) * 100, 2) AS utilization'),
            DB::raw('round(COUNT(trip_number)/COUNT(vehicle_code)) AS trip_truck')
        ]);

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $vehicle_utilisation_query->where('transcation_date', $request->start_date);
            } else {
                $vehicle_utilisation_query->whereBetween('transcation_date', [$request->start_date, $request->end_date]);
            }
        }

        if (!$request->report_type) {
                if($request->report_type != '')
                {
                    $vehicle_utilisation_query->where('salesman_type',$request->report_type);
                }else{
                    $vehicle_utilisation_query->where('salesman_type',$request->report_type);
                }
           
               
        }else{
            $vehicle_utilisation_query->where('salesman_type',$request->report_type);
        }

        if ($request->salesman_id != '') {
            $vehicle_utilisation_query->where('salesman_id', $request->salesman_id);
        }

        if (!$request->region_id) {
            $vehicle_utilisation = $vehicle_utilisation_query->groupBy('salesman_id')
                ->orderBy('transcation_date', 'desc')
                ->get();
        } else {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', $request->region_id)
                ->groupBy('salesman_id')
                ->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $vehicle_utilisation, [], "Truck Utilisation", $this->success);
        } else {

            $columns = [
                'Zone',
                'File no',
                "Name",
                "Trucks assigned",
                "Trips travelled",
                'Loaded QTY',
                'Invoiced QTY',
                'Delivery Success rate %',
                'On time delivery rate %',
                'POD submission  rate %',
                'Capacity on this Truck',
                'Truck Utilization',
                'Trip / Trucks',
            ];

            $file_name = 'driver-utilization.' . $request->export_type;

                
            Excel::store(new DriverUtilisationReportExport($vehicle_utilisation, $columns), $file_name);
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);

        }

    }

    
    public function truckUtilisation(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $vehicle_utilisation_query = VehicleUtilisation::select([
            DB::raw(
                'transcation_date,
                zone_name,
                salesman_code,
                salesman_name,
                trip_number,
                vehicle_code as vehicle_code,
				COUNT(vehicle_code) AS no_of_vehical,
                SUM(customer_count) AS windows_to_delivered,
                SUM(less_delivery_count) AS windoes_less_case,
                SUM(order_count) AS no_orders,
                SUM(order_qty) AS order_qty,
                SUM(load_qty) AS delivery_qty,
                SUM(invoice_count) AS windows_delivered',
            ),
            DB::raw('round((SUM(invoice_count)/SUM(customer_count) * 100), 2) AS windows_delivered_per'),
            DB::raw('round(SUM(less_delivery_count) / SUM(customer_count) * 100, 2) AS windows_less_delivery'),
            DB::raw('SUM(vehicle_capacity) AS capacity_day'),
            DB::raw('IF(round(SUM(invoice_qty) / (8 * 60) * 100, 2) > 100 , 100, round(SUM(invoice_qty) / (8 * 60) * 100, 2)) AS Utilazation'),
            DB::raw('round(SUM(invoice_qty)/SUM(customer_count)) AS avgcaswindow'),
            DB::raw('round(SUM(invoice_qty)/480, 2) AS trip'),
            DB::raw("IF(round(start_km) > 0, start_km, 0) as start_km"),
            DB::raw("IF(end_km > 0, end_km, 0) as end_km"),
            DB::raw("IF(diesel > 0, diesel, 0) as diesel")
        ]);

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $vehicle_utilisation_query->where('transcation_date', $request->start_date);
            } else {
                $vehicle_utilisation_query->whereBetween('transcation_date', [$request->start_date, $request->end_date]);
            }
        }

        $vehicle_utilisation_query->where('salesman_type', '1');
        if ($request->vehicle_id != '') {
            $vehicle_utilisation_query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->salesman_id != '') {
            $vehicle_utilisation_query->where('salesman_id', $request->salesman_id);
        }

        if (!$request->region_id) {
            $vehicle_utilisation = $vehicle_utilisation_query->groupBy('id')
                ->orderBy('transcation_date', 'desc')
                ->get();
        } else {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', $request->region_id)
                ->groupBy('id')
                ->orderBy('transcation_date', 'desc')
                ->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $vehicle_utilisation, [], "Truck Utilisation", $this->success);
        } else {


            $columns = [
                'Date',
                'Region Name',
                "Salesan Code",
                "Salesan Name",
                "Trip",
                'Vehicles Number',
                'Vehicles Count',
                'Window to Delivery',
                'Windows 10 <= cases',
                'No of Orders',
                'Orders qty',
                'Delivered qty',
                'Windows Delivered',
                'Windows Delivered %',
                'Windows <= 10 cases',
                // 'Overall Capicity',
                'Capacity on this day',
                'Utilization (1,2)',
                'Avg Cases/ Window',
                'Trip / Trucks',
                'start km',
                'end km',
                'diesel',
            ];

            $file_name = 'truck-utilization.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Truck Utilization",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $vehicle_utilisation,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf_truck_utilisation',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new TruckUtilisationReportExport($vehicle_utilisation, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function vehical_Utilisation(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $vehicle_utilisation_query = VehicleUtilisation::select([
            DB::raw(
                'zone_name as region_name,
				sum(`order_qty`) as total_volume_orderd,
				SUM(`invoice_qty`) as total_volume_delivered,
				count(DISTINCT `vehicle_id`) as no_of_vehical,
				count(`trip_number`) as no_of_trips,
				SUM(customer_count) AS no_of_windows'
            ),
            DB::raw('round(SUM(customer_count)/count(`trip_number`)) AS avg_windows_delivered'),
            DB::raw('IF(round(SUM(invoice_qty) / (8 * 60) * 100, 2) > 100 , 100, round(SUM(invoice_qty) / (8 * 60) * 100, 2)) AS Utilazation'),
            DB::raw('round(SUM(invoice_qty)/SUM(customer_count)) AS avgcaswindow'),
            DB::raw('round(SUM(invoice_qty)/480, 2) AS trip'),
            DB::raw('IFNULL(trip_utilization(1,zone_id,transcation_date,transcation_date),"0") AS trip_1_utilization'),
            DB::raw('IFNULL(trip_utilization(2,zone_id,transcation_date,transcation_date),"0") AS trip_2_utilization'),
            DB::raw('IFNULL(trip_utilization(3,zone_id,transcation_date,transcation_date),"0") AS trip_3_utilization')
        ]);

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $vehicle_utilisation_query->where('transcation_date', $request->start_date);
            } else {
                $vehicle_utilisation_query->whereBetween('transcation_date', [$request->start_date, $request->end_date]);
            }
        }


        if ($request->vehicle_id != '') {
            $vehicle_utilisation_query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->salesman_id != '') {
            $vehicle_utilisation_query->where('salesman_id', $request->salesman_id);
        }

        if (!$request->region_id) {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', "!=", "")
                ->groupBy('zone_id')
                ->get();
        } else {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', $request->region_id)
                ->groupBy('zone_id')
                ->get();
        }
        //print_r($vehicle_utilisation);
        if ($request->export == 0) {
            return prepareResult(true, $vehicle_utilisation, [], "vehical Utilisation", $this->success);
        } else {


            $columns = [
                'Region Name',
                'Total Valume Orderd',
                'Total volume delivered',
                'No of Vehical',
                'No of Trips',
                'No of Windows',
                'Window to Delivery',
                'Utilazation(1,2)',
                'avg case window',
                'avg_trips',
                'Trip 1 Utilization',
                'Trip 2 Utilization',
                'Trip 3 Utilization',

            ];

            $file_name = date('Y-m-d') . '-vehical-utilization.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Vehical Utilization",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $vehicle_utilisation,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf_truck_utilisation',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new VehicalUtilisationReportExport($vehicle_utilisation, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function csrfReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $csrf_report_query = VehicleUtilisation::select([
            DB::raw(
                'zone_name as region_name,
                SUM(order_qty) AS totalorders,
                SUM(invoice_qty) AS totalinvoiced,
                SUM(cancel_qty) AS totalcancel'
            ),
            DB::raw('round((SUM(invoice_qty) / SUM(order_qty) * 100), 2) AS cfr'),
        ]);

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $csrf_report_query->where('transcation_date', $request->start_date);
            } else {
                $csrf_report_query->whereBetween('transcation_date', [$request->start_date, $request->end_date]);
            }
        }

        if ($request->region_id) {
            $csrf_report_query->where('zone_id', $request->region_id)
                ->groupBy('zone_id');
        } else {
            $csrf_report_query->groupBy('zone_id', 'transcation_date');
        }

        $csrf_report = $csrf_report_query->orderBy('transcation_date', 'desc')->get();

        if ($request->export == 0) {
            return prepareResult(true, $csrf_report, [], "CSRF Report", $this->success);
        } else {
            $columns = [
                'Region',
                'Total Orders',
                'Total Invoiced',
                'Total Cancelled',
                'CFR%',
            ];

            $file_name = 'daily-csrf.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Daily CSRF",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $csrf_report,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new DailyCSRFExport($csrf_report, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function salesQuantity(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
        $end_date = Carbon::parse($request->end_date)->format('Y-m-d');

        $invoice_query = DB::table('invoices')->select(
            'items.item_code',
            'items.item_name',
        )

            ->leftJoin('invoice_details', function ($join) {
                $join->on('invoice_details.invoice_id', '=', 'invoices.id');
            })
            ->leftJoin('items', function ($join) {
                $join->on('items.id', '=', 'invoice_details.item_id');
            })
            ->leftJoin('item_uoms', function ($join) {
                $join->on('item_uoms.id', '=', 'items.lower_unit_uom_id')
                    ->where('items.lower_unit_uom_id', '!=', 0);
            })
            ->groupBy('invoice_details.item_id')
            ->selectRaw("SUM(invoice_details.lower_unit_qty) as qty");

        $invoice_query->where('items.organisation_id', $request->user()->organisation_id)
            ->whereNull('invoices.deleted_at');

        if ($request->van_id) {
            $invoice_query->where('invoice_details.van_id', $request->van_id);
        }

        if ($request->region_id) {
            $salesman_ids = getSalesmanIds("region", $request->region_id);
            $invoice_query->whereIn('invoices.salesman_id', $salesman_ids);
        }

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $invoice_query->where('invoices.invoice_date', $start_date);
            } else {
                $invoice_query->whereBetween('invoices.invoice_date', [$start_date, $end_date]);
            }
        }

        $invoices = $invoice_query->get();

        if ($request->export == 0) {
            return prepareResult(true, $invoices, [], "Sales Quantity listing", $this->success);
        } else {

            $columns = [
                'Item Code',
                "Item Name",
                "Qty",
            ];

            $file_name = 'salesInvoice.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Sales Invoice",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $invoices,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new SalesInvoiceReportExport($invoices, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;

        if ($type == "getFilter") {
            $validator = \Validator::make($input, [
                'division' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "getFilterSalesman") {
            $validator = \Validator::make($input, [
                'division' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "consolidatedLoadReport") {
            $validator = \Validator::make($input, [
                'warehouse_id' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "loadingChartByWarehouse") {
            $validator = \Validator::make($input, [
                // 'warehouse_id' => 'required',
                // 'van_id' => 'required',
                // 'salesman_id' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "getCustomerByDevision") {
            $validator = \Validator::make($input, [
                'division' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function salesGrvReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if ($request->start_date != '' && $request->end_date != '') {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');
        } else if ($request->start_date != '') {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $end_date = Carbon::parse($request->start_date)->format('Y-m-d');
        } else {
            $end_date = Carbon::now()->format('Y-m-d');
            $start_date = Carbon::parse($end_date)->subDays(6)->format('Y-m-d');
        }

        $dateRange = CarbonPeriod::create($start_date, $end_date);

        $details = new Collection();

        foreach ($dateRange as $key => $date) {

            $get_date = $date->format(\DateTime::ATOM);
            $get_date = Carbon::parse($get_date)->format('Y-m-d');

            $invoice_query = DB::table('invoices')->select(
                'invoices.invoice_date as date',
                DB::raw('SUM(invoices.grand_total) as sales')

            )->groupBy('invoices.invoice_date')->where('invoices.invoice_date', $get_date)
                ->where('invoices.organisation_id', $request->user()->organisation_id);

            if ($request->van_id) {
                $invoice_query->where('invoices.van_id', $request->van_id);
            }
            if ($request->region) {
                $salesman_ids = getSalesmanIds("region", $request->region);
                $invoice_query->whereIn('invoices.salesman_id', $salesman_ids);
            }
            $invoices = $invoice_query->first();

            $credit_notes_query = DB::table('credit_notes')
                ->select(
                    'credit_notes.credit_note_date as date',
                    DB::raw('SUM(credit_notes.grand_total) as credit_note')
                )
                ->groupBy('credit_notes.credit_note_date')
                ->where('credit_notes.credit_note_date', $get_date)
                ->where('credit_notes.organisation_id', $request->user()->organisation_id);

            if ($request->van_id) {
                $credit_notes_query->where('credit_notes.van_id', $request->van_id);
            }

            if ($request->region_id) {
                $salesman_ids = getSalesmanIds("region", $request->region_id);
                $credit_notes_query->whereIn('credit_notes.salesman_id', $salesman_ids);
            }
            $credit_notes = $credit_notes_query->first();

            $pr = 0;
            $get_date = $date->format(\DateTime::ATOM);
            $get_date = Carbon::parse($get_date)->format('Y-m-d');
            $inv = (isset($invoices) && $invoices->sales ? $invoices->sales : '0');
            $grv = (isset($credit_notes) && $credit_notes->credit_note ? $credit_notes->credit_note : '0');

            if ($grv > 0 && $inv > 0) {
                $pr = round($grv / $inv, 2);
            } else {
                $pr = '0';
            }

            $details->push((object) [
                "date" => $get_date,
                "sales" => $inv,
                "grv" => $grv,
                "%" => $pr,
            ]);
        }

        if ($request->export == 0) {
            return prepareResult(true, $details, [], "SalesGrvReport listing", $this->success);
        } else {

            $columns = [
                'Date',
                "Sales",
                "GRV",
                "%",

            ];

            $file_name = 'salesGrv.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Sales Grv",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $details,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new SalesGrvReportExport($details, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function difot(Request $request)
    {
        $difot_query = DIFOTReport::select('report_date', 'region_code', DB::raw('AVG(difot) as percentage'));

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $difot_query->where('report_date', $request->start_date);
            } else {
                $difot_query->whereBetween('report_date', [$request->start_date, $request->end_date]);
            }
        }

        if ($request->region_id) {
            $difot_query->where('region_id', $request->region_id);
        }

        $difots = $difot_query->get();

        if ($request->export == 0) {
            return prepareResult(true, $difots, [], "difots listing", $this->success);
        } else {

            $columns = [
                'Date',
                "Region",
                "Percentage",
            ];

            $t = time();

            $file_name = $t . '_difot_report.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Difot",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $difots,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new GlobalReportExport($difots, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    private function extensions($extensions_type)
    {
        if ($extensions_type == 'XLSX') {
            return \Maatwebsite\Excel\Excel::XLSX;
        } else if ($extensions_type == 'CSV') {
            return \Maatwebsite\Excel\Excel::CSV;
        } else if ($extensions_type == 'PDF') {
            return \Maatwebsite\Excel\Excel::MPDF;
        } else if ($extensions_type == 'XLS') {
            return \Maatwebsite\Excel\Excel::XLS;
        }
    }

    public function reportPlanVisit(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "journeyPlanVisit");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating return grv report.", $this->unprocessableEntity);
        }

        $journeyPlanVisit = DeliveryDriverJourneyPlan::select('date', 'delivery_driver_id', DB::raw('COUNT(customer_id) as total_customer'), DB::raw('SUM(is_visited) as actual_visit'), 'customer_id')
            ->with(
                'merchandiser',
                'salesman_infos'
            );

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $journeyPlanVisit->where('date', $request->start_date);
            } else {
                $journeyPlanVisit->whereBetween('date', [$request->start_date, $request->end_date]);
            }
        }

        if ($request->driver_id) {
            $journeyPlanVisit->where('delivery_driver_id', $request->driver_id);
        }

        $journeyPlanVisit->groupBy('date', 'delivery_driver_id')
            ->orderBy('date', 'desc');

        $planVisit = $journeyPlanVisit->get();

        $details = new Collection();

        if (count($planVisit)) {
            foreach ($planVisit as $k => $detail) {
                $per = ($detail->actual_visit / $detail->total_customer) * 100;
                $details->push((object) [
                    "date" => $detail->date,
                    "merchandiser_name" => $detail->merchandiser->firstname,
                    "merchandiser_code " => $detail->salesman_infos->salesman_code,
                    "planned_count" => $detail->total_customer,
                    "actual_visite" => $detail->actual_visit,
                    "percentage" => ($per > 1 ? $per : '0'),

                ]);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $details, [], "Delivery Driver JP listing", $this->success);
        } else {

            $columns = [
                'Date',
                "Merchandiser Name",
                "Merchandiser Code",
                "Planned Count",
                "Actual Visite",
                "Percentage",
            ];

            $file_name = 'journeyPlan.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Delivery Driver Route Plan",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $details,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new JourneyPlanReportExport($details, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function returnGrvReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "returnGrvReport");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating return grv report.", $this->unprocessableEntity);
        }

        $return_grv_query = ReturnGrvReport::select('date', 'reason_name', DB::raw("SUM(qty) as quantity"));

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $return_grv_query->where('date', $request->start_date);
            } else {
                $return_grv_query->whereBetween('date', [$request->start_date, $request->end_date]);
            }
        }

        $returnGrvHeader = $return_grv_query->groupBy('reason_id')
            ->orderBy('date', 'desc')
            ->get();

        $details = new Collection();

        if (count($returnGrvHeader)) {
            foreach ($returnGrvHeader as $k => $detail) {

                $details->push((object) [
                    "date" => $detail->date,
                    "reason_name" => $detail->reason_name,
                    "qty" => $detail->quantity,

                ]);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $details, [], "returnGrvReport listing", $this->success);
        } else {

            $columns = [
                'Date',
                "Reason Name",
                "Qty",
            ];

            $file_name = 'returnGrvReport.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Return GRV",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $details,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf2',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new GlobalReportExport($details, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    public function salesVSGrvReport(Request $request)
    {
        if ($request->report_type == 1) {
            $d = 'kam_name as KSM_NAME';
        }

        if ($request->report_type == 2) {
            $d = DB::raw('CONCAT(MONTHNAME(date),- YEAR(date)) AS MONTH');
        }

        $saleVSgrv = SalesVsGrv::select(
            $d,
            DB::raw('sum(invoice_qty) as invoice_qty'),
            DB::raw('sum(grv_qty) as grv_qty'),
            DB::raw('round(sum(grv_qty) / sum(invoice_qty) * 100, 2) as grv_per'),
            DB::raw('sum(invoice_amount) as invoice_amount'),
            DB::raw('sum(grv_amount) as grv_amount'),
            DB::raw('round(sum(grv_amount) / sum(invoice_amount) * 100, 2) as grv_amount_per'),
        );

        // report type : 1 KSM
        if ($request->report_type == 1) {
            if (is_array($request->kam_ids) && sizeof($request->kam_ids) > 1) {
                $saleVSgrv->whereIn('kam_id', $request->kam_ids);
            }
        }

        if ($request->start_date != "" && $request->end_date != "") {
            if ($request->start_date == $request->end_date) {
                $saleVSgrv->where('date', $request->start_date);
            } else {
                $e_date = Carbon::parse($request->end_date)->format('Y-m-d');
                $saleVSgrv->whereBetween('date', [$request->start_date, $e_date]);
            }
        }

        // report type : 1 KSM
        if ($request->report_type == 1) {
            $saleVSgrvs = $saleVSgrv->groupBy('KSM_NAME')->orderBy('KSM_NAME')->get();
        }

        // report type : 2 Monthly
        if ($request->report_type == 2) {
            $saleVSgrvs = $saleVSgrv->groupBy(DB::raw('MONTH(date)'))->orderBy('date')->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $saleVSgrvs, [], "Data successfully", $this->success);
        } else {

            $columns = [
                ($request->report_type == 1) ? 'KSM Name' : "Date",
                'Invoiced Qty',
                'Invoiced Amount',
                'GRVs Qty',
                'GRVs Amount',
                'Total GRV Per',
                'Total GRV Amount Per'
            ];

            $file_name = date('Y-m-d') . '-sales-vs-grv.' . $request->export_type;

            Excel::store(new GlobalReportExport($saleVSgrvs, $columns), $file_name);
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    public function vehical_Utilisation_yearly(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $vehicle_utilisation_query = VehicleUtilisation::select([
            DB::raw('CONCAT(MONTHNAME(vehicle_utilisations.transcation_date),- YEAR(vehicle_utilisations.transcation_date)) AS month'),
            DB::raw(
                'sum(`order_qty`) as total_volume_orderd,
				SUM(`invoice_qty`) as total_volume_delivered,
				count(DISTINCT `vehicle_id`) as no_of_vehical,
				count(`trip_number`) as no_of_trips,
				SUM(customer_count) AS no_of_windows'
            ),
            DB::raw('round(SUM(customer_count)/count(`trip_number`)) AS avg_windows_delivered'),
            DB::raw('IF(round(SUM(invoice_qty) / (8 * 60) * 100, 2) > 100 , 100, round(SUM(invoice_qty) / (8 * 60) * 100, 2)) AS Utilazation'),
            DB::raw('round(SUM(invoice_qty)/SUM(customer_count)) AS avgcaswindow'),
            DB::raw('round(SUM(invoice_qty)/480, 2) AS trip'),
            DB::raw('IFNULL(trip_utilization(1,zone_id,transcation_date,transcation_date),"0") AS trip_1_utilization'),
            DB::raw('IFNULL(trip_utilization(2,zone_id,transcation_date,transcation_date),"0") AS trip_2_utilization'),
            DB::raw('IFNULL(trip_utilization(3,zone_id,transcation_date,transcation_date),"0") AS trip_3_utilization')


        ]);

        if ($request->start_date != '' && $request->end_date != '') {
            if ($request->start_date == $request->end_date) {
                $vehicle_utilisation_query->where('transcation_date', $request->start_date);
            } else {
                $vehicle_utilisation_query->whereBetween('transcation_date', [$request->start_date, $request->end_date]);
            }
        }


        if ($request->vehicle_id != '') {
            $vehicle_utilisation_query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->salesman_id != '') {
            $vehicle_utilisation_query->where('salesman_id', $request->salesman_id);
        }

        if (!$request->region_id) {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', "!=", "")
                ->groupBy(DB::raw('MONTH(vehicle_utilisations.transcation_date)'))
                ->get();
        } else {
            $vehicle_utilisation = $vehicle_utilisation_query->where('zone_id', $request->region_id)
                ->groupBy(DB::raw('MONTH(vehicle_utilisations.transcation_date)'))
                ->get();
        }
        //print_r($vehicle_utilisation);
        if ($request->export == 0) {
            return prepareResult(true, $vehicle_utilisation, [], "vehical Utilisation", $this->success);
        } else {


            $columns = [
                'Month Name',
                'Total Valume Orderd',
                'Total volume delivered',
                'No of Vehical',
                'No of Trips',
                'No of Windows',
                'Window to Delivery',
                'Utilazation(1,2)',
                'avg case window',
                'avg_trips',
                'Trip 1 Utilization',
                'Trip 2 Utilization',
                'Trip 3 Utilization',

            ];

            $file_name = date('Y-m-d') . '-vehical-utilization.' . $request->export_type;

            if ($request->export_type == "PDF") {

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                $data = array(
                    'title' => "Vehical Utilization",
                    'w_code' => "",
                    'w_name' => "",
                    'date' => $request->start_date,
                    'header' => $columns,
                    'rows' => $vehicle_utilisation,
                );

                $pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

                PDF::loadView(
                    'html.report_pdf_truck_utilisation',
                    $data
                )->save($pdfFilePath);

                $pdfFilePath = url('uploads/pdf/' . $file_name);
                $result['file_url'] = $pdfFilePath;

                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            } else {
                Excel::store(new VehicalUtilisationMonthlyReportExport($vehicle_utilisation, $columns), $file_name);
                $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
                return prepareResult(true, $result, [], "Data successfully exported", $this->success);
            }
        }
    }

    // public function loadUtilization(Request $request)
    // {
    //     if (!$this->isAuthorized) {
    //         return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
    //     }

    //     $start_date = Carbon::parse($request->start_date);
    //     $end_date = Carbon::parse($request->end_date);
    //     $totalDays = $end_date->diffInDays($start_date) + 1;
    //     $period = new CarbonPeriod($start_date->startOfMonth(), '1 month', $end_date);

    //     $overallData = [
    //         'start_date' => $start_date->toDateString(),
    //         'end_date' => $end_date->toDateString(),
    //         'total_days' => $totalDays,
    //     ];

    //     $monthlyData = [];

    //     foreach ($period as $dt) {
    //         $month_start = $dt->copy();
    //         $month_end = $dt->endOfMonth();
    
    //         if ($month_end->greaterThan($end_date)) {
    //             $month_end = $end_date->copy();
    //         }
    
    //         $monthData = [
    //             'month' => $month_start->format('M-Y'),
    //             'trips_this_month' => 0, // This will sum up the trips for all regions
    //             'days_in_month' => $month_end->diffInDays($month_start) + 1,
    //         ];
    
    //         foreach ($request->region_id as $regionId) {
    //             $aggregate_data = VehicleUtilisation::where('zone_id', $regionId)
    //                 ->whereBetween('transcation_date', [$month_start, $month_end])
    //                 ->selectRaw('
    //                     SUM(trip_number) as total_trips,
    //                     SUM(load_qty) as total_load_qty,
    //                     SUM(vehicle_capacity) as total_vehicle_capacity,
    //                     SUM(invoice_qty) as cases_delivered
    //                 ')
    //                 ->first();
    
    //             $totalTrips = optional($aggregate_data)->total_trips ?? 0;
    //             $totalLoadQty = optional($aggregate_data)->total_load_qty ?? 0;
    //             $totalVehicleCapacity = optional($aggregate_data)->total_vehicle_capacity ?? 0;
    //             $casesDelivered = optional($aggregate_data)->cases_delivered ?? 0;
    
    //             $tripsPerDay = $monthData['days_in_month'] > 0 ? $totalTrips / $monthData['days_in_month'] : 0;
    //             $loadUtilization = $totalVehicleCapacity > 0 ? ($totalLoadQty / $totalVehicleCapacity) * 100 : 0;
    
    //             $monthData['trips_per_day_' . $regionId] = round($tripsPerDay, 2);
    //             $monthData['load_utilization_' . $regionId] = round($loadUtilization, 2);
    //             $monthData['cases_delivered_' . $regionId] = $casesDelivered;
    //             $monthData['trips_this_month'] += $totalTrips; // Sum up the trips for all regions
    //         }
    
    //         $monthlyData[] = $monthData;
    //     }
        
    //     foreach ($request->region_id as $regionId) {
    //         $aggregate_data = VehicleUtilisation::where('zone_id', $regionId)
    //             ->whereBetween('transcation_date', [$start_date, $end_date])
    //             ->selectRaw('
    //                 SUM(trip_number) as total_trips,
    //                 SUM(load_qty) as total_load_qty,
    //                 SUM(vehicle_capacity) as total_vehicle_capacity,
    //                 SUM(invoice_qty) as cases_delivered
    //             ')
    //             ->first();
    
    //         $totalTrips = optional($aggregate_data)->total_trips ?? 0;
    //         $totalLoadQty = optional($aggregate_data)->total_load_qty ?? 0;
    //         $totalVehicleCapacity = optional($aggregate_data)->total_vehicle_capacity ?? 0;
    //         $casesDelivered = optional($aggregate_data)->cases_delivered ?? 0;
    
    //         $tripsPerDay = $overallData['total_days'] > 0 ? $totalTrips / $overallData['total_days'] : 0;
    //         $loadUtilization = $totalVehicleCapacity > 0 ? ($totalLoadQty / $totalVehicleCapacity) * 100 : 0;
    
    //         $overallData['total_trips_' . $regionId] = $totalTrips;
    //         $overallData['trips_per_day_' . $regionId] = round($tripsPerDay, 2);
    //         $overallData['load_utilization_' . $regionId] = round($loadUtilization, 2);
    //         $overallData['cases_delivered_' . $regionId] = $casesDelivered;
    //     }
    
    //     return response()->json([
    //         'overall' => $overallData,
    //         'monthly' => $monthlyData
    //     ]);
    // }
    public function loadUtilization(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "User not authenticate."], "User not authenticate.", $this->unauthorized);
        }

        $start_date = Carbon::parse($request->start_date);
        $end_date = Carbon::parse($request->end_date);
        $totalDays = $end_date->diffInDays($start_date) + 1;
        $period = new CarbonPeriod($start_date->startOfMonth(), '1 month', $end_date);

        $overallData = [
            'start_date' => $start_date->toDateString(),
            'end_date' => $end_date->toDateString(),
            'total_days' => $totalDays,
        ];

        $monthlyData = [];

        foreach ($period as $dt) {
            $month_start = $dt->copy();
            $month_end = $dt->endOfMonth();
    
            if ($month_end->greaterThan($end_date)) {
                $month_end = $end_date->copy();
            }
    
            $monthData = [
                'month' => $month_start->format('M-Y'),
                'trips_this_month' => 0, // This will sum up the trips for all regions
                'days_in_month' => $month_end->diffInDays($month_start) + 1,
            ];

            $regionIds = $request->region_id;

            if(empty($regionIds)){
                $uniqueRegionIds = VehicleUtilisation::query()
                ->distinct()
                ->pluck('zone_id');
                $regionIds = $uniqueRegionIds->toArray();
            }
    
            foreach ($regionIds as $regionId) {
                $aggregate_data = VehicleUtilisation::where('zone_id', $regionId)
                    ->whereBetween('transcation_date', [$month_start, $month_end])
                    ->selectRaw('
                        SUM(trip_number) as total_trips,
                        SUM(load_qty) as total_load_qty,
                        SUM(vehicle_capacity) as total_vehicle_capacity,
                        SUM(invoice_qty) as cases_delivered
                    ')
                    ->first();
    
                $totalTrips = optional($aggregate_data)->total_trips ?? 0;
                $totalLoadQty = optional($aggregate_data)->total_load_qty ?? 0;
                $totalVehicleCapacity = optional($aggregate_data)->total_vehicle_capacity ?? 0;
                $casesDelivered = optional($aggregate_data)->cases_delivered ?? 0;
    
                $tripsPerDay = $monthData['days_in_month'] > 0 ? $totalTrips / $monthData['days_in_month'] : 0;
                $loadUtilization = $totalVehicleCapacity > 0 ? ($totalLoadQty / $totalVehicleCapacity) * 100 : 0;
    
                $monthData['trips_per_day_' . $regionId] = round($tripsPerDay, 2);
                $monthData['load_utilization_' . $regionId] = round($loadUtilization, 2);
                $monthData['cases_delivered_' . $regionId] = $casesDelivered;
                $monthData['trips_this_month'] += $totalTrips; // Sum up the trips for all regions
            }
    
            $monthlyData[] = $monthData;
        }
        
        foreach ($regionIds as $regionId) {
            $aggregate_data = VehicleUtilisation::where('zone_id', $regionId)
                ->whereBetween('transcation_date', [$start_date, $end_date])
                ->selectRaw('
                    SUM(trip_number) as total_trips,
                    SUM(load_qty) as total_load_qty,
                    SUM(vehicle_capacity) as total_vehicle_capacity,
                    SUM(invoice_qty) as cases_delivered
                ')
                ->first();
    
            $totalTrips = optional($aggregate_data)->total_trips ?? 0;
            $totalLoadQty = optional($aggregate_data)->total_load_qty ?? 0;
            $totalVehicleCapacity = optional($aggregate_data)->total_vehicle_capacity ?? 0;
            $casesDelivered = optional($aggregate_data)->cases_delivered ?? 0;
    
            $tripsPerDay = $overallData['total_days'] > 0 ? $totalTrips / $overallData['total_days'] : 0;
            $loadUtilization = $totalVehicleCapacity > 0 ? ($totalLoadQty / $totalVehicleCapacity) * 100 : 0;
    
            $overallData['total_trips_' . $regionId] = $totalTrips;
            $overallData['trips_per_day_' . $regionId] = round($tripsPerDay, 2);
            $overallData['load_utilization_' . $regionId] = round($loadUtilization, 2);
            $overallData['cases_delivered_' . $regionId] = $casesDelivered;
        }
    
        return response()->json([
            'overall' => $overallData,
            'monthly' => $monthlyData
        ]);
    }

    public function geoApprovalReport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $rules_1 = [
            'start_date' => 'required',
            'end_date'  => 'required',
        ];

        $validator = Validator::make($input, $rules_1);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate add salesman load", $this->unauthorized);
        }

        $start_date = $request->start_date;
        $end_date = $request->end_date;       
        $salesman_id = $request->salesman_id;

        $geoApprovals = GeoApproval::with([
            'customer',
            'customer.customerInfo',
            'salesman',
            'salesman.salesmanInfo',
            'supervisor',
            // 'route',
        ])
            ->select(
                'salesman_id',
                // 'route_id',
                'supervisor_id',
                'customer_id',
                'salesman_lat',
                'salesman_long',
                'customer_lat',
                'customer_long',
                'radius',
                // 'type',
                'status'
            );

        // if (!empty($request->route)) {
        //     $geoApprovals->whereIn('route_id', $route);
        // }
        if (!empty($request->salesman_id)) {
            $geoApprovals->whereIn('salesman_id', $salesman_id);
        }

        if ($start_date != '' && $end_date != '') {
            $geoApprovals->whereBetween('date', [$start_date, $end_date])->get();
        }

        // $geoApproval = $geoApprovals->whereIn('type', array('checkin', 'checkout'))->get();
        $geoApproval = $geoApprovals->get();

        $palletReturns = new collectionMerge();
        foreach ($geoApproval as $return) {
            $palletReturns->push([
                'customerCode' => optional(optional($return->customer)->customerInfo)->customer_code,
                'customerName' => optional($return->customer)->firstname . ' ' . optional($return->customer)->lastname,
                // 'routeCode' => optional($return->route)->route_code,
                // 'routeName' => optional($return->route)->route_name,
                'salesmanCode' => optional(optional($return->salesman)->salesmanInfo)->salesman_code,
                'salesmanName' => optional($return->salesman)->firstname . ' ' . optional($return->salesman)->lastname,
                'supervisorName' => optional($return->supervisor)->firstname . ' ' . optional($return->supervisor)->lastname,
                'customer_lat' => $return->customer_lat,
                'customer_long' => $return->customer_long,
                'salesman_lat' => $return->salesman_lat,
                'salesman_long' => $return->salesman_long,
                'radius' => $return->radius,
                // 'type' => $return->type,
                'status' => $return->status,
            ]);
        }

        if ($request->export == 1) {
            $columns = array(
                'Site Code',
                'Site Name',
                // 'Route Code',
                // 'Route Name',
                'Salesman Code',
                'Salesman Name',
                'Supervisor',
                'Customer Lat',
                'Customer Long',
                'Salesman Lat',
                'Salesman Long',
                'Radius',
                // 'Type',
                'Approval Status',
            );
            $time = time();
            $org_id = $request->user()->organisation_id;
            $type = 'csv';
            Excel::store(new PalletReportExport($palletReturns, $columns), 'export/' . $org_id . '_' . $time . '_geo_approval.' . $type);
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/' . $org_id . '_' . $time . '_geo_approval.' . $type));

            return prepareResult(true, $result, [], "Geo Approval data successfully exported!", $this->success);
        } else {
            return prepareResult(true, $palletReturns, [], "Geo Approval Report!", $this->success);
        }
    }

    public function geoApprovalListing(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        try {
            $notifications = Notifications::with(
                'geoApproval:id,uuid,salesman_id,salesman_lat,salesman_long,date,status,request_reason,reason,radius',
                'geoApproval.salesman:id,firstname,lastname'
            )
            ->where('type', 'Geo Approval')
            ->orderByDesc('id')
            ->paginate((!empty($request->page_size) ? $request->page_size : 10));

            $data = $notifications->items();
            $pagination = [
                'current_page' => $notifications->currentPage(),
                'total_pages' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total_items' => $notifications->total(),
                'next_page_url' => $notifications->nextPageUrl(),
                'prev_page_url' => $notifications->previousPageUrl()
            ];

            if (!empty($notifications) || !is_null($notifications)) {
                return prepareResult(true, $data, [], "Geo Approvals from Notification Listing!", $this->success, $pagination);
            }
        } catch (\Throwable $th) {
            return prepareResult(false, $th->getMessage(), [], "Something Went Wrong!!!", $this->internal_server_error);
        }
    }
}
