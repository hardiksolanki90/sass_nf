<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Item;
use App\Model\ItemMajorCategory;
use App\Model\ItemUom;
use App\Model\ItemMainPrice;
use App\Model\Brand;
use App\Model\CustomerInfo;
use App\Model\Country;
use App\User;
use App\Model\Region;

class ALBItemController extends Controller
{
	 /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $product_catalog = $request->product_catalog;

        $itemList = Item::with(
            'itemUomLowerUnit:id,name,code',
            'ItemMainPrice:id,item_id,item_upc,item_uom_id,item_price,purchase_order_price,stock_keeping_unit,item_shipping_uom,is_secondary',
            'ItemMainPrice.itemUom:id,name,code',
            'itemMajorCategory:id,uuid,name',
            'itemGroup:id,uuid,name,code,status',
            'brand:id,uuid,brand_name,status',
            'productCatalog'
        );

        if ($request->item_code) {
            $itemList->where('item_code', 'like', '%' . $request->item_code . '%');
        }

        if ($request->item_name) {
            $itemList->where('item_name', 'like', '%' . $request->item_name . '%');
        }

        if ($request->brand) {
            $brand = $request->brand;
            $itemList->whereHas('brand', function ($q) use ($brand) {
                $q->where('brand_name', 'like', '%' . $brand . '%');
            });
        }

        if ($request->category) {
            $category = $request->category;
            $itemList->whereHas('itemMajorCategory', function ($q) use ($category) {
                $q->where('name', 'like', '%' . $category . '%');
            });
        }

