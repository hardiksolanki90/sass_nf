<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithValidation;

class CreditnoteImport implements ToModel, WithValidation, SkipsOnFailure, SkipsOnError, WithMapping
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
		return 3;
	}

	final public function map($row): array
	{
		$heading_array = $this->heading_array;
		$map_key_value_array = $this->map_key_value_array;

		$Credit_Note_Number_key 	= "0";
		$Customer_Name_key 			= "1";
		$Invoice_Number_key 		= "2";
		$Credit_Note_Date_key 		= "3";
		$Route_key 					= "4";
		$Total_Gross_key 			= "5";
		$Total_Discount_Amount_key 	= "6";
		$Total_Net_key 				= "7";
		$Total_Vat_key 				= "8";
		$Total_Excise_key 			= "9";
		$Grand_Total_key 			= "10";
		$Pending_Credit_key 		= "11";
		$Status_key 				= "12";
		$Item_Code_key 				= "13";
		$Item_Uom_key 				= "14";
		$Item_Qty_key 				= "15";
		$Item_Price_key 			= "16";
		$Item_Vat_key 				= "17";
		$Item_Net_key 				= "18";
		$Item_Grand_Total_key 		= "19";

		$couter = 0;
		foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
			if ($couter == 0) {
				$Credit_Note_Number_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 1) {
				$Customer_Name_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 2) {
				$Invoice_Number_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 3) {
				$Credit_Note_Date_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 4) {
				$Route_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 5) {
				$Total_Gross_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 6) {
				$Total_Discount_Amount_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 7) {
				$Total_Net_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 8) {
				$Total_Vat_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 9) {
				$Total_Excise_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 10) {
				$Grand_Total_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 11) {
				$Pending_Credit_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 12) {
				$Status_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 13) {
				$Item_Code_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 14) {
				$Item_Uom_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 15) {
				$Item_Qty_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 16) {
				$Item_Price_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 17) {
				$Item_Vat_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 18) {
				$Item_Net_key = array_search($map_key_value_array_value, $heading_array, true);
			} else if ($couter == 19) {
				$Item_Grand_Total_key = array_search($map_key_value_array_value, $heading_array, true);
			}
			$couter++;
		}

		$map =   [
			'0'  => isset($row[$Credit_Note_Number_key]) ? $row[$Credit_Note_Number_key] : "",
			'1'  => isset($row[$Customer_Name_key]) ? $row[$Customer_Name_key] : "",
			'2'  => isset($row[$Invoice_Number_key]) ? $row[$Invoice_Number_key] : "",
			'3'  => isset($row[$Credit_Note_Date_key]) ? $row[$Credit_Note_Date_key] : "",
			'4'  => isset($row[$Route_key]) ? $row[$Route_key] : "",
			'5'  => isset($row[$Total_Gross_key]) ? $row[$Total_Gross_key] : "",
			'6'  => isset($row[$Total_Discount_Amount_key]) ? $row[$Total_Discount_Amount_key] : "",
			'7'  => isset($row[$Total_Net_key]) ? $row[$Total_Net_key] : "",
			'8'  => isset($row[$Total_Vat_key]) ? $row[$Total_Vat_key] : "",
			'9'  => isset($row[$Total_Excise_key]) ? $row[$Total_Excise_key] : "",
			'10'  => isset($row[$Grand_Total_key]) ? $row[$Grand_Total_key] : "",
			'11'  => isset($row[$Pending_Credit_key]) ? $row[$Pending_Credit_key] : "",
			'12'  => isset($row[$Status_key]) ? $row[$Status_key] : "",
			'13'  => isset($row[$Item_Code_key]) ? $row[$Item_Code_key] : "",
			'14'  => isset($row[$Item_Uom_key]) ? $row[$Item_Uom_key] : "",
			'15'  => isset($row[$Item_Qty_key]) ? $row[$Item_Qty_key] : "",
			'16'  => isset($row[$Item_Price_key]) ? $row[$Item_Price_key] : "",
			'17'  => isset($row[$Item_Vat_key]) ? $row[$Item_Vat_key] : "",
			'18'  => isset($row[$Item_Net_key]) ? $row[$Item_Net_key] : "",
			'19'  => isset($row[$Item_Grand_Total_key]) ? $row[$Item_Grand_Total_key] : "",
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
				'1' => 'required|exists:customer_infos,customer_code',
				'3' => 'required',
				// '4' => 'required|exists:routes,route_name',
				'12' => 'required|in:Yes,No',
				// '13' => 'required|exists:items,item_code',
				// '14'  => 'required|exists:item_uoms,name',
				// '15'  => 'required',
				// '16'  => 'required',
				// '17'  => 'required',
				// '18'  => 'required',
				// '19'  => 'required'
			];
		} else {
			return [
				'0' => 'required',
				'1' => 'required|exists:customer_infos,customer_code',
				'3' => 'required',
				// '4' => 'required|exists:routes,route_name',
				'12' => 'required|in:Yes,No',
				// '13' => 'required|exists:items,item_code',
				// '14'  => 'required|exists:item_uoms,name',
				// '15'  => 'required',
				// '16'  => 'required',
				// '17'  => 'required',
				// '18'  => 'required',
				// '19'  => 'required'
			];
		}
	}
	public function customValidationMessages()
	{
		return [
			'0.required' => 'Credit Note Number required',
			'1.required' => 'Customer Name required',
			'1.exists' => 'Customer not exists',
			'3.required' => 'Credit Note Date required',
			'4.required' => 'Route Name required',
			'4.exists' => 'Route not exists',
			'13.required' => 'Item Code required',
			'13.exists' => 'Item Code not exists',
			'14.required' => 'Item Uom Name required',
			'14.exists' => 'Item Uom  not exists',
			'15.required' => 'Item Qty required',
			'16.required' => 'Item Price required',
			'17.required' => 'Item Vat required',
			'18.required' => 'Item Net required',
			'19.required' => 'Item Grand Total required',
		];
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
