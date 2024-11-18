<?php

namespace App\Exports;

use App\Model\Route;
use App\Model\Area;
use App\Model\Depot;
use Maatwebsite\Excel\Concerns\FromCollection;

class RouteExport implements FromCollection
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
        $routes = Route::with('areas:id,area_name,uuid', 'depot:id,depot_name');
		if($start_date!='' && $end_date!=''){
			$routes = $routes->whereBetween('created_at', [$start_date, $end_date]);
		}
        $routes = $routes->get();
		
		if(is_object($routes)){
			foreach($routes as $key=>$route){
				$depot = Depot::find($route->depot_id);
				$area = Area::find($route->area_id);
				unset($routes[$key]->id);
				unset($routes[$key]->uuid);
				unset($routes[$key]->organisation_id);
				unset($routes[$key]->depot_id);
				unset($routes[$key]->area_id);
				unset($routes[$key]->created_at);
				unset($routes[$key]->updated_at);
				unset($routes[$key]->deleted_at);
				
				$routes[$key]->depot_name = (is_object($depot))?$depot->depot_name:'';
				$routes[$key]->area_name = (is_object($area))?$area->area_name:'';
			}
		}
		
		return $routes;
    }
}
