<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerBasedPriceActiveExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $StartDate, $EndDate, $customerid,$key,$item_code;

    public function __construct(String  $StartDate, String $EndDate, $customerid,$key,$item_code)
    {
		//print_r($customerid); exit;
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
		$this->customerid = $customerid;
		$this->key = $key;
		$this->item_code = $item_code;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
		$now = date('Y-m-d');
        $start_date = $this->StartDate;
        $end_date = $this->EndDate;
		//print_r($this->customerid);exit;
		//$customerid = $this->customerid;
		
		
        $data = DB::table('customer_based_pricings')
            ->select(
				'items.item_code as item_code',
                'items.item_name as item_name',
                'ci.customer_code as customer_code',
                'ci_user.firstname as customer_name',
                'item_uoms.name as umo_name',
                'customer_based_pricings.price as customer_price',
				'item_base_prices.price as base_price',
				DB::raw('(customer_based_pricings.price  - item_base_prices.price) as Discount_Price'),
                'key',
                'customer_based_pricings.start_date',
                'customer_based_pricings.end_date'
            )
            ->leftJoin('customer_infos as ci', 'ci.user_id', '=', 'customer_based_pricings.customer_id')
            ->leftJoin('users as ci_user', 'ci_user.id', '=', 'customer_based_pricings.customer_id')
            ->leftJoin('items', 'items.id', '=', 'customer_based_pricings.item_id')
			->leftJoin('item_base_prices', 'item_base_prices.item_id', '=', 'items.id')
            ->leftJoin('item_uoms', 'item_uoms.id', '=', 'customer_based_pricings.item_uom_id')
			->groupBy('customer_id')
			->groupBy('customer_based_pricings.item_id')
			->whereIn('customer_based_pricings.id', function($query)
			{
				$query->selectRaw(DB::raw('MAX(customer_based_pricings.id) as maxId FROM customer_based_pricings GROUP BY customer_id, customer_based_pricings.item_id'));
			});
		if ($this->customerid != '') 
		{
			$data->where('customer_based_pricings.customer_id', "=", $this->customerid);
		}
			 if ($this->item_code) {
            $data->where('customer_based_pricings.item_id', '=',$this->item_code);
			
            }
			if ($this->key) {
            $data->where('customer_based_pricings.key', 'LIKE','%'.$this->key.'%');
			
            }
			
        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $data->where('customer_based_pricings.start_date', $start_date);
            } else {
                $data->where('customer_based_pricings.start_date', ">=", $start_date)
                    ->where('customer_based_pricings.end_date', "<=", $end_date);

                // $data->whereBetween('customer_based_pricings.start_date', [$start_date, $end_date]);
            }
        } else {
            $data->where('customer_based_pricings.start_date', ">=", date('Y-m-d'))
                ->where('customer_based_pricings.end_date', "<=", date('Y-m-d'));
        }
		$data->where('customer_based_pricings.end_date','>=',$now);
			$data->groupBy('customer_based_pricings.item_id');
			$data->orderBy('customer_based_pricings.id', 'desc');

        $datas = $data->get();
        return $datas;
    }

    public function headings(): array
    {
        return [
			'Item Code',
			'Item Description',
            'Customer Code',
			'Customer name',
			'Item Uom',            
			'Customer Price',
			'Base Price',
			'Discount Price',
            'Key',
            'Start Date',
            'End Date'
        ];
    }
}
