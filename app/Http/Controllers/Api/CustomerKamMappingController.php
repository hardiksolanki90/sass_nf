<?php

namespace App\Http\Controllers\Api;

use App\Exports\CustomerKamKasImport;
use App\Http\Controllers\Controller;
use App\Model\CustomerKamMapping;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class CustomerKamMappingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $cwms = CustomerKamMapping::with(
            'customerInfo:id,user_id,customer_code',
            'customer:id,firstname,lastname',
            'kam:id,firstname,lastname',
            'kas:id,firstname,lastname',
        );

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
                $cwms->whereHas('customer', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $cwms->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->kas) {
            $name = $request->kas;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $cwms->whereHas('kas', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $cwms->whereHas('kas', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->kam) {
            $name = $request->kam;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $cwms->whereHas('kam', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $cwms->whereHas('kam', function ($q) use ($n) {
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
            'customer_kam_ksm' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer kam import", $this->unprocessableEntity);
        }

        Excel::import(new CustomerKamKasImport, request()->file('customer_kam_ksm'));

        return prepareResult(true, [], [], "Record imported successfully", $this->success);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Model\CustomerKamMapping  $customerKamMapping
     * @return \Illuminate\Http\Response
     */
    public function show(CustomerKamMapping $customerKamMapping)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Model\CustomerKamMapping  $customerKamMapping
     * @return \Illuminate\Http\Response
     */
    public function edit(CustomerKamMapping $customerKamMapping)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Model\CustomerKamMapping  $customerKamMapping
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CustomerKamMapping $customerKamMapping)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Model\CustomerKamMapping  $customerKamMapping
     * @return \Illuminate\Http\Response
     */
    public function destroy(CustomerKamMapping $customerKamMapping)
    {
        //
    }
}
