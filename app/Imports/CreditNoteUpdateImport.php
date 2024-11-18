<?php

namespace App\Imports;

use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\CustomerInfo;
use App\Model\Delivery;
use App\Model\Item;
use App\Model\Order;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\Van;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;

class CreditNoteUpdateImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        if (isset($row[0]) && $row[0] != 'Credit Note No') {

            $credit_note = CreditNote::where('credit_note_number', $row[0])
                ->where('approval_status', '!=', 'Truck Allocated')
                ->first();

            if (is_object($credit_note)) {

                $customerInfo = CustomerInfo::where('customer_code', $row[1])->first();

                if (is_object($customerInfo)) {
                    $salemsnaInfo = SalesmanInfo::where('salesman_code', 'like', "%" . $row[13] . "%")
                        ->first();

                    if (is_object($salemsnaInfo)) {
                        $item = Item::where('item_code', $row[4])->first();
                        if ($item) {

                            $credit_note_detail = CreditNoteDetail::where('credit_note_id', $credit_note->id)
                                ->where('item_id', $item->id)
                                ->first();

                            if ($credit_note_detail) {
                                // if trip sequence 1 then add salesman in header and details both table otherwise only details
                                // if ($row[10] == 1) {
                                //     $salesman_load_exist->salesman_id = $salemsnaInfo->user_id;
                                //     $salesman_load_exist->save();
                                // }

                                $van = Van::where('van_code', 'like', "%$row[11]%")->first();

                                $credit_note_detail->salesman_id                  = $salemsnaInfo->user_id;
                                $credit_note_detail->template_credit_note_id      = $credit_note->id;
                                $credit_note_detail->van_id                       = (!empty($van)) ? $van->id : null;
                                $credit_note_detail->template_sold_to_outlet_id   = $customerInfo->user_id;
                                $credit_note_detail->template_item_id             = $item->id;
                                $credit_note_detail->template_driver_id           = $salemsnaInfo->user_id;
                                $credit_note_detail->template_credit_note_number  = $credit_note->credit_note_number;
                                $credit_note_detail->template_sold_to_outlet_code = $customerInfo->customer_code;
                                $credit_note_detail->template_sold_to_outlet_name = $customerInfo->user->getName();
                                $credit_note_detail->template_return_request_date = Carbon::parse($credit_note->created_at)->format('Y-m-d');
                                $credit_note_detail->template_item_name           = $item->item_name;
                                $credit_note_detail->template_item_code           = $item->item_code;
                                $credit_note_detail->template_total_value_in_case = $row[6];
                                $credit_note_detail->template_total_amount        = $row[7];
                                $credit_note_detail->template_delivery_sequnce    = $row[8];
                                $credit_note_detail->template_trip                = $row[9];
                                $credit_note_detail->template_trip_sequnce        = $row[10];
                                $credit_note_detail->template_vechicle            = $row[11];
                                $credit_note_detail->template_driver_name         = $row[12];
                                $credit_note_detail->template_driver_code         = $row[13];
                                $credit_note_detail->template_is_last_trip        = $row[14];
                                $credit_note_detail->save();
                            }
                        }
                    }
                }

                $return_details = CreditNoteDetail::where('credit_note_id', $credit_note->id)
                    ->whereNull('template_driver_code')
                    ->first();

                if (!is_object($return_details)) {
                    $credit_note->approval_status = "Truck Allocated";
                    $credit_note->save();
                }
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }
}
