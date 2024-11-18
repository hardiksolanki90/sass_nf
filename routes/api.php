<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('phpinfo', function(){
    return phpinfo();
});
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// set the cusotmer base on jp cusomter
// Route::get('customerMerchandiserSync', 'Api\DefaultController@customerMerchandiserSync');
Route::get('merchandiserUpdateSysnc', 'Api\DefaultController@merchandiserUpdateSysnc');
// Country Route
Route::get('country/all', 'Api\CountryController@allCountry');
// Route::get('check', 'Api\CountryController@checkData');
Route::post('invite-user/password-change', 'Api\InviteUserController@ChangePassword');
Route::get('notification-test', 'Api\GlobalController@test');
Route::post('orderposting/add', 'Api\OrderPostingController@store');
Route::post('orderpostingprd/add', 'Api\OrderPostingPrdController@store');


Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('supervisor-login', 'Api\AuthController@supervisorLogin');
    Route::post('salesman-login', 'Api\AuthController@salesmanLogin');
    Route::post('ad-salesman-login', 'Api\AuthController@adSalesmanLogin');
    Route::post('login', 'Api\AuthController@login');
    Route::post('ad-login', 'Api\AuthController@adLogin');
    Route::post('social-login', 'Api\AuthController@socialMedialogin');
    Route::post('signup', 'Api\AuthController@signup');
    Route::post('user-verification', 'Api\AuthController@userVerification');
    Route::post('forgot-password', 'Api\ForgotPasswordController@sendResetLink');
    Route::post('reset-password', 'Api\ResetPasswordController@resetPassword');
});


