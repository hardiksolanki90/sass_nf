<?php

namespace App\Imports;

use App\Model\Item;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
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

class InvoiceDetailImport implements ToModel, WithValidation, SkipsOnFailure, SkipsOnError,WithHeadingRow
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
            $portfolio_management               = new InvoiceDetail;
            $portfolio_management->name         = $row['outlet_name'] ?? null;
            $portfolio_management->code         = $row['outlet_code'];
            $portfolio_management->start_date   = "2023-06-01";
            $portfolio_management->end_date     = "2040-12-31";
            $portfolio_management->save();
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
