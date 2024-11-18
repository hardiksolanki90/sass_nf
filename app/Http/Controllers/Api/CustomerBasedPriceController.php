<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\CustomerBasedPriceImport;
use App\Model\CustomerBasedPricing;
use App\Model\CustomerInfo;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\ItemMainPrice;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CustomerBasedPriceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $now = date('Y-m-d');
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $cbp = CustomerBasedPricing::select(
            'customer_based_pricings.id',
            'customer_based_pricings.uuid',
            'customer_based_pricings.key',
            'customer_based_pricings.start_date',
            'customer_based_pricings.end_date',
            'customer_based_pricings.customer_id',
            'customer_based_pricings.item_id',
            'customer_based_pricings.item_uom_id',
            'customer_based_pricings.price as customerprice',
            'item_base_prices.price as itembaseprice',
            'items.item_code as itemcode',
            'items.item_name as itemname',
            'item_uoms.name as uomname',
            'customer_infos.customer_code as customer_code',
            'users.firstname as firstname',
            'users.lastname as lastname'
        );

        $cbp->leftJoin('item_base_prices', function ($join) {
            $join->on('customer_based_pricings.item_id', '=', 'item_base_prices.item_id');
            $join->on('customer_based_pricings.item_uom_id', '=', 'item_base_prices.item_uom_id');
        });
        $cbp->Join('items', 'items.id', '=', 'customer_based_pricings.item_id');
        $cbp->Join('customer_infos', 'customer_infos.user_id', '=', 'customer_based_pricings.customer_id');
        $cbp->Join('users', 'users.id', '=', 'customer_based_pricings.customer_id');
        $cbp->Join('item_uoms', 'item_uoms.id', '=', 'customer_based_pricings.item_uom_id');
        if ($request->customer_id) {
            $cbp->where('customer_based_pricings.customer_id', '=', $request->customer_id);
        }
        if ($request->key) {
            $cbp->where('customer_based_pricings.key', '=', $request->key);
        }
        $cbp->where('customer_based_pricings.end_date', '>=', $now);

        // if ($request->uom_code) {
        // $uom_code = $request->uom_code;
        // $cbp->whereHas('itemUom', function ($q) use ($uom_code) {
        //     $q->where('code', 'like', '%' . $uom_code . '%');
        // });
        // }
        //  if ($request->customer_id) {
        // $customer_code = $request->customer_id;
        // $cbp->whereHas('customer_based_pricings', function ($q) use ($customer_code) {
        //     $q->where('customer_id', '=',$request->customer_id);
        // });
        // }
        $all_ibp = $cbp->get();
        //$all_ibp = $cbp->paginate((!empty($request->page_size)) ? $request->page_size : 50);
        //$cbp->paginate((!empty($request->page_size)) ? $request->page_size : 50);
        // $all_ibp = $cbp;
        //print_r($all_ibp);
        // $ibps = $all_ibp->items();

        //    $pagination = array();
        //     $pagination['total_pages'] = $all_ibp->lastPage();
        //     $pagination['current_page'] = (int) $all_ibp->perPage();
        //     $pagination['total_records'] = $all_ibp->total();

        return prepareResult(true, $all_ibp, [], "Customer based price listing", $this->success);
    }
    public function Activelist(Request $request)
    {
        $now = date('Y-m-d');
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $cbp = CustomerBasedPricing::select(
            'customer_based_pricings.id',
            'customer_based_pricings.uuid',
            'customer_based_pricings.key',
            'customer_based_pricings.start_date',
            'customer_based_pricings.end_date',
            'customer_based_pricings.customer_id',
            'customer_based_pricings.item_id',
            'customer_based_pricings.item_uom_id',
            'customer_based_pricings.price as customerprice',
            'item_base_prices.price as itembaseprice',
            'items.item_code as itemcode',
            'items.item_name as itemname',
            'item_uoms.name as uomname',
            'customer_infos.customer_code as customer_code',
            'users.firstname as firstname',
            'users.lastname as lastname'
        );

        $cbp->leftJoin('item_base_prices', function ($join) {
            $join->on('customer_based_pricings.item_id', '=', 'item_base_prices.item_id');
            $join->on('customer_based_pricings.item_uom_id', '=', 'item_base_prices.item_uom_id');
        });
        $cbp->Join('items', 'items.id', '=', 'customer_based_pricings.item_id');
        $cbp->Join('customer_infos', 'customer_infos.user_id', '=', 'customer_based_pricings.customer_id');
        $cbp->Join('users', 'users.id', '=', 'customer_based_pricings.customer_id');
        $cbp->Join('item_uoms', 'item_uoms.id', '=', 'customer_based_pricings.item_uom_id');
        if ($request->customer_id) {
            $cbp->where('customer_based_pricings.customer_id', '=', $request->customer_id);
        }
        if ($request->item_code) {
            $cbp->where('customer_based_pricings.item_id', '=', $request->item_code);
        }
        if ($request->key) {
            $cbp->where('customer_based_pricings.key', 'LIKE', '%' . $request->key . '%');
        }
        $cbp->where('customer_based_pricings.end_date', '>=', $now);
        $cbp->groupBy('customer_based_pricings.item_id');
        $cbp->orderBy('id', 'desc');
        // if ($request->uom_code) {
        // $uom_code = $request->uom_code;
        // $cbp->whereHas('itemUom', function ($q) use ($uom_code) {
        //     $q->where('code', 'like', '%' . $uom_code . '%');
        // });
        // }
        //  if ($request->customer_id) {
        // $customer_code = $request->customer_id;
        // $cbp->whereHas('customer_based_pricings', function ($q) use ($customer_code) {
        //     $q->where('customer_id', '=',$request->customer_id);
        // });
        // }
        $all_ibp = $cbp->get();
        //$all_ibp = $cbp->paginate((!empty($request->page_size)) ? $request->page_size : 50);
        //$cbp->paginate((!empty($request->page_size)) ? $request->page_size : 50);
        // $all_ibp = $cbp;
        //print_r($all_ibp);
        // $ibps = $all_ibp->items();

        //    $pagination = array();
        //     $pagination['total_pages'] = $all_ibp->lastPage();
        //     $pagination['current_page'] = (int) $all_ibp->perPage();
        //     $pagination['total_records'] = $all_ibp->total();

        return prepareResult(true, $all_ibp, [], "Customer based price listing", $this->success);
    }

    public function parse_row($row)
    {
        return array_map('trim', explode(',', $row));
    }

    public function import(Request $request)
    {
        $this->organisation_id = $request->user()->organisation_id;
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'customer_based_bulk_item_price' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer based import", $this->unprocessableEntity);
        }

        $s_date = $request->start_date;
        $e_date = $request->end_date;
        $price_key = $request->price_key;

        $fileName = $_FILES["customer_based_bulk_item_price"]["tmp_name"];


        if ($_FILES["customer_based_bulk_item_price"]["size"] > 0) {

            $file = fopen($fileName, "r");
            $item_array = array();
            $count = 0;
            $new = 0;

            $cusotmer_not_found = array();
            $customer_code_array = array();
            $item_not_found     = array();

            $rows   = str_getcsv(file_get_contents($fileName), "\n");

            $keys   = $this->parse_row(array_shift($rows));
            $result = array();

            foreach ($rows as $row) {
                $row = $this->parse_row($row);

                $result[] = array_combine($keys, $row);
            }
            $secondrow = array(array_filter($result[0]));

            for ($i = 1; $i <= count($secondrow[0]); $i++) {
                $cust[$i] = array_column($result, 'ItemCode' . $i);
            }
            $customers = array_column($result, 'CustomerCodes');

            $improt_item = array();
            $CustomerCollection = new Collection();

            for ($i = 1; $i <= count($secondrow[0]); $i++) {

                foreach (array_filter($customers) as $key => $val) {

                    $customer = DB::table('customer_infos')->select('customer_infos.user_id', 'users.firstname')
                        ->leftJoin('users', 'users.id', '=', 'customer_infos.user_id')
                        ->where('customer_infos.customer_code', $val)
                        ->where('users.organisation_id', $this->organisation_id)
                        ->first();

                    $item = Item::where('item_code', $cust[$i][0])->first();


                    if (is_object($item) && is_object($customer)) {
                        $CustomerCollection->push((object)[
                            "item" => $cust[$i][0],
                            "item_name" => $item->item_name,
                            "Customer_code" => $val,
                            "customer_name" => $customer->firstname,
                            "price" => $cust[$i][$key],
                            "start_date" => $s_date,
                            "end_date" => $e_date,
                            "key" => $price_key
                        ]);
                    }
                }
            }


            return prepareResult(true,  $CustomerCollection, [], "Customer base price Listing successfully.", $this->success);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //
        foreach ($request->data as $key => $row) {
            //Ite id
            $item = Item::where('item_code', $row['item'])->first();
            //Item UOM
            $getitemuom = ItemMainPrice::where('item_id', $item->id)
                ->where('is_secondary', 1)
                ->first();

            if (is_object($getitemuom)) {
                $item_uom_id = $getitemuom->item_uom_id;
            } else {
                $getaltitemuom = ItemMainPrice::where('item_id', $item->id)
                    ->where('item_shipping_uom', 1)
                    ->first();
                if (is_object($getaltitemuom)) {
                    $item_uom_id = $getaltitemuom->item_uom_id;
                }
            }
            //Customerid
            $customer = CustomerInfo::where('customer_code', $row['Customer_code'])->first();

            $customer_data = new CustomerBasedPricing;
            $customer_data->key = $row['key'];
            $customer_data->start_date = $row['start_date'];
            $customer_data->end_date = $row['end_date'];
            $customer_data->customer_id = $customer->user_id;
            $customer_data->item_id = $item->id;
            $customer_data->item_uom_id = (!empty($item_uom_id)) ? $item_uom_id : 1;
            $customer_data->price = $row['price'];
            $customer_data->save();
        }

        return prepareResult(true, $customer_data, [], "Customer Price added successfully", $this->created);
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
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'customer_based_price' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer based import", $this->unprocessableEntity);
        }

        $fileName = $_FILES["customer_based_price"]["tmp_name"];

        if ($_FILES["customer_based_price"]["size"] > 0) {

            $file = fopen($fileName, "r");

            while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
                if ($row[1] !== "Item Code") {

                    $customer_code  = $row[0];
                    $item_code      = $row[1];
                    $uom            = $row[2];
                    $price          = $row[3];
                    $key            = $row[4];

                    $customerInfo = CustomerInfo::where('customer_code', $customer_code)->first();
                    $item         = Item::where('item_code', $item_code)->first();
                    $itemUom      = ItemUom::where('name', $uom)->first();
                    if (
                        $customerInfo &&
                        $item &&
                        $itemUom
                    ) {
                        $start_date     = Carbon::parse($row[5])->format('Y-m-d');
                        $end_date       = Carbon::parse($row[6])->format('Y-m-d');
    
                        $CustomerBasedPricing = new CustomerBasedPricing();
                        $CustomerBasedPricing->key           = $key;
                        $CustomerBasedPricing->customer_id   = $customerInfo->user_id;
                        $CustomerBasedPricing->item_id       = $item->id;
                        $CustomerBasedPricing->item_uom_id   = $itemUom->id;
                        $CustomerBasedPricing->price         = $price;
                        $CustomerBasedPricing->start_date    = $start_date;
                        $CustomerBasedPricing->end_date      = $end_date;
                        $CustomerBasedPricing->save();
                    }
                }
            }
        }

        // Excel::import(new CustomerBasedPriceImport, request()->file('customer_based_price'));

        return prepareResult(true, [], [], "Record imported successfully", $this->success);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
