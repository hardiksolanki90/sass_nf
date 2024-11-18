<?php

namespace App\Exports;

use App\User;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\Order;
use App\Model\OrderType;
use App\Model\Delivery;
use App\Model\PaymentTerm;
use App\Model\PriceDiscoPromoPlan;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class InvoiceExport implements FromCollection, WithHeadings
{
	/**
	 * @return \Illuminate\Support\Collection
	 */
	protected $StartDate, $EndDate;

	public function __construct(String  $StartDate, String $EndDate)
	{
		$this->StartDate = $StartDate;
		$this->EndDate = $EndDate;
	}

	public function collection()
	{
		$start_date = $this->StartDate;
		$end_date = $this->EndDate;

		$invoice = Invoice::select('*');

		if ($start_date != '' && $end_date != '') {
			if ($start_date == $end_date) {
				$invoice->whereDate('created_at', $start_date);
			} else {
				$e_date = Carbon::parse($end_date)->addDay()->format('Y-m-d');
				$invoice->whereBetween('created_at', [$start_date, $e_date]);
			}
		}

		$invoices = $invoice->get();

		$InvoicesCollection = new Collection();
		if (is_object($invoices)) {
			foreach ($invoices as $invoice) {
				$invoicedetails = InvoiceDetail::where('invoice_id', $invoice->id)->get();
				if (is_object($invoicedetails)) {
					foreach ($invoicedetails as $invoicedetail) {

						$customer = User::find($invoice->customer_id);
						$item = Item::find($invoicedetail->item_id);
						$itemuom = ItemUom::find($invoicedetail->item_uom_id);

						if ($invoice->oddo_post_id !== NULL) {
							$erp_status = "Posted";
						} else if (($invoice->odoo_failed_response === NULL) && ($invoice->oddo_post_id === NULL)) {
							$erp_status = "Not Posted";
						} else if ($invoice->odoo_failed_response !== NULL) {
							$erp_status = "Failed";
						}

						$InvoicesCollection->push((object)[
							'invoice_number' => $invoice->invoice_number,
							'order_number'	=> model($invoice->order, 'order_number'),
							'customer_name'	=> ($customer) ? $customer->getName() : "",
							'customer_code'	=> ($customer) ? ($customer->customerInfo) ? $customer->customerInfo->customer_code : "" : "",
							'invoice_date' => $invoice->invoice_date,
							'total_gross' => $invoice->total_gross,
							'total_discount_amount' => $invoice->total_discount_amount,
							'total_net' => $invoice->total_net,
							'total_vat' => $invoice->total_vat,
							'total_excise' => $invoice->total_excise,
							'grand_total' => $invoice->grand_total,
							'item_code' => (is_object($item)) ? $item->item_code : "",
							'item' => (is_object($item)) ? $item->item_name : "",
							'itemuom' => (is_object($itemuom)) ? $itemuom->name : "",
							'item_qty' => $invoicedetail->item_qty,
							'base_price' => ($invoicedetail->base_price > 0) ? $invoicedetail->base_price : $invoicedetail->item_price,
							'item_price' => $invoicedetail->item_price,
							'item_gross' => $invoicedetail->item_gross,
							'item_discount_amount' => $invoicedetail->item_discount_amount,
							'item_net' => $invoicedetail->item_net,
							'item_vat' => $invoicedetail->item_vat,
							'item_excise' => $invoicedetail->item_excise,
							'item_grand_total' => $invoicedetail->item_grand_total,
							'erp_status' => $erp_status
						]);
					}
				}
			}
		}
		return $InvoicesCollection;
	}
	public function headings(): array
	{
		return [
			"Invoice Number",
			"Order Number",
			"Customer Name",
			"Customer Code",
			"Invoice Date",
			"Total Gross",
			"Total discount amount",
			"Total net",
			"Total vat",
			"Total Excise",
			"Grand Total",
			"Item Code",
			"Item",
			"Item UOM",
			"Qty",
			"Customer Price",
			"Base Price",
			"Gross",
			"Discount Amount",
			"Net",
			"Item Vat",
			"Excise",
			"Grand Total",
			"ERP Stattis"

			// 'Invoice Number',
			// 'Invoice Type',
			// 'Invoice Date',
			// 'Invoice due date',
			// 'Total Qty',
			// 'Total Gross',
			// 'Total discount amount',
			// 'Total net',
			// 'Total vat',
			// 'Total Excise',
			// 'Grand Total',
			// 'Status',
			// 'Source',
			// 'Customer Email',
			// 'Order Number',
			// 'Order Type',
			// 'Delivery Number',
			// 'Payment Term',
			// 'Item',
			// 'Item UOM',
			// 'Discount',
			// 'Is Free',
			// 'Is Item POI',
			// 'Promotion',
			// 'Qty',
			// 'Price',
			// 'Gross',
			// 'Discount Amount',
			// 'Net',
			// 'Excise',
			// 'Grand Total',
		];
	}
}
