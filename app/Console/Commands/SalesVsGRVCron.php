<?php

namespace App\Console\Commands;

use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\CustomerKamMapping;
use App\Model\CustomerRegion;
use App\Model\Invoice;
use App\Model\SalesVsGrv;
use Illuminate\Console\Command;

class SalesVsGRVCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salesvsgrv:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is the sales vs grv report';

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
        $invoices = Invoice::select('id', 'invoice_date', 'total_qty', 'total_gross', 'customer_id')
            ->where('invoice_date', now()->subDay()->format('Y-m-d'))
            ->get();

        if ($invoices) {
            foreach ($invoices as $invoice) {
                $ksm = CustomerKamMapping::where('customer_id', $invoice->customer_id)->first();
                $cr = CustomerRegion::where('customer_id', $invoice->customer_id)->first();
                if ($ksm && $cr) {

                    $SalesVsGrv = SalesVsGrv::where('date', $invoice->invoice_date)
                        ->where('kam_id', $ksm->kam_id)
                        ->where('zone_id', $cr->zone_id)
                        ->first();

                    if ($SalesVsGrv) {
                        $SalesVsGrv->update([
                            'invoice_qty' => $SalesVsGrv->invoice_qty + $invoice->total_qty,
                            'invoice_amount' => $SalesVsGrv->invoice_amount + $invoice->total_gross,
                        ]);
                    } else {
                        SalesVsGrv::create([
                            'date'          => $invoice->invoice_date,
                            'zone_id'       => $cr->zone_id,
                            'zone_name'     => model($cr->zone, 'name'),
                            'kam_id'        => $ksm->kam_id,
                            'kam_name'      => model($ksm->kam, 'firstname') . ' ' . model($ksm->kam, 'lastname'),
                            'invoice_qty'   => $invoice->total_qty,
                            'invoice_amount' => $invoice->total_gross,
                            'grv_qty'       => 0,
                            'grv_amount'    => 0,
                        ]);
                    }
                }
            }
        }

        $creditNotes = CreditNote::select('id', 'total_qty', 'total_gross', 'picking_date', 'customer_id', 'credit_note_date')
            ->where('picking_date', now()->subDay()->format('Y-m-d'))
            ->get();

        if ($creditNotes) {
            foreach ($creditNotes as $creditNote) {
                $ksm = CustomerKamMapping::where('customer_id', $creditNote->customer_id)->first();
                $cr = CustomerRegion::where('customer_id', $creditNote->customer_id)->first();

                if ($ksm && $cr) {

                    $SalesVsGrv = SalesVsGrv::where('date', $creditNote->picking_date)
                        ->where('kam_id', $ksm->kam_id)
                        ->where('zone_id', $cr->zone_id)
                        ->first();

                    $cd = CreditNoteDetail::selectRaw("sum(item_qty) as qty")
                        ->where('credit_note_id', $creditNote->id)
                        ->first();

                    if ($SalesVsGrv) {
                        $SalesVsGrv->update([
                            'grv_qty' => $SalesVsGrv->grv_qty + $creditNote->total_qty,
                            'grv_amount' => $SalesVsGrv->grv_amount + $creditNote->total_gross,
                        ]);
                    } else {

                        SalesVsGrv::create([
                            'date'          => $creditNote->picking_date,
                            'zone_id'       => $cr->zone_id,
                            'zone_name'     => model($cr->zone, 'name'),
                            'kam_id'        => $ksm->kam_id,
                            'kam_name'      => model($ksm->kam, 'firstname') . ' ' . model($ksm->kam, 'lastname'),
                            'invoice_qty'   => 0,
                            'invoice_amount' => 0,
                            'grv_qty'       => ($cd) ? $cd->qty : 0,
                            'grv_amount'    => $creditNote->total_gross,
                        ]);
                    }
                }
            }
        }
    }
}
