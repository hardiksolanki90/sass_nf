<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\DistributionImport;
use App\Imports\ShelfDisplayImport;
use App\Model\Channel;
use App\Model\CustomerInfo;
use App\Model\CustomerLob;
use App\Model\Distribution;
use App\Model\DistributionCustomer;
use App\Model\DistributionDamageItem;
use App\Model\DistributionExpireItem;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\OrganisationRoleAttached;
use App\Model\DistributionPostImage;
use App\Model\DistributionStock;
use App\Model\ImportTempFile;
use App\Model\ShareOfShelf;
use App\Model\Survey;
use App\Model\Item;
use App\Model\ItemUom;
use Illuminate\Http\Request;
use League\OAuth2\Server\RequestEvent;
use stdClass;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use File;
use Illuminate\Support\Facades\DB;
use URL;
use App\Model\PortfolioManagement;
use App\Model\PortfolioManagementItem;
use App\User;
use Illuminate\Support\Collection;
use App\Exports\StockItemListExport;
use Excel;

class TestDistributionController extends Controller
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

        $all_salesman = getSalesman(true);
        //DB::getQueryLog();
        $distribution_query = Distribution::with(
            'distributionCustomer',
            'distributionCustomer.customer:id,firstname,lastname',
            'distributionCustomer.customer.customerInfo:id,user_id,customer_code'
        );

        if ($request->name) {
            $distribution_query->where('name', $request->name);
        }

        // if (count($all_salesman)) {
        //     $distribution_query->whereHas('distributionCustomer', function ($q) use ($all_salesman) {
        //         $q->whereIn('customer_id', $all_salesman);
        //     });
        // }

        if ($request->date) {
            $distribution_query->where('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->start_date) {
            $distribution_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $distribution_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }
        $distribution = $distribution_query->orderBy('id', 'desc')
            ->get();
            //$queries = DB::getQueryLog();
            //dd($queries);
        $distribution_array = array();
        if (is_object($distribution)) {
            foreach ($distribution as $key => $distribution1) {
                $distribution_array[] = $distribution[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($distribution_array[$offset])) {
                    $data_array[] = $distribution_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($distribution_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($distribution_array);
        } else {
            $data_array = $distribution_array;
        }
        return prepareResult(true, $data_array, [], "Distribution listing", $this->success, $pagination);
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
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution", $this->unprocessableEntity);
        }

        if (is_array($request->customer) && sizeof($request->customer) < 1) {
            return prepareResult(false, [], "Please add atleast one customer.", "Error while validating distribution", $this->success);
        }

        \DB::beginTransaction();
        try {

            $distribution = new Distribution;
            $distribution->name = $request->name;
            $distribution->trip_id = $request->trip_id;
            $distribution->start_date = $request->start_date;
            $distribution->end_date = $request->end_date;
            $distribution->height = $request->height;
            $distribution->width = $request->width;
            $distribution->depth = $request->depth;
            $distribution->status = $request->status;
            $distribution->save();

            foreach ($request->customer as $customer) {
                $distribution_customer = new DistributionCustomer;
                $distribution_customer->customer_id = $customer;
                $distribution_customer->distribution_id = $distribution->id;
                $distribution_customer->save();
            }

            \DB::commit();

            $distribution->distributionCustomer;
            if (count($distribution->distributionCustomer)) {
                foreach ($distribution->distributionCustomer as $k => $customer) {
                    $distribution->distributionCustomer[$k]->customer = $customer->customer;
                }
            }

            return prepareResult(true, $distribution, [], "Distribution added successfully", $this->created);
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
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating distribution", $this->unauthorized);
        }

        $distribution = Distribution::where('uuid', $uuid)
            ->with('distributionCustomer', 'distributionCustomer.customer:id,firstname,lastname')
            ->first();

        if (!is_object($distribution)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $distribution, [], "Distribution Edit", $this->success);
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
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution", $this->unprocessableEntity);
        }

        if (is_array($request->customer) && sizeof($request->customer) < 1) {
            return prepareResult(false, [], "Please add atleast one customer.", "Error while validating distribution", $this->success);
        }

        \DB::beginTransaction();
        try {

            $distribution = Distribution::where('uuid', $uuid)->first();
            DistributionCustomer::where('distribution_id', $distribution->id)->delete();

            $distribution->name = $request->name;
            $distribution->start_date = $request->start_date;
            $distribution->end_date = $request->end_date;
            $distribution->height = $request->height;
            $distribution->width = $request->width;
            $distribution->depth = $request->depth;
            $distribution->status = $request->status;
            $distribution->save();

            foreach ($request->customer as $customer) {
                $distribution_customer = new DistributionCustomer;
                $distribution_customer->customer_id = $customer;
                $distribution_customer->distribution_id = $distribution->id;
                $distribution_customer->save();

                updateMerchandiser($request->user()->organisation_id, $customer, true);
            }

            \DB::commit();

            $distribution->getSaveData();

            return prepareResult(true, $distribution, [], "Distribution updated successfully", $this->created);
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
            return prepareResult(false, [], [], "Error while validating distribution", $this->unauthorized);
        }

        $distribution = Distribution::where('uuid', $uuid)
            ->first();

        if (is_object($distribution)) {
            DistributionCustomer::where('distribution_id', $distribution->id)->delete();
            DistributionDamageItem::where('distribution_id', $distribution->id)->delete();
            DistributionExpireItem::where('distribution_id', $distribution->id)->delete();
            DistributionModelStock::where('distribution_id', $distribution->id)->delete();
            DistributionPostImage::where('distribution_id', $distribution->id)->delete();
            DistributionStock::where('distribution_id', $distribution->id)->delete();
            $distribution->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'name' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'height' => 'required',
                'width' => 'required',
                'depth' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "addPostImage") {
            $validator = \Validator::make($input, [
                'distribution_id' => 'required|integer|exists:distributions,id',
                'customer_id' => 'required|integer|exists:users,id',
                'salesman_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "expireitems") {
            $validator = \Validator::make($input, [
                'distribution_id' => 'required|integer|exists:distributions,id',
                'customer_id' => 'required|integer|exists:users,id',
                'salesman_id' => 'required|integer|exists:users,id',
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'qty' => 'required',
                'expiry_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "damageitems") {
            $validator = \Validator::make($input, [
                'distribution_id' => 'required|integer|exists:distributions,id',
                'customer_id' => 'required|integer|exists:users,id',
                'salesman_id' => 'required|integer|exists:users,id',
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'damage_item_qty' => 'required',
                'expire_item_qty' => 'required',
                'saleable_item_qty' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "stockitems") {
            $validator = \Validator::make($input, [
                'distribution_id' => 'required|integer|exists:distributions,id',
                'customer_id' => 'required|integer|exists:users,id',
                'salesman_id' => 'required|integer|exists:users,id',
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'stock' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "allInOne") {
            $validator = \Validator::make($input, [
                'distribution_id' => 'required|integer|exists:distribution,id',
                'customer_id' => 'required|integer|exists:users,id',
                'salesman_id' => 'required|integer|exists:users,id'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function storeExpireItems(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "expireitems");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution expire items", $this->unprocessableEntity);
        }
        \DB::beginTransaction();
        try {

            $distribution_expire_items = new DistributionExpireItem;
            $distribution_expire_items->distribution_id = $request->distribution_id;
            $distribution_expire_items->customer_id = $request->customer_id;
            $distribution_expire_items->salesman_id = $request->salesman_id;
            $distribution_expire_items->item_id = $request->item_id;
            $distribution_expire_items->item_uom_id = $request->item_uom_id;
            $distribution_expire_items->qty = $request->qty;
            $distribution_expire_items->expiry_date = $request->expiry_date;
            $distribution_expire_items->save();

            \DB::commit();

            return prepareResult(true, $distribution_expire_items, [], "Distribution expire items added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function expireItemsList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->distribution_id) {
            return prepareResult(false, [], [], "Error while validating expire item", $this->unprocessableEntity);
        }

        $distribution_id = $request->distribution_id;
        $all_salesman = getSalesman();

        $distribution_expire_item_query = DistributionExpireItem::with(
            'item:id,item_name,item_code',
            'itemUom:id,name',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'distribution'
        )
            ->where('distribution_id', $distribution_id);
        if ($request->date) {
            $distribution_expire_item_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }
        if (count($all_salesman)) {
            $distribution_expire_item_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->salesman_name) {
            $salesman_name = $request->salesman_name;
            $exploded_name = explode(" ", $salesman_name);
            if (count($exploded_name) < 2) {
                $distribution_expire_item_query->whereHas('salesman', function ($q) use ($salesman_name) {
                    $q->where('firstname', 'like', '%' . $salesman_name . '%')
                        ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distribution_expire_item_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $distribution_expire_item_query->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distribution_expire_item_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $code = $request->customer_code;
            $distribution_expire_item_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                $q->where('customer_code', $code);
            });
        }

        if ($request->item_name) {
            $item_name = $request->item_name;
            $distribution_expire_item_query->whereHas('item', function ($q) use ($item_name) {
                $q->where('item_name', $item_name);
            });
        }

        if ($request->item_code) {
            $code = $request->item_code;
            $distribution_expire_item_query->whereHas('item', function ($q) use ($code) {
                $q->where('item_code', $code);
            });
        }

        if ($request->distribution_name) {
            $distribution_name = $request->distribution_name;
            $distribution_expire_item_query->whereHas('distribution', function ($q) use ($distribution_name) {
                $q->where('name', $distribution_name);
            });
        }

        if ($request->all) {
            $distribution_expire_items = $distribution_expire_item_query->orderBy('id', 'desc')->get();
        } else {
            if ($request->today && !$request->date) {
                $distribution_expire_item_query->whereDate('created_at', date('Y-m-d'));
            }
            $distribution_expire_items = $distribution_expire_item_query->orderBy('id', 'desc')->get();
        }

        $distribution_expire_items_array = array();
        if (is_object($distribution_expire_items)) {
            foreach ($distribution_expire_items as $key => $distribution_expire_items1) {
                $distribution_expire_items_array[] = $distribution_expire_items[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($distribution_expire_items_array[$offset])) {
                    $data_array[] = $distribution_expire_items_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($distribution_expire_items_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($distribution_expire_items_array);
        } else {
            $data_array = $distribution_expire_items_array;
        }

        return prepareResult(true, $data_array, [], "Distribution Expire Items listing", $this->success, $pagination);
    }

    public function storeDamageItems(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "damageitems");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution damage.", $this->unprocessableEntity);
        }
        \DB::beginTransaction();
        try {

            $distribution_damage_items = new DistributionDamageItem;
            $distribution_damage_items->distribution_id = $request->distribution_id;
            $distribution_damage_items->customer_id = $request->customer_id;
            $distribution_damage_items->salesman_id = $request->salesman_id;
            $distribution_damage_items->item_id = $request->item_id;
            $distribution_damage_items->item_uom_id = $request->item_uom_id;
            $distribution_damage_items->damage_item_qty = $request->damage_item_qty;
            $distribution_damage_items->expire_item_qty = $request->expire_item_qty;
            $distribution_damage_items->saleable_item_qty = $request->saleable_item_qty;
            $distribution_damage_items->save();

            \DB::commit();

            return prepareResult(true, $distribution_damage_items, [], "Distribution damage items added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function damageItemsList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->distribution_id) {
            return prepareResult(false, [], [], "Error while validating damage item", $this->unprocessableEntity);
        }

        $distribution_id = $request->distribution_id;

        $all_salesman = getSalesman();

        $distribution_damage_item_query = DistributionDamageItem::with(
            'item:id,item_name,item_code',
            'itemUom:id,name',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'distribution'
        )
            ->where('distribution_id', $distribution_id);

        if ($request->date) {
            $distribution_damage_item_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }
        if (count($all_salesman)) {
            $distribution_damage_item_query->whereIn('salesman_id', $all_salesman);
        }
        if ($request->salesman_name) {
            $salesman_name = $request->salesman_name;
            $exploded_name = explode(" ", $salesman_name);
            if (count($exploded_name) < 2) {
                $distribution_damage_item_query->whereHas('salesman', function ($q) use ($salesman_name) {
                    $q->where('firstname', 'like', '%' . $salesman_name . '%')
                        ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distribution_damage_item_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $distribution_damage_item_query->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distribution_damage_item_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $code = $request->customer_code;
            $distribution_damage_item_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                $q->where('customer_code', $code);
            });
        }

        if ($request->item_name) {
            $item_name = $request->item_name;
            $distribution_damage_item_query->whereHas('item', function ($q) use ($item_name) {
                $q->where('item_name', $item_name);
            });
        }

        if ($request->item_code) {
            $code = $request->item_code;
            $distribution_damage_item_query->whereHas('item', function ($q) use ($code) {
                $q->where('item_code', $code);
            });
        }

        if ($request->distribution_name) {
            $distribution_name = $request->distribution_name;
            $distribution_damage_item_query->whereHas('distribution', function ($q) use ($distribution_name) {
                $q->where('name', $distribution_name);
            });
        }

        if ($request->all) {
            $distribution_damage_items = $distribution_damage_item_query->orderBy('id', 'desc')->get();
        } else {
            if ($request->today && !$request->date) {
                $distribution_damage_item_query->whereDate('created_at', date('Y-m-d'));
            }
            $distribution_damage_items = $distribution_damage_item_query->orderBy('id', 'desc')->get();
        }

        $distribution_damage_items_array = array();
        if (is_object($distribution_damage_items)) {
            foreach ($distribution_damage_items as $key => $distribution_damage_items1) {
                $distribution_damage_items_array[] = $distribution_damage_items[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($distribution_damage_items_array[$offset])) {
                    $data_array[] = $distribution_damage_items_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($distribution_damage_items_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($distribution_damage_items_array);
        } else {
            $data_array = $distribution_damage_items_array;
        }

        return prepareResult(true, $data_array, [], "Distribution Expire Items listing", $this->success, $pagination);
    }

    public function storeStockItems(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "stockitems");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution stock.", $this->unprocessableEntity);
        }
        \DB::beginTransaction();
        try {

            $distribution_stock = new DistributionStock;
            $distribution_stock->distribution_id = $request->distribution_id;
            $distribution_stock->customer_id = $request->customer_id;
            $distribution_stock->salesman_id = $request->salesman_id;
            $distribution_stock->item_id = $request->item_id;
            $distribution_stock->item_uom_id = $request->item_uom_id;
            $distribution_stock->stock = $request->stock;
            $distribution_stock->is_out_of_stock = $request->is_out_of_stock;
            $distribution_stock->save();

            \DB::commit();

            $distribution_stock->getSaveData();

            return prepareResult(true, $distribution_stock, [], "Distribution stock added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function stockItemsList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->distribution_id) {
            return prepareResult(false, [], [], "Error while validating stock item", $this->unprocessableEntity);
        }

        $distribution_id = $request->distribution_id;

        $all_salesman = getSalesman();

        if ($request->export == 0) {
            $distribution_stock_query = DistributionStock::with(
                'item:id,item_name,item_code',
                'itemUom:id,name,code',
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'distribution'
            )->where('distribution_id', $distribution_id);

            if ($request->date) {
                $distribution_stock_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
            }

            if ($request->start_date && $request->end_date) {
                $from = date($request->start_date.' 00:00:00');
                $to = date($request->end_date.' 23:59:59');
                $distribution_stock_query->whereBetween('created_at', [$from, $to]);
                //dd($distribution_stock_query->get(),$distribution_stock_query->toSql(), $distribution_stock_query->getBindings());
            }

            if (count($all_salesman)) {
                $distribution_stock_query->whereIn('salesman_id', $all_salesman);
            }

            if ($request->salesman_name) {
                $salesman_name = $request->salesman_name;
                $exploded_name = explode(" ", $salesman_name);
                if (count($exploded_name) < 2) {
                    $distribution_stock_query->whereHas('salesman', function ($q) use ($salesman_name) {
                        $q->where('firstname', 'like', '%' . $salesman_name . '%')
                            ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                    });
                } else {
                    foreach ($exploded_name as $n) {
                        $distribution_stock_query->whereHas('salesman', function ($q) use ($n) {
                            $q->where('firstname', 'like', '%' . $n . '%')
                                ->orWhere('lastname', 'like', '%' . $n . '%');
                        });
                    }
                }
            }

            if ($request->customer_name) {
                $customer_name = $request->customer_name;
                $exploded_name = explode(" ", $customer_name);
                if (count($exploded_name) < 2) {
                    $distribution_stock_query->whereHas('customer', function ($q) use ($customer_name) {
                        $q->where('firstname', 'like', '%' . $customer_name . '%')
                            ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                    });
                } else {
                    foreach ($exploded_name as $n) {
                        $distribution_stock_query->whereHas('customer', function ($q) use ($n) {
                            $q->where('firstname', 'like', '%' . $n . '%')
                                ->orWhere('lastname', 'like', '%' . $n . '%');
                        });
                    }
                }
            }

            if ($request->customer_code) {
                $code = $request->customer_code;
                $distribution_stock_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                    $q->where('customer_code', $code);
                });
            }

            if ($request->item_name) {
                $item_name = $request->item_name;
                $distribution_stock_query->whereHas('item', function ($q) use ($item_name) {
                    $q->where('item_name', $item_name);
                });
            }

            if ($request->item_code) {
                $code = $request->item_code;
                $distribution_stock_query->whereHas('item', function ($q) use ($code) {
                    $q->where('item_code', $code);
                });
            }

            if ($request->all) {
                $distribution_stock = $distribution_stock_query->orderBy('id', 'desc')->get();
            } else {
                if ($request->today && !$request->date) {
                    $distribution_stock_query->whereDate('created_at', date('Y-m-d'));
                }
                $distribution_stock = $distribution_stock_query->orderBy('id', 'desc')->get();
            }

            $distribution_stock_array = array();
            if (is_object($distribution_stock)) {
                foreach ($distribution_stock as $key => $distribution_stock1) {
                    $distribution_stock_array[] = $distribution_stock[$key];
                }
            }

            $data_array = array();
            $page = (isset($request->page)) ? $request->page : '';
            $limit = (isset($request->page_size)) ? $request->page_size : '';
            $pagination = array();
            if ($page != '' && $limit != '') {
                $offset = ($page - 1) * $limit;
                for ($i = 0; $i < $limit; $i++) {
                    if (isset($distribution_stock_array[$offset])) {
                        $data_array[] = $distribution_stock_array[$offset];
                    }
                    $offset++;
                }

                $pagination['total_pages'] = ceil(count($distribution_stock_array) / $limit);
                $pagination['current_page'] = (int)$page;
                $pagination['total_records'] = count($distribution_stock_array);
            } else {
                $data_array = $distribution_stock_array;
            }

            return prepareResult(true, $data_array, [], "Distribution Expire Items listing", $this->success, $pagination);
        }else {
            $columns = [
                "Date",
                "Merchandiser Name",
                "Merchandiser Code",
                "Customer Code",
                "Customer Name",
                "Item Code",
                "Item Name",
                "Capacity",
                "Good Saleable",
                "Is Out of Stock",
                "MSL Item"
            ];
            $final_array = new Collection();
            $distribution_stock_query = DistributionStock::with(
                'item:id,item_name,item_code',
                'itemUom:id,name,code',
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname', 
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'distribution'
            )->where('distribution_id', $distribution_id);
            if ($request->date) {
                $distribution_stock_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
            }

            if ($request->start_date && $request->end_date) {
                $from = date($request->start_date.' 00:00:00');
                $to = date($request->end_date.' 23:59:59');
                $distribution_stock_query->whereBetween('created_at', [$from, $to]);
                //dd($distribution_stock_query->get(),$distribution_stock_query->toSql(), $distribution_stock_query->getBindings());
            }
            
            if (count($all_salesman)) {
                $distribution_stock_query->whereIn('salesman_id', $all_salesman);
            }

            if ($request->salesman_name) {
                $salesman_name = $request->salesman_name;
                $exploded_name = explode(" ", $salesman_name);
                if (count($exploded_name) < 2) {
                    $distribution_stock_query->whereHas('salesman', function ($q) use ($salesman_name) {
                        $q->where('firstname', 'like', '%' . $salesman_name . '%')
                            ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                    });
                } else {
                    foreach ($exploded_name as $n) {
                        $distribution_stock_query->whereHas('salesman', function ($q) use ($n) {
                            $q->where('firstname', 'like', '%' . $n . '%')
                                ->orWhere('lastname', 'like', '%' . $n . '%');
                        });
                    }
                }
            }

            if ($request->customer_name) {
                $customer_name = $request->customer_name;
                $exploded_name = explode(" ", $customer_name);
                if (count($exploded_name) < 2) {
                    $distribution_stock_query->whereHas('customer', function ($q) use ($customer_name) {
                        $q->where('firstname', 'like', '%' . $customer_name . '%')
                            ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                    });
                } else {
                    foreach ($exploded_name as $n) {
                        $distribution_stock_query->whereHas('customer', function ($q) use ($n) {
                            $q->where('firstname', 'like', '%' . $n . '%')
                                ->orWhere('lastname', 'like', '%' . $n . '%');
                        });
                    }
                }
            }

            if ($request->customer_code) {
                $code = $request->customer_code;
                $distribution_stock_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                    $q->where('customer_code', $code);
                });
            }

            if ($request->item_name) {
                $item_name = $request->item_name;
                $distribution_stock_query->whereHas('item', function ($q) use ($item_name) {
                    $q->where('item_name', $item_name);
                });
            }

            if ($request->item_code) {
                $code = $request->item_code;
                $distribution_stock_query->whereHas('item', function ($q) use ($code) {
                    $q->where('item_code', $code);
                });
            }

            if ($request->all) {
                $distribution_stock = $distribution_stock_query->orderBy('id', 'desc')->get();
            } else {
                if ($request->today && !$request->date) {
                    $distribution_stock_query->whereDate('created_at', date('Y-m-d'));
                }
                $distribution_stock = $distribution_stock_query->orderBy('id', 'desc')->get();
            }

            foreach ($distribution_stock as $key => $distributionStock) {
                $portfolioManagementItem = PortfolioManagementItem::where('item_id', $distributionStock->item->id)->first();
                $portfolioStatus = 'No';
                if ($portfolioManagementItem) {
                    $portfolioStatus = 'Yes';
                }
                $salesman_infos           = DB::table('salesman_infos')->where('user_id', $distributionStock->salesman_id ?? 0)->first();
                $salesman_name      = User::where('id', $salesman_infos->user_id ?? 0)->where('usertype', 3)->first();
                $salesman_firstname       = $salesman_name->firstname ?? '';
                $salesman_lastname        =  $salesman_name->lastname ?? '';
                $salesman_name            = $salesman_firstname.' '.$salesman_lastname;

                $customer_infos     = CustomerInfo::where('user_id', $distributionStock->customer_id)->first();
                $customer_name      = User::where('id', $customer_infos->user_id ?? 0)->where('usertype', 2)->first();

                $customer_firstname       = $customer_name->firstname ?? '';
                $customer_lastname        =  $customer_name->lastname ?? '';
                $customer_name            = $customer_firstname.' '.$customer_lastname;

                if ($distributionStock->is_out_of_stock==1) {
                    $is_out_of_stock = 'Yes';
                }else{
                    $is_out_of_stock = 'No';
                }

                $final_array->push((object) [
                    "date"              => date('Y-m-d', strtotime($distributionStock->created_at)),
                    "Merchandiser Name" => $salesman_name,
                    "Merchandiser Code" => $salesman_infos->salesman_code,
                    "Customer Code"     => $customer_infos->customer_code,
                    "Customer Name"     => $customer_name,
                    "Item Code"         => $distributionStock->item->item_code,
                    "Item Name"         => $distributionStock->item->item_name,
                    "Capacity"          => $distributionStock->capacity,
                    "Good Saleable"     => $distributionStock->capacity,
                    "Is Out of Stock"   => $is_out_of_stock,
                    "MSL Item"  => $portfolioStatus,
                ]);
            }
        }

        $file_name = $request->user()->organisation_id . time() . '_stock_item_list.' . $request->export_type;
        Excel::store(new StockItemListExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

        $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
        return prepareResult(true, $result, [], "Data successfully exported", $this->success);

        
    }

    public function distributionSurveyList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->distribution_id) {
            return prepareResult(false, [], [], "Error while validating survey", $this->unprocessableEntity);
        }

        $distribution_id = $request->distribution_id;

        $survey_query = Survey::with(
            'distribution',
            'distribution.distributionModelStock',
            'surveyType:id,survey_name'
        )
            ->where('distribution_id', $distribution_id);
        if ($request->date) {
            $survey_query->where('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->end_date) {
            $survey_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }

        if ($request->start_date) {
            $survey_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->name) {
            $survey_query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->all) {
            $survey = $survey_query->orderBy('id', 'desc')->get();
        } else {
            if ($request->today) {
                $survey_query->whereDate('created_at', date('Y-m-d'));
            }
            $survey = $survey_query->orderBy('id', 'desc')->get();
        }

        $survey_array = array();
        if (is_object($survey)) {
            foreach ($survey as $key => $survey1) {
                $survey_array[] = $survey[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($survey_array[$offset])) {
                    $data_array[] = $survey_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($survey_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($survey_array);
        } else {
            $data_array = $survey_array;
        }

        return prepareResult(true, $data_array, [], "Survey Distribution listing", $this->success, $pagination);
    }

    public function storeAllInOneItems(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }


        $input = $request->json()->all();
        $validate = $this->validations($input, "allInOne");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution stock.", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            foreach ($request->items as $item) {

                $distribution_stock = new DistributionStock;
                $distribution_stock->distribution_id = $request->distribution_id;
                $distribution_stock->customer_id = $request->customer_id;
                $distribution_stock->salesman_id = $request->salesman_id;
                $distribution_stock->trip_id = $request->trip_id;
                $distribution_stock->item_id = $item['item_id'];
                $distribution_stock->item_uom_id = $item['item_uom_id'];
                $distribution_stock->stock = $item['stock'];
                $distribution_stock->capacity = $item['capacity'];
                $distribution_stock->is_out_of_stock = $item['is_out_of_stock'] ? 1 : 0;
                $distribution_stock->save();


                //self::createMSL($distribution_stock);
                
                ///dd('test');
                if ($distribution_stock->capacity) {
                    $customer_id = $distribution_stock->customer_id;
                    $model_stock = DistributionModelStockDetails::with('distributionModelStock')
                        ->where('item_id', $distribution_stock->item_id)
                        ->where('item_uom_id', $item['item_uom_id'])
                        ->whereHas('distributionModelStock', function ($q) use ($customer_id) {
                            $q->where('customer_id', $customer_id);
                        })
                        ->first();

                    $model_stock->capacity = $distribution_stock->capacity;
                    $model_stock->save();
                }

                if (is_array($item['expiry']) && sizeof($item['expiry']) >= 1) {
                    foreach ($item['expiry'] as $expiry) {
                        $distribution_expire_items = new DistributionExpireItem;
                        $distribution_expire_items->distribution_id = $request->distribution_id;
                        $distribution_expire_items->customer_id = $request->customer_id;
                        $distribution_expire_items->salesman_id = $request->salesman_id;
                        $distribution_expire_items->item_id = $expiry['item_id'];
                        $distribution_expire_items->item_uom_id = $expiry['item_uom_id'];
                        $distribution_expire_items->qty = $expiry['qty'];
                        $distribution_expire_items->expiry_date = $expiry['expiry_date'];
                        $distribution_expire_items->save();
                    }
                }

                if (is_array($item['damage']) && sizeof($item['damage']) >= 1) {
                    foreach ($item['damage'] as $damage) {
                        $distribution_damage_items = new DistributionDamageItem;
                        $distribution_damage_items->distribution_id = $request->distribution_id;
                        $distribution_damage_items->customer_id = $request->customer_id;
                        $distribution_damage_items->salesman_id = $request->salesman_id;
                        $distribution_damage_items->item_id = $damage['item_id'];
                        $distribution_damage_items->item_uom_id = $damage['item_uom_id'];
                        $distribution_damage_items->damage_item_qty = $damage['damage_item_qty'];
                        $distribution_damage_items->expire_item_qty = $damage['expire_item_qty'];
                        $distribution_damage_items->saleable_item_qty = $damage['saleable_item_qty'];
                        $distribution_damage_items->save();
                    }
                }

                if (is_array($item['share_of_shelf']) && sizeof($item['share_of_shelf']) >= 1) {
                    foreach ($item['share_of_shelf'] as $sos) {
                        $share_of_shelf = new ShareOfShelf;
                        $share_of_shelf->distribution_id = $request->distribution_id;
                        $share_of_shelf->customer_id = $request->customer_id;
                        $share_of_shelf->salesman_id = $request->salesman_id;
                        $share_of_shelf->item_id = $sos['item_id'];
                        $share_of_shelf->item_uom_id = $sos['item_uom_id'];
                        $share_of_shelf->total_number_of_facing = $sos['total_number_of_facing'];
                        $share_of_shelf->actual_number_of_facing = $sos['actual_number_of_facing'];
                        $share_of_shelf->score = $sos['score'];
                        $share_of_shelf->save();

                        // $distribution_model_stock = DistributionModelStockDetails::where('distribution_id', $request->distribution_id)
                        //     ->where('item_id', $sos['item_id'])
                        //     ->where('item_uom_id', $sos['item_uom_id'])
                        //     ->first();

                        // if (is_object($distribution_model_stock)) {
                        //     if ($distribution_model_stock->total_number_of_facing == 0) {
                        //         $distribution_model_stock->total_number_of_facing = $share_of_shelf->total_number_of_facing;
                        //         $distribution_model_stock->save();
                        //     }
                        // }
                    }
                }
            }

                $msl_item_perform     = DistributionStock::where(['customer_id'=>$request->customer_id])->groupBy('item_id')->whereDate('created_at', date('Y-m-d', strtotime($distribution_stock->created_at)))->get();
                $msl_item_perform     = count($msl_item_perform);
                //dd($msl_item_perform);

                $merchandiser_msls  = DB::table('merchandiser_msls')->whereDate('date', date('Y-m-d', strtotime($distribution_stock->created_at)))->where(['customer_id' => $request->customer_id])->first();

                if ($merchandiser_msls) {
                    //dd('test');
                    $total_msl_item     = $merchandiser_msls->total_msl_item;
                    $devide             = $total_msl_item == 0 ? 1 : $total_msl_item;
                    $percentage         = round(($msl_item_perform/$devide)*100);
                    $salesman_infos     = DB::table('salesman_infos')->where('id', $request->salesman_id ?? 0)->first();
                    $salesman_name      = User::where('id', $salesman_infos->user_id ?? 0)->where('usertype', 3)->first();
                    $salesman_firstname = $salesman_name->firstname ?? '';
                    $salesman_lastname  =  $salesman_name->lastname ?? '';
                    $salesman_name      = $salesman_firstname.' '.$salesman_lastname;

                    DB::table('merchandiser_msls')->where('id', $merchandiser_msls->id)->update(
                        [
                            'msl_item_perform'  => $msl_item_perform,
                            'msl_percentage'    => $percentage,
                            'merchandiser_id'   => $request->salesman_id,
                            'merchandiser_name' => $salesman_name ?? '',
                            'created_at'        => NOW(),
                            'updated_at'        => NOW()
                        ]
                    );
                }


            \DB::commit();

            return prepareResult(true, $distribution_stock, [], "Distribution stock added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage().' '.$exception->getLine(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage().' '.$exception->getLine(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    // public function imports(Request $request)
    // {
    //     if (!$this->isAuthorized) {
    //         return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'distribution_file' => 'required|mimes:xlsx,xls,csv'
    //     ]);
    //     pre($validator->fails());
    //     if ($validator->fails()) {
    //         $error = $validator->messages()->first();
    //         return prepareResult(false, [], $error, "Failed to validate distribution import", $this->unauthorized);
    //     }

    //     $errors = array();
    //     try {
    //         $file = request()->file('distribution_file')->store('import');
    //         $import = new DistributionImport($request->skipduplicate);
    //         $import->import($file);
    //         if (count($import->failures()) > 16) {
    //             $errors[] = $import->failures();
    //         }
    //     } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
    //         $failures = $e->failures();
    //         foreach ($failures as $failure) {
    //             info($failure->row());
    //             info($failure->attribute());
    //             $failure->row(); // row that went wrong
    //             $failure->attribute(); // either heading key (if using heading row concern) or column index
    //             $failure->errors(); // Actual error messages from Laravel validator
    //             $failure->values(); // The values of the row that has failed.
    //             $errors[] = $failure->errors();
    //         }

    //         return prepareResult(true, [], $errors, "Failed to validate distribution import", $this->success);
    //     }
    //     return prepareResult(true, [], $errors, "Distribution successfully imported", $this->success);
    // }

    public function storePostImage(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "addPostImage");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating distribution", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {

            $dpi = new DistributionPostImage;
            $dpi->distribution_id = $request->distribution_id;
            $dpi->salesman_id = $request->salesman_id;
            $dpi->customer_id = $request->customer_id;

            if ($request->image1) {
                $saveImage1 = saveImage("dpi_" . Str::slug(rand(100000000000, 99999999999999)), $request->image1, "distribution-post-image");
                $dpi->image1 = $saveImage1;
            }
            if ($request->image2) {
                $saveImage2 = saveImage("dpi_" . Str::slug(rand(100000000000, 99999999999999)), $request->image2, "distribution-post-image");
                $dpi->image2 = $saveImage2;
            }
            if ($request->image3) {
                $saveImage3 = saveImage("dpi_" . Str::slug(rand(100000000000, 99999999999999)), $request->image3, "distribution-post-image");
                $dpi->image3 = $saveImage3;
            }
            if ($request->image4) {
                $saveImage4 = saveImage("dpi_" . Str::slug(rand(100000000000, 99999999999999)), $request->image3, "distribution-post-image");
                $dpi->image4 = $saveImage4;
            }

            // $dpi->image2 = $this->saveImage($request->image2);
            // $dpi->image3 = $this->saveImage($request->image3);
            // $dpi->image4 = $this->saveImage($request->image4);
            $dpi->save();

            \DB::commit();

            return prepareResult(true, $dpi, [], "Distribution post image added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexPostImage(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$request->distribution_id) {
            return prepareResult(false, [], [], "Error while validating distribution post image", $this->unprocessableEntity);
        }

        $distribution_id = $request->distribution_id;

        $all_salesman = getSalesman();

        $distributions_query = DistributionPostImage::with(
            'distribution:id,name',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code'
        )
            ->where('distribution_id', $distribution_id);
        if ($request->date) {
            $distributions_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }
        if (count($all_salesman)) {
            $distributions_query->whereIn('salesman_id', $all_salesman);
        }
        if ($request->salesman_name) {
            $salesman_name = $request->salesman_name;
            $exploded_name = explode(" ", $salesman_name);
            if (count($exploded_name) < 2) {
                $distributions_query->whereHas('salesman', function ($q) use ($salesman_name) {
                    $q->where('firstname', 'like', '%' . $salesman_name . '%')
                        ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distributions_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $distributions_query->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $distributions_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $code = $request->customer_code;
            $distributions_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                $q->where('customer_code', $code);
            });
        }

        $distributions = $distributions_query->orderBy('id', 'desc')->get();

        $distributions_array = array();
        if (is_object($distributions)) {
            foreach ($distributions as $key => $distributions1) {
                $distributions_array[] = $distributions[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($distributions_array[$offset])) {
                    $data_array[] = $distributions_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($distributions_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($distributions_array);
        } else {
            $data_array = $distributions_array;
        }

        return prepareResult(true, $data_array, [], "Distribution post image listing", $this->success, $pagination);
    }

    // private function saveImage($image)
    // {
    //     if ($image) {
    //
    //         $destinationPath = 'uploads/distribution-post-image/';
    //         $image_name = \Str::slug(rand(100000000000, 99999999999999));
    //         $image = $image;
    //         $getBaseType = explode(',', $image);
    //         $getExt = explode(';', $image);
    //         $image = str_replace($getBaseType[0] . ',', '', $image);
    //         $image = str_replace(' ', '+', $image);
    //         $fileName = $image_name . '-' . time() . '.' . basename($getExt[0]);
    //         \File::put($destinationPath . $fileName, base64_decode($image));
    //         $url = URL('/') . '/' . $destinationPath . $fileName;
    //         return $url;
    //     } else {
    //         return NULL;
    //     }
    // }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        // {"Name": "Name","Start date": "Start date","End date": "End date","Height": "Height","Width": "Width","Depth": "Depth","Status": "Status","Customer code": "Customer code","Item code": "Item code","Item uom": "Item uom","Capacity": "Capacity","Total no of facing": "Total no of facing"}

        $mappingarray = array("Name", "Start date", "End date", "Height", "Width", "Depth", "Status", "Customer code", "Item", "Uom", "Capacity", "Total no of facing");

        return prepareResult(true, $mappingarray, [], "Distribution Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'distribution_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate distribution import", $this->unauthorized);
        }

        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('distribution_file')->store('import');
            $filename = storage_path("app/" . $file);
            $fp = fopen($filename, "r");
            $content = fread($fp, filesize($filename));
            $lines = explode("\n", $content);
            $heading_array_line = isset($lines[0]) ? $lines[0] : '';
            $heading_array = explode(",", trim($heading_array_line));
            fclose($fp);

            if (!$heading_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }

            if (!$map_key_value_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }

            $import = new DistributionImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            $succussrecords = 0;
            $successfileids = 0;

            if ($import->successAllRecords()) {
                $succussrecords = count($import->successAllRecords());
                $data = json_encode($import->successAllRecords());
                $fileName = time() . '_datafile.txt';
                \File::put(storage_path() . '/app/tempimport/' . $fileName, $data);

                $importtempfiles = new ImportTempFile;
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
                        //print_r($failure->errors());

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
                            //$errror_array['errormessage'][] = array("There was an error on row ".$failure->row().". ".$error_msg);
                            //$errror_array['errorresult'][] = $failure->values();
                            $error_result = array();
                            $error_row_loop = 0;
                            foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
                                $error_result[$map_key_value_array_value] = isset($failure->values()[$error_row_loop]) ? $failure->values()[$error_row_loop] : '';
                                $error_row_loop++;
                            }
                            $errror_array[] = array(
                                'errormessage' => "There was an error on row " . $failure->row() . ". " . $error_msg,
                                'errorresult' => $error_result, //$failure->values(),
                                //'attribute' => $failure->attribute(),//$failure->values(),
                                //'error_result' => $error_result,
                                //'map_key_value_array' => $map_key_value_array,
                            );
                        }
                    }
                }
                $errorrecords = count($errror_array);
            }

            $errors = $errror_array;
            $result['successrecordscount'] = $succussrecords;
            $result['errorrcount'] = $errorrecords;
            $result['successfileids'] = $successfileids;
            $result['skipduplicate'] = $request->skipduplicate;
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
        return prepareResult(true, $result, $errors, "distribution successfully imported", $this->success);
    }

    public function finalimport(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        if ($importtempfile) {

            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            $skipduplicate = $request->skipduplicate;
            if ($finaldata) :
                foreach ($finaldata as $row) :
                    $current_organisation_id = request()->user()->organisation_id;

                    $customer = CustomerInfo::where('customer_code', $row[7])->first();

                    $s_date = Carbon::parse($row[1])->format('Y-m-d');
                    $d_date = Carbon::parse($row[2])->format('Y-m-d');

                    $distribution = Distribution::where('name', trim($row[0]))
                        ->where('start_date', $s_date)
                        ->where('end_date', $d_date)
                        ->first();

                    $item       = Item::where('item_code', $row[8])->first();
                    $item_uom   = ItemUom::where('name', $row[9])->first();

                    if ((!isset($item->id) && $item->id) && (!isset($item_uom->id) && $item_uom->id) && (!$customer->id) && $customer->id) {
                        continue;
                    }

                    if ($skipduplicate) {
                        if (is_object($distribution)) {
                            $distribution_customers = DistributionCustomer::where('distribution_id', $distribution->id)
                                ->where('customer_id', $customer->user_id)
                                ->first();

                            // not find distribustion customer create new
                            if (!is_object($distribution_customers)) {
                                $distribution_customers = new DistributionCustomer;
                            }

                            if (isset($distribution_customers->id) && $distribution_customers->id) {
                                $distribution_model_stock_details = DistributionModelStockDetails::where('distribution_id', $distribution->id)
                                    ->where('item_id', $row[8])
                                    ->where('item_uom_id', $row[9])
                                    ->first();
                                if (is_object($distribution_model_stock_details)) {
                                    continue;
                                } else {
                                    $distribution_model_stock_details = new DistributionModelStockDetails;
                                }
                            } else {
                                $distribution_model_stock_details = new DistributionModelStockDetails;
                            }
                        } else {
                            $distribution = new Distribution;
                            $distribution_customers = new DistributionCustomer;
                            $distribution_model_stock_details = new DistributionModelStockDetails;
                        }

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
                        if ($customer) {

                            $distribution_customers->distribution_id = $distribution->id;
                            $distribution_customers->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                            $distribution_customers->save();

                            $distribution_model_stocks = DistributionModelStock::where('distribution_id', $distribution->id)
                                ->where('customer_id', $customer->user_id)
                                ->first();

                            if (!is_object($distribution_model_stocks)) {
                                if (!isset($distribution_model_stocks->id)) {
                                    $distribution_model_stocks = new DistributionModelStock;
                                    $distribution_model_stocks->distribution_id = $distribution->id;
                                    $distribution_model_stocks->customer_id = $customer->user_id;
                                    $distribution_model_stocks->save();
                                    $distribution_model_stock_details = new DistributionModelStockDetails;
                                }
                            } else {
                                $distribution_model_stock_details = new DistributionModelStockDetails;
                            }

                            $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                            $distribution_model_stock_details->distribution_id = $distribution->id;
                            $distribution_model_stock_details->item_id = (is_object($item)) ? $item->id : null;
                            $distribution_model_stock_details->item_uom_id = (is_object($item_uom)) ? $item_uom->id : null;
                            $distribution_model_stock_details->capacity = $row[10];
                            $distribution_model_stock_details->total_number_of_facing = $row[11];
                            $distribution_model_stock_details->save();
                        }
                    } else {
                        if (is_object($distribution) && is_object($customer)) {
                            $distribution_customers = DistributionCustomer::where('distribution_id', $distribution->id)
                                ->where('customer_id', $customer->user_id)
                                ->first();

                            // not find distribustion customer create new
                            if (!is_object($distribution_customers)) {
                                $distribution_customers = new DistributionCustomer;
                            }

                            if (isset($distribution_customers->id) && $distribution_customers->id) {
                                $distribution_model_stock_details = DistributionModelStockDetails::where('distribution_id', $distribution->id)
                                    ->where('item_id', $row[8])
                                    ->where('item_uom_id', $row[9])
                                    ->first();
                                if (!is_object($distribution_model_stock_details)) {
                                    $distribution_model_stock_details = new DistributionModelStockDetails;
                                }
                            } else {
                                $distribution_model_stock_details = new DistributionModelStockDetails;
                            }
                        } else {
                            $distribution = new Distribution;
                            $distribution_customers = new DistributionCustomer;
                            $distribution_model_stock_details = new DistributionModelStockDetails;
                        }

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
                        if ($customer) {

                            $distribution_customers->distribution_id = $distribution->id;
                            $distribution_customers->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                            $distribution_customers->save();

                            $distribution_model_stocks = DistributionModelStock::where('distribution_id', $distribution->id)
                                ->where('customer_id', $customer->user_id)
                                ->first();

                            if (!is_object($distribution_model_stocks)) {
                                if (!isset($distribution_model_stocks->id)) {
                                    $distribution_model_stocks = new DistributionModelStock;
                                    $distribution_model_stocks->distribution_id = $distribution->id;
                                    $distribution_model_stocks->customer_id = $customer->user_id;
                                    $distribution_model_stocks->save();
                                    $distribution_model_stock_details = new DistributionModelStockDetails;
                                }
                            } else {
                                $distribution_model_stock_details = new DistributionModelStockDetails;
                            }

                            $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                            $distribution_model_stock_details->distribution_id = $distribution->id;
                            $distribution_model_stock_details->item_id = (is_object($item)) ? $item->id : null;
                            $distribution_model_stock_details->item_uom_id = (is_object($item_uom)) ? $item_uom->id : null;
                            $distribution_model_stock_details->capacity = $row[10];
                            $distribution_model_stock_details->total_number_of_facing = $row[11];
                            $distribution_model_stock_details->save();
                        }
                    }


                // if (is_object($distribution)) {
                //     $distribution->name = $row[0];
                //     $distribution->start_date  = date('Y-m-d', strtotime($row[1]));
                //     $distribution->end_date = date('Y-m-d', strtotime($row[2]));
                //     $distribution->height = $row[3];
                //     $distribution->width = $row[4];
                //     $distribution->depth = $row[5];
                //     $distribution->status = $row[6];
                //     $distribution->save();

                //     $distribution_customer = DistributionCustomer::where('distribution_id', $distribution->id)
                //         ->where('customer_id', $customer->user_id)
                //         ->first();
                //     if (!is_object($distribution_customer)) {
                //         $distribution_customer = new DistributionCustomer;
                //     }
                //     $distribution_customer->distribution_id = $distribution->id;
                //     $distribution_customer->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                //     $distribution_customer->save();

                //     $distribution_model_stocks = DistributionModelStock::where('customer_id', $customer->user_id)
                //         ->where('distribution_id', $distribution->id)
                //         ->first();
                //     if (!is_object($distribution_model_stocks)) {
                //         $distribution_model_stocks = new DistributionModelStock;
                //     }

                //     $distribution_model_stocks->customer_id = (is_object($customer)) ? $customer->user_id : NULL;
                //     $distribution_model_stocks->distribution_id = $distribution->id;
                //     $distribution_model_stocks->save();

                //     $distribution_model_stock_detail = DistributionModelStockDetails::where('distribution_model_stock_id', $distribution_model_stocks->id)
                //         ->where('distribution_id', $distribution->id)
                //         ->where('item_id', $item->id)
                //         ->where('item_uom_id', $item_uom->id)
                //         ->first();

                //     if (!is_object($distribution_model_stock_detail)) {
                //         $distribution_model_stock_detail = new DistributionModelStockDetails;
                //     }

                //     $distribution_model_stock_detail->distribution_model_stock_id = $distribution_model_stocks->id;
                //     $distribution_model_stock_detail->distribution_id = $distribution->id;
                //     $distribution_model_stock_detail->item_id = (is_object($item)) ? $item->id : 0;
                //     $distribution_model_stock_detail->item_uom_id = (is_object($item_uom)) ? $item_uom->id : 0;
                //     $distribution_model_stock_detail->capacity = $row[10];
                //     $distribution_model_stock_detail->save();
                // } else {

                //     if (!is_object($customer)) {
                //         return prepareResult(false, [], [], "customer not exists", $this->unauthorized);
                //     }

                //     $distribution = new Distribution;
                //     $distribution->organisation_id = $current_organisation_id;
                //     $distribution->name = $row[0];
                //     $distribution->start_date  = date('Y-m-d', strtotime($row[1]));
                //     $distribution->end_date = date('Y-m-d', strtotime($row[2]));
                //     $distribution->height = $row[3];
                //     $distribution->width = $row[4];
                //     $distribution->depth = $row[5];
                //     $distribution->status = $row[6];
                //     $distribution->save();

                //     $distribution_customer = new DistributionCustomer;
                //     $distribution_customer->distribution_id = $distribution->id;
                //     $distribution_customer->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                //     $distribution_customer->save();

                //     $distribution_model_stocks = DistributionModelStock::where('customer_id', $customer->user_id)
                //         ->where('distribution_id', $distribution->id)
                //         ->first();

                //     if (!is_object($distribution_model_stocks)) {
                //         $distribution_model_stocks = new DistributionModelStock;
                //     }

                //     $distribution_model_stocks->customer_id = (is_object($customer)) ? $customer->user_id : NULL;
                //     $distribution_model_stocks->distribution_id = $distribution->id;
                //     $distribution_model_stocks->save();

                //     $item = Item::where('item_code', $row[8])->first();
                //     $item_uom = ItemUom::where('name', $row[9])->first();

                //     $distribution_model_stock_detail = DistributionModelStockDetails::where('distribution_model_stock_id', $distribution_model_stocks->id)
                //         ->where('distribution_id', $distribution->id)
                //         ->where('item_id', $item->id)
                //         ->where('item_uom_id', $item_uom->id)
                //         ->first();

                //     if (!is_object($distribution_model_stock_detail)) {
                //         $distribution_model_stock_detail = new DistributionModelStockDetails;
                //     }
                //     $distribution_model_stock_detail->distribution_model_stock_id = $distribution_model_stocks->id;
                //     $distribution_model_stock_detail->distribution_id = $distribution->id;
                //     $distribution_model_stock_detail->item_id = (is_object($item)) ? $item->id : 0;
                //     $distribution_model_stock_detail->item_uom_id = (is_object($item_uom)) ? $item_uom->id : 0;
                //     $distribution_model_stock_detail->capacity = $row[10];
                //     $distribution_model_stock_detail->save();
                // }
                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                \DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Distribution successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function shelfDisplayImport(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'shelf_display_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate shelf display import", $this->unauthorized);
        }
        //dd(request()->file('portfolio_management_file'));
        $errors = array();
        try {
            $file = request()->file('shelf_display_file')->store('import');
            $import = new ShelfDisplayImport();
            $import->import($file);
            $errors[] = $import->failures();
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                info($failure->row());
                info($failure->attribute());
                $failure->row(); // row that went wrong
                $failure->attribute(); // either heading key (if using heading row concern) or column index
                $failure->errors(); // Actual error messages from Laravel validator
                $failure->values(); // The values of the row that has failed.
                $errors[] = $failure->errors();
            }

            return prepareResult(true, [], $errors, "Failed to validate bank import", $this->success);
        }

        //Excel::import(new VendorImport, request()->file('vendor_file'));
        return prepareResult(true, [], $errors, "Shelf display successfully imported", $this->success);
    }

    public static function createMSL($distribution_stock){
        $portfolio_managements = PortfolioManagement::where('organisation_id', auth()->user()->organisation_id)->get();
        foreach ($portfolio_managements as $key => $pm) {

            $customer_infos = CustomerInfo::where('customer_code', $pm->code)->first();
            //dd($customer_infos);
            if ($customer_infos) {
                $merchandiser = DB::table('merchandiser_msls')->where(['date'=>date('Y-m-d', strtotime($distribution_stock->created_at)),'customer_code'=>$customer_infos->customer_code,'customer_id'=>$customer_infos->user_id])->first();

                $total_msl_item     = PortfolioManagementItem::where(['customer_id'=>$customer_infos->user_id])->groupBy('item_id')->get();

                $total_msl_item     = count($total_msl_item);
                $msl_item_perform = 0;
                if ($total_msl_item==0) {
                    $msl_item_perform = 0;
                }else{
                    $msl_item_perform   = DistributionStock::where(['customer_id'=>$customer_infos->user_id,'is_msl'=>0, 'is_out_of_stock'=>0])->whereDate('created_at',date('Y-m-d', strtotime($distribution_stock->created_at)))->count();
                }

                $distribution_stock1 = DistributionStock::where(['customer_id'=>$customer_infos->user_id, 'is_msl'=>0, 'is_out_of_stock'=>0])->whereDate('created_at',date('Y-m-d', strtotime($distribution_stock->created_at)))->first();



                if(is_null($distribution_stock1) || is_null($distribution_stock1->salesman)){
                    $customer_merchandisers = DB::table('customer_merchandisers')->where('customer_id', $customer_infos->user_id)->first();
                    $salesman_infos     = DB::table('salesman_infos')->where('id', $customer_merchandisers->merchandiser_id ?? 0)->first();

                    $salesman_name      = User::where('id', $salesman_infos->user_id ?? 0)->where('usertype', 3)->first();
                    $salesman_id        = $salesman_infos->id ?? 0; 
                    $salesman_firstname = $salesman_name->firstname ?? '';
                    $salesman_lastname  =  $salesman_name->lastname ?? '';
                    $salesman_name      = $salesman_firstname.' '.$salesman_lastname;
                }else{
                    $salesman_id        = $distribution_stock1->salesman->id ?? 0; 
                    $salesman_firstname = $distribution_stock1->salesman->firstname ?? '';
                    $salesman_lastname  = $distribution_stock1->salesman->lastname ?? '';
                    $salesman_name      = $salesman_firstname.' '.$salesman_lastname;
                }

                $devide = $total_msl_item == 0 ? 1 : $total_msl_item;
                $percentage = round(($msl_item_perform/$devide)*100);

                if(is_null($merchandiser)){
                    DB::table('merchandiser_msls')->insert(
                        [
                            'date'              => date('Y-m-d', strtotime($distribution_stock->created_at)),
                            'customer_code'     => $customer_infos->customer_code,
                            'customer_id'       => $customer_infos->user_id,
                            'customer_name'     => $customer_infos->user->firstname.' '.$customer_infos->user->lastname,
                            'total_msl_item'    => $total_msl_item,
                            'msl_item_perform'  => $msl_item_perform,
                            'msl_percentage'    => $percentage,
                            'merchandiser_id'   => $salesman_id,
                            'merchandiser_name' => $salesman_name,
                            'created_at'        => NOW(),
                            'updated_at'        => NOW()
                        ]
                    );
                }else{
                    DB::table('merchandiser_msls')->where('id',$merchandiser->id)->update(
                        [
                            'date'              => date('Y-m-d', strtotime($distribution_stock->created_at)),
                            'customer_code'     => $customer_infos->customer_code,
                            'customer_id'       => $customer_infos->user_id,
                            'customer_name'     => $customer_infos->user->firstname.' '.$customer_infos->user->lastname,
                            'total_msl_item'    => $total_msl_item,
                            'msl_item_perform'  => $msl_item_perform,
                            'msl_percentage'    => $percentage,
                            'merchandiser_id'   => $salesman_id,
                            'merchandiser_name' => $salesman_name,
                            'created_at'        => NOW(),
                            'updated_at'        => NOW()
                        ]
                    );
                }
                
                
            }
            
        }
        return true;
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



    public function importShelfCustomerDistributionStock(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        } 

        $validator = Validator::make($request->all(), [
            'shelf_display_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate shelf display import", $this->unauthorized);
        }


        /* foreach($customer_code as $c_code){ 
            $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
                    print_r($customerInfo->user_id);
                    print '<br/>';
        }
        die("comes"); */


        //test 
        //$customer_code2 = [6000000703];

            $fileName = $_FILES["shelf_display_file"]["tmp_name"]; 
            if ($_FILES["shelf_display_file"]["size"] > 0) {
                
                        //6000000703,
                $customer_code = [1549,10292,10354,10399,10473,10490,10504];
                
                foreach($customer_code as $c_code){ 
                     $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
					 $file = fopen($fileName, "r");
					 $header = fgetcsv($file, 10000, ","); 
                    //print_r($customerInfo->user_id);
                    //print '<br/>';
                    while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                        $data = array_combine($header, $column);  
                        $distribution = Distribution::where('name', 'like', '%' . $data['name'] . '%')->first();
                        
                        $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
                    
                            if (is_null($distribution)) {
                                $distribution               = new Distribution;
                                $distribution->name         = $data['name'] ?? null;
                                $distribution->start_date   = $data['start_date'] ?? null;
                                $distribution->end_date     = $data['end_date'] ?? null;
                                $distribution->height     = $data['height'] ?? null;
                                $distribution->width     = $data['width'] ?? null;
                                $distribution->depth     = $data['depth'] ?? null;
                                $distribution->depth     =  strtolower($data['status']) === 'yes' ? 1 : 0;
                                $distribution->save();
                            }
 
                            if($customerInfo)
                            {
                                $ddCustomer = DistributionCustomer::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                                ->first();
                        
                                if (is_null($ddCustomer)) {
                                    $distribution_customer         = new DistributionCustomer;
                                    $distribution_customer->distribution_id = $distribution->id;
                                    $distribution_customer->customer_id     = $customerInfo->user_id;
                                    $distribution_customer->save();
                        
                                    $distribution_model_stocks  = new DistributionModelStock;
                                    $distribution_model_stocks->organisation_id  = auth()->user()->organisation_id;
                                    $distribution_model_stocks->distribution_id  = $distribution->id;
                                    $distribution_model_stocks->customer_id      = $customerInfo->user_id;
                                    $distribution_model_stocks->save();
                                }else{
                                    $distribution_model_stocks = DistributionModelStock::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                                    ->first();
                                }
                             //dd($distribution_model_stocks);
                                    $item  = Item::where('item_code',$data['item'])->first();
                                    $item_uom = ItemUom::where('name', $data['uom'])->first();
                                    if($item)
                                    {
                                        $distribution_model_stock_details                              = new DistributionModelStockDetails;
                                        $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                                        $distribution_model_stock_details->distribution_id             = $distribution->id;
                                        $distribution_model_stock_details->item_id                     = $item->id;
                                        $distribution_model_stock_details->item_uom_id                 = $item_uom->id; //$item->lower_unit_uom_id;
                                        $distribution_model_stock_details->capacity                    = $data['capacity'];
                                        $distribution_model_stock_details->total_number_of_facing      = $data['no_of_facing'];
                                       // $distribution_model_stock_details->capacity = $data['capacity'];
                                       // $distribution_model_stock_details->rack_id = $data['rack_id'];
                                        $distribution_model_stock_details->save();
                                    }
                            }
                    }
                }
                
                fclose($file);  
                return prepareResult(true, [], [], "Distribution successfully imported", $this->success);
                
            }else{
                   
                return prepareResult(true, [], [], "Failed to imported File Please check File Validation", $this->success);
            }
    }

    public function importShelfCustomerJSONDistributionStock(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        } 

       // dd("ss")
        // $validator = Validator::make($request->all(), [
        //     'shelf_display_file' => 'required|mimes:xlsx,xls,csv,txt'
        // ]);

        // if ($validator->fails()) {
        //     $error = $validator->messages()->first();
        //     return prepareResult(false, [], $error, "Failed to validate shelf display import", $this->unauthorized);
        // }


        /* foreach($customer_code as $c_code){ 
            $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
                    print_r($customerInfo->user_id);
                    print '<br/>';
        }
        die("comes"); */


        //test 
        //$customer_code2 = [6000000703];

                        //6000000703,
                $customer_code = [10292,10354,10399,10473,10490,10504,10524,10616,10641,10642,10655,10680,10694,10722,10732,10738,10786,10788,10798,10868,10912,10921,10934,11043,11090,11269,11310,11354,11362,11392,11402,11433,11441,11455,11486,11502,11623,11653,11660,11679,11802,11832,11839,11852,11864,11967,12068,14124,18598,19040,21031,21307,27642,32210,32380,34646,34771,34786,34951,35017,35821,36053,36228,37508,40633,41896,42863,43011,43424,44701,47466,47479,48575,49057,52071,54017,54515,54880,57147,58243,59262,61569,62614,68135,70715,70940,75199,75473,76375,77306,78632,80143,80147,80628,82053,82935,91622,92444,92723,92788,92979,92992,93348,94356,94397,94538,94692,95105,95157,95160,95171,95173,95237,96035,96191,96298,96734,97153,97154,97185,97206,97213,97219,97224,97252,97258,97265,97270,97273,97278,97289,97295,97308,97322,97332,97361,97362,97405,97407,97421,97438,97443,97459,97470,97492,97494,97497,97536,97584,97600,97638,97639,97662,97692,97745,97785,97846,97859,97889,97911,97932,97935,97944,97962,97980,97981,97997,98781,99054,99067,99084,99592,104896,104918,104919,104920,104967,104968,104988,104989,105045,105087,105113,105649,105718,105803,105809,105861,105922,105926,105928,105932,105941,105966,106251,106299,106389,106990,107515,107625,108373,108430,110687,110770,111051,112431,113442,113903,114192,114220,114255,114541,114557,114702,114725,115086,115280,115394,115682,116098,116163,116397,116499,116624,116899,117108,117475,117993,118197,118589,118778,119063,119064,119101,119702,120065,120244,120403,121008,121395,121464,122171,122172,122380,122824,122864,123468,124049,124157,124990,126542,126641,126821,127158,127182,127184,127367,128626,129533,129713,129722];
                
                foreach($customer_code as $c_code){ 
                     $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
					 
                     foreach ($request->data as $route) {

                        $distribution = Distribution::where('name', 'like', '%' . $route['name'] . '%')->first();
                        
                        $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();

                        $distribution = Distribution::where('name', 'like', '%' . $route['name'] . '%')->first();
                        
                        $customerInfo = CustomerInfo::select('user_id')->where('customer_code',$c_code)->first();
                    
                            if (is_null($distribution)) {
                                $distribution               = new Distribution;
                                $distribution->name         = $route['name'] ?? null;
                                $distribution->start_date   = $route['start_date'] ?? null;
                                $distribution->end_date     = $route['end_date'] ?? null;
                                $distribution->height     = $route['height'] ?? null;
                                $distribution->width     = $route['width'] ?? null;
                                $distribution->depth     = $route['depth'] ?? null;
                                $distribution->depth     =  strtolower($route['status']) === 'yes' ? 1 : 0;
                                $distribution->save();
                            }
 
                            if($customerInfo)
                            {
                                $ddCustomer = DistributionCustomer::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                                ->first();
                        
                                if (is_null($ddCustomer)) {
                                    $distribution_customer         = new DistributionCustomer;
                                    $distribution_customer->distribution_id = $distribution->id;
                                    $distribution_customer->customer_id     = $customerInfo->user_id;
                                    $distribution_customer->save();
                        
                                    $distribution_model_stocks  = new DistributionModelStock;
                                    $distribution_model_stocks->organisation_id  = auth()->user()->organisation_id;
                                    $distribution_model_stocks->distribution_id  = $distribution->id;
                                    $distribution_model_stocks->customer_id      = $customerInfo->user_id;
                                    $distribution_model_stocks->save();
                                }else{
                                    $distribution_model_stocks = DistributionModelStock::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                                    ->first();
                                }
                             //dd($distribution_model_stocks);
                                    $item  = Item::where('item_code',$route['item'])->first();
                                    $item_uom = ItemUom::where('name', $route['uom'])->first();
                                    if($item)
                                    {
                                        $distribution_model_stock_details                              = new DistributionModelStockDetails;
                                        $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                                        $distribution_model_stock_details->distribution_id             = $distribution->id;
                                        $distribution_model_stock_details->item_id                     = $item->id;
                                        $distribution_model_stock_details->item_uom_id                 = $item_uom->id; //$item->lower_unit_uom_id;
                                        $distribution_model_stock_details->capacity                    = $route['capacity'];
                                        $distribution_model_stock_details->total_number_of_facing      = $route['no_of_facing'];
                                       // $distribution_model_stock_details->capacity = $data['capacity'];
                                       // $distribution_model_stock_details->rack_id = $data['rack_id'];
                                        $distribution_model_stock_details->save();
                                    }
                            }
                     }
                     
                
                }
                
                
                return prepareResult(true, [], [], "Distribution successfully imported", $this->success);
                
          
    }
    
}
