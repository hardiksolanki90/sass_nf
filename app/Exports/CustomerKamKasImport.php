<?php

namespace App\Exports;

use App\Model\CustomerInfo;
use App\Model\CustomerKamMapping;
use App\User;
use Maatwebsite\Excel\Concerns\ToModel;

class CustomerKamKasImport implements ToModel
{
    public function model(array $row)
    {
        if ($row[0] != "KAM") {

            $kam             = User::where('email', $row[0])->first();
            $ksm             = User::where('email', $row[1])->first();
            $customer_info   = CustomerInfo::where('customer_code', $row[2])->first();

            if (
                $customer_info &&
                $kam &&
                $ksm
            ) {

                $cr = CustomerKamMapping::where('customer_id', $customer_info->user_id)
                    ->where('kam_id', $kam->id)
                    ->where('kas_id', $ksm->id)
                    ->first();

                if (!$cr) {
                    $cr = new CustomerKamMapping();
                }

                $cr->customer_id = $customer_info->user_id;
                $cr->kam_id   = $kam->id;
                $cr->kas_id   = $ksm->id;
                $cr->save();

                return $cr;
            }
        }
    }
}
