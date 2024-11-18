<?php

namespace App\Imports;
use App\Model\Item;
use App\Model\CustomerInfo;
use App\Model\Distribution;
use App\Model\DistributionCustomer;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Throwable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ShelfDisplayImport implements ToModel
{
    use Importable, SkipsErrors, SkipsFailures;
    protected $skipduplicate;


    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function __construct()
    {
        //dd('ghkl');
        $this->skipduplicate = 0;
    }
    public function startRow(): int
    {
        return 2;
    }


    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model_old(array $row)
    {
        $skipduplicate = $this->skipduplicate;
        if(isset($row['outlet_code']) && $row['outlet_code']=='OUTLET CODE'){
            return null;
        }
        
        $distribution = Distribution::where('name',$row['name'])->first();
        $customerInfo = CustomerInfo::where('customer_code',$row['outlet_code'])->first();
        
        
        if (is_null($distribution)) {
            $distribution               = new Distribution;
            $distribution->name         = $row['name'] ?? null;
            $distribution->start_date   = "2023-06-01";
            $distribution->end_date     = "2040-12-31";
            $distribution->save();
        }

        if($customerInfo)
        {
            $ddCustomer = DistributionCustomer::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
            ->first();
    
            if (!is_null($distribution) && is_null($ddCustomer)) {
                $distribution_customer         = new DistributionCustomer;
                $distribution_customer->distribution_id = $distribution->id;
                $distribution_customer->customer_id     = $customerInfo->user_id;
                $distribution_customer->save();
    
                $distribution_model_stocks  = new DistributionModelStock;
                $distribution_model_stocks->organisation_id  = auth()->user()->organisation_id;
                $distribution_model_stocks->distribution_id  = $distribution->id;
                $distribution_model_stocks->customer_id      = $customerInfo->user_id;
                $distribution_model_stocks->save();
            }else{
                $distribution_model_stocks = DistributionModelStock::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                ->first();
            }
     
                $item                                                          = Item::where('item_code',$row['parent_code'])->first();
                if($item)
                {
                    $distribution_model_stock_details                              = new DistributionModelStockDetails;
                    $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                    $distribution_model_stock_details->distribution_id             = $distribution->id;
                    $distribution_model_stock_details->item_id                     = $item->id;
                    $distribution_model_stock_details->item_uom_id                 = $item->lower_unit_uom_id;
                    $distribution_model_stock_details->capacity                    = 0;
                    $distribution_model_stock_details->total_number_of_facing      = 0;
                    $distribution_model_stock_details->save();
                }
                
        }
        

        return $distribution;
    }


    public function model(array $row)
    {
        //dd($row); die('coimes');
        $skipduplicate = $this->skipduplicate;
        if(isset($row[1]) && $row[1]=='customer_code'){
            return null;
        }
        
        $distribution = Distribution::where('name',$row[0])->first();
        $customerInfo = CustomerInfo::where('customer_code',$row[1])->first();
        
        
        if (is_null($distribution)) {
            $distribution               = new Distribution;
            $distribution->name         = $row[0] ?? null;
            $distribution->start_date   = "2024-04-01";
            $distribution->end_date     = "2099-12-31";
            $distribution->save();
        }

        if($customerInfo)
        {
            $ddCustomer = DistributionCustomer::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
            ->first();
    
            if (!is_null($distribution) && is_null($ddCustomer)) {
                $distribution_customer         = new DistributionCustomer;
                $distribution_customer->distribution_id = $distribution->id;
                $distribution_customer->customer_id     = $customerInfo->user_id;
                $distribution_customer->save();
    
                $distribution_model_stocks  = new DistributionModelStock;
                $distribution_model_stocks->organisation_id  = auth()->user()->organisation_id;
                $distribution_model_stocks->distribution_id  = $distribution->id;
                $distribution_model_stocks->customer_id      = $customerInfo->user_id;
                $distribution_model_stocks->save();
            }else{
                $distribution_model_stocks = DistributionModelStock::where('distribution_id',$distribution->id)->where('customer_id',$customerInfo->user_id)
                ->first();
            }
    
                $item                                                          = Item::where('item_code',$row[2])->first();
                if($item)
                {
                    $distribution_model_stock_details                              = new DistributionModelStockDetails;
                    $distribution_model_stock_details->distribution_model_stock_id = $distribution_model_stocks->id;
                    $distribution_model_stock_details->distribution_id             = $distribution->id;
                    $distribution_model_stock_details->item_id                     = $item->id;
                    $distribution_model_stock_details->item_uom_id                 = $item->lower_unit_uom_id;
                    //$distribution_model_stock_details->capacity                    = 0;
                    $distribution_model_stock_details->total_number_of_facing      = 0;
                    //$distribution_model_stock_details->rack_id = $row['rack_id'];
                    $distribution_model_stock_details->capacity = 1; //$row['capacity'];
                    $distribution_model_stock_details->save();
                }
                
        }
        

        return $distribution;
    }


    // public function rules(): array
    // {
    //     return [
    //         '*.outlet_code' => 'required',
    //         '*.parent_code' => 'required',
    //     ];
    // }
}
