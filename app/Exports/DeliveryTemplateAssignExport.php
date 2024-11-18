<?php

namespace App\Exports;

use App\Model\CustomerRegion;
use App\Model\DeliveryAssignTemplate;
use App\Model\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class DeliveryTemplateAssignExport implements FromCollection, WithHeadings
{
    protected $StartDate, $EndDate, $storage_location_id, $is_header_level, $zone_id;

    public function __construct(String $StartDate, String $EndDate, int $storage_location_id, $is_header_level, int $zone_id)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
        $this->storage_location_id = $storage_location_id;
        $this->is_header_level = $is_header_level;
        $this->zone_id = $zone_id;
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

        $dat_query = Order::with(
            'deliveries',
            'deliveries.deliveryDetails',
            // 'delivery_assign_template',
            // 'delivery_assign_template.deliveryDriver',
            // 'delivery_assign_template.deliveryDriverInfo',
            'orderDetails',
            'orderDetails.item',
            'orderDetails.itemUom',
            'customerInfo',
            'customer'
           
        );
        // ->where('qty', '!=', 0);
        // ->whereHas('delivery_assign_template', function ($q) {
        //             $q->where('qty', '!=', 0);
        //         });
        // $dat_query = DeliveryAssignTemplate::with(
        //     'order',
        //     'delivery',
        //     'deliveryDetail',
        //     'customerInfo',
        //     'deliveryDriver',
        //     'deliveryDriverInfo',
        //     'item',
        //     'itemUom'
        // )

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $dat_query->where('delivery_date', $end_date);
                
                // $dat_query->whereHas('order', function ($q) use ($end_date) {
                //     $q->where('delivery_date', $end_date);
                // });
            } else {
                $endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
                $dat_query->whereBetween('delivery_date', [$start_date, $endDate]);
            }
        }

        if ($zoneId > 0) {
            $cr = CustomerRegion::where('zone_id', $zoneId)->get();
            if (count($cr)) {
                $customer_ids = $cr->pluck('customer_id')->toArray();
            }
        }

        if (count($customer_ids)) {
            $dat_query->whereIn('customer_id', $customer_ids);
        }

        $user = auth()->user();
        if ($user->usertype == 2) {
            $dat_query->where('customer_id', auth()->user()->id);
        }

        if ($storage_location_id) {
            $dat_query->where('storage_location_id', $storage_location_id);
        }

        $dats = $dat_query->get();
        // echo($dats);
        // exit();
        $orderCollection = new Collection();
        if (count($dats)) {

            if ($this->is_header_level == 0) {
                foreach ($dats as $dat) {
                    // echo $dat."=================";
                    $dat_queryss = DeliveryAssignTemplate::with(
                        'deliveryDriver',
                        'deliveryDriverInfo',
                       
                    )
                    ->where('order_id',$dat->id)->where('qty', '!=', 0)->get();
                    $t_qty = 0;
                    $ctn_qty = 0;
                    if(count($dat_queryss) > 0)
                    {

                        foreach ($dat_queryss as $datss) {
                        $getItemQtyByUom = qtyConversion($datss->item_id, $datss->item_uom_id, $datss->qty);
                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                        $ctn_qty = $ctn_qty + round(CTNQuantity($datss), 2);
                        
                        $orderCollection->push((object) [
                            'order_number'      => $dat->order_number,
                            'customer_code'     => model($dat->customerInfo, 'customer_code'),
                            'customer_name'     => model($dat->customer, 'firstname') . ' ' . model($dat->customer, 'lastname'),
                            'order_date'        => Carbon::parse($dat->order_date)->format('Y-m-d'),
                            'request_date'      => Carbon::parse($dat->delivery_date)->format('Y-m-d'),
                            'customer_lpo'      => $dat->customer_lop,
                            'item_qty'          => $dat->total_qty - $dat->total_cancel_qty,
                            'item_price'        => $t_qty,
                            'delivery_sequence' => "",
                            'trip'              => !empty($datss->trip) ? $datss->trip : "",
                            'driver_code'       => !empty($datss) ? model($datss->deliveryDriverInfo, 'salesman_code') : "",
                            'is_last_trip'      =>!empty($datss) ? $datss->is_last_trip : "",
                            'ctn_qty'           => $ctn_qty,
                            'status'       => $dat->approval_status
                        ]);
                        // $orderCollection->push((object) [
                        //     'order_number'      => model($dat->order, 'order_number'),
                        //     'customer_code'     => model($dat->customerInfo, 'customer_code'),
                        //     'customer_name'     => model($dat->customer, 'firstname') . ' ' . model($dat->customer, 'lastname'),
                        //     'order_date'        => Carbon::parse($dat->order_date)->format('Y-m-d'),
                        //     'request_date'      => Carbon::parse($dat->order->delivery_date)->format('Y-m-d'),
                        //     'customer_lpo'      => $dat->order->customer_lop,
                        //     'item_qty'          => $dat->order->total_qty - $dat->order->total_cancel_qty,
                        //     'item_price'        => $t_qty,
                        //     'delivery_sequence' => "",
                        //     'trip'              => $dat->trip,
                        //     'driver_code'       => model($dat->deliveryDriverInfo, 'salesman_code'),
                        //     'is_last_trip'      => $dat->is_last_trip,
                        //     'ctn_qty'           => $ctn_qty,
                        //     'status'       => $dat->delivery->approval_status
                        // ]);
                    }
                    }else{
                        $orderCollection->push((object) [
                            'order_number'      => $dat->order_number,
                            'customer_code'     => model($dat->customerInfo, 'customer_code'),
                            'customer_name'     => model($dat->customer, 'firstname') . ' ' . model($dat->customer, 'lastname'),
                            'order_date'        => Carbon::parse($dat->order_date)->format('Y-m-d'),
                            'request_date'      => Carbon::parse($dat->delivery_date)->format('Y-m-d'),
                            'customer_lpo'      => $dat->customer_lop,
                            'item_qty'          => $dat->total_qty - $dat->total_cancel_qty,
                            'item_price'        => $t_qty,
                            'delivery_sequence' => "",
                            'trip'              =>number_format((float)0, 2, '.', ''),
                            'driver_code'       => number_format((float)0, 2, '.', ''),
                            'is_last_trip'      => number_format((float)0, 2, '.', ''),
                            'ctn_qty'           => $ctn_qty,
                            'status'       => $dat->approval_status
                        ]);
                    }
                }
            } else {
                foreach ($dats as $dat) {
                    // echo($dat);
                    // exit();
                    $dat_queryss = DeliveryAssignTemplate::with(
                        'deliveryDriver',
                        'deliveryDriverInfo',
                        'item',
                        'itemUom',
                        'deliveryDetail'
                       
                    )
                    ->where('order_id',$dat->id)->get();
                    foreach ($dat_queryss as $datss) {
                    $orderCollection->push((object) [
                        'order_number'      => $dat->order_number,
                        'customer_code'     => model($dat->customerInfo, 'customer_code'),
                        'customer_name'     => model($dat->customer, 'firstname') . ' ' . model($dat->customer, 'lastname'),
                        'order_date'        => Carbon::parse($dat->order_date)->format('Y-m-d'),
                        'request_date'      => Carbon::parse($dat->delivery_date)->format('Y-m-d'),
                        'customer_lpo'      => $dat->customer_lop,
                        'item_code'     => model($datss->item, 'item_code'),
                        'item_name'     => model($datss->item, 'item_name'),
                        'item_qty'      => $datss->item_qty,
                        'item_price'    => model($datss->deliveryDetail, 'item_grand_total'),
                        'delivery_sequence' => "",
                        'trip'              => $datss->trip,
                        'driver_code'       => model($dat->deliveryDriverInfo, 'salesman_code'),
                        'is_last_trip'      => $datss->is_last_trip,
                        "UOM"           => model($datss->itemUom, 'name'),
                        'reason'        => model($datss->reason, 'name'),
                        'ctn_qty'       => round(CTNQuantity($datss), 2),
                        'status'       => $dat->approval_status
                    ]);
                }
                    // $orderCollection->push((object) [
                    //     'order_number'      => model($dat->order, 'order_number'),
                    //     'customer_code'     => model($dat->customerInfo, 'customer_code'),
                    //     'customer_name'     => model($dat->customer, 'firstname') . ' ' . model($dat->customer, 'lastname'),
                    //     'order_date'        => Carbon::parse($dat->order_date)->format('Y-m-d'),
                    //     'request_date'      => Carbon::parse($dat->order->delivery_date)->format('Y-m-d'),
                    //     'customer_lpo'      => $dat->order->customer_lop,
                    //     'item_code'     => model($dat->item, 'item_code'),
                    //     'item_name'     => model($dat->item, 'item_name'),
                    //     'item_qty'      => $dat->item_qty,
                    //     'item_price'    => model($dat->deliveryDetail, 'item_grand_total'),
                    //     'delivery_sequence' => "",
                    //     'trip'              => $dat->trip,
                    //     'driver_code'       => model($dat->deliveryDriverInfo, 'salesman_code'),
                    //     'is_last_trip'      => $dat->is_last_trip,
                    //     "UOM"           => model($dat->itemUom, 'name'),
                    //     'reason'        => model($dat->reason, 'name'),
                    //     'ctn_qty'       => round(CTNQuantity($dat), 2),
                    //     'status'       => $dat->delivery->approval_status
                    // ]);
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
                "Status"
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
                "Status"
            ];
        }
    }
}
