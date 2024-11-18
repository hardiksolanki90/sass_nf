<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use URL;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PricingExport;
use App\Exports\UsersExport;
use App\Exports\SalesmanExport;
use App\Exports\RegionExport;
use App\Exports\DepotExport;
use App\Exports\DebitnotesExport;
use App\Exports\VanExport;
use App\Exports\RouteExport;
use App\Exports\ItemExport;
use App\Exports\DeliveryExport;
use App\Exports\CreditnoteExport;
use App\Exports\ItemuomExport;
use App\Exports\InvoiceExport;
use App\Exports\WarehouseExport;
use App\Exports\CollectionExport;
use App\Exports\VendorExport;
use App\Exports\BankExport;
use App\Exports\PurchaseorderExport;
use App\Exports\ExpensesExport;
use App\Exports\EstimationExport;
use App\Exports\PlanogramExport;
use App\Exports\DistributionExport;
use App\Exports\CompetitorinfoExport;
use App\Exports\ComplaintfeedbackExport;
use App\Exports\CampaignPictureExport;
use App\Exports\AssetTrackingExport;
use App\Exports\PalletExport;
use App\Exports\ConsumerSurveyExport;
use App\Exports\PromotionalsExport;
use App\Exports\AssignInventoryDetailsExport;
use App\Exports\AssignInventoryExport;
use App\Exports\CustomCustomerVisitExport;
use App\Exports\CustomerBasedPriceExport;
use App\Exports\CustomerBasedPriceActiveExport;
use App\Exports\CustomerKsmKamMappingExport;
use App\Exports\CustomerRegionMappingExport;
use App\Exports\CustomerWarehouseMappingExport;
use App\Exports\DailyActivityExport;
use App\Exports\DeliveryTemplateAssignExport;
use App\Exports\DistributionModelStockExport;
use App\Exports\ItemBasePriceExport;
use App\Exports\ItemBranchPlantExport;
use App\Exports\JourneyPlanDetailsExport;
use App\Exports\MasterStockListExport;
use App\Exports\OrderFullExport;
use App\Exports\PalettesExport;
use App\Exports\MerchandiserMslExport;
use App\Model\DeliveryAssignTemplate;
use Illuminate\Support\Facades\Validator;
use DB;