Route::group([
    'middleware' => ['auth:api', 'gzip']
], function () {
    Route::get('logout', 'Api\AuthController@logout');
    Route::get('supervisor-logout', 'Api\AuthController@supervisorLogout');
    Route::get('user', 'Api\AuthController@userDetail');

    Route::get('user-login-log\{id}', 'Api\AuthController@userLoginLog');

    // choose-plan
    Route::post('choose/plan/add', 'Api\PlanController@choosePlan');
    Route::get('choose/plan/list', 'Api\PlanController@indexOrgPlan');


    Route::post('code-setting', 'Api\CodeSettingController@store');
    Route::post('get-next-comming-code', 'Api\CodeSettingController@getNextCommingCode');

    // Van Route
    Route::get('van/list', 'Api\VanController@index');
    Route::post('van/list', 'Api\VanController@index');
    Route::post('van/add', 'Api\VanController@store');
    Route::get('van/edit/{uuid}', 'Api\VanController@edit');
    Route::post('van/edit/{uuid}', 'Api\VanController@update');
    Route::any('van/delete/{uuid}', 'Api\VanController@destroy');
    Route::post('van/import', 'Api\VanController@import');

    // Van Category Route
    Route::get('van-category/list', 'Api\VanCategoryController@index');
    Route::post('van-category/add', 'Api\VanCategoryController@store');
    Route::get('van-category/edit/{uuid}', 'Api\VanCategoryController@edit');
    Route::post('van-category/edit/{uuid}', 'Api\VanCategoryController@update');
    Route::any('van-category/delete/{uuid}', 'Api\VanCategoryController@destroy');

    // VehicleUtilisation Route
    Route::post('/load-utilization', 'Api\ReportController@loadUtilization');

    // Van Type Route
    Route::get('van-type/list', 'Api\VanTypeController@index');
    Route::post('van-type/add', 'Api\VanTypeController@store');
    Route::get('van-type/edit/{uuid}', 'Api\VanTypeController@show');
    Route::post('van-type/edit/{uuid}', 'Api\VanTypeController@update');
    Route::any('van-type/delete/{uuid}', 'Api\VanTypeController@destroy');

    // Region Route
    Route::get('region/list', 'Api\RegionController@index');
    Route::post('region/add', 'Api\RegionController@store');
    Route::get('region/edit/{uuid}', 'Api\RegionController@edit');
    Route::post('region/edit/{uuid}', 'Api\RegionController@update');
    Route::any('region/delete/{uuid}', 'Api\RegionController@destroy');
    Route::post('region/bulk-action', 'Api\RegionController@bulkAction');
    Route::post('region/import', 'Api\RegionController@import');

    // Depot Route
    Route::get('depot/list', 'Api\DepotsController@index');
    Route::post('depot/add', 'Api\DepotsController@store');
    Route::get('depot/edit/{uuid}', 'Api\DepotsController@edit');
    Route::post('depot/edit/{uuid}', 'Api\DepotsController@update');
    Route::any('depot/delete/{uuid}', 'Api\DepotsController@destroy');
    Route::post('depot/import', 'Api\DepotsController@import');
    Route::post('depot/bulk-action', 'Api\DepotsController@bulkAction');

    // Area Route
    Route::get('area/list', 'Api\AreaController@index');
    Route::post('area/add', 'Api\AreaController@store');
    Route::get('area/edit/{uuid}', 'Api\AreaController@edit');
    Route::post('area/edit/{uuid}', 'Api\AreaController@update');
    Route::any('area/delete/{uuid}', 'Api\AreaController@destroy');
    Route::post('area/bulk-action', 'Api\AreaController@bulkAction');

    // route
    Route::get('route/list', 'Api\RoutesController@index');
    Route::post('route/add', 'Api\RoutesController@store');
    Route::get('route/edit/{uuid}', 'Api\RoutesController@edit');
    Route::post('route/edit/{uuid}', 'Api\RoutesController@update');
    Route::any('route/delete/{uuid}', 'Api\RoutesController@destroy');
    Route::post('route/customer', 'Api\RoutesController@routeCustomers');
    Route::get('depot-route/{depot_id}', 'Api\RoutesController@depotRoutes');
    Route::get('route-salesman/{route_id}', 'Api\RoutesController@routeSalesman');

    // Organisation
    Route::get('organisation/list', 'Api\OrganisationController@index');
    Route::post('organisation/add', 'Api\OrganisationController@store');
    Route::get('organisation/edit/{uuid}', 'Api\OrganisationController@edit');
    Route::post('organisation/edit/{uuid}', 'Api\OrganisationController@update');
    Route::any('organisation/delete/{uuid}', 'Api\OrganisationController@destroy');
    Route::get('organisation/users-list', 'Api\OrganisationController@usersList');
    Route::get('organisation/show', 'Api\OrganisationController@show');

    // Customer Category
    Route::get('customer-category/list', 'Api\CustomerCategoryController@index');
    Route::post('customer-category/add', 'Api\CustomerCategoryController@store');
    Route::get('customer-category/edit/{uuid}', 'Api\CustomerCategoryController@edit');
    Route::post('customer-category/edit/{uuid}', 'Api\CustomerCategoryController@update');
    Route::any('customer-category/delete/{uuid}', 'Api\CustomerCategoryController@destroy');
    Route::post('customer-category/bulk-action', 'Api\CustomerCategoryController@bulkAction');

    // Customer
    Route::get('customer-type/list', 'Api\CustomerController@customerTypes');
    Route::post('customer/list', 'Api\CustomerController@index');
    Route::post('customer/add', 'Api\CustomerController@store');
    Route::get('customer/edit/{uuid}', 'Api\CustomerController@edit');
    Route::post('customer/edit/{uuid}', 'Api\CustomerController@update');
    Route::any('customer/delete/{uuid}', 'Api\CustomerController@destroy');
    Route::get('customer/customer-details/{customer_id}', 'Api\CustomerController@customerDetails');
    Route::get('customer/customer-balances/{customer_id}', 'Api\CustomerController@customerBalances');
    Route::post('customer/balance-statement', 'Api\CustomerController@customerBalanceStatement');
    Route::post('customer/invoice-chart', 'Api\CustomerController@invoiceChart');
    Route::post('customer/bulk-action', 'Api\CustomerController@bulkAction');

    Route::get('customer-comment/list/{comment_id}', 'Api\CustomerController@listCustomerComments');
    Route::post('customer-comment/add', 'Api\CustomerController@customerComment');
    Route::get('customer-comment/delete/{comment_id}', 'Api\CustomerController@deleteCustomerComment');
    Route::post('getSalesmanDeliveryCustomer', 'Api\CustomerController@getSalesmanDeliveryCustomer');

    Route::post('data-collection', 'Api\GlobalController@masterData');
    Route::post('data-collection-mobile', 'Api\GlobalController@masterDataMobile');

    // Major Category
    Route::get('major-category/list', 'Api\ItemMajorCategoryController@index');
    Route::post('major-category/add', 'Api\ItemMajorCategoryController@store');
    Route::get('major-category/edit/{uuid}', 'Api\ItemMajorCategoryController@edit');
    Route::post('major-category/edit/{uuid}', 'Api\ItemMajorCategoryController@update');
    Route::any('major-category/delete/{uuid}', 'Api\ItemMajorCategoryController@destroy');

    // Item Group
    Route::get('item-group/list', 'Api\ItemGroupController@index');
    Route::post('item-group/add', 'Api\ItemGroupController@store');
    Route::get('item-group/edit/{uuid}', 'Api\ItemGroupController@edit');
    Route::post('item-group/edit/{uuid}', 'Api\ItemGroupController@update');
    Route::any('item-group/delete/{uuid}', 'Api\ItemGroupController@destroy');
    Route::post('item-group/bulk-action', 'Api\ItemGroupController@bulkAction');

    // OutletProduct
    Route::get('outlet-product/list', 'Api\OutletProductController@index');
    Route::post('outlet-product/add', 'Api\OutletProductController@store');
    Route::get('outlet-product/edit/{uuid}', 'Api\OutletProductController@edit');
    Route::post('outlet-product/edit/{uuid}', 'Api\OutletProductController@update');
    Route::any('outlet-product/delete/{uuid}', 'Api\OutletProductController@destroy');

    // Country
    Route::get('country/list', 'Api\CountryController@index');
    Route::post('country/add', 'Api\CountryController@store');
    Route::get('country/edit/{id}', 'Api\CountryController@edit');
    Route::post('country/edit/{id}', 'Api\CountryController@update');
    Route::any('country/delete/{uuid}', 'Api\CountryController@destroy');
    Route::post('country/view', 'Api\CountryController@view');
    Route::post('country/bulk-action', 'Api\CountryController@bulkAction');


    Route::get('default-roles/list', 'Api\DefaultRolePermissionController@index');
    Route::get('default-roles-with-permission/list', 'Api\DefaultRolePermissionController@rolesPermission');
    Route::get('permissions/list', 'Api\DefaultRolePermissionController@permissions');
    Route::get('group-wise-permissions/list', 'Api\DefaultRolePermissionController@groupPermissions');

    Route::get('org-roles/list', 'Api\RoleController@index');
    Route::get('org-roles-with-permission/list', 'Api\RoleController@rolesPermission');
    Route::post('org-roles/customer', 'Api\OrganisationController@orgCustomerList');
    Route::get('org-roles/customer/{parent_id}', 'Api\RoleController@roleCustomer');

    Route::get('user-with-permission/list', 'Api\UserPermissionController@index');
    Route::post('user-assigned-role/{uuid}', 'Api\UserPermissionController@userAssignedRole');
    Route::post('user-assigned-custom-permission/{uuid}', 'Api\UserPermissionController@userAssignedCustomPermission');


    // Channel Route
    Route::get('channel/list', 'Api\ChannelController@index');
    Route::post('channel/add', 'Api\ChannelController@store');
    Route::get('channel/edit/{uuid}', 'Api\ChannelController@edit');
    Route::post('channel/edit/{uuid}', 'Api\ChannelController@update');
    Route::any('channel/delete/{uuid}', 'Api\ChannelController@destroy');
    Route::post('channel/bulk-action', 'Api\ChannelController@bulkAction');

    // Channel Route
    Route::get('sales-organisation/list', 'Api\SalesOrganisationController@index');
    Route::post('sales-organisation/add', 'Api\SalesOrganisationController@store');
    Route::get('sales-organisation/edit/{uuid}', 'Api\SalesOrganisationController@edit');
    Route::post('sales-organisation/edit/{uuid}', 'Api\SalesOrganisationController@update');
    Route::any('sales-organisation/delete/{uuid}', 'Api\SalesOrganisationController@destroy');

    // Item UOMs Route
    Route::get('item-uom/list', 'Api\ItemUomController@index');
    Route::get('item-uom/list/{status}', 'Api\ItemUomController@index');
    Route::post('item-uom/add', 'Api\ItemUomController@store');
    Route::get('item-uom/edit/{uuid}', 'Api\ItemUomController@edit');
    Route::post('item-uom/edit/{uuid}', 'Api\ItemUomController@update');
    Route::any('item-uom/delete/{uuid}', 'Api\ItemUomController@destroy');
    Route::post('item-uom/bulk-action', 'Api\ItemUomController@bulkAction');
    Route::post('item-uom/import', 'Api\ItemUomController@import');

    // Item Route
    Route::post('item/list', 'Api\ItemController@index');
    Route::get('item/list/with-child', 'Api\ItemController@indexMobile');
    Route::post('item/add', 'Api\ItemController@store');
    Route::get('item/edit/{uuid}', 'Api\ItemController@edit');
    Route::post('item/edit/{uuid}', 'Api\ItemController@update');
    Route::any('item/delete/{uuid}', 'Api\ItemController@destroy');
    Route::post('item/bulk-action', 'Api\ItemController@bulkAction');
    Route::get('promotional-items', 'Api\ItemController@promotionalItems');
    Route::get('item/new-lunch', 'Api\ItemController@newLunchIndex');
    Route::post('item/minimum-import', 'Api\ItemController@itemMinimumImport');

    // Customer Group Route
    Route::get('customer-group/list', 'Api\CustomerGroupController@index');
    Route::post('customer-group/add', 'Api\CustomerGroupController@store');
    Route::get('customer-group/edit/{uuid}', 'Api\CustomerGroupController@edit');
    Route::post('customer-group/edit/{uuid}', 'Api\CustomerGroupController@update');
    Route::any('customer-group/delete/{uuid}', 'Api\CustomerGroupController@destroy');
    Route::post('customer-group/bulk-action', 'Api\CustomerGroupController@bulkAction');

    // Role Group Route
    Route::post('org-roles/add', 'Api\RoleController@store');
    Route::get('org-roles/edit/{uuid}', 'Api\RoleController@edit');
    Route::post('org-roles/update/{uuid}', 'Api\RoleController@update');
    Route::get('org-roles/delete/{uuid}', 'Api\RoleController@destroy');

    // Brand Route
    Route::get('brand/list', 'Api\BrandController@index');
    Route::post('brand/add', 'Api\BrandController@store');
    Route::get('brand/edit/{uuid}', 'Api\BrandController@edit');
    Route::post('brand/edit/{uuid}', 'Api\BrandController@update');
    Route::any('brand/delete/{uuid}', 'Api\BrandController@destroy');
    Route::post('brand/bulk-action', 'Api\BrandController@bulkAction');

    // Batch Route
    Route::get('batch/list', 'Api\BatchController@index');
    Route::post('batch/add', 'Api\BatchController@store');
    Route::get('batch/edit/{uuid}', 'Api\BatchController@edit');
    Route::post('batch/edit/{uuid}', 'Api\BatchController@update');
    Route::any('batch/delete/{uuid}', 'Api\BatchController@destroy');
    Route::post('batch/bulk-action', 'Api\BatchController@bulkAction');

    // salesman Route
    Route::get('salesman-role/list', 'Api\SalesmanController@salesmanRoleList');
    Route::get('salesman-type/list', 'Api\SalesmanController@salesmanTypeList');
    Route::post('salesman/list', 'Api\SalesmanController@index');
    Route::post('salesman/add', 'Api\SalesmanController@store');
    Route::get('salesman/edit/{uuid}', 'Api\SalesmanController@edit');
    Route::post('salesman/edit/{uuid}', 'Api\SalesmanController@update');
    Route::any('salesman/delete/{uuid}', 'Api\SalesmanController@destroy');
    Route::post('salesman/bulk-action', 'Api\SalesmanController@bulkAction');
    Route::post('global/bulk-action', 'Api\GlobalController@bulkAction');

    // Payment Terms Route
    Route::get('payment-term/list', 'Api\PaymentTermsController@index');
    Route::post('payment-term/add', 'Api\PaymentTermsController@store');
    Route::get('payment-term/edit/{uuid}', 'Api\PaymentTermsController@edit');
    Route::post('payment-term/edit/{uuid}', 'Api\PaymentTermsController@update');
    Route::any('payment-term/delete/{uuid}', 'Api\PaymentTermsController@destroy');

    // Journey Plan Route
    Route::post('journey-plan/list', 'Api\JourneyPlanController@index');
    Route::post('journey-plan/add', 'Api\JourneyPlanController@store');
    Route::get('journey-plan/edit/{uuid}', 'Api\JourneyPlanController@edit');
    Route::post('journey-plan/edit/{uuid}', 'Api\JourneyPlanController@update');
    Route::any('journey-plan/delete/{uuid}', 'Api\JourneyPlanController@destroy');
    Route::get('journey-plan/show/{uuid}', 'Api\JourneyPlanController@show');
    Route::get('journey-plan/route/{id}', 'Api\JourneyPlanController@showRoute');
    Route::get('journey-plan/merchandiser/{merchandiser_id}', 'Api\JourneyPlanController@journeyPlanByMerchandise');
    Route::get('journey-plan/supervisor/{supervisor_id}', 'Api\JourneyPlanController@journeyPlanBySupervisor');

    // Journey Plan Visit Download
    Route::post('journey-plan/customer-visit', 'Api\JourneyPlanController@downloadCustomerVisit');

    // Route::get('pricing-plan/route', 'Api\PricingPlanController@routeForPricing');
    // This route is use for add six week data same as fith week data
    Route::get('journey-plan/merge', 'Api\JourneyPlanController@mergeWeekData');

    // Bank Information Route
    Route::get('bank-information/list', 'Api\BankInformationController@index');
    Route::post('bank-information/add', 'Api\BankInformationController@store');
    Route::get('bank-information/edit/{uuid}', 'Api\BankInformationController@edit');
    Route::post('bank-information/edit/{uuid}', 'Api\BankInformationController@update');
    Route::any('bank-information/delete/{uuid}', 'Api\BankInformationController@destroy');
    Route::post('bank-information/import', 'Api\BankInformationController@import');

    // Route Item Grouping Route
    Route::post('route-item-grouping/list', 'Api\RouteItemGroupController@index');
    Route::post('route-item-grouping/add', 'Api\RouteItemGroupController@store');
    Route::get('route-item-grouping/edit/{uuid}', 'Api\RouteItemGroupController@edit');
    Route::post('route-item-grouping/edit/{uuid}', 'Api\RouteItemGroupController@update');
    Route::any('route-item-grouping/delete/{uuid}', 'Api\RouteItemGroupController@destroy');
    Route::any('route-item-grouping/delete/{uuid}', 'Api\RouteItemGroupController@destroy');
    Route::post('route-item-grouping-by-merchandiser', 'Api\RouteItemGroupController@routeGroupByMerchandiser');

    // Reason Route
    Route::get('reason-all/list', 'Api\ResonsController@allReason');
    Route::get('reason/list', 'Api\ResonsController@index');
    Route::post('reason/add', 'Api\ResonsController@store');
    Route::get('reason/edit/{uuid}', 'Api\ResonsController@edit');
    Route::post('reason/edit/{uuid}', 'Api\ResonsController@update');
    Route::any('reason/delete/{uuid}', 'Api\ResonsController@destroy');
    Route::any('reason/delete/{uuid}', 'Api\ResonsController@destroy');

    // expense category Route
    Route::get('expense-category/list', 'Api\ExpenseCategoryController@index');
    Route::post('expense-category/add', 'Api\ExpenseCategoryController@store');
    Route::get('expense-category/edit/{uuid}', 'Api\ExpenseCategoryController@edit');
    Route::post('expense-category/edit/{uuid}', 'Api\ExpenseCategoryController@update');
    Route::any('expense-category/delete/{uuid}', 'Api\ExpenseCategoryController@destroy');
    Route::any('expense-category/delete/{uuid}', 'Api\ExpenseCategoryController@destroy');

    // expense Route
    // Route::get('expense/list', 'Api\ExpenseController@index');
    // Route::post('expense/add', 'Api\ExpenseController@store');
    // Route::get('expense/edit/{uuid}', 'Api\ExpenseController@edit');
    // Route::post('expense/edit/{uuid}', 'Api\ExpenseController@update');
    // Route::any('expense/delete/{uuid}', 'Api\ExpenseController@destroy');

    // Route::post('get/combination', 'Api\DataFilterController@ComfinationResult');
    // Key Combination Route
    Route::post('get/combination-country', 'Api\KeyCombinationController@countryList');
    Route::post('get/regions', 'Api\KeyCombinationController@regionsList');
    Route::post('get/depots', 'Api\KeyCombinationController@depotList');
    Route::post('get/areas', 'Api\KeyCombinationController@areaList');
    Route::post('get/routes', 'Api\KeyCombinationController@RouteList');
    Route::post('get/sales-organisations', 'Api\KeyCombinationController@SalesOrganisationList');
    Route::post('get/channels', 'Api\KeyCombinationController@ChannelList');
    Route::post('get/customer-categories', 'Api\KeyCombinationController@CustomerCategoryList');
    Route::post('get/customers', 'Api\KeyCombinationController@CustomerList');
    Route::post('get/major-categories', 'Api\KeyCombinationController@MajorCategoryList');
    Route::post('get/item-groups', 'Api\KeyCombinationController@ItemGroupList');
    Route::post('get/items', 'Api\KeyCombinationController@ItemList');
    Route::post('get/combination-items', 'Api\KeyCombinationController@CombinationItems');

    Route::post('get/combination', 'Api\KeyCombinationController@getListByParam');

    // Invite user Route
    Route::post('invite-user/add', 'Api\InviteUserController@store');
    Route::get('invite-user/edit/{uuid}', 'Api\InviteUserController@edit');
    Route::post('invite-user/edit/{uuid}', 'Api\InviteUserController@update');
    Route::get('invite-user/list', 'Api\InviteUserController@index');
    Route::any('invite-user/delete/{uuid}', 'Api\InviteUserController@destroy');

    //Ashok
    // Order Type Route
    Route::get('order-type/list', 'Api\OrderTypeController@index');
    Route::post('order-type/add', 'Api\OrderTypeController@store');
    Route::get('order-type/edit/{uuid}', 'Api\OrderTypeController@edit');
    Route::post('order-type/edit/{uuid}', 'Api\OrderTypeController@update');
    Route::any('order-type/delete/{uuid}', 'Api\OrderTypeController@destroy');

    // Order Route
    Route::post('order/list', 'Api\OrderController@index');
    Route::post('orderList', 'Api\OrderController@orderList');
    Route::post('delivery_report', 'Api\OrderController@delivery_report');
    // Route::post('live_tracking', 'Api\OrderController@live_tracking');
    Route::post('order_for_report', 'Api\OrderController@order_for_report');

    Route::post('delivery/report', 'Api\OrderController@deliveryReport');

    Route::post('geo-approval/listing', 'Api\ReportController@geoApprovalListing');

    
    Route::post('order/add', 'Api\OrderController@store');
    Route::post('order/show', 'Api\OrderController@show');
    Route::get('order/edit/{uuid}', 'Api\OrderController@edit');
    Route::post('order/edit/{uuid}', 'Api\OrderController@update');
    Route::any('order/delete/{uuid}', 'Api\OrderController@destroy');
    Route::post('order/bulk-action', 'Api\OrderController@bulkAction');
    Route::post('order/small-item-apply-price', 'Api\OrderController@smallItemApplyPrice');
    Route::post('order/item-apply-price', 'Api\OrderController@itemApplyPrice');
    Route::post('order/item-apply-price/multiple', 'Api\OrderController@itemApplyPriceMultiple');
    Route::post('order/normal-item-apply-price', 'Api\OrderController@normalItemApplyPrice');
    Route::post('order/item-apply-promotion', 'Api\OrderController@itemApplyPromotion');
    Route::post('route/promotion', 'Api\PriceDiscoPromoController@routeApplyPriceDiscPromotion');
    Route::post('order/import', 'Api\OrderController@import');
    Route::get('order/test-routific', 'Api\OrderController@testRoutific');
    // order cancelled
    Route::post('order/cancel', 'Api\OrderController@cancel');
    // order status change as picking confirm
    Route::post('order-to-picking', 'Api\OrderController@orderToPicking');
    // OCR Order
    Route::post('ocr-order/add', 'Api\OrderController@storeOcrOrder');
    // infinite

    // order import directly 
    Route::post('order/import-order', 'Api\OrderController@OrderTemplateImport');
    // order LPO check 
    Route::post('order/lpo', 'Api\OrderController@orderLPOCheck');


    // Pricing Paln Route
    Route::post('pricing-paln/add', 'Api\PriceDiscoPromoController@store');
    Route::get('pricing-paln/edit/{uuid}', 'Api\PriceDiscoPromoController@edit');
    Route::post('pricing-paln/edit/{uuid}', 'Api\PriceDiscoPromoController@update');
    Route::post('pricing-paln/list', 'Api\PriceDiscoPromoController@index');
    Route::any('pricing-paln/delete/{uuid}', 'Api\PriceDiscoPromoController@destroy');

    Route::get('pdp/mobile/{type}', 'Api\PriceDiscoPromoController@pdpMobile');
    Route::post('pdp/mobile-by-route', 'Api\PriceDiscoPromoController@pdpMobileByRoute');

    // Pricing Import
    Route::get('pricing/getmappingfield', 'Api\PriceDiscoPromoController@getmappingfield');
    Route::post('pricing/import', 'Api\PriceDiscoPromoController@import');
    Route::post('pricing/finalimport', 'Api\PriceDiscoPromoController@finalimport');

    // Debit Notes Route
    Route::post('debit-notes/list', 'Api\DebitNoteController@index');
    Route::post('debit-notes/add', 'Api\DebitNoteController@store');
    Route::get('debit-notes/edit/{uuid}', 'Api\DebitNoteController@edit');
    Route::post('debit-notes/edit/{uuid}', 'Api\DebitNoteController@update');
    Route::any('debit-notes/delete/{uuid}', 'Api\DebitNoteController@destroy');
    Route::post('debit-notes/bulk-action', 'Api\DebitNoteController@bulkAction');
    Route::post('debitnote/import', 'Api\DebitNoteController@import');

    // Bundle Promotion Route
    Route::post('bundle-promotion/add', 'Api\PriceDiscoPromoController@store');
    Route::get('bundle-promotion/edit/{uuid}', 'Api\PriceDiscoPromoController@edit');
    Route::post('bundle-promotion/edit/{uuid}', 'Api\PriceDiscoPromoController@update');
    Route::post('bundle-promotion/list', 'Api\PriceDiscoPromoController@index');
    Route::any('bundle-promotion/delete/{uuid}', 'Api\PriceDiscoPromoController@destroy');

    // Discount Route
    Route::post('discount/add', 'Api\PriceDiscoPromoController@store');
    Route::get('discount/edit/{uuid}', 'Api\PriceDiscoPromoController@edit');
    Route::post('discount/edit/{uuid}', 'Api\PriceDiscoPromoController@update');
    Route::post('discount/list', 'Api\PriceDiscoPromoController@index');
    Route::any('discount/delete/{uuid}', 'Api\PriceDiscoPromoController@destroy');

    //Work Flow Rule
    Route::get('work-flow-module/list', 'Api\WorkFlowRuleController@moduleList');
    Route::get('work-flow/list', 'Api\WorkFlowRuleController@index');
    Route::post('work-flow/add', 'Api\WorkFlowRuleController@store');
    Route::get('work-flow/edit/{uuid}', 'Api\WorkFlowRuleController@edit');
    Route::post('work-flow/edit/{uuid}', 'Api\WorkFlowRuleController@update');
    Route::any('work-flow/delete/{uuid}', 'Api\WorkFlowRuleController@destroy');
    //Work flow approval request
    Route::get('request-for-approval/list', 'Api\WFMApprovalRequestController@index');
    Route::post('request-for-approval/action/{uuid}', 'Api\WFMApprovalRequestController@action');
    Route::post('bulk-request-for-approval/action', 'Api\WFMApprovalRequestController@bulkAction');
    // Route::get('generateDebitNote/{grn_id}', 'Api\WFMApprovalRequestController@generateGRNDebitNote');

    // Delivery Route
    Route::post('delivery/list', 'Api\DeliveryController@index');
    Route::post('delivery/add', 'Api\DeliveryController@store');
    Route::get('delivery/edit/{uuid}', 'Api\DeliveryController@edit');
    Route::post('delivery/edit/{uuid}', 'Api\DeliveryController@update');
    Route::any('delivery/delete/{uuid}', 'Api\DeliveryController@destroy');
    Route::post('delivery/import', 'Api\DeliveryController@import');
    Route::post('delivery/cancel', 'Api\DeliveryController@cancel');
    Route::post('delivery/get-merchandiser', 'Api\DeliveryController@getMerchandiserDate');
    Route::post('delivery/delivery_trip_change', 'Api\DeliveryController@deliveryTripChange');
    Route::post('delivery/delivery_bulk_trip_change', 'Api\DeliveryController@deliveryBulkTripChange');
    Route::post('total_delivery_report', 'Api\DeliveryController@total_delivery_report');
Route::post('delivery/delivery_code_change', 'Api\DeliveryController@deliveryCodeChange');


    // Delivery Update

    /* update-import-new */
    Route::post('delivery/update-import', 'Api\DeliveryController@deliveryTemplateImport');
    Route::post('delivery/update-import-new', 'Api\DeliveryController@deliveryTemplateImportNew');
    Route::post('delivery/template-update', 'Api\DeliveryController@deliveryTemplateUpdate');

    // only for nfpc Delivery convert to salesmanload
    Route::post('deliveryConvertToLoad', 'Api\DeliveryController@deliveryConvertToLoad');
    // delivery Nots
    Route::post('deliveryNots/add', 'Api\DeliveryController@deliveryNots');
    // delivery adding invoice number
    Route::post('delivery/invoiceNumber', 'Api\DeliveryController@invoiceNumber');
    Route::get('delivery/invoiceNumber/{delivery_id}', 'Api\DeliveryController@getinvoiceNumber');
    Route::get('delivery/details/{delivery_id}', 'Api\DeliveryController@getDeliveryDetails');

    // Delivery Template by delivery id
    Route::get('delivery-template-assign-details/{delivery_id}', 'Api\DeliveryController@templateAssingDetails');

    // Delivery Note base on delivery id
    Route::get('get/delivery/note/{delivery_id}', 'Api\DeliveryController@getDeliveryNoteById');
    // Delivery Note base on delivery id
    Route::post('delivery/note/update', 'Api\DeliveryController@deliveryNoteReasonUpdate');


    // Invoice Route
    Route::post('invoice/list', 'Api\InvoiceController@index');
    Route::post('invoice/add', 'Api\InvoiceController@store');
    Route::get('invoice/edit/{uuid}', 'Api\InvoiceController@edit');
    Route::post('invoice/edit/{uuid}', 'Api\InvoiceController@update');
    Route::any('invoice/delete/{uuid}', 'Api\InvoiceController@destroy');
    Route::post('invoice/bulk-action', 'Api\InvoiceController@bulkAction');
    Route::get('pending-invoice/list/{route_id}', 'Api\InvoiceController@pendingInvoice');
    Route::post('invoice/import', 'Api\InvoiceController@import');
    Route::post('invoice-details/import', 'Api\InvoiceController@invoiceDetailsImport');
    Route::post('invoice-details/import3', 'Api\InvoiceController@import3');
    Route::post('invoice-haris-customer/import-haris-customer', 'Api\InvoiceController@importHarisCustomer');

    // Invoice Canclel
    Route::post('invoice/{invoice}/reason', 'Api\InvoiceController@invoiceReason');
    Route::post('invoice/{invoice}/cancel', 'Api\InvoiceController@invoiceCancel');

    // Invoice ERP Post
    Route::post('invoice/erp-post/{id}', 'Api\InvoiceController@postInvoiceInJDE');

    // OdoMeter Add
    Route::get('odo-meter-by-van', 'Api\OdoMeterController@index');
    Route::post('odometer/day-start', 'Api\OdoMeterController@store');
    Route::post('odometer/day-end', 'Api\OdoMeterController@update');
    Route::get('odometer/details/{van_id}', 'Api\OdoMeterController@getDetails');
    Route::post('odometer/update/{id}', 'Api\OdoMeterController@odometerChange');

    // Warehouse Route
    Route::get('warehouse/list', 'Api\WarehouseController@index');
    Route::post('warehouse/add', 'Api\WarehouseController@store');
    Route::get('warehouse/edit/{uuid}', 'Api\WarehouseController@edit');
    Route::post('warehouse/edit/{uuid}', 'Api\WarehouseController@update');
    Route::any('warehouse/delete/{uuid}', 'Api\WarehouseController@destroy');
    Route::post('warehouse/bulk-action', 'Api\WarehouseController@bulkAction');

    // Warehouse detail Route
    Route::get('warehousedetail/list/{id}', 'Api\WarehousedetailController@index');
    Route::post('warehousedetail/add', 'Api\WarehousedetailController@store');
    Route::get('warehousedetail/edit/{uuid}', 'Api\WarehousedetailController@edit');
    Route::post('warehousedetail/edit/{uuid}', 'Api\WarehousedetailController@update');
    Route::any('warehousedetail/delete/{uuid}', 'Api\WarehousedetailController@destroy');
    Route::post('warehousedetail/bulk-action', 'Api\WarehousedetailController@bulkAction');

    //Collection Route
    Route::post('collection/list', 'Api\CollectionsController@index');
    Route::get('collection/pendinginvoice/{id}', 'Api\CollectionsController@pendinginvoice');
    Route::post('collection/add', 'Api\CollectionsController@store');
    Route::get('collection/edit/{uuid}', 'Api\CollectionsController@edit');
    Route::post('collection/edit/{uuid}', 'Api\CollectionsController@update');
    Route::get('collection/customer-payment/{customer_id}', 'Api\CollectionsController@customerPayment');
    Route::post('collection/bulk-action', 'Api\CollectionsController@bulkAction');
    Route::post('collection/import', 'Api\CollectionsController@import');

    // Vendor Route
    Route::get('vendor/list', 'Api\VendorController@index');
    Route::post('vendor/add', 'Api\VendorController@store');
    Route::get('vendor/edit/{uuid}', 'Api\VendorController@edit');
    Route::post('vendor/edit/{uuid}', 'Api\VendorController@update');
    Route::any('vendor/delete/{uuid}', 'Api\VendorController@destroy');
    Route::post('vendor/bulk-action', 'Api\VendorController@bulkAction');
    Route::post('vendor/import', 'Api\VendorController@import');

    Route::post('goodreceiptnote/list', 'Api\GoodreceiptnoteController@index');
    Route::post('goodreceiptnote/add', 'Api\GoodreceiptnoteController@store');
    Route::get('goodreceiptnote/edit/{uuid}', 'Api\GoodreceiptnoteController@edit');
    Route::post('goodreceiptnote/edit/{uuid}', 'Api\GoodreceiptnoteController@update');
    Route::any('goodreceiptnote/delete/{uuid}', 'Api\GoodreceiptnoteController@destroy');
    Route::post('goodreceiptnote/bulk-action', 'Api\GoodreceiptnoteController@bulkAction');
    Route::get('goodreceiptnote/approve/{uuid}', 'Api\GoodreceiptnoteController@approve');
    Route::post('goodreceiptnote/update-truck', 'Api\GoodreceiptnoteController@updateTruck');

    // Purchase order
    Route::get('purchaseorder/list', 'Api\PurchaseOrderController@index');
    Route::post('purchaseorder/add', 'Api\PurchaseOrderController@store');
    Route::get('purchaseorder/edit/{uuid}', 'Api\PurchaseOrderController@edit');
    Route::post('purchaseorder/edit/{uuid}', 'Api\PurchaseOrderController@update');
    Route::any('purchaseorder/delete/{uuid}', 'Api\PurchaseOrderController@destroy');
    Route::post('purchaseorder/bulk-action', 'Api\PurchaseOrderController@bulkAction');
    Route::get('purchaseorder/approve/{uuid}', 'Api\PurchaseOrderController@approve');
    Route::post('purchaseorder/import', 'Api\PurchaseorderController@import');

    // Latitude and Longitude update in CustomerInfo Table Import
    Route::post('import/customers/lat-long', 'Api\CustomerController@importCustomerLatLong');
    

    // Stockadjustment order
    Route::get('stock-adjustment/list', 'Api\StockAdjustmentController@index');
    Route::post('stock-adjustment/getquantity', 'Api\StockAdjustmentController@getquantity');
    Route::post('stock-adjustment/add', 'Api\StockAdjustmentController@store');
    Route::get('stock-adjustment/edit/{uuid}', 'Api\StockAdjustmentController@edit');
    Route::post('stock-adjustment/edit/{uuid}', 'Api\StockAdjustmentController@update');
    Route::any('stock-adjustment/delete/{uuid}', 'Api\StockAdjustmentController@destroy');
    Route::post('stock-adjustment/bulk-action', 'Api\StockAdjustmentController@bulkAction');
    Route::any('stock-adjustment/convertoadjustment/{uuid}', 'Api\StockAdjustmentController@convertoadjustment');

    // Salesman load Route
    Route::post('salesman-load/list', 'Api\SalesmanLoadController@index');
    Route::post('salesman-load/add', 'Api\SalesmanLoadController@store');
    Route::get('salesman-load/edit/{uuid}', 'Api\SalesmanLoadController@edit');
    Route::post('salesman-load/edit/{uuid}', 'Api\SalesmanLoadController@update');
    Route::any('salesman-load/delete/{uuid}', 'Api\SalesmanLoadController@destroy');
    Route::post('salesman-load/bulk-action', 'Api\SalesmanLoadController@bulkAction');
    // Salesman Load For Mobile
    Route::post('salesman-load/loadlist', 'Api\SalesmanLoadController@loadlist');
    Route::post('salesman-load/confirm', 'Api\SalesmanLoadController@loadConfirm');

    //Salesman Unload
    Route::post('salesman-unload/list', 'Api\SalesmanUnloadController@index');
    Route::post('salesman-unload/add', 'Api\SalesmanUnloadController@store');
    Route::post('salesman-unload/unloadlist', 'Api\SalesmanUnloadController@unloadlist');
    Route::get('salesman-unload/edit/{uuid}', 'Api\SalesmanUnloadController@edit');
    Route::post('salesman-unload/edit/{uuid}', 'Api\SalesmanUnloadController@update');
    Route::any('salesman-unload/delete/{uuid}', 'Api\SalesmanUnloadController@destroy');

    //Portfolio Management
    Route::post('portfolio-management/list', 'Api\PortfolioManagementController@index');
    Route::post('portfolio-management/add', 'Api\PortfolioManagementController@store');
    Route::get('portfolio-management/edit/{uuid}', 'Api\PortfolioManagementController@edit');
    Route::post('portfolio-management/edit/{uuid}', 'Api\PortfolioManagementController@update');
    Route::any('portfolio-management/delete/{uuid}', 'Api\PortfolioManagementController@destroy');
    Route::post('import', 'Api\PortfolioManagementController@import');
    Route::post('add-merchandiser-msl-cron', 'Api\PortfolioManagementController@dateWiseAddMerchandiserMSL');
    Route::post('add-merchandiser_msl_compliances-msl-cron', 'Api\PortfolioManagementController@addMerchandiserMslCompliance');
    //Route::post('date-wise-add-merchandiser-msl-cron', 'Api\PortfolioManagementController@dateWiseAddMerchandiserMSL');


    // Depot Damage Expiry
    Route::get('depot-damage-expiry/list', 'Api\DepotDamageExpiryController@index');
    Route::post('depot-damage-expiry/add', 'Api\DepotDamageExpiryController@store');
    Route::get('depot-damage-expiry/edit/{uuid}', 'Api\DepotDamageExpiryController@edit');
    Route::post('depot-damage-expiry/edit/{uuid}', 'Api\DepotDamageExpiryController@update');
    Route::any('depot-damage-expiry/delete/{uuid}', 'Api\DepotDamageExpiryController@destroy');
    Route::post('depot-damage-expiry/bulk-action', 'Api\DepotDamageExpiryController@bulkAction');

    // Trip Controller
    Route::post('beginday/add', 'Api\TripController@beginday');
    Route::post('endday/add', 'Api\TripController@endday');

    // Expense
    Route::get('expenses/list', 'Api\ExpenseController@index');
    Route::post('expenses/add', 'Api\ExpenseController@store');
    Route::get('expenses/edit/{uuid}', 'Api\ExpenseController@edit');
    Route::post('expenses/edit/{uuid}', 'Api\ExpenseController@update');
    Route::any('expenses/delete/{uuid}', 'Api\ExpenseController@destroy');
    Route::post('expenses/bulk-action', 'Api\ExpenseController@bulkAction');

    // Credit Notes Route
    Route::post('creditnotes/list', 'Api\CreditNoteController@index');
    Route::post('creditnotes/add', 'Api\CreditNoteController@store');
    Route::get('creditnotes/edit/{uuid}', 'Api\CreditNoteController@edit');
    Route::post('creditnotes/edit/{uuid}', 'Api\CreditNoteController@update');
    Route::any('creditnotes/delete/{uuid}', 'Api\CreditNoteController@destroy');
    Route::post('creditnotes/bulk-action', 'Api\CreditNoteController@bulkAction');
    Route::get('getcustomerinvoice/{user_id}', 'Api\CreditNoteController@getcustomerinvoice');
    Route::get('getinvoiceitem/{invoice_id}', 'Api\CreditNoteController@getinvoiceitem');
    Route::post('creditnotes/post-with-sap', 'Api\CreditNoteController@postWithSap');
    Route::post('creditnotes/test-guzzle-post-with-sap', 'Api\CreditNoteController@testGuzzlePostWithSap');
    Route::post('get-items', 'Api\ALBItemController@index');
    Route::post('store-item', 'Api\ALBItemController@item_Sap');
    Route::post('store-customer-sap', 'Api\ALBItemController@storeCustomeSap');

    // Credit Note Import
    Route::get('creditnotes/getmappingfield', 'Api\CreditNoteController@getmappingfield');
    Route::post('creditnotes/import', 'Api\CreditNoteController@import');
    Route::post('creditnotes/finalimport', 'Api\CreditNoteController@finalimport');

    Route::post('return/reverse', 'Api\CreditNoteController@return_reverse');

    // User Credit limit Route
    Route::get('credit-limit-option/list', 'Api\UserCreditLimitController@index');
    Route::post('credit-limit-option/add', 'Api\UserCreditLimitController@store');

    // CreditNote add salesman and date
    Route::post('creditnotes/update-import', 'Api\CreditNoteController@updateImport');
    // for indivisual
    Route::post('creditnotes/update-truck', 'Api\CreditNoteController@updateTruck');
    //
    Route::get('creditnotes/{credit_note_id}', 'Api\CreditNoteController@getCreditNoteByID');

    // Credit Note ERP POST
    Route::post('creditnotes/erp-post/{id}', 'Api\CreditNoteController@postReturnInJDE');

    // CreditNote Supervisor request approved
    Route::post('creditnotes/supervisorApprovalNotification', 'Api\CreditNoteController@supervisorApprovalNotification');

    // CreditNote add salesman and date
    Route::post('creditnotes/update-import', 'Api\CreditNoteController@updateImport');

    Route::get('creditnotes/requested/{salesman_id}', 'Api\CreditNoteController@creditNoteRequeustedList');
    Route::get('creditnotes/requested-accepted/{uuid}', 'Api\CreditNoteController@creditNoteRequeustAccepted');

    // Credit Note Notes
    Route::post('creditnote/notes/add', 'Api\CreditNoteController@creditNoteNotes');

    // Customer visit Route
    Route::post('customer-visit/list', 'Api\CustomerVisitController@index');
    Route::post('customer-visit/add', 'Api\CustomerVisitController@store');
    Route::get('customer-visit/edit/{uuid}', 'Api\CustomerVisitController@edit');
    Route::post('customer-visit/edit/{uuid}', 'Api\CustomerVisitController@update');
    Route::any('customer-visit/delete/{uuid}', 'Api\CustomerVisitController@destroy');
    Route::post('customer-visit/bulk-action', 'Api\CustomerVisitController@bulkAction');
    Route::post('customer-visit/salesman/list', 'Api\CustomerVisitController@activityBySalesman');

    //Deliveries
    Route::post('getdeliveries', 'Api\DeliveryController@getdeliveries');
    Route::post('getDeliveriesDetails', 'Api\DeliveryController@getDeliveriesDetails');
    //Warehouse detail
    Route::post('getwarehousedetail', 'Api\WarehouseController@getwarehousedetail');
    //Route item grouping
    Route::get('routeitemgrouping/{route_id}', 'Api\RouteItemGroupingController@getitems');
    Route::get('getitemstock/{route_id}/{item_id}', 'Api\WarehouseController@getitemstock');
    // Route Combination Key
    Route::get('combination-key', 'Api\GlobalController@combination_key');

    // Van to van transfer Route
    Route::get('van-to-van-transfer/list', 'Api\VantovanTransferController@index');
    Route::post('van-to-van-transfer/add', 'Api\VantovanTransferController@store');
    Route::get('van-to-van-transfer/edit/{uuid}', 'Api\VantovanTransferController@edit');
    Route::post('van-to-van-transfer/edit/{uuid}', 'Api\VantovanTransferController@update');
    Route::any('van-to-van-transfer/delete/{uuid}', 'Api\VantovanTransferController@destroy');
    Route::post('van-to-van-transfer/bulk-action', 'Api\VantovanTransferController@bulkAction');
    Route::get('van-to-van-transfer/itemlist/{route_id}', 'Api\VantovanTransferController@itemlist');
    Route::get('van-to-van-transfer/accept/{id}', 'Api\VantovanTransferController@accept');
    Route::get('van-to-van-transfer/liststock', 'Api\VantovanTransferController@liststock');

    // Sales Target Route
    Route::get('salestarget/list', 'Api\SalesTargetController@index');
    Route::post('salestarget/add', 'Api\SalesTargetController@store');
    Route::get('salestarget/edit/{uuid}', 'Api\SalesTargetController@edit');
    Route::post('salestarget/edit/{uuid}', 'Api\SalesTargetController@update');
    Route::any('salestarget/delete/{uuid}', 'Api\SalesTargetController@destroy');
    Route::post('salestarget/bulk-action', 'Api\SalesTargetController@bulkAction');
    Route::get('salestarget/salesachived/{uuid}', 'Api\SalesTargetController@salesachived');

    // cashier reciept Route
    Route::get('cashierreciept/getcollection/{salesman_id}', 'Api\CashierRecieptController@getcollection');
    Route::get('cashierreciept/list', 'Api\CashierRecieptController@index');
    Route::post('cashierreciept/add', 'Api\CashierRecieptController@store');
    Route::any('cashierreciept/delete/{uuid}', 'Api\CashierRecieptController@destroy');
    Route::post('cashierreciept/bulk-action', 'Api\CashierRecieptController@bulkAction');

    // estimation
    Route::get('estimation/list', 'Api\EstimationController@index');
    Route::post('estimation/add', 'Api\EstimationController@store');
    Route::get('estimation/edit/{uuid}', 'Api\EstimationController@edit');
    Route::post('estimation/edit/{uuid}', 'Api\EstimationController@update');
    Route::any('estimation/delete/{uuid}', 'Api\EstimationController@destroy');
    Route::post('estimation/bulk-action', 'Api\EstimationController@bulkAction');

    //Sales person Route
    Route::get('salesperson/list', 'Api\SalespersonController@index');
    Route::post('salesperson/add', 'Api\SalespersonController@store');

    Route::get('account/list', 'Api\AccountController@index');

    //Apply credit
    Route::get('invoice-by-credit-note-number/{creditnote_number}', 'Api\ApplyCreditController@index');
    Route::post('apply-credit-save', 'Api\ApplyCreditController@store');

    // Custom Field Route
    Route::get('customfield/list', 'Api\CustomFieldController@index');
    Route::post('customfield/add', 'Api\CustomFieldController@store');
    Route::get('customfield/edit/{uuid}', 'Api\CustomFieldController@edit');
    Route::post('customfield/edit/{uuid}', 'Api\CustomFieldController@update');
    Route::any('customfield/delete/{uuid}', 'Api\CustomFieldController@destroy');
    Route::post('customfield/bulk-action', 'Api\CustomFieldController@bulkAction');
    Route::get('module/modulewisefield/{id}', 'Api\CustomFieldController@getmoulewisecustomfield');
    Route::get('module/getallmodules', 'Api\CustomFieldController@getmodule');

    // Custom Field Value Route
    Route::get('customfieldvalue/list', 'Api\CustomFieldValueController@index');
    Route::post('customfieldvalue/add', 'Api\CustomFieldValueController@store');
    Route::get('customfieldvalue/edit/{uuid}', 'Api\CustomFieldValueController@edit');
    Route::post('customfieldvalue/edit/{uuid}', 'Api\CustomFieldValueController@update');
    Route::any('customfieldvalue/delete/{uuid}', 'Api\CustomFieldValueController@destroy');
    Route::post('customfieldvalue/bulk-action', 'Api\CustomFieldValueController@bulkAction');
    Route::post('customfieldvalue/getmoduledetails', 'Api\CustomFieldValueController@getmoduledetails');

    // Module Route
    Route::get('module/list', 'Api\ModuleController@index');
    Route::post('module/add', 'Api\ModuleController@store');
    Route::get('module/edit/{uuid}', 'Api\ModuleController@edit');
    Route::post('module/edit/{uuid}', 'Api\ModuleController@update');
    Route::any('module/delete/{uuid}', 'Api\ModuleController@destroy');
    Route::post('module/bulk-action', 'Api\ModuleController@bulkAction');
    Route::post('module/checkstatus', 'Api\ModuleController@checkstatus');
    //currency master
    Route::get('all-currency', 'Api\CurrencyController@allCurrency');
    Route::get('currency/list', 'Api\CurrencyController@index');
    Route::post('currency/add', 'Api\CurrencyController@store');
    Route::get('currency/edit/{uuid}', 'Api\CurrencyController@edit');
    Route::post('currency/edit/{uuid}', 'Api\CurrencyController@update');
    Route::any('currency/delete/{uuid}', 'Api\CurrencyController@destroy');

    // Action History
    Route::get('action-history/list', 'Api\ActionhistoryController@index');
    Route::post('action-history/add', 'Api\ActionhistoryController@store');
    Route::any('action-history/delete/{uuid}', 'Api\ActionhistoryController@destroy');
    Route::post('action-history/list-by-module', 'Api\ActionhistoryController@listbymodule');

    //Export
    Route::post('Export/module', 'Api\ExportController@export');

    // Invoice Reminders
    Route::get('invoice-reminder/list', 'Api\InvoiceReminderController@index');
    Route::post('invoice-reminder/add', 'Api\InvoiceReminderController@store');
    Route::get('invoice-reminder/edit/{invoice_id}', 'Api\InvoiceReminderController@edit');
    Route::post('invoice-reminder/edit/{invoice_id}', 'Api\InvoiceReminderController@update');
    Route::any('invoice-reminder/delete/{uuid}', 'Api\InvoiceReminderController@destroy');

    //Advanced Search
    Route::post('advanced-search', 'Api\CommonController@advancedSearch');

    // Assign Inventory
    Route::post('assign-inventory/list', 'Api\AssignInventoryController@index');
    Route::post('assign-inventory/add', 'Api\AssignInventoryController@store');
    Route::get('assign-inventory/edit/{uuid}', 'Api\AssignInventoryController@edit');
    Route::post('assign-inventory/edit/{uuid}', 'Api\AssignInventoryController@update');
    Route::any('assign-inventory/delete/{uuid}', 'Api\AssignInventoryController@destroy');
    Route::post('assign-inventory/show-post', 'Api\AssignInventoryController@showInverntoryPost');
    Route::post('assign-inventory-post/add', 'Api\AssignInventoryController@storeInverntoryPost');
    Route::get('assign-inventory-by-customer/{merchandiser_id}', 'Api\AssignInventoryController@showCustomerInventory');
    Route::post('assign-inventory-damage/list', 'Api\AssignInventoryController@assignDamageList');

    // Complaint Feedback
    Route::post('complaint-feedback/list', 'Api\ComplaintFeedbackController@index');
    Route::post('complaint-feedback/add', 'Api\ComplaintFeedbackController@store');
    Route::any('complaint-feedback/delete/{uuid}', 'Api\ComplaintFeedbackController@destroy');

    // Campaign Picture
    Route::post('campaign-picture/list', 'Api\CampaignPictureController@index');
    Route::post('campaign-picture/add', 'Api\CampaignPictureController@store');
    Route::get('campaign-picture/show/{uuid}', 'Api\CampaignPictureController@show');

    // Report module
    Route::get('report-module/list', 'Api\ReportmoduleController@index');
    Route::post('report-module/add', 'Api\ReportmoduleController@store');
    Route::any('report-module/delete/{uuid}', 'Api\ReportmoduleController@destroy');

    // Report
    Route::post('report/sales_by_customer', 'Api\ReportController@sales_by_customer');
    Route::post('report/sales_by_item', 'Api\ReportController@sales_by_item');
    Route::post('report/sales_by_salesman', 'Api\ReportController@sales_by_salesman');
    Route::post('report/invoice_details', 'Api\ReportController@invoice_details');
    Route::post('report/payment_received', 'Api\ReportController@payment_received');
    Route::post('report/creditnote_detail', 'Api\ReportController@creditnote_detail');
    Route::post('report/debitnote_detail', 'Api\ReportController@debitnote_detail');
    Route::post('report/estimate_detail', 'Api\ReportController@estimate_detail');
    Route::post('report/aging_summary', 'Api\ReportController@aging_summary');

    // Presale reports
    Route::post('report/consolidatedLoadReport', 'Api\ReportController@consolidatedLoadReport');
    Route::post('report/loadingChartByWarehouse', 'Api\ReportController@loadingChartByWarehouse');
    Route::post('report/consolidate-load-return', 'Api\ReportController@consolidateLoadReturn');
    Route::post('report/orderDetailsReport', 'Api\ReportController@orderDetailsReport');
    Route::post('report/truck-utilisation', 'Api\ReportController@truckUtilisation');
    Route::post('report/driver_utilisation', 'Api\ReportController@driverUtilisation');
    Route::post('report/csrf', 'Api\ReportController@csrfReport');
    Route::post('report/sales_quantity', 'Api\ReportController@salesQuantity');
    Route::post('report/sales_grv_report', 'Api\ReportController@salesGrvReport');
    Route::post('report/difot', 'Api\ReportController@difot');
    Route::post('report/delivery_driver_journey_plan', 'Api\ReportController@reportPlanVisit');
    Route::post('report/return_grv_Report', 'Api\ReportController@returnGrvReport');
    Route::post('report/itemreport', 'Api\ReportItemController@item_report');
    Route::post('report/grvreport', 'Api\ReportItemController@grv_report');
    Route::post('report/CfrRegionReport', 'Api\ReportItemController@cfrRegionReport');
    Route::post('report/spot_report', 'Api\ReportItemController@spot_return');
    Route::post('report/vehicle-utilisation', 'Api\ReportController@vehical_Utilisation');
    Route::post('report/cancel_return', 'Api\ReportItemController@cancel_return');
    Route::post('report/sales_vs_grv_report', 'Api\ReportController@salesVSGrvReport');
    Route::post('report/vehicle-utilisation-yearly', 'Api\ReportController@vehical_Utilisation_yearly');

    // Geo Approval
    Route::post('report/geo-approval', 'Api\ReportController@geoApprovalReport');
    
    // Invoice Check
    Route::post('invoice/check', 'Api\InvoiceController@invoiceCheck');


    Route::post('report/orderSCReport', 'Api\ReportController@orderSCReport');
    
    // Global Report APi
    Route::post('report/merchandiser', 'Api\MerchandiserReportController@reports');

    // Distribution
    Route::post('distribution/list', 'Api\DistributionController@index');
    Route::post('distribution/list-test', 'Api\TestDistributionController@index');
    Route::post('distribution/add', 'Api\DistributionController@store');
    Route::get('distribution/edit/{uuid}', 'Api\DistributionController@edit');
    Route::post('distribution/edit/{uuid}', 'Api\DistributionController@update');
    Route::any('distribution/delete/{uuid}', 'Api\DistributionController@destroy');

    // Distribution
    Route::post('distribution-post-image/add', 'Api\DistributionController@storePostImage');
    Route::post('distribution-post-image/list', 'Api\DistributionController@indexPostImage');

    //
    Route::post('distribution/survey', 'Api\DistributionController@distributionSurveyList');

    Route::post('distribution-expire-item/add', 'Api\DistributionController@storeExpireItems');
    Route::post('distribution-expire-item/list', 'Api\DistributionController@expireItemsList');

    Route::post('distribution-damage-item/add', 'Api\DistributionController@storeDamageItems');
    Route::post('distribution-damage-item/list', 'Api\DistributionController@damageItemsList');

    Route::post('distribution-stock-item/add', 'Api\DistributionController@storeStockItems');
    Route::post('distribution-stock-item/list', 'Api\DistributionController@stockItemsList');

    //sos
    Route::post('share-of-shelf/add', 'Api\ShareOfShelfController@store');
    Route::post('share-of-shelf/list', 'Api\ShareOfShelfController@index');

    Route::post('distribution-all-in-one-item/add', 'Api\DistributionController@storeAllInOneItems');
    Route::get('distribution-msl/add/{date}', 'Api\DistributionController@createMSL');

    // Distribution
    Route::get('distribution-model-stock/list', 'Api\DistributionModelStockController@index');
    Route::post('distribution-model-stock/add', 'Api\DistributionModelStockController@store');
    Route::get('distribution-model-stock/edit/{uuid}', 'Api\DistributionModelStockController@edit');
    Route::post('distribution-model-stock/edit/{uuid}', 'Api\DistributionModelStockController@update');
    Route::any('distribution-model-stock/delete/{uuid}', 'Api\DistributionModelStockController@destroy');
    Route::get('distribution-model-stock/customer/{customer_id}/{distribution_id}', 'Api\DistributionModelStockController@indexByCustomer');

    Route::get('model-stock-detail/edit/{uuid}', 'Api\DistributionModelStockController@modelStockEdit');
    Route::post('model-stock-detail/edit/{uuid}', 'Api\DistributionModelStockController@modelStockUpdate');
    Route::get('model-stock-detail/delete/{uuid}', 'Api\DistributionModelStockController@modelStockDestroy');

    // Planogram Post
    Route::post('planogram-post/list', 'Api\PlanogramPostController@index');
    Route::get('planogram-post/{planogram_id}/list', 'Api\PlanogramPostController@indexByID');
    Route::post('planogram-post/add', 'Api\PlanogramPostController@store');
    Route::get('planogram-post/edit/{uuid}', 'Api\PlanogramPostController@edit');
    Route::post('planogram-post/edit/{uuid}', 'Api\PlanogramPostController@update');
    Route::any('planogram-post/delete/{uuid}', 'Api\PlanogramPostController@destroy');

    // Planogram
    Route::post('planogram/list', 'Api\PlanogramController@testList');
    Route::post('planogram/add', 'Api\PlanogramController@store');
    Route::get('planogram/edit/{uuid}', 'Api\PlanogramController@edit');
    Route::post('planogram/edit/{uuid}', 'Api\PlanogramController@update');
    Route::any('planogram/delete/{uuid}', 'Api\PlanogramController@destroy');
    Route::post('planogram/customer', 'Api\PlanogramController@planogramCustomerList');
    Route::get('planogram/merchandiser/customer/{customer_id}', 'Api\PlanogramController@planogramMerchandiserbyCustomer');

    Route::post('planogram/addPlanoCustomer', 'Api\PlanogramController@addCustomerPlanogram');
    Route::post('planogram/test-list', 'Api\PlanogramController@testList');
    Route::post('planogram/planogram-list', 'Api\PlanogramController@planogramList');
    Route::post('planogram/planogram-customer-list', 'Api\PlanogramController@planogramCustomerListDetails');

    
    // CompetitorInfo
    Route::post('competitor-info/list', 'Api\CompetitorInfoController@index');
    Route::post('competitor-info/add', 'Api\CompetitorInfoController@store');
    Route::get('competitor-info/edit/{uuid}', 'Api\CompetitorInfoController@edit');
    Route::post('competitor-info/edit/{uuid}', 'Api\CompetitorInfoController@update');
    Route::any('competitor-info/delete/{uuid}', 'Api\CompetitorInfoController@destroy');
    Route::get('competitor-info/brand', 'Api\CompetitorInfoController@competitorBrand');

    // Asset Tracking
    Route::post('asset-tracking/list', 'Api\AssetTrackingController@index');
    Route::post('asset-tracking/add', 'Api\AssetTrackingController@store');
    Route::get('asset-tracking/edit/{uuid}', 'Api\AssetTrackingController@edit');
    Route::post('asset-tracking/edit/{uuid}', 'Api\AssetTrackingController@update');
    Route::any('asset-tracking/delete/{uuid}', 'Api\AssetTrackingController@destroy');
    Route::get('asset-tracking/show-information/{id}', 'Api\AssetTrackingController@showAssetInfo');

    Route::post('asset-tracking-post/add', 'Api\AssetTrackingController@storeAssetTrakingPost');
    Route::post('asset-tracking-post', 'Api\AssetTrackingController@indexPostList');

    Route::post('asset-tracking/survey', 'Api\AssetTrackingController@assetTrackingSurveyList');

    // Survey
    Route::post('survey/list', 'Api\SurveyController@index');
    Route::post('survey/add', 'Api\SurveyController@store');
    Route::get('survey/edit/{uuid}', 'Api\SurveyController@edit');
    Route::post('survey/edit/{uuid}', 'Api\SurveyController@update');
    Route::any('survey/delete/{uuid}', 'Api\SurveyController@destroy');
    Route::post('survey/list-by-type', 'Api\SurveyController@indexByType');

    // Survey Type
    Route::get('survey-type/list', 'Api\SurveyTypeController@index');
    Route::post('survey-type/add', 'Api\SurveyTypeController@store');
    Route::get('survey-type/edit/{uuid}', 'Api\SurveyTypeController@edit');
    Route::post('survey-type/edit/{uuid}', 'Api\SurveyTypeController@update');
    Route::any('survey-type/delete/{uuid}', 'Api\SurveyTypeController@destroy');

    //Survey by customer
    Route::get('consumer/survey/{customer_id}', 'Api\SurveyByCustomerController@consumerSurveyByCustomer');
    Route::get('asset-tracking/survey/{customer_id}', 'Api\SurveyByCustomerController@assetTrackingSurveyByCustomer');

    // Survey Question
    Route::get('survey-question/list/{survey_id}', 'Api\SurveyQuestionController@index');
    Route::get('survey-question/all-list', 'Api\SurveyQuestionController@listAll');
    Route::post('survey-question/add', 'Api\SurveyQuestionController@store');
    Route::get('survey-question/edit/{uuid}', 'Api\SurveyQuestionController@edit');
    Route::post('survey-question/edit/{uuid}', 'Api\SurveyQuestionController@update');
    Route::any('survey-question/delete/{uuid}', 'Api\SurveyQuestionController@destroy');

    // Route::get('rolemenu/list', 'Api\RolemenuController@index');
    // Route::post('rolemenu/add', 'Api\RolemenuController@store');
    // Route::get('rolemenu/edit/{uuid}', 'Api\RolemenuController@edit');
    // Route::post('rolemenu/edit/{uuid}', 'Api\RolemenuController@update');
    // Route::any('rolemenu/delete/{uuid}', 'Api\RolemenuController@destroy');
    // Route::get('rolemenu/editrolemenu/{id}', 'Api\RolemenuController@editrolemenu');
    // Route::post('rolemenu/updaterolemenu', 'Api\RolemenuController@updaterolemenu');

    Route::get('decimalrate/getdecimalrate', 'Api\DecimalrateController@getdecimalrate');
    Route::post('decimalrate/savedecimalrate', 'Api\DecimalrateController@savedecimalrate');

    //survey-question-answer
    Route::post('survey-question-answer/add', 'Api\SurveyQuestionController@storeQuestionAnswer');
    Route::post('survey-question-answer/list', 'Api\SurveyQuestionController@indexQuestion');
    Route::get('survey-question-answer/{survey_id}', 'Api\SurveyQuestionController@surveyQuestionAnswer');
    Route::get('survey-question-answer-details/{id}', 'Api\SurveyQuestionController@surveyQuestionAnswerDetails');

    // ProductCatalog
    Route::get('product-catalog/list', 'Api\ProductCatalogController@index');
    Route::post('product-catalog/add', 'Api\ProductCatalogController@store');
    Route::get('product-catalog/edit/{uuid}', 'Api\ProductCatalogController@edit');
    Route::post('product-catalog/edit/{uuid}', 'Api\ProductCatalogController@update');
    Route::any('product-catalog/delete/{uuid}', 'Api\ProductCatalogController@destroy');

    // Promotional
    Route::get('promotional/list', 'Api\PromotionalController@index');
    Route::post('promotional/add', 'Api\PromotionalController@store');
    Route::get('promotional/edit/{uuid}', 'Api\PromotionalController@edit');
    Route::post('promotional/edit/{uuid}', 'Api\PromotionalController@update');
    Route::any('promotional/delete/{uuid}', 'Api\PromotionalController@destroy');
    Route::get('promotional/mobile/list', 'Api\PromotionalController@indexMobile');

    // Promotional Post
    Route::get('promotional-post/list/{promotional_post_id}', 'Api\PromotionalController@indexPromotionalPost');
    Route::post('promotional-post/add', 'Api\PromotionalController@storePormotionalPost');

    // Customer activity Route
    Route::get('customer-activity/list/{visit_id}', 'Api\CustomerActivityController@index');
    Route::post('customer-activity/add', 'Api\CustomerActivityController@store');
    Route::get('customer-activity/edit/{uuid}', 'Api\CustomerActivityController@edit');
    Route::post('customer-activity/edit/{uuid}', 'Api\CustomerActivityController@update');
    Route::any('customer-activity/delete/{uuid}', 'Api\CustomerActivityController@destroy'); 
    Route::post('customer-activity/bulk-action', 'Api\CustomerActivityController@bulkAction');

    Route::get('distribution/customer/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@distributionCustomers');
    Route::get('distribution/customers/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@distributionCustomersNew');
    Route::get('distribution/customers-item/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@distributionCustomersItem');
    Route::get('asset-tracking/customer/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@assetTrackingCustomers');

    // FCM Notification Testing
    Route::get('test-notification', function () {
        // dd('hello');
        $serviceAccountPath = storage_path('app/key/nfpc-presales-live-firebase-adminsdk-ofhlt-78b0226ac6.json');
        $googleClient = new GoogleClient();
        $googleClient->setAuthConfig($serviceAccountPath);
        $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');
    
        // Generate an OAuth2 token
        $accessToken = $googleClient->fetchAccessTokenWithAssertion()['access_token'];
            $response = Http::withHeaders([
               'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/v1/projects/nfpc-presales-live/messages:send', [
                'message' => [
                    'token' => 'eMJkPphgTY-BZEJkm78kwq:APA91bHfDivO84L1NcpAq3fdINiI6P95zag-EgnLN0y3YIPwOjiyDbF_MlZqaQJEFkNMqlBtorCRQmQwjX5E3Rk0RHjeMH8IZMcjJqZjEeH45hj6KqsGtefscrVthhk62ldHyRNEHGNQ',
                    'notification' => [
                        'title' => 'Notification Title',
                        'body' => 'Notification Body',
                    ],
                ],
            ]);
            
            $responseData = $response->json();
            return $responseData;
        });


    // Survey Cusromer by merchandiser
    Route::get('distribution-survey/merchandiser/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@distributionSurveyCustomers');
    Route::get('asset-tracking-survey/merchandiser/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@assetTrackingSurveyCustomers');
    Route::get('consumer-survey/merchandiser/{merchandiser_id}', 'Api\SurveyMerchandiserByCustomer@consumerSurveyCustomers');
    Route::get('sensory-survey', 'Api\SurveyMerchandiserByCustomer@sensorySurveyCustomers');

    // Cusromer by merchandiser
    Route::get('asset-tracking/merchandiser/customer/{merchandiser_id}', 'Api\MerchandiseByCustomer@assetMerchandiserbyCustomer');
    Route::get('merchandiser/customer/{merchandiser_id}', 'Api\MerchandiseByCustomer@merchandiserbyCustomer');
    Route::get('portfolio/customer/{merchandiser_id}', 'Api\MerchandiseByCustomer@merchandiserbyPortfolio');
    Route::get('activity-profile/customer/{merchandiser_id}', 'Api\MerchandiseByCustomer@merchandiserbyActivity');
    Route::get('supervisor/customer/{supervisor_id}', 'Api\MerchandiseByCustomer@supervisorbyCustomer');


    //Import
    Route::post('customer/import', 'Api\CustomerController@import');
    Route::post('customer/finalimport', 'Api\CustomerController@finalimport');
    Route::post('debitnote/import', 'Api\DebitNoteController@import');
    Route::post('expenses/import', 'Api\ExpenseController@import');
    Route::post('estimation/import', 'Api\EstimationController@import');
    Route::post('planogram/import', 'Api\PlanogramController@import');
    Route::post('distribution/import', 'Api\DistributionController@import');
    Route::post('distribution/finalimport', 'Api\DistributionController@finalimport');
    Route::post('distribution/shelf-display-import', 'Api\DistributionController@shelfDisplayImport');
    Route::post('assigninventory/import', 'Api\AssignInventoryController@import');
    Route::post('competitor-info/import', 'Api\CompetitorInfoController@import');
    Route::post('competitor-info/finalimport', 'Api\CompetitorInfoController@finalimport');
    Route::post('complaintfeedback/import', 'Api\ComplaintFeedbackController@import');
    Route::post('complaintfeedback/finalimport', 'Api\ComplaintFeedbackController@finalimport');
    Route::post('campaignpictures/import', 'Api\CampaignPictureController@import');
    Route::post('assettracking/import', 'Api\AssetTrackingController@import');
    Route::post('planogram/import', 'Api\PlanogramController@import');
    Route::post('planogram/finalimport', 'Api\PlanogramController@finalimport');
    Route::post('assign-inventory/import', 'Api\AssignInventoryController@import');
    Route::post('assign-inventory/finalimport', 'Api\AssignInventoryController@finalimport');
    Route::post('journey-plan/import', 'Api\JourneyPlanController@import');
    Route::post('journey-plan/finalimport', 'Api\JourneyPlanController@finalimport');
    Route::post('item/import', 'Api\ItemController@import');
    Route::post('item/finalimport', 'Api\ItemController@finalimport');
    Route::post('salesman/import', 'Api\SalesmanController@import');
    Route::post('salesman/finalimport', 'Api\SalesmanController@finalimport');


    
    //direct upload shelf customer and stock 
    Route::post('distribution/shelf-data-import', 'Api\DistributionController@importShelfCustomerDistributionStock');
    Route::post('distribution/json-shelf-data-import', 'Api\DistributionController@importShelfCustomerJSONDistributionStock');

    //6Oct new shelf API 
    Route::post('distribution/shelf-stock-import', 'Api\DistributionController@importShelfDistributionStockDetails');
    Route::post('distribution/shelf-customer-import', 'Api\DistributionController@importShelfDistributionCustomer');
    Route::post('distribution/shelf-distribution-list-by-distribution', 'Api\DistributionController@itemListByDistributionId');

    

    //merchandiserList
    Route::get('merchandiser/list', 'Api\SalesmanController@merchandiserList');

    //Invoice Mail Send
    Route::post('invoice/sendinvoice', 'Api\InvoiceController@sendinvoice');
    Route::post('send-mail', 'Api\GlobalController@sendMails');

    // Template
    Route::get('template/list/{module}', 'Api\TemplateController@index');
    Route::post('template/add', 'Api\TemplateController@store');
    // Route::get('template/usertemplate', 'Api\TemplateController@usertemplate');
    Route::post('template/updatetemplate', 'Api\TemplateController@updatetemplate');

    // plans
    Route::get('plan/list', 'Api\PlanController@index');
    Route::post('plan/add', 'Api\PlanController@store');
    Route::get('plan/edit/id', 'Api\PlanController@edit');
    Route::post('plan/edit/{id}', 'Api\PlanController@update');
    Route::get('plan/delete/{id}', 'Api\PlanController@delete');
    Route::get('plan-by-software/{software_id}', 'Api\PlanController@softwareByPlan');

    Route::get('plan-feature/{plan_id}', 'Api\PlanFeatureController@index');
    Route::post('plan-feature/add', 'Api\PlanFeatureController@store');
    Route::get('plan-feature/edit/id', 'Api\PlanFeatureController@edit');
    Route::post('plan-feature/edit/{id}', 'Api\PlanFeatureController@update');
    Route::get('plan-feature/delete/{id}', 'Api\PlanFeatureController@delete');

    // Software
    Route::get('software/list', 'Api\SoftwareController@index');
    Route::post('software/add', 'Api\SoftwareController@store');
    Route::get('software/edit/{id}', 'Api\SoftwareController@edit');
    Route::post('software/edit/{id}', 'Api\SoftwareController@update');
    Route::get('software/delete/{id}', 'Api\SoftwareController@delete');

    // Offer
    Route::get('offer/list', 'Api\OfferController@index');
    Route::post('offer/add', 'Api\OfferController@store');
    Route::get('offer/edit/{id}', 'Api\OfferController@edit');
    Route::post('offer/edit/{id}', 'Api\OfferController@update');
    Route::get('offer/delete/{id}', 'Api\OfferController@destroy');

    // Salesman Role
    Route::get('salesman-menu', 'Api\SalesmanMenuController@index');
    Route::post('salesman-menu-change', 'Api\SalesmanMenuController@roleChange');

    // Salesman Activity Profile
    Route::get('salesman-activity-profile/list', 'Api\SalesmanActivityProfileController@index');
    Route::post('salesman-activity-profile/add', 'Api\SalesmanActivityProfileController@store');
    Route::get('salesman-activity-profile/edit/{id}', 'Api\SalesmanActivityProfileController@edit');
    Route::post('salesman-activity-profile/edit/{id}', 'Api\SalesmanActivityProfileController@update');
    Route::get('salesman-activity-profile/delete/{id}', 'Api\SalesmanActivityProfileController@destroy');
    Route::get('salesman-activity-profile/{id}', 'Api\SalesmanActivityProfileController@indexBymc');

    Route::get('customer/getmappingfield', 'Api\CustomerController@getmappingfield');
    Route::get('complaintfeedback/getmappingfield', 'Api\ComplaintFeedbackController@getmappingfield');
    Route::get('distribution/getmappingfield', 'Api\DistributionController@getmappingfield');
    Route::get('assign-inventory/getmappingfield', 'Api\AssignInventoryController@getmappingfield');
    Route::get('planogram/getmappingfield', 'Api\PlanogramController@getmappingfield');
    Route::get('competitor-info/getmappingfield', 'Api\CompetitorInfoController@getmappingfield');
    Route::get('journey-plan/getmappingfield', 'Api\JourneyPlanController@getmappingfield');
    Route::get('item/getmappingfield', 'Api\ItemController@getmappingfield');
    Route::get('salesman/getmappingfield', 'Api\SalesmanController@getmappingfield');

    // Download
    Route::post('invoice/download', 'Api\DownloadController@invoice');
    Route::post('delivery/download', 'Api\DownloadController@deliveryInvoice');
    Route::post('credit-note/download', 'Api\DownloadController@creditNote');
    Route::post('debit-note/download', 'Api\DownloadController@debitNote');
    Route::post('order/download', 'Api\DownloadController@order');
    Route::post('estimate/download', 'Api\DownloadController@estimate');
    Route::post('delivery/group_pdf_download', 'Api\DownloadController@groupPDF');

    Route::post('customer/download', 'Api\DownloadController@customer');
    Route::post('expense/download', 'Api\DownloadController@expense');
    Route::post('collection/download', 'Api\DownloadController@collection');

    // get-menu-by-software
    Route::get('get-menu-by-software', 'Api\GlobalController@getMenuBySoftware');
    Route::get('get-setting-menu-by-software', 'Api\GlobalController@getSettingMenuBySoftware');
    Route::get('permission', 'Api\GlobalController@permissionSingle');

    // Subscription
    Route::post('subscription', 'Api\PaymentController@store');
    Route::post('unsubscription', 'Api\PaymentController@planUnsubscribed');
    Route::post('update-subscription', 'Api\PaymentController@updateSubscription');

    //Login Domain Track
    Route::post('login-track', 'Api\PaymentController@loginDomainTrack');
    Route::get('global-setting', 'Api\GlobalController@generalSetting');

    // Load Request
    Route::post('loadrequest/list', 'Api\LoadrequestController@index');
    Route::post('loadrequest/add', 'Api\LoadrequestController@store');
    Route::get('loadrequest/edit/{uuid}', 'Api\LoadrequestController@edit');
    Route::post('loadrequest/edit/{uuid}', 'Api\LoadrequestController@update');
    Route::any('loadrequest/delete/{uuid}', 'Api\LoadrequestController@destroy');
    Route::post('loadrequest/bulk-action', 'Api\LoadrequestController@bulkAction');
    Route::any('loadrequest/approve/{uuid}', 'Api\LoadrequestController@approve');

    Route::post('pdp-mobile', 'Api\PriceDiscoPromoController@PDPMobileIndex');
    Route::post('pdp-mobile-pricing', 'Api\PriceDiscoPromoController@PDPMobileIdexnPricing');
    Route::post('pdp-mobile-other', 'Api\PriceDiscoPromoController@PDPMobileIndexOther');
    Route::get('pdp-lob/{lob_id}', 'Api\PriceDiscoPromoController@lobPrice');

    // Theme
    Route::get('themes', 'Api\ThemeController@index');
    Route::post('change/theme', 'Api\ThemeController@store');

    // Tax Exemption
    Route::get('tax-exemption/list', 'Api\TaxExemptionController@index');
    Route::post('tax-exemption/add', 'Api\TaxExemptionController@store');
    Route::get('tax-exemption/edit/{uuid}', 'Api\TaxExemptionController@edit');
    Route::post('tax-exemption/edit/{uuid}', 'Api\TaxExemptionController@update');
    Route::any('tax-exemption/delete/{uuid}', 'Api\TaxExemptionController@destroy');

    // Tax Rate
    Route::get('tax-rate/list', 'Api\TaxRateController@index');
    Route::post('tax-rate/add', 'Api\TaxRateController@store');
    Route::get('tax-rate/edit/{uuid}', 'Api\TaxRateController@edit');
    Route::post('tax-rate/edit/{uuid}', 'Api\TaxRateController@update');
    Route::any('tax-rate/delete/{uuid}', 'Api\TaxRateController@destroy');

    // Reason
    Route::get('reason-type-all', 'Api\ReasonTypeController@indexAll');
    Route::get('reason-type/list', 'Api\ReasonTypeController@index');
    Route::post('reason-type/add', 'Api\ReasonTypeController@store');
    Route::get('reason-type/edit/{uuid}', 'Api\ReasonTypeController@edit');
    Route::post('reason-type/edit/{uuid}', 'Api\ReasonTypeController@update');
    Route::any('reason-type/delete/{uuid}', 'Api\ReasonTypeController@destroy');

    // Tax Preference
    Route::get('tax-preference/show', 'Api\TaxPreferenceController@index');
    Route::post('tax-preference/add', 'Api\TaxPreferenceController@store');

    // Tax Setting
    Route::get('tax-setting/show', 'Api\TaxSettingController@index');
    Route::post('tax-setting/add', 'Api\TaxSettingController@store');

    // Share Assortment
    Route::post('share-assortment/list', 'Api\ShareOfAssortmentController@index');
    Route::post('share-assortment/add', 'Api\ShareOfAssortmentController@store');

    // Share Display
    Route::post('share-display/list', 'Api\ShareOfDisplayController@index');
    Route::post('share-display/add', 'Api\ShareOfDisplayController@store');

    // SOS
    Route::post('sos/list', 'Api\SOSController@index');
    Route::post('sos/add', 'Api\SOSController@store');

    // market-promotion
    Route::post('market-promotion/list', 'Api\MarketPromotionController@index');
    Route::post('market-promotion/add', 'Api\MarketPromotionController@store');

    // pricing-check
    Route::post('pricing-check/list', 'Api\PricingCheckController@index');
    Route::post('pricing-check/list-mobile', 'Api\PricingCheckController@indexMobile');
    Route::post('pricing-check/add', 'Api\PricingCheckController@store');

    // Merchandiser Replacements
    Route::get('merchandiser-replacement/list', 'Api\MerchandiserReplacementsController@index');
    Route::post('merchandiser-replacement/add', 'Api\MerchandiserReplacementsController@store');
    Route::post('merchandiser-swap/add', 'Api\MerchandiserReplacementsController@storeSwap');

    // Dashboard
    Route::post('dashboard', 'Api\DashboardController@index');
    Route::post('dashboard2', 'Api\Dashboard2Controller@index');
    Route::post('dashboard3', 'Api\Dashboard3Controller@index');
    Route::post('dashboard4', 'Api\Dashboard4Controller@index');

    // LogUpload
    Route::post('log-upload', 'Api\DownloadController@logUpload');
    Route::post('salesman-login-log/list', 'Api\SalesmanController@salesmanLoginLog');
    Route::get('merchandiser-update/delete/{merchandiser_id}', 'Api\GlobalController@deleteMerchandiserUpdate');

    // Login Log
    Route::post('login-detail/{user_id}', 'Api\LogDetailController@getUserLogsDetail');

    // AD ID update
    Route::post('user/adid', 'Api\GlobalController@updateAdidImport');

    // Import Controller
    Route::post('customerGeoImport', 'Api\ImportController@customerGeoImport')->name('customerGeoImport');
    Route::post('exsice-import', 'Api\ImportController@exciseImport')->name('exsice_import');

    // Route Diversion
    Route::get('salesman-route-approval/list', 'Api\SalesmanRouteChangeController@index');
    Route::post('salesman-route-approval/add', 'Api\SalesmanRouteChangeController@store');
    Route::post('salesman-route-approval/update/{uuid}', 'Api\SalesmanRouteChangeController@approval');

    // Notificaiton
    Route::post('notifications', 'Api\NotificationController@index');
    Route::post('notification/status/change', 'Api\NotificationController@statusChange');
    Route::post('notification/read/{notification_id}', 'Api\NotificationController@notificationRead');
    Route::get('notification/read-all', 'Api\NotificationController@readAll');
    Route::get('notification/delete-all', 'Api\NotificationController@notificationDelete');

    // Geo Approval
    Route::post('salesman-geo-approval-request', 'Api\GeoApprovalController@requestGeoApprovalSalesman');
    Route::post('supervisor-geo-approval-request', 'Api\GeoApprovalController@requestGeoApprovalSupervisor');

    // overdue limits Approval
    Route::post('salesman-overdue-limits-approval-request', 'Api\OverdueLimitController@requestOverDueLimitApprovalSalesman');
    Route::post('supervisor-overdue-limits-approval-request', 'Api\OverdueLimitController@requestOverDueLimitSupervisor');

    // Mobile data
    Route::post('get-salesman-sales', 'Api\SalesmanController@getMobileSalesData');
    Route::post('geoMail', 'Api\GeoApprovalController@geoMail');

    // LOB Route
    Route::get('lob/list', 'Api\LobController@index');
    Route::post('lob/add', 'Api\LobController@store');
    Route::any('lob/delete/{uuid}', 'Api\LobController@destroy');

    Route::post('customer/dropdown-list', 'Api\CustomerController@customerDropDown');
    Route::post('item/dropdown-list', 'Api\ItemController@itemDropDown');

    // get customer lob list
    Route::get('customer/lob-list/{user_id}', 'Api\CustomerController@customer_lob');
    Route::get('get/warehouse/{lob_id}', 'Api\CustomerController@getWarehouse');


    // Warehouse Route
    Route::get('warehouse/list', 'Api\WarehouseController@index');
    Route::post('warehouse/add', 'Api\WarehouseController@store');
    Route::get('warehouse/edit/{uuid}', 'Api\WarehouseController@edit');
    Route::post('warehouse/edit/{uuid}', 'Api\WarehouseController@update');
    Route::any('warehouse/delete/{uuid}', 'Api\WarehouseController@destroy');
    Route::post('warehouse/bulk-action', 'Api\WarehouseController@bulkAction');

    // Warehouse detail Route
    Route::get('warehousedetail/list/{id}', 'Api\WarehousedetailController@index');
    Route::post('warehousedetail/add', 'Api\WarehousedetailController@store');
    Route::get('warehousedetail/edit/{uuid}', 'Api\WarehousedetailController@edit');
    Route::post('warehousedetail/edit/{uuid}', 'Api\WarehousedetailController@update');
    Route::any('warehousedetail/delete/{uuid}', 'Api\WarehousedetailController@destroy');
    Route::post('warehousedetail/bulk-action', 'Api\WarehousedetailController@bulkAction');
    Route::get('warehousedetail/item-count/{warehouse_id}/{item_id}', 'Api\WarehousedetailController@getWarehouseItem');

    // Storagelocation Route
    Route::get('storage-location/list', 'Api\StoragelocationController@indexAll');
    Route::get('storage/list/{id}', 'Api\StoragelocationController@index');
    Route::post('storage/add', 'Api\StoragelocationController@store');
    Route::get('storage/edit/{uuid}', 'Api\StoragelocationController@edit');
    Route::post('storage/edit/{uuid}', 'Api\StoragelocationController@update');
    Route::any('storage/delete/{uuid}', 'Api\StoragelocationController@destroy');
    Route::post('storage/bulk-action', 'Api\StoragelocationController@bulkAction');
    Route::post('isStockCheck', 'Api\StoragelocationController@isStockCheck');
    Route::post('isStockCheckMultiple', 'Api\StoragelocationController@isStockCheckMultiple');
    Route::post('routeItemQty', 'Api\StoragelocationController@routeItemQty');

    // Storagelocation detail Route
    Route::get('storagedetails', 'Api\StoragelocationdetailController@indexAll');
    Route::get('storagedetail/list/{id}', 'Api\StoragelocationdetailController@index');
    Route::post('storagedetail/add', 'Api\StoragelocationdetailController@store');
    Route::get('storagedetail/edit/{uuid}', 'Api\StoragelocationdetailController@edit');
    Route::post('storagedetail/edit/{uuid}', 'Api\StoragelocationdetailController@update');
    Route::any('storagedetail/delete/{uuid}', 'Api\StoragelocationdetailController@destroy');
    Route::post('storagedetail/bulk-action', 'Api\StoragelocationdetailController@bulkAction');
    Route::get('storagedetail/item-count/{storge_location_id}/{item_id}', 'Api\StoragelocationdetailController@getWarehouseItem');
    Route::post('storagedetail/routeItemQty', 'Api\StoragelocationdetailController@getRouteItem');

    // Cusomer Warehouse Mapping List
    Route::post('customer-warehouse-mapping/list', 'Api\CustomerWarehouseMappingController@index');
    // Import customer warehouse
    Route::post('customer-warehouse-mapping', 'Api\CustomerWarehouseMappingController@customerWarehouseMapping');

    // item branch plant
    Route::post('item-branch-plant/list', 'Api\ItemBranchPlantController@index');

    // Customer Region Mapping
    Route::post('customer-region-mapping', 'Api\CustomerRegionMappingController@storeImport');
    Route::get('customer-region-mapping/edit/{uuid}', 'Api\CustomerRegionMappingController@edit');
    Route::post('customer-region-mapping/edit/{uuid}', 'Api\CustomerRegionMappingController@update');
    Route::post('customer-region-mapping/list', 'Api\CustomerRegionMappingController@index');

    // Customer KAM KSM Mapping
    Route::post('customer-ksm-kam-mapping', 'Api\CustomerKamMappingController@store');
    Route::post('customer-ksm-kam-mapping/list', 'Api\CustomerKamMappingController@index');

    // Item Base Price
    Route::post('item-base-price-mapping', 'Api\ItemBasePriceController@store');
    Route::post('item-base-price-mapping/list', 'Api\ItemBasePriceController@index');

    // Customer Based Price
    Route::post('customer-based-price-mapping', 'Api\CustomerBasedPriceController@store');
    Route::post('customer-based-price-mapping/import', 'Api\CustomerBasedPriceController@import');
    Route::post('customer-based-price-mapping/list', 'Api\CustomerBasedPriceController@index');
    Route::post('customer-based-price-mapping/create', 'Api\CustomerBasedPriceController@create');
    Route::post('customer-based-price-mapping/activelist', 'Api\CustomerBasedPriceController@Activelist');

    // Organisation Setting
    Route::post('organisation-setting/add', 'Api\OrganisationSettingController@store');
    Route::get('organisation-setting/edit/{uuid}', 'Api\OrganisationSettingController@edit');
    Route::post('organisation-setting/edit/{uuid}', 'Api\OrganisationSettingController@update');

    // Driver and Van Swaping Route
    Route::get('driver-van-swaping/list', 'Api\DriverAndVanSwappingController@index');
    Route::post('driver-van-swaping/add', 'Api\DriverAndVanSwappingController@store');

    // JED APIs
    Route::get('jde-customer-download', 'Api\CustomerController@JDENewCustomerDownload');
    Route::get('jde-lob-customer-download', 'Api\CustomerController@JDENewLobCustomerDownload');
    Route::get('jde-item-download', 'Api\ItemController@JDEItemDownload');
    Route::get('jde-warehouse-download', 'Api\StoragelocationController@JDEWarehouseDownload');

    // Pallet
    Route::post('palettes', 'Api\PaletteController@index');
    Route::get('palette/detail/{salesman_id}', 'Api\PaletteController@indexBySalesman');
    Route::post('palette/add', 'Api\PaletteController@store');
    Route::get('palette/edit/{uuid}', 'Api\PaletteController@edit');
    Route::post('palette-by-salesman', 'Api\PaletteController@show');

    Route::get('pallet-return/{salesman_id}', 'Api\PaletteController@indexByRetunPending');
    Route::post('update-pallet-return', 'Api\PaletteController@updatePalletApprovalStatus');

    // Zone
    Route::get('zone/list', 'Api\ZoneController@index');
    Route::post('zone/add', 'Api\ZoneController@store');
    Route::get('zone/edit/{uuid}', 'Api\ZoneController@edit');
    Route::post('zone/edit/{uuid}', 'Api\ZoneController@update');
    Route::any('zone/delete/{uuid}', 'Api\ZoneController@destroy');

    // Only For Mobiles
    Route::prefix('v1')->group(function () {
        Route::get('reason-type/list', 'Api\ReasonTypeController@indexMobile');
        Route::get('item/list', 'Api\ItemController@mobileIndex');
        Route::get('helper/list', 'Api\SalesmanController@helperMobileList');
        Route::get('item-branch-plant/list', 'Api\ItemBranchPlantController@indexMobile');
        Route::get('item-base-price/list', 'Api\ItemBasePriceController@indexMobile');
        Route::get('pdp-mobile', 'Api\PriceDiscoPromoController@mobilePrice');
        Route::get('salesman-tomorrow-delivery/{salesman_id}', 'Api\SalesmanController@tomorrowDelivery');
        Route::post('salesman-shipment', 'Api\SalesmanController@shipmentDeliveryStatus');
        Route::get('invoice-submitted/{salesman_id}', 'Api\SalesmanController@invoiceSubmitted');
        Route::post('invoice-submitted-post', 'Api\SalesmanController@invoiceSubmittedPosting');
        Route::post('credit-note/update/{uuid}', 'Api\CreditNoteController@updateCreditNote');
        //pallet
        Route::post('get-pallets', 'Api\PaletteController@indexPalletBySalesman');
        Route::post('pallet-status-update', 'Api\PaletteController@updatePalletStatus');
        Route::post('pallet-return-add', 'Api\PaletteController@storeReturn');
        Route::get('item-return-show/{salesman_id}', 'Api\PaletteController@showReturn');
    });
});

Route::get('orderSpot_report', 'Api\ImportController@orderSpotReport')->name('orderSpotReport');
Route::get('orderSpot_report_clone', 'Api\ImportController@orderSpotReportClone')->name('orderSpotReportClone');
Route::post('customer-merchandiser/add', 'Api\GlobalController@uploadFitle');
Route::get('plan-by-pass/list', 'Api\PlanController@indexByPass');
Route::post('distributionImport', 'Api\ImportController@distributionImport')->name('distributionImport');
Route::post('channelToRadius', 'Api\ImportController@channelToRadius')->name('channelToRadius');
Route::get('distributionItemsChannel', 'Api\ImportController@distributionItemsChannel')->name('distributionItemsChannel');
Route::get('ItemsChannel', 'Api\ImportController@ItemsChannel')->name('ItemsChannel');
Route::post('itemCategory', 'Api\ImportController@itemCategory')->name('itemCategory');
Route::post('customer-price-import', 'Api\ImportController@customerItemPrice')->name('customer_price_import');
Route::get('rfgen_order_picking', 'Api\ImportController@rfGenOrderPicking')->name('rfGenOrderPicking');
Route::get('order_views', 'Api\ImportController@orderView')->name('orderView');
Route::get('returnAssingSalesman', 'Api\ImportController@returnAssingSalesman')->name('returnAssingSalesman');
Route::get('truck_utilisation_report', 'Api\ImportController@VehicleUtilisationReport')->name('VehicleUtilisationReport');
Route::get('spotReturn_report', 'Api\ImportController@spotReturnReport')->name('spotReturnReport');
Route::get('odUpdate', 'Api\ImportController@odUpdate')->name('odUpdate');
Route::get('salesVsGrv', 'Api\ImportController@salesVsGrv')->name('salesVsGrv');
Route::get('saleunlo', 'Api\ImportController@saleunlo')->name('saleunlo');
Route::post('merchandiserSupervisorASMUpdate', 'Api\ImportController@merchandiserSupervisorASMUpdate')->name('merchandiserSupervisorASMUpdate');

Route::get('geoMail', 'Api\GeoApprovalController@geoMail');
Route::get('load_item', 'Api\DeliveryController@load_item');
Route::get('password', 'Api\AuthController@password');
Route::get('order-sendmail', 'Api\DownloadController@sendMailToGroupCustomer');
// Route::get('sendCustomerMailFile/{delivery}/{order}', 'Api\WFMApprovalRequestController@sendCustomerMailFile');

Route::fallback(function () {
    return response()->json([
        'message' => 'Page Not Found. If error persists, contact admin'
    ], 404);
});

Route::get('text-mail', function () {
    $data = array('name' => "Virat Gandhi");

    Mail::send(['email' => 'emails.template'], $data, function ($message) {
        $message->to('hardiksolanki811@gmail.com', 'Tutorials Point')->subject('Laravel Basic Testing Mail');
        $message->from('accounts.receivable@nfpc.net', 'Hardik Solanki');
    });
});


Route::get('/prd-order', function () {

    $order = \App\Model\OrderPrd::get()->take(10);
    pre($order);
    $records = \DB::connection('server_mysql')
        ->table('orders')
        ->get()
        ->take(10)
        ->toArray();

    pre($records);
});
