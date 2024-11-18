<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\OdoMeter;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanVehicle;
use App\Model\Van;
use Illuminate\Database\Eloquent\Collection;
use App\Model\VehicleUtilisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OdoMeterController extends Controller
{
    public function index()
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $vans = Van::with('route:id,van_id,route_name,route_code', 'route.salesmanNumberRange')
            ->select(
                'id',
                'uuid',
                'van_code',
                'plate_number',
                'description',
                'capacity',
                'van_status',
                'reading',
            )->get();

        return prepareResult(true, $vans, [], "Van list with odo meter Successfully ", $this->success);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Odo Meter", $this->unprocessableEntity);
        }

        $odometer = new OdoMeter();
        $odometer->salesman_id  = $request->salesman_id;
        $odometer->trip_id      = $request->trip_id;
        $odometer->date         = $request->date;
        $odometer->van_id       = $request->van_id;
        $odometer->start_fuel   = $request->start_fuel;
        $odometer->end_fuel     = $request->end_fuel;
        $odometer->diesel       = 0;
        $odometer->status       = "start";
        $odometer->save();

        $sv = SalesmanVehicle::where('salesman_id', $request->salesman_id)
            ->where('van_id', $request->van_id)
            ->where('date', $request->date)
            ->first();

        if (!$sv) {
            // find the route base on vehicle
            $r = Route::where('van_id', $request->van_id)->first();

            $sv = new SalesmanVehicle;
            $sv->salesman_id = $request->salesman_id;
            $sv->van_id = $request->van_id;
            $sv->route_id = ($r) ? $r->id : null;
            $sv->date = $request->date;
            $sv->helper1_id = $request->helper1_id;
            $sv->helper2_id = $request->helper2_id;
            $sv->save();

            if ($request->salesman_id) {
                if ($r) {
                    SalesmanInfo::where('user_id', $request->salesman_id)
                        ->update([
                            'route_id' => $r->id,
                        ]);
                }
            }
        }

        // $dats = DeliveryAssignTemplate::where('delivery_driver_id', $request->salesman_id)
        //     ->get();

        // $er = array();
        // $old_delivery = '';
        // foreach ($dats as $key => $dat) {
        //     if ($dat) {
        //         $delivery = Delivery::find($dat->delivery_id);
        //         DB::beginTransaction();
        //         if (!$delivery) {
        //             continue;
        //         }
        //         try {
        //             $new_delivery = true;
        //             foreach ($delivery->deliveryDetails as $detail) {

        //                 $delivery_date = model($dat->delivery, 'delivery_date');

        //                 $vu = VehicleUtilisation::where('vehicle_id', $request->van_id)
        //                     ->where('transcation_date', $delivery_date)
        //                     ->first();

        //                 if (!$vu) {

        //                     $region_code = null;
        //                     $region_name = null;

        //                     if (is_object($delivery->customerRegion)) {
        //                         if ($delivery->customerRegion->region) {
        //                             $region_code = $delivery->customerRegion->region->region_code;
        //                             $region_name = $delivery->customerRegion->region->region_code;
        //                         }
        //                     }

        //                     // if record not exist then create new record
        //                     $vu = new VehicleUtilisation();
        //                     $vu->region_id = model($dat->delivery->customerRegion, 'region_id');
        //                     $vu->region_code = $region_code;
        //                     $vu->region_name = $region_name;
        //                     $vu->vehicle_id = $request->van_id;
        //                     $vu->vehicle_code = model($dat->van, 'van_code');
        //                     $vu->customer_count = $this->getCustomerCount(date('Y-m-d'), $request->van_id);
        //                     $vu->delivery_qty = model($dat->delivery, 'total_qty');
        //                     $vu->cancle_count = 0;
        //                     $vu->cancel_qty = model($dat->delivery, 'total_cancel_qty');
        //                     $vu->transcation_date = date('Y-m-d');
        //                     $vu->less_delivery_count = (model($dat->order, 'total_qty') <= 10) ? 1 : 0;
        //                     $vu->order_count = 1;
        //                     $vu->order_qty = model($dat->order, 'total_qty');
        //                     $vu->vehicle_capacity = model($dat->van, 'capacity');
        //                     $vu->save();
        //                 } else {
        //                     if ($new_delivery) {
        //                         $vu->update([
        //                             'customer_count' => $this->getCustomerCount(date('Y-m-d'), $request->van_id),
        //                             'delivery_qty' => $vu->delivery_qty + $dat->delivery->total_qty,
        //                             'cancle_count' => $vu->cancle_count + $dat->delivery->total_cancel_qty,
        //                             'less_delivery_count' => (model($dat->order, 'total_qty') <= 10) ? $vu->less_delivery_count + 1 : $vu->less_delivery_count,
        //                             'order_count' => $vu->order_count + 1,
        //                             'order_qty' => $vu->order_qty + model($dat->order, 'total_qty'),
        //                         ]);
        //                     }
        //                 }
        //                 $new_delivery = false;
        //             }

        //             DB::commit();
        //         } catch (\Exception $exception) {
        //             DB::rollback();
        //             $delivery->sync_status = $exception;
        //             $delivery->save();
        //             return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        //         } catch (\Throwable $exception) {
        //             DB::rollback();
        //             $delivery->sync_status = $exception;
        //             $delivery->save();
        //             return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        //         }
        //     }
        // }

        return prepareResult(true, $odometer, [], "OdoMeter Added Successfully ", $this->success);
    }

    public function update(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Odo Meter", $this->unprocessableEntity);
        }

        $odometer = OdoMeter::where('salesman_id', $request->salesman_id)
            ->where('trip_id', $request->trip_id)
            ->where('date', $request->date)
            ->where('van_id', $request->van_id)
            ->first();

        if ($odometer) {
            $odometer->end_fuel = $request->end_fuel;
            $odometer->diesel = $request->diesel;
            $odometer->status = "end";
            $odometer->save();

            Van::where('id', $odometer->van_id)
                ->update([
                    'reading' => $request->end_fuel,
                ]);

            return prepareResult(true, $odometer, [], "OdoMeter updated Successfully ", $this->success);
        }
        return prepareResult(false, [], [], "Could not find vehicle ", $this->unprocessableEntity);
    }

    public function getDetails($van_id)
    {
       
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $odometer = OdoMeter::where('van_id', $van_id)
            ->with('salesmanInfo.user:id,firstname,lastname')
            ->with('trip')
            ->with('salesman_vehicles.helperInfo.user:id,firstname,lastname')
            ->orderBy('date', 'DESC')
            ->get();
/* 
          $odometer = new Collection();

        foreach($odometer1 as $odometer_data){
             
            
            $helper_info = SalesmanVehicle::where('van_id', $van_id)->where('date', $odometer_data->date)
            ->with('helperInfo')
            ->orderBy('date', 'DESC')
            ->first();

            $odometer->push((object)[
                 $odometer_data,
                

            ]);

        }  */ 

        return prepareResult(true, $odometer, [], "OdoMeter  List ", $this->success);
    }

    public function odometerChange(Request $request, $id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "update");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Odo Meter", $this->unprocessableEntity);
        }

        $odometer = OdoMeter::find($id);

        if ($odometer) {
            $odometer->start_fuel = $request->start_fuel;
            $odometer->end_fuel = $request->end_fuel;
            $odometer->diesel = $request->diesel;
            $odometer->save();

            return prepareResult(true, $odometer, [], "OdoMeter updated Successfully ", $this->success);
        }
        return prepareResult(false, [], [], "Could not find vehicle ", $this->unprocessableEntity);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'salesman_id' => 'required',
                'trip_id' => 'required',
                'date' => 'required',
                'van_id' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        if ($type == "update") {
            $validator = Validator::make($input, [
                'start_fuel' => 'required',
                'diesel' => 'required',
                'end_fuel' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }
}
