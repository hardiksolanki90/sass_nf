<?php

namespace App\Console\Commands;

use App\Model\Goodreceiptnotedetail;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\ReturnView;
use App\Model\rfGenView;
use App\Model\SalesmanUnloadDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RfGenViewCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rfgen:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        Log::info("rfgen cron start.");
        $rfGenView = rfGenView::where('mobiato_order_picked', '!=', 1)
            ->where('OrderPicked', "Yes")
            ->get();

        $rfGenView->each(function ($o, $key) {
            $od = OrderDetail::find($o->order_detail_id);

            if ($od) {
                // status updated in mobiato order_details table
                $od->update([
                    'is_rfgen_sync' => ($o->OrderPicked == "Yes") ? 1 : 0,
                ]);

                // status updated rg_gen_view table
                $o->update([
                    'mobiato_order_picked' => 1
                ]);

                // status updated order table
                $order = Order::where('order_number', $o->Order_Number)
                    ->where('approval_status', '!=', 'Picking Confirmed')
                    ->first();

                if ($order) {
                    $order->update([
                        'approval_status' => 'Picking Confirmed'
                    ]);
                }
            }
        });
        Log::info("rfgen cron Order Completed");
        // Order Cron done
        // Log::info("rfgen cron GRV start");
        // // GRV Cron
        // $rfGenView = ReturnView::where('mobiato_order_picked', '!=', 1)
        //     ->where('FLAG_GD_CTN', "Y")
        //     ->orWhere('FLAG_GD_PCS', "Y")
        //     ->orWhere('FLAG_DM', "Y")
        //     ->orWhere('FLAG_EX', "Y")
        //     ->orWhere('FLAG_NR', "Y")
        //     ->get();

        // $rfGenView->each(function ($o, $key) {
        //     if ($o->salesman_unload_detail_id) {
        //         $od = SalesmanUnloadDetail::find($o->salesman_unload_detail_id);
        //     } else {
        //         $od = Goodreceiptnotedetail::find($o->salesman_unload_detail_id);
        //     }

        //     if ($od) {
        //         // status updated in mobiato order_details table
        //         $picked = 0;
        //         if ($od->FLAG_GD_CTN == "Y") {
        //             $picked = 1;
        //         } else if ($od->FLAG_GD_PCS == "Y") {
        //             $picked = 1;
        //         } else if ($od->FLAG_DM == "Y") {
        //             $picked = 1;
        //         } else if ($od->FLAG_EX == "Y") {
        //             $picked = 1;
        //         } else if ($od->FLAG_NR == "Y") {
        //             $picked = 1;
        //         }


        //         $od->update([
        //             'is_rfgen_sync' => $picked,
        //         ]);

        //         // status updated rg_gen_view table
        //         $o->update([
        //             'mobiato_order_picked' => 1
        //         ]);
        //     }
        // });
        // Log::info("rfgen is working fine.");
    }
}