        if ($product_catalog) {
            $item = $itemList->where('is_product_catalog', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $item = $itemList->orderBy('id', 'desc')->get();
        }

        $item_array = array();
        if (is_object($item)) {
            foreach ($item as $key => $item1) {
                $item_array[] = $item[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($item_array[$offset])) {
                    $data_array[] = $item_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($item_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($item_array);
        } else {
            $data_array = $item_array;
        }

        return prepareResult(true, $data_array, [], "Item listing", $this->success, $pagination);
    }

    public function delivery_Sap(Request $request){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://c22pas.albatha.com:8018/sap/opu/odata/sap/zgi_mb_download_srv/DeliveryDetailsSet?%24filter=DeliveryOrdDate%20ge%20%2720221123%27%20and%20SalesOrg%20eq%20%271051%27&sap-client=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 80,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic UkZDTU9CSUFUTzphbGJhdGhh',
                'Cookie: SAP_SESSIONID_C22_100=g9mXR1cKIADKBDnKxOocbEgOdmiZDxHulWuL9sC_3CM%3d; sap-usercontext=sap-client=100'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $xml = simplexml_load_string($response);
        foreach ($xml->entry as $entry) {
            $m_elements   = $entry->content->children('m', TRUE);
            $m_properties = $m_elements->properties;
            $d_elements   = $m_properties->children('d', TRUE);
            $data = json_encode($d_elements, true);
            $array = json_decode($data,TRUE);
            $Delivery   = new Delivery;
            $Delivery->salesman_id  = is_array($array['DriverNo']) && empty($array['DriverNo']) ? '' : $array['DriverNo'];
            $Delivery->delivery_number = is_array($array['DeliveryOrdNumber']) && empty($array['DeliveryOrdNumber']) ? '' : $array['DeliveryOrdNumber'];
            $Delivery->delivery_date = is_array($array['DeliveryOrdDate']) && empty($array['DeliveryOrdDate']) ? '' : $array['DeliveryOrdDate'];
            $Delivery->customer_id = is_array($array['SoldtoParty']) && empty($array['SoldtoParty']) ? '' : $array['SoldtoParty'];
            $Delivery->invoice_number = is_array($array['InvoiceNO']) && empty($array['InvoiceNO']) ? '' : $array['InvoiceNO'];
            $Delivery->current_stage = "Approved";
            $Delivery->save();
        }
        return prepareResult(true, [], [], "Delivery store successfully.", $this->success);
    }

    //item sap
    public function item_Sap(Request $request){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://c22pas.albatha.com:8018/sap/opu/odata/sap/zgi_mb_download_srv/itemsSet?%24filter=Delta%20eq%20%27X%27%20and%20DeltaDate%20ge%20%2720231123%27&sap-client=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 80,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic UkZDTU9CSUFUTzphbGJhdGhh',
                'Cookie: SAP_SESSIONID_C22_100=g9mXR1cKIADKBDnKxOocbEgOdmiZDxHulWuL9sC_3CM%3d; sap-usercontext=sap-client=100'
            ),
        ));
        $response = curl_exec($curl);
        
        curl_close($curl);
        //$response = $request->getContent();
        $xml = simplexml_load_string($response);
        foreach ($xml->entry as $entry) {
            $m_elements   = $entry->content->children('m', TRUE);
            $m_properties = $m_elements->properties;
            $d_elements   = $m_properties->children('d', TRUE);
            $data = json_encode($d_elements, true);
            $array = json_decode($data,TRUE);
            //dd($array);
            $uomList = [];
            $uom1    = array();
            if($array['LOWERUNIT'] != $array['UOM1']){
                $uom1['uom'] = $array['UOM1'];
                $uom1['uom_upc'] = $array['NUMERATOR1'];
                $uomList[]     = $uom1;
            }

            if($array['UOM2']!=$array['UOM1'] && $array['LOWERUNIT'] != $array['UOM2']){
                $uom1['uom'] = $array['UOM2'];
                $uom1['uom_upc'] = $array['NUMERATOR2'];
                $uomList[]     = $uom1;
            }

            if($array['LOWERUNIT'] != $array['UOM3'] && $array['UOM2']!=$array['UOM3'] && $array['UOM1'] != $array['UOM3']){
                $uom1['uom'] = $array['UOM3'];
                $uom1['uom_upc'] = $array['NUMERATOR3'];
                $uomList[]     = $uom1;
            }

            if($array['LOWERUNIT'] != $array['UOM4'] && $array['UOM2']!=$array['UOM4'] && $array['UOM1'] != $array['UOM4'] && $array['UOM3'] != $array['UOM4']){
                $uom1['uom'] = $array['UOM4'];
                $uomList[]     = $uom1;
                $uom1['uom_upc'] = $array['NUMERATOR4'];
            }
            
            
            $brand = Brand::where('brand_name', $array['MGROUPDESC'])->first();
            if(!is_array($array['MGROUPDESC']) && is_null($brand)){
                $brand = new Brand;
                $brand->brand_name = $array['MGROUPDESC'];
                $brand->save();
            }

            $ItemUom = ItemUom::where('name', $array['LOWERUNIT'])->first();
            if(!is_array($array['LOWERUNIT']) && is_null($ItemUom)){
                $ItemUom = new ItemUom;
                $ItemUom->name = $array['LOWERUNIT'];
                $ItemUom->code = nextComingNumber('App\Model\ItemUom', 'item_uoms', 'code', $array['LOWERUNIT']);
                $ItemUom->save();
            }

            $ItemMajorCategory = ItemMajorCategory::where('name', $array['Type'])->first();
            if(is_null($ItemMajorCategory)){
                $ItemMajorCategory = new ItemMajorCategory;
                $ItemMajorCategory->name = $array['Type'];
                $ItemMajorCategory->save();
            }
            $item = Item::where(
                [
                    'item_code'        => $array['Material']
                ])->first();
            if(is_null($item)){
                $item  = new Item;
                $item->item_code = $array['Material'];
                $item->item_name = $array['Name1'];
                $item->item_description = $array['Name1'];
                $item->item_barcode = $array['EAN'];
                $item->brand_id = $brand->id;
                $item->lower_unit_uom_id = $ItemUom->id;
                $item->lower_unit_item_upc = 1;
                $item->lower_unit_item_price = $array['Price'];
                
                $item->item_major_category_id = $ItemMajorCategory->id;
                $item->save();

                
            }else{
                $item  = Item::find($item->id);
                $item->item_code = $array['Material'];
                $item->item_name = $array['Name1'];
                $item->item_description = $array['Name1'];
                $item->item_barcode = $array['EAN'];
                $item->brand_id = $brand->id;
                $item->lower_unit_uom_id = $ItemUom->id;
                $item->lower_unit_item_upc = 1;
                $item->lower_unit_item_price = $array['Price'];
                
                $item->item_major_category_id = $ItemMajorCategory->id;
                $item->save();


            }
            $this->saveItemMainPrice($item, $uomList);
        }
        return prepareResult(true, [], [], "Item store successfully.", $this->success);
    }

