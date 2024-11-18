<?php

namespace App\Imports;

use App\Model\CustomerInfo;
use App\Model\Delivery;
use App\Model\DeliveryDetail;
use App\Model\Item;
use App\Model\Order;
use App\Model\SalesmanInfo;
use App\Model\Van;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;

class DeliveryUpdateImport implements ToModel
{
    private $errorsrecords = array();

    private $rows = 0;

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $errors = array();
        if (isset($row[0]) && $row[0] != 'Order No') {

            $order = Order::where('order_number', $row[0])->first();

            ++$this->rows;

            if (!$order) {
                if (!in_array($row[0], $errors)) {
                    $errors[] = "Order Number does not exist" . $row[0];
                }
            }

            $salemsnaInfo = SalesmanInfo::where('salesman_code', $row[15])
                ->first();
            if ($salemsnaInfo) {
                if (!in_array($row[15], $errors)) {
                    $errors[] = "Salesman does not exist" . $row[15];
                }
            }

            $van = Van::where('van_cde', $row[13])
                ->first();
            if ($van) {
                if (!in_array($row[13], $errors)) {
                    $errors[] = "Vehicle does not exist" . $row[13];
                }
            }

            if (is_object($order->cusotmerInfo)) {
                if ($order->cusotmerInfo->cusotmer_code != $row[1]) {
                    if (!in_array($row[1], $errors)) {
                        $errors[] = "Cusotmer is not match with order" . $row[1];
                    }
                }
            }

            $order_details = $order->orderDetails;

            foreach ($order_details as $details) {

                $od_item_code = $details->item->item_code;

                if ($od_item_code != $row[6]) {
                    $errors[] = "Entered item is not in the order" . $row[6];
                }
            }

            if (count($errors) <= 0) {

                if (is_object($order)) {
                    $delivery_exist = Delivery::where('order_id', $order->id)
                        ->where('approval_status', '!=', 'Truck Allocated')
                        ->first();
                    if (is_object($delivery_exist)) {
                        $customerInfo = CustomerInfo::where('customer_code', $row[1])->first();
                        if (is_object($customerInfo)) {
                            $salemsnaInfo = SalesmanInfo::where('salesman_code', 'like', "%" . $row[15] . "%")
                                ->first();
                            if (is_object($salemsnaInfo)) {
                                $item = Item::where('item_code', $row[6])->first();
                                if ($item) {

                                    $delivery_details = DeliveryDetail::where('delivery_id', $delivery_exist->id)
                                        ->where('item_id', $item->id)
                                        ->first();

                                    if ($delivery_details) {
                                        // if trip sequence 1 then add salesman in header and details both table otherwise only details
                                        if ($row[12] == 1) {
                                            $delivery_exist->salesman_id = $salemsnaInfo->user_id;
                                            $delivery_exist->save();
                                        }

                                        $van = Van::where('van_code', 'like', "%$row[13]%")->first();

                                        $delivery_details->salesman_id                  = $salemsnaInfo->user_id;
                                        $delivery_details->template_order_id            = $order->id;
                                        $delivery_details->van_id                       = (!empty($van)) ? $van->id : null;
                                        $delivery_details->template_sold_to_outlet_id   = $customerInfo->user_id;
                                        $delivery_details->template_item_id             = $item->id;
                                        $delivery_details->template_driver_id           = $salemsnaInfo->user_id;
                                        $delivery_details->template_order_number        = $order->order_number;
                                        $delivery_details->template_sold_to_outlet_code = $customerInfo->customer_code;
                                        $delivery_details->template_sold_to_outlet_name = $customerInfo->user->getName();
                                        $delivery_details->template_lpo_raised_date     = $order->delivery_date;
                                        $delivery_details->template_raised_date         = Carbon::parse($order->created_at)->format('Y-m-d');
                                        $delivery_details->template_customer_lpo_no     = $row[5];
                                        $delivery_details->template_item_name           = $item->item_name;
                                        $delivery_details->template_item_code           = $item->item_code;
                                        $delivery_details->template_total_value_in_case = $row[8];
                                        $delivery_details->template_total_amount        = $row[9];
                                        $delivery_details->template_delivery_sequnce    = $row[10];
                                        $delivery_details->template_trip                = $row[11];
                                        $delivery_details->template_trip_sequnce        = $row[12];
                                        $delivery_details->template_vechicle            = $row[13];
                                        $delivery_details->template_driver_name         = $row[14];
                                        $delivery_details->template_driver_code         = $row[15];
                                        $delivery_details->template_is_last_trip        = $row[16];
                                        $delivery_details->transportation_status        = "Delegated";
                                        $delivery_details->save();
                                    }
                                }
                            }
                        }

                        $delivery_details = DeliveryDetail::where('delivery_id', $delivery_exist->id)
                            ->whereNull('template_driver_code')
                            ->first();

                        if (!is_object($delivery_details)) {

                            $dd = DeliveryDetail::where('delivery_id', $delivery_exist->id)
                                ->where('transportation_status', "No")
                                ->first();

                            $delivery_exist->transportation_status = "No";

                            if (!is_object($dd)) {
                                $delivery_exist->transportation_status = "Delegated";

                                Order::where('id', $delivery_exist->order_id)
                                    ->update([
                                        'transportation_status' => "Delegated",
                                        'approval_status' => "Truck Allocated"
                                    ]);
                            }
                            Order::where('id', $delivery_exist->order_id)
                                ->update([
                                    'approval_status' => "Truck Allocated"
                                ]);

                            $delivery_exist->approval_status = "Truck Allocated";
                            $delivery_exist->save();
                        }
                    }
                }
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }

    public function errors()
    {
        return $this->errorsrecords;
    }
}
