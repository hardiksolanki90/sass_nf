<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\PricingImport;
use App\Model\CombinationMaster;
use App\Model\CustomerBasedPricing;
use App\Model\CombinationPlanKey;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerRoute;
use App\Model\Delivery;
use App\Model\Area;
use App\Model\Channel;
use App\Model\Country;
use App\Model\CustomerCategory;
use App\Model\SalesmanInfo;
use App\Model\DeliveryAssignTemplate;
use App\Model\Item;
use App\Model\PDPArea;
use App\Model\PDPChannel;
use App\Model\PDPCombinationSlab;
use App\Model\PDPCountry;
use App\Model\PDPCustomer;
use App\Model\PDPCustomerCategory;
use App\Model\PDPDiscountSlab;
use App\Model\PDPItem;
use App\Model\PDPItemGroup;
use App\Model\PDPItemMajorCategory;
use App\Model\PDPItemSubCategory;
use App\Model\PDPPromotionItem;
use App\Model\PDPPromotionOfferItem;
use App\Model\PDPRegion;
use App\Model\PDPRoute;
use App\Model\PDPSalesOrganisation;
use App\Model\PriceDiscoPromoPlan;
use App\Model\Route;
use App\Model\PDPLob;
use App\Model\ImportTempFile;
use App\Model\ItemGroup;
use App\Model\ItemMajorCategory;
use App\Model\ItemUom;
use App\Model\Lob;
use App\Model\Region;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class PriceDiscoPromoController extends Controller
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

        // $price_discon_promo_plan = PriceDiscoPromoPlan::where('use_for', $use_for)->orderBy('id', 'desc')->get();

        // $price_discon_promo_plan = PriceDiscoPromoPlan::with(
        //     'PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
        //     'PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
        //     'PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
        //     'PDPRegions.region:id,uuid,region_code,region_name,region_status',
        //     'PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
        //     'PDPAreas.area:id,uuid,depot_id,area_name,area_manager,area_manager_contact,status',
        //     'PDPSubAreas:id,uuid,price_disco_promo_plan_id,sub_area_id',
        //     'PDPSubAreas.subArea:id,uuid,subarea_code,subarea_name,status',
        //     'PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
        //     'PDPRoutes.route:id,uuid,route_code,route_name,status',
        //     'PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
        //     'PDPSalesOrganisations.customerInfos.user:id,uuid,firstname,lastname,email',
        //     'PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
        //     'PDPChannels.channel:id,uuid,code,name,status',
        //     'PDPSubChannels:id,uuid,price_disco_promo_plan_id,sub_channel_id',
        //     'PDPSubChannels.subChannel:id,uuid,name,code,status,channel_id',
        //     'PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
        //     'PDPCustomerCategories.customerInfo.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
        //     'PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
        //     'PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
        //     'PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id',
        //     'PDPItemMajorCategories.itemMajorCategory:id,uuid,name,code',
        //     'PDPItemSubCategories:id,uuid,price_disco_promo_plan_id,item_sub_category_id',
        //     'PDPItemSubCategories.itemSubCategory:id,uuid,item_major_category_id,code,name,status',
        //     'PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
        //     'PDPItemGroups.itemGroup:id,uuid,name,code,status',
        //     'PDPItems:id,uuid,price_disco_promo_plan_id,item_id',
        //     'PDPItems.item:id,uuid,item_name,item_code,status',
        //     'PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_uom_id,item_qty,price',
        //     'PDPPromotionItems.item:id,uuid,item_code,item_name',
        //     'PDPPromotionItems.itemUom:id,uuid,name,code,status',
        //     'PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
        //     'PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
        //     'PDPPromotionOfferItems.itemUom:id,uuid,name,code,status'
        // )
        // ->get();

        $use_for = $request->use_for;

        $price_discon_promo_plan_query = PriceDiscoPromoPlan::where('use_for', $use_for);

        if ($request->name) {
            $price_discon_promo_plan_query->where('name', $request->name);
        }

        if ($request->start_date) {
            $price_discon_promo_plan_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $price_discon_promo_plan_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }

        // $price_discon_promo_plan = $price_discon_promo_plan_query->orderBy('id', 'desc')->get();

        // $price_discon_promo_plan_array = array();
        // if (is_object($price_discon_promo_plan)) {
        //     foreach ($price_discon_promo_plan as $key => $price_discon_promo_plan1) {
        //         $price_discon_promo_plan_array[] = $price_discon_promo_plan[$key];
        //     }
        // }

        // $data_array = array();
        // $page = (isset($request->page)) ? $request->page : '';
        // $limit = (isset($request->page_size)) ? $request->page_size : '';
        // $pagination = array();
        // if ($page != '' && $limit != '') {
        //     $offset = ($page - 1) * $limit;
        //     for ($i = 0; $i < $limit; $i++) {
        //         if (isset($price_discon_promo_plan_array[$offset])) {
        //             $data_array[] = $price_discon_promo_plan_array[$offset];
        //         }
        //         $offset++;
        //     }

        //     $pagination['total_pages'] = ceil(count($price_discon_promo_plan_array) / $limit);
        //     $pagination['current_page'] = (int)$page;
        //     $pagination['total_records'] = count($price_discon_promo_plan_array);
        // } else {
        //     $data_array = $price_discon_promo_plan_array;
        // }


        $all_price_discon_promo_plan = $price_discon_promo_plan_query->orderBy('id', 'desc')
            ->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $price_discon_promo_plan = $all_price_discon_promo_plan->items();

        $pagination = array();
        $pagination['total_pages'] = $all_price_discon_promo_plan->lastPage();
        $pagination['current_page'] = (int)$all_price_discon_promo_plan->perPage();
        $pagination['total_records'] = $all_price_discon_promo_plan->total();

        return prepareResult(true, $price_discon_promo_plan, [], "listing", $this->success, $pagination);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating pricing plan", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            if (is_null($request->combination_plan_key_id) && empty($request->combination_plan_key_id)) {

                $combination_keys = CombinationMaster::whereIn('name', $request->combination_key_value)->get();

                $name = $combination_keys->pluck('name')->toArray();
                $key_codes = $combination_keys->pluck('id')->toArray();

                $combination_plan_keys = new CombinationPlanKey;
                $combination_plan_keys->combination_key_name = implode(" ", $name);
                $combination_plan_keys->combination_key = implode('/', $name);
                $combination_plan_keys->combination_key_code = implode('/', $key_codes);
                $combination_plan_keys->status = 1;
                $combination_plan_keys->save();
            }

            $price_discon_promo_plan = new PriceDiscoPromoPlan;
            if (isset($combination_plan_keys->id) && $combination_plan_keys->id) {
                $price_discon_promo_plan->combination_plan_key_id = $combination_plan_keys->id;
            } else {
                $price_discon_promo_plan->combination_plan_key_id = $request->combination_plan_key_id;
            }
            $price_discon_promo_plan->use_for = $request->use_for;
            $price_discon_promo_plan->name = $request->name;
            $price_discon_promo_plan->start_date = $request->start_date;
            $price_discon_promo_plan->end_date = $request->end_date;
            $price_discon_promo_plan->combination_key_value = implode('/', $request->combination_key_value);

            if ($request->use_for == 'Promotion') {
                $price_discon_promo_plan->order_item_type = $request->order_item_type;
                $price_discon_promo_plan->offer_item_type = $request->offer_item_type;
            }

            if ($request->use_for == 'Discount') {

                $price_discon_promo_plan->type = $request->type;
                $price_discon_promo_plan->qty_from = $request->qty_from;
                $price_discon_promo_plan->qty_to = $request->qty_to;
                $price_discon_promo_plan->discount_type = $request->discount_type;
                $price_discon_promo_plan->discount_apply_on = (!empty($request->discount_apply_on)) ? $request->discount_apply_on : "0";
                $price_discon_promo_plan->discount_value = (!empty($request->discount_value)) ? $request->discount_value : "0.00";
                $price_discon_promo_plan->discount_percentage = $request->discount_percentage;
            }

            $price_discon_promo_plan->priority_sequence = count($request->combination_key_value);
            $price_discon_promo_plan->status = $request->status;
            $price_discon_promo_plan->save();

            if (is_array($request->country_ids) && sizeof($request->country_ids) >= 1) {
                $this->dataAdd($request->country_ids, 'PDPCountry', $price_discon_promo_plan->id, 'country_id');
            }

            if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
                $this->dataAdd($request->region_ids, 'PDPRegion', $price_discon_promo_plan->id, 'region_id');
            }

            if (is_array($request->area_ids) && sizeof($request->area_ids) >= 1) {
                $this->dataAdd($request->area_ids, 'PDPArea', $price_discon_promo_plan->id, 'area_id');
            }

            if (is_array($request->route_ids) && sizeof($request->route_ids) >= 1) {
                $this->dataAdd($request->route_ids, 'PDPRoute', $price_discon_promo_plan->id, 'route_id');
            }

            if (is_array($request->sales_organisation_ids) && sizeof($request->sales_organisation_ids) >= 1) {
                $this->dataAdd($request->sales_organisation_ids, 'PDPSalesOrganisation', $price_discon_promo_plan->id, 'sales_organisation_id');
            }

            if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
                $this->dataAdd($request->channel_ids, 'PDPChannel', $price_discon_promo_plan->id, 'channel_id');
            }

            if (is_array($request->customer_category_ids) && sizeof($request->customer_category_ids) >= 1) {
                $this->dataAdd($request->customer_category_ids, 'PDPCustomerCategory', $price_discon_promo_plan->id, 'customer_category_id');
            }

            if (is_array($request->customer_ids) && sizeof($request->customer_ids) >= 1) {
                $this->dataAdd($request->customer_ids, 'PDPCustomer', $price_discon_promo_plan->id, 'customer_id');
            }

            if (is_array($request->item_major_category_ids) && sizeof($request->item_major_category_ids) >= 1) {
                $this->dataAdd($request->item_major_category_ids, 'PDPItemMajorCategory', $price_discon_promo_plan->id, 'item_major_category_id');
            }

            if (is_array($request->item_group_ids) && sizeof($request->item_group_ids) >= 1) {
                $this->dataAdd($request->item_group_ids, 'PDPItemGroup', $price_discon_promo_plan->id, 'item_group_id');
            }

            if ($request->use_for == 'Discount') {
                if (is_array($request->slabs) && sizeof($request->slabs) >= 1) {
                    foreach ($request->slabs as $slab) {
                        //save PDPDiscountSlab
                        $pdp_discount_slab = new PDPDiscountSlab;
                        $pdp_discount_slab->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_discount_slab->min_slab = $slab['min_slab'];
                        $pdp_discount_slab->max_slab = $slab['max_slab'];
                        $pdp_discount_slab->value = $slab['value'];
                        $pdp_discount_slab->percentage = $slab['percentage'];
                        $pdp_discount_slab->save();
                    }
                }
            }


            if ($request->use_for == 'Pricing') {
                if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                    foreach ($request->item_ids as $item) {
                        if (!empty($item['price'])) {
                            //save PDPItem
                            $pdp_item = new PDPItem;
                            $pdp_item->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                            $pdp_item->item_id      = $item['item_id'];
                            $pdp_item->item_uom_id  = (!empty($item['item_uom_id'])) ? $item['item_uom_id'] : null;
                            $pdp_item->lob_id       = (!empty($item['lob_id'])) ? $item['lob_id'] : null;
                            $pdp_item->price        = (!empty($item['price'])) ? $item['price'] : null;
                            // $pdp_item->max_price    = (isset($item['max_price'])) ? $item['max_price'] : null;
                            $pdp_item->save();
                        }
                    }
                }

                // PDP Lob save
                if (!empty($request->lob_ids)) {
                    $pdp_lob = new PDPLob;
                    $pdp_lob->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                    $pdp_lob->lob_id                    = (!empty($item['lob_id'])) ? $item['lob_id'] : null;
                    $pdp_lob->save();
                }
            }

            if ($request->use_for == 'Promotion') {
                if (is_array($request->promotion_items) && sizeof($request->promotion_items) >= 1) {
                    foreach ($request->promotion_items as $key => $promotion_item) {
                        $pdp_promotion_item = new PDPPromotionItem;
                        $pdp_promotion_item->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_promotion_item->item_id = $promotion_item['item_id'];
                        $pdp_promotion_item->item_uom_id = $promotion_item['item_uom_id'];
                        $pdp_promotion_item->item_qty = $promotion_item['item_qty'];
                        $pdp_promotion_item->price = $promotion_item['price'];
                        $pdp_promotion_item->save();
                    }
                }

                if (is_array($request->promotion_offer_items) && sizeof($request->promotion_offer_items) >= 1) {
                    foreach ($request->promotion_offer_items as $key => $promotion_offer_items) {
                        $pdp_promotion_offer_items = new PDPPromotionOfferItem;
                        $pdp_promotion_offer_items->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_promotion_offer_items->item_id = $promotion_offer_items['item_id'];
                        $pdp_promotion_offer_items->item_uom_id = $promotion_offer_items['item_uom_id'];
                        $pdp_promotion_offer_items->offered_qty = $promotion_offer_items['offered_qty'];
                        $pdp_promotion_offer_items->save();
                    }
                }
            }

            DB::commit();
            return prepareResult(true, $price_discon_promo_plan, [], $price_discon_promo_plan->use_for . " added successfully", $this->success);
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
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $price_discon_promo_plan = PriceDiscoPromoPlan::where('uuid', $uuid)
            ->with(
                'PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
                'PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
                'PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
                'PDPRegions.region:id,uuid,region_code,region_name,region_status',
                'PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
                'PDPAreas.area:id,uuid,area_name,status',
                'PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
                'PDPRoutes.route:id,uuid,route_code,route_name,status',
                'PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
                'PDPSalesOrganisations.salesOrganisation.customerInfos.user:id,uuid,firstname,lastname,email',
                'PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
                'PDPChannels.channel:id,uuid,name,status',
                'PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
                'PDPCustomerCategories.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
                'PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
                'PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
                'PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id,item_major_category_id',
                'PDPItemMajorCategories.itemMajorCategory:id,uuid,name',
                'PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
                'PDPItemGroups.itemGroup:id,uuid,name,code,status',
                'PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
                'PDPItems.item:id,uuid,item_name,item_code,item_description,status',
                'PDPItems.itemUom:id,uuid,name,code,status',
                'PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,item_qty,price',
                'PDPPromotionItems.item:id,uuid,item_code,item_name',
                'PDPPromotionItems.itemUom:id,uuid,name,code,status',
                'PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
                'PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
                'PDPPromotionOfferItems.itemUom:id,uuid,name,code,status',
                'PDPDiscountSlabs:id,price_disco_promo_plan_id,min_slab,max_slab,value,percentage',
                'PDPLob:id,price_disco_promo_plan_id,lob_id',
                'PDPLob.lob:id,name',
                'lob:id,name'
            )
            ->first();

        if (!is_object($price_discon_promo_plan)) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unprocessableEntity);
        }

        return prepareResult(true, $price_discon_promo_plan, [], $price_discon_promo_plan->use_for . " code Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating outlet product code", $this->unprocessableEntity);
        }
        DB::beginTransaction();
        try {
            $price_discon_promo_plan = PriceDiscoPromoPlan::where('uuid', $uuid)
                ->first();

            if (!is_object($price_discon_promo_plan)) {
                return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
            }

            PDPArea::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPChannel::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPCountry::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPCustomer::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPCustomerCategory::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPItem::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPItemGroup::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPItemMajorCategory::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPPromotionItem::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPPromotionOfferItem::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPDiscountSlab::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPRegion::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPRoute::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();
            PDPSalesOrganisation::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)->delete();

            if (is_null($request->combination_plan_key_id) && empty($request->combination_plan_key_id)) {

                $combination_keys = CombinationMaster::whereIn('name', $request->combination_key_value)->get();

                $name = $combination_keys->pluck('name')->toArray();
                $key_codes = $combination_keys->pluck('id')->toArray();

                $combination_plan_keys = new CombinationPlanKey;
                $combination_plan_keys->combination_key_name = implode(" ", $name);
                $combination_plan_keys->combination_key = implode('/', $name);
                $combination_plan_keys->combination_key_code = implode('/', $key_codes);
                $combination_plan_keys->status = 1;
                $combination_plan_keys->save();
            }

            if (isset($combination_plan_keys->id) && $combination_plan_keys->id) {
                $price_discon_promo_plan->combination_plan_key_id = $combination_plan_keys->id;
            } else {
                $price_discon_promo_plan->combination_plan_key_id = $request->combination_plan_key_id;
            }

            $price_discon_promo_plan->use_for = $request->use_for;
            $price_discon_promo_plan->name = $request->name;
            $price_discon_promo_plan->start_date = $request->start_date;
            $price_discon_promo_plan->end_date = $request->end_date;
            $price_discon_promo_plan->combination_key_value = implode('/', $request->combination_key_value);

            if ($request->use_for == 'Promotion') {
                $price_discon_promo_plan->order_item_type = $request->order_item_type;
                $price_discon_promo_plan->offer_item_type = $request->offer_item_type;
            }

            if ($request->use_for == 'Discount') {
                $price_discon_promo_plan->type = $request->type;
                $price_discon_promo_plan->qty_from = $request->qty_from;
                $price_discon_promo_plan->qty_to = $request->qty_to;
                $price_discon_promo_plan->discount_type = $request->discount_type;
                $price_discon_promo_plan->discount_apply_on = (!empty($request->discount_apply_on)) ? $request->discount_apply_on : "0";
                $price_discon_promo_plan->discount_value = (!empty($request->discount_value)) ? $request->discount_value : "0.00";
                $price_discon_promo_plan->discount_percentage = $request->discount_percentage;
            }

            $price_discon_promo_plan->priority_sequence = $request->priority_sequence;
            $price_discon_promo_plan->status = $request->status;
            $price_discon_promo_plan->save();

            if (is_array($request->country_ids) && sizeof($request->country_ids) >= 1) {
                $this->dataAdd($request->country_ids, 'PDPCountry', $price_discon_promo_plan->id, 'country_id');
            }

            if (is_array($request->region_ids) && sizeof($request->region_ids) >= 1) {
                $this->dataAdd($request->region_ids, 'PDPRegion', $price_discon_promo_plan->id, 'region_id');
            }

            if (is_array($request->area_ids) && sizeof($request->area_ids) >= 1) {
                $this->dataAdd($request->area_ids, 'PDPArea', $price_discon_promo_plan->id, 'area_id');
            }

            if (is_array($request->route_ids) && sizeof($request->route_ids) >= 1) {
                $this->dataAdd($request->route_ids, 'PDPRoute', $price_discon_promo_plan->id, 'route_id');
            }

            if (is_array($request->sales_organisation_ids) && sizeof($request->sales_organisation_ids) >= 1) {
                $this->dataAdd($request->sales_organisation_ids, 'PDPSalesOrganisation', $price_discon_promo_plan->id, 'sales_organisation_id');
            }

            if (is_array($request->channel_ids) && sizeof($request->channel_ids) >= 1) {
                $this->dataAdd($request->channel_ids, 'PDPChannel', $price_discon_promo_plan->id, 'channel_id');
            }

            if (is_array($request->customer_category_ids) && sizeof($request->customer_category_ids) >= 1) {
                $this->dataAdd($request->customer_category_ids, 'PDPCustomerCategory', $price_discon_promo_plan->id, 'customer_category_id');
            }

            if (is_array($request->customer_ids) && sizeof($request->customer_ids) >= 1) {
                $this->dataAdd($request->customer_ids, 'PDPCustomer', $price_discon_promo_plan->id, 'customer_id');
            }

            if (is_array($request->item_major_category_ids) && sizeof($request->item_major_category_ids) >= 1) {
                $this->dataAdd($request->item_major_category_ids, 'PDPItemMajorCategory', $price_discon_promo_plan->id, 'item_major_category_id');
            }

            // if (is_array($request->item_sub_category_ids) && sizeof($request->item_sub_category_ids) >= 1) {
            //     $this->dataAdd($request->item_sub_category_ids, 'PDPItemMajorCategory', $price_discon_promo_plan->id, 'item_sub_category_id');
            // }

            if (is_array($request->item_group_ids) && sizeof($request->item_group_ids) >= 1) {
                $this->dataAdd($request->item_group_ids, 'PDPItemGroup', $price_discon_promo_plan->id, 'item_group_id');
            }

            if ($request->use_for == 'Discount') {
                if (is_array($request->slabs) && sizeof($request->slabs) >= 1) {
                    foreach ($request->slabs as $slab) {
                        //save PDPDiscountSlab
                        $pdp_discount_slab = new PDPDiscountSlab;
                        $pdp_discount_slab->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_discount_slab->min_slab = $slab['min_slab'];
                        $pdp_discount_slab->max_slab = $slab['max_slab'];
                        $pdp_discount_slab->value = $slab['value'];
                        $pdp_discount_slab->percentage = $slab['percentage'];
                        $pdp_discount_slab->save();
                    }
                }
            }

            // if ($request->use_for == 'Pricing' || $request->use_for == 'Discount') {
            if ($request->use_for == 'Pricing') {
                if (is_array($request->item_ids) && sizeof($request->item_ids) >= 1) {
                    foreach ($request->item_ids as $item) {
                        if (!empty($item['price'])) {
                            //save PDPItem
                            $pdp_item = new PDPItem;
                            $pdp_item->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                            $pdp_item->item_id      = $item['item_id'];
                            $pdp_item->item_uom_id  = (!empty($item['item_uom_id'])) ? $item['item_uom_id'] : null;
                            $pdp_item->price        = (!empty($item['price'])) ? $item['price'] : null;
                            $pdp_item->lob_id       = (!empty($item['lob_id'])) ? $item['lob_id'] : null;
                            // $pdp_item->max_price    = (isset($item['max_price'])) ? $item['max_price'] : null;
                            $pdp_item->save();
                        }
                    }
                }

                if (!empty($request->lob_ids)) {
                    $p_d_p = PDPLob::where('price_disco_promo_plan_id', $price_discon_promo_plan->id)
                        ->where('lob_id', $item['lob_id'])
                        ->first();

                    if (!is_object($p_d_p)) {
                        $p_d_p = new PDPLob;
                    }

                    $p_d_p->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                    $p_d_p->lob_id = (!empty($item['lob_id'])) ? $item['lob_id'] : null;
                    $p_d_p->save();
                }
            }

            if ($request->use_for == 'Promotion') {
                if (is_array($request->promotion_items) && sizeof($request->promotion_items) >= 1) {
                    foreach ($request->promotion_items as $key => $promotion_item) {
                        $pdp_promotion_item = new PDPPromotionItem;
                        $pdp_promotion_item->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_promotion_item->item_id = $promotion_item['item_id'];
                        $pdp_promotion_item->item_uom_id = $promotion_item['item_uom_id'];
                        $pdp_promotion_item->item_qty = $promotion_item['item_qty'];
                        $pdp_promotion_item->price = $promotion_item['price'];
                        $pdp_promotion_item->save();
                    }
                }

                if (is_array($request->promotion_offer_items) && sizeof($request->promotion_offer_items) >= 1) {
                    foreach ($request->promotion_offer_items as $key => $promotion_offer_items) {
                        $pdp_promotion_offer_items = new PDPPromotionOfferItem;
                        $pdp_promotion_offer_items->price_disco_promo_plan_id = $price_discon_promo_plan->id;
                        $pdp_promotion_offer_items->item_id = $promotion_offer_items['item_id'];
                        $pdp_promotion_offer_items->item_uom_id = $promotion_offer_items['item_uom_id'];
                        $pdp_promotion_offer_items->offered_qty = $promotion_offer_items['offered_qty'];
                        $pdp_promotion_offer_items->save();
                    }
                }
            }

            DB::commit();
            return prepareResult(true, $price_discon_promo_plan, [], $price_discon_promo_plan->use_for . " updated successfully", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating", $this->unprocessableEntity);
        }

        $price_discon_promo_plan = PriceDiscoPromoPlan::where('uuid', $uuid)
            ->first();

        if (is_object($price_discon_promo_plan)) {
            $price_discon_promo_plan->delete();

            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  array  $data
     * @param  object  $obj create new object
     * @param  int  $id pdp id
     * @param  int  $sub_key child key
     * @return \Illuminate\Http\Response
     */
    private function dataAdd($data, $obj, $id, $sub_key)
    {
        foreach ($data as $data_id) {
            $obj_data = 'App\\Model\\' . $obj;
            $pdp = new $obj_data;
            $pdp->price_disco_promo_plan_id = $id;
            $pdp->$sub_key = $data_id;
            $pdp->save();
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'use_for' => 'required',
                'name' => 'required',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                // 'priority_sequence' => 'required',
                'status' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "routeByPDP") {
            $validator = Validator::make($input, [
                'route_id' => 'required|integer|exists:routes,id',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "pdpMobile") {
            $validator = Validator::make($input, [
                'type' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function routeApplyPriceDiscPromotion(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "routeWisePDP");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating route", $this->unprocessableEntity);
        }

        $p_d_p_route = PDPRoute::where('route_id', $request->route_id)
            ->with(
                'priceDiscoPromoPlan',
                'priceDiscoPromoPlan.PDPPromotionItems.item',
                'priceDiscoPromoPlan.PDPPromotionItems.itemUom',
                'priceDiscoPromoPlan.PDPPromotionOfferItems.item',
                'priceDiscoPromoPlan.PDPPromotionOfferItems.itemUom',
                'priceDiscoPromoPlan.PDPDiscountSlabs',
                'route'
            )
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $p_d_p_route, [], "Route wise promotion successfully", $this->success);
    }

    public function pdpMobile($type)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$type) {
            return prepareResult(false, [], [], "Error while validating pdp", $this->unprocessableEntity);
        }

        $price_discon_promo_plan = PriceDiscoPromoPlan::where('use_for', $type)
            ->with(
                'PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
                'PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
                'PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
                'PDPRegions.region:id,uuid,region_code,region_name,region_status',
                'PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
                'PDPAreas.area:id,uuid,area_name,status',
                'PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
                'PDPRoutes.route:id,uuid,route_code,route_name,status',
                'PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
                'PDPSalesOrganisations.salesOrganisation.customerInfos.user:id,uuid,firstname,lastname,email',
                'PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
                'PDPChannels.channel:id,uuid,name,status',
                'PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
                'PDPCustomerCategories.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
                'PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
                'PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
                'PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id,item_major_category_id',
                'PDPItemMajorCategories.itemMajorCategory:id,uuid,name',
                'PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
                'PDPItemGroups.itemGroup:id,uuid,name,code,status',
                'PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
                'PDPItems.item:id,uuid,item_name,item_code,item_description,status',
                'PDPItems.itemUom:id,uuid,name,code,status',
                'PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,item_qty,price',
                'PDPPromotionItems.item:id,uuid,item_code,item_name',
                'PDPPromotionItems.itemUom:id,uuid,name,code,status',
                'PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
                'PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
                'PDPPromotionOfferItems.itemUom:id,uuid,name,code,status',
                'PDPDiscountSlabs:id,price_disco_promo_plan_id,min_slab,max_slab,value,percentage',
                'PDPLob:id,price_disco_promo_plan_id,lob_id',
                'PDPLob.lob:id,name',
                'lob:id,name'
            )
            ->orderBy('id', 'desc')
            ->get();

        $price_discon_promo_plan_array = array();
        if (is_object($price_discon_promo_plan)) {
            foreach ($price_discon_promo_plan as $key => $price_discon_promo_plan1) {
                $price_discon_promo_plan_array[] = $price_discon_promo_plan[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($price_discon_promo_plan_array[$offset])) {
                    $data_array[] = $price_discon_promo_plan_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($price_discon_promo_plan_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($price_discon_promo_plan_array);
        } else {
            $data_array = $price_discon_promo_plan_array;
        }

        return prepareResult(true, $data_array, [], ucfirst($type) . " listing", $this->success, $pagination);
    }

    public function pdpMobileByRoute(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "routeByPDP");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating route", $this->unprocessableEntity);
        }

        $route = Route::find($request->route_id);
        $type = $request->type;
        // route wise customer
        $customer_info = CustomerInfo::where('route_id', $route->id)->get();
        $pdpArray = array();
        $data_array = array();
        $data_array1 = array();

        if (count($customer_info)) {
            foreach ($customer_info as $cKye => $customer) {
                //Get Customer Info
                // same for all customer
                //Location
                $customerCountry = $customer->user->country_id; //1
                $customerRegion = $customer->region_id; //2
                $customerRoute = $customer->route_id; //4

                //Customer
                $getAreaFromRoute = Route::find($customerRoute);
                $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                $customerSalesOrganisation = $customer->sales_organisation_id; //5
                $customerChannel = $customer->channel_id; //6
                $customerCustomerCategory = $customer->customer_category_id; //7
                $customerCustomer = $customer->id; //8

                $pdp_customer = PDPCustomer::select('p_d_p_customers.id as p_d_p_customer_id', 'combination_plan_key_id', 'price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                    ->join('price_disco_promo_plans', function ($join) {
                        $join->on('p_d_p_customers.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                    })
                    ->join('combination_plan_keys', function ($join) {
                        $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                    })
                    ->where('customer_id', $customer->id)
                    ->where('price_disco_promo_plans.organisation_id', auth()->user()->organisation_id)
                    ->where('start_date', '<=', date('Y-m-d'))
                    ->where('end_date', '>=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.use_for', $type)
                    ->where('price_disco_promo_plans.status', 1)
                    ->where('combination_plan_keys.status', 1)
                    ->orderBy('priority_sequence', 'ASC')
                    ->orderBy('combination_key_code', 'DESC')
                    ->get();

                if ($pdp_customer->count() > 0) {
                    $getKey = [];
                    $getDiscountKey = [];
                    foreach ($pdp_customer as $key => $filterPrice) {
                        $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_customer_id, $filterPrice->price, $filterPrice->priority_sequence);
                    }

                    $useThisItem = '';
                    $isPromotion = false;
                    $isDiscount = false;
                    $lastKey = '';
                    foreach ($getKey as $checking) {
                        $usePrice = false;
                        if (isset($checking['combination_key_code'])) {
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);

                                if ($isFind) {
                                    $usePrice = true;
                                } else {
                                    $usePrice = false;
                                    break;
                                }

                                if ($usePrice) {
                                    $useThisItem = $checking['price_disco_promo_plan_id'];
                                    if ($checking['use_for'] == 'Discount') {
                                        $isDiscount = true;
                                        $lArr = explode('/', $checking['combination_key']);
                                        $lastKey = end($lArr);
                                    }

                                    if ($checking['use_for'] == 'Promotion') {
                                        $isPromotion = true;
                                    }
                                    break;
                                }
                            }
                        }
                    }

                    if ($useThisItem) {
                        if ($isPromotion) {
                            $price_promotion_discount = PriceDiscoPromoPlan::with(
                                'PDPPromotionItems',
                                'PDPPromotionItems.item:id,item_name',
                                'PDPPromotionItems.itemUom:id,name',
                                'PDPPromotionOfferItems',
                                'PDPPromotionOfferItems.item:id,item_name',
                                'PDPPromotionOfferItems.itemUom:id,name'
                            )
                                ->where('id', $useThisItem)
                                ->first();
                        } else if ($isDiscount) {
                            if ($lastKey == 'Item Group') {
                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItemGroups:id,price_disco_promo_plan_id,item_group_id'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();

                                $items = '';
                                foreach ($price_promotion_discount->PDPItemGroups as $pdpgroup) {
                                    $items = Item::select('id', 'uuid', 'item_name', 'lower_unit_uom_id', 'lower_unit_item_upc')
                                        ->with('itemUomLowerUnit:id,name,code')
                                        ->where('item_group_id', $pdpgroup['item_group_id'])
                                        ->get();
                                }

                                $price_promotion_discount->p_d_p_items = $items;
                            } else if ($lastKey == 'Major Category') {
                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItemMajorCategories:id,price_disco_promo_plan_id,item_major_category_id'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();

                                $items = '';
                                foreach ($price_promotion_discount->PDPItemMajorCategories as $pdpgroup) {
                                    $items = Item::select('id', 'uuid', 'item_name', 'lower_unit_uom_id', 'lower_unit_item_upc')
                                        ->with('itemUomLowerUnit:id,name,code')
                                        ->where('item_major_category_id', $pdpgroup['item_major_category_id'])
                                        ->get();
                                }

                                $price_promotion_discount->p_d_p_items = $items;
                            } else {

                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItems:id,price_disco_promo_plan_id,item_id,item_uom_id,price',
                                    'PDPItems.item:id,item_name,lower_unit_uom_id,lower_unit_item_upc',
                                    'PDPItems.itemUom:id,name'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();
                            }
                        } else {
                            $price_promotion_discount = PriceDiscoPromoPlan::with('PDPItems:id,price_disco_promo_plan_id,item_id,item_uom_id,price', 'PDPItems.item:id,item_name', 'PDPItems.itemUom:id,name')
                                ->where('id', $useThisItem)
                                ->first();
                        }

                        $user = $customer->user()->select('id', 'firstname', 'lastname')->first();

                        $data_array[] = array(
                            'customer' => $user,
                            'pdp' => $price_promotion_discount
                        );
                    }
                } else {
                    // table query

                    $price_disc_promo_plan_query = PriceDiscoPromoPlan::with(
                        'combinationPlanKeyPricingPlain',
                        'PDPAreas:id,price_disco_promo_plan_id,area_id',
                        'PDPAreas.area:id,area_name',
                        'PDPRoutes:id,price_disco_promo_plan_id,route_id',
                        'PDPRoutes.route:id,route_name',
                        'PDPSalesOrganisations:id,price_disco_promo_plan_id,sales_organisation_id',
                        'PDPSalesOrganisations.salesOrganisation:id,name',
                        'PDPChannels:id,price_disco_promo_plan_id,channel_id',
                        'PDPChannels.channel:id,name',
                        'PDPRegions:id,price_disco_promo_plan_id,region_id',
                        'PDPRegions.region:id,region_name'
                    )
                        ->where('organisation_id', auth()->user()->organisation_id)
                        ->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('use_for', $type)
                        ->where('status', 1)
                        // ->whereHas('combinationPlanKeyPricing', function ($que) {
                        //     $que->where('status', 1);
                        // })
                        ->orderBy('priority_sequence', 'ASC');
                    // ->whereHas('combinationPlanKeyPricingPlain', function ($q) {
                    //     $q->orderBy('combination_key_code', 'DESC');
                    // });

                    $price_disc_promo_plan = $price_disc_promo_plan_query->get()
                        ->sortByDesc('combinationPlanKeyPricingPlain.combination_key_code');

                    $getKey = [];
                    $getDiscountKey = [];
                    $isDiscount = false;
                    $lastKey = '';

                    foreach ($price_disc_promo_plan as $key => $filterPrice) {
                        if (!in_array('Customer', explode('/', $filterPrice->combination_key_value))) {
                            $getKey[] = $this->makeKeyValue(
                                $customerCountry, // Customer Country
                                $customerRegion, // Customer Region
                                $customerArea, // Customer Are
                                $customerRoute,
                                $customerSalesOrganisation,
                                $customerChannel,
                                $customerCustomerCategory,
                                $customerCustomer,
                                $filterPrice->combinationPlanKeyPricingPlain->combination_key_code, // Combination code Route / Material
                                $filterPrice->combinationPlanKeyPricingPlain->combination_key,
                                $filterPrice->id,
                                $filterPrice->combinationPlanKeyPricingPlain->p_d_p_customer_id,
                                $filterPrice->combinationPlanKeyPricingPlain->price,
                                $filterPrice->combinationPlanKeyPricingPlain->priority_sequence
                            );
                        } else {
                            unset($price_disc_promo_plan[$key]);
                        }
                    }

                    $useThisItem = '';
                    $isPromotion = false;
                    foreach ($getKey as $checking) {
                        $usePrice = false;
                        if (isset($checking['combination_key_code'])) {
                            if (!in_array(8, explode('/', $checking['combination_key_code']))) {
                                foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                    $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                    $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                    if ($isFind) {
                                        $usePrice = true;
                                    } else {
                                        $usePrice = false;
                                        break;
                                    }

                                    if ($usePrice) {
                                        $useThisItem = $checking['price_disco_promo_plan_id'];
                                        if ($checking['use_for'] == 'Discount') {
                                            $lArr = explode('/', $checking['combination_key']);
                                            $lastKey = end($lArr);
                                            $isDiscount = true;
                                        }

                                        if ($checking['use_for'] == 'Promotion') {
                                            $isPromotion = true;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ($useThisItem) {
                        if ($isPromotion) {
                            $price_promotion_discount = PriceDiscoPromoPlan::with(
                                'PDPPromotionItems',
                                'PDPPromotionItems.item:id,item_name',
                                'PDPPromotionItems.itemUom:id,name',
                                'PDPPromotionOfferItems',
                                'PDPPromotionOfferItems.item:id,item_name',
                                'PDPPromotionOfferItems.itemUom:id,name'
                            )
                                ->where('id', $useThisItem)
                                ->first();
                        } else if ($isDiscount) {
                            if ($lastKey == 'Item Group') {
                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItemGroups:id,price_disco_promo_plan_id,item_group_id'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();

                                $items = '';
                                foreach ($price_promotion_discount->PDPItemGroups as $pdpgroup) {
                                    $items = Item::select('id', 'uuid', 'item_name', 'lower_unit_uom_id', 'lower_unit_item_upc')
                                        ->with('itemUomLowerUnit:id,name,code')
                                        ->where('item_group_id', $pdpgroup['item_group_id'])
                                        ->get();
                                }

                                $price_promotion_discount->p_d_p_items = $items;
                            } else if ($lastKey == 'Major Category') {
                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItemMajorCategories:id,price_disco_promo_plan_id,item_major_category_id'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();

                                $items = '';
                                foreach ($price_promotion_discount->PDPItemMajorCategories as $pdpgroup) {
                                    $items = Item::select('id', 'uuid', 'item_name', 'lower_unit_uom_id', 'lower_unit_item_upc')
                                        ->with('itemUomLowerUnit:id,name,code')
                                        ->where('item_major_category_id', $pdpgroup['item_major_category_id'])
                                        ->get();
                                }

                                $price_promotion_discount->p_d_p_items = $items;
                            } else {

                                $price_promotion_discount = PriceDiscoPromoPlan::with(
                                    'PDPDiscountSlabs',
                                    'PDPItems:id,price_disco_promo_plan_id,item_id,item_uom_id,price',
                                    'PDPItems.item:id,item_name,lower_unit_uom_id,lower_unit_item_upc',
                                    'PDPItems.itemUom:id,name'
                                )
                                    ->where('id', $useThisItem)
                                    ->first();
                            }
                        } else {
                            $price_promotion_discount = PriceDiscoPromoPlan::with('PDPItems:id,price_disco_promo_plan_id,item_id,item_uom_id,price', 'PDPItems.item:id,item_name', 'PDPItems.itemUom:id,name')
                                ->where('id', $useThisItem)
                                ->first();
                        }

                        $user = $customer->user()->select('id', 'firstname', 'lastname')->first();

                        $data_array1[] = array(
                            'customer' => $user,
                            'pdp' => $price_promotion_discount
                        );
                    }
                }
            }
        }
        $d = array_merge($data_array, $data_array1);
        return prepareResult(true, $d, [], "pdp listing", $this->success);
    }

    private function makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $combination_key_code, $combination_key, $price_disco_promo_plan_id, $p_d_p_item_id, $price, $priority_sequence)
    {
        $keyCodes = '';
        $combination_actual_id = '';

        foreach (explode('/', $combination_key_code) as $hierarchyNumber) {
            if ($hierarchyNumber == 11) {
                break;
            }
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
                    // case '9':
                    //     if (empty($add)) {
                    //         $add = $itemMajorCategory;
                    //     } else {
                    //         $add = '/' . $itemMajorCategory;
                    //     }
                    //     // $add  = '/' . $itemMajorCategory;
                    //     break;
                    // case '10':
                    //     if (empty($add)) {
                    //         $add = $itemItemGroup;
                    //     } else {
                    //         $add = '/' . $itemItemGroup;
                    //     }
                    //     // $add  = '/' . $itemItemGroup;
                    //     break;
                    // case '11':
                    //     if (empty($add)) {
                    //         $add = $item;
                    //     } else {
                    //         $add = '/' . $item;
                    //     }
                    // $add  = '/' . $item;
                    // break;
                default:
                    # code...
                    break;
            }
            $keyCodes .= $hierarchyNumber;

            $combination_actual_id .= $add;
        }

        $getIdentify = PriceDiscoPromoPlan::find($price_disco_promo_plan_id);

        $returnData = array();

        if (isset($getIdentify->id) && $getIdentify->use_for == 'Discount') {
            return array(
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
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

        if (is_object($getIdentify) && $getIdentify->use_for == 'Promotion') {
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

        if (isset($getIdentify->id)) {

            $returnData = [
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
                'auto_sequence_by_code' => $hierarchyNumber,
                'hierarchyNumber' => $keyCodes,
                // 'p_d_p_item_id' => $p_d_p_item_id,
                'p_d_p_customer_id' => $customerCustomer,
                'priority_sequence' => $priority_sequence,
                'price' => $price,
                'use_for' => $getIdentify->use_for
            ];
        }


        return $returnData;
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


        if ($checkExistOrNot) {
            return true;
        }

        return false;
    }

    public function PDPMobileIndex()
    {

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "pdpMobile");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating pricing plan", $this->unprocessableEntity);
        }


        $price_discon_promo_plan = PriceDiscoPromoPlan::with(
            'PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
            'PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
            'PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
            'PDPRegions.region:id,uuid,region_code,region_name,region_status',
            'PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
            'PDPAreas.area:id,uuid,depot_id,area_name,area_manager,area_manager_contact,status',
            'PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
            'PDPRoutes.route:id,uuid,route_code,route_name,status',
            'PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
            'PDPSalesOrganisations.salesOrganisation.customerInfos.user:id,uuid,firstname,lastname,email',
            'PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
            'PDPChannels.channel:id,uuid,parent_id,name,node_level,status',
            'PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
            'PDPCustomerCategories.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
            'PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
            'PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
            'PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id',
            'PDPItemMajorCategories.itemMajorCategory:id,uuid,name,parent_id,node_level',
            'PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
            'PDPItemGroups.itemGroup:id,uuid,name,code,status',
            'PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
            'PDPItems.item:id,uuid,item_name,item_code,status',
            'PDPItems.itemUom:id,uuid,name',
            'PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,item_qty,price',
            'PDPPromotionItems.item:id,uuid,item_code,item_name',
            'PDPPromotionItems.itemUom:id,uuid,name,code,status',
            'PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
            'PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
            'PDPPromotionOfferItems.itemUom:id,uuid,name,code,status',
            'PDPLob:id,price_disco_promo_plan_id,lob_id',
            'PDPLob.lob:id,name',
            'lob:id,name'
        )
            ->where('use_for', request()->type)
            ->where('start_date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->orderBy('id', 'desc')
            ->get();

        $price_discon_promo_plan_array = array();
        if (is_object($price_discon_promo_plan)) {
            foreach ($price_discon_promo_plan as $key => $price_discon_promo_plan1) {
                $price_discon_promo_plan_array[] = $price_discon_promo_plan[$key];
            }
        }

        $data_array = array();
        $page = (isset(request()->page)) ? request()->page : '';
        $limit = (isset(request()->page_size)) ? request()->page_size : '';

        $pagination = array();

        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($price_discon_promo_plan_array[$offset])) {
                    $data_array[] = $price_discon_promo_plan_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($price_discon_promo_plan_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($price_discon_promo_plan_array);
        } else {
            $data_array = $price_discon_promo_plan_array;
        }

        return prepareResult(true, $data_array, [], "Price disc plan listing", $this->success, $pagination);
    }

    public function PDPMobileIdexnPricing(Request $request)
    {

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "pdpMobilePricing");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating pricing plan", $this->unprocessableEntity);
        }

        $records = [];
        $customer_Ids = [];

        if ($request->route_id) {
            $customer_routes = CustomerRoute::select('customer_id')
                ->where('route_id', $request->route_id)
                ->get();

            if (count($customer_routes)) {
                $customer_Ids = $customer_routes->pluck('customer_id')->toArray();
            }
        }

        // if salesman id came in request find the cusotmer by delivery
        if ($request->salesman_id) {
            $delivery = Delivery::select('customer_id')
                ->where('salesman_id', $request->salesman_id)
                ->get();

            if (count($delivery)) {
                $customer_user_Ids = $delivery->pluck('customer_id')->toArray();
                $customer_info = CustomerInfo::select('id')->whereIn('user_id', $customer_user_Ids)->get();
                if (count($customer_info)) {
                    $customer_Ids = $customer_info->pluck('id')->toArray();
                }
            }
        }

        // if merchandiser_id came in request find the cusotmer by CustomerMerchandiser
        if ($request->merchandiser_id) {
            $cm = CustomerMerchandiser::select('customer_id')
                ->where('merchandiser_id', $request->merchandiser_id)
                ->get();

            if (count($cm)) {
                $customer_user_Ids = $cm->pluck('customer_id')->toArray();
                $customer_info = CustomerInfo::select('id')->whereIn('user_id', $customer_user_Ids)->get();
                if (count($customer_info)) {
                    $customer_Ids = $customer_info->pluck('id')->toArray();
                }
            }
        }

        $datas = PDPCustomer::with(
            'customerInfo.user:id,uuid,firstname,lastname',
            'priceDiscoPromoPlanData',
            'priceDiscoPromoPlanData.PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
            'priceDiscoPromoPlanData.PDPItems.item:id,uuid,item_name,item_code,status',

            'priceDiscoPromoPlanData.PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
            'priceDiscoPromoPlanData.PDPRoutes.route:id,uuid,route_code,route_name,status',

            'priceDiscoPromoPlanData.PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
            'priceDiscoPromoPlanData.PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
            'priceDiscoPromoPlanData.PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
            'priceDiscoPromoPlanData.PDPRegions.region:id,uuid,region_code,region_name,region_status',
            'priceDiscoPromoPlanData.PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
            'priceDiscoPromoPlanData.PDPAreas.area:id,uuid,parent_id,area_name,node_level,status',

            'priceDiscoPromoPlanData.PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
            'priceDiscoPromoPlanData.PDPSalesOrganisations.salesOrganisation.customerInfos.user:id,uuid,firstname,lastname,email',
            'priceDiscoPromoPlanData.PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
            'priceDiscoPromoPlanData.PDPChannels.channel:id,uuid,parent_id,name,node_level,status',
            'priceDiscoPromoPlanData.PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
            'priceDiscoPromoPlanData.PDPCustomerCategories.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
            'priceDiscoPromoPlanData.PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
            'priceDiscoPromoPlanData.PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
            'priceDiscoPromoPlanData.PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id',
            'priceDiscoPromoPlanData.PDPItemMajorCategories.itemMajorCategory:id,uuid,name,parent_id,node_level',
            'priceDiscoPromoPlanData.PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
            'priceDiscoPromoPlanData.PDPItemGroups.itemGroup:id,uuid,name,code,status',
            'priceDiscoPromoPlanData.PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
            'priceDiscoPromoPlanData.PDPItems.item:id,uuid,item_name,item_code,status',
            'priceDiscoPromoPlanData.PDPItems.itemUom:id,uuid,name',
            'priceDiscoPromoPlanData.PDPCombinationSlabs:id,price_disco_promo_plan_id,item_uom_id,from_qty,to_qty,offer_qty',
            'priceDiscoPromoPlanData.PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,item_qty,price',
            'priceDiscoPromoPlanData.PDPPromotionItems.item:id,uuid,item_code,item_name',
            'priceDiscoPromoPlanData.PDPPromotionItems.itemUom:id,uuid,name,code,status',
            'priceDiscoPromoPlanData.PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
            'priceDiscoPromoPlanData.PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
            'priceDiscoPromoPlanData.PDPPromotionOfferItems.itemUom:id,uuid,name,code,status'
        )
            ->whereIn('customer_id', $customer_Ids)
            ->orderBy('id', 'desc')
            ->get();

        $count_numc = 0;
        foreach ($datas as $key => $data) {

            foreach ($data['priceDiscoPromoPlanData'] as $key1 => $priceDiscoPromoPlanData) {

                $records[$count_numc]['customer_id'] = $data['customer_id'];
                $records[$count_numc]['customer_code'] = $data['customerInfo']['customer_code'];
                $records[$count_numc]['customer_name'] = $data['customerInfo']['user']->firstname . ' ' . $data['customerInfo']['user']->lastname;
                $records[$count_numc]['Priceinplan'][$key1]['price_disco_promo_plans_id'] = $priceDiscoPromoPlanData['id'];
                $records[$count_numc]['Priceinplan'][$key1]['price_disco_promo_plans_name'] = $priceDiscoPromoPlanData['name'];
                $records[$count_numc]['Priceinplan'][$key1]['combination_plan_key_id'] = $priceDiscoPromoPlanData['combination_plan_key_id'];
                $records[$count_numc]['Priceinplan'][$key1]['combination_plan_key_value'] = $priceDiscoPromoPlanData['combination_key_value'];
                $records[$count_numc]['Priceinplan'][$key1]['use_for'] = $priceDiscoPromoPlanData['use_for'];

                foreach ($priceDiscoPromoPlanData['pdpitems']  as $key2 => $item) {
                    $records[$count_numc]['Priceinplan'][$key1]['item'][$key2]['item_code'] = $item['item']['item_code'];
                    $records[$count_numc]['Priceinplan'][$key1]['item'][$key2]['item_uom_id'] = $item['item_uom_id'];
                    $records[$count_numc]['Priceinplan'][$key1]['item'][$key2]['price'] = $item['price'];
                }

                //$records[$key]['customer_id'] = $data['customer_id'];
                // $records[$key] = $data;

                $count_numc++;
            }
        }

        return prepareResult(true, $records, [], "Price disc plan listing", $this->success);
    }

    public function lobPrice(Request $request, $lob_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$lob_id) {
            return prepareResult(false, [], ["error" => "Error while validating lob id"], "Error while validating lob id.", $this->unprocessableEntity);
        }

        $pdp_items = PDPItem::with(
            'item:id,item_name,item_code',
            'itemUom:id,code,name'
        )
            ->where('lob_id', $lob_id)
            ->get();

        return prepareResult(true, $pdp_items, [], "Price disc plan lob price", $this->success);
    }

    public function PDPMobileIndexOther(Request $request)
    {

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = request()->json()->all();
        $validate = $this->validations($input, "pdpMobileOther");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating pricing plan", $this->unprocessableEntity);
        }

        $records = [];

        // $customer = DB::table('customer_infos')
        //     ->leftJoin("customer_lobs", "customer_infos.id", '=', "customer_lobs.customer_info_id")
        //     ->select(
        //         'customer_infos.id as customer_id',
        //     );

        // if (isset($request->route_id) && !empty($request->route_id)) {
        //     $customer = $customer->where('customer_infos.route_id', $request->route_id)
        //         ->orwhere('customer_lobs.route_id', $request->route_id);
        // }

        // $customer = $customer->orderBy('customer_infos.id', 'ASC');
        // $customers = $customer->get()->toarray();

        $customer_Ids = [];

        // foreach ($customers as $key => $customer) {
        //     $customer_Ids[$key] = $customer->customer_id;
        // }

        $customer_routes = CustomerRoute::select('customer_id')->where('route_id', $request->route_id)->get();
        if (count($customer_routes)) {
            $customer_Ids = $customer_routes->pluck('customer_id')->toArray();
        }

        $plandetails = PriceDiscoPromoPlan::with(
            'PDPCountries:id,uuid,price_disco_promo_plan_id,country_id',
            'PDPCountries.country:id,uuid,name,currency,currency_code,currency_symbol,status',
            'PDPRegions:id,uuid,price_disco_promo_plan_id,region_id',
            'PDPRegions.region:id,uuid,region_code,region_name,region_status',
            'PDPAreas:id,uuid,price_disco_promo_plan_id,area_id',
            'PDPAreas.area:id,uuid,parent_id,area_name,node_level,status',
            'PDPRoutes:id,uuid,price_disco_promo_plan_id,route_id',
            'PDPRoutes.route:id,uuid,route_code,route_name,status',
            'PDPSalesOrganisations:id,uuid,price_disco_promo_plan_id,sales_organisation_id',
            'PDPSalesOrganisations.salesOrganisation.customerInfos.user:id,uuid,firstname,lastname,email',
            'PDPChannels:id,uuid,price_disco_promo_plan_id,channel_id',
            'PDPChannels.channel:id,uuid,parent_id,name,node_level,status',
            'PDPCustomerCategories:id,uuid,price_disco_promo_plan_id,customer_category_id',
            'PDPCustomerCategories.customerCategory:id,uuid,customer_category_code,customer_category_name,status',
            'PDPCustomers:id,uuid,price_disco_promo_plan_id,customer_id',
            'PDPCustomers.customerInfo.user:id,uuid,firstname,lastname',
            'PDPItemMajorCategories:id,uuid,price_disco_promo_plan_id',
            'PDPItemMajorCategories.itemMajorCategory:id,uuid,name,parent_id,node_level',
            'PDPItemGroups:id,uuid,price_disco_promo_plan_id,item_group_id',
            'PDPItemGroups.itemGroup:id,uuid,name,code,status',
            'PDPItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,price',
            'PDPItems.item:id,uuid,item_name,item_code,status',
            'PDPItems.itemUom:id,uuid,name',
            'PDPCombinationSlabs:id,price_disco_promo_plan_id,item_uom_id,from_qty,to_qty,offer_qty',
            'PDPPromotionItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,item_qty,price',
            'PDPPromotionItems.item:id,uuid,item_code,item_name',
            'PDPPromotionItems.itemUom:id,uuid,name,code,status',
            'PDPPromotionOfferItems:id,uuid,price_disco_promo_plan_id,item_id,item_uom_id,offered_qty',
            'PDPPromotionOfferItems.item:id,uuid,item_code,item_name',
            'PDPPromotionOfferItems.itemUom:id,uuid,name,code,status'
        )

            ->where('use_for', $request->type)
            ->where('start_date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        $plan_ids = [];

        foreach ($plandetails as $key => $plandetail) {
            $plan_ids[] = $plandetail->id;
        }

        $a = 0;
        $count_numc = (int)$a;
        $records = [];
        foreach ($plandetails as $key => $plandetail) {
            $case = 0;
            foreach ($plandetail['PDPRoutes'] as $key => $PDPRoutes) {
                if (in_array($PDPRoutes->price_disco_promo_plan_id, $plan_ids) && ($PDPRoutes->route_id == $request->route_id)) {
                    $records[$count_numc] = $plandetail;
                    $case = 1;
                }
            }

            foreach ($plandetail['PDPCustomers'] as $key => $PDPCustomers) {
                if (in_array($PDPCustomers->price_disco_promo_plan_id, $plan_ids) && in_array($PDPCustomers->customer_id, $customer_Ids)) {
                    $records[$count_numc] = $plandetail;
                    $case = 1;
                }
            }

            if ($case) {
                $count_numc++;
            }
        }

        return prepareResult(true, $records, [], "Price disc plan listing", $this->success);
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $data = array(
            "Name", "Combination Key Value", "Start Date", "End Date", "Area Name", "Channel", "Country", "Customer Code", "Customer Category", "Item Code", "Item Uom", "Item Price", "Item Group", "Item Major Category", "Lob Name", "Region Name", "Route Code"
        );

        return prepareResult(true, $data, [], "Pricing Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'pricing' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate pricing import", $this->unauthorized);
        }

        $errors = array();

        try {
            $map_key_value          = $request->map_key_value;
            $map_key_value_array    = json_decode($map_key_value, true);
            $file                   = request()->file('pricing')->store('import');
            $filename               = storage_path("app/" . $file);
            $fp                     = fopen($filename, "r");
            $content                = fread($fp, filesize($filename));
            $lines                  = explode("\n", $content);
            $heading_array_line     = isset($lines[0]) ? $lines[0] : '';
            $heading_array          = explode(",", trim($heading_array_line));
            fclose($fp);

            if (!$heading_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }
            if (!$map_key_value_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }

            $import = new PricingImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            $succussrecords = 0;
            $successfileids = 0;

            if ($import->successAllRecords()) {
                $succussrecords = count($import->successAllRecords());
                $data = json_encode($import->successAllRecords());
                $fileName = time() . '_datafile.txt';
                File::put(storage_path() . '/app/tempimport/' . $fileName, $data);

                $importtempfiles = new ImportTempFile();
                $importtempfiles->FileName = $fileName;
                $importtempfiles->save();
                $successfileids = $importtempfiles->id;
            }
            $errorrecords = 0;
            $errror_array = array();
            if ($import->failures()) {
                foreach ($import->failures() as $failure_key => $failure) {
                    if ($failure->row() != 1) {
                        $failure->row(); // row that went wrong
                        $failure->attribute(); // either heading key (if using heading row concern) or column index
                        $failure->errors(); // Actual error messages from Laravel validator
                        $failure->values(); // The values of the row that has failed.

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
                            $error_result = array();
                            $error_row_loop = 0;
                            foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
                                $error_result[$map_key_value_array_value] = isset($failure->values()[$error_row_loop]) ? $failure->values()[$error_row_loop] : '';
                                $error_row_loop++;
                            }
                            $errror_array[] = array(
                                'errormessage' => "There was an error on row " . $failure->row() . ". " . $error_msg,
                                'errorresult' => $error_result, //$failure->values(),
                            );
                        }
                    }
                }
                $errorrecords = count($errror_array);
            }
            $errors = $errror_array;
            $result['successrecordscount'] = $succussrecords;
            $result['skipduplicate'] = $request->skipduplicate;
            $result['errorrcount'] = $errorrecords;
            $result['successfileids'] = $successfileids;
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                if ($failure->row() != 1) {
                    info($failure->row());
                    info($failure->attribute());
                    $failure->row(); // row that went wrong
                    $failure->attribute(); // either heading key (if using heading row concern) or column index
                    $failure->errors(); // Actual error messages from Laravel validator
                    $failure->values(); // The values of the row that has failed.
                    $errors[] = $failure->errors();
                }
            }

            return prepareResult(true, [], $errors, "Failed to validate bank import", $this->success);
        }
        return prepareResult(true, $result, $errors, "Pricing successfully imported", $this->success);
    }

    public function finalimport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        $skipduplicate = $request->skipduplicate;

        //$skipduplicate = 1 means skip the data
        //$skipduplicate = 0 means overwrite the data

        if ($importtempfile) {
            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            $old_price = 0;
            $pricing_array = array();
            if ($finaldata) :
                foreach ($finaldata as $row) :
                    if (isset($row[0]) && $row[0] == 'Name') {
                        continue;
                    }

                    if (empty($row[1])) {
                        continue;
                    }

                    $combination_plan_keys = CombinationPlanKey::where('combination_key', $row[1])
                        ->where('status', 1)
                        ->first();

                    if (!$combination_plan_keys) {
                        $master_key = explode('/', $row[1]);
                        $combination_keys = CombinationMaster::whereIn('name', $master_key)->get();
                        $name = $combination_keys->pluck('name')->toArray();
                        $key_codes = $combination_keys->pluck('id')->toArray();

                        $combination_plan_keys = new CombinationPlanKey;
                        $combination_plan_keys->combination_key_name    = implode(" ", $name);
                        $combination_plan_keys->combination_key         = implode('/', $name);
                        $combination_plan_keys->combination_key_code    = implode('/', $key_codes);
                        $combination_plan_keys->status = 1;
                        $combination_plan_keys->save();
                    }


                    if ($skipduplicate == 1) {

                        $price = PriceDiscoPromoPlan::where('name', $row[0])
                            ->where('combination_plan_key_id', $combination_plan_keys->id)
                            ->where('status', 1)
                            ->first();

                        if ($price) {
                            continue;
                        }

                        DB::beginTransaction();
                        try {
                            $price = PriceDiscoPromoPlan::where('name', $row[0])
                                ->where('combination_plan_key_id', $combination_plan_keys->id)
                                ->first();

                            if (!$price) {
                                $price = $this->savePricing($row, $combination_plan_keys->id, "pricing", $skipduplicate);
                            }

                            if (!in_array($price->id, $pricing_array)) {
                                $pricing_array[] = $price->id;
                            }

                            $this->saveArea($price, $row);
                            $this->saveChannel($price, $row);
                            $this->saveCountries($price, $row);
                            $this->saveCustomer($price, $row);
                            $this->saveCustomerCategory($price, $row);
                            $this->saveItem($price, $row);
                            $this->saveItemGroup($price, $row);
                            $this->saveItemMajorCategory($price, $row);
                            $this->saveLob($price, $row);
                            $this->saveRegion($price, $row);
                            $this->saveRoute($price, $row);

                            DB::commit();
                        } catch (\Exception $exception) {
                            DB::rollback();
                            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        } catch (\Throwable $exception) {
                            DB::rollback();
                            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    } else {

                        DB::beginTransaction();
                        try {

                            $price = PriceDiscoPromoPlan::where('name', $row[0])
                                ->where('combination_plan_key_id', $combination_plan_keys->id)
                                ->first();

                            if (!$price) {
                                $price = $this->savePricing($row, $combination_plan_keys->id, "pricing", $skipduplicate);
                            }

                            $new_price = $price->id;

                            if ($new_price != $old_price) {

                                PDPArea::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPChannel::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPCountry::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPCustomer::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPCustomerCategory::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPItem::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPItemGroup::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPItemMajorCategory::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPPromotionItem::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPPromotionOfferItem::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPDiscountSlab::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPRegion::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPRoute::where('price_disco_promo_plan_id', $price->id)->delete();
                                PDPSalesOrganisation::where('price_disco_promo_plan_id', $price->id)->delete();

                                $old_price = $price->id;
                            }

                            $this->saveArea($price, $row);
                            $this->saveChannel($price, $row);
                            $this->saveCountries($price, $row);
                            $this->saveCustomer($price, $row);
                            $this->saveCustomerCategory($price, $row);
                            $this->saveItem($price, $row);
                            $this->saveItemGroup($price, $row);
                            $this->saveItemMajorCategory($price, $row);
                            $this->saveLob($price, $row);
                            $this->saveRegion($price, $row);
                            $this->saveRoute($price, $row);

                            DB::commit();
                        } catch (\Exception $exception) {
                            DB::rollback();
                            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        } catch (\Throwable $exception) {
                            DB::rollback();
                            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                        }
                    }
                endforeach;

                if (count($pricing_array)) {
                    $pricing = PriceDiscoPromoPlan::whereIn('id', $pricing_array)
                        ->update([
                            'status' => 1
                        ]);
                }
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Pricing successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveArea($price, $row)
    {
        if ($row[4] == "") {
            return;
        }

        $area = Area::where('area_name', 'like', '%' . $row[4] . '%')->first();
        if ($area) {
            $pdpArea = PDPArea::where('price_disco_promo_plan_id', $price->id)
                ->where('area_id', $area->id)
                ->first();
            if (!is_object($pdpArea)) {
                $pdpArea = new PDPArea;
            }
            $pdpArea->price_disco_promo_plan_id = $price->id;
            $pdpArea->area_id = $area->id;
            $pdpArea->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveChannel($price, $row)
    {
        if (!isset($row[5]) && !$row[5]) {
            return;
        }

        $channel = Channel::where('name', 'like', '%' . $row[5] . '%')->first();
        if ($channel) {
            $PDPChannel = PDPChannel::where('price_disco_promo_plan_id', $price->id)
                ->where('channel_id', $channel->id)
                ->first();
            if (!is_object($PDPChannel)) {
                $PDPChannel = new PDPChannel;
            }
            $PDPChannel->price_disco_promo_plan_id = $price->id;
            $PDPChannel->channel_id = $channel->id;
            $PDPChannel->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveCountries($price, $row)
    {
        if (!isset($row[6]) && !$row[6]) {
            return;
        }

        $country = Country::where('name', 'like', '%' . $row[6] . '%')->first();
        if ($country) {
            $PDPCountry = PDPCountry::where('price_disco_promo_plan_id', $price->id)
                ->where('country_id', $country->id)
                ->first();
            if (!is_object($PDPCountry)) {
                $PDPCountry = new PDPCountry;
            }
            $PDPCountry->price_disco_promo_plan_id = $price->id;
            $PDPCountry->country_id = $country->id;
            $PDPCountry->save();
        }
    }
    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveCustomer($price, $row)
    {
        if (!isset($row[7]) && !$row[7]) {
            return;
        }

        $customer = CustomerInfo::where('customer_code', $row[7])->first();

        if ($customer) {
            $PDPCustomer = PDPCustomer::where('price_disco_promo_plan_id', $price->id)
                ->where('customer_id', $customer->id)
                ->first();

            if (!is_object($PDPCustomer)) {
                $PDPCustomer = new PDPCustomer;
            }
            $PDPCustomer->price_disco_promo_plan_id  = $price->id;
            $PDPCustomer->customer_id                = $customer->id;
            $PDPCustomer->save();
        }
    }
    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveCustomerCategory($price, $row)
    {
        if (!isset($row[8]) && !$row[8]) {
            return;
        }

        $CustomerCategory = CustomerCategory::where('customer_category_name', 'like', '%' . $row[8] . '%')->first();
        if ($CustomerCategory) {
            $PDPCustomerCategory = PDPCustomerCategory::where('price_disco_promo_plan_id', $price->id)
                ->where('customer_category_id', $CustomerCategory->id)
                ->first();

            if (!is_object($PDPCustomerCategory)) {
                $PDPCustomerCategory = new PDPCustomerCategory;
            }
            $PDPCustomerCategory->price_disco_promo_plan_id = $price->id;
            $PDPCustomerCategory->customer_category_id = $CustomerCategory->id;
            $PDPCustomerCategory->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveItem($price, $row)
    {
        if (!isset($row[9]) && !$row[9]) {
            return;
        }

        if (!isset($row[10]) && !$row[10]) {
            return;
        }

        $item = Item::where('item_code', $row[9])->first();
        $itemUom = ItemUom::where('name', 'like', '%' . $row[10] . '%')->first();
        if ($item && $itemUom) {
            $PDPItem = PDPItem::where('price_disco_promo_plan_id', $price->id)
                ->where('item_id', $item->id)
                ->where('item_uom_id', $itemUom->id)
                ->first();

            if (!is_object($PDPItem)) {
                $PDPItem = new PDPItem;
            }

            $PDPItem->price_disco_promo_plan_id = $price->id;
            $PDPItem->item_id       = $item->id;
            $PDPItem->item_uom_id   = $itemUom->id;
            $PDPItem->price         = $row[11];
            $PDPItem->lob_id        = null;
            // $PDPItem->max_price     = $row[8];
            $PDPItem->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveItemGroup($price, $row)
    {
        if (!isset($row[12]) && !$row[12]) {
            return;
        }

        $itemGroup = ItemGroup::where('name', 'like', '%' . $row[12] . '%')->first();
        if ($itemGroup) {
            $PDPItemGroup = PDPItemGroup::where('price_disco_promo_plan_id', $price->id)
                ->where('item_group_id', $itemGroup->id)
                ->first();
            if (!is_object($PDPItemGroup)) {
                $PDPItemGroup = new PDPItemGroup;
            }
            $PDPItemGroup->price_disco_promo_plan_id = $price->id;
            $PDPItemGroup->item_group_id = $itemGroup->id;
            $PDPItemGroup->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveItemMajorCategory($price, $row)
    {
        if ($row[13] == "") {
            return;
        }

        $imc = ItemMajorCategory::where('name', 'like', '%' . $row[13] . '%')->first();
        if ($imc) {
            $PDPItemMajorCategory = PDPItemMajorCategory::where('price_disco_promo_plan_id', $price->id)
                ->where('item_major_category_id', $imc->id)
                ->first();

            if (!is_object($PDPItemMajorCategory)) {
                $PDPItemMajorCategory = new PDPItemMajorCategory;
            }

            $PDPItemMajorCategory->price_disco_promo_plan_id = $price->id;
            $PDPItemMajorCategory->item_major_category_id = $imc->id;
            $PDPItemMajorCategory->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveLob($price, $row)
    {
        if (!isset($row[14]) && !$row[14]) {
            return;
        }

        $lob = Lob::where('name', 'like', '%' . $row[14] . '%')->first();
        if ($lob) {
            $PDPLob = PDPLob::where('price_disco_promo_plan_id', $price->id)
                ->where('lob_id', $lob->id)
                ->first();
            if (!is_object($PDPLob)) {
                $PDPLob = new PDPLob;
            }
            $PDPLob->price_disco_promo_plan_id = $price->id;
            $PDPLob->lob_id = $lob->id;
            $PDPLob->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveRegion($price, $row)
    {
        if (!isset($row[15]) && !$row[15]) {
            return;
        }

        $region = Region::where('region_name', 'like', '%' . $row[15] . '%')->first();
        if ($region) {
            $PDPRegion = PDPRegion::where('price_disco_promo_plan_id', $price->id)
                ->where('region_id', $region->id)
                ->first();

            if (!is_object($PDPRegion)) {
                $PDPRegion = new PDPRegion;
            }
            $PDPRegion->price_disco_promo_plan_id = $price->id;
            $PDPRegion->region_id = $region->id;
            $PDPRegion->save();
        }
    }

    /**
     * This is the sub function of price promotion discount
     *
     * @param [type] $price
     * @param [type] $row
     * @return void
     */
    private function saveRoute($price, $row)
    {
        if (!isset($row[16]) && !$row[16]) {
            return;
        }

        $route = Route::where('route_code', 'like', '%' . $row[16] . '%')->first();
        if ($route) {
            $pdpRoute = PDPRoute::where('price_disco_promo_plan_id', $price->id)
                ->where('route_id', $route->id)
                ->first();

            if (!is_object($pdpRoute)) {
                $pdpRoute = new PDPRoute;
            }

            $pdpRoute->price_disco_promo_plan_id = $price->id;
            $pdpRoute->route_id = $route->id;
            $pdpRoute->save();
        }
    }

    private function savePricing($row, $combination_plan_keys, $use_for, $skip)
    {
        $price_discon_promo_plan = new PriceDiscoPromoPlan;
        $price_discon_promo_plan->combination_plan_key_id   = $combination_plan_keys;
        $price_discon_promo_plan->use_for                   = $use_for;
        $price_discon_promo_plan->name                      = $row[0];
        $price_discon_promo_plan->start_date                = Carbon::parse($row[2])->format('Y-m-d');
        $price_discon_promo_plan->end_date                  = Carbon::parse($row[3])->format('Y-m-d');
        $price_discon_promo_plan->combination_key_value     = $row[1];
        $price_discon_promo_plan->priority_sequence         = count(explode('/', $row[1]));
        if ($skip) {
            $price_discon_promo_plan->status                    = 0;
        } else {
            $price_discon_promo_plan->status                    = 1;
        }
        if ($use_for == "Pricing") {
        }

        if ($use_for == 'Promotion') {
            // $price_discon_promo_plan->order_item_type = $request->order_item_type;
            // $price_discon_promo_plan->offer_item_type = $request->offer_item_type;
        }

        if ($use_for == 'Discount') {
            // $price_discon_promo_plan->type                  = $request->type;
            // $price_discon_promo_plan->qty_from              = $request->qty_from;
            // $price_discon_promo_plan->qty_to                = $request->qty_to;
            // $price_discon_promo_plan->discount_type         = $request->discount_type;
            // $price_discon_promo_plan->discount_apply_on     = (!empty($request->discount_apply_on)) ? $request->discount_apply_on : "0";
            // $price_discon_promo_plan->discount_value        = (!empty($request->discount_value)) ? $request->discount_value : "0.00";
            // $price_discon_promo_plan->discount_percentage   = $request->discount_percentage;
        }

        $price_discon_promo_plan->is_key_combination    = 0;
        $price_discon_promo_plan->discount_main_type    = 0;
        $price_discon_promo_plan->lob_id                = null;
        $price_discon_promo_plan->save();

        return $price_discon_promo_plan;
    }

    public function mobilePrice()
    {
        // $salesman_info = SalesmanInfo::where('user_id', request()->user()->id)->first();

        $dat = DeliveryAssignTemplate::where('delivery_driver_id', request()->user()->id)->get();
        
        if (count($dat)) {
            $customer_ids = $dat->pluck()->toArray();
            $price = CustomerBasedPricing::whereIn('customer_id', $customer_ids)
                ->where('start_date', '<=', date('Y-m-d'))
                ->where('end_date', '>=', date('Y-m-d'))
                ->orderBy('updated_at', 'asc')
                ->get();

            return prepareResult(true, $price, [], "pricing.", $this->success);
        }
        return prepareResult(false, [], ["error" => "No price found"], "User not authenticate.", $this->unauthorized);
    }
}
