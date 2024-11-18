<?php

namespace App\Console\Commands;

use App\Model\CreditNote;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReturnTruckAllocationCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return:cron';

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
        Log::info("return cron start.");

        $creditNote = CreditNote::select('id', 'credit_note_number', 'salesman_id', 'customer_id')
            ->where('approval_status', 'Requested')
            ->where('current_stage', 'Approved')
            ->get();

        if (count($creditNote)) {
            $creditNote->each(function ($cr, $key) {
                $dat = DeliveryAssignTemplate::where('customer_id', $cr->customer_id)
                    ->whereDate('created_at', now()->subDay()->format('Y-m-d'))
                    ->where('is_last_trips', 1)
                    ->first();

                if ($dat) {
                    $cr->salesman_id = $dat->delivery_driver_id;
                    $cr->save();
                }
            });
        }

        Log::info("return cron end.");
        return 0;
    }
}
