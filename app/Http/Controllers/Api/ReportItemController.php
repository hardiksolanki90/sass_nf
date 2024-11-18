<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Invoice;
use Illuminate\Support\Facades\DB;
use Meneses\LaravelMpdf\Facades\LaravelMpdf as PDF;
use URL;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ItemReportExport;
use App\Exports\ConsolidatedLoadReportExport;
use App\Exports\ConsolidateLoadReturnReportExport;
use App\Exports\CfrRegionReportExport;
use App\Exports\GlobalReportExport;
use App\Exports\LoadingChartByWarehouseReportExport;
use App\Exports\LoadingChartFinalByRouteReportExport;
use App\Exports\OrderDetailsReportExport;
use App\Exports\SalesGrvReportExport;
use App\Exports\SalesInvoiceReportExport;
use App\Model\ConsolidateLoadReturnReport;
use App\Model\DeliveryDetail;
use App\Model\DIFOTReport;
use App\Model\Item;
use App\Model\Order;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\Grvreport;
use App\Model\SalesmanInfo;
use App\Model\Storagelocation;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ReportItemController extends Controller
{
	public function item_report(Request $request)
	{
		if (!$this->isAuthorized) {
			return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
		}

		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$location_id = $request->warehouse_id;

		$salesbyitem = DB::table('load_item')
			->join('items', 'items.id', '=', 'load_item.item_id', 'left');

			$salesbyitem = $salesbyitem->select(
				'items.id',
				'items.item_code',
				'items.item_name',
				'load_item.dmd_lower_upc as dmd_lower_upc',
				DB::raw('sum(load_item.prv_load_qty) as p_ref_pack'),
				DB::raw('sum(load_item.damage_qty) as dmg_pcs'),
				DB::raw('sum(load_item.expiry_qty) as exp_pcs'),
				DB::raw('sum(load_item.loadqty) as dmd_packs'),
				DB::raw('sum(load_item.on_hold_qty) as N_exp_pc'),
				DB::raw('sum(load_item.sales_qty) as N_sales_packs'),
				DB::raw('(sum(load_item.unload_qty) - sum(load_item.on_hold_qty)) as g_ret_pack'),
				// 'load_item.van_code',
				// 'storagelocations.name as warehouse_name',
				// 'storagelocations.code as warehouse_code'
			)
				->addSelect(DB::raw('"0" as p_ref_pc'))
				->addSelect(DB::raw('"0" as dmd_pcs'))
				->addSelect(DB::raw('"0" as N_sales_pc'))
				->addSelect(DB::raw('"0" as g_ret_pcs'));

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$salesbyitem = $salesbyitem->where('load_item.report_date', $start_date);
			} else {
				$salesbyitem = $salesbyitem->whereBetween('load_item.report_date', [$start_date, $end_date]);
			}
		}

		if ($location_id != '') {
			$salesbyitem = $salesbyitem->where('load_item.storage_location_id', $location_id);
		}

		if ($request->salesman_id != '') {
			$salesbyitem = $salesbyitem->where('load_item.salesman_id', $request->salesman_id);
		}

		$salesbyitem = $salesbyitem->groupBy('items.id')
			->groupBy('items.item_code')
			// ->groupBy('items.item_name')
			->groupBy('van_code')
			->groupBy('storage_location_id')
			->orderBy('items.item_code', 'asc')
			->get();

		if ($request->export == 0) {
			return prepareResult(true, $salesbyitem, [], "Sales by item listing", $this->success);
		} else {

			$columns = [
				"PROD CODE",
				"PROD DESC",
				"CONVERSE",
				"P.RET PACKS",
				"P.RET PIECES",
				"DMD PACKS",
				"DMD PIECES",
				"G.RET PACKS",
				"G.RET PIECES",
				"DMG PIECES",
				"EXPY PIECES",
				"ON HOLD PACKS",
				"N.SALES PACKS",
				"N.SALES PIECES"
			];

			$file_name = 'loading_chart_final_by_route.' . $request->export_type;

			if ($request->export_type == "PDF") {

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				$storage = Storagelocation::find($request->warehouse_id);

				$s_code = "";
				$s_name = "";

				if ($request->salesman_id) {
					$s_code = SalesmanInfo::where('user_id', $request->salesman_id)->first();
					$s_name = User::find($request->salesman_id);
				}

				$data = array(
					'title' => "Loading Chart Final By Route",
					'w_code' => ($storage) ? $storage->code : "",
					'w_name' => ($storage) ? $storage->name : "",
					'date' => $request->start_date,
					'header' => $columns,
					'rows' => $salesbyitem,
					's_code' => ($s_code) ? $s_code->salesman_code : NULL,
					's_name' => ($s_name) ? $s_name->getName() : NULL,
				);

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				PDF::loadView(
					'html.report_pdf_route_by',
					$data
				)->save($pdfFilePath);

				$pdfFilePath = url('uploads/pdf/' . $file_name);
				$result['file_url'] = $pdfFilePath;

				return prepareResult(true, $result, [], "Data successfully exported", $this->success);
			} else {

				if (count($salesbyitem)) {
					foreach ($salesbyitem as $key => $item) {
						unset($salesbyitem[$key]->id);
					}
				}
				Excel::store(new LoadingChartFinalByRouteReportExport($salesbyitem, $columns), $file_name);
				$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
				return prepareResult(true, $result, [], "Data successfully exported", $this->success);
			}
		}
	}

	/** This is the GRV report based on Date and KSM from grv_report details table
	 *
	 * @param Type|date,ksmid $var
	 * @return void
	 * */
	public function grv_report(Request $request)
	{
		//-------------
		$input = $request->json()->all();


		$s_date = $input['start_date'];
		$e_date = $input['end_date'];
		$k_id = $input['ksm_id'];

		$returnbyksm = DB::table('grvreports');

		$returnbyksm = $returnbyksm->select(
			'grvreports.id as id',
			'grvreports.ksm_id as kmsid',
			'grvreports.ksm_name as ksmname',
			'grvreports.reason as reason',
			'grvreports.tran_date as rdate',
			'grvreports.qty as qty',
			'grvreports.amount as amount'
		);
		if ($s_date != '' && $e_date != '') {
			$returnbyksm = $returnbyksm->whereBetween('grvreports.tran_date', array("$s_date", "$e_date"));
		}
		if ($k_id != '') {
			$returnbyksm = $returnbyksm->whereIn('grvreports.ksm_id', array("$k_id"));
		}
		$returnbyksm = $returnbyksm->get();


		if ($request->export == 0) {
			return prepareResult(true, $returnbyksm, [], "GRV by KSM listing", $this->success);
		} else {

			$columns = [
				'id',
				'kmsid',
				'KSM Name',
				'Reason',
				'Transaction Date',
				'qty',
				'amount'
			];

			$file_name = 'grv_by_ksm.' . $request->export_type;

			if ($request->export_type == "PDF") {

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				$data = array(
					'title' => "GRV by KSM",
					'w_code' => "",
					'w_name' => "",
					'date' => $request->start_date,
					'header' => $columns,
					'rows' => $returnbyksm,
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
				Excel::store(new GlobalReportExport($returnbyksm, $columns), $file_name);
				$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
				return prepareResult(true, $result, [], "Data successfully exported", $this->success);
			}
		}
	}

	public function spot_return(Request $request)
	{
		//-------------
		$input = $request->json()->all();


		$s_date = $input['start_date'];
		$e_date = $input['end_date'];
		$k_id = $input['ksm_id'];

		$returnbyksm = DB::table('spot_reports');

		// $returnbyksm = $returnbyksm->select(
		// 	'spot_reports.id as id',
		// 	'spot_reports.ksm_id as kmsid',
		// 	'spot_reports.ksm_name as ksmname',
		// 	'spot_reports.reason as reason',
		// 	'spot_reports.tran_date as rdate',
		// 	'spot_reports.amount as amount'
		// );
		$returnbyksm->select([  DB::raw(
		   'spot_reports.tran_date as rdate,
			spot_reports.ksm_name as ksmname,
			spot_reports.reason as reason'
		),
		DB::raw('SUM(spot_reports.qty) as qty'),
		DB::raw('spot_reports.amount as amount'),
	]);

		if ($s_date != '' && $e_date != '') {
			$returnbyksm = $returnbyksm->whereBetween('spot_reports.tran_date', array("$s_date", "$e_date"));
		}
		if ($k_id != '') {
			$returnbyksm = $returnbyksm->whereIn('spot_reports.ksm_id', array("$k_id"));
		}
		$returnbyksm = $returnbyksm->orderBy('spot_reports.ksm_id', 'DESC');
		$returnbyksm = $returnbyksm->groupBy(['spot_reports.ksm_name', 'spot_reports.reason'])->get();


		if ($request->export == 0) {
			return prepareResult(true, $returnbyksm, [], "Spot report by KSM listing", $this->success);
		} else {

			$columns = [
				'Date',
				'KSM Name',
				'Reason',
				'qty',
				'amount'
			];

			$file_name = 'grv_by_ksm.' . $request->export_type;

			if ($request->export_type == "PDF") {

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				$data = array(
					'title' => "GRV by KSM",
					'w_code' => "",
					'w_name' => "",
					'date' => $request->start_date,
					'header' => $columns,
					'rows' => $returnbyksm,
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
				Excel::store(new GlobalReportExport($returnbyksm, $columns), $file_name);
				$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
				return prepareResult(true, $result, [], "Data successfully exported", $this->success);
			}
		}
	}
	public function cancel_return(Request $request)
	{
		//-------------
		$input = $request->json()->all();


		$s_date = $input['start_date'];
		$e_date = $input['end_date'];
		$zone_id = $input['zone_id'];

		$returnbyksm = DB::table('daily_cancel_orders');

		$returnbyksm = $returnbyksm->select(
			'daily_cancel_orders.id as id',
			'daily_cancel_orders.ksm_id as kmsid',
			'daily_cancel_orders.ksm_name as ksmname',
			'daily_cancel_orders.zone_id as zone_id',
			'daily_cancel_orders.zone_name as zone_name',
			'daily_cancel_orders.reason_name as reason',
			'daily_cancel_orders.date as rdate',
			'daily_cancel_orders.qty as qty',
			'daily_cancel_orders.amount as amount'
		);
		if ($s_date != '' && $e_date != '') {
			$returnbyksm = $returnbyksm->whereBetween('daily_cancel_orders.date', array("$s_date", "$e_date"));
		}
		if ($k_id != '') {
			$returnbyksm = $returnbyksm->whereIn('daily_cancel_orders.ksm_id', array("$k_id"));
		}
		if ($zone_id != '') {
			$returnbyksm = $returnbyksm->whereIn('daily_cancel_orders.zone_id', array("$zone_id"));
		}
		$returnbyksm = $returnbyksm->groupBy('daily_cancel_orders.ksm_id')->get();


		if ($request->export == 0) {
			return prepareResult(true, $returnbyksm, [], "Cancel Return report by KSM listing", $this->success);
		} else {

			$columns = [
				'id',
				'kmsid',
				'KSM Name',
				'zone id',
				'Zone Name',
				'Reason',
				'Transaction Date',
				'qty',
				'amount'
			];

			$file_name = 'cancel_by_zone.' . $request->export_type;

			if ($request->export_type == "PDF") {

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				$data = array(
					'title' => "Cancle by zone",
					'w_code' => "",
					'w_name' => "",
					'date' => $request->start_date,
					'header' => $columns,
					'rows' => $returnbyksm,
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
				Excel::store(new GlobalReportExport($returnbyksm, $columns), $file_name);
				$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
				return prepareResult(true, $result, [], "Data successfully exported", $this->success);
			}
		}
	}


	private function grv_report_insert($request)
	{

		//-------------
		$grvrepotins = new Grvreport;
		//--------------
		$item_sec_uom =  Grvreport::where('reason', $request->reason)
			->where('tran_date',  $request->tran_date)
			->where('ksm_id',  $request->ksm_id)
			->first();
		//	echo count($item_sec_uom);
		if (is_object($item_sec_uom)) {
			$grvrepotins->qty          = $grvrepotins->qty + $request->qty;
			$grvrepotins->amount          = $grvrepotins->amount + $request->amount;
		} else {
			$grvrepotins->tran_date       = (!empty($request->tran_date)) ? $request->tran_date : null;
			$grvrepotins->ksm_id          = (!empty($request->ksm_id)) ? $request->ksm_id : null;
			$grvrepotins->ksm_name              = (!empty($request->ksm_id)) ? $request->ksm_id : null;
			$grvrepotins->reason       = (!empty($request->reason)) ? $request->reason : null;
			$grvrepotins->qty          = (!empty($request->qty)) ? $request->qty : null;
			$grvrepotins->amount          = (!empty($request->amount)) ? $request->amount : null;
		}
		//--------------

		$grvrepotins->save();
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

		$salesmanLoad_query = SalesmanLoad::where('storage_location_id', $request->warehouse_id);

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$salesmanLoad_query->where('load_date', $start_date);
			} else {
				$salesmanLoad_query->whereBetween('load_date', [$start_date, $end_date]);
			}
		}

		$salesmanLoad = $salesmanLoad_query->get();
		$details = new Collection();
		$count = 0;
		if (count($salesmanLoad)) {
			foreach ($salesmanLoad as $k => $detail) {
				foreach ($detail->salesmanLoadDetails as $key => $load_detail) {

					$count = $count + 1;

					$details->push((object) [
						"SR_No"              => $count,
						"Item"               => model($load_detail->item, 'item_code'),
						"Item_description"   => model($load_detail->item, 'item_name'),
						"qty"                => model($load_detail, 'load_qty'),
						"uom"                => model($load_detail->itemUom, 'name'),
						"sec_qty"            => "",
						"sec_uom"            => "",
						"from_location"      => "",
						"to_location"        => "",
						"from_lot_serial"    => "",
						"to_lot_number"      => "",
						"to_lot_status_code" => "",
						"load_date"          => Carbon::parse($load_detail->load_date)->format('Y-m-d'),
						"warehouse"          => (isset($load_detail->storage_location_id)) ? model($load_detail->storageocation, 'code') : model($detail->storageocation, 'code'),
						"is_exported"        => "NO",
						"salesman"          => model($load_detail->salesmanInfo, 'salesman_code'),
					]);
				}
			}
		}

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
				"IsExported"
			];


			$file_name = 'consolidated_load.' . $request->export_type;
			Excel::store(new ConsolidatedLoadReportExport($details, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
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

		$start_date = $request->start_date;
		$end_date = $request->end_date;

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
					"SR_No"              => $load_detail->SR_No,
					"Item"               => $load_detail->Item,
					"Item_description"   => $load_detail->Item_description,
					"qty"                => $load_detail->qty,
					"uom"                => $load_detail->uom,
					"sec_qty"            => $load_detail->sec_qty,
					"sec_uom"            => $load_detail->sec_uom,
					"from_location"      => $load_detail->from_location,
					"to_location"        => $load_detail->to_location,
					"from_lot_serial"    => $load_detail->from_lot_serial,
					"to_lot_number"      => $load_detail->to_lot_number,
					"to_lot_status_code" => $load_detail->to_lot_status_code,
					"load_date"          => $load_detail->load_date,
					"warehouse"          => $load_detail->warehouse,
					"is_exported"        => $load_detail->is_exported,
					"salesman"         	 => $load_detail->salesman,
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
				"IsExported"
			];


			$file_name = 'consolidate_load_return.' . $request->export_type;
			Excel::store(new ConsolidateLoadReturnReportExport($final_result, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
		}
	}

	public function cfrRegionReport(Request $request)
	{
		//-------------
		$input = $request->json()->all();


		$s_date = $input['start_date'];
		$e_date = $input['end_date'];
		//$k_id = $input['ksm_id'];
		if ($request->export_for == 0) {
			$returnbyksm = DB::table('difot_item');

			$returnbyksm = $returnbyksm->select(
				'difot_item.report_date as date',
				'difot_item.zone_name as region',
				DB::Raw("Concat(Round((SUM(CASE WHEN (difot_item.difot = 1)THEN 1 ELSE 0 END)/count(difot_item.region_id) * 100),2),'%') as difot")
			);
			if ($s_date != '' && $e_date != '') {
				$returnbyksm = $returnbyksm->whereBetween('difot_item.report_date', array("$s_date", "$e_date"));
			}

			$returnbyksm = $returnbyksm->groupBy('difot_item.report_date')
				//->groupBy('difot_reports.report_date')
				->get();
		} else {

			$returnbyksm = DB::table('difot_item');

			$returnbyksm = $returnbyksm->select(
				'difot_item.report_date as date',
				'difot_item.zone_name as region',
				'difot_item.invoice_number as order number',
				'difot_item.report_date as invoice_date',
				'difot_item.remarks as remarks',
				'difot_item.difot as difot',
				'difot_item.customer_code as customer_code',
				'difot_item.firstname as firstname',
				'difot_item.item_code as item_code',
				'difot_item.item_name as item_name',
				'difot_item.remarks as cancel_order',
				'difot_item.canceled as canceled',
				'difot_item.inoviced as inoviced',
				'difot_item.grand_total as grand_total'

			);
			if ($s_date != '' && $e_date != '') {
				$returnbyksm = $returnbyksm->whereBetween('difot_item.report_date', array("$s_date", "$e_date"));
			}

			$returnbyksm = $returnbyksm->get();
		}




		if ($request->export == 0) {
			return prepareResult(true, $returnbyksm, [], "DIFOT by KSM listing", $this->success);
		} else {
			if ($request->export_for == 0) {

				$columns = [
					'Date',
					'Location',
					'DIFOT'
				];

				$file_name = 'difot_region.' . $request->export_type;
			} else {

				$columns = [
					'Date',
					'Location',
					'Order Number',
					'Invoice Date',
					'Remarks',
					'DIFOT',
					'Sold To',
					'Sold To Name',
					'Item Code',
					'Description 1',
					'Cancel Order',
					'Cancelled',
					'Invoiced',
					'Grand Total'

				];

				$file_name = 'difot_detail.' . $request->export_type;
			}


			if ($request->export_type == "PDF") {

				$pdfFilePath = public_path() . "/uploads/pdf/" . $file_name;

				$data = array(
					'title' => "DIFOTReport",
					'date' => $request->start_date,
					'header' => $columns,
					'rows' => $returnbyksm,
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
				Excel::store(new CfrRegionReportExport($returnbyksm, $columns), $file_name);
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

		$loaddetails_query = SalesmanLoadDetails::select(
			'salesman_load_details.id',
			'salesman_load_details.warehouse_id',
			'salesman_load_details.van_id',
			'salesman_load_details.item_uom',
			'items.item_code',
			'items.item_name',
			'items.id as item_id',
			DB::raw('SUM(salesman_load_details.load_qty) as total_qty')
		)
			->leftJoin('items', function ($join) {
				$join->on('items.id', '=', 'salesman_load_details.item_id');
			})
			->where('storage_location_id', $request->warehouse_id)
			->where('van_id', $request->van_id);

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$loaddetails_query->where('load_date', $start_date);
			} else {
				$loaddetails_query->whereBetween('load_date', [$start_date, $end_date]);
			}
		}
		$salesmanLoadDetails = $loaddetails_query->groupBy('item_id')->get();

		$final_array = array();
		$detail = new Collection();
		foreach ($salesmanLoadDetails as $key => $details) {
			$item = Item::where('id', $details->item_id)
				->where('lower_unit_uom_id', $details->item_uom)
				->get();
			if (count($item) > 0) {
				$dmd_ctn = '0';
				$dmd_pcs = $details->total_qty;
				$net_ctn = '0';
				$net_pcs = $details->total_qty;
			} else {
				$dmd_ctn = $details->total_qty;
				$dmd_pcs = '0';
				$net_ctn = $details->total_qty;
				$net_pcs = '0';
			}

			$detail->push((object) [
				"prod_code"          => $details->item_code,
				"prod_desc"          => $details->item_name,
				"prev_rt_ctn"        => '0',
				"prev_rt_pcs"        => '0',
				"dmd_ctn"            => $dmd_ctn,
				"dmd_pcs"            => $dmd_pcs,
				"net_ctn"            => $net_ctn,
				"net_pcs"            =>  $net_pcs,
			]);
		}


		if ($request->export == 0) {
			return prepareResult(true, $detail, [], "loadingchartbywarehouse listing", $this->success);
		} else {
			$columns = [
				'Prod Code',
				"Prod Desc",
				"Prv Rt-CTN",
				"Prv Rt.Pcs",
				"Dmd-CTN",
				"Dmd-Pcs",
				"Net-Iss-CTN",
				"Net-Iss-Pcs"
			];

			$file_name = 'loadingchartbywarehouse.' . $request->export_type;
			Excel::store(new LoadingChartByWarehouseReportExport($detail, $columns), $file_name);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
		}
	}

	public function orderDetailsReport(Request $request)
	{
		if (!$this->isAuthorized) {
			return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
		}

		$start_date = $request->start_date;
		$end_date = $request->end_date;

		$order_query = Order::query('*');

		if ($request->warehouse_id) {
			$order_query->where('storage_location_id', $request->warehouse_id);
		}

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$order_query->where('order_date', $start_date);
			} else {
				$order_query->whereBetween('order_date', [$start_date, $end_date]);
			}
		}

		$orderHeader = $order_query->orderBy('order_date', 'desc')->get();

		$details = new Collection();

		if (count($orderHeader)) {
			foreach ($orderHeader as $k => $detail) {
				$line_numbber = 0;
				foreach ($detail->orderDetails as $key => $order_detail) {

					$salesmanLoad_query = SalesmanLoad::select('created_at')->where('order_id', $detail->id)->first();
					$Invoice_query = Invoice::select('created_at')->where('order_id', $detail->id)->first();
					$deliveryDetails_query = DeliveryDetail::select('item_qty')->where('id', $order_detail->id)->first();

					$qty_cancelled = 0;

					if ($detail->approval_status == 'Cancelled') {
						$qty_cancelled = $order_detail->item_qty;
					} else if ($order_detail->is_deleted == 1) {
						$qty_cancelled = $order_detail->item_qty;
					} else if ($order_detail->reason_id) {
						$qty_cancelled = $order_detail->original_item_qty - $order_detail->item_qty;
					}

					$total_cancel = $qty_cancelled * $order_detail->item_price;

					$total_cancel_aed = ($total_cancel ? $total_cancel : '0');

					$line_numbber             = $line_numbber + 1;
					$revision_reason          = ($order_detail->reason ? $order_detail->reason->name : '');
					$extended_amount          = ($order_detail->reason ? '0' : $order_detail->item_gross);
					$load_date                = ($salesmanLoad_query ? $salesmanLoad_query->load_date : '');
					$Invoice_date             = ($Invoice_query ? $Invoice_query->load_date : '');
					$business_unit            = ($detail->storageocation ? $detail->storageocation->code : '');
					$quantity_shipped         = ($deliveryDetails_query ? $deliveryDetails_query->item_qty : '');

					$details->push((object) [
						'order_number'                 => $detail->order_number,
						"order_type"                   => 'SA',
						"order_code"                   => model($detail->lob, 'lob_code'),
						"line_number"                  => $line_numbber,
						"sold_to"                      => model($detail->customerInfo, 'customer_code'),
						"sold_to_name"                 => model($detail->customer, 'firstname') . ' ' . model($detail->customer, 'lastname'),
						"2nd_item_number"              => model($order_detail->item, 'item_code'),
						"description1"                 => model($order_detail->item, 'item_name'),
						"quantity"                     => $order_detail->item_qty,
						"uom"                          => model($order_detail->itemUom, 'name'),
						"revision_number"              => '0',
						"revision_reason"              => $revision_reason,
						"secondary_quantity"           => $order_detail->item_qty,
						"secondary_uom"                => model($order_detail->itemUom, 'name'),
						"requested_date"               => $detail->delivery_date,
						"customer_po"                  => $detail->customer_lop,
						"ship_to"                      => model($detail->customerInfo, 'customer_code'),
						"ship_to_description"          => model($detail->customer, 'firstname') . '' . model($detail->customer, 'lastname'),
						"original_order_type"          => '',
						"original_line_number"         => '',
						"3rd_item_number"              => model($order_detail->item, 'item_code'),
						"parent_number"                => '',
						"pick_number"                  => '',
						"unit_price"                   => $order_detail->item_price,
						"extended_amount"              => $extended_amount,
						"pricing_uom"                  => model($order_detail->itemUom, 'name'),
						"order_date"                   => Carbon::parse($detail->created_at)->format('Y-m-d'),
						"document_number"              => '',
						"doument_type"                 => '',
						"document_company"             => model($detail->lob, 'lob_code'),
						"scheduled_pick_date"          => $detail->delivery_date,
						"actual_ship_date"             => $load_date,
						"invoice_date"                 => $Invoice_date,
						"cancel_date"                  => '',
						"gl_date"                      => '',
						"promised_delivery_date"       => $detail->delivery_date,
						"business_unit"                => $business_unit,
						"quantity_ordered"             => $order_detail->original_item_qty,
						"quantity_shipped"             => $quantity_shipped,
						"quantity_backordered"         => '0',
						"quantity_canceled"            => $qty_cancelled,
						"price_effective_date"         => $detail->delivery_date,
						"unit_cost"                    => '0',
						"reason_code"                  => $revision_reason,
						"total_cancel_aed"             => $total_cancel_aed,
					]);
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
				"Quantity Backordered",
				"Quantity Canceled",
				"Price Effective Date",
				"Unit Cost",
				"REASON CODE",
				"Total Cancel AED",
			];


			$file_name = 'orderDetailsReport.' . $request->export_type;
			Excel::store(new OrderDetailsReportExport($details, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
		}
	}





	public function salesQuantity(Request $request)
	{
		if (!$this->isAuthorized) {
			return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
		}

		$start_date = Carbon::parse($request->start_date)->format('Y-m-d');
		$end_date   = Carbon::parse($request->end_date)->format('Y-m-d');

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
				"Qty"
			];

			$file_name = 'salesInvoice.' . $request->export_type;
			Excel::store(new SalesInvoiceReportExport($invoices, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
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
				'division' => 'required'
			]);

			if ($validator->fails()) {
				$error = true;
				$errors = $validator->errors();
			}
		}

		if ($type == "consolidatedLoadReport") {
			$validator = \Validator::make($input, [
				'warehouse_id' => 'required'
			]);

			if ($validator->fails()) {
				$error = true;
				$errors = $validator->errors();
			}
		}

		if ($type == "loadingChartByWarehouse") {
			$validator = \Validator::make($input, [
				'warehouse_id' => 'required',
				'van_id' => 'required'
			]);

			if ($validator->fails()) {
				$error = true;
				$errors = $validator->errors();
			}
		}

		if ($type == "getCustomerByDevision") {
			$validator = \Validator::make($input, [
				'division' => 'required'
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
			$end_date   = Carbon::parse($request->end_date)->format('Y-m-d');
		} else if ($request->start_date != '') {
			$start_date = Carbon::parse($request->start_date)->format('Y-m-d');
			$end_date   = Carbon::parse($request->start_date)->format('Y-m-d');
		} else {
			$end_date = Carbon::now()->format('Y-m-d');
			$start_date = Carbon::parse($end_date)->subDays(6)->format('Y-m-d');
		}

		$dateRange  = CarbonPeriod::create($start_date, $end_date);

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
				)->groupBy('credit_notes.credit_note_date')
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
				"date"           => $get_date,
				"sales "         =>  $inv,
				"grv "           => $grv,
				"%"              => $pr,
			]);
		}


		if ($request->export == 0) {
			return prepareResult(true, $details, [], "SalesGrvReport listing", $this->success);
		} else {

			$columns = [
				'Date',
				"Sales",
				"GRV",
				"%"

			];

			$file_name = 'salesGrv.' . $request->export_type;
			Excel::store(new SalesGrvReportExport($details, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
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
			Excel::store(new GlobalReportExport($difots, $columns), $file_name, '', $this->extensions($request->export_type));
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
			return prepareResult(true, $result, [], "Data successfully exported", $this->success);
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
}
