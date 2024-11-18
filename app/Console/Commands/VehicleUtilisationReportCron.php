<?php

namespace App\Console\Commands;

use App\Model\CustomerRegion;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\DeliveryNote;
use App\Model\Invoice;
use App\Model\OdoMeter;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\SalesmanVehicle;
use Illuminate\Console\Command;
use App\Model\VehicleUtilisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleUtilisationReportCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicleutilisation:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It is the vehicle utilisation report entry';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info("Vehicle utilisation cron start.");

        $date = now()->subDay()->format('Y-m-d');

        $cancle_count = 0;
        $customer_count = 0;
        $less_delivery_count = 0;
        $order_count = 0;

        $svs = SalesmanVehicle::where('date', $date)
            // ->where('salesman_id', 64165)
            ->get();

        foreach ($svs as $sv) {

            $order_count = 0;

            $dtas  = DeliveryAssignTemplate::select(DB::raw('SUM(qty) as qty'), 'delivery_driver_id', 'trip')
                ->whereHas('delivery', function ($q) use ($date) {
                    $q->where('delivery_date', $date);
                })
                ->where('delivery_driver_id', $sv->salesman_id)
                ->groupBy('delivery_driver_id')
                ->groupBy('trip')
                ->get();

            foreach ($dtas as $dta) {

                $dat_delivery_id  = DeliveryAssignTemplate::select('delivery_id')
                    ->whereHas('delivery', function ($q) use ($date) {
                        $q->where('delivery_date', $date);
                    })
                    ->where('delivery_driver_id', $dta->delivery_driver_id)
                    ->where('trip', $dta->trip)
                    ->groupBy('delivery_id')
                    ->get();


                $delivery_ids = array();
                $invoice_qty = 0;
                $invoice_count = 0;
                $load_qty = 0;
                $order_qty = 0;
                $cr = "";

                if (count($dat_delivery_id)) {
                    $delivery_ids = $dat_delivery_id->pluck('delivery_id')->toArray();

                    $delivery_note = DeliveryNote::select(DB::raw('SUM(qty) as qty'))
                        ->whereIn('delivery_id', $delivery_ids)
                        ->where('salesman_id', $dta->delivery_driver_id)
                        ->where('is_cancel', 0)
                        ->first();

                    $delivery_can = Delivery::select(DB::raw('count(id) as cancel_delivery'))
                        ->whereIn('id', $delivery_ids)
                        ->where('approval_status', 'Cancel')
                        ->first();

                    $co_count = Delivery::select(
                        DB::raw('count(Distinct customer_id) as customer_ids'),
                        DB::raw('count(id) as order_count'),
                        DB::raw('group_concat(customer_id) as customer_id')
                    )
                        ->whereIn('id', $delivery_ids)
                        ->first();

                    $deliveries = Delivery::select('order_id')
                        ->whereIn('id', $delivery_ids)
                        ->get();

                    if (count($deliveries)) {
                        $ids = $deliveries->pluck('order_id')->toArray();
                        $od = OrderDetail::selectRaw('sum(item_qty) as qty')
                            ->whereIn('order_id', $ids)
                            ->first();

                        if ($od->qty > 0) {
                            $order_qty = $od->qty;
                        }
                    }

                    $less_delivery = Delivery::select(
                        DB::raw('count(id) as count_delivery')
                    )
                        ->whereIn('id', $delivery_ids)
                        ->where('total_qty', '<', '11')
                        ->first();

                    $inv_count = Invoice::select(DB::raw('distinct customer_id'))
                        ->whereIn('delivery_id', $delivery_ids)
                        // ->groupBy('customer_id')
                        ->get();

                    $invoice_count = count($inv_count);

                    $less_delivery_count = $less_delivery->count_delivery;

                    $customer_count = $co_count->customer_ids;
                    $order_count    = $co_count->order_count;

                    $cancle_count = $delivery_can->cancel_delivery;

                    $invoice_qty = ($delivery_note->qty > 0) ? $delivery_note->qty : 0;

                    $c_id_exploed = explode(',', $co_count->customer_id);

                    $cr = CustomerRegion::whereIn('customer_id', $c_id_exploed)->first();

                    $salesmanLoad = SalesmanLoad::select('id')
                        ->whereIn('delivery_id', $delivery_ids)
                        ->where('salesman_id', $dta->delivery_driver_id)
                        ->get();

                    if (count($salesmanLoad)) {
                        $ids = $salesmanLoad->pluck('id')->toArray();
                        $load_d = SalesmanLoadDetails::select(DB::raw('SUM(load_qty) as load_qty'))
                            ->whereIn('salesman_load_id', $ids)
                            ->first();
                        if ($load_d && $load_d->load_qty > 0) {
                            $load_qty = $load_d->load_qty;
                        }
                    }
                }

                $om = OdoMeter::where('salesman_id', $dta->delivery_driver_id)
                    ->where('van_id', $sv->van_id)
                    ->where('status', 'end')
                    ->where('date', $date)
                    ->get()
                    ->toArray();

                $s_km   = '0';
                $e_km   = '0';
                $diesel = '0';

                if (count($om)) {
                    $key = $dta->trip - 1;
                    $omObj = (object) $om[$key];

                    if ($omObj) {
                        $s_km   = $omObj->start_fuel;
                        $e_km   = $omObj->end_fuel;
                        $diesel = $omObj->diesel;
                    }
                }

                $vu = new VehicleUtilisation();
                $vu->zone_id      = ($cr) ? $cr->zone_id : NULL;
                $vu->zone_name    = ($cr) ? model($cr->zone, 'name') : NULL;

                $vu->vehicle_id     = $sv->van_id;
                $vu->vehicle_code   = model($sv->van, 'van_code');

                $vu->salesman_id     = $dta->delivery_driver_id;
                $vu->salesman_code   = model($dta->deliveryDriverInfo, 'salesman_code');
                $vu->salesman_name   = model($dta->deliveryDriver, 'firstname') . ' ' . model($dta->deliveryDriver, 'lastname');
                $vu->trip_number     = $dta->trip;

                $vu->invoice_count  = $invoice_count;
                $vu->invoice_qty    = $invoice_qty;

                $vu->customer_count = $customer_count;
                $vu->delivery_qty   = $dta->qty;
                $vu->cancle_count   = $cancle_count;
                $vu->cancel_qty     = $dta->qty - $invoice_qty;
                // $vu->transcation_date = $date;
                $vu->transcation_date = $date;
                $vu->less_delivery_count = $less_delivery_count;
                $vu->order_count    = $order_count;
                $vu->order_qty      = $order_qty;
                $vu->load_qty       = $load_qty;
                $vu->vehicle_capacity = model($sv->van, 'capacity');

                $vu->start_km       = $s_km;
                $vu->end_km         = $e_km;
                $vu->diesel         = $diesel;
                $vu->save();
            }
        }
        Log::info("Vehicle utilisation cron end.");
    }
}
