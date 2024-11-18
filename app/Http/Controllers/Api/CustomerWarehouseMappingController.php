<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\CustomerWarehouseMappingImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\CustomerWarehouseMapping;
use Illuminate\Support\Facades\Validator;

class CustomerWarehouseMappingController extends Controller
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

        $cwms = CustomerWarehouseMapping::with(
            'customerInfo:id,user_id,customer_code',
            'customerInfo.user:id,firstname,lastname',
            'lob:id,name,lob_code',
            'storageocation:id,name,code'
        );

        if ($request->branch_plant) {
            $cc = $request->branch_plant;
            $cwms->whereHas('storageocation', function ($q) use ($cc) {
                $q->where('code', $cc);
            });
        }

        if ($request->sales_org) {
            $cc = $request->sales_org;
            $cwms->whereHas('lob', function ($q) use ($cc) {
                $q->where('lob_code', 'like', "%$cc%");
            });
        }

        if ($request->customer_code) {
            $cc = $request->customer_code;
            $cwms->whereHas('customerInfo', function ($q) use ($cc) {
                $q->where('customer_code', $cc);
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

        $all = $cwms->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);

        $cwm = $all->items();

        $pagination = array();
        $pagination['total_pages'] = $all->lastPage();
        $pagination['current_page'] = (int)$all->perPage();
        $pagination['total_records'] = $all->total();

        return prepareResult(true, $cwm, [], "Customer Warehouse Lists", $this->success, $pagination);
    }

    public function customerWarehouseMapping(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'customer_mapping' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer mapping import", $this->unprocessableEntity);
        }

        Excel::import(new CustomerWarehouseMappingImport, request()->file('customer_mapping'));

        return prepareResult(true, [], [], "Record imported successfully", $this->success);
    }
}
