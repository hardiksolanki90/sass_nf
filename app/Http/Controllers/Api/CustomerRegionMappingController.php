<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\CustomerRegionImport;
use Illuminate\Http\Request;
use App\Model\CustomerRegion;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class CustomerRegionMappingController extends Controller
{

    public function index(Request $request)
    {
        $cwms = CustomerRegion::with(
            'customerInfo:id,user_id,customer_code',
            'customerInfo.user:id,firstname,lastname',
            'region:id,region_code,region_name',
            'zone:id,name'
        );

        if ($request->customer_code) {
            $cc = $request->customer_code;
            $cwms->whereHas('customerInfo', function ($q) use ($cc) {
                $q->where('customer_code', $cc);
            });
        }

        if ($request->zone_name) {
            $zn = $request->zone_name;
            $cwms->whereHas('zone', function ($q) use ($zn) {
                $q->where('name', $zn);
            });
        }

        if ($request->customer_name) {
            $name = $request->customer_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $cwms->whereHas('customerInfo.user', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $cwms->whereHas('customerInfo.user', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        $cwmss = $cwms->orderBy('id', 'desc')
            ->paginate((!empty($request->page_size)) ? $request->page_size : 10);

        $cr = $cwmss->items();

        $pagination = array();
        $pagination['total_pages'] = $cwmss->lastPage();
        $pagination['current_page'] = (int)$cwmss->perPage();
        $pagination['total_records'] = $cwmss->total();

        return prepareResult(true, $cr, [], "Customer region list", $this->success, $pagination);
    }

    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $cwms = CustomerRegion::with(
            'customerInfo:id,user_id,customer_code',
            'customerInfo.user:id,firstname,lastname',
            'region:id,region_code,region_name',
            'zone:id,name'
        )
            ->where('uuid', $uuid)
            ->first();

        if ($cwms) {
            return prepareResult(true, $cwms, [], "Customer region added successfully", $this->success);
        } else {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }


    /**
     * Update a created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Customer group", $this->unprocessableEntity);
        }

        $cwms = CustomerRegion::where('uuid', $uuid)
            ->first();

        if (!is_object($cwms)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        $cwms->zone_id = $request->zone_id;
        $cwms->region_id = $request->region_id;
        $cwms->customer_id = $request->customer_id;
        $cwms->save();

        return prepareResult(true, $cwms, [], "Customer region updated successfully", $this->success);
    }

    public function storeImport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'customer_region' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer region import", $this->unprocessableEntity);
        }

        Excel::import(new CustomerRegionImport, request()->file('customer_region'));

        return prepareResult(true, [], [], "Record imported successfully", $this->success);
    }
}
