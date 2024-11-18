<?php

namespace App\Jobs;

use App\Imports\DeliveryImport;
use App\Model\PickingSlipGenerator;
use App\Model\CustomerInfo;
use App\Model\SalesmanUnload;
use App\Model\Delivery;
use App\Model\DeliveryAssignTemplate;
use App\Model\DeliveryDetail;
use App\Model\DeliveryDriverJourneyPlan;
use App\Model\DeliveryLog;
use App\Model\DeliveryNote;
use App\Model\DeviceDetail;
use App\Model\CustomerRegion;
use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\ItemUom;
use App\Model\LoadItem;
use App\Model\Group;
use App\Model\GroupCustomer;
use App\Model\CustomerGroupMail;
use App\Model\Notifications;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\OrganisationRole;
use App\Model\rfGenView;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanLoad;
use App\Model\SalesmanLoadDetails;
use App\Model\SalesmanVehicle;
use Carbon\Carbon;
use App\Model\VehicleUtilisation;
use App\Model\Warehouse;
use App\Model\WarehouseDetail;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowRuleApprovalUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Mail\Mailer;
use App\Jobs\File;



class DeliveryUpdateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $file_location;
    private $is_header_level;
    private $username;
    private $useremail;
    private $userid;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file_location, $is_header_level,$username,$useremail,$userid)
    {
       
        $this->file_location = $file_location;
        $this->is_header_level = $is_header_level;
        $this->username = $username;
        $this->useremail = $useremail;
        $this->userid = $userid;
     
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Mailer $mailer)
    {
      
        $is_header_level=$this->is_header_level;
        $item_array = [];
        $customer_code_array = [];
        $van_array = [];
        $errors=null;
        $faild_to_post[]=array();
        $delivery_ids = [];
        $old_delivery_id = "";
        $file = fopen($this->file_location, "r");
        $i=0;
        $count=0;
        while (($row = fgetcsv($file, 10000, ",")) !== false) {
           
            if($i!=0){
                
             if ($is_header_level == 0) {
            
                 if (isset($row[0]) && $row[0] != "Order No") {
                     // if (isset($row[13]) && $row[13]) {
                     //     return prepareResult(false, [], ['error' => 'You template file is sku level and you choose header level format.'], "You template file is sku level and you choose header level format.", false);
                     // }

                     if ($row[0] == "") {
                         $errors = "Order Number is not added.";
                     }

                     if ($row[1] == "") {
                         $errors = "Cusotmer is not added.";
                     }

                     if ($row[3] == "") {
                         $errors = "LPO Raised Date is not added.";
                     }

                     if ($row[4] == "") {
                         $errors = "LPO Request Date is not added.";
                     }

                     // if ($row[5] == '') {
                     //     $errors = "Customer LPO No is not added.";
                     // }

                     if ($row[7] == "") {
                         $errors = "Extended Amount is not added.";
                     }

                     if ($row[8] == "") {
                         $errors = "Delivery Sequence is not added.";
                     }

                     if ($row[9] == "") {
                         $errors = "Trip is not added.";
                     }

                     if ($row[10] == "") {
                         $errors = "Driver code is not added.";
                     }

                     if ($row[11] == "") {
                         $errors = "Last trip is not added.";
                     }

                     if ($row[13] == "") {
                         $errors = "On Hold is not added.";
                     }

                 
                     $onHold = $row[17];
                     $order = Order::where("order_number","like","%$row[0]%")->first();
                    
                     $customerInfo = CustomerInfo::where("customer_code",$row[1])->first();
                    
                     // $van = Van::where('van_code', 'like', "%$row[10]%")->first();
                     $salesmanInfo = SalesmanInfo::where("salesman_code",$row[12])->first();
                
                     $order_error = [];
                     if (!$order) {
                        
                             $order_error = $row[0];
                             $errors =
                                 "Order Number does not exist " . $row[0];
                         
                     }

                     if ($order->approval_status == "Cancelled") {

                        $errors ="The order has been cancelled " . $order->order_number;
                       
                     }


                     if (!$customerInfo) {
                        $customer_code_array[] = $row[1];
                        $errors ="Cusotmer does not match " .$row[1];
                    
                      }
                   
                     if (is_object($order) && is_object($order->cusotmerInfo) ) {

                         if ( $order->cusotmerInfo->cusotmer_code != $row[1] ) {
                             
                                $customer_code_array[] = $row[1];
                                 $errors =
                                     "Cusotmer is not match with order " .
                                     $row[1];
                             
                         }
                     }

                     $salesman_code_array = [];

                   if (!$salesmanInfo) {
                        
                             $salesman_code_array[] = $row[12];
                             $errors =
                                 "Salesman does not exist " . $row[12] ."For This Order".$row[0];
                         
                     } 

                     // if (!$van) {
                     //     if (!in_array($row[10], $van_array)) {
                     //         $van_array[] = $row[10];
                     //         $errors = "Vehicle does not exist " . $row[10];
                     //     }
                     // }

                     if (!$errors) {
                         if (is_object($order)) {
                             if ($onHold == "Yes") {
                                 $delivery_exist = Delivery::where( "order_id",$order->id)
                                     ->where("approval_status", "=","Shipment")
                                     ->first();
                                     
                                 if (is_object($delivery_exist)) {

                                     if (is_object($salesmanInfo)) {

                                         $delivery_exist->salesman_id =$salesmanInfo->user_id;
                                         $delivery_exist->save();

                                         DeliveryAssignTemplate::where( "delivery_id", $delivery_exist->id)
                                                    ->update([
                                                        "delivery_driver_id" =>
                                                        $salesmanInfo->user_id,
                                                    ]);

                                         DeliveryAssignTemplate::where("delivery_id",$delivery_exist->id )
                                                    ->update([
                                                        "delivery_sequence" => $row[10],
                                                    ]);

                                         DeliveryAssignTemplate::where("delivery_id", $delivery_exist->id)
                                                ->update([
                                                    "trip" => $row[11],
                                                ]);
    
                                         SalesmanLoad::where("delivery_id",$delivery_exist->id)
                                                ->update([
                                                    "salesman_id" =>
                                                    $salesmanInfo->user_id,
                                                ]);
    
                                         SalesmanLoad::where( "delivery_id",$delivery_exist->id)
                                                    ->update([
                                                        "trip_number" => $row[11],
                                                    ]);
                                     }
                                   } else {
                                             $errors=$order->order_number ." delivery is already completed";
                                 }
                                 
                             } else {
                                 $delivery_exist = Delivery::where("order_id", $order->id )
                                     ->where( "approval_status", "!=","Shipment")
                                     ->where( "approval_status", "!=","Truck Allocated")
                                     ->where( "transportation_status", "!=","Delegated")
                                     ->first();

                                 if (is_object($delivery_exist)) {

                                     // check is shipment is generated
                                     $slCheck = SalesmanLoad::where('delivery_id', $delivery_exist->id)->first();
                                     if ($slCheck) {
                                         continue;
                                     }
                                     // delete assign record is second time upload
                                     DeliveryAssignTemplate::where("delivery_id", $delivery_exist->id)->delete();

                                     DeliveryDetail::where( "delivery_id",$delivery_exist->id)->update(["transportation_status" => "No",]);

                                     $delivery_exist->update([ "approval_status" => "Created", ]);

                                     if ( is_object($customerInfo) && is_object($salesmanInfo) ) {

                                         $delivery_exist->customer_id = $customerInfo->user_id;
                                         $delivery_exist->salesman_id =$salesmanInfo->user_id;
                                         $delivery_exist->save();

                                         $dd = DeliveryDetail::where("delivery_id", $delivery_exist->id)->get();
                                       
                                         if (count($dd)) {
                                             foreach ($dd as $d) {
                                                 $this->saveHeaderDeliveryAssignTemplate(
                                                     $delivery_exist,
                                                     $d,
                                                     $salesmanInfo,
                                                     $row
                                                 );
                                             }
                                         }
                                     }

                                     $delivery_exist->transportation_status = "Delegated";
                                     $delivery_exist->approval_status ="Truck Allocated";
                                     $delivery_exist->is_truck_allocated = 1;
                                     $delivery_exist->save();

                                     $data = [
                                         "created_user" => request()->user()->id,
                                         "order_id" => $delivery_exist->order_id,
                                         "delviery_id" => $delivery_exist->id,
                                         "updated_user" => $request->user()->id,
                                         "previous_request_body" => null,
                                         "request_body" => $delivery_exist,
                                         "action" => "Delivery TEMPLATE",
                                         "status" => "Created",
                                     ];

                                     saveOrderDeliveryLog($data);

                                     $checkOrder = Order::find( $delivery_exist->order_id);

                                     if ( $checkOrder->order_generate_picking===1) {
                                           Order::where("id",$delivery_exist->order_id)->update([
                                                    "transportation_status" =>"Delegated",
                                                    "approval_status" =>"Truck Allocated",
                                              ]);
                                     } else {

                                         Order::where("id",$delivery_exist->order_id)->update(["transportation_status" =>"Delegated", ]);
                                     }

                                     if ( !in_array($delivery_exist->id,$delivery_ids)) {
                                         $delivery_ids[] =$delivery_exist->id;
                                     }
                                 } else {

                                    $errors= $order->order_number ." delivery is not generated either its shipped";
                                 
                                 }
                             }
                         }
                     }
                 }
             } else {
     
                 if (isset($row[0]) && $row[0] != "Order No") {
                     // if (!isset($row[13]) && !$row[13]) {
                     //     return prepareResult(false, [], ['error' => 'You template file is header level and you choose sku level format.'], "You template file is header level and you choose sku level format.", false);
                     // }
             
                     if ($row[0] == "") {
                         $errors = "Order Number is not added.";
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                     if ($row[1] == "") {
                         $errors = "Cusotmer is not added. for the order number ".$row[0] ;
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                     if ($row[6] == "") {
                         $errors = "Item code is not added. for the order number ".$row[0];
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                     if ($row[14] == "") {
                         $errors = "Item Uom is not added. for the order number ".$row[0];
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                     // if ($row[12] == '') {
                     //     $errors = "Vehicel is not added. for the order number ".$row[0];
                     // }

                     if ($row[12] == "") {
                         $errors = "Delivery Driver is not added. for the order number ".$row[0];
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                     if ($row[17] == "") {
                         $errors = "On Hold is not added. for the order number ".$row[0];
                         $faild_to_post[$count]['reason']=$errors;
                         $faild_to_post[$count]['order_number']=$row[0];
                         $count++;
                         continue;
                     }

                    

                     $onHold = $row[17];
                     
                     $order = Order::where("order_number","like","%$row[0]%")->first();
                   
                     $order_error = [];
                     if (!$order) {
                        
                             $order_error = $row[0];
                             $errors =
                                 "Order Number does not exist " . $row[0];

                                 $faild_to_post[$count]['reason']=$errors;
                                 $faild_to_post[$count]['order_number']=$row[0];
                                 $count++;
                                 continue;
                         
                     }

                     if ($order->approval_status == "Cancelled") {

                        $errors = "The order has been cancelled " .$order->order_number;

                        $faild_to_post[$count]['reason']=$errors;
                        $faild_to_post[$count]['order_number']=$row[0];
                        $count++;
                        continue;
                       
                        
                     }

                     $salesmanInfo = SalesmanInfo::where("salesman_code",$row[12])->first();
                  
                     $salesman_code_array = [];

                     if (!$salesmanInfo) {
                               $salesman_code_array[] = $row[12];
                               $errors =" Salesman does not exist " . $row[12] ." For This Order ". $row[0];

                               $faild_to_post[$count]['reason']=$errors;
                               $faild_to_post[$count]['order_number']=$row[0];
                               $count++;
                               continue;
                           
                       } 

                   

                     // $van = Van::where('van_code', 'like', "%$row[12]%")
                     //     ->first();

                     // if (!$van) {
                     //     if (!in_array($row[12], $van_array)) {
                     //         $van_array[] = $row[12];
                     //         $errors = "Vehicle does not exist " . $row[12];
                     //     }
                     // }

                     $customerInfo = CustomerInfo::where("customer_code",$row[1] )->first();
                     if (!$customerInfo) {
                        $customer_code_array[] = $row[1];
                        $errors =$row[1] ."Cusotmer does not Found for the order" .$row[0];

                        $faild_to_post[$count]['reason']=$errors;
                        $faild_to_post[$count]['order_number']=$row[0];
                        $count++;
                        continue;
                    
                      }

                     if (is_object($order) && is_object($order->cusotmerInfo) ) {

                         if ( $order->cusotmerInfo->cusotmer_code != $row[1]) {
                               $customer_code_array[] = $row[1];
                               $errors ="Cusotmer is not match with order " .$row[1];

                               $faild_to_post[$count]['reason']=$errors;
                               $faild_to_post[$count]['order_number']=$row[0];
                               $count++;
                               continue;
                             
                         } 
                     }
                   
                     $item = Item::where("item_code",$row[6])->first();
                     
                     if (is_object($order)) {
                         if (!$item) {
                             $errors =$row[6]." Entered item is not in the order " . $row[0];

                             $faild_to_post[$count]['reason']=$errors;
                             $faild_to_post[$count]['order_number']=$row[0];
                             $count++;
                             continue;
                     
                         }

                         $order_details_array = $order->orderDetails
                             ->pluck("item_id")
                             ->toArray();
                           
                         if (!in_array($item->id, $order_details_array)) {
                             $item_array[] = $row[6];
                             $errors =$row[6]. " Entered item is not in the order " . $row[0];

                             $faild_to_post[$count]['reason']=$errors;
                             $faild_to_post[$count]['order_number']=$row[0];
                             $count++;
                             continue;
                         }
                     } else {
                         if (!$item) {
                             $item_array[] = $row[6];
                             $errors =" Entered item does not exitst " . $row[6];

                             $faild_to_post[$count]['reason']=$errors;
                             $faild_to_post[$count]['order_number']=$row[0];
                             $count++;
                             continue;
                         }
                     }

                         if (is_object($order)) {
                             if ($onHold == "Yes") {
                                 $delivery_exist = Delivery::where( "order_id",$order->id)
                                     // ->where('approval_status', '!=', 'Shipment')
                                     ->first();
                                 if (is_object($delivery_exist)) {
                                     if (is_object($customerInfo) && is_object($salesmanInfo)) {

                                         $delivery_exist->salesman_id =$salesmanInfo->user_id;
                                         $delivery_exist->save();

                                         if ($item) {
                                             $delivery_details = DeliveryDetail::where("delivery_id", $delivery_exist->id)
                                                        ->where("item_id", $item->id )
                                                        ->where("item_qty", "!=", 0)
                                                        ->where("item_price","!=",0)
                                                        ->where("is_deleted", 0)
                                                        ->get();
                                          
                                             if (count($delivery_details) > 1) {
                                                 $delivery_details = DeliveryDetail::where( "delivery_id",$delivery_exist->id)
                                                        ->where("item_id", $item->id)
                                                        ->where("item_qty","!=", 0)
                                                        ->where("item_price","!=", 0)
                                                        ->where("is_deleted", 0)
                                                        ->where( "transportation_status","No")
                                                        ->first();
                                             } else {
                                                      $delivery_details = $delivery_details->first();
                                              }

                                             if ($delivery_details) {
                                                     $uom = ItemUom::where("name","like","%$row[14]%")->first();

                                                      DeliveryAssignTemplate::where("delivery_id",  $delivery_exist->id)
                                                        ->where("item_id", $item->id)
                                                        ->where("item_uom_id",$uom->id)
                                                        ->where("qty", $row[8])
                                                        ->update([ 
                                                                    "delivery_driver_id" => $salesmanInfo->user_id, 
                                                                    "delivery_sequence" => $row[10], 
                                                                    "trip"=> $row[11],
                                                                ]);

                                                    $dsaas = DeliveryAssignTemplate::where("delivery_id", $delivery_exist->id)
                                                                ->where("item_id",$item->id)->where( "item_uom_id", $uom->id)
                                                                ->where("qty", $row[8])
                                                                ->first();

                                                    $loaddetail = SalesmanLoadDetails::where("item_id", $item->id )
                                                                                    ->where("load_qty", number_format($row[8],2))
                                                                                    ->where("dat_id",$dsaas->id)
                                                                                    ->get();
                                                   if ($loaddetail) {
                                                           $loaddetail = $loaddetail->first();

                                                     SalesmanLoad::where( "id", $loaddetail->salesman_load_id)
                                                                    ->update([
                                                                              "salesman_id" =>  $salesmanInfo->user_id,
                                                                            ]);

                                                     SalesmanLoad::where( "id",$loaddetail->salesman_load_id )
                                                                    ->update([
                                                                        "trip_number" =>
                                                                        $row[11],
                                                                    ]);
                                                     SalesmanLoadDetails::where("item_id",$item->id)
                                                         ->where("load_qty",number_format($row[8],2))
                                                         ->where("dat_id",$dsaas->id )
                                                         ->update([
                                                             "salesman_id" =>
                                                             $salesmanInfo->user_id,
                                                         ]);
                                                 }
                                             }
                                         }
                                     }
                                 } else {

                                    $errors="The order " .$order->order_number ." delivery is not generated either its shipped";

                                    $faild_to_post[$count]['reason']=$errors;
                                    $faild_to_post[$count]['order_number']=$row[0];
                                    $count++;
                                    continue;
                                     
                                 }
                             } else {
                                 $delivery_exist = Delivery::where("order_id",$order->id)
                                     // ->where('approval_status', '!=', 'Shipment')
                                     ->first();
                                    
                                // new_delivery_id is current delivery id

                                 if (is_object($delivery_exist)) {
                                     // check if shipment is generated means salesmanLoad created
                                     $slCheck = SalesmanLoad::where('delivery_id', $delivery_exist->id)->first();
                        
                                     if ($slCheck) {
                                         continue;
                                     }
                                  
                                     $new_delivery_id = $delivery_exist->id;

                                     if ( $old_delivery_id != $new_delivery_id) {
                                        
                                                $old_delivery_id = $delivery_exist->id;
                                                $total_qty = 0;
                                                $delivery_exist->update(["salesman_id" => null,]);
                                                DeliveryAssignTemplate::where("delivery_id",$delivery_exist->id)->delete();
                                                DeliveryDetail::where("delivery_id",$delivery_exist->id)->update([
                                                    "transportation_status" => "No",
                                                ]);
                                     }

                                     if ( is_object($customerInfo) &&is_object($salesmanInfo)) {
                                         if ($item) {
                                             $delivery_details = DeliveryDetail::where("delivery_id", $delivery_exist->id)
                                                        ->where("item_id",$item->id)
                                                        ->where("item_qty", "!=", 0)
                                                        ->where("item_price","!=", 0)
                                                        ->where("is_deleted", 0)
                                                        // ->where('transportation_status', "No")
                                                        ->get();

                                                if (count($delivery_details) > 1) {

                                                    $delivery_details = DeliveryDetail::where( "delivery_id", $delivery_exist->id )
                                                                    ->where("item_id",$item->id)
                                                                    ->where("item_qty","!=", 0)
                                                                    ->where( "item_price", "!=",0)
                                                                    ->where("is_deleted", 0)
                                                                    ->where("transportation_status", "No")
                                                                    ->first();
                                                } else {
                                                    $delivery_details = $delivery_details->first();
                                                }
                                             
                                             if ($delivery_details) {
                                                 // if trip sequence 1 then add salesman in header and details both table otherwise only details
                                                 // if ($row[12] == 1) {
                                                 //     $delivery_exist->salesman_id = $salesmanInfo->user_id;
                                                 //     $delivery_exist->save();
                                                 // }
                                                 $uom = ItemUom::where("name","like","%$row[14]%")->first();
                                                 // $total_qty = $total_qty + $row[8];
                                               
                                              
                                                 $this->saveSKUDeliveryAssignTemplate(
                                                                    $delivery_exist,
                                                                    $delivery_details,
                                                                    $salesmanInfo,
                                                                    $item,
                                                                    $uom,
                                                                    $row
                                                       );
                                             }
                                         }
                                     }

                                     $delivery_details = DeliveryDetail::where("delivery_id", $delivery_exist->id)
                                                        ->where("transportation_status","No")
                                                        ->first();
                                    $delivery_exist->transportation_status = "Delegated";
                                    $delivery_exist->approval_status ="Truck Allocated";
                                    $delivery_exist->is_truck_allocated = 1;
                                    $delivery_exist->save();                  
                                                                   
                                     if ($delivery_details!='') {


                                         DeliveryDetail::where('delivery_id', $delivery_exist->id)
                                         ->update([
                                             'transportation_status' => "Delegated",
                                         ]);

                                         $checkOrder = Order::find($delivery_exist->order_id );
                                        
                                         if ($checkOrder->order_generate_picking === 1) {
                                             Order::where("id",$delivery_exist->order_id)
                                                        ->update([
                                                            "transportation_status" =>
                                                            "Delegated",
                                                            "approval_status" =>
                                                            "Truck Allocated",
                                                        ]);
                                         } else {
                                             Order::where( "id",$delivery_exist->order_id)
                                                    ->update([
                                                        "transportation_status" =>
                                                        "Delegated",
                                                    ]);
                                         }
                                     }

                                     if (!in_array($delivery_exist->id,$delivery_ids ) ) {
                                         $delivery_ids[] = $delivery_exist->id;
                                     }
                                 } else {
                                    $errors="The order " .$order->order_number ." delivery is not generated either its shipped";
                                    $faild_to_post[$count]['reason']=$errors;
                                    $faild_to_post[$count]['order_number']=$row[0];
                                    $count++;
                                    continue;
                                    
                                 }
                             }
                             
                         }
                       
                     
                 }
             }
            }


            sleep(1);
            

             $i++;
         }
         if(!$errors){

            if (file_exists($this->file_location)) {

                @unlink($this->file_location);
         
            }
             $response='';
             $username=$this->username;
             //maill start 
             $response='File Imported Successfully';
             $subject = 'Delivery Importe' . $username;
             /*  $email = $this->useremail; */
             $email = 'yadapkmax@gmail.com';
            
            $mailer->send('emails.deliverytemplate', ['data' => $response,'username'=>$username,'emails'=>$this->useremail,], function ($message) use ($email, $subject) {
                $message->to($email)
              ->subject($subject);
            });
            //maill end

         }else{

            $this->sendMailOnError($errors, $this->file_location,$this->username,$this->useremail,$mailer,$faild_to_post);
            
         }
      


    }


    private function sendMailOnError($errors, $file_location,$username,$emails,$mailer,$faild_to_post)
    {
        if (file_exists($file_location)) {

            @unlink($file_location);
     
        }
       $response='';
      
        //maill start 
        if ($errors) {
            $response=$errors.' Please Contact to the System Admin';
        }
        else{
            $response='File Imported Successfully';
        }
        $subject = ' Delivery Importe Error ' . $username;
 /*        $email = $this->useremail; */
           $email = 'yadavpankaj8845@gmail.com';
        
        $mailer->send('emails.deliveryErrortemplate', ['data' => $response,'username'=>$username,'emails'=>$emails,'faild_to_post'=>$faild_to_post,], function ($message) use ($email, $subject) {
            $message->to($email)
          ->subject($subject);
        });
    }

    private function saveHeaderDeliveryAssignTemplate($delivery, $delivery_details, $salesmanInfo, $row)
    {
       /*  dd($delivery, $delivery_details, $salesmanInfo, $row); */
        if ($delivery_details->item_id > 0 && $delivery_details->item_price > 0) {

            $dat = new DeliveryAssignTemplate();
            $dat->uuid                  = (string) \Uuid::generate();
            $dat->order_id              = $delivery->order_id;
            $dat->delivery_id           = $delivery->id;
            $dat->delivery_details_id   = $delivery_details->id;
            $dat->storage_location_id   = $delivery->storage_location_id;
            $dat->warehouse_id          = getWarehuseBasedOnStorageLoacation($delivery->storage_location_id, false);
            $dat->customer_id           = $delivery->customer_id;
            $dat->delivery_driver_id    = $salesmanInfo->user_id;
            $dat->item_id               = $delivery_details->item_id;
            $dat->item_uom_id           = $delivery_details->item_uom_id;
            $dat->qty                   = $delivery_details->item_qty;
            $dat->amount                = $delivery_details->item_price;
            $dat->delivery_sequence     = $row[10];
            $dat->trip                  = $row[11];
            // $dat->trip_sequence = $row[10];
            // $dat->van_id = (!empty($van)) ? $van->id : null;
            $dat->is_last_trip          = $row[11];
            $dat->save();

            DeliveryDetail::where('id', $delivery_details->id)
                ->update([
                    'transportation_status' => "Delegated",
                ]);

            DeliveryDetail::where('delivery_id', $delivery->id)
                ->where('item_price', 0)
                ->orWhere('item_qty', 0)
                ->orWhere('is_deleted', 1)
                ->update([
                    'transportation_status' => "Delegated",
                ]);

            $data = [
                'created_user'          => $this->userid,
                'order_id'              => $delivery->order_id,
                'delviery_id'           => $delivery->order_id,
                'updated_user'          => $this->userid,
                'previous_request_body' => NULL,
                'request_body'          => $dat,
                'action'                => 'Delivery TEMPLATE',
                'status'                => 'Created',
            ];

            saveOrderDeliveryLog($data);

          /*   $this->sendNotificationToDeliveryDriver(null, $delivery); */
        }
    }

    private function saveSKUDeliveryAssignTemplate($delivery, $delivery_details, $salesmanInfo, $item, $uom, $row)
    {
        if (empty($delivery->salesman_id)) {
            $delivery->salesman_id = $salesmanInfo->user_id;
            $delivery->save();
        }

        $dat = new DeliveryAssignTemplate();
        $dat->uuid                  = (string) \Uuid::generate();
        $dat->order_id              = $delivery->order_id;
        $dat->delivery_id           = $delivery->id;
        $dat->delivery_details_id   = $delivery_details->id;
        $dat->customer_id           = $delivery->customer_id;
        $dat->delivery_driver_id    = $salesmanInfo->user_id;
        $dat->storage_location_id   = $delivery->storage_location_id;
        $dat->warehouse_id          = getWarehuseBasedOnStorageLoacation($delivery->storage_location_id, false);
        $dat->item_id               = $item->id;
        $dat->item_uom_id           = $delivery_details->item_uom_id;
        $dat->qty                   = $row[8];
        $dat->amount                = $row[9];
        $dat->delivery_sequence     = $row[10];
        $dat->trip                  = $row[11];
        $dat->is_last_trip          = $row[13];
        $dat->save();

        DeliveryDetail::where('id', $dat->delivery_details_id)
            ->update([
                'transportation_status' => "Delegated",
            ]);

        DeliveryDetail::where('delivery_id', $delivery->id)
            ->where('item_price', 0)
            ->orWhere('item_qty', 0)
            ->orWhere('is_deleted', 1)
            ->update([
                'transportation_status' => "Delegated",
            ]);

        $data = [
            'created_user'          => $this->userid,
            'order_id'              => $delivery->order_id,
            'delviery_id'           => $delivery->order_id,
            'updated_user'          => $this->userid,
            'previous_request_body' => NULL,
            'request_body'          => $dat,
            'action'                => 'Delivery TEMPLATE',
            'status'                => 'Created',
        ];

        saveOrderDeliveryLog($data);

       /*  $this->sendNotificationToDeliveryDriver($dat); */
    }
}
