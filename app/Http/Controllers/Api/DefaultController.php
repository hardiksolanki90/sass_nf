<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerMerchandiser;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanCustomer;
use App\Model\MerchandiserUpdated;
use App\Model\SalesmanInfo;
use App\User;

class DefaultController extends Controller
{

    public function customerMerchandiserSync()
    {
        $jpc = JourneyPlanCustomer::get();
        $CustomerMerchandiser = CustomerMerchandiser::get();

        collect($CustomerMerchandiser)->each(function ($customer, $key) {
            $customer->delete();
        });

        \DB::table('customer_merchandisers')->delete();

        collect($jpc)->each(function ($customer, $key) {
            $jp = JourneyPlan::find($customer->journey_plan_id);
            if (is_object($jp)) {
                CustomerMerchandiser::create([
                    'customer_id' => $customer->customer_id,
                    'merchandiser_id' => $jp->merchandiser_id
                ]);
            }
        });
    }

    // update the flag
    public function merchandiserUpdateSysnc()
    {
        $salesman = SalesmanInfo::get();
        foreach ($salesman as $s) {
            MerchandiserUpdated::where('merchandiser_id', $s->user_id)->delete();
            MerchandiserUpdated::create([
                'organisation_id' => 1,
                'merchandiser_id' => $s->user_id,
                'is_updated' => 1
            ]);
        }
    }

    public function supervisorUpdateSysnc()
    {
        $users = User::where('usertype', 4)->get();
        collect($users)->each(function ($u, $key) {
            
            $name = $u->firstname . ' ' . $u->lastname;

            // $name = $u->firstname;

            SalesmanInfo::where('salesman_supervisor', 'like', '%' . $name . '%')->update([
                'salesman_supervisor' => $u->id
            ]);

            // pre($name);

        });
    }
}
