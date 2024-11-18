<?php

namespace App\Imports;

use App\User;
use App\Model\SalesmanInfo;
use App\Model\Country;
use App\Model\Region;
use App\Model\Route;
use App\Model\SalesmanType;
use App\Model\SalesmanRole;
use App\Model\SalesmanNumberRange;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowObjectAction;
use App\Model\WorkFlowRuleApprovalRole;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;

class SalesmanImport implements ToModel, WithValidation, SkipsOnFailure, SkipsOnError, WithMapping
//, WithHeadingRow
//class UsersImport implements ToModel, WithMapping, WithValidation, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;
    protected $skipduplicate;
    protected $map_key_value_array;
    private $rowsrecords = array();
    private $rows = 0;
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function __construct(String  $skipduplicate, array $map_key_value_array, array $heading_array)
    {
        $this->skipduplicate = $skipduplicate;
        $this->map_key_value_array = $map_key_value_array;
        $this->heading_array = $heading_array;
    }
    public function startRow(): int
    {
        return 2;
    }

    final public function map($row): array
    {
        $heading_array = $this->heading_array;
        $map_key_value_array = $this->map_key_value_array;
        //print_r($heading_array);
        //print_r($map_key_value_array);
        $First_Name_key = '0';
        $Last_Name_key = '1';
        $Email_key = '2';
        $Password_key = '3';
        $Mobile_key = '4';
        $Country_key = '5';
        $Status_key = '6';
        $Route_key = '7';
        $Merchandiser_Type_key = '8';
        $Merchandiser_Role_key = '9';
        $Merchandiser_Code_key = '10';
        $Merchandiser_Supervisor_key = '11';
        $Order_From_key = '12';
        $Order_To_key = '13';
        $Invoice_From_key  = '14';
        $Invoice_To_key  = '15';
        $Collection_From_key  = '16';
        $Collection_To_key = '17';
        $Return_From_key = '18';
        $Return_To_key = '19';
        $Unload_From_key = '20';
        $Unload_To_key = '21';
        $couter = 0;
        foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
            //$map_key_value_array_key.'--'.$map_key_value_array_value;
            //array_search($map_key_value_array_value,$heading_array,true);
            if ($couter == 0) {
                $First_Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 1) {
                //echo '==>'.$map_key_value_array_value;
                //print_r($heading_array);
                $Last_Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 2) {
                $Email_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 3) {
                $Password_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 4) {
                $Mobile_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 5) {
                $Country_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 6) {
                $Status_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 7) {
                $Route_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 8) {
                $Merchandiser_Type_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 9) {
                $Merchandiser_Role_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 10) {
                $Merchandiser_Code_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 11) {
                $Merchandiser_Supervisor_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 12) {
                $Order_From_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 13) {
                $Order_To_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 14) {
                $Invoice_From_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 15) {
                $Invoice_To_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 16) {
                $Collection_From_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 17) {
                $Collection_To_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 18) {
                $Return_From_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 19) {
                $Return_To_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 20) {
                $Unload_From_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 21) {
                $Unload_To_key = array_search($map_key_value_array_value, $heading_array, true);
            }
            $couter++;
        }
        //echo $Last_Name_key.'<br>';
        $map =   [
            '0'  => isset($row[$First_Name_key]) ? $row[$First_Name_key] : "", //First Name
            '1'  => isset($row[$Last_Name_key]) ? $row[$Last_Name_key] : "", //Last Name
            '2'  => isset($row[$Email_key]) ? $row[$Email_key] : "", //Email
            '3'  => isset($row[$Password_key]) ? $row[$Password_key] : "", //Password
            '4'  => isset($row[$Mobile_key]) ? $row[$Mobile_key] : "", //Mobile
            '5'  => isset($row[$Country_key]) ? $row[$Country_key] : "", //Country
            '6'  => isset($row[$Status_key]) ? $row[$Status_key] : "", //Status
            '7'  => isset($row[$Route_key]) ? $row[$Route_key] : "", //Region
            '8'  => isset($row[$Merchandiser_Type_key]) ? $row[$Merchandiser_Type_key] : "", //Group Name
            '9'  => isset($row[$Merchandiser_Role_key]) ? $row[$Merchandiser_Role_key] : "", //Sales Organisation
            '10'  => isset($row[$Merchandiser_Code_key]) ? $row[$Merchandiser_Code_key] : "", //Route
            '11'  => isset($row[$Merchandiser_Supervisor_key]) ? $row[$Merchandiser_Supervisor_key] : "", //Channel
            '12'  => isset($row[$Order_From_key]) ? $row[$Order_From_key] : "", //Customer Category
            '13'  => isset($row[$Order_To_key]) ? $row[$Order_To_key] : "", //Customer Code
            '14'  => isset($row[$Invoice_From_key]) ? $row[$Invoice_From_key] : "", //Customer Type
            '15'  => isset($row[$Invoice_To_key]) ? $row[$Invoice_To_key] : "", //Address one
            '16'  => isset($row[$Collection_From_key]) ? $row[$Collection_From_key] : "", //Address two
            '17'  => isset($row[$Collection_To_key]) ? $row[$Collection_To_key] : "", //City
            '18'  => isset($row[$Return_From_key]) ? $row[$Return_From_key] : "", //State
            '19'  => isset($row[$Return_To_key]) ? $row[$Return_To_key] : "", //Zipcode
            '20'  => isset($row[$Unload_From_key]) ? $row[$Unload_From_key] : "", //Phone
            '21'  => isset($row[$Unload_To_key]) ? $row[$Unload_To_key] : ""
        ];
        //print_r($map);
        return $map;
    }
    public function model(array $row)
    {
        ++$this->rows;
        //print_r($row);
        $skipduplicate = $this->skipduplicate;
        $this->rowsrecords[] = $row;
    }
    public function rules(): array
    {
        $skipduplicate = $this->skipduplicate;
        if ($skipduplicate == 0) {
            return [
                '0' => 'required',
                // '1' => 'required',
                '2' => 'required|email',
                '3' => 'required',
                // '4' => 'required',
                '5' => 'required|exists:country_masters,name',
                '6' => 'required',
                // '7' => 'required|exists:routes,route_name',
                '8' => 'required|exists:salesman_types,name',
                '9' => 'required|exists:salesman_roles,name',
                '10' => 'required',
                '11' => 'required',
                // '12' => 'required',
                // '13' => 'required',
                // '14' => 'required',
                // '15' => 'required',
                // '16'  => 'required',
                // '17'  => 'required',
                // '18'  => 'required',
                // '19'  => 'required',
                // '20'  => 'required',
                // '21'  => 'required'
            ];
        } else {
            return [
                '0' => 'required',
                // '1' => 'required',
                '2' => 'required|email|unique:users,email',
                '3' => 'required',
                // '4' => 'required',
                '5' => 'required|exists:country_masters,name',
                '6' => 'required',
                // '7' => 'required|exists:routes,route_name',
                '8' => 'required|exists:salesman_types,name',
                '9' => 'required|exists:salesman_roles,name',
                '10' => 'required|unique:salesman_infos,salesman_code',
                '11' => 'required',
                // '12' => 'required',
                // '13' => 'required',
                // '14' => 'required',
                // '15' => 'required',
                // '16'  => 'required',
                // '17'  => 'required',
                // '18'  => 'required',
                // '19'  => 'required',
                // '20'  => 'required',
                // '21'  => 'required'
            ];
        }
    }
    public function customValidationMessages()
    {
        return [
            '0.required' => 'First name required',
            // '1.required' => 'Last name required',
            '2.required' => 'Email required',
            '2.email' => 'Email not valid',
            '2.unique' => 'Email already_exists',
            '3.required' => 'Password required',
            // '4.required' => 'Mobile required',
            '5.required' => 'Country required',
            '5.exists' => 'Country not exists',
            '6.required' => 'Status required',
            // '7.required' => 'Route required',
            // '7.exists' => 'Route not exists',
            '8.required' => 'Merchandiser type required',
            '8.exists' => 'Merchandiser type not exists',
            '9.required' => 'Merchandiser role required',
            '9.exists' => 'Merchandiser role not exists',
            '10.required' => 'Merchandiser code required',
            '10.unique' => 'Merchandiser code already_exists',
            '11.required' => 'Merchandiser Supervisor required',
            // '12.required' => 'Order From required',
            // '13.required' => 'Order To required',
            // '14.required' => 'Invoice From required',
            // '15.required' => 'Invoice To required',
            // '16.required'  => 'Collection From required',
            // '17.required'  => 'Collection To required',
            // '18.required'  => 'Return From required',
            // '19.required'  => 'Return To required',
            // '20.required'  => 'Unload From required',
            // '21.required'  => 'Unload To required'
        ];
    }
    /* public function onFailure(Failure ...$failures)
    {
        // Handle the failures how you'd like.
    } */
    public function createWorkFlowObject($work_flow_rule_id, $module_name, $row, $raw_id)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id   = $work_flow_rule_id;
        $createObj->module_name         = $module_name;
        $createObj->raw_id                 = $raw_id;
        $createObj->request_object      = $row;
        $createObj->save();
    }
    public function getRowCount(): int
    {
        return $this->rows;
    }
    public function successAllRecords()
    {
        return $this->rowsrecords;
    }
}