class ExportController extends Controller
{
	public function export(Request $request)
	{
		if (!$this->isAuthorized) {
			return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
		}
		$input = $request->json()->all();

		$validate = $this->validations($input, "export");
		if ($validate["error"]) {
			return prepareResult(false, [], $validate['errors']->first(), "Error while validating export", $this->unprocessableEntity);
		}

		$module = $request->module;
		$criteria = $request->criteria;
		$file_type = $request->file_type;
		$is_password_protected = $request->is_password_protected;
		$start_date = '';
		$end_date = '';

		if ($criteria != 'all') {
			$start_date = $request->start_date;
			$end_date = $request->end_date;
			if ($start_date == '' || $end_date == '') {
				return prepareResult(false, [], [], "Start date and End date required", $this->unauthorized);
			}
		}
		if ($module == 'customer') {
			Excel::store(new UsersExport($start_date, $end_date), 'customer_export.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/customer_export.' . $request->file_type));
		} else if ($module == 'salesman') {
			$durl = str_replace('public/', '', URL::to('/storage/app/export/merchandiser.' . $request->file_type));
			if (file_exists($durl)) {
				unlink($durl);
			}
			Excel::store(new SalesmanExport($start_date, $end_date), 'export/merchandiser.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/merchandiser.' . $request->file_type));
		} else if ($module == 'region') {
			Excel::store(new RegionExport($start_date, $end_date), 'region.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/region.' . $request->file_type));
		} else if ($module == 'depot') {
			Excel::store(new DepotExport($start_date, $end_date), 'depot.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/depot.' . $request->file_type));
		} else if ($module == 'van') {
			Excel::store(new VanExport($start_date, $end_date), 'van.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/van.' . $request->file_type));
		} else if ($module == 'route') {
			Excel::store(new RouteExport($start_date, $end_date), 'route.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/route.' . $request->file_type));
		} else if ($module == 'item') {
			Excel::store(new ItemExport($start_date, $end_date), 'items.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/items.' . $request->file_type));
		} else if ($module == 'order') {
			$storage_location_id = $request->storage_location_id;
			$is_header_level = $request->is_header_level;
			$zone_id = $request->region_id;
			Excel::store(new OrderFullExport($start_date, $end_date, $storage_location_id, $is_header_level, $zone_id), 'order.csv');
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/order.csv'));
			// Excel::store(new OrderExport($start_date, $end_date), 'order.' . $request->file_type);
			// $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/order.' . $request->file_type));
		} else if ($module == 'delivery') {
			Excel::store(new DeliveryExport($start_date, $end_date), 'delivery.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/delivery.' . $request->file_type));
		} else if ($module == 'creditnote') {
			Excel::store(new CreditnoteExport($start_date, $end_date), 'creditnote.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/creditnote.' . $request->file_type));
		} else if ($module == 'itemuom') {
			Excel::store(new ItemuomExport($start_date, $end_date), 'item_uom.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/item_uom.' . $request->file_type));
		} else if ($module == 'journeyplan') {
			$jp_id = array();
			if($request->jp_id){
				$jp_id = $request->jp_id;
			}
			// Excel::store(new JourneyPlanDetailsExport($start_date, $end_date, $jp_id), 'journeyplan.' . $request->file_type);
			Excel::store(new JourneyPlanDetailsExport($start_date == null ? "" : $start_date, $end_date == null ? "" : $end_date, $jp_id), 'journeyplan.' . $request->file_type);

			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/journeyplan.' . $request->file_type));

			// Excel::store(new JourneyPlanExport($start_date, $end_date), 'export/'.strtotime(date("y-m-d H:i")).'_journey_plan.' . $request->file_type);
			// $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/'.strtotime(date("y-m-d H:i")).'_journey_plan.' . $request->file_type));
		} else if ($module == 'invoice') {
			Excel::store(new InvoiceExport($start_date, $end_date), 'invoice.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/invoice.' . $request->file_type));
		} else if ($module == 'debitnote') {
			Excel::store(new DebitnotesExport($start_date, $end_date), 'debit_note.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/debit_note.' . $request->file_type));
		} else if ($module == 'warehouse') {
			Excel::store(new WarehouseExport($start_date, $end_date), 'warehouse.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/warehouse.' . $request->file_type));
		} else if ($module == 'collection') {
			Excel::store(new CollectionExport($start_date, $end_date), 'collection.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/collection.' . $request->file_type));
		} else if ($module == 'vendor') {
			Excel::store(new VendorExport($start_date, $end_date), 'vendor.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/vendor.' . $request->file_type));
		} else if ($module == 'bank') {
			Excel::store(new BankExport($start_date, $end_date), 'bank.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/bank.' . $request->file_type));
		} else if ($module == 'purchaseorder') {
			Excel::store(new PurchaseorderExport($start_date, $end_date), 'purchaseorder.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/purchaseorder.' . $request->file_type));
		} else if ($module == 'expenses') {
			Excel::store(new ExpensesExport($start_date, $end_date), 'expenses.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/expenses.' . $request->file_type));
		} else if ($module == 'estimation') {
			Excel::store(new EstimationExport($start_date, $end_date), 'estimation.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/estimation.' . $request->file_type));
		} else if ($module == 'planogram') {
			Excel::store(new PlanogramExport($start_date, $end_date), 'planogram.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/planogram.' . $request->file_type));
		} else if ($module == 'distribution') {
			Excel::store(new DistributionExport($start_date, $end_date), 'shelf_display.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/shelf_display.' . $request->file_type));
		} else if ($module == 'competitorinfo') {
			Excel::store(new CompetitorinfoExport($start_date, $end_date), 'competitorinfo.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/competitorinfo.' . $request->file_type));
		} else if ($module == 'complaintfeedback') {
			Excel::store(new ComplaintfeedbackExport($start_date, $end_date), 'complaintfeedback.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/complaintfeedback.' . $request->file_type));
		} else if ($module == 'campaignpictures') {
			Excel::store(new CampaignPictureExport($start_date, $end_date), 'campaignpictures.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/campaignpictures.' . $request->file_type));
		} else if ($module == 'assettracking') {
			Excel::store(new AssetTrackingExport($start_date, $end_date), 'assettracking.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/assettracking.' . $request->file_type));
		} else if ($module == 'consumersurvey') {
			Excel::store(new ConsumerSurveyExport($start_date, $end_date), 'consumersurvey.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/consumersurvey.' . $request->file_type));
		} else if ($module == 'promotionals') {
			Excel::store(new PromotionalsExport($start_date, $end_date), 'promotionals.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/promotionals.' . $request->file_type));
		} else if ($module == 'stockinstore') {
			Excel::store(new AssignInventoryDetailsExport($start_date, $end_date), 'stockinstore.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/stockinstore.' . $request->file_type));
		} else if ($module == 'custome_visit') {
			$email = $request->email;
			$s_date = $request->start_date ?? "";
			$e_date = $request->end_date ?? "";
			Excel::store(new CustomCustomerVisitExport($s_date, $e_date, $email), 'export/customer_visits.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/_customer_visits.' . $request->file_type));
		} else if ($module == 'msl') {
			$t = time();
			Excel::store(new MasterStockListExport($start_date, $end_date), 'export/msl.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/msl.' . $request->file_type));
		} else if ($module == 'daily-activity') {
			Excel::store(new DailyActivityExport($start_date, $end_date), 'export/daily_activity.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/daily_activity.' . $request->file_type));
		} else if ($module == 'customer-warehouse-mapping') {
			$t = time();
			Excel::store(new CustomerWarehouseMappingExport(), 'export/CustomerWarehouseMapping.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/CustomerWarehouseMapping.' . $request->file_type));
		} else if ($module == 'item-branch-plant') {
			$t = time();
			Excel::store(new ItemBranchPlantExport(), 'export/ItemBranchPlant.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/ItemBranchPlant.' . $request->file_type));
		} else if ($module == 'distributinModelStock') {
			Excel::store(new DistributionModelStockExport($start_date, $end_date), 'distributionModelStock.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/distributionModelStock.' . $request->file_type));
		} else if ($module == 'stockinstore') {
			Excel::store(new AssignInventoryExport($start_date, $end_date), 'stockinstore.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/stockinstore.' . $request->file_type));
		} else if ($module == 'pricing') {
			Excel::store(new PricingExport($start_date, $end_date), 'export/pricing.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/pricing.' . $request->file_type));
		} else if ($module == 'customer-region-mapping') {
			Excel::store(new CustomerRegionMappingExport($start_date, $end_date), 'export/cusotmerRegion.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/cusotmerRegion.' . $request->file_type));
		} else if ($module == 'customer-based-price') {
			Excel::store(new CustomerBasedPriceExport($start_date, $end_date, $request->customer_id), 'export/cusotmerBasePrice.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/cusotmerBasePrice.' . $request->file_type));
		} else if ($module == 'customer-based-price-active') {
			Excel::store(new CustomerBasedPriceActiveExport($start_date, $end_date, $request->customer_id, $request->key, $request->item_code), 'export/cusotmerBasePrice.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/cusotmerBasePrice.' . $request->file_type));
		} else if ($module == 'item-based-price') {
			Excel::store(new ItemBasePriceExport($start_date, $end_date), 'export/ItemBasePrice.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/ItemBasePrice.' . $request->file_type));
		} else if ($module == 'pallet') {
			$type = $request->type ?? "";
			$s_date = $request->start_date ?? "";
			$e_date = $request->end_date ?? "";
			Excel::store(new PalletExport($start_date, $end_date, $type), 'pallet.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/pallet.' . $request->file_type));
		} else if ($module == 'customer-ksm-kam-mapping') {
			Excel::store(new CustomerKsmKamMappingExport($start_date, $end_date), 'export/cusotmer-kam-kas.' . $request->file_type);
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/cusotmer-kam-kas.' . $request->file_type));
		} else if ($module == 'delivery-assign-template') {
			$storage_location_id = $request->storage_location_id;
			$is_header_level = $request->is_header_level;
			$zone_id = $request->region_id;
			Excel::store(new DeliveryTemplateAssignExport($start_date, $end_date, $storage_location_id, $is_header_level, $zone_id), 'DeliveryAssign.csv');
			$result['file_url'] = str_replace('public/', '', URL::to('/storage/app/DeliveryAssign.csv'));
			// Excel::store(new OrderExport($start_date, $end_date), 'order.' . $request->file_type);
			// $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/order.' . $request->file_type));
		}
		else if ($module == 'merchandiser-msls') { 
			if ($request->export==0 || $request->export=='' || $request->export==null) {
				$data = $this->responseMerchandiserMsl($request);
				return prepareResult(true, $data, [], "Merchandiser MSL listing", $this->created);
			}else{
				Excel::store(new MerchandiserMslExport($request), 'export/_merchandiser_msls.' . $request->file_type);
			    $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/export/_merchandiser_msls.' . $request->file_type));
			}
			
		}

		return prepareResult(true, $result, [], "Data successfully exported", $this->created);
	}

	private function validations($input, $type)
	{
		$errors = [];
		$error = false;
		if ($type == "export") {
			$validator = Validator::make($input, [
				'module' => 'required',
				'criteria' => 'required',
				'file_type' => 'required',
				'is_password_protected' => 'required'
			]);
		}
		if ($validator->fails()) {
			$error = true;
			$errors = $validator->errors();
		}

		return ["error" => $error, "errors" => $errors];
	}

	private function responseMerchandiserMsl($request){

		$start_date        = $request->start_date;
        $end_date          = $request->end_date;
        $customer_id       = $request->customer_id!=null && $request->customer_id!='' ? explode(',',$request->customer_id) : [];
        $merchandiser_id   = $request->merchandiser_id!=null && $request->merchandiser_id!='' ? explode(',', $request->merchandiser_id) : [];

        $merchandiser_msls = DB::table('merchandiser_msls')->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
            $customer->whereIn('customer_id', $customer_id);
        })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
            $customer->whereIn('merchandiser_id', $merchandiser_id);
        })->groupBy('customer_id')->get();

        $merchatArray = [];
        $dataArrays = [];
        foreach($merchandiser_msls as $key=>$merchandiser){
        	$total_msl = 0;
            $total_msl_check = 0;

            $data = DB::table('merchandiser_msls')->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                $customer->whereIn('customer_id', $customer_id);

            })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                $customer->whereIn('merchandiser_id', $merchandiser_id);

            })->groupBy('customer_id')->get();
            foreach($data as $value){

            	$customer_max_msl = DB::table('merchandiser_msls')->where('customer_id', $value->customer_id)->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                        $customer->whereIn('customer_id', $customer_id);

                    })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                        $customer->whereIn('merchandiser_id', $merchandiser_id);

                    })->max('total_msl_item');
                    $total_msl = $total_msl+$customer_max_msl;

                    $customer_max_msl_check = DB::table('merchandiser_msls')->where('customer_id', $value->customer_id)->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                        $customer->whereIn('customer_id', $customer_id);
                    })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                        $customer->whereIn('merchandiser_id', $merchandiser_id);
                    })->max('msl_item_perform');
                    $total_msl_check = $total_msl_check+$customer_max_msl_check;

                    if(!in_array($merchandiser->merchandiser_id,$merchatArray)){
                        array_push($merchatArray,$merchandiser->merchandiser_id);
                    }else{
                        continue;
                    }

                    $devide = $total_msl == 0 ? 1 : $total_msl;
                    $percentage = round(($total_msl_check/$devide)*100);
                 $dataArray = [
			        	'total_msl' => $total_msl,
			        	'total_msl_check' => $total_msl_check,
			        	'percentage' => $percentage
			        ];
			        if(count($merchandiser_id) > 0){
			        	$dataArray['merchandiser_name'] = $merchandiser->merchandiser_name;
			        }
			        if(count($customer_id) > 0){
			        	$dataArray['customer_name'] = $merchandiser->customer_name;
			        }
                 array_push($dataArrays,$dataArray);
            }
        }
        return $dataArrays;
	}
}