    private function saveItemMainPrice($item, $uomList)
    {
        
        foreach ($uomList as $key => $value) {
            $ItemUom = ItemUom::where('name', $value['uom'])->first();
            if(is_null($ItemUom)){
                $ItemUom = new ItemUom;
                $ItemUom->name = $value['uom'];
                $ItemUom->code = nextComingNumber('App\Model\ItemUom', 'item_uoms', 'code', $value['uom']);
                $ItemUom->save();
            }
            $item_main_price = ItemMainPrice::where([
                'item_id'               => $item->id,
                'item_uom_id'           => $ItemUom->id
            ])->first();

            if(is_null($item_main_price)){
                $item_main_price                        = new ItemMainPrice;
                $item_main_price->item_id               = $item->id;
                $item_main_price->item_upc              = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->item_uom_id           = $ItemUom->id;
                $item_main_price->item_price            = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->purchase_order_price  = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->save();
            }else{
                $item_main_price                        = ItemMainPrice::find($item_main_price->id);
                $item_main_price->item_id               = $item->id;
                $item_main_price->item_upc              = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->item_uom_id           = $ItemUom->id;
                $item_main_price->item_price            = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->purchase_order_price  = $item->lower_unit_item_price * $value['uom_upc'];
                $item_main_price->save();
            }
        }
    }

    public function storeCustomeSap(Request $request){
        $date = str_replace("'", "", date('Ymd'));
        //dd('http://c22pas.albatha.com:8018/sap/opu/odata/sap/ZGI_MB_DOWNLOAD_SRV/CustomerHeaderSet?$filter=Delta%20eq%20%27X%27%20and%20DeltaDate%20ge%20%27'.$date.'%27&$expand=CustomerSalesAreas,CustomerSalesAreas,CustomerFlags,CustomerCredit&sap-client=100*/');
        
    	$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://c22pas.albatha.com:8018/sap/opu/odata/sap/ZGI_MB_DOWNLOAD_SRV/CustomerHeaderSet?$filter=Delta%20eq%20%27X%27%20and%20DeltaDate%20ge%20%27    %27&$expand=CustomerSalesAreas,CustomerSalesAreas,CustomerFlags,CustomerCredit&sap-client=100*/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 80,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic UkZDTU9CSUFUTzphbGJhdGhh',
                'Cookie: SAP_SESSIONID_C22_100=g9mXR1cKIADKBDnKxOocbEgOdmiZDxHulWuL9sC_3CM%3d; sap-usercontext=sap-client=100'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = $request->getContent();
        $data = json_decode($response, true);
        if(isset($data['d']['results'])){
            foreach ($data['d']['results'] as $key => $value) {
                $customerInfo = CustomerInfo::where('customer_code', $value['CustNo'])->first();
                if(is_null($customerInfo)){
                    $customerInfo = new CustomerInfo;
                    $this->updateCustomer($value, $customerInfo);
                }else{
                     $customerInfo = CustomerInfo::find($customerInfo->id);
                    $this->updateCustomer($value, $customerInfo);
                }
                //dd($value);
            }
            //dd($data['d']['results']);
        }
        return prepareResult(true, [], [], "Customer store successfully.", $this->success);
        //dd($data);
    }

