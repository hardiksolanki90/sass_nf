<?php

namespace App\Http\Controllers\Api;

use App\Model\Area;
use App\Model\Channel;
use App\Model\Country;
use App\Model\CustomerCategory;
use App\Model\CustomerInfo;
use App\Model\CustomerType;
use App\Model\ItemGroup;
use App\Model\ItemMajorCategory;
use App\Model\Region;
use App\Model\Route;
use App\Model\Reason;
use App\Model\SalesOrganisation;
use App\Model\OrderType;
use App\Model\Depot;
use App\Model\Item;
use App\Model\UserLoginLog;
use App\User;
use App\Http\Controllers\Controller;
use App\Imports\AdidUpdateImport;
use App\Imports\CustomerMerchandiserImport;
use App\Model\Accounts;
use App\Model\Brand;
use App\Model\CodeSetting;
use App\Model\CombinationPlanKey;
use App\Model\CountryMaster;
use App\Model\Currency;
use App\Model\ItemUom;
use App\Model\ItemUomMaster;
use App\Model\Organisation;
use App\Model\PaymentTerm;
use App\Model\Plan;
use App\Model\PlanFeature;
use App\Model\SalesmanInfo;
use App\Model\SalesmanRole;
use App\Model\SalesmanType;
use App\Model\SettingMenu;
use App\Model\Software;
use App\Model\Subscription;
use App\Model\UserLoginTrack;
use App\Model\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\MerchandiserUpdated;
use App\Model\OrganisationRole;
use App\Model\OrganisationRoleAttached;
use App\Model\OrganisationTheme;
use App\Model\Storagelocation;
use App\Model\Warehouse;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class GlobalController extends Controller
{

    private $is_org = false;

    public function masterDataMobile(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $channel = array();
        $region = array();
        $customer_category = array();
        $sales_organisation = array();

        $region = Region::select('id', 'region_name', 'region_status')
            ->where('region_status', 1)
            ->get();

        $sales_organisation = SalesOrganisation::select('id', 'name', 'parent_id', 'node_level', 'status')
            ->where('status', 1)
            ->get();

        $channel = Channel::select('id', 'name', 'parent_id', 'node_level', 'status')
            ->where('status', 1)
            ->get();

        $customer_category = CustomerCategory::select('id', 'customer_category_code as code', 'customer_category_name as name', 'parent_id', 'node_level', 'status')
            ->where('status', 1)
            ->get();

        $data = array(
            'region' => $region,
            'sales_organisation' => $sales_organisation,
            'channel' => $channel,
            'customer_category' => $customer_category
        );

        return prepareResult(true, $data, [], "Master listing", $this->success);
    }

    public function masterData(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $country = array();
        $country_master = array();
        $org_country = array();
        $currency = array();
        $channel = array();
        $region = array();
        $area = array();
        $area_list = array();
        $route = array();
        $sales_organisation = array();
        $sales_organisation_list = array();
        $salesman_role = array();
        $salesman_type = array();
        $brand = array();
        $brand_list = array();
        $customer_category = array();
        $customer_type = array();
        $item_major_category = array();
        $item_group = array();
        $item_uom = array();
        $payment_term = array();
        $vendor = array();
        $reason = array();
        $account = array();
        $role = array();
        $nsm = array();
        $asm = array();
        $order_type = array();
        $items = array();
        $customers = array();
        $depot = array();
        $salesmans = array();
        $merchandiser = array();
        $promotional_item = array();
        $item_major_category_list = array();
        $reason_list = array();
        $channel_list = array();
        $salesman_supervisor = array();
        $all_salesman_supervisor = array();
        $order_created_user = array();
        $storage_location = array();
        $warehouse = array();

        $organisation = Organisation::where('id', $request->user()->organisation_id)->first();
        $user = request()->user();
        $usertype = $user->usertype;
        $login_user_id = $user->id;

        if ($request->function_for) {
            $variable = $request->function_for;
            $nextComingNumber['number_is'] = null;
            $nextComingNumber['prefix_is'] = null;
            if (CodeSetting::count() > 0) {
                $code_setting = CodeSetting::first();
                if ($code_setting['is_final_update_' . $variable] == 1) {
                    $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                    $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
                }
            }

            $code = $nextComingNumber;
        } else {
            $code = 0;
        }

        $listData = $request->list_data;
        $login_user_id = request()->user()->id;

        $all_customers = getSalesman(true);
        $all_salesmans = getSalesman();

        if (in_array('order_users', $listData)) {
            $order_created_user = User::select('id', 'firstname', 'lastname')->where('role_id', 11)->get();
        }

        if (in_array('storage_location', $listData)) {
            $storage_location = Storagelocation::select('id', 'code', 'name')
                ->where('warehouse_type', 34)
                ->get();
        }

        if (in_array('warehouse', $listData)) {
            $warehouse = Warehouse::select('id', 'code', 'name')
                ->get();
        }

        if (in_array('country', $listData)) {
            $country = Country::first();
        }

        if (in_array('country_master', $listData)) {
            $country_master = CountryMaster::get();
        }

        if (in_array('org_country', $listData)) {
            $org_country = Country::get();
        }

        // if (in_array('account', $listData)) {
        //     $account = Accounts::select('id', 'account_name')->get();
        // }

        if (in_array('region', $listData)) {
            $regions_ids = array();
            if (count($all_customers)) {
                $customerInfos = CustomerInfo::whereIn('user_id', $all_customers)
                    ->groupBy('region_id')
                    ->get();
                $regions_ids = $customerInfos->pluck('region_id')->toArray();
            }

            $region_query = Region::where('region_status', 1);

            if (count($regions_ids)) {
                $region_query->whereIn('id', $regions_ids);
            }

            $region = $region_query->get();
        }

        if (in_array('currency', $listData)) {
            $currency = Currency::where('default_currency', 1)->first();
        }

        if (in_array('reason', $listData)) {
            $reason = Reason::select('id', 'name', 'parent_id', 'status')
                ->with('children')
                ->whereNull('parent_id')
                ->where('status', 1)
                ->get();
        }

        if (in_array('reason_list', $listData)) {
            $reason_list = Reason::select('id', 'name', 'parent_id', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('depot', $listData)) {
            $depot = Depot::select('id', 'depot_name', 'depot_manager', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('area', $listData)) {
            $area = Area::select('id', 'area_name', 'parent_id', 'node_level', 'status')
                ->with('children')
                ->where('status', 1)
                ->whereNull('parent_id')
                ->get();
        }

        if (in_array('area_list', $listData)) {
            $area_list = Area::select('id', 'area_name', 'parent_id', 'node_level', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('route', $listData)) {
            $route = Route::select('id', 'route_name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('sales_organisation', $listData)) {
            $sales_organisation = SalesOrganisation::select('id', 'name', 'parent_id', 'node_level', 'status')
                ->with(
                    'children'
                )
                ->where('status', 1)
                ->whereNull('parent_id')
                ->get();
        }

        if (in_array('sales_organisation_list', $listData)) {
            $sales_organisation_list = SalesOrganisation::select('id', 'name', 'parent_id', 'node_level', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('salesman_role', $listData)) {
            $salesman_role = SalesmanRole::select('id', 'name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('salesman_type', $listData)) {
            $salesman_type = SalesmanType::select('id', 'name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('channel', $listData)) {
            $channel_ids = array();
            if (count($all_customers)) {
                $customerInfos = CustomerInfo::whereIn('user_id', $all_customers)
                    ->groupBy('channel_id')
                    ->get();
                $channel_ids = $customerInfos->pluck('channel_id')->toArray();
            }

            $channel_query = Channel::select('id', 'name', 'parent_id', 'node_level', 'status')
                ->with('children')
                ->where('status', 1)
                ->whereNull('parent_id');

            if (count($channel_ids)) {
                $channel_query->whereIn('id', $channel_ids);
            }

            $channel = $channel_query->get();
        }

        if (in_array('org-role', $listData)) {
            $user = request()->user();
            if (isset($user->role_id) && $user->role_id) {
                $role = OrganisationRole::where('id', $user->role_id)->first();
                $data = array();
                $global = array(
                    'Channel',
                    'Region',
                    'Merchandiser',
                );

                if ($role->name == "NSM") {
                    $data = array(
                        "ASM",
                        "Supervisor"
                    );
                }

                if ($role->name == "ASM") {
                    $data = array(
                        "Supervisor"
                    );
                }

                if ($role->name == "Sales Analyst") {
                    $data = array(
                        'NSM',
                        "ASM",
                        "Supervisor"
                    );
                }

                if ($role->name == "Sales Director") {
                    $data = array(
                        'NSM',
                        "ASM",
                        "Supervisor"
                    );
                }

                if ($role->name == "Marketing") {
                    $data = array(
                        'NSM',
                        "ASM",
                        "Supervisor"
                    );
                }

                if ($role->name == "IT Admin") {
                    $data = array(
                        'NSM',
                        "ASM",
                        "Supervisor"
                    );
                }

                if ($role->name == "org-admin") {
                    $data = array(
                        'NSM',
                        "ASM",
                        "Supervisor"
                    );
                }

                $role = array_merge($data, $global);
            }
        }

        if (in_array('channel_list', $listData)) {
            $channel_ids = array();
            if (count($all_customers)) {
                $customerInfos = CustomerInfo::whereIn('user_id', $all_customers)
                    ->groupBy('channel_id')
                    ->get();
                $channel_ids = $customerInfos->pluck('channel_id')->toArray();
            }

            $channel_list_query = Channel::select('id', 'name', 'parent_id', 'node_level', 'status')
                ->where('status', 1);

            if (count($channel_ids)) {
                $channel_query->whereIn('id', $channel_ids);
            }
            $channel_list = $channel_list_query->get();
        }

        if (in_array('customer_category', $listData)) {
            $customer_category = CustomerCategory::select('id', 'customer_category_code as code', 'customer_category_name as name', 'parent_id', 'node_level', 'status')
                ->with('children')
                ->where('status', 1)
                ->whereNull('parent_id')
                ->get();
        }

        if (in_array('customer_type', $listData)) {
            $customer_type = CustomerType::select('id', 'customer_type_name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('major_category‌', $listData)) {
            $item_major_category = ItemMajorCategory::select('id', 'parent_id', 'name', 'node_level', 'status')
                ->with('children')
                ->where('status', 1)
                ->whereNull('parent_id')
                ->get();
        }

        if (in_array('major_category_list', $listData)) {
            $item_major_category_list = ItemMajorCategory::select('id', 'parent_id', 'name', 'node_level', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('item_group', $listData)) {
            $item_group = ItemGroup::select('id', 'name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('item_uom', $listData)) {
            $item_uom = ItemUom::select('id', 'name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('brand', $listData)) {
            $brand = Brand::select('id', 'parent_id', 'brand_name', 'node_level', 'status')
                ->with('children')
                ->where('status', 1)
                ->whereNull('parent_id')
                ->get();
        }

        if (in_array('brand_list', $listData)) {
            $brand_list = Brand::select('id', 'parent_id', 'brand_name', 'node_level', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('payment_term', $listData)) {
            $payment_term = PaymentTerm::select('id', 'name', 'number_of_days', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('vendor', $listData)) {
            $vendor = Vendor::select('id', 'firstname', 'lastname')
                ->get();
        }

        if (in_array('item', $listData)) {
            $items = Item::select('id', 'item_name', 'item_code', 'status', 'current_stage', 'lower_unit_uom_id')
                ->with('itemUomLowerUnit:id,name')
                ->where('status', 1)
                ->where('current_stage', 'Approved')
                ->get();
        }

        if (in_array('order_type', $listData)) {
            $order_type = OrderType::select('id', 'use_for', 'for_module', 'name', 'status')
                ->where('status', 1)
                ->get();
        }

        if (in_array('customer', $listData)) {
            $customers_query = User::select('id', 'firstname', 'lastname')
                ->with('customerInfo')
                ->where('usertype', 2)
                ->whereHas('customerInfo', function ($q) {
                    $q->where('status', 1)
                        ->where('current_stage', "Approved");
                });

            if (count($all_customers)) {
                $customers_query->whereIn('id', $all_customers);
            }

            $customers = $customers_query->get();
        }

        if (in_array('salesman', $listData)) {
            $salesmans_query = User::select('id', 'firstname', 'lastname')
                ->with('salesmanInfo')
                ->where('usertype', 3)
                ->where('status', 1)
                ->whereHas('salesmanInfo', function ($q) {
                    $q->where('status', 1)
                        ->where('current_stage', "Approved");
                });
            if (count($all_salesmans)) {
                $salesmans_query->whereIn('id', $all_salesmans);
            }
            $salesmans = $salesmans_query->get();
        }

        if (in_array('merchandiser', $listData)) {
            $merchandiser_query = SalesmanInfo::select('id', 'user_id', 'salesman_code', 'salesman_type_id', 'salesman_supervisor', 'status', 'current_stage')
                ->with(
                    'user:id,firstname,lastname,email,mobile,role_id,country_id,status',
                    'supervisorUser:id,firstname,lastname'
                )
                ->where('salesman_type_id', 2)
                ->where('status', 1)
                ->where('current_stage', 'Approved');

            if (count($all_salesmans)) {
                $merchandiser_query->whereIn('user_id', $all_salesmans);
            }

            $merchandiser = $merchandiser_query->get();
        }

        if (in_array('salesman_supervisor', $listData)) {
            // $all_salesman = getSalesman();

            // $salesman_supervisor_query = SalesmanInfo::select('id', 'user_id', 'salesman_supervisor', 'current_stage')
            //     ->with(
            //         'supervisorUser:id,firstname,lastname'
            //     )
            //     ->whereNotNull('salesman_supervisor');

            // if (count($all_salesmans)) {
            //     $salesman_supervisor_query->whereIn('user_id', $all_salesmans);
            // }

            // $salesman_supervisor = $salesman_supervisor_query->where('current_stage', 'Approved')
            //     ->groupBy('salesman_supervisor')
            //     ->get();

            // foreach ($salesman_supervisor as $key => $supervisor) {
            //     $salesman_supervisor[$key]->id = $supervisor->salesman_supervisor;
            // }

            $user = request()->user();

            $org_role = OrganisationRole::where('name', 'like', '%' . 'Supervisor' . '%')->first();

            if ($user->usertype == 1) {
                $salesman_supervisor = User::select('id', 'firstname', 'lastname', 'email', 'mobile')
                    ->where('role_id', $org_role->id)
                    ->where('status', 1)
                    ->get();
            } else {
                $organisation_role_attached = OrganisationRoleAttached::where('user_id', $user->id)->first();
                if (isset($organisation_role_attached->last_role_id)) {
                    $role_ids = explode(',', $organisation_role_attached->last_role_id);
                    $salesman_supervisor = User::select('id', 'firstname', 'lastname', 'email', 'mobile')
                        ->whereIn('id', $role_ids)
                        ->where('status', 1)
                        ->get();
                }
            }
        }

        if (in_array('all_salesman_supervisor', $listData)) {
            $org_role = OrganisationRole::where('name', 'like', '%' . 'Supervisor' . '%')
                ->first();
            if (is_object($org_role)) {
                $salesman_supervisor = User::select('id', 'firstname', 'lastname', 'email', 'mobile')
                    ->where('role_id', $org_role->id)
                    ->where('status', 1)
                    ->get();
            }
        }

        if (in_array('promotional_item', $listData)) {
            $promotional_item = Item::select('id', 'item_name', 'item_code', 'is_promotional', 'status', 'current_stage', 'lower_unit_uom_id')
                ->with('itemUomLowerUnit:id,name')
                ->where('is_promotional', 1)
                ->where('status', 1)
                ->where('current_stage', 'Approved')
                ->get();
        }

        if (in_array('nsm', $listData)) {

            $org_role = OrganisationRole::where('id', $user->role_id)
                ->where('id', '!=', 8)
                ->whereNull('parent_id')
                ->first();

            if (is_object($org_role)) {
                $nsm = User::select('id', 'firstname', 'lastname')
                    ->where('role_id', 6)
                    ->get();
            } else {
                if ($usertype == '1') {
                    $nsm = User::select('id', 'firstname', 'lastname')
                        ->where('role_id', 6)
                        ->get();
                } else {
                    $nsm = User::select('id', 'firstname', 'lastname')
                        ->where('role_id', 6)
                        ->where('parent_id', $login_user_id)
                        ->get();
                }
            }
        }

        if (in_array('asm', $listData)) {
            $user = request()->user();
            if ($usertype == '1') {
                $asm = User::select('id', 'firstname', 'lastname')->where('role_id', '7')->get();
                // $asm = DB::select("Select id,firstname,lastname from users where role_id = 7");
            } else if (($user->role_id) == '8') {
                $asm = DB::select("Select id,firstname,lastname from users where role_id = 7 and parent_id in (select id from users where role_id = 6 and parent_id in (select id from users where role_id=8 and id = $login_user_id))");
            } else if (($user->role_id) == '6') {
                $asm = DB::select("Select id,firstname,lastname from users where role_id = 7 and parent_id in (select id from users where role_id = 6 and id = $login_user_id)");
            } else {
                $asm = User::select('id', 'firstname', 'lastname')->where('role_id', '7')->get();
            }
        }

        $data = array(
            'code' => $code,
            'country' => $country,
            'org_country' => $org_country,
            'country_master' => $country_master,
            'currency' => $currency,
            'region' => $region,
            'depot' => $depot,
            'area' => $area,
            'area_list' => $area_list,
            'route' => $route,
            'sales_organisation' => $sales_organisation,
            'sales_organisation_list' => $sales_organisation_list,
            'salesman_role' => $salesman_role,
            'salesman_type' => $salesman_type,
            'channel' => $channel,
            'customer_category' => $customer_category,
            'customer_type' => $customer_type,
            'item_major_category' => $item_major_category,
            'item_major_category_list' => $item_major_category_list,
            'item_group' => $item_group,
            'item_uom' => $item_uom,
            'brand' => $brand,
            'brand_list' => $brand_list,
            'vendor' => $vendor,
            'payment_term' => $payment_term,
            'order_type' => $order_type,
            'salesmans' => $salesmans,
            'customers' => $customers,
            'items' => $items,
            'reason' => $reason,
            'reason_list' => $reason_list,
            'merchandiser' => $merchandiser,
            'account' => $account,
            'channel_list' => $channel_list,
            'salesman_supervisor' => $salesman_supervisor,
            'promotional_item' => $promotional_item,
            'role' => $role,
            'nsm' => $nsm,
            'asm' => $asm,
            'all_salesman_supervisor' => $all_salesman_supervisor,
            'order_created_user' => $order_created_user,
            'warehouse' => $warehouse,
            'storage_location' => $storage_location,
        );

        return prepareResult(true, $data, [], "Master listing", $this->success);
    }

    public function combination_key()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $combination_key = CombinationPlanKey::get();

        return prepareResult(true, $combination_key, [], "Combination Key listing", $this->success);
    }

    public function getMenuBySoftware()
    {
        $sub = Subscription::get();
        $sub_domain = config('app.current_domain');

        if ($sub_domain) {
            if ($sub_domain === "devmobiato") {
                $software = Software::where('slug', 'merchandising')->first();
            } else {
                $software = Software::where('slug', $sub_domain)->first();
            }
        } else {
            $software = Software::where('slug', 'merchandising')->first();
        }

        if (request()->user()->role->name == "org-admin") {

            $plan = Plan::select('id')
                ->where('name', 'Enterprise')
                ->where('software_id', $software->id)
                ->get();

            $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                ->whereIn('plan_id', $plan)
                ->where('status', 1)
                ->get();
            //dd($sidebar);

            $setting = SettingMenu::select('id', 'software_id', 'name', 'is_active')
                ->where('is_active', 1)->get();
        } else {

            if (count($sub)) {
                $subArray = $sub->pluck('plan_id')->toArray();
                $plan = Plan::where('software_id', $software->id)
                    ->whereIn('id', $subArray)
                    ->first();

                if (is_object($plan)) {
                    $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                        ->where('status', 1)
                        ->where('plan_id', $plan->id)
                        ->get();
                } else {
                    $plan = Plan::where('software_id', $software->id)->where('name', 'Enterprise')->first();
                    $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                        ->where('status', 1)
                        ->where('plan_id', $plan->id)
                        ->get();
                }
            } else {

                $plan = Plan::where('software_id', $software->id)
                    ->where('name', 'Enterprise')
                    ->first();

                $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                    ->where('plan_id', $plan->id)
                    ->where('status', 1)
                    ->get();
            }
        }

        if (is_object(request()->user()->role)) {
            if (request()->user()->role->name == "org-admin") {
                $this->is_org = true;
            }
        }

        if (request()->user()->role->name != 'org-admin') {
            $setting = $this->settingMenu($software->id);
        }

        $data = array(
            'sidebar' => $sidebar,
            'setting' => $setting
        );

        return prepareResult(true, $data, [], "Plan and feature listing", $this->success);
    }

    public function getSettingMenuBySoftware()
    {
        $sub = Subscription::get();
        $sub_domain = config('app.current_domain');

        if ($sub_domain) {
            if ($sub_domain === "devmobiato") {
                $software = Software::where('slug', 'merchandising')->first();
            } else {
                $software = Software::where('slug', $sub_domain)->first();
            }
        } else {
            $software = Software::where('slug', 'merchandising')->first();
        }

        if (is_object(request()->user()->role)) {
            if (request()->user()->role->name == "org-admin") {
                $this->is_org = true;
            }
        }
        $setting = $this->settingMenu($software->id);

        return prepareResult(true, $setting, [], "Setting Menu listing", $this->success);
    }

    public function generalSetting(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $organisation = Organisation::where('id', $request->user()->organisation_id)->first();

        $sub_domain = config('app.current_domain');

        if ($sub_domain) {
            if ($sub_domain === "devmobiato") {
                $software = Software::where('slug', 'merchandising')->first();
            } else {
                $software = Software::where('slug', $sub_domain)->first();
            }
            //$software = Software::where('slug', $sub_domain)->first();
        } else {
            //pre("else");
            $software = Software::where('slug', 'merchandising')->first();
        }
       
        $all_software = Software::select('id', 'name')->where('status', 1)->get();
        $sub = Subscription::get();
        $currency = Currency::where('default_currency', 1)->first();
        $country = Country::find($organisation->org_country_id);
        if (!is_object($country)) {
            $country = $this->saveCountry($organisation->org_country_id);
        }

        if (count($sub)) {
            $subArray = $sub->pluck('plan_id')->toArray();
            $plan = Plan::where('software_id', $software->id)
                ->whereIn('id', $subArray)
                ->first();

            if (is_object($plan)) {
                $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                    ->where('plan_id', $plan->id)
                    ->where('status', 1)
                    ->get();
            } else {
                $plan = Plan::where('software_id', $software->id)
                    ->where('name', 'Enterprise')
                    ->first();

                $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                    ->where('plan_id', $plan->id)
                    ->where('status', 1)
                    ->get();
            }

            if (is_object(request()->user()->role)) {
                if (request()->user()->role->name == "org-admin") {
                    $this->is_org = true;
                }
            }

            $setting = $this->settingMenu($software->id);
        } else {
        
            $plan = Plan::where('software_id', $software->id)->where('name', 'Enterprise')->first();

            $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
                ->where('plan_id', $plan->id)
                ->where('status', 1)
                ->get();

            if (is_object(request()->user()->role)) {
                if (request()->user()->role->name == "org-admin") {
                    $this->is_org = true;
                }
            }

            $setting = $this->settingMenu($software->id);
        }

        // if (isset($organisation->id) && $organisation->id == 1) {
        //     pre($organisation);
        //     $plan = Plan::where('software_id', $software->id)
        //         ->where('name', 'Enterprise')
        //         ->first();

        //     $sidebar = PlanFeature::select('id', 'feature_name', 'heading')
        //         ->where('status', 1)
        //         ->where('plan_id', $plan->id)
        //         ->groupBy('feature_name')
        //         ->get();

        //     if (is_object(request()->user()->role)) {
        //         if (request()->user()->role->name == "org-admin") {
        //             $this->is_org = true;
        //         }
        //     }

        //     $setting = $this->settingMenu($software->id);
        // }

        $login_track_activity = UserLoginTrack::with(
            'subscription',
            'software:id,name,slug'
        )
            ->get();

        foreach ($login_track_activity as $login_activity) {
            if (is_object($login_activity->subscription)) {
                $login_activity->subscribed = 1;
                $login_activity->is_trial = 0;
            } else {
                if (date('Y-m-d') <= $login_activity->trial_expired_date) {
                    $login_activity->is_trial = 1;
                } else {
                    $login_activity->is_trial = 0;
                }
            }
        }

        foreach ($all_software as $s) {
            if (in_array($s->id, $login_track_activity->pluck('software_id')->toArray())) {
                $s->is_active = 1;
            } else {
                $s->is_active = 0;
            }
        }

        $user_info = $request->user();
        $loggedin_user = $request->user();


        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
        $usersPermissions = DB::table('users as us')
            ->join('organisation_role_has_permissions as orhp', 'us.role_id', '=', 'orhp.organisation_role_id')
            ->join('default_permissions as dp', 'orhp.permission_id', '=', 'dp.id')
            ->join('permission_groups as pg', 'dp.group_id', '=', 'pg.id')
            ->select('dp.id', 'dp.name AS permission', 'pg.name AS permission_group')
            ->where("email", $user_info->email)
            ->groupBy("dp.id")
            ->orderBy("pg.id")
            ->get();

        $permissionsData = array();
        $counter = $loop = 0;
        foreach ($usersPermissions as $permission) {
            if ($permission->permission_group == "salesmans" && config('app.current_domain') == "merchandising") {
                $permissionsData[$counter]['moduleName'] = "merchandising";
                $permissionsData[$counter]['permissions'][]["name"] = $permission->permission;
            } else {
                $permissionsData[$counter]['moduleName'] = $permission->permission_group;
                $permissionsData[$counter]['permissions'][]["name"] = $permission->permission;
            }

            if (isset($usersPermissions[$loop + 1]) and $usersPermissions[$loop + 1]->permission_group != $permission->permission_group)
                $counter++;
            $loop++;
        }

        $theme = OrganisationTheme::select('theme_id')->first();
        if (is_object($theme)) {
            $theme = $theme->theme_id;
        }

        $data = array(
            'theme' => $theme,
            'sidebar' => $sidebar,
            'setting' => $setting,
            'currency' => $currency,
            'country' => $country,
            'login_track_activity' => $login_track_activity,
            'allSoftware' => $all_software,
            'user_info' => $user_info->select('*')->with('organisation_trim:id,org_name,org_logo', 'role:id,name')->first(),
            'loggedin_user' => $loggedin_user,
            'permissions-name' => $permissionsData
        );

        return prepareResult(true, $data, [], "Setting Menu listing", $this->success);
    }

    private function saveCountry($country_id)
    {
        $country_master = CountryMaster::find($country_id);
        $country = new Country;
        $country->name = $country_master->name;
        $country->country_code = $country_master->country_code;
        $country->dial_code = $country_master->dial_code;
        $country->currency = $country_master->currency;
        $country->currency_code = $country_master->currency_code;
        $country->currency_symbol = $country_master->currency_symbol;
        $country->status = 1;
        $country->save();

        return $country;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string $action
     * @param  string $status
     * @param  string $uuid
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        // if (!checkPermission('item-group-bulk-action')) {
        //     return prepareResult(false, [], [], "You do not have the required authorization.", $this->forbidden);
        // }

        $input = $request->json()->all();
        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating item.", $this->unprocessableEntity);
        }

        $action = $request->action;
        $module = $request->module;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            $uuids = $request->ids;
            $module_path = 'App\\Model\\' . $module;
            $module = new $module_path;
            foreach ($uuids as $uuid) {
                if ($request->module == "SalesmanInfo" || $request->module == "CustomerInfo") {
                    $data = $module::where('uuid', $uuid)->first();
                    if (is_object($data)) {
                        User::where('id', $data->user_id)->update([
                            'status' => ($action == 'active') ? 1 : 0
                        ]);
                    }
                }
                $module::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0
                ]);
            }

            return prepareResult(true, "", [], "status updated", $this->success);
        } else if ($action == 'delete') {
            $uuids = $request->ids;
            foreach ($uuids as $uuid) {
                $module::where('uuid', $uuid)->delete();
            }

            // $CustomerInfo = $this->index();
            return prepareResult(true, "", [], "Item deleted success", $this->success);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'module' => 'required',
                'action' => 'required',
                'ids' => 'required'
            ]);
            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function sendMails(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = \Validator::make($request->all(), [
            'type' => 'required',
            'to_email' => 'required',
            'subject' => 'required',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate send mail", $this->unauthorized);
        }

        $tag = "";

        if ($request->type == 'invoice') {
            $tag = "Invoice";
        } else if ($request->type == 'order') {
            $tag = "Order";
        } else if ($request->type == 'delivery') {
            $tag = "Delivery";
        } else if ($request->type == 'collection') {
            $tag = "Collection";
        } else if ($request->type == 'credit_note') {
            $tag = "Credit Note";
        } else if ($request->type == 'debit note') {
            $tag = "Debit Note";
        } else if ($request->type == 'expense') {
            $tag = "Expense";
        } else if ($request->type == 'estimate') {
            $tag = "Estimate";
        } else if ($request->type == 'customer') {
            $tag = "Customer";
        } else if ($request->type == 'salesman') {
            $tag = "Salesman";
        }

        $user = $request->user();
        $organisation = $user->organisation;
        $logo = $organisation->org_logo;

        $subject = $request->subject;
        $to = $request->to_email;
        $from_email = $user->email;
        $from_name = $user->getName();
        $data['content'] = $request->message;


        Mail::send('emails.invoice', [
            'content' => $request->message,
            'logo' => $logo,
            'title' => '',
            'url' => '',
            'tag' => $tag,
            'branch_name' => ''
        ], function ($message) use ($subject, $to, $from_email, $from_name) {
            $message->from($from_email, $from_name);
            $message->to($to);
            $message->subject($subject);
        });

        return prepareResult(true, [], [], "Mail sent successfully", $this->success);
    }

    private function settingMenu($software_id)
    {
        $setting_query = SettingMenu::select('id', 'software_id', 'name', 'is_active')
            ->where('is_active', 1)
            ->where('software_id', $software_id);

        if ($this->is_org == 1) {
            $setting = $setting_query->get();
        } else {
            if (request()->user()->role->name == "IT Admin") {
                $setting = $setting_query->get();
                return $setting;
            }
            $setting = $setting_query->where('name', '!=', "Users & Roles")
                ->get();
        }
        return $setting;
    }

    public function permissionSingle(Request $request)
    {
        $user_info = $request->user();

        // every time check the user login log
        $user_login_log = UserLoginLog::where('user_id', $user_info->id)
            ->whereDate('created_at', date('Y-m-d'))
            ->first();

        if (!is_object($user_login_log)) {
            $user_login_log = new UserLoginLog;
            $user_login_log->user_id            = $user_info->id;
            $user_login_log->ip                 = request()->ip();
            $user_login_log->organisation_id    = $user_info->organisation_id;
            $user_login_log->browser            = $_SERVER['HTTP_USER_AGENT'];
            $user_login_log->save();
        }

        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
        $usersPermissions = DB::table('users as us')
            ->join('organisation_role_has_permissions as orhp', 'us.role_id', '=', 'orhp.organisation_role_id')
            ->join('default_permissions as dp', 'orhp.permission_id', '=', 'dp.id')
            ->join('permission_groups as pg', 'dp.group_id', '=', 'pg.id')
            ->select('dp.id', 'dp.name AS permission', 'pg.name AS permission_group')
            ->where("email", $user_info->email)
            ->groupBy("dp.id")
            ->orderBy("pg.id")
            ->get();

        $permissionsData = array();
        $counter = $loop = 0;

        foreach ($usersPermissions as $permission) {
            if (config('app.current_domain') == "merchandising") {
                $permissionsData[$counter]['moduleName'] = "merchandising";
                $permissionsData[$counter]['permissions'][]["name"] = $permission->permission;
            } else {
                $permissionsData[$counter]['moduleName'] = $permission->permission_group;
                $permissionsData[$counter]['permissions'][]["name"] = $permission->permission;
            }

            if (isset($usersPermissions[$loop + 1]) and $usersPermissions[$loop + 1]->permission_group != $permission->permission_group)
                $counter++;
            $loop++;
        }

        return $permissionsData;
    }

    public function deleteMerchandiserUpdate(Request $request, $merchandiser_id)
    {
        if (!$merchandiser_id) {
            return prepareResult(false, [], [], "Error while validating merchandiser update", $this->unauthorized);
        }

        $merchandiserUpdated = MerchandiserUpdated::where('merchandiser_id', $merchandiser_id)->first();
        if (is_object($merchandiserUpdated)) {
            $merchandiserUpdated->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function objectToArray($data)
    {
        if (is_object($data))
            $d = get_object_vars($data);
    }

    public function test()
    {
        $notification_id = "667992545005";
        $title = "Greeting Notification";
        $message = "Have good day!";
        $id = "123";
        $type = "basic";

        $res = send_notification_FCM(
            $notification_id,
            $title,
            $message,
            $id,
            $type
        );

        if ($res == 1) {
            echo "sueess";
            // success code

        } else {
            echo "failed";

            // fail code
        }
    }

    public function uploadFitle()
    {
        Excel::import(new CustomerMerchandiserImport, request()->file('customer'));
        return "done";
    }


    public function updateAdidImport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        // $validator = \Validator::make($request->all(), [
        //     'addi_update_file' => 'required|mimes:csv,xlsx,xls'
        // ]);

        // if ($validator->fails()) {
        //     $error = $validator->messages()->first();
        //     return prepareResult(false, [], $error, "Failed to validate delivery import", $this->unauthorized);
        // }

        Excel::import(new AdidUpdateImport, request()->file('addi_update_file'));

        return prepareResult(true, [], [], "adid successfully imported", $this->success);
    }
}
