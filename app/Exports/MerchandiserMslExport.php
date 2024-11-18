<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use DB;

class MerchandiserMslExport implements FromView
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $request;

    public function __construct($request)
    { 
        $this->request = $request;
    }

    public function view(): View
    { 
        $start_date        = $this->request->start_date;
        $end_date          = $this->request->end_date;
        $customer_id       = $this->request->customer_id!=null && $this->request->customer_id!='' ? explode(',',$this->request->customer_id) : [];
        $merchandiser_id   = $this->request->merchandiser_id!=null && $this->request->merchandiser_id!='' ? explode(',', $this->request->merchandiser_id) : [];


        $merchandiser_msls = DB::table('merchandiser_msls')->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
            $customer->whereIn('customer_id', $customer_id);
        })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
            $customer->whereIn('merchandiser_id', $merchandiser_id);
        })->groupBy('customer_id')->get();

        //dd($merchandiser_msls,$merchandiser_id,$customer_id);
        return view('export.merchandiser_msl', [
            'merchandiser_msls' => $merchandiser_msls,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'customer_id'       => $customer_id,
            'merchandiser_id'   => $merchandiser_id,
        ]);
    }
}
