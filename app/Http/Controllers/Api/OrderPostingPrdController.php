<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\OrderPrd;
use App\Model\OrderDetailPrd;
use App\Model\ItemMainPricePrd;
use App\Model\ItemPrd;
use App\Model\PriceDiscoPromoPlanPrd;
use App\Model\CustomerInfoPrd;
use App\Model\Delivery;
use App\Model\RoutePrd;
use App\Model\PDPDiscountSlabPrd;
use App\Model\PDPItemPrd;
use App\Model\PDPPromotionItem;
use App\Model\WorkFlowObjectPrd;
use App\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Imports\OrderImport;
use App\Model\StoragelocationDetailPrd;
use App\Model\StoragelocationPrd;
use App\Model\Warehouse;
use App\Model\CodeSettingPrd;
use App\Model\CustomerLobPrd;
use App\Model\DeliveryDetail;
use App\Model\ItemUomPrd;
use App\Model\OCRLogs;
use App\Model\WorkFlowRuleApprovalUser;
use App\Model\OrderLog;
use App\Model\OrderType;
use App\Model\PortfolioManagement;
use App\Model\PortfolioManagementItem;
use App\Model\LobPrd;
use App\Model\CustomerBasedPricingPrd;
use Illuminate\Support\Facades\DB;
use stdClass;
use Ixudra\Curl\Facades\Curl;
use App\Model\PaymentTermPrd;
use App\Model\ItemBasePricePrd;
use Carbon\Carbon;
use DateTime;


