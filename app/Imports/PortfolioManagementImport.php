<?php

namespace App\Imports;

use App\Model\PortfolioManagement;
use App\Model\PortfolioManagementItem;
use App\Model\PortfolioManagementCustomer;
use App\Model\Item;
use App\Model\CustomerInfo;
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
use App\Model\Vendor;
use App\Model\Lob;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PortfolioManagementImport implements ToModel, WithValidation, SkipsOnFailure, SkipsOnError,WithHeadingRow
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
    public function model(array $row)
    {
        $skipduplicate = $this->skipduplicate;
        if(isset($row['outlet_code']) && $row['outlet_code']=='OUTLET CODE'){
            return null;
        }
        
        $portfolio_management = PortfolioManagement::where('code',$row['outlet_code'])->first();
        $customerInfo = CustomerInfo::where('customer_code',$row['outlet_code'])->first();
        if (is_null($portfolio_management) && !is_null($customerInfo)) {
            $portfolio_management               = new PortfolioManagement;
            $portfolio_management->name         = $row['outlet_name'] ?? null;
            $portfolio_management->code         = $row['outlet_code'];
            $portfolio_management->start_date   = "2023-06-01";
            $portfolio_management->end_date     = "2040-12-31";
            $portfolio_management->save();

            $portfolio_management_customer = new PortfolioManagementCustomer;
            $portfolio_management_customer->portfolio_management_id = $portfolio_management->id;
            $portfolio_management_customer->user_id                 = $customerInfo->user_id;
            $portfolio_management_customer->save();

        }

        if (!is_null($customerInfo)) {
            $item = Item::where('item_code',$row['item_code'])->first();
            if(!empty($item)){
            //     dd($row['item_code']);
                $portfolio_management_item = new PortfolioManagementItem;
                $portfolio_management_item->portfolio_management_id = $portfolio_management->id;
                $portfolio_management_item->item_id                 = $item->id;
                $portfolio_management_item->store_price             = 0;
                $portfolio_management_item->listing_fees            = 0;
                $portfolio_management_item->customer_id             = $customerInfo->id;
                $portfolio_management_item->vendor_item_uom_id      = $item->lower_unit_uom_id;
                $portfolio_management_item->vendor_item_code        = $row['item_code'];
                $portfolio_management_item->save();
            }
        }
        
        return $portfolio_management;
    }

    public function rules(): array
    {
        return [
            '*.outlet_code' => 'required',
            '*.outlet_name' => 'required',
            // '*.item_name' => 'required',
            '*.item_code' => 'required',
        ];
    }
}
