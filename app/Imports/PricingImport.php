<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithValidation;

class PricingImport implements ToModel, WithValidation, SkipsOnFailure, SkipsOnError, WithMapping
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $skipduplicate;

    protected $map_key_value_array;

    private $rowsrecords = array();

    private $rows = 0;

    public function __construct(String  $skipduplicate, array $map_key_value_array, array $heading_array)
    {
        $this->skipduplicate        = $skipduplicate;
        $this->map_key_value_array  = $map_key_value_array;
        $this->heading_array        = $heading_array;
    }

    public function startRow(): int
    {
        return 2;
    }

    final public function map($row): array
    {
        $heading_array          = $this->heading_array;
        $map_key_value_array    = $this->map_key_value_array;

        $Name_key                   = "0";
        $Combination_Key_Value_key  = "1";
        $Start_Date_key             = "2";
        $End_Date_key               = "3";
        $Area_Name_key              = "4";
        $Channel_key                = "5";
        $Country_key                = "6";
        $Customer_Code_key          = "7";
        $Customer_Category_key      = "8";
        $Item_Code_key              = "9";
        $Item_Uom_key               = "10";
        $Item_Price_key             = "11";
        $Item_Group_key             = "12";
        $Item_Major_Category_key    = "13";
        $Lob_Name_key               = "14";
        $Region_Name_key            = "15";
        $Route_Cod_key              = "16";

        $couter = 0;
        foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
            if ($couter == 0) {
                $Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 1) {
                $Combination_Key_Value_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 2) {
                $Start_Date_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 3) {
                $End_Date_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 4) {
                $Area_Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 5) {
                $Channel_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 6) {
                $Country_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 7) {
                $Customer_Code_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 8) {
                $Customer_Category_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 9) {
                $Item_Code_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 10) {
                $Item_Uom_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 11) {
                $Item_Price_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 12) {
                $Item_Group_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 13) {
                $Item_Major_Category_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 14) {
                $Lob_Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 15) {
                $Region_Name_key = array_search($map_key_value_array_value, $heading_array, true);
            } else if ($couter == 16) {
                $Route_Cod_key = array_search($map_key_value_array_value, $heading_array, true);
            }

            $couter++;
        }

        $map =   [
            '0'  => isset($row[$Name_key]) ? $row[$Name_key] : "",
            '1'  => isset($row[$Combination_Key_Value_key]) ? $row[$Combination_Key_Value_key] : "",
            '2'  => isset($row[$Start_Date_key]) ? $row[$Start_Date_key] : "",
            '3'  => isset($row[$End_Date_key]) ? $row[$End_Date_key] : "",
            '4'  => isset($row[$Area_Name_key]) ? $row[$Area_Name_key] : "",
            '5'  => isset($row[$Channel_key]) ? $row[$Channel_key] : "",
            '6'  => isset($row[$Country_key]) ? $row[$Country_key] : "",
            '7'  => isset($row[$Customer_Code_key]) ? $row[$Customer_Code_key] : "",
            '8'  => isset($row[$Customer_Category_key]) ? $row[$Customer_Category_key] : "",
            '9'  => isset($row[$Item_Code_key]) ? $row[$Item_Code_key] : "",
            '10'  => isset($row[$Item_Uom_key]) ? $row[$Item_Uom_key] : "",
            '11'  => isset($row[$Item_Price_key]) ? $row[$Item_Price_key] : "",
            '12'  => isset($row[$Item_Group_key]) ? $row[$Item_Group_key] : "",
            '13'  => isset($row[$Item_Major_Category_key]) ? $row[$Item_Major_Category_key] : "",
            '14'  => isset($row[$Lob_Name_key]) ? $row[$Lob_Name_key] : "",
            '15'  => isset($row[$Region_Name_key]) ? $row[$Region_Name_key] : "",
            '16'  => isset($row[$Route_Cod_key]) ? $row[$Route_Cod_key] : "",
        ];

        return $map;
    }

    public function model(array $row)
    {
        ++$this->rows;

        $skipduplicate = $this->skipduplicate;

        $this->rowsrecords[] = $row;
    }

    public function rules(): array
    {
        $skipduplicate = $this->skipduplicate;
        if ($skipduplicate == 0) {
            return [
                '0' => 'required',
                '1' => 'required',
                '2' => 'required',
                '3' => 'required',
            ];
        } else {
            return [
                '0' => 'required',
                '1' => 'required',
                '2' => 'required',
                '3' => 'required',
            ];
        }
    }

    public function customValidationMessages()
    {
        return [];
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