class OrderPostingPrdController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
		$input = $request->json()->all();
		 $validate = $this->validations($input, "insert");
		 if ($validate["error"]) {
                return prepareResult(false, [], ['error' => "Error while validating empty data array"], "Error while validating empty data array", $this->unprocessableEntity);
            }
        $converter = new \JJC;
        //--------------
        $cy = Carbon::parse($input['date'])->format('y'); 
        $cday = Carbon::parse($input['date'])->format('z'); 
            
        if(strlen($cday)=='3')
        {
           $cday = ($cday +1);
        }
        
        if(strlen($cday)=='2')
        {
          
           if($cday >= 99)
           {
               $cday = ($cday +1);
           }else{

               $cday = '0'.($cday +1);
               //$cday = ($cday +1);
           }
          // dd($cday);
        }
        
        if(strlen($cday)=='1')
        {
            
            if($cday >= 9)
            {
                $cday = '0'.($cday+1);
            }else{
                $cday = '00'.($cday+1);
            }
          
          //dd($cday);	
        }
        
        $cdate = '1'.$cy.$cday;
        //dd($cdate);
        //  echo $cdate = '1'.$cy.$cday;
		//  echo $deldate = $this->jdedateConvert($cdate);
	    // exit;

         $branchPlant = '';
         if($input['branch'] == '16')
         {
             $branchPlant = '      101122';
         }else if($input['branch'] == '9')
         {
             $branchPlant = '      100822';
         }else if($input['branch'] == '238')
         {
             $branchPlant = '      100322';
         }else if($input['branch'] == '12')
         {
             $branchPlant = '      101222';
         }else if($input['branch'] == '11')
         {
             $branchPlant = '      101822';
         }else if($input['branch'] == '14')
         {
             $branchPlant = '      101422';
         }else if($input['branch'] == '13')
         {
             $branchPlant = '      100922';
         }
        
        

        //echo $jdate = gregoriantojd.(22,1,2022);exit;
		
        //echo $cdate;
        //--------------
        //$input = $request->json()->all();
       // $conn2 = oci_connect("SAMOAP01", "Samoap123", "jdedbpd_pdb1");  //PRODDTA
        //$conn2 = oci_connect("SAMOAP01", "s@moap01", "JDEDBDEV_PDB1");  //CRPDTA
        $conn2 = oci_connect("MOBIATO", "Mobjdeatp123$", "(description= (retry_count=20)(retry_delay=3)(address=(protocol=tcps)(port=1521)(host=nfpcdbpd.adb.me-dubai-1.oraclecloud.com))(connect_data=(service_name=csb29cbrpbm1rmf_jdedbpd_tp.adb.oraclecloud.com)))");
        $resultodbc = oci_parse($conn2, "SELECT SYEDTY,SYEDSQ,SYEKCO,SYEDOC,SYEDCT,SYEDLN,SYEDST,SYEDFT,SYEDDT,SYEDER,SYEDDL,SYEDSP,SYEDBT,SYPNID,SYOFRQ,SYNXDJ,SYSSDJ,SYTPUR,SYKCOO,SYDOCO,SYDCTO,SYSFXO,SYMCU,SYCO,SYOKCO,SYOORN,SYOCTO,SYRKCO,SYRORN,SYRCTO,SYAN8,SYSHAN,SYPA8,SYDRQJ,SYTRDJ,SYPDDJ,SYOPDJ,SYADDJ,SYCNDJ,SYPEFJ,SYPPDJ,SYPSDJ,SYVR01,SYVR02,SYDEL1,SYDEL2,SYINMG,SYPTC,SYRYIN,SYASN,SYPRGP,SYTRDC,SYPCRT,SYTXA1,SYEXR1,SYTXCT,SYATXT,SYPRIO,SYBACK,SYSBAL,SYNTR,SYANBY,SYCARS,SYMOT,SYCOT,SYROUT,SYSTOP,SYZON,SYCNID,SYFRTH,SYAFT,SYRCD,SYOTOT,SYTOTC,SYWUMD,SYVUMD,SYAUTN,SYCACT,SYCEXP,SYCRMD,SYCRRM,SYCRCD,SYCRR,SYLNGP,SYFAP,SYFCST,SYORBY,SYTKBY,SYURCD,SYURDT,SYURAT,SYURAB,SYURRF,SYTORG,SYUSER,SYPID,SYJOBN,SYUPMJ,SYTDAY,SYIR01,SYIR02,SYIR03,SYIR04,SYIR05,SYVR03,SYSOOR,SYPMDT,SYRSDT,SYRQSJ,SYPSTM,SYPDTT,SYOPTT,SYDRQT,SYADTM,SYADLJ,SYPBAN,SYITAN,SYFTAN,SYDVAN,SYDOC1,SYDCT4,SYCORD,SYBSC,SYBCRC,SYRSHT,SYHOLD,SYFUF1,SYAUFT,SYAUFI,SYOPBO,SYOPTC,SYOPLD,SYOPBK,SYOPSB,SYOPPS,SYOPPL,SYOPMS,SYOPSS,SYOPBA,SYOPLL,SYPRAN8,SYPRCIDLN,SYOPPID,SYCCIDLN,SYSDATTN,SYSHCCIDLN,SYSPATTN,SYOTIND,SYEXVAR0,SYEXVAR1,SYEXVAR4,SYEXVAR5,SYEXVAR6,SYEXVAR7,SYEXVAR12,SYEXVAR13,SYEXNM0,SYEXNM1,SYEXNM2,SYEXNMP0,SYEXNMP1,SYEXNMP2,SYEXDT0,SYEXDT1,SYEXDT2,SYPOHP01,SYPOHP02,SYPOHP03,SYPOHP04,SYPOHP05,SYPOHP06,SYPOHP07,SYPOHP08,SYPOHP09,SYPOHP10,SYPOHP11,SYPOHP12,SYPOHC01,SYPOHC02,SYPOHC03,SYPOHC04,SYPOHC05,SYPOHC06,SYPOHC07,SYPOHC08,SYPOHC09,SYPOHC10,SYPOHC11,SYPOHC12,SYPOHD01,SYPOHD02,SYPOHAB01,SYPOHAB02,SYPOHP13,SYPOHU01,SYPOHU02,SYRETI,SYCLASS01,SYCLASS02,SYCLASS03,SYCLASS04,SYCLASS05,SYGAN8,SYGSHAN,SYGPA8,SYGCARS,SYGPBAN,SYGITAN,SYGFTAN,SYGDVAN,SYGPRAN8 from PRODDTA.F47011 D
        where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
        //where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYAN8 IN ('177707') and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
		//where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
        //where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
        //where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
        
        //where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYAN8 IN ('143256') and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
       // where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");
        
		//where  D.SYDRQJ ='123100' and D.SYEDCT = 'SA' and D.SYAN8 IN ('177503') and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant.//"' ");
         
        // where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA' and D.SYAN8 IN ('179480') and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU = '".$branchPlant."' ");

       // $resultodbc = oci_parse($conn2, "SELECT SYEDTY,SYEDSQ,SYEKCO,SYEDOC,SYEDCT,SYEDLN,SYEDST,SYEDFT,SYEDDT,SYEDER,SYEDDL,SYEDSP,SYEDBT,SYPNID,SYOFRQ,SYNXDJ,SYSSDJ,SYTPUR,SYKCOO,SYDOCO,SYDCTO,SYSFXO,SYMCU,SYCO,SYOKCO,SYOORN,SYOCTO,SYRKCO,SYRORN,SYRCTO,SYAN8,SYSHAN,SYPA8,SYDRQJ,SYTRDJ,SYPDDJ,SYOPDJ,SYADDJ,SYCNDJ,SYPEFJ,SYPPDJ,SYPSDJ,SYVR01,SYVR02,SYDEL1,SYDEL2,SYINMG,SYPTC,SYRYIN,SYASN,SYPRGP,SYTRDC,SYPCRT,SYTXA1,SYEXR1,SYTXCT,SYATXT,SYPRIO,SYBACK,SYSBAL,SYNTR,SYANBY,SYCARS,SYMOT,SYCOT,SYROUT,SYSTOP,SYZON,SYCNID,SYFRTH,SYAFT,SYRCD,SYOTOT,SYTOTC,SYWUMD,SYVUMD,SYAUTN,SYCACT,SYCEXP,SYCRMD,SYCRRM,SYCRCD,SYCRR,SYLNGP,SYFAP,SYFCST,SYORBY,SYTKBY,SYURCD,SYURDT,SYURAT,SYURAB,SYURRF,SYTORG,SYUSER,SYPID,SYJOBN,SYUPMJ,SYTDAY,SYIR01,SYIR02,SYIR03,SYIR04,SYIR05,SYVR03,SYSOOR,SYPMDT,SYRSDT,SYRQSJ,SYPSTM,SYPDTT,SYOPTT,SYDRQT,SYADTM,SYADLJ,SYPBAN,SYITAN,SYFTAN,SYDVAN,SYDOC1,SYDCT4,SYCORD,SYBSC,SYBCRC,SYRSHT,SYHOLD,SYFUF1,SYAUFT,SYAUFI,SYOPBO,SYOPTC,SYOPLD,SYOPBK,SYOPSB,SYOPPS,SYOPPL,SYOPMS,SYOPSS,SYOPBA,SYOPLL,SYPRAN8,SYPRCIDLN,SYOPPID,SYCCIDLN,SYSDATTN,SYSHCCIDLN,SYSPATTN,SYOTIND,SYEXVAR0,SYEXVAR1,SYEXVAR4,SYEXVAR5,SYEXVAR6,SYEXVAR7,SYEXVAR12,SYEXVAR13,SYEXNM0,SYEXNM1,SYEXNM2,SYEXNMP0,SYEXNMP1,SYEXNMP2,SYEXDT0,SYEXDT1,SYEXDT2,SYPOHP01,SYPOHP02,SYPOHP03,SYPOHP04,SYPOHP05,SYPOHP06,SYPOHP07,SYPOHP08,SYPOHP09,SYPOHP10,SYPOHP11,SYPOHP12,SYPOHC01,SYPOHC02,SYPOHC03,SYPOHC04,SYPOHC05,SYPOHC06,SYPOHC07,SYPOHC08,SYPOHC09,SYPOHC10,SYPOHC11,SYPOHC12,SYPOHD01,SYPOHD02,SYPOHAB01,SYPOHAB02,SYPOHP13,SYPOHU01,SYPOHU02,SYRETI,SYCLASS01,SYCLASS02,SYCLASS03,SYCLASS04,SYCLASS05,SYGAN8,SYGSHAN,SYGPA8,SYGCARS,SYGPBAN,SYGITAN,SYGFTAN,SYGDVAN,SYGPRAN8 from PRODDTA.F47011 D
       // where  D.SYDRQJ ='".$cdate."' and D.SYEDCT = 'SA'and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYMCU IN ('      100822','      101122') ");
			//where  D.SYDRQJ ='123033' and D.SYEDCT = 'SA'and D.SYEDSP = '0'  and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010' AND D.SYAN8 IN ('177502','144206','177671','140492','135596','106691','133579','138504','109984','177973','177559','177656','177600','177803','185347','185351','185559','178926','184873','184918','184931','185421')   ");
        //122249 SYEDOC = '003-23000353' D.SYVR01 = '015-22117633' 
        //$deldate = $converter->Convert();
        //D.SYEDOC ='" . $cdate . "' and D.SYEDSP = '0' and D.SYTRDJ ='" . $cdate . "' and D.SYEDCT = 'SA' and D.SYEDSP = '0' and D.SYTKBY = 'INFINITE' AND D.SYEKCO = '00010'



        //-----------
        //-----------
        $bid = 1;
        $ruom = 1;
        $categ = 1;
        oci_execute($resultodbc);
        oci_set_prefetch($resultodbc, 1000);
        //   pre($cdate);

        
        // echo jdtogregorian(trim($row['SYEDDT']));
        //exit;
        //$order_array = new Order;
        //echo trim($resultodbc);
        while (($row = oci_fetch_array($resultodbc, OCI_BOTH)) != false) {
			//print_r($row);	
            $order = new OrderPrd;
			// echo "jdate".trim($row['SYDRQJ']);
            // //$deldate = $converter->Convert(trim($row['SYDRQJ']));
			// $deldate = $this->jdedateConvert('123200');
            // pre("te".$deldate);
			// exit;
            $customer_code = trim($row['SYAN8']);
            $getcustomerid = CustomerInfoPrd::select('id', 'user_id')->where('customer_code', $customer_code)->first();

            if (is_object($getcustomerid)) {

                //get location code
                //echo trim($row['SYMCU']);
                $location_code = trim($row['SYMCU']);
                $getlocation_id = StoragelocationPrd::select('warehouse_code')->where('code', $location_code)->first();
                $getlocationid = StoragelocationPrd::select('id')->where('warehouse_type', '34')->where('warehouse_code', $getlocation_id->warehouse_code)->first();
                //get lob id
                $lob_code = trim($row['SYEKCO']);
                $getlobid = LobPrd::select('id')->where('lob_code', $lob_code)->first();
                $gettype = CustomerLobPrd::select('customer_type_id')->where('lob_id', $getlobid->id)->where('customer_info_id', $getcustomerid->id)->first();
                $getpaymenttermid = CustomerLobPrd::select('payment_term_id')->where('lob_id', $getlobid->id)->where('customer_info_id', $getcustomerid->id)->first();
                $payment_term = PaymentTermPrd::select('number_of_days')->where('id', '1')->first();
                //DB::beginTransaction();
                //{
                $t_qty = 0;
                $status = 1;
                $current_stage = 'Approved';
                $current_organisation_id = 1;

                if ($isActivate = checkWorkFlowRule2('Order', 'create', $current_organisation_id)) {
                    $status = 0;
                    $current_stage = 'Pending';
                }
                $getorder_id = OrderPrd::select('id')->where('erp_number', trim($row['SYEDOC']))->first();
                
                if (is_object($getorder_id)) {
                    $orderid = $getorder_id->id;
                } else {
					//print_r($row);
                    //$deldate = '2023-07-04';
					$deldate = $this->convertJDEJulianToDate(trim($row['SYDRQJ']));
                   // $deldate = $converter->Convert(trim($row['SYDRQJ']));
                    //pre("tes".$deldate1);exit;
                    if ($payment_term->number_of_days == 0) {
                        $duedate = $deldate;
                    } else {

                        $duedate = $deldate->addDays(trim($payment_term->number_of_days));
                    } {
                        $t_qty = 0;
                        $variable = "order";
                        $nextComingNumberPrd['number_is'] = null;
                        $nextComingNumberPrd['prefix_is'] = null;
                        if (CodeSettingPrd::count() > 0) {
                            $code_setting = CodeSettingPrd::first();
                            if ($code_setting['is_final_update_' . $variable] == 1) {
                                $nextComingNumberPrd['number_is'] = $code_setting['next_coming_number_' . $variable];
                                $nextComingNumberPrd['prefix_is'] = $code_setting['prefix_code_' . $variable];
                            }
                        }
                    }
                    if (isset($nextComingNumberPrd['number_is'])) {
                        $order_number = $nextComingNumberPrd['number_is'];
                    } else {
                        $order_number = "0000001";
                    }


                    $order->organisation_id         = 1;
                    //$order->order_number            = trim($row['SYEDOC']);
                    $order->order_number            = nextComingNumberPrd('App\Model\OrderPrd', 'order', 'order_number', $order_number);
                    $order->customer_id             = $getcustomerid->user_id;
                    $order->depot_id                = null;
                    $order->order_type_id           = 1;
                    //$order->order_date              = $converter->Convert(trim($row['SYEDDT']));
                    $order->order_date              = $this->convertJDEJulianToDate(trim($row['SYEDDT']));
                    $order->delivery_date           = $deldate;
                    //$order->delivery_date           = $converter->Convert(trim($row['SYDRQJ']));
                    $order->salesman_id             = null;
                    $order->route_id                = null;
                    $order->reason_id               = null;
                    $order->customer_lop            = (!empty(trim($row['SYVR01']))) ? trim($row['SYVR01']) : 0;
                    $order->payment_term_id         =  1;
                    $order->due_date                = $duedate;
                    $order->total_qty               = 0;
                    $order->total_gross             = 0;
                    $order->total_discount_amount   = 0;
                    $order->total_net               = 0;
                    $order->total_vat               = 0;
                    $order->total_excise            = 0;
                    $order->grand_total             = 0;
                    $order->any_comment             = null;
                    $order->source                  = 4;
                    $order->is_presale_order        = 1;
                    $order->status                  = $status;
                    $order->current_stage           = $current_stage;
                    $order->current_stage_comment   = null;
                    $order->approval_status         = "Created";
                    $order->warehouse_id            = (is_object($getlocationid)) ? getWarehuseBasedOnStorageLoacation2($getlocationid->id, false) : 1;
                    $order->lob_id                  = (is_object($getlobid)) ? $getlobid->id : 0;
                    $order->storage_location_id     = (is_object($getlocationid)) ? $getlocationid->id : 1;
                    $order->erp_number              = (!empty(trim($row['SYEDOC']))) ? trim($row['SYEDOC']) : 0;
                    $order->save();

                    if ($order->id) {
                        updateNextComingNumberPrd('App\Model\OrderPrd', 'order');
                    }

                    $orderid = $order->id;
                    if ($isActivate = checkWorkFlowRule2('Order', 'Create', $current_organisation_id)) {
                        $this->createWorkFlowObject($isActivate, 'OrderPrd', $request, $order);
                    }

                    // $order_array = $order;
                    $resultodbc2 = oci_parse($conn2, "SELECT SZEDTY,SZEDSQ,SZEKCO,SZEDOC,SZEDCT,SZEDLN,SZEDST,SZEDFT,SZEDDT,SZEDER,SZEDDL,SZEDSP,SZEDBT,SZPNID,SZKCOO,SZDOCO,SZDCTO,SZLNID,SZSFXO,SZMCU,SZCO,SZOKCO,SZOORN,SZOCTO,SZOGNO,SZRKCO,SZRORN,SZRCTO,SZRLLN,SZDMCT,SZDMCS,SZAN8,SZSHAN,SZPA8,SZDRQJ,SZTRDJ,SZPDDJ,SZOPDJ,SZADDJ,SZIVD,SZCNDJ,SZDGL,SZRSDJ,SZPEFJ,SZPPDJ,SZPSDJ,SZVR01,SZVR02,SZITM,SZLITM,SZAITM,SZCITM,SZLOCN,SZLOTN,SZFRGD,SZTHGD,SZFRMP,SZTHRP,SZEXDP,SZDSC1,SZDSC2,SZLNTY,SZNXTR,SZLTTR,SZEMCU,SZRLIT,SZKTLN,SZCPNT,SZRKIT,SZKTP,SZSRP1,SZSRP2,SZSRP3,SZSRP4,SZSRP5,SZPRP1,SZPRP2,SZPRP3,SZPRP4,SZPRP5,SZUOM,SZUORG,SZSOQS,SZSOBK,SZSOCN,SZSONE,SZUOPN,SZQTYT,SZQRLV,SZCOMM,SZOTQY,SZUPRC,SZAEXP,SZAOPN,SZPROV,SZTPC,SZAPUM,SZLPRC,SZUNCS,SZECST,SZCSTO,SZTCST,SZINMG,SZPTC,SZRYIN,SZDTBS,SZTRDC,SZFUN2,SZASN,SZPRGR,SZCLVL,SZDSPR,SZDSFT,SZFAPP,SZCADC,SZKCO,SZDOC,SZDCT,SZODOC,SZODCT,SZOKC,SZPSN,SZDELN,SZTAX1,SZTXA1,SZEXR1,SZATXT,SZPRIO,SZRESL,SZBACK,SZSBAL,SZAPTS,SZLOB,SZEUSE,SZDTYS,SZNTR,SZVEND,SZANBY,SZCARS,SZMOT,SZCOT,SZROUT,SZSTOP,SZZON,SZCNID,SZFRTH,SZAFT,SZFUF1,SZFRTC,SZFRAT,SZRATT,SZSHCM,SZSHCN,SZSERN,SZPQOR,SZSQOR,SZITWT,SZWTUM,SZITVL,SZVLUM,SZRPRC,SZORPR,SZORP,SZCMGP,SZCMGL,SZGLC,SZCTRY,SZFY,SZSTTS,SZSO01,SZSO02,SZSO03,SZSO04,SZSO05,SZSO06,SZSO07,SZSO08,SZSO09,SZSO10,SZSO11,SZSO12,SZSO13,SZSO14,SZSO15,SZACOM,SZCMCG,SZRCD,SZGRWT,SZGWUM,SZANI,SZAID,SZOMCU,SZOBJ,SZSUB,SZLT,SZSBL,SZSBLT,SZLCOD,SZUPC1,SZUPC2,SZUPC3,SZSWMS,SZUNCD,SZCRMD,SZCRCD,SZCRR,SZFPRC,SZFUP,SZFEA,SZFUC,SZFEC,SZURCD,SZURDT,SZURAT,SZURAB,SZURRF,SZTORG,SZUSER,SZPID,SZJOBN,SZUPMJ,SZTDAY,SZIR01,SZIR02,SZIR03,SZIR04,SZIR05,SZSOOR,SZDEID,SZPSIG,SZRLNU,SZPMDT,SZRLTM,SZRLDJ,SZDRQT,SZADTM,SZOPTT,SZPDTT,SZPSTM,SZPMTN,SZBSC,SZCBSC,SZDVAN,SZRFRV,SZSHPN,SZPRJM,SZHOLD,SZPMTO,SZDUAL,SZPODC01,SZPODC02,SZPODC03,SZPODC04,SZJBCD,SZSRQTY,SZSRUOM,SZCFGFL,SZGAN8,SZGSHAN,SZGPA8,SZGVEND,SZGCARS,SZGDVAN,SZPMPN From PRODDTA.F47012 B Left join
					 PRODDTA.F4101 A on A.IMITM = B.SZLITM and A.IMSRP4 ='Long Life'  
					where SZEDOC = '" . $row['SYEDOC'] . "' and SZEKCO = '" . $row['SYEKCO'] . "' and SZEDCT = '" . $row['SYEDCT'] . "'");

                    oci_execute($resultodbc2);
                    oci_set_prefetch($resultodbc2, 1000);
                    
                    while (($row2 = oci_fetch_array($resultodbc2, OCI_BOTH)) != false) {

                        if ($row2['SZLITM'] != "") {

                            $item_code = trim($row2['SZLITM']);
                            $getitemid = ItemPrd::select('id')->where('item_code', $item_code)->first();

                            if (is_object($getitemid)) {

                                $uom_code = $row2['SZUOM'];
                                $getitemuomid = ItemUomPrd::select('id')->where('name', $uom_code)->first();
                                $item_qty = (!empty($row2['SZUORG'])) ? ($row2['SZUORG'] / 100) : 0;

                                $getItemQtyByUom = qtyConversion2($getitemid->id, $getitemuomid->id, $item_qty);

                                //----------Item price Start
                                try {
                                    //echo "ts4";exit;					
                                    $stdObject = new stdClass();
                                    $stdObject->item_id       = $getitemid->id;
                                    $stdObject->customer_id   = $getcustomerid->id;
                                    $stdObject->item_qty      = $getItemQtyByUom['Qty'];
                                    $stdObject->item_uom_id   = $getitemuomid->id;
                                    $stdObject->lob_id        = $getlobid->id;
                                    $stdObject->delivery_date   = $order->delivery_date;

                                    $item_apply = item_apply_price($stdObject);
                                    
                                    $original = (array)$item_apply;
                                    // pre($original)
                                    //----------Item Pricr End
                                    if (!empty($original['item_price'])) {
                                        $orderDetail = new OrderDetailPrd;
                                        $orderDetail->order_id              = $orderid;
                                        $orderDetail->item_id               = (is_object($getitemid)) ? $getitemid->id : 1;
                                        $orderDetail->item_uom_id           = $getitemuomid->id;
                                        $orderDetail->original_item_uom_id  =  $getitemuomid->id;
                                        $orderDetail->discount_id           = (isset($original['discount_id']) ? $original['discount_id'] : 0);
                                        $orderDetail->is_free               = (isset($original['is_free']) ? $original['is_free'] : 0);
                                        $orderDetail->is_item_poi           = (isset($original['is_item_poi']) ? $original['is_item_poi'] : 0);
                                        $orderDetail->promotion_id          = (isset($original['promotion_id']) ? $original['promotion_id'] : 0);
                                        $orderDetail->reason_id             = null;
                                        $orderDetail->is_deleted            = 0;
                                        $orderDetail->item_qty              = $getItemQtyByUom['Qty'];
                                        $orderDetail->item_weight           =  0;
                                        $orderDetail->item_price            = (!empty($original['item_price'])) ? $original['item_price'] : 0;
                                        $orderDetail->item_gross            = (!empty($original['item_gross'])) ? $original['item_gross'] : 0;
                                        $orderDetail->item_discount_amount  = (!empty($original['discount_id'])) ? $original['discount_id'] : 0;
                                        $orderDetail->item_net              = (!empty($original['total_net'])) ? $original['total_net'] : 0;
                                        $orderDetail->item_vat              = (!empty($original['total_vat'])) ? $original['total_vat'] : 0;
                                        $orderDetail->item_excise           = (!empty($original['total_excise'])) ? $original['total_excise'] : 0;
                                        $orderDetail->item_grand_total      = (!empty($original['total'])) ? $original['total'] : 0;
                                        $orderDetail->original_item_qty     = (!empty($getItemQtyByUom)) ? $getItemQtyByUom['Qty'] : 0;
                                        $orderDetail->save();
                                        $t_qty = $t_qty + $getItemQtyByUom['Qty'];
                                    }
                                } catch (\Exception $exception) {
                                    DB::rollback();
                                    return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
                                }
                            }
                        }
                    }

                    $ods = OrderDetailPrd::selectRaw(
                        '
                        sum(item_net) as ordernetsum,
                        sum(item_gross) as ordergrosssum,
                        sum(item_vat) as ordervatum,
                        sum(item_excise) as orderitemexicise,
                        sum(item_grand_total) as ordergtotal'
                    )
                        ->where('order_id', '=', $order->id)
                        ->first();

                    // $ordergrosssum      = OrderDetailPrd::where('order_id', '=', $order->id)->sum('item_gross');
                    // $ordervatum         = OrderDetailPrd::where('order_id', '=', $order->id)->sum('item_vat');
                    // $orderitemexicise   = OrderDetailPrd::where('order_id', '=', $order->id)->sum('item_excise');
                    // $ordergtotal        = OrderDetailPrd::where('order_id', '=', $order->id)->sum('item_grand_total');

                    if ($ods) {
                        $order_update = OrderPrd::find($order->id);
                        $order_update->total_gross = $ods->ordergrosssum;
                        $order_update->total_vat = $ods->ordervatum;
                        $order_update->total_excise = $ods->orderitemexicise;
                        $order_update->total_net = $ods->ordernetsum;
                        $order_update->grand_total = $ods->ordergtotal;
                        $order_update->total_qty = $t_qty;
                        $order_update->save();
                    }
                    // updateNextComingNumberPrd('App\Model\OrderPrd', 'order');
                }

                $resultodbcupdate = oci_parse($conn2, "Update PRODDTA.F47011 SET SYEDSP = 'Y' where SYEDOC = '" . $row['SYEDOC'] . "' and SYEKCO = '" . $row['SYEKCO'] . "' and SYEDCT = '" . $row['SYEDCT'] . "'");
                oci_execute($resultodbcupdate);
            }
        }
        return prepareResult(true, [], [], "Order added successfully", $this->success);
    }
    private function data_giuliana($date = null)
    {
        $cdate = $date ? Carbon::parse($date) : Carbon::now();
        $anno = $cdate->format('y'); // 2 digit year

        $timestamp = $cdate->copy()->firstOfYear()->timestamp;
        $yearFirstDay = floor($timestamp / 86400);
        $today = ceil($cdate->timestamp / 86400);
        $giorno = ($today - $yearFirstDay);

        $data_giuliana = "1" . $anno . $giorno;

        return $data_giuliana;
    }

    /**
     * This function is calculate the price base on customer and item base price
     *
     */
    private function item_apply_price_old($request)
    {
        $cusotmer = CustomerInfoPrd::find($request->customer_id);

        $item = ItemPrd::find($request->item_id);
        $qty = $request->item_qty;

        // first find the price based on item and customer
        $item_price_objs = CustomerBasedPricingPrd::where('customer_id', $cusotmer->user_id)
            ->where('item_id', $request->item_id)
            ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->get();

        if (count($item_price_objs)) {
            $item_price_obj = CustomerBasedPricingPrd::where('customer_id', $cusotmer->user_id)
                ->where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->orderBy('updated_at', 'desc')
                ->first();

            // cusotmer price with same requested uom
            if ($item_price_obj) {
                $price = $item_price_obj->price;
                return itemPriceSet($qty, $price, $item, $request);
            }

            if (!$item_price_obj) {
                $item_price_obj = $item_price_objs->first();

                // customer base price
                $cusotmer_price = $item_price_obj->price;
                $cusotmer_lower_price = 0;

                if ($item_price_obj->item_uom_id == $item->lower_unit_uom_id) {
                    $cusotmer_lower_price = $cusotmer_price;
                } else {
                    $item_main_price = ItemMainPricePrd::where('item_id', $item_price_obj->item_id)
                        ->where('item_uom_id', $item_price_obj->item_uom_id)
                        ->first();

                    if ($item_main_price) {
                        $upc = $item_main_price->item_upc;
                        if ($upc < 1) {
                            $cusotmer_lower_price = $cusotmer_price / 1;
                        } else {
                            $cusotmer_lower_price = $cusotmer_price / $upc;
                        }
                    } else {
                        $item_price_objs = ItemBasePricePrd::where('item_id', $request->item_id)
                            // ->where('item_uom_id', $request->item_uom_id)
                            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            ->orderBy('updated_at', 'desc')
                            ->get();

                        if (count($item_price_objs)) {
                            return itemBasePrice($request, $qty, $item, $item_price_objs);
                        }
                    }
                }

                $price = 0;
                if ($request->item_uom_id == $item->lower_unit_uom_id) {
                    $price = $cusotmer_lower_price;
                } else {
                    $item_main_price = ItemMainPricePrd::where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->first();

                    if ($item_main_price) {
                        $upc = $item_main_price->item_upc;
                        if ($upc < 1) {
                            $price = $cusotmer_lower_price * 1;
                        } else {
                            $price = $cusotmer_lower_price * $upc;
                        }
                    } else {
                        $item_price_objs = ItemBasePricePrd::where('item_id', $request->item_id)
                            // ->where('item_uom_id', $request->item_uom_id)
                            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                            ->orderBy('updated_at', 'desc')
                            ->get();

                        if (count($item_price_objs)) {
                            return itemBasePrice($request, $qty, $item, $item_price_objs);
                        }
                    }
                }

                return $this->itemPriceSet($qty, $price, $item, $request);
            }

            // return $this->itemPriceSet($qty, $price, $item, $request);
        }

        if (count($item_price_objs) < 1) {
            $item_price_objs = ItemBasePricePrd::where('item_id', $request->item_id)
                // ->where('item_uom_id', $request->item_uom_id)
                // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
                ->orderBy('updated_at', 'desc')
                ->get();

            if (count($item_price_objs)) {
                return itemBasePrice($request, $qty, $item, $item_price_objs);
            }
        }

        if (count($item_price_objs) < 1) {
            $std_object = new stdClass;
            $std_object->item_qty               = $request->item_qty;
            $std_object->item_price             = 0;
            $std_object->totla_price            = 0;
            $std_object->item_gross             = 0;
            $std_object->net_gross              = 0;
            $std_object->net_excise             = 0;
            $std_object->discount               = 0;
            $std_object->discount_percentage    = 0;
            $std_object->discount_id            = 0;
            $std_object->total_net              = 0;
            $std_object->is_free                = false;
            $std_object->is_item_poi            = false;
            $std_object->promotion_id           = null;
            $std_object->total_excise           = 0;
            $std_object->total_vat              = 0;
            $std_object->total                  = 0;

            return $std_object;
        }
    }


    private function itemBasePrice_old($request, $qty, $item, $item_price_objs)
    {
        $item_price_obj = ItemBasePricePrd::where('item_id', $request->item_id)
            ->where('item_uom_id', $request->item_uom_id)
            // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($item_price_obj) {
            $price = $item_price_obj->price;
            return $this->itemPriceSet($qty, $price, $item, $request);
        }

        if (!$item_price_obj) {
            $item_price_obj = $item_price_objs->first();
            $cusotmer_price = $item_price_obj->price;

            $cusotmer_lower_price = 0;

            if ($item_price_obj->item_uom_id == $item->lower_unit_uom_id) {
                $cusotmer_lower_price = $cusotmer_price;
            } else {
                $item_main_price = ItemMainPricePrd::where('item_id', $request->item_id)
                    ->where('item_uom_id', $item_price_obj->item_uom_id)
                    ->first();
                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $cusotmer_lower_price = $cusotmer_price / 1;
                    } else {
                        $cusotmer_lower_price = $cusotmer_price / $upc;
                    }
                }
            }

            $price = 0;
            if ($request->item_uom_id == $item->lower_unit_uom_id) {
                $price = $cusotmer_lower_price;
            } else {
                $item_main_price = ItemMainPricePrd::where('item_id', $request->item_id)
                    ->where('item_uom_id', $request->item_uom_id)
                    ->first();

                if ($item_main_price) {
                    $upc = $item_main_price->item_upc;
                    if ($upc < 1) {
                        $price = $cusotmer_lower_price * 1;
                    } else {
                        $price = $cusotmer_lower_price * $upc;
                    }
                }
            }
        }
        return $this->itemPriceSet($qty, $price, $item, $request);
    }

    private function itemPriceSet_old($qty, $price, $item, $request)
    {
        $item_price = $price;

        $total_price = $item_price + (($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0);
        $item_gross = $qty * $total_price;
        $net_gross = $qty * $item_price;

        $item_excise = ($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0;

        $net_excise = $qty * ($item->is_item_excise == 1) ? exciseConversation($item->item_excise, $item, $request) : 0;

        $total_net = $item_gross - 0;
        $vat = 5;
        if ($item->item_vat_percentage > 0) {
            $vat = $item->item_vat_percentage;
        }
        $item_vat = ($total_net * $vat) / 100;
        $total = $total_net + $item_vat;

        $std_object = new stdClass;
        $std_object->item_qty               = $qty;
        $std_object->item_price             = number_format(round($item_price, 2), 2);
        $std_object->totla_price            = number_format(round($total_price, 2), 2);
        $std_object->item_gross             = number_format($item_gross, 2);
        $std_object->net_gross              = number_format($net_gross, 2);
        $std_object->net_excise             = number_format($net_excise, 2);
        $std_object->discount               = 0;
        $std_object->discount_percentage    = 0;
        $std_object->discount_id            = 0;
        $std_object->total_net              = number_format($total_net, 2);
        $std_object->is_free                = false;
        $std_object->is_item_poi            = false;
        $std_object->promotion_id           = null;
        $std_object->total_excise           = number_format($item_excise, 2);
        $std_object->total_vat              = number_format($item_vat, 2);
        $std_object->total                  = number_format($total, 2);

        return $std_object;
    }

    /**
     * Get price specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    /*private function item_apply_price($request)
    {
        $itemPriceInfo = [];
        $lower_uom = false;
        $pdp_lower_uom = false;
        $request_excise = false;
        $request_lower_uom = false;
        $useThisDiscountID = '';
        $slab_obj = '';

        $itemPrice = ItemMainPricePrd::where('item_id', $request->item_id)
            ->where('item_uom_id', $request->item_uom_id)
            ->first();

        if (!$itemPrice) {
            $itemPrice = ItemPrd::where('id', $request->item_id)
                ->where('lower_unit_uom_id', $request->item_uom_id)
                ->first();
            $lower_uom = true;
        }

        if ($itemPrice) {
            $item_vat_percentage = 0;
            $item_excise = 0;
            $item_excise_uom_id = 0;
            $getTotal = 0;
            $discount = 0;
            $discount_id = 0;
            $discount_per = 0;
            $lower_unit_price = 0;
            $final_excise_amount = 0;
            $slab_obj = "";

            $getItemInfo = ItemPrd::find($request->item_id);

            if ($getItemInfo) {
                if ($getItemInfo->is_tax_apply == 1) {
                    $item_vat_percentage = $getItemInfo->item_vat_percentage;
                    $item_net = $getItemInfo->item_net;
                    if ($getItemInfo->is_item_excise) {
                        $item_excise = $getItemInfo->item_excise;
                        $item_excise_uom_id = $getItemInfo->item_excise_uom_id;
                    }
                }
            }

            if ($request->customer_id) {
                //Get Customer Info
                $getCustomerInfo = CustomerInfoPrd::find($request->customer_id);
                //Location
                $customerCountry = $getCustomerInfo->user->country_id; //1
                $customerRegion = $getCustomerInfo->region_id; //2
                $customerRoute = $getCustomerInfo->route_id; //4

                //Customer
                $getAreaFromRoute = RoutePrd::find($customerRoute);
                $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                $customerChannel = $getCustomerInfo->channel_id; //6
                $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                $customerCustomer = $getCustomerInfo->id; //8
            }

            //Item
            $itemMajorCategory = $getItemInfo->item_major_category_id; //9
            $itemItemGroup = $getItemInfo->item_group_id; //10
            $item = $getItemInfo->id; //11

            if ($request->customer_id) {
                $getPricingList_query_customer = PDPItemPrd::select(
                    'p_d_p_items.id as p_d_p_item_id',
                    'p_d_p_items.item_id as item_id',
                    'price',
                    'p_d_p_items.lob_id',
                    'combination_plan_key_id',
                    'p_d_p_items.price_disco_promo_plan_id',
                    'combination_key_name',
                    'combination_key',
                    'combination_key_code',
                    'price_disco_promo_plans.priority_sequence',
                    'price_disco_promo_plans.use_for',
                    'price_disco_promo_plans.discount_main_type',
                    'item_uom_id',
                    'p_d_p_customers.customer_id'
                )
                    ->join('price_disco_promo_plans', function ($join) {
                        $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                    })
                    ->join('combination_plan_keys', function ($join) {
                        $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                    })
                    ->join('p_d_p_customers', function ($join) {
                        $join->on('price_disco_promo_plans.id', '=', 'p_d_p_customers.price_disco_promo_plan_id');
                    })
                    ->where('p_d_p_customers.customer_id', $request->customer_id)
                    ->where('item_id', $request->item_id)
                    ->whereNull('p_d_p_items.lob_id')
                    ->where('price_disco_promo_plans.organisation_id', 1)
                    ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.status', 1)
                    ->where('combination_plan_keys.status', 1)
                    ->whereNull('price_disco_promo_plans.deleted_at')
                    ->orderBy('priority_sequence', 'ASC')
                    ->orderBy('combination_key_code', 'DESC');

                $getPricingList = $getPricingList_query_customer->get();

                if (count($getPricingList) <= 0 && $request->lob_id) {
                    $getPricingList_query_lob = PDPItemPrd::select(
                        'p_d_p_items.id as p_d_p_item_id',
                        'price',
                        'p_d_p_items.lob_id',
                        'combination_plan_key_id',
                        'p_d_p_items.price_disco_promo_plan_id',
                        'combination_key_name',
                        'combination_key',
                        'combination_key_code',
                        'price_disco_promo_plans.priority_sequence',
                        'price_disco_promo_plans.use_for',
                        'price_disco_promo_plans.discount_main_type',
                        'item_uom_id'
                    )
                        ->join('price_disco_promo_plans', function ($join) {
                            $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                        })
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->where('item_id', $request->item_id)
                        ->where('price_disco_promo_plans.organisation_id', 1)
                        ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->where('p_d_p_items.lob_id', $request->lob_id);

                    $getPricingList = $getPricingList_query_lob->whereNull('price_disco_promo_plans.deleted_at')
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC')
                        ->get();
                }

                if ($getPricingList->count() <= 0) {
                    // for Discount Header Level
                    $getPricingList = DB::connection('server_mysql')
                        ->table('price_disco_promo_plans')
                        ->select('combination_plan_key_id', 'price_disco_promo_plans.id as price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->where('combination_plan_keys.organisation_id', 1)
                        ->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('use_for', 'Discount')
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->whereNull('price_disco_promo_plans.deleted_at')
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC')
                        ->get();
                }

                if ($getPricingList->count() > 0) {
                    $getKey = [];
                    $getDiscountKey = [];

                    foreach ($getPricingList as $key => $filterPrice) {
                        if ($filterPrice->use_for == 'Pricing') {
                            $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence, $filterPrice->lob_id, $filterPrice->item_uom_id);
                        } elseif (
                            isset($filterPrice->p_d_p_item_id) &&
                            isset($filterPrice->price)
                        ) {
                            $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                        } else {
                            $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, null, null, $filterPrice->priority_sequence);
                        }
                    }

                    $useThisPrice = '';
                    $useitemuomId = '';
                    foreach ($getKey as $checking) {
                        $usePrice = false;
                        foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                            $combination_actual_id = explode('/', $checking['combination_actual_id']);
                            $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                            if ($isFind) {
                                $usePrice = true;
                            } else {
                                $usePrice = false;
                                break;
                            }
                        }

                        if ($usePrice) {
                            $useThisPrice = $checking['price'];
                            $useitemuomId = $checking['item_uom_id'];
                            break;
                        }
                    }

                    $useThisType = '';
                    $useThisDiscountPercentage = '';
                    $useThisDiscountType = '';
                    $useThisDiscount = '';
                    $useThisDiscountQty = '';
                    $useThisDiscountApply = '';

                    foreach ($getDiscountKey as $checking) {
                        $useDiscount = false;
                        foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                            $combination_actual_id = explode('/', $checking['combination_actual_id']);

                            $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);

                            if ($isFind) {
                                $useDiscount = true;
                            } else {
                                $useDiscount = false;
                                break;
                            }
                        }

                        if ($useDiscount) {
                            $is_discount = false;
                            $useThisType = $checking['type'];
                            $useThisDiscountType = $checking['discount_type'];
                            if ($checking['discount_type'] == 1) {
                                $useThisDiscount = $checking['discount_value'];
                            }
                            if ($checking['discount_type'] == 2) {
                                $useThisDiscountPercentage = $checking['discount_percentage'];
                            }
                            $useThisDiscountID = $checking['price_disco_promo_plan_id'];
                            $useThisDiscountQty = $checking['qty_to'];
                            $useThisDiscountApply = $checking['discount_apply_on'];
                            $is_discount = true;
                            break;
                        }
                    }
                }

                $item_qty = $request->item_qty;
                if ($lower_uom) {
                    $item_price = $itemPrice->lower_unit_item_price;
                } else {
                    $item_price = $itemPrice->item_price;
                }

                if (isset($usePrice) && $usePrice) {
                    // PDP Item Price set

                    if ($useitemuomId) {
                        $itemPrice = ItemMainPricePrd::where('item_id', $request->item_id)
                            ->where('item_uom_id', $useitemuomId)
                            ->first();

                        if (!$itemPrice) {
                            $itemPrice = ItemPrd::where('id', $request->item_id)
                                ->where('lower_unit_uom_id', $useitemuomId)
                                ->first();
                            $pdp_lower_uom = true;
                        }
                    }

                    $requested_item_data = ItemMainPricePrd::where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->first();

                    if (!is_object($requested_item_data)) {
                        $requested_item_data = ItemPrd::where('id', $request->item_id)
                            ->where('lower_unit_uom_id', $request->item_uom_id)
                            ->first();

                        $request_lower_uom = true;
                    }

                    if ($request_lower_uom && $pdp_lower_uom) {
                        $item_price = $useThisPrice;
                    } else {
                        if ($pdp_lower_uom && !$request_lower_uom) {
                            $request_upc = $requested_item_data->item_upc;
                            // $request_upc = $requested_item_data->lower_unit_item_upc;
                            $item_price = $request_upc * $useThisPrice;
                        } else {
                            $main_price_item_upc = $itemPrice->item_upc;
                            $lower_unit_price = $useThisPrice / $main_price_item_upc;
                            if ($request_lower_uom) {
                                $item_price = $lower_unit_price;
                            } else {
                                $item_price = $lower_unit_price * $requested_item_data->item_upc;
                            }
                        }
                    }
                }

                if (isset($useDiscount) && $useDiscount) {
                    // Slab

                    if ($useThisType == 2) {
                        $discount_slab = PDPDiscountSlabPrd::where('price_disco_promo_plan_id', $useThisDiscountID)->get();
                        $slab_obj = '';
                        foreach ($discount_slab as $slab) {
                            if ($useThisDiscountApply == 1) {
                                if (!$slab->max_slab) {
                                    if ($item_qty >= $slab->min_slab) {
                                        $slab_obj = $slab;
                                        break;
                                    }
                                } else {
                                    if ($item_qty >= $slab->min_slab && $item_qty <= $slab->max_slab) {
                                        $slab_obj = $slab;
                                        break;
                                    }
                                }
                            }

                            if ($useThisDiscountApply == 2) {
                                $item_gross = $item_qty * $item_price;
                                if (!$slab->max_slab) {
                                    if ($item_gross >= $slab->min_slab) {
                                        $slab_obj = $slab;
                                        break;
                                    }
                                } else {
                                    if ($item_gross >= $slab->min_slab && $item_gross <= $slab->max_slab) {
                                        $slab_obj = $slab;
                                        break;
                                    }
                                }
                            }
                        }
                        // slab value
                        if ($useThisDiscountType == 1) {
                            $discount = $slab_obj->value;
                            $discount_id = $useThisDiscountID;
                        }

                        // slab percentage
                        if ($useThisDiscountType == 2) {
                            $discount_id = $useThisDiscountID;
                            $item_gross = $item_qty * $item_price;
                            $discount = $item_gross * $slab_obj->percentage / 100;
                            $discount_per = $slab_obj->percentage;
                        }
                    } else {
                        // 1 is qty
                        if ($useThisDiscountApply == 1) {
                            if ($request->item_qty >= $checking['qty_to']) {
                                // 1: Fixed 2 Percentage
                                if ($useThisDiscountType == 1) {
                                    $discount = $useThisDiscount;
                                    $discount_id = $useThisDiscountID;
                                }

                                if ($useThisDiscountType == 2) {
                                    $discount_id = $useThisDiscountID;
                                    $item_gross = $item_qty * $item_price;
                                    $discount = $item_gross * $useThisDiscountPercentage / 100;
                                    $discount_per = $useThisDiscountPercentage;
                                }
                            }
                        }

                        // 2 is value
                        if ($useThisDiscountApply == 2) {
                            $item_gross = $item_qty * $item_price;
                            if ($item_gross >= $checking['qty_to']) {
                                // 1: Fixed 2 Percentage
                                if ($useThisDiscountType == 1) {
                                    $discount = $useThisDiscount;
                                    $discount_id = $useThisDiscountID;
                                }
                                if ($useThisDiscountType == 2) {
                                    $discount_id = $useThisDiscountID;
                                    $item_gross = $item_qty * $item_price;
                                    $discount = $item_gross * $useThisDiscountPercentage / 100;
                                    $discount_per = $useThisDiscountPercentage;
                                }
                            }
                        }
                    }
                }
            }

            if (!$request->customer_id) {
                $item_qty   = $request->item_qty;
                $item_price = $itemPrice->item_price;
            }
            // Old Condition
            // $item_excise = ($total_net * $item_excise) / 100;
            // $item_excise = $item_excise;
            $finalPrice = 0;
            if ($item_excise != 0) {
                if ($item_excise_uom_id == $request->item_uom_id) {
                    $final_excise_amount = $item_excise;
                    $finalPrice = round($item_price, 2) + $item_excise;
                } else {
                    $requested_item_excise = ItemMainPricePrd::where('item_id', $request->item_id)
                        ->where('item_uom_id', $item_excise_uom_id)
                        ->first();

                    if (!is_object($requested_item_excise)) {
                        $requested_item_excise = ItemPrd::where('id', $request->item_id)
                            ->where('lower_unit_uom_id', $item_excise_uom_id)
                            ->first();

                        $request_excise = true;
                    }

                    $request_upc = 0;
                    $requested_item_data = ItemMainPricePrd::where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->first();

                    if (!is_object($requested_item_data)) {
                        $requested_item_data = ItemPrd::where('id', $request->item_id)
                            ->where('lower_unit_uom_id', $request->item_uom_id)
                            ->first();

                        $request_upc = $requested_item_data->lower_unit_item_upc;
                    } else {
                        $request_upc = $requested_item_data->item_upc;
                    }

                    if ($request_excise) {
                        $excise_amount = $request_upc * $item_excise;
                        $final_excise_amount = $excise_amount;
                        $finalPrice = round($item_price, 2) + $excise_amount;
                    } else {
                        $lower_excise_amount = $item_excise / $requested_item_excise->item_upc;
                        $excise_amount = $request_upc * round($lower_excise_amount, 2);
                        $final_excise_amount = $excise_amount;
                        $finalPrice = round($item_price, 2) + $excise_amount;
                    }
                }
            } else {
                $finalPrice = round($item_price, 2);
            }
            //pre($item_qty,false);
            //pre($finalPrice);exit;
            $totla_price    = $finalPrice;
            $item_gross     = $item_qty * $totla_price;
            $net_gross      = $item_qty * round($item_price, 2);
            $net_excise     = $item_qty * round($final_excise_amount, 2);
            $total_net      = $item_gross - $discount;
            $item_vat       = ($total_net * $item_vat_percentage) / 100;
            $total          = $total_net + $item_vat;

            $itemPriceInfo = [
                'item_qty' => $item_qty,
                'item_price' => number_format(round($item_price, 2), 2),
                'totla_price' => number_format(round($totla_price, 2), 2),
                'item_gross' => number_format($item_gross, 2),
                'net_gross' => number_format($net_gross, 2),
                'net_excise' => number_format($net_excise, 2),
                'discount' => $discount,
                'discount_percentage' => $discount_per,
                'discount_id' => $discount_id,
                'total_net' => number_format($total_net, 2),
                'is_free' => false,
                'is_item_poi' => false,
                'promotion_id' => null,
                'total_excise' => number_format($final_excise_amount, 2),
                'total_vat' => number_format($item_vat, 2),
                'total' => number_format($total, 2)
            ];
        }

        DB::commit();

        return $itemPriceInfo;
        // return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
    }
*/
    public function itemApplyPriceold(Request $request)
    {

        $input = $request->json()->all();
        $validate = $this->validations($input, "item-apply-price");
        if ($validate["error"]) {
            return prepareResult(false, [], ["error" => $validate['errors']->first()], "Error while validating order", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;
            $pdp_lower_uom = false;
            $request_excise = false;
            $request_lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $item_excise_uom_id = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;
                $lower_unit_price = 0;
                $final_excise_amount = 0;

                $getItemInfo = Item::find($request->item_id);

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        if ($getItemInfo->is_item_excise) {
                            $item_excise = $getItemInfo->item_excise;
                            $item_excise_uom_id = $getItemInfo->item_excise_uom_id;
                        }
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                //Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                if ($request->customer_id) {
                    $getPricingList_query_customer = PDPItem::select(
                        'p_d_p_items.id as p_d_p_item_id',
                        'price',
                        'p_d_p_items.lob_id',
                        'combination_plan_key_id',
                        'p_d_p_items.price_disco_promo_plan_id',
                        'combination_key_name',
                        'combination_key',
                        'combination_key_code',
                        'price_disco_promo_plans.priority_sequence',
                        'price_disco_promo_plans.use_for',
                        'price_disco_promo_plans.discount_main_type',
                        'item_uom_id',
                        'p_d_p_customers.customer_id'
                    )
                        ->join('price_disco_promo_plans', function ($join) {
                            $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                        })
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->join('p_d_p_customers', function ($join) {
                            $join->on('price_disco_promo_plans.id', '=', 'p_d_p_customers.price_disco_promo_plan_id');
                        })
                        ->where('p_d_p_customers.customer_id', $request->customer_id)
                        ->where('item_id', $request->item_id)
                        ->whereNull('p_d_p_items.lob_id')
                        ->where('price_disco_promo_plans.organisation_id', auth()->user()->organisation_id)
                        ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->whereNull('price_disco_promo_plans.deleted_at')
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC');

                    $getPricingList = $getPricingList_query_customer->get();

                    if (count($getPricingList) <= 0 && $request->lob_id) {
                        $getPricingList_query_lob = PDPItem::select(
                            'p_d_p_items.id as p_d_p_item_id',
                            'price',
                            'p_d_p_items.lob_id',
                            'combination_plan_key_id',
                            'p_d_p_items.price_disco_promo_plan_id',
                            'combination_key_name',
                            'combination_key',
                            'combination_key_code',
                            'price_disco_promo_plans.priority_sequence',
                            'price_disco_promo_plans.use_for',
                            'price_disco_promo_plans.discount_main_type',
                            'item_uom_id'
                        )
                            ->join('price_disco_promo_plans', function ($join) {
                                $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                            })
                            ->join('combination_plan_keys', function ($join) {
                                $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                            })
                            ->where('item_id', $request->item_id)
                            ->where('price_disco_promo_plans.organisation_id', 1)
                            ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                            ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                            ->where('price_disco_promo_plans.status', 1)
                            ->where('combination_plan_keys.status', 1)
                            ->where('p_d_p_items.lob_id', $request->lob_id);

                        $getPricingList = $getPricingList_query_lob->whereNull('price_disco_promo_plans.deleted_at')
                            ->orderBy('priority_sequence', 'ASC')
                            ->orderBy('combination_key_code', 'DESC')
                            ->get();
                    }

                    if ($getPricingList->count() <= 0) {
                        // for Discount Header Level
                        $getPricingList = DB::table('price_disco_promo_plans')
                            ->select('combination_plan_key_id', 'price_disco_promo_plans.id as price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                            ->join('combination_plan_keys', function ($join) {
                                $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                            })
                            ->where('combination_plan_keys.organisation_id', 1)
                            ->where('start_date', '<=', date('Y-m-d'))
                            ->where('end_date', '>=', date('Y-m-d'))
                            ->where('use_for', 'Discount')
                            ->where('price_disco_promo_plans.status', 1)
                            ->where('combination_plan_keys.status', 1)
                            ->whereNull('price_disco_promo_plans.deleted_at')
                            ->orderBy('priority_sequence', 'ASC')
                            ->orderBy('combination_key_code', 'DESC')
                            ->get();
                    }

                    if ($getPricingList->count() > 0) {
                        $getKey = [];
                        $getDiscountKey = [];

                        foreach ($getPricingList as $key => $filterPrice) {
                            if ($filterPrice->use_for == 'Pricing') {
                                $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence, $filterPrice->lob_id, $filterPrice->item_uom_id);
                            } elseif (
                                isset($filterPrice->p_d_p_item_id) &&
                                isset($filterPrice->price)
                            ) {
                                $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            } else {
                                $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, null, null, $filterPrice->priority_sequence);
                            }
                        }

                        $useThisPrice = '';
                        $useitemuomId = '';
                        foreach ($getKey as $checking) {
                            $usePrice = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $usePrice = true;
                                } else {
                                    $usePrice = false;
                                    break;
                                }
                            }

                            if ($usePrice) {
                                $useThisPrice = $checking['price'];
                                $useitemuomId = $checking['item_uom_id'];
                                break;
                            }
                        }

                        $useThisType = '';
                        $useThisDiscountPercentage = '';
                        $useThisDiscountType = '';
                        $useThisDiscount = '';
                        $useThisDiscountQty = '';
                        $useThisDiscountApply = '';

                        foreach ($getDiscountKey as $checking) {
                            $useDiscount = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);

                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);

                                if ($isFind) {
                                    $useDiscount = true;
                                } else {
                                    $useDiscount = false;
                                    break;
                                }
                            }

                            if ($useDiscount) {
                                $is_discount = false;
                                $useThisType = $checking['type'];
                                $useThisDiscountType = $checking['discount_type'];
                                if ($checking['discount_type'] == 1) {
                                    $useThisDiscount = $checking['discount_value'];
                                }
                                if ($checking['discount_type'] == 2) {
                                    $useThisDiscountPercentage = $checking['discount_percentage'];
                                }
                                $useThisDiscountID = $checking['price_disco_promo_plan_id'];
                                $useThisDiscountQty = $checking['qty_to'];
                                $useThisDiscountApply = $checking['discount_apply_on'];
                                $is_discount = true;
                                break;
                            }
                        }
                    }

                    $item_qty = $request->item_qty;
                    if ($lower_uom) {
                        $item_price = $itemPrice->lower_unit_item_price;
                    } else {
                        $item_price = $itemPrice->item_price;
                    }

                    if (isset($usePrice) && $usePrice) {
                        // PDP Item Price set

                        if ($useitemuomId) {
                            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                                ->where('item_uom_id', $useitemuomId)
                                ->first();

                            if (!$itemPrice) {
                                $itemPrice = Item::where('id', $request->item_id)
                                    ->where('lower_unit_uom_id', $useitemuomId)
                                    ->first();
                                $pdp_lower_uom = true;
                            }
                        }


                        $requested_item_data = ItemMainPrice::where('item_id', $request->item_id)
                            ->where('item_uom_id', $request->item_uom_id)
                            ->first();

                        if (!is_object($requested_item_data)) {
                            $requested_item_data = Item::where('id', $request->item_id)
                                ->where('lower_unit_uom_id', $request->item_uom_id)
                                ->first();

                            $request_lower_uom = true;
                        }

                        if ($request_lower_uom && $pdp_lower_uom) {
                            $item_price = $useThisPrice;
                        } else {
                            if ($pdp_lower_uom) {
                                $request_upc = $requested_item_data->item_upc;
                                $item_price = $request_upc * $useThisPrice;
                            } else {
                                $main_price_item_upc = $itemPrice->item_upc;
                                $lower_unit_price = $useThisPrice / $main_price_item_upc;
                                if ($request_lower_uom) {
                                    $item_price = $lower_unit_price;
                                } else {
                                    $item_price = $lower_unit_price * $requested_item_data->item_upc;
                                }
                            }
                        }
                    }

                    if (isset($useDiscount) && $useDiscount) {
                        // Slab

                        if ($useThisType == 2) {
                            $discount_slab = PDPDiscountSlab::where('price_disco_promo_plan_id', $useThisDiscountID)->get();
                            $slab_obj = '';
                            foreach ($discount_slab as $slab) {
                                if ($useThisDiscountApply == 1) {
                                    if (!$slab->max_slab) {
                                        if ($item_qty >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_qty >= $slab->min_slab && $item_qty <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }

                                if ($useThisDiscountApply == 2) {
                                    $item_gross = $item_qty * $item_price;
                                    if (!$slab->max_slab) {
                                        if ($item_gross >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_gross >= $slab->min_slab && $item_gross <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                            }
                            // slab value
                            if ($useThisDiscountType == 1) {
                                $discount = $slab_obj->value;
                                $discount_id = $useThisDiscountID;
                            }

                            // slab percentage
                            if ($useThisDiscountType == 2) {
                                $discount_id = $useThisDiscountID;
                                $item_gross = $item_qty * $item_price;
                                $discount = $item_gross * $slab_obj->percentage / 100;
                                $discount_per = $slab_obj->percentage;
                            }
                        } else {
                            // 1 is qty
                            if ($useThisDiscountApply == 1) {
                                if ($request->item_qty >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }

                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }

                            // 2 is value
                            if ($useThisDiscountApply == 2) {
                                $item_gross = $item_qty * $item_price;
                                if ($item_gross >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$request->customer_id) {
                    $item_qty   = $request->item_qty;
                    $item_price = $itemPrice->item_price;
                }

                // Old Condition
                // $item_excise = ($total_net * $item_excise) / 100;
                // $item_excise = $item_excise;
                $finalPrice = 0;
                if ($item_excise != 0) {
                    if ($item_excise_uom_id == $request->item_uom_id) {
                        $final_excise_amount = $item_excise;
                        $finalPrice = round($item_price, 2) + $item_excise;
                    } else {
                        $requested_item_excise = ItemMainPrice::where('item_id', $request->item_id)
                            ->where('item_uom_id', $item_excise_uom_id)
                            ->first();

                        if (!is_object($requested_item_excise)) {
                            $requested_item_excise = Item::where('id', $request->item_id)
                                ->where('lower_unit_uom_id', $item_excise_uom_id)
                                ->first();

                            $request_excise = true;
                        }

                        $request_upc = 0;
                        $requested_item_data = ItemMainPrice::where('item_id', $request->item_id)
                            ->where('item_uom_id', $request->item_uom_id)
                            ->first();

                        if (!is_object($requested_item_data)) {
                            $requested_item_data = Item::where('id', $request->item_id)
                                ->where('lower_unit_uom_id', $request->item_uom_id)
                                ->first();

                            $request_upc = $requested_item_data->lower_unit_item_upc;
                        } else {
                            $request_upc = $requested_item_data->item_upc;
                        }

                        if ($request_excise) {
                            $excise_amount = $request_upc * $item_excise;
                            $final_excise_amount = $excise_amount;
                            $finalPrice = round($item_price, 2) + $excise_amount;
                        } else {
                            $lower_excise_amount = $item_excise / $requested_item_excise->item_upc;
                            $excise_amount = $request_upc * round($lower_excise_amount, 2);
                            $final_excise_amount = $excise_amount;
                            $finalPrice = round($item_price, 2) + $excise_amount;
                        }
                    }
                } else {
                    $finalPrice = round($item_price, 2);
                }

                $totla_price    = $finalPrice;
                $item_gross     = $item_qty * $totla_price;
                $net_gross      = $item_qty * round($item_price, 2);
                $net_excise     = $item_qty * round($final_excise_amount, 2);
                $total_net      = $item_gross - $discount;
                $item_vat       = ($total_net * $item_vat_percentage) / 100;
                $total          = $total_net + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => number_format(round($item_price, 2), 2),
                    'totla_price' => number_format(round($totla_price, 2), 2),
                    'item_gross' => number_format($item_gross, 2),
                    'net_gross' => number_format($net_gross, 2),
                    'net_excise' => number_format($net_excise, 2),
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => number_format($total_net, 2),
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => number_format($final_excise_amount, 2),
                    'total_vat' => number_format($item_vat, 2),
                    'total' => number_format($total, 2)
                ];
            }

            DB::commit();
            return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }
    }

    public function itemApplyPriceMultiple(Request $request)
    {


        $input = $request->json()->all();
        if (empty($input)) {
            return prepareResult(false, [], ['error' => "Error while validating empty data array"], "Error while validating empty data array", $this->unprocessableEntity);
        }

        $totalItems = count($input);
        $itemPriceInfo = array();
        for ($i = 0; $i < $totalItems; $i++) {
            $validate = $this->validations($input[$i], "item-apply-price");
            if ($validate["error"]) {
                return prepareResult(false, [], ['error' => "Error while validating empty data array"], "Error while validating empty data array", $this->unprocessableEntity);
            }

            try {
                $retData = $this->singleItemApplyPrice((object)$input[$i]);
                if ($retData['status']) {
                    if (!empty($retData['itemPriceInfo'])) {
                        $itemPriceInfo[] = $retData['itemPriceInfo'];
                    }
                } else {
                    return prepareResult(false, [], ['error' => "Oops!!!, something went wrong, please try again"], "Oops!!!, something went wrong, please try again", $this->internal_server_error);
                }
            } catch (Throwable $exception) {
                return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
            }
        }

        return prepareResult(true, $itemPriceInfo, [], "Item prices.", $this->created);
    }

    private function singleItemApplyPrice($request)
    {
        DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;

                //Default Price
                $getItemInfo = Item::where('id', $request->item_id)
                    ->first();

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        $item_excise = $getItemInfo->item_excise;
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                ////Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                if ($request->customer_id) {

                    //Check Price : different level
                    $getPricingList = PDPItemPrd::select('p_d_p_items.id as p_d_p_item_id', 'price', 'lob_id', 'combination_plan_key_id', 'price_disco_promo_plan_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'price_disco_promo_plans.priority_sequence', 'price_disco_promo_plans.use_for')
                        ->join('price_disco_promo_plans', function ($join) {
                            $join->on('p_d_p_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                        })
                        ->join('combination_plan_keys', function ($join) {
                            $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                        })
                        ->where('item_id', $request->item_id)
                        ->where('item_uom_id', $request->item_uom_id)
                        ->where('price_disco_promo_plans.organisation_id', 1)
                        ->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('price_disco_promo_plans.status', 1)
                        ->where('combination_plan_keys.status', 1)
                        ->whereNull('price_disco_promo_plans.deleted_at')
                        ->orderBy('priority_sequence', 'ASC')
                        ->orderBy('combination_key_code', 'DESC')
                        ->get();


                    if ($getPricingList->count() > 0) {
                        $getKey = [];
                        $getDiscountKey = [];
                        foreach ($getPricingList as $key => $filterPrice) {
                            if ($filterPrice->use_for == 'Pricing') {
                                $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence, $filterPrice->lob_id);
                            } else {
                                $getDiscountKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_item_id, $filterPrice->price, $filterPrice->priority_sequence);
                            }
                        }

                        // $checkKeyForPrice = $this->arrayOrderDesc($getKey, 'hierarchyNumber');

                        $useThisPrice = '';
                        foreach ($getKey as $checking) {
                            $usePrice = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $usePrice = true;
                                } else {
                                    $usePrice = false;
                                    break;
                                }
                            }

                            if ($usePrice) {
                                $useThisPrice = $checking['price'];
                                break;
                            }
                        }

                        $useThisType = '';
                        $useThisDiscountPercentage = '';
                        $useThisDiscountType = '';
                        $useThisDiscount = '';
                        $useThisDiscountQty = '';
                        $useThisDiscountApply = '';

                        foreach ($getDiscountKey as $checking) {
                            $useDiscount = false;
                            foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                                $combination_actual_id = explode('/', $checking['combination_actual_id']);
                                $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                                if ($isFind) {
                                    $useDiscount = true;
                                } else {
                                    $useDiscount = false;
                                    break;
                                }
                            }

                            if ($useDiscount) {
                                $is_discount = false;
                                $useThisType = $checking['type'];
                                $useThisDiscountType = $checking['discount_type'];
                                if ($checking['discount_type'] == 1) {
                                    $useThisDiscount = $checking['discount_value'];
                                }
                                if ($checking['discount_type'] == 2) {
                                    $useThisDiscountPercentage = $checking['discount_percentage'];
                                }
                                $useThisDiscountID = $checking['price_disco_promo_plan_id'];
                                $useThisDiscountQty = $checking['qty_to'];
                                $useThisDiscountApply = $checking['discount_apply_on'];
                                $is_discount = true;
                                break;
                            }
                        }

                        //return prepareResult(true, $checkKeyForPrice, [], "Item price.", $this->created);
                    }

                    $item_qty = $request->item_qty;
                    if ($lower_uom) {
                        $item_price = $itemPrice->lower_unit_item_price;
                    } else {
                        $item_price = $itemPrice->item_price;
                    }

                    if (isset($usePrice) && $usePrice) {
                        $item_price = $useThisPrice;
                    }

                    if (isset($useDiscount) && $useDiscount) {
                        // Slab

                        if ($useThisType == 2) {
                            $discount_slab = PDPDiscountSlab::where('price_disco_promo_plan_id', $useThisDiscountID)->get();
                            $slab_obj = '';
                            foreach ($discount_slab as $slab) {
                                if ($useThisDiscountApply == 1) {
                                    if (!$slab->max_slab) {
                                        if ($item_qty >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_qty >= $slab->min_slab && $item_qty <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                                if ($useThisDiscountApply == 2) {
                                    $item_gross = $item_qty * $item_price;
                                    if (!$slab->max_slab) {
                                        if ($item_gross >= $slab->min_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    } else {
                                        if ($item_gross >= $slab->min_slab && $item_gross <= $slab->max_slab) {
                                            $slab_obj = $slab;
                                            break;
                                        }
                                    }
                                }
                            }
                            // slab value
                            if ($useThisDiscountType == 1) {
                                $discount = $slab_obj->value;
                                $discount_id = $useThisDiscountID;
                            }
                            // slab percentage
                            if ($useThisDiscountType == 2) {
                                $discount_id = $useThisDiscountID;
                                $item_gross = $item_qty * $item_price;
                                $discount = $item_gross * $slab_obj->percentage / 100;
                                $discount_per = $slab_obj->percentage;
                            }
                        } else {
                            // 1 is qty
                            if ($useThisDiscountApply == 1) {
                                if ($request->item_qty >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }

                            // 2 is value
                            if ($useThisDiscountApply == 2) {
                                $item_gross = $item_qty * $item_price;
                                if ($item_gross >= $checking['qty_to']) {
                                    // 1: Fixed 2 Percentage
                                    if ($useThisDiscountType == 1) {
                                        $discount = $useThisDiscount;
                                        $discount_id = $useThisDiscountID;
                                    }
                                    if ($useThisDiscountType == 2) {
                                        $discount_id = $useThisDiscountID;
                                        $item_gross = $item_qty * $item_price;
                                        $discount = $item_gross * $useThisDiscountPercentage / 100;
                                        $discount_per = $useThisDiscountPercentage;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$request->customer_id) {
                    $item_qty = $request->item_qty;
                    $item_price = $itemPrice->item_price;
                }

                $item_gross = $item_qty * $item_price;
                $total_net = $item_gross - $discount;
                $item_excise = ($total_net * $item_excise) / 100;
                $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                $total = $total_net + $item_excise + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => $item_price,
                    'item_gross' => $item_gross,
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => $total_net,
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => $item_excise,
                    'total_vat' => $item_vat,
                    'total' => $total,
                ];
            }

            DB::commit();
            $retArray['status'] = true;
            $retArray['itemPriceInfo'] = $itemPriceInfo;
        } catch (\Exception $exception) {
            $retArray['status'] = false;
        } catch (\Throwable $exception) {
            $retArray['status'] = false;
        }

        return $retArray;
    }

    private function makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $combination_key_code, $combination_key, $price_disco_promo_plan_id, $p_d_p_item_id, $price, $priority_sequence, $lob_id = null, $item_uom_id = null)
    {
        $keyCodes = '';
        $combination_actual_id = '';
        foreach (explode('/', $combination_key_code) as $hierarchyNumber) {
            switch ($hierarchyNumber) {
                case '1':
                    if (empty($add)) {
                        $add = $customerCountry;
                    } else {
                        $add = '/' . $customerCountry;
                    }
                    // $add  = $customerCountry;
                    break;
                case '2':
                    if (empty($add)) {
                        $add = $customerRegion;
                    } else {
                        $add = '/' . $customerRegion;
                    }
                    // $add  = '/' . $customerRegion;
                    break;
                case '3':
                    if (empty($add)) {
                        $add = $customerArea;
                    } else {
                        $add = '/' . $customerArea;
                    }
                    // $add  = '/' . $customerArea;
                    break;
                case '4':
                    if (empty($add)) {
                        $add = $customerRoute;
                    } else {
                        $add = '/' . $customerRoute;
                    }
                    // $add  = '/' . $customerRoute;
                    break;
                case '5':
                    if (empty($add)) {
                        $add = $customerSalesOrganisation;
                    } else {
                        $add = '/' . $customerSalesOrganisation;
                    }
                    break;
                case '6':
                    if (empty($add)) {
                        $add = $customerChannel;
                    } else {
                        $add = '/' . $customerChannel;
                    }
                    // $add  = '/' . $customerChannel;
                    break;
                case '7':
                    if (empty($add)) {
                        $add = $customerCustomerCategory;
                    } else {
                        $add = '/' . $customerCustomerCategory;
                    }
                    // $add  = '/' . $customerCustomerCategory;
                    break;
                case '8':
                    if (empty($add)) {
                        $add = $customerCustomer;
                    } else {
                        $add = '/' . $customerCustomer;
                    }
                    // $add  = '/' . $customerCustomer;
                    break;
                case '9':
                    if (empty($add)) {
                        $add = $itemMajorCategory;
                    } else {
                        $add = '/' . $itemMajorCategory;
                    }
                    // $add  = '/' . $itemMajorCategory;
                    break;
                case '10':
                    if (empty($add)) {
                        $add = $itemItemGroup;
                    } else {
                        $add = '/' . $itemItemGroup;
                    }
                    // $add  = '/' . $itemItemGroup;
                    break;
                case '11':
                    if (empty($add)) {
                        $add = $item;
                    } else {
                        $add = '/' . $item;
                    }
                    break;
                default:
                    # code...
                    break;
            }
            $keyCodes .= $hierarchyNumber;
            $combination_actual_id .= $add;
        }

        $getIdentify = PriceDiscoPromoPlan::find($price_disco_promo_plan_id);
        $discount = array();
        // $returnData = array();

        if ($getIdentify->use_for == 'Promotion') {
            return array(
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
                'auto_sequence_by_code' => $hierarchyNumber,
                'hierarchyNumber' => $keyCodes,
                'p_d_p_promotion_items' => $p_d_p_item_id,
                'priority_sequence' => $priority_sequence,
                'price' => $price,
                'use_for' => $getIdentify->use_for
            );
        }

        if ($getIdentify->use_for == 'Discount') {
            return array(
                'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
                'combination_key' => $combination_key,
                'combination_key_code' => $combination_key_code,
                'combination_actual_id' => $combination_actual_id,
                'auto_sequence_by_code' => $hierarchyNumber,
                'hierarchyNumber' => $keyCodes,
                'p_d_p_item_id' => $p_d_p_item_id,
                'priority_sequence' => $priority_sequence,
                'price' => $price,
                'use_for' => $getIdentify->use_for,
                'type' => $getIdentify->type,
                'qty_from' => $getIdentify->qty_from,
                'qty_to' => $getIdentify->qty_to,
                'discount_type' => $getIdentify->discount_type,
                'discount_value' => $getIdentify->discount_value,
                'discount_percentage' => $getIdentify->discount_percentage,
                'discount_apply_on' => $getIdentify->discount_apply_on
            );
        }

        $returnData = [
            'price_disco_promo_plan_id' => $price_disco_promo_plan_id,
            'combination_key' => $combination_key,
            'combination_key_code' => $combination_key_code,
            'combination_actual_id' => $combination_actual_id,
            // 'auto_sequence_by_depth' => explode('/', $combination_key_code),
            // 'auto_sequence_by_depth_count' => count(explode('/', $combination_key_code)),
            'auto_sequence_by_code' => $hierarchyNumber,
            'hierarchyNumber' => $keyCodes,
            'p_d_p_item_id' => $p_d_p_item_id,
            'item_uom_id' => $item_uom_id,
            'priority_sequence' => $priority_sequence,
            'price' => $price,
            'use_for' => $getIdentify->use_for
        ];

        return $returnData;
    }

    private function arrayOrderDesc()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            // if (is_string($field)) {
            //     $tmp = array();
            //     foreach ($data as $key => $row)
            //         $tmp[$key] = $row[$field];
            //     $args[$n] = $tmp;
            // }
            foreach ($data as $key => $row) {
                $return_fare[$n] = $row[$field];
                $one_way_fare[$n] = $row['priority_sequence'];
            }
        }
        $sorted = array_multisort(
            array_column($data, 'hierarchyNumber'),
            SORT_ASC,
            array_column($data, 'priority_sequence'),
            SORT_DESC,
            $data
        );

        return $data;
        // $sorted = array_multisort($data, 'one_way_fare', SORT_ASC, 'return_fare', SORT_DESC);
        // $args[] = &$data;
        // call_user_func_array('array_multisort', $args);
        // return array_pop($args);
    }

    private function checkDataExistOrNot(
        $combination_key_number,
        $combination_actual_id,
        $price_disco_promo_plan_id
    ) {
        switch ($combination_key_number) {
            case '1':
                $model = 'App\Model\PDPCountry';
                $field = 'country_id';
                break;
            case '2':
                $model = 'App\Model\PDPRegion';
                $field = 'region_id';
                break;
            case '3':
                $model = 'App\Model\PDPArea';
                $field = 'area_id';
                break;
            case '4':
                $model = 'App\Model\PDPRoute';
                $field = 'route_id';
                break;
            case '5':
                $model = 'App\Model\PDPSalesOrganisation';
                $field = 'sales_organisation_id';
                break;
            case '6':
                $model = 'App\Model\PDPChannel';
                $field = 'channel_id';
                break;
            case '7':
                $model = 'App\Model\PDPCustomerCategory';
                $field = 'customer_category_id';
                break;
            case '8':
                $model = 'App\Model\PDPCustomer';
                $field = 'customer_id';
                break;
            case '9':
                $model = 'App\Model\PDPItemMajorCategory';
                $field = 'item_major_category_id';
                break;
            case '10':
                $model = 'App\Model\PDPItemGroup';
                $field = 'item_group_id';
                break;
            case '11':
                $model = 'App\Model\PDPItem';
                $field = 'item_id';
                break;
            default:
                $model = '';
                $field = '';
                break;
        }

        $checkExistOrNot = $model::where('price_disco_promo_plan_id', $price_disco_promo_plan_id)->where($field, $combination_actual_id)->first();

        if ($checkExistOrNot) {
            return true;
        }

        return false;
    }

    private function getListByParam($obj, $param)
    {
        $object = $obj;
        $array = [];
        $get = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($object), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($get as $key => $value) {
            if ($key === $param) {
                $array = array_merge($array, $value);
            }
        }
        return $array;
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'order_type_id' => 'required|integer|exists:order_types,id',
                'order_number' => 'required',
                'due_date' => 'required|date',
                'delivery_date' => 'required|date',
                'total_qty' => 'required',
                'total_discount_amount' => 'required',
                'total_vat' => 'required',
                'total_net' => 'required',
                'total_excise' => 'required',
                'grand_total' => 'required',
                'source' => 'required|integer',
            ]);
        }
		if ($type == "insert") {
            $validator = \Validator::make($input, [
                'date' => 'required',
                'branch' => 'required',
            ]);
        }

        if ($type == "addPayment") {
            $validator = \Validator::make($input, [
                // 'payment_term_id' => 'required|integer|exists:payment_terms,id'
            ]);
        }

        if ($type == 'bulk-action') {
            $validator = \Validator::make($input, [
                'action' => 'required',
                'order_ids' => 'required'
            ]);
        }

        if ($type == 'item-apply-price') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'normal-item-apply-price') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id',
                'item_uom_id' => 'required|integer|exists:item_uoms,id',
                'item_qty' => 'required|numeric',
            ]);
        }

        if ($type == 'applyPDP') {
            $validator = \Validator::make($input, [
                'item_id' => 'required|integer|exists:items,id'
                // 'item_uom_id'   => 'required|integer|exists:item_uoms,id',
                // 'item_qty'      => 'required|numeric',
            ]);
        }

        if ($type == 'cancel') {
            $validator = \Validator::make($input, [
                'order_id' => 'required|integer|exists:orders,id',
                'reason_id' => 'required|integer|exists:reason_types,id',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * Get price specified item and item UOM.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function normalItemApplyPrice(Request $request)
    {


        $input = $request->json()->all();
        $validate = $this->validations($input, "normal-item-apply-price");
        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating order", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            $itemPriceInfo = [];
            $lower_uom = false;

            $itemPrice = ItemMainPrice::where('item_id', $request->item_id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

            if (!$itemPrice) {
                $itemPrice = Item::where('id', $request->item_id)
                    ->where('lower_unit_uom_id', $request->item_uom_id)
                    ->first();
                $lower_uom = true;
            }

            if ($itemPrice) {
                $item_vat_percentage = 0;
                $item_excise = 0;
                $getTotal = 0;
                $discount = 0;
                $discount_id = 0;
                $discount_per = 0;

                //////////Default Price
                $getItemInfo = Item::where('id', $request->item_id)
                    ->first();

                if ($getItemInfo) {
                    if ($getItemInfo->is_tax_apply == 1) {
                        $item_vat_percentage = $getItemInfo->item_vat_percentage;
                        $item_net = $getItemInfo->item_net;
                        $item_excise = $getItemInfo->item_excise;
                    }
                }

                if ($request->customer_id) {
                    //Get Customer Info
                    $getCustomerInfo = CustomerInfo::find($request->customer_id);
                    //Location
                    $customerCountry = $getCustomerInfo->user->country_id; //1
                    $customerRegion = $getCustomerInfo->region_id; //2
                    $customerRoute = $getCustomerInfo->route_id; //4

                    //Customer
                    $getAreaFromRoute = Route::find($customerRoute);
                    $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                    $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                    $customerChannel = $getCustomerInfo->channel_id; //6
                    $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                    $customerCustomer = $getCustomerInfo->id; //8
                }

                ////Item
                $itemMajorCategory = $getItemInfo->item_major_category_id; //9
                $itemItemGroup = $getItemInfo->item_group_id; //10
                $item = $getItemInfo->id; //11

                // if (!$request->customer_id) {
                if ($lower_uom) {
                    $item_price = $itemPrice->lower_unit_item_price;
                } else {
                    $item_price = $itemPrice->item_price;
                }
                $item_qty = $request->item_qty;
                // $item_price     = $itemPrice->item_price;
                // }

                $item_gross = $item_qty * $item_price;
                $total_net = $item_gross - $discount;
                $item_excise = ($total_net * $item_excise) / 100;
                $item_vat = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                $total = $total_net + $item_excise + $item_vat;

                $itemPriceInfo = [
                    'item_qty' => $item_qty,
                    'item_price' => $item_price,
                    'item_gross' => $item_gross,
                    'discount' => $discount,
                    'discount_percentage' => $discount_per,
                    'discount_id' => $discount_id,
                    'total_net' => $total_net,
                    'is_free' => false,
                    'is_item_poi' => false,
                    'promotion_id' => null,
                    'total_excise' => $item_excise,
                    'total_vat' => $item_vat,
                    'total' => $total,
                ];
            }

            DB::commit();
            return prepareResult(true, $itemPriceInfo, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }
    }

    /**
     * Get price specified item and item UOM.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */
    public function itemApplyPromotion(Request $request)
    {


        if (is_array($request->item_id) && sizeof($request->item_id) < 1) {
            return prepareResult(false, [], ['error' => "Error Please add atleast one items."], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (is_array($request->item_uom_id) && sizeof($request->item_uom_id) < 1) {
            return prepareResult(false, [], ['error' => "Error Please add atleast one items UOM."], "Error Please add atleast one items UOM.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            $itemPromotionInfo = [];
            $offerItems = [];
            $item_vat_percentage = 0;
            $item_excise = 0;
            $getTotal = 0;
            $discount = 0;
            $discount_id = 0;
            $discount_per = 0;

            $itemPrice = ItemMainPrice::whereIn('item_id', $request->item_id)
                ->whereIn('item_uom_id', $request->item_uom_id)
                ->get();

            if (count($itemPrice)) {
                $getItemInfo = Item::whereIn('id', $request->item_id)
                    ->get();
            }

            if ($request->customer_id) {
                //Get Customer Info
                $getCustomerInfo = CustomerInfo::find($request->customer_id);
                //Location
                $customerCountry = $getCustomerInfo->user->country_id; //1
                $customerRegion = $getCustomerInfo->region_id; //2
                $customerRoute = $getCustomerInfo->route_id; //4

                //Customer
                $getAreaFromRoute = Route::find($customerRoute);
                $customerArea = ($getAreaFromRoute) ? $getAreaFromRoute->area_id : null; //3
                $customerSalesOrganisation = $getCustomerInfo->sales_organisation_id; //5
                $customerChannel = $getCustomerInfo->channel_id; //6
                $customerCustomerCategory = $getCustomerInfo->customer_category_id; //7
                $customerCustomer = $getCustomerInfo->id; //8
            }

            if ($request->customer_id) {
                $getPricingList = PDPPromotionItem::select('p_d_p_promotion_items.id as p_d_p_promotion_items_id', 'p_d_p_promotion_items.price_disco_promo_plan_id', 'p_d_p_promotion_items.item_id', 'p_d_p_promotion_items.item_uom_id', 'p_d_p_promotion_items.item_qty', 'p_d_p_promotion_items.price', 'combination_plan_key_id', 'combination_key_name', 'combination_key', 'combination_key_code', 'priority_sequence', 'use_for')
                    ->join('price_disco_promo_plans', function ($join) {
                        $join->on('p_d_p_promotion_items.price_disco_promo_plan_id', '=', 'price_disco_promo_plans.id');
                    })
                    ->join('combination_plan_keys', function ($join) {
                        $join->on('price_disco_promo_plans.combination_plan_key_id', '=', 'combination_plan_keys.id');
                    })
                    ->whereIn('item_id', $request->item_id)
                    ->whereIn('item_uom_id', $request->item_uom_id)
                    // ->whereIn('item_qty', $request->item_qty)
                    ->where('price_disco_promo_plans.organisation_id', 1)
                    ->where('price_disco_promo_plans.start_date', '<=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.end_date', '>=', date('Y-m-d'))
                    ->where('price_disco_promo_plans.status', 1)
                    ->where('combination_plan_keys.status', 1)
                    ->orderBy('price_disco_promo_plans.priority_sequence', 'ASC')
                    ->orderBy('combination_plan_keys.combination_key_code', 'DESC')
                    ->get();

                if ($getPricingList->count() > 0) {
                    $getKey = [];
                    $getDiscountKey = [];
                    foreach ($getPricingList as $key => $filterPrice) {
                        $items = Item::where('id', $filterPrice->item_id)->first();
                        $itemMajorCategory = $items->item_major_category_id; //9
                        $itemItemGroup = $items->item_group_id; //10
                        $item = $items->id; //11
                        if (empty($request->item_qty[$key])) {
                            continue;
                        }

                        if ($filterPrice->item_qty > $request->item_qty[$key]) {
                            continue;
                        }

                        $getKey[] = $this->makeKeyValue($customerCountry, $customerRegion, $customerArea, $customerRoute, $customerSalesOrganisation, $customerChannel, $customerCustomerCategory, $customerCustomer, $itemMajorCategory, $itemItemGroup, $item, $filterPrice->combination_key_code, $filterPrice->combination_key, $filterPrice->price_disco_promo_plan_id, $filterPrice->p_d_p_promotion_items_id, $filterPrice->price, $filterPrice->priority_sequence);
                    }

                    $result = array();
                    $price_disco_promo_plan_id = '';
                    foreach ($getKey as $element) {
                        if ($price_disco_promo_plan_id != $element['price_disco_promo_plan_id']) {
                            $price_disco_promo_plan_id = $element['price_disco_promo_plan_id'];
                            $result[] = $element;
                        }
                    }

                    // Check order item and offer item
                    foreach ($result as $checking) {
                        $usePromotion = false;
                        foreach (explode('/', $checking['combination_key_code']) as $key => $combination) {
                            $combination_actual_id = explode('/', $checking['combination_actual_id']);
                            $isFind = $this->checkDataExistOrNot($combination, $combination_actual_id[$key], $checking['price_disco_promo_plan_id']);
                            if ($isFind) {
                                $usePromotion = true;
                            } else {
                                $usePromotion = false;
                                break;
                            }
                        }

                        if ($checking['price_disco_promo_plan_id']) {
                            $price_disco_promo_plan = PriceDiscoPromoPlan::where('id', $checking['price_disco_promo_plan_id'])
                                ->with(
                                    'PDPPromotionItems',
                                    'PDPPromotionItems.item',
                                    'PDPPromotionItems.itemUom'
                                )
                                ->first();

                            $is_promotion = false;
                            $promotion_item = array();
                            $PDPPromotionItems = $price_disco_promo_plan->PDPPromotionItems;

                            $price_disco_promo_plan_offer = PriceDiscoPromoPlan::where('id', $checking['price_disco_promo_plan_id'])
                                ->with(
                                    'PDPPromotionOfferItems',
                                    'PDPPromotionOfferItems.item',
                                    'PDPPromotionOfferItems.itemUom:id,name'
                                )
                                ->first();

                            foreach ($PDPPromotionItems as $key => $item) {
                                if ($price_disco_promo_plan->order_item_type == "All") {
                                    if (!empty($request->item_qty[$key]) && count($PDPPromotionItems) == count($request->item_qty)) {
                                        $qty = $request->item_qty[$key];

                                        if ($item->item_qty <= $qty) {
                                            $is_promotion   = true;
                                            $offerItems     = $price_disco_promo_plan_offer->PDPPromotionOfferItems;
                                            $item_price     = $item->price;
                                            $item_qty       = $qty;
                                            $item_gross     = $item_qty * $item_price;
                                            $total_net      = $item_gross - $discount;
                                            $item_excise    = ($total_net * $item_excise) / 100;
                                            $item_vat       = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                                            $total = $total_net + $item_excise + $item_vat;

                                            $itemPromotionInfo[] = [
                                                'item_price' => $item_price,
                                                'item_gross' => $item_gross,
                                                'discount' => $discount,
                                                'total_net' => $total_net,
                                                'is_free' => true,
                                                'is_item_poi' => false,
                                                'order_item_type' => $price_disco_promo_plan->order_item_type,
                                                'offer_item_type' => $price_disco_promo_plan->offer_item_type,
                                                'promotion_id' => $item->id,
                                                'total_excise' => $item_excise,
                                                'total_vat' => $item_vat,
                                                'total' => $total,
                                            ];
                                        }
                                    }
                                } else {
                                    if (!empty($request->item_qty[$key])) {
                                        $qty = $request->item_qty[$key];

                                        if ($item->item_qty <= $qty) {
                                            $is_promotion   = true;
                                            $offerItems     = $price_disco_promo_plan_offer->PDPPromotionOfferItems;
                                            $item_price     = $item->price;
                                            $item_qty       = $qty;
                                            $item_gross     = $item_qty * $item_price;
                                            $total_net      = $item_gross - $discount;
                                            $item_excise    = ($total_net * $item_excise) / 100;
                                            $item_vat       = (($total_net + $item_excise) * $item_vat_percentage) / 100;

                                            $total = $total_net + $item_excise + $item_vat;

                                            $itemPromotionInfo[] = [
                                                'item_price' => $item_price,
                                                'item_gross' => $item_gross,
                                                'discount' => $discount,
                                                'total_net' => $total_net,
                                                'is_free' => true,
                                                'is_item_poi' => false,
                                                'order_item_type' => $price_disco_promo_plan->order_item_type,
                                                'offer_item_type' => $price_disco_promo_plan->offer_item_type,
                                                'promotion_id' => $item->id,
                                                'total_excise' => $item_excise,
                                                'total_vat' => $item_vat,
                                                'total' => $total,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (is_array($offerItems) && sizeof($offerItems) > 1) {
                        $offerItems = $offerItems->pluck('item')->toArray();
                    }
                }
            }

            $offerData = array('offer_items' => $offerItems, 'itemPromotionInfo' => $itemPromotionInfo);

            DB::commit();
            return prepareResult(true, $offerData, [], "Item price.", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }
    }

    /*public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id = $work_flow_rule_id;
        $createObj->module_name = $module_name;
        $createObj->raw_id = $obj->id;
        $createObj->request_object = $request->all();
        $createObj->save();

        $wfrau = WorkFlowRuleApprovalUser::where('work_flow_rule_id', $work_flow_rule_id)->first();

        $data = array(
            'uuid' => (is_object($obj)) ? $obj->uuid : 0,
            'user_id' => $wfrau->user_id,
            'type' => $module_name,
            'message' => "Approve the New " . $module_name,
            'status' => 1,
        );
        saveNotificaiton($data);
    }*/
    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {

        if (isset($obj->id)) {
            $module_path = 'App\\Model\\' . $module_name;
            $module = $module_path::where('id', $obj->id)
                ->where('organisation_id', 1)
                ->where('approval_status', 'Updated')
                ->first();
            if ($module) {
                WorkFlowObjectPrd::where('raw_id', $obj->id)->delete();
            }
        }

        $createObj = new WorkFlowObjectPrd;
        $createObj->organisation_id   = 1;
        $createObj->work_flow_rule_id   = $work_flow_rule_id;
        $createObj->module_name         = 'Order';
        $createObj->raw_id              = $obj->id;
        $createObj->request_object      = $obj;
        $createObj->save();

        //$wfrau = WorkFlowRuleApprovalUser::where('work_flow_rule_id', $work_flow_rule_id)->first();

        /* $data = array(
            'uuid' => (is_object($obj)) ? $obj->uuid : 0,
            'user_id' => $wfrau->user_id,
            'type' => $module_name,
            'message' => "Approve the New " . $module_name,
            'status' => 1,
        );
        saveNotificaiton($data);*/
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'order_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate order import", $this->unauthorized);
        }

        Excel::import(new OrderImport, request()->file('order_file'));
        return prepareResult(true, [], [], "Order successfully imported", $this->success);
    }

    /**
     * This funciton is use for the cancel the order and put it the reason
     *
     * @param Request $request
     * @param mixed $reason_id
     * @param mixed $order_id
     * @return void
     * Hardik Solanki - 24-05
     */
    public function cancel(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "cancel");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating order cancel", $this->unprocessableEntity);
        }

        $order = Order::find($request->order_id);
        if ($order) {
            $order->reason_id = $request->reason_id;
            $order->approval_status = "Cancelled";
            $order->save();

            return prepareResult(true, $order, [], "Order cancelled", $this->success);
        }
    }

    /**
     * This function is generate the delivery on order id
     * @param array $order_id
     */

    public function orderToPicking(Request $request)
    {


        if (!is_array($request->order_ids)) {
            return prepareResult(false, [], ["error" => "Order id is not added."], "Order id is not added.", $this->unauthorized);
        }

        $orders = Order::whereIn('id', $request->order_ids)
            ->where('current_stage', "Approved")
            ->whereIn('approval_status', ["Created", "Updated", "Partial-Delivered", "Delivered"])
            ->get();

        if (count($orders)) {
            foreach ($orders as $order) {
                $variable = "delivery";
                $nextComingNumber['number_is'] = null;
                $nextComingNumber['prefix_is'] = null;
                if (CodeSettingPrd::count() > 0) {
                    $code_setting = CodeSettingPrd::first();
                    if ($code_setting['is_final_update_' . $variable] == 1) {
                        $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                        $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
                    } else {
                        $code_setting['is_code_auto_' . $variable]     = "1";
                        $code_setting['prefix_code_' . $variable]      = "DELV0";
                        $code_setting['start_code_' . $variable]       = "00001";
                        $code_setting['next_coming_number_' . $variable] = "DELV000001";
                        $code_setting['is_final_update_' . $variable]  = "1";
                        $code_setting->save();

                        $nextComingNumber = "DELV000001";
                    }
                }

                $code = $nextComingNumber;

                DB::beginTransaction();
                try {
                    $status = 1;
                    $current_stage = 'Approved';
                    $current_organisation_id = 1;
                    if ($isActivate = checkWorkFlowRule2('Deliviery', 'create', $current_organisation_id)) {
                        $status = 0;
                        $current_stage = 'Pending';
                        //$this->createWorkFlowObject($isActivate, 'Deliviery);
                    }

                    $delivery = new Delivery();
                    $delivery->delivery_number          = nextComingNumber('App\Model\Delivery', 'delivery', 'delivery_number', $code);
                    $delivery->order_id                 = $order->id;
                    $delivery->customer_id              = $order->customer_id;
                    $delivery->salesman_id              = null;
                    $delivery->reason_id                = null;
                    $delivery->route_id                 = null;
                    $delivery->storage_location_id      = (!empty($order->storage_location_id)) ? $order->storage_location_id : null;
                    $delivery->warehouse_id             = (!empty($request->warehouse_id)) ? $request->warehouse_id : 0;
                    $delivery->delivery_type            = $order->order_type_id;
                    $delivery->delivery_type_source     = 2;
                    $delivery->delivery_date            = $order->delivery_date;
                    $delivery->delivery_time            = (isset($order->delivery_time)) ? $order->delivery_time : date('H:m:s');
                    $delivery->delivery_weight          = $order->delivery_weight;
                    $delivery->payment_term_id          = $order->payment_term_id;
                    $delivery->total_qty                = $order->total_qty;
                    $delivery->total_gross              = $order->total_gross;
                    $delivery->total_discount_amount    = $order->total_discount_amount;
                    $delivery->total_net                = $order->total_net;
                    $delivery->total_vat                = $order->total_vat;
                    $delivery->total_excise             = $order->total_excise;
                    $delivery->grand_total              = $order->grand_total;
                    $delivery->current_stage_comment    = $order->current_stage_comment;
                    $delivery->delivery_due_date        = $order->due_date;
                    $delivery->source                   = $order->source;
                    $delivery->status                   = $status;
                    $delivery->current_stage            = $current_stage;
                    $delivery->approval_status          = "Created";
                    $delivery->lob_id                   = (!empty($order->lob_id)) ? $order->lob_id : null;
                    $delivery->save();

                    if (count($order->orderDetailsWithoutDelete)) {
                        foreach ($order->orderDetailsWithoutDelete as $od) {
                            //save DeliveryDetail

                            $deliveryDetail = new DeliveryDetail();
                            $deliveryDetail->id                     = $od->id;
                            $deliveryDetail->delivery_id            = $delivery->id;
                            $deliveryDetail->salesman_id            = null;
                            $deliveryDetail->item_id                = $od->item_id;
                            $deliveryDetail->item_uom_id            = $od->item_uom_id;
                            $deliveryDetail->original_item_uom_id   = $od->item_uom_id;
                            $deliveryDetail->discount_id            = $od->discount_id;
                            $deliveryDetail->is_free                = $od->is_free;
                            $deliveryDetail->is_item_poi            = $od->is_item_poi;
                            $deliveryDetail->promotion_id           = $od->promotion_id;
                            $deliveryDetail->reason_id              = null;
                            $deliveryDetail->is_deleted             = 0;
                            $deliveryDetail->item_qty               = $od->item_qty;
                            $deliveryDetail->original_item_qty      = $od->item_qty;
                            $deliveryDetail->open_qty               = $od->item_qty;
                            $deliveryDetail->item_price             = $od->item_price;
                            $deliveryDetail->item_gross             = $od->item_gross;
                            $deliveryDetail->item_discount_amount   = $od->item_discount_amount;
                            $deliveryDetail->item_net               = $od->item_net;
                            $deliveryDetail->item_vat               = $od->item_vat;
                            $deliveryDetail->item_excise            = $od->item_excise;
                            $deliveryDetail->item_grand_total       = $od->item_grand_total;
                            $deliveryDetail->batch_number           = $od->batch_number;
                            $deliveryDetail->save();
                        }
                    }

                    if ($isActivate = checkWorkFlowRule2('Delivery', 'create', $current_organisation_id)) {
                        $this->createWorkFlowObject($isActivate, 'Delivery', $order, $delivery);
                    }

                    DB::commit();

                    $order->sync_status     = NULL;
                    $order->approval_status = "Picking Confirmed";
                    $order->save();

                    updateNextComingNumber('App\Model\Delivery', 'delivery');

                    // return prepareResult(true, $delivery, [], "Delivery added successfully.", $this->success);
                } catch (\Exception $exception) {
                    DB::rollback();
                    $order->sync_status = $exception;
                    $order->save();
                    // return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                } catch (\Throwable $exception) {
                    DB::rollback();
                    $order->sync_status = $exception;
                    $order->save();
                    // return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
                }
            }
        }

        return prepareResult(true, [], [], "Delivery created successfully.", $this->success);
    }
	
	////////
    public function getMonth($start, $end) {
        $month = [];
        for ($i = 0; $i < $end; ++$i) {
            array_push($month, ++$start);
        }
        return $month;
    }
    public function getDate($days, $month_array, $month, $year) {
        $day = array_search($days, $month_array) + 1;
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        return ($month >= 10) ? $year.'-'.$month.'-'.$day : $year.'-0'.$month.'-'.$day;
    }
    public function jdedateConvert($julian_date) {
        if (strlen($julian_date) == 5) {
            $julian_date = "1" . $julian_date;
        }
        $array = str_split($julian_date);
        $century = $array[0];
        $century = 19 + $century;
        $year = $century.$array[1].$array[2];
        $year = (int) $year;
        $days = $array[3].$array[4].$array[5];
        $days = (int) $days;

        // is leap year ?
        $plus_one = ($year % 4) || (($year % 100 === 0) && ($year % 400)) ? 0 : 1;

        $jan_month = $this->getMonth(0, 31);
        $feb_month = $this->getMonth(31 + $plus_one, 28);
        $mars_month = $this->getMonth(59 + $plus_one, 31);
        $april_month = $this->getMonth(90 + $plus_one, 30);
        $may_month = $this->getMonth(120 + $plus_one, 31);
        $june_month = $this->getMonth(151 + $plus_one, 30);
        $july_month = $this->getMonth(181 + $plus_one, 31);
        $august_month = $this->getMonth(212 + $plus_one, 31);
        $sept_month = $this->getMonth(243 + $plus_one, 30);
        $oct_month = $this->getMonth(273 + $plus_one, 31);
        $nov_month = $this->getMonth(304 + $plus_one, 30);
        $dec_month = $this->getMonth(334 + $plus_one, 31);

        switch ($days) {
            // Jan
            case in_array($days, $jan_month):
            return $this->getDate($days, $jan_month, 1, $year);
            break;

            // Feb
            case in_array($days, $feb_month):
            return $this->getDate($days, $feb_month, 2, $year);
            break;

            // Mars
            case in_array($days, $mars_month):
            return $this->getDate($days, $mars_month, 3, $year);
            break;

            // April
            case in_array($days, $april_month):
            return $this->getDate($days, $april_month, 4, $year);
            break;

            // May
            case in_array($days, $may_month):
            return $this->getDate($days, $may_month, 5, $year);
            break;

            // June
            case in_array($days, $june_month):
            return $this->getDate($days, $june_month, 6, $year);
            break;

            // July
            case in_array($days, $july_month):
            return $this->getDate($days, $may_month, 7, $year);
            break;

            // August
            case in_array($days, $august_month):
            return $this->getDate($days, $may_month, 8, $year);
            break;

            // September
            case in_array($days, $sept_month):
            return $this->getDate($days, $sept_month, 9, $year);
            break;

            // October
            case in_array($days, $oct_month):
            return $this->getDate($days, $oct_month, 10, $year);
            break;

            // November
            case in_array($days, $nov_month):
            return $this->getDate($days, $nov_month, 11, $year);
            break;

            // December
            case in_array($days, $dec_month):
            return $this->getDate($days, $dec_month, 12, $year);
            break;

            default:
            return date('Y-m-d', strtotime("+30 days")); // default date (MySQL date)
            break;
        }
    }
    //
function convertJDEJulianToDate($jdeJulianDate)
    {
        $julianDate = $jdeJulianDate + 1900000;
		$julianDate = $julianDate -1;
         $year = (int)substr($julianDate, 0, 4);
         $dayOfYear = (int)substr($julianDate, 4);

        $date = DateTime::createFromFormat('Yz', $year . $dayOfYear);
        return $date->format('Y-m-d');
    }


}
