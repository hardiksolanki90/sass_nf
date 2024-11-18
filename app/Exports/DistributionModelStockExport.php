<?php

namespace App\Exports;

use App\Model\Distribution;
use App\Model\DistributionCustomer;
use App\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DistributionModelStockExport implements FromCollection,WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
	protected $StartDate,$EndDate;
	public function __construct(String  $StartDate,String $EndDate)
	{
		$this->StartDate = $StartDate;
		$this->EndDate = $EndDate;
	}
    public function collection()
    {
			$organisation_id = request()->user()->organisation_id;
		$start_date = $this->StartDate;
		$end_date = $this->EndDate;
		
		$distribution_query = DB::table('distributions')->select('distributions.name','distributions.start_date','distributions.end_date','distributions.height','distributions.width','distributions.depth','distributions.status','customer_infos.customer_code','users.firstname','items.item_code','items.item_name','item_uoms.name as uom','distribution_model_stock_details.capacity','distribution_model_stock_details.total_number_of_facing');
        $distribution_query->join("distribution_customers", function ($join) {
            $join->on("distribution_customers.distribution_id", "=", "distributions.id");
        });
        $distribution_query->join("customer_infos", function ($join) {
            $join->on("customer_infos.id", "=", "distribution_customers.customer_id");
        });
        $distribution_query->join("users", function ($join) {
            $join->on("users.id", "=", "customer_infos.user_id");
        });
        $distribution_query->join("distribution_model_stocks", function ($join) {
            $join->on("distribution_model_stocks.customer_id", "=", "distribution_customers.customer_id");
        });
		
        $distribution_query->join("distribution_model_stock_details", function ($join) {
            $join->on("distribution_model_stock_details.distribution_model_stock_id", "=", "distribution_model_stocks.id");
        });
		$distribution_query->join("item_uoms", function ($join) {
            $join->on("item_uoms.id", "=", "distribution_model_stock_details.item_uom_id");
        });
		$distribution_query->join("items", function ($join) {
            $join->on("items.id", "=", "distribution_model_stock_details.item_id");
        });
		       	
		if($start_date!='' && $end_date!=''){
			$distribution_query->whereBetween('distributions.created_at', [$start_date, $end_date]);
		}
		$distribution_query->where('distributions.organisation_id', $organisation_id);
		
		// $distribution_query->orderBy('id', 'desc');
		 
		 $distribution_model_stock = $distribution_query->get();
		// exit;
		return $distribution_model_stock;
    }
	public function headings(): array
    {
        return [
            'Name',
			'Start date',
			'End date',
			'Height',
			'Width',
			'Depth',
			'Status',
			'Customer Code',
			'Customer Name',
			'Item Code',
			'Item Name',
			'UOM',
			'Model Capacity',
			'Total Facing'			
        ];
    }
}
