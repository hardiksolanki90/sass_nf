<?php

namespace App\Exports;

use App\Model\CustomerRegion;
use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\Order;
use Carbon\Carbon;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\SalesmanInfo;
use App\User;
use App\Model\Depot;
use App\Model\OrderType;
use App\Model\PaymentTerm;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderFullExport implements FromCollection, WithHeadings
{
    protected $StartDate, $EndDate, $storage_location_id, $is_header_level, $zone_id;

    public function __construct(String $StartDate, String $EndDate, int $storage_location_id, $is_header_level, int $zone_id)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
        $this->storage_location_id = $storage_location_id;
        $this->zone_id = $zone_id;
        $this->is_header_level = $is_header_level;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $start_date = $this->StartDate;
        $end_date = $this->EndDate;

        $storage_location_id = $this->storage_location_id;
        $zoneId = $this->zone_id;
        $customer_ids = array();

        if ($zoneId > 0) {
            $cr = CustomerRegion::where('zone_id', $zoneId)->get();
            if (count($cr)) {
                $customer_ids = $cr->pluck('customer_id')->toArray();
            }
        }

        $orders_query = Order::with(array('customer' => function ($query) {
            $query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'orderType:id,name,description',
                'paymentTerm:id,name,number_of_days',
                'orderDetails',
                'orderDetails.item:id,item_name,item_code',
                'orderDetails.itemUom:id,name,code',
                'depot:id,depot_name'
            )
            ->where('current_stage', "Approved")
           // ->whereNotIn('approval_status', ['Cancelled', 'Shipment', 'Completed', 'Truck Allocated', 'Delivered'])
            ->whereNotIn('approval_status', ['Cancelled',  'Completed', 'Truck Allocated', 'Delivered'])
            ->whereHas('orderDetails', function ($q) {
                $q->where('is_deleted', '!=', 1)
                    ->where('item_price', '>', 0)
                    ->where('item_qty', '>', 0);
            });

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $orders_query->where('delivery_date', $end_date)->orWhere('change_date', $end_date);
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $orders_query->whereBetween('delivery_date', [$start_date, $endDate]);
            }
        }

        $user = auth()->user();
        if ($user->usertype == 2) {
            $orders_query->where('customer_id', auth()->user()->id);
        }

        if (count($customer_ids)) {
            $orders_query->whereIn('customer_id', $customer_ids);
        }

        if ($storage_location_id) {
            $orders_query->where('storage_location_id', $storage_location_id);
        }

        $orders = $orders_query->get();

        $orderCollection = new Collection();
        if (count($orders)) {

            if ($this->is_header_level == 0) {
                foreach ($orders as $order) {
                    
                    if($order->change_date == NULL && $order->approval_status == "Shipment")
                    {

                    }else{
                        $t_qty = 0;
                        $ctn_qty = 0;
                        foreach ($order->orderDetails as $detail) {
                            $getItemQtyByUom = qtyConversion($detail->item_id, $detail->item_uom_id, $detail->item_qty);
                            $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                            $ctn_qty = $ctn_qty + round(CTNQuantity($detail), 2);
                        }
    
                        $onhold = 'No';
                        $driver = '';
                        $trip ='';
                        $isLast = '';
                        $seq = '';
                        if($order->change_date != NULL)
                        {
                            $onhold = 'Yes';

                            $cr = Delivery::where('order_id', $order->id)->first();
                            $SalesmanInfo = SalesmanInfo::where('user_id',$cr->salesman_id)->first();
                            $driver = $SalesmanInfo->salesman_code;
                            $delivery_details = DeliveryAssignTemplate::where('delivery_id', $cr->id)
                            ->first();
                            $trip = $delivery_details->trip;
                            $isLast = $delivery_details->is_last_trip;
                            $seq = $delivery_details->delivery_sequence;

                        }
                        $orderCollection->push((object) [
                            'order_number'      => $order->order_number,
                            'customer_code'     => model($order->customerInfo, 'customer_code'),
                            'customer_name'     => model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname'),
                            'order_date'        => Carbon::parse($order->order_date)->format('Y-m-d'),
                            'request_date'      => Carbon::parse($order->delivery_date)->format('Y-m-d'),
                            'customer_lpo'      => $order->customer_lop,
                            'item_qty'          => $order->total_qty - $order->total_cancel_qty,
                            'item_price'        => $t_qty,
                            'delivery_sequence' => $seq,
                            'trip'              => $trip,
                            'driver_code'       => $driver,
                            'is_last_trip'      => $isLast,
                            'ctn_qty'           => $ctn_qty,
                            'on_hold'           => $onhold,
                        ]);
                    }
                    
                }
            } else {
                foreach ($orders as $order) {

                    if($order->change_date == NULL && $order->approval_status == "Shipment")
                    {

                    }else{
                    
                        foreach ($order->orderDetails as $detail) {
                            if ($detail->item_qty != 0 && $detail->item_price != 0) {

                                $onhold = 'No';
                                $driver = '';
                                $trip ='';
                                $isLast = '';
                                $seq = '';
                                if($order->change_date != NULL)
                                {
                                    $onhold = 'Yes';

                                    $cr = Delivery::where('order_id', $order->id)->first();
                                    $SalesmanInfo = SalesmanInfo::where('user_id',$cr->salesman_id)->first();
                                    $driver = $SalesmanInfo->salesman_code;
                                    $delivery_details = DeliveryAssignTemplate::where('delivery_id', $cr->id)
                                    ->first();
                                    $trip = $delivery_details->trip;
                                    $isLast = $delivery_details->is_last_trip;
                                    $seq = $delivery_details->delivery_sequence;

                                }

                                $orderCollection->push((object) [
                                    'order_number'  => $order->order_number,
                                    'customer_code' => model($order->customerInfo, 'customer_code'),
                                    'customer_name' => model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname'),
                                    'order_date'    => Carbon::parse($order->order_date)->format('Y-m-d'),
                                    'request_date'  => Carbon::parse($order->delivery_date)->format('Y-m-d'),
                                    'customer_lpo'  => $order->customer_lop,
                                    'item_code'     => model($detail->item, 'item_code'),
                                    'item_name'     => model($detail->item, 'item_name'),
                                    'item_qty'      => $detail->item_qty,
                                    'item_price'    => $detail->item_grand_total,
                                    'delivery_sequence' => $seq,
                                    'trip'          => $trip,
                                    'driver_code'   => $driver,
                                    'is_last_trip'  => $isLast,
                                    "UOM"           => model($detail->itemUom, 'name'),
                                    'reason'        => model($detail->reason, 'name'),
                                    'ctn_qty'       => round(CTNQuantity($detail), 2),
                                    'on_hold'           => $onhold,
                                ]);
                            }
                        }
                    }
                }
            }
        }
        return $orderCollection;
    }

    public function headings(): array
    {
        if ($this->is_header_level == 0) {
            return [
                "Order No",
                "Sold To Outlet Id",
                "Sold to Name",
                "LPO Raised Date",
                "LPO Request Date",
                "Customer LPO No",
                "Total Qty",
                "Extended Amount",
                "Delivery Sequence",
                "Trip",
                "Drive Code",
                "Is Last Trip",
                "CTN Qty",
                "On Hold"
            ];
        } else {
            return [
                "Order No",
                "Sold To Outlet Id",
                "Sold to Name",
                "LPO Raised Date",
                "LPO Request Date",
                "Customer LPO No",
                "Item Code",
                "Item Name",
                "Total Volume in Case",
                "Extended Amount",
                "Delivery Sequence",
                "Trip",
                "Drive Code",
                "Is Last Trip",
                "UOM",
                "Revision Reason",
                "CTN Qty",
                "On Hold"
            ];
        }
    }
}
