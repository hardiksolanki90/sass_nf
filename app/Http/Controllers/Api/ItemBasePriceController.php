<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\ItemBasePriceImport;
use App\Model\ItemBasePrice;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class ItemBasePriceController extends Controller
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

        $ibp = ItemBasePrice::select(
            'id',
            'uuid',
            'storage_location_id',
            'warehouse_id',
            'item_id',
            'item_uom_id',
            'price',
            'start_date',
            'end_date'
        )
            ->with(
                'storageocation:id,name,code',
                'item:id,item_name,item_code',
                'itemUom:id,name,code'
            );

        // {"warehouse_code":0,"uom_code":null,"item_code":1}

        if ($request->warehouse_code && $request->warehouse_code != 0) {
            $ibp->where('storage_location_id', $request->warehouse_code);
        }

        if ($request->item_code && $request->item_code != 0) {
            $ibp->where('item_id', $request->item_code);
        }

        if ($request->uom_code && $request->uom_code != 0) {
            $ibp->where('item_uom_id', $request->uom_code);
        }

        // if ($request->warehouse_code) {
        //     $warehouse_code = $request->warehouse_code;
        //     $ibp->whereHas('storageocation', function ($q) use ($warehouse_code) {
        //         $q->where('code', 'like', '%' . $warehouse_code . '%');
        //     });
        // }

        // if ($request->item_code) {
        //     $item_code = $request->item_code;
        //     $ibp->whereHas('item', function ($q) use ($item_code) {
        //         $q->where('item_code', 'like', '%' . $item_code . '%');
        //     });
        // }

        // if ($request->uom_code) {
        //     $uom_code = $request->uom_code;
        //     $ibp->whereHas('itemUom', function ($q) use ($uom_code) {
        //         $q->where('code', 'like', '%' . $uom_code . '%');
        //     });
        // }

        $all_ibp = $ibp->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 50);
        $ibps = $all_ibp->items();

        $pagination = array();
        $pagination['total_pages'] = $all_ibp->lastPage();
        $pagination['current_page'] = (int) $all_ibp->perPage();
        $pagination['total_records'] = $all_ibp->total();

        return prepareResult(true, $ibps, [], "Item base price listing", $this->success, $pagination);
    }

    public function indexMobile()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $ibp = ItemBasePrice::select(
            'id',
            'uuid',
            'storage_location_id',
            'warehouse_id',
            'item_id',
            'item_uom_id',
            'price',
            'start_date',
            'end_date'
        )
            ->with(
                'storageocation:id,name,code',
                'item:id,item_name,item_code',
                'itemUom:id,name,code'
            )
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $ibp, [], "Item base price listing", $this->success);
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
            'item_base_price' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate item price mapping import", $this->unprocessableEntity);
        }

        Excel::import(new ItemBasePriceImport, request()->file('item_base_price'));

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
