<?php

namespace App\Exports;

use App\Model\Region;
use Maatwebsite\Excel\Concerns\FromCollection;

class RegionExport implements FromCollection
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
        $start_date = $this->StartDate;
		$end_date = $this->EndDate;
		$regions = Region::select('id', 'uuid', 'organisation_id', 'country_id', 'region_name', 'region_code','region_status')
            ->with('country:id,name,uuid');
			
		if($start_date!='' && $end_date!=''){
			$regions = $regions->whereBetween('created_at', [$start_date, $end_date]);
		}
		$regions = $regions->get();
		
		if(is_object($regions)){
			foreach($regions as $key=>$region){
				unset($regions[$key]->id);
				unset($regions[$key]->uuid);
				unset($regions[$key]->organisation_id);
				unset($regions[$key]->country_id);
				unset($regions[$key]->created_at);
				unset($regions[$key]->updated_at);
				unset($regions[$key]->deleted_at);
				
				if(is_object($regions[$key]->country)){
					$regions[$key]->country_name = $regions[$key]->country->name;
				}else{
					$regions[$key]->country_name = "-";
				}
			}
		}
		/* echo '<pre>';
		print_r($regions);exit; */
        return $regions;
    }
}
