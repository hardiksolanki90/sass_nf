<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\DeliveryDetail;
use App\Model\DriverAndVanSwaping;
use App\Model\Order;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\SalesmanVehicle;
use App\Model\Van;
use App\User;
use Illuminate\Http\Request;

class DriverAndVanSwappingController extends Controller
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

        $dvs = DriverAndVanSwaping::with(
            'newSalesman:id,firstname,lastname',
            'newSalesman.salesmaninfo:id,user_id,salesman_code',
            'oldSalesman:id,firstname,lastname',
            'oldSalesman.salesmaninfo:id,user_id,salesman_code',
            'oldVan:id,van_code,plate_number',
            'newVan:id,van_code,plate_number',
            'route:id,route_code,route_name',
            'login_user:id,firstname,lastname',
            'reason:id,type,name,code'
        );

        if ($request->salesman_id) {
            $dvs->where('salesman_id', $request->salesman_id);
        }

        if ($request->van_id) {
            $dvs->where('van_id', $request->van_id);
        }

        $all_dvs = $dvs->orderBy('id', 'desc')->get();

        return prepareResult(true, $all_dvs, [], "Driver and Van Swaping listing", $this->success);
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
            return prepareResult(true, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating reason", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $dvs = new DriverAndVanSwaping;
            $dvs->order_id          = implode(",",$request->order_id);
            $dvs->new_salesman_id   = $request->new_salesman_id;
            $dvs->old_salesman_id   = $request->old_salesman_id;
            $dvs->old_van_id        = $request->old_van_id;
            $dvs->new_van_id        = $request->new_van_id;
            $dvs->reason_id         = $request->reason_id;
            $dvs->date              = $request->date;
            $dvs->login_user_id     = request()->user()->id;
            $dvs->save();

            if (!$request->old_salesman_id && $request->old_van_id) {

                SalesmanVehicle::where('van_id', $request->old_van_id)
                    ->where('date', $request->date)
                    ->update([
                        'van_id'    => $request->new_van_id,
                    ]);

                $sls = SalesmanLoad::where('van_id', $request->old_van_id)
                    ->where('load_date', $request->date)
                    ->get();

                if (count($sls)) {
                    foreach ($sls as $sl) {
                        $sl->van_id   = $request->new_van_id;
                        $sl->save();

                        SalesmanLoadDetails::where('salesman_load_id', $sl->id)->update([
                            'van_id'   => $request->new_van_id,
                        ]);

                        $r = Route::where('van_id', $request->new_van_id)->first();
                        if ($r) {
                            SalesmanInfo::where('user_id', $sl->salesman_id)
                                ->update([
                                    'route_id' => $r->id
                                ]);
                        }
                    }
                }
            } else if ($request->old_salesman_id && !$request->old_van_id) {

                $deliveries = array();

                SalesmanVehicle::where('salesman_id', $request->old_salesman_id)
                    ->where('date', $request->date)
                    ->update([
                        'salesman_id'   => $request->new_salesman_id
                    ]);

                // $new_user_salesman = User::find($request->new_salesman_id);
                $old_salesman = $request->old_salesman_id;

                if ($request->order_id) {
                    $order = Order::whereIn('order_number', $request->order_id)->pluck("id")
                    ->toArray();
                   
                    if (!$order) {
                        return prepareResult(false, [], ['error' => 'Order not found.'], "Order not found.", $this->not_found);
                    }
                    if (is_array($order)) {
                        $deliveryObj = Delivery::whereIn('order_id', $order)->pluck("id")
                        ->toArray();
                        if (is_array($deliveryObj)) {
                            $deliveries = Delivery::whereIn('order_id', $order)->where('delivery_date', $request->date)
                                ->whereIn('id', $deliveryObj)
                                ->whereHas('deliveryAssignTemplate', function ($q) use ($old_salesman) {
                                    $q->where('delivery_driver_id', $old_salesman);
                                })
                                ->get();
                        }
                    }
                } else {

                    $deliveries = Delivery::where('delivery_date', $request->date)
                        ->whereHas('deliveryAssignTemplate', function ($q) use ($old_salesman) {
                            $q->where('delivery_driver_id', $old_salesman);
                        })
                        ->get();
                }


                if (count($deliveries) > 0) {
                    foreach ($deliveries as $delivery) {

                        DeliveryAssignTemplate::where('delivery_driver_id', $request->old_salesman_id)
                            ->where('delivery_id', $delivery->id)
                            ->update([
                                'delivery_driver_id' => $request->new_salesman_id
                            ]);


                        $delivery->update([
                            'salesman_id' => $request->new_salesman_id
                        ]);

                        $sl = SalesmanLoad::where('salesman_id', $request->old_salesman_id)
                            ->where('delivery_id', $delivery->id)
                            ->first();

                        if ($sl) {
                            $sl->salesman_id   = $request->new_salesman_id;
                            if ($request->order_id) {
                                $sl->status   = 0;
                            }
                            $sl->save();

                            SalesmanLoadDetails::where('salesman_load_id', $sl->id)->update([
                                'salesman_id'   => $request->new_salesman_id,
                            ]);

                            $r = Route::where('van_id', $sl->van_id)->first();
                            if ($r) {
                                SalesmanInfo::where('user_id', $sl->salesman_id)
                                    ->update([
                                        'route_id' => $r->id
                                    ]);
                            }
                        }
                    }
                }
            }


            \DB::commit();
            return prepareResult(true, $dvs, [], "Driver and van swapping added successfully", $this->created);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                // 'new_salesman_id'   => 'integer|salesman_infos:table,user_id',
                // 'old_salesman_id'   => 'integer|salesman_infos:table,user_id',
                // 'new_van_id'        => 'integer|vans:table,id',
                // 'old_van_id'        => 'integer|vans:table,id',
                'date'              => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }
}