    private function updateCustomer($value, $customerInfo)
    {
        $country      = Country::where(['country_code' => $value['CountryCode']])->first();
        $user = User::where('email', $value['CustNo'].'@albatha.com')->first();
        if(is_null($user)){
            $user = new User;
            $user->usertype = 2;
            $user->parent_id = 0;
            $user->firstname = $value['Name1'];
            $user->lastname = '';
            $user->email = $value['CustNo'].'@albatha.com';
            $user->password = \Hash::make('abcdefg');
            $user->mobile = $value['PhoneNumber'];
            $user->country_id = $country->id;
            $user->api_token = \Str::random(35);
            $user->status = 1;
            $user->save();
        }

        
        $customerInfo->user_id = $user->id;
        $region = Region::where('region_code', $value['Regio'])->first();
        if (is_null($region)) {
            $region = new Region;
            $region->country_id = $country->id;
            $region->region_code = $value['Regio'];
            $region->region_name = $value['RegioDesc'];
            $region->region_status = 1;
            $region->save();

        }
        $customerInfo->region_id = $region->id;
        $customerInfo->country_id    = $country->id;
                           
        $customerInfo->customer_code = $value['CustNo'];                    
        $customerInfo->payment_term_id = 4;
        $customerInfo->customer_city = $value['City'];
        $customerInfo->customer_state = $value['District'];
        $customerInfo->customer_zipcode = $value['PostCode'];
        $customerInfo->customer_phone = $value['PhoneNumber'];
        $customerInfo->credit_days = 30;
        $customerInfo->current_stage = "Approved";
        
        $customerInfo->status = 1;
        if(isset($value['CustomerSalesAreas']['results'][0])){
            $channel_name = $value['CustomerSalesAreas']['results'][0]['CustomerGroup2Desc'];
            $channel = \App\Model\Channel::where('name', $channel_name)->first();
            if (is_null($channel)) {
                $channel = new \App\Model\Channel;
                $channel->name = $channel_name;
                $channel->status = 1;
                $channel->save();
            }
            $cg = \App\Model\CustomerGroup::where('group_code', $value['CustomerSalesAreas']['results'][0]['CustomerGroup'])->first();
            if(is_null($cg)){
                $cg = new \App\Model\CustomerGroup;
                $cg->group_code = $value['CustomerSalesAreas']['results'][0]['CustomerGroup'];
                $cg->group_name = $value['CustomerSalesAreas']['results'][0]['CustomerGroupDesc'];
                $cg->save();
            }
            $customerInfo->customer_group_id = $cg->id;
            $customerInfo->channel_id = $channel->id;

            $cc   = \App\Model\CustomerCategory::where('customer_category_code', $value['CustomerSalesAreas']['results'][0]['CustomerGroup3'])->first();
            if(is_null($cc)){
                $cc    = new \App\Model\CustomerCategory;
                $cc->customer_category_code = $value['CustomerSalesAreas']['results'][0]['CustomerGroup3'];
                $cc->customer_category_name = $value['CustomerSalesAreas']['results'][0]['CustomerGroup3Desc'];
                $cc->save();
            }
             $customerInfo->customer_category_id = $cc->id;
        }

        $customerInfo->save();
        $cmInfo = CustomerInfo::find($customerInfo->id);
        if(isset($value['CustomerSalesAreas']['results'][0])){
            $sold_to_party = CustomerInfo::where('customer_code', $value['CustomerSalesAreas']['results'][0]['SoldToNo'])->first();
            $bill_to_payer = CustomerInfo::where('customer_code', $value['CustomerSalesAreas']['results'][0]['BillToNo'])->first();
            $ship_to_party = CustomerInfo::where('customer_code', $value['CustomerSalesAreas']['results'][0]['ShipToNo'])->first();

             $PayerNo = CustomerInfo::where('customer_code', $value['CustomerSalesAreas']['results'][0]['PayerNo'])->first();

            $cmInfo->ship_to_party = $ship_to_party->id ?? $customerInfo->id;
            $cmInfo->sold_to_party = $sold_to_party->id ?? $customerInfo->id;
            $cmInfo->payer         = $PayerNo->id ?? $customerInfo->id;
            $cmInfo->bill_to_payer = $bill_to_payer->id ?? $customerInfo->id;
            $cmInfo->current_stage = "Approved";
        }else{
            $cmInfo->ship_to_party =  $customerInfo->id;
            $cmInfo->sold_to_party =  $customerInfo->id;
            $cmInfo->payer         =  $customerInfo->id;
            $cmInfo->bill_to_payer =  $customerInfo->id;
        }

        $cmInfo->save();
        
    }
}
?>