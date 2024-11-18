<?php

namespace App\Exports;

use App\Model\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class OrderExport implements FromCollection, WithHeadings
{
	/**
	 * @return \Illuminate\Support\Collection
	 */
	protected $StartDate, $EndDate, $storage_location_id;

	public function __construct(String  $StartDate, String $EndDate, int $storage_location_id)
	{
		$this->StartDate = $StartDate;
		$this->EndDate = $EndDate;
		$this->storage_location_id = $storage_location_id;
	}

	public function collection()
	{
		$start_date = $this->StartDate;
		$end_date = $this->EndDate;
		$storage_location_id = $this->storage_location_id;


		$orders_query = Order::with(array('customer' => function ($query) {
			$query->select('id', 'firstname', 'lastname', \DB::raw("CONCAT('firstname','lastname') AS display_name"));
		}))
			->with(
				'customer:id,firstname,lastname',
				'customer.customerInfo:id,user_id,customer_code',
				'salesman:id,firstname,lastname',
				'salesman.salesmanInfo:id,user_id,salesman_code',
				'orderType:id,name,description',
				'paymentTerm:id,name,number_of_days',
				'orderDetails',
				'orderDetails.item:id,item_name,item_code',
				'orderDetails.itemUom:id,name,code',
				'depot:id,depot_name'
			);

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$orders_query->whereDate('created_at', $end_date);
			} else {
				$endDate = Carbon::parse($end_date)->addDays()->format('Y-m-d');
				$orders_query->whereBetween('created_at', [$start_date, $endDate]);
			}
		}

		$user = auth()->user();
		if ($user->usertype == 2) {
			$orders_query->where('customer_id', auth()->user()->id);
		}

		if ($storage_location_id) {
			$orders_query->where('storage_location_id', $storage_location_id);
		}

		$orders = $orders_query->get();


		$orderCollection = new Collection();
		if (count($orders)) {
			foreach ($orders as $order) {
				foreach ($order->orderDetails as $detail) {

					$orderCollection->push((object) [
						'order_number' 				=> $order->order_number,
						'customer_code' 			=> model($order->customerInfo, 'customer_code'),
						'customer_name' 			=> model($order->customer, 'firstname') . ' ' . model($order->customer, 'lastname'),
						'order_date' 				=> $order->order_date,
						'delivery_date'				=> $order->delivery_date,
						'salesman_code' 			=> model($order->salesmanInfo, 'salesman_code'),
						'salesman_name' 			=> model($order->salesman, 'firstname') . ' ' . model($order->salesman, 'lastname'),
						'order_type' 				=> model($order->orderType, 'name'),
						'payment_terms' 			=> model($order->paymentTerm, 'name'),
						'due_date'					=> $order->due_date,
						'total_gross'				=> $order->total_gross,
						'total_discount_amount'		=> $order->total_discount_amount,
						'total_net'					=> $order->total_net,
						'total_vat'					=> $order->total_vat,
						'total_excise'				=> $order->total_excise,
						'grand_total'				=> $order->grand_total,
						'any_comment'				=> $order->any_comment,
						'status'					=> $order->status,
						'current_stage'				=> $order->current_stage,
						'item_name'					=> model($detail->item, 'item_name'),
						'item_code'					=> model($detail->item, 'item_code'),
						'item_uom'					=> model($detail->itemUom, 'name'),
						'is_free'					=> $detail->is_free,
						'is_item_poi'				=> $detail->is_item_poi,
						'item_qty'					=> $detail->item_qty,
						'item_price'				=> $detail->item_price,
						'item_gross'				=> $detail->item_gross,
						'item_discount_amount'		=> $detail->item_discount_amount,
						'item_net'					=> $detail->item_net,
						'item_vat'					=> $detail->item_vat,
						'item_excise'				=> $detail->item_excise,
						'item_grand_total'			=> $detail->item_grand_total
					]);
				}
			}
		}

		return $orderCollection;
	}

	public function headings(): array
	{
		return [
			"Order Number",
			"Customer Code",
			"Customer Name",
			"Order Date",
			"Requested Date",
			"Salesman Code",
			"Salesman Name",
			"Order Type",
			"Payment Term",
			"Due Date",
			"Total Gross",
			"Total Discount amount",
			"Total Net",
			"Total Vat",
			"Total Excise",
			"Grand Total",
			"Any Comment",
			"Order Status",
			"Current Stage",
			"Item Name",
			"Item Code",
			"Item Uom",
			"Is Free",
			"Is Item Poi",
			"Item Qty",
			"Item Price",
			"Item Gross",
			"Item Discount amount",
			"Item Net",
			"Item Vat",
			"Item Excise",
			"Item Grand Total",
		];
	}
}
