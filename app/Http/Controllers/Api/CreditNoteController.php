<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\CreditNote;
use App\Model\CreditNoteDetail;
use App\Model\Invoice;
use App\Model\InvoiceDetail;
use App\Model\WorkFlowObject;
use App\Model\CodeSetting;
use App\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CreditnoteImport;
use App\Model\CreditNoteNote;
use App\Model\CustomerInfo;
use App\Model\DeviceDetail;
use App\Model\ImportTempFile;
use App\Model\Item;
use App\Model\ItemUom;
use App\Model\Notifications;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\Transaction;
use Carbon\Carbon;
use App\Model\Storagelocation;
use App\Model\StoragelocationDetail;
use App\Model\Van;
use App\Model\WorkFlowRuleApprovalUser;
use File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
use GuzzleHttp\Client;

class CreditNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $creditnotes_query = CreditNote::with(array('customer' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerinfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'driver:id,firstname,lastname',
                'driver.salesmaninfo:id,user_id,salesman_code',
                'salesman.salesmaninfo:id,user_id,salesman_code',
                'supervisor:id,firstname,lastname',
                'invoice',
                'creditNoteDetails',
                'creditNoteDetails.item:id,item_name,item_code',
                'creditNoteDetails.itemUom:id,name,code',
                'lob',
                'route:id,route_name,route_code',
                'storageocation',
                'warehouse'
            );
        //->where('order_date', date('Y-m-d'))

        if ($request->date) {
            $creditnotes_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->credit_note_number) {
            $creditnotes_query->where('credit_note_number', 'like', '%' . $request->credit_note_number . '%');
        }

        if ($request->current_stage) {
            $creditnotes_query->where('current_stage', 'like', '%' . $request->current_stage . '%');
        }

        if ($request->branch_plant_code) {
            $branch_plant_code = $request->branch_plant_code;
            $creditnotes_query->whereHas('storageocation', function ($q) use ($branch_plant_code) {
                $q->where('code', 'like', '%' . $branch_plant_code . '%');
            });
        }

        if ($request->customer_reference_number) {
            $creditnotes_query->where('customer_reference_number', $request->customer_reference_number);
        }

        if ($request->approval_status) {
            $creditnotes_query->where('approval_status', $request->approval_status);
        }

        if ($request->picking_date) {
            $creditnotes_query->where('picking_date', $request->picking_date);
        }

        if ($request->wh_approve_date) {
            $creditnotes_query->where('wh_approve_date', $request->wh_approve_date);
        }

        if ($request->unload_date) {
            $creditnotes_query->where('unload_date', $request->unload_date);
        }

        if ($request->approval_date) {
            $creditnotes_query->whereDate('approval_date', date('Y-m-d', strtotime($request->approval_date)));
        }

        if ($request->customer_name) {
            $name = $request->customer_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $creditnotes_query->whereHas('customer', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $creditnotes_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $customer_code = $request->customer_code;
            $creditnotes_query->whereHas('customerInfo', function ($q) use ($customer_code) {
                $q->where('customer_code', 'like', '%' . $customer_code . '%');
            });
        }

        if ($request->route_code) {
            $route_code = $request->route_code;
            $creditnotes_query->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', '%' . $route_code . '%');
            });
        }

        if ($request->route_name) {
            $route_name = $request->route_name;
            $creditnotes_query->whereHas('route', function ($q) use ($route_name) {
                $q->where('route_name', 'like', '%' . $route_name . '%');
            });
        }

        if ($request->supervisor_id) {
            $name = $request->supervisor_id;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $creditnotes_query->whereHas('supervisor', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $creditnotes_query->whereHas('supervisor', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->driver_name) {
            $name = $request->driver_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->merchandiser_name) {
            $name = $request->merchandiser_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->driver_code) {
            $salesman_code = $request->driver_code;
            $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', '%' . $salesman_code . '%');
            });
        }

        if ($request->merchandiser_code) {
            $merchandiser_code = $request->merchandiser_code;
            $creditnotes_query->whereHas('salesman.salesmanInfo', function ($q) use ($merchandiser_code) {
                $q->where('salesman_code', 'like', '%' . $merchandiser_code . '%');
            });
        }

        if ($request->erp_status) {

            if ($request->erp_status == "Not Posted") {
                $creditnotes_query->whereNotNull('erp_failed_response')
                    ->where(function ($query) {
                        $query->where('erp_id', 0)
                            ->orWhereNull('erp_id');
                    });
            }

            if ($request->erp_status == "Failed") {
                $creditnotes_query->whereNotNull('erp_id')
                    ->whereNotNull('erp_failed_response');
            }

            if ($request->erp_status == "Posted") {
                $creditnotes_query->whereNotNull('erp_id')
                    ->whereNull('erp_failed_response');
            }
        }

        if (in_array($request->user()->role_id, [6, 7, 9]) && config('app.current_domain') != "presales") {
            if ((int) $request->user()->role_id === 9) {
                $creditnotes_query->where('supervisor_id', $request->user()->id);
            } else {
                $creditnotes_query->whereIn('supervisor_id', $this->findSupervisor($request->user()->role_id));
            }
        }else{
            if ((int) $request->user()->role_id === 16) {
                $creditnotes_query->where('created_user_id', $request->user()->id);
            }else if ((int) $request->user()->role_id === 17) {
                $creditnotes_query->where('created_user_id', "42353");
            }
        }

        $all_creditnotes = $creditnotes_query->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $creditnotes = $all_creditnotes->items();

        $pagination = array();
        $pagination['total_pages'] = $all_creditnotes->lastPage();
        $pagination['current_page'] = (int)$all_creditnotes->perPage();
        $pagination['total_records'] = $all_creditnotes->total();

        // $creditnotes = $creditnotes_query->orderBy('id', 'desc')->get();

        $results = GetWorkFlowRuleObject('Credit Note', $all_creditnotes->pluck('id')->toArray());

        $approve_need_creditnotes = array();
        $approve_need_creditnotes_detail_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_creditnotes[] = $raw['object']->raw_id;
                $approve_need_creditnotes_detail_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $creditnotes_array = array();
        if (count($creditnotes)) {
            foreach ($creditnotes as $key => $creditnotes1) {
                if (in_array($creditnotes[$key]->id, $approve_need_creditnotes)) {
                    $creditnotes[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_creditnotes_detail_object_id[$creditnotes[$key]->id])) {
                        $creditnotes[$key]->objectid = $approve_need_creditnotes_detail_object_id[$creditnotes[$key]->id];
                    } else {
                        $creditnotes[$key]->objectid = '';
                    }
                } else {
                    $creditnotes[$key]->need_to_approve = 'no';
                    $creditnotes[$key]->objectid = '';
                }

                // if (
                //     $creditnotes[$key]->current_stage == 'Approved' ||
                //     request()->user()->usertype == 1 ||
                //     $creditnotes[$key]->created_user_id == request()->user()->id ||
                //     in_array($creditnotes[$key]->id, $approve_need_creditnotes)
                // ) {
                //     $creditnotes_array[] = $creditnotes[$key];
                // }

                if (
                    $creditnotes[$key]->current_stage == 'Approved' ||
                    $creditnotes[$key]->current_stage == 'Pending' ||
                    request()->user()->usertype == 1 ||
                    $creditnotes[$key]->created_user_id == request()->user()->id ||
                    in_array($creditnotes[$key]->id, $approve_need_creditnotes)
                ) {
                    $creditnotes_array[] = $creditnotes[$key];
                }
            }
        }

        return prepareResult(true, $creditnotes_array, [], "Credit notes listing", $this->success, $pagination);
    }


    public function return_document_sap()
    {
            $curl = curl_init();                           // Cert Password
            curl_setopt($curl, CURLOPT_URL, 'my_addr');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            $pem=realpath("cert_dev__cert_out.pem");
          // dd($pem);
            if(!$pem || !is_readable($pem)){
                die("error: myfile.pem is not readable! realpath: \"{$pem}\" - working dir: \"".getcwd()."\" effective user: ".print_r(posix_getpwuid(posix_geteuid()),true));
            }
           
            curl_setopt($curl, CURLOPT_SSLCERT, $pem);
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://albathadev.it-cpi001-rt.cfapps.eu10.hana.ondemand.com/http/mobiato/returnorder',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'
            {

                "RETURNDATA": [

                    {

                        "DOCTYPE": "ZRGI",

                        "SOLDTOPARTY": "0000011132",

                        "CUSTOMERLPO": "MOB_TEST2",

                        "MATERIAL": "GI22048022",

                        "QUANTITY": "4",

                        "UOM": "EA",

                        "CURRENCY": "",

                        "PRICE": "1",

                        "USAGE": "51"

                    },

                    {

                        "DOCTYPE": "ZRGI",

                        "SOLDTOPARTY": "0000011132",

                        "CUSTOMERLPO": "MOB_TEST2",

                        "MATERIAL": "GI22048020",

                        "QUANTITY": "5",

                        "UOM": "CAS",

                        "CURRENCY": "",

                        "PRICE": "2",

                        "USAGE": "52"

                    }

                ]

            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_SSLCERTTYPE       => 'PEM'
            ));

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                dd($error_msg);
            }
            curl_close($curl);
            echo $response;
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @param int $order_type_id = 1  and 2
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating credit note", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Please add atleast one items."], "Please add atleast one items.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Credit Note', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Credit Note',$request);
            }

            if (!empty($request->route_id)) {
                $route_id = $request->route_id;
            } elseif (!empty($request->salesman_id)) {
                $route_id = getRouteBySalesman($request->salesman_id);
            }

            $creditnote = new CreditNote;
            if ($request->source == 1) {
                $repeat_number = codeCheck('CreditNote', 'credit_note_number', $request->credit_note_number, 'credit_note_date');
                if (is_object($repeat_number)) {
                    return prepareResult(true, $repeat_number, [], 'Record saved', $this->success);
                } else {
                    $repeat_number = codeCheck('CreditNote', 'credit_note_number', $request->credit_note_number);
                    if (is_object($repeat_number)) {
                        return prepareResult(false, [], ["error" => "This Credit Note Number " . $request->credit_note_number . " is already added."], "This Order Number is already added.", $this->unprocessableEntity);
                    }
                }

                $variable = "credit_note";
                $nextComingNumber['number_is'] = null;
                $nextComingNumber['prefix_is'] = null;
                if (CodeSetting::count() > 0) {
                    $code_setting = CodeSetting::first();
                    if ($code_setting['is_final_update_' . $variable] == 1) {
                        $nextComingNumber['number_is'] = $code_setting['next_coming_number_' . $variable];
                        $nextComingNumber['prefix_is'] = $code_setting['prefix_code_' . $variable];
                    }
                }

                if (isset($nextComingNumber['number_is'])) {
                    $credit_note_number = $nextComingNumber['number_is'];
                } else {
                    $credit_note_number = "10140001";
                }

                $creditnote->credit_note_number = nextComingNumber('App\Model\CreditNote', 'credit_note', 'credit_note_number', $credit_note_number);
                $creditnote->credit_note_mobile_number = $request->credit_note_number;
            } else {
                $creditnote->credit_note_number = nextComingNumber('App\Model\CreditNote', 'credit_note', 'credit_note_number', $request->credit_note_number);
            }

            $creditnote->customer_id            = (!empty($request->customer_id)) ? $request->customer_id : null;
            $creditnote->invoice_id             = (!empty($request->invoice_id)) ? $request->invoice_id : null;
            $creditnote->salesman_id            = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $creditnote->delivery_driver_id     = (!empty($request->delivery_driver_id)) ? $request->delivery_driver_id : null;
            $creditnote->credit_note_date       = date('Y-m-d', strtotime($request->credit_note_date));
            $creditnote->trip_id                = $request->trip_id;
            $creditnote->merchandiser_status    = null;
            $creditnote->delivery_driver_status = null;
            $creditnote->payment_term_id        = $request->payment_term_id;
            $creditnote->storage_location_id    = $request->storage_location_id;
            $creditnote->warehouse_id           = getWarehuseBasedOnStorageLoacation($request->storage_location_id, false);
            $creditnote->route_id               = (!empty($route_id)) ? $route_id : null;
            $creditnote->customer_reference_number = $request->customer_reference_number;
            $creditnote->total_qty              = $request->total_qty;
            $creditnote->total_gross            = $request->total_gross;
            $creditnote->total_discount_amount  = $request->total_discount_amount;
            $creditnote->total_net              = $request->total_net;
            $creditnote->total_vat              = $request->total_vat;
            $creditnote->total_excise           = $request->total_excise;
            $creditnote->grand_total            = $request->grand_total;
            $creditnote->order_type_id          = (!empty($request->order_type_id)) ? $request->order_type_id : 2;
            $creditnote->current_stage          = $current_stage;
            $creditnote->return_type            = (!empty($request->return_type)) ? $request->return_type : "badReturn";
            $creditnote->source                 = $request->source;
            $creditnote->reason                 = $request->reason;
            $creditnote->status                 = $status;
            $creditnote->approval_status        = (isset($request->is_requested)) ? "Requested" : "Created";
            $creditnote->picking_date           = (isset($request->picking_date)) ? $request->picking_date : NULL;
            $creditnote->approval_date          = ($request->approval_date) ? $request->approval_date : NULL;
            $creditnote->lob_id                 = (!empty($request->lob_id)) ? $request->lob_id : null;
            $creditnote->is_exchange            = (isset($request->is_exchange)) ? 1 : 0;
            $creditnote->exchange_number        = (isset($request->exchange_number)) ? $request->exchange_number : null;
            $creditnote->customer_amount        = (isset($request->customer_amount)) ? $request->customer_amount : 0;

            if ($request->is_exchange == 1) {
                $creditnote->pending_credit     = 0;
            } else {
                if (isset($request->order_type_id) && $request->order_type_id == 1) {
                    $creditnote->pending_credit     = 0;
                } else {
                    $creditnote->pending_credit     = $request->grand_total;
                }
            }

            if ($request->merchandiser_image_1) {
                $creditnote->merchandiser_image_1 = saveImage($request->credit_note_number . 'merchandiser_image_1', $request->merchandiser_image_1, 'merchandiser_image');
            }

            if ($request->merchandiser_image_2) {
                $creditnote->merchandiser_image_2 = saveImage($request->credit_note_number . 'merchandiser_image_1', $request->merchandiser_image_2, 'merchandiser_image');
            }
            if ($request->merchandiser_image_3) {
                $creditnote->merchandiser_image_3 = saveImage($request->credit_note_number . 'merchandiser_image_3', $request->merchandiser_image_1, 'merchandiser_image');
            }
            if ($request->merchandiser_image_4) {
                $creditnote->merchandiser_image_4 = saveImage($request->credit_note_number . 'merchandiser_image_4', $request->merchandiser_image_4, 'merchandiser_image');
            }
            $creditnote->save();

            if ($isActivate = checkWorkFlowRule('Credit Note', 'create', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Credit Note', $request, $creditnote);
            }

            if (is_array($request->items)) {
                foreach ($request->items as $key => $item) {
                    //-----------
                    $creditnoteDetail = new CreditNoteDetail;
                    $creditnoteDetail->credit_note_id       = $creditnote->id;
                    $creditnoteDetail->salesman_id          = (!empty($request->salesman_id)) ? $request->salesman_id : null;;
                    $creditnoteDetail->item_id              = $item['item_id'];
                    $creditnoteDetail->item_condition       = (isset($item['item_condition'])) ? $item['item_condition'] : 1;
                    $creditnoteDetail->item_uom_id          = $item['item_uom_id'];
                    $creditnoteDetail->discount_id          = $item['discount_id'];
                    $creditnoteDetail->is_free              = $item['is_free'];
                    $creditnoteDetail->is_item_poi          = $item['is_item_poi'];
                    $creditnoteDetail->promotion_id         = $item['promotion_id'];
                    $creditnoteDetail->item_qty             = $item['item_qty'];
                    $creditnoteDetail->original_item_qty    = $item['item_qty'];
                    $creditnoteDetail->item_price           = $item['item_price'];
                    $creditnoteDetail->item_gross           = $item['item_gross'];
                    $creditnoteDetail->item_discount_amount = $item['item_discount_amount'];
                    $creditnoteDetail->item_net             = $item['item_net'];
                    $creditnoteDetail->item_vat             = $item['item_vat'];
                    $creditnoteDetail->item_excise          = $item['item_excise'];
                    $creditnoteDetail->item_grand_total     = $item['item_grand_total'];
                    $creditnoteDetail->batch_number         = $item['batch_number'];
                    $creditnoteDetail->reason               = $item['reason'];
                    // $creditnoteDetail->open_qty             = $item['item_qty'];
                    $creditnoteDetail->item_expiry_date     = (!empty($item['item_expiry_date'])) ? date('Y-m-d', strtotime($item['item_expiry_date'])) : null;

                    if ($request->source == 1) {
                        if (isset($item['invoice_number'])) {
                            $invoice = Invoice::where('invoice_number', $item['invoice_number'])->first();
                            $creditnoteDetail->invoice_number   = (!empty($item['invoice_number'])) ? $item['invoice_number'] : 0;
                            $creditnoteDetail->invoice_id       = (!empty($invoice)) ? $invoice->id : null;
                        }
                    } else {
                        $creditnoteDetail->invoice_id = (!empty($item['invoice_number'])) ? $item['invoice_number'] : null;
                    }
                    $creditnoteDetail->invoice_total = (!empty($item['invoice_total'])) ? $item['invoice_total'] : null;
                    $creditnoteDetail->save();
                }
            }

            if (is_object($creditnote) && $creditnote->source == 1) {
                $user = User::find($request->user()->id);
                if (is_object($user)) {
                    $salesmanInfo = $user->salesmanInfo;
                    if ($salesmanInfo) {
                        updateMobileNumberRange($salesmanInfo, 'credit_note_from', $creditnote->credit_note_mobile_number);
                    }
                }

                if ($creditnote->grand_total >= 500) {
                    $this->salesmanToApprovalNotificaiton($creditnote);
                } else {
                    $this->salesmanToSupervisorApprovalNotificaiton($creditnote);
                }
            }

            create_action_history("CreditNote", $creditnote->id, auth()->user()->id, "create", "Credit Note created by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            // storesap
            $this->storeData($creditnote->id);
            DB::commit();

            // change on 22-12-2022 for auto generate number
            // if ($request->source != 1) {
            // }
            updateNextComingNumber('App\Model\CreditNote', 'credit_note');

            return prepareResult(true, $creditnote, [], "Credit note added successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    public function salesmanToApprovalNotificaiton($credit_note)
    {
        $salesmanInfo = SalesmanInfo::where('user_id', request()->user()->id)
            ->first();

        $salesman = $salesmanInfo->user->getName();

        $message = "The $salesman is requesting for the return.";

        $user_notification = User::find(1623);

        $dataNofi = array(
            'uuid'          => $credit_note->uuid,
            'user_id'       => $user_notification->id,
            'type'          => "Return",
            'other'         => $credit_note->salesman_id,
            'message'       => $message,
            'status'        => 1,
            'title'         => "Return From Supervisor",
            'noti_type'     => "Return Approval",
            'reason'        => $credit_note->reason,
            'customer_id'   => $credit_note->customer_id
        );

        $device_detail = DeviceDetail::where('user_id', $user_notification->id)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        saveNotificaiton($dataNofi);
    }

    /*
    *   sending Notification supervisor
    */
    public function salesmanToSupervisorApprovalNotificaiton($credit_note)
    {
        $salesmanInfo = SalesmanInfo::with('salesmanSupervisor')
            ->where('user_id', request()->user()->id)
            ->first();

        if (!is_object($salesmanInfo)) {
            return true;
        }

        if (!$salesmanInfo && !is_object($salesmanInfo->salesmanSupervisor)) {
            return true;
        }

        $salesman_supervisor = $salesmanInfo->salesmanSupervisor->firstname . ' ' . $salesmanInfo->salesmanSupervisor->lastname;

        $message = "The return requested has been Approve by $salesman_supervisor.";

        $dataNofi = array(
            'uuid'          => $credit_note->uuid,
            'user_id'       => $credit_note->salesmaninfo->salesman_supervisor,
            'type'          => "Return",
            'other'         => $credit_note->salesman_id,
            'message'       => $message,
            'status'        => 1,
            'title'         => "Return From Supervisor",
            'noti_type'     => "Return Approval",
            'reason'        => $credit_note->reason,
            'customer_id'   => $credit_note->customer_id
        );

        $device_detail = DeviceDetail::where('user_id', $credit_note->salesmaninfo->salesman_supervisor)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        saveNotificaiton($dataNofi);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], ['error' => "Error while validating credit notes."], "Error while validating credit notes.", $this->unprocessableEntity);
        }

        $creditnote = CreditNote::with(array('customer' => function ($query) {
            $query->select('id', 'firstname', 'lastname', DB::raw("CONCAT('firstname','lastname') AS display_name"));
        }))
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerinfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmaninfo:id,user_id,salesman_code',
                'invoice',
                'creditNoteDetails',
                'creditNoteDetails.invoice',
                'creditNoteDetails.item:id,item_name,item_code',
                'creditNoteDetails.itemUom:id,name,code',
                'creditNoteDetails.item.itemMainPrice',
                'creditNoteDetails.item.itemMainPrice.itemUom:id,name',
                'creditNoteDetails.item.itemUomLowerUnit:id,name',
                'lob',
                'route:id,route_name,route_code'
            )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($creditnote)) {
            return prepareResult(false, [], ["error" => "Unvalid Credit note."], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $creditnote, [], "Credit Note Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $uuid
     * @param int $order type = 1
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating credit notes.", $this->unprocessableEntity);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items."], "Error Please add atleast one items.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }


        DB::beginTransaction();
        try {
            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Credit Note', 'edit', $current_organisation_id)) {
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Credit Note',$request);
            }

            if (!empty($request->route_id)) {
                $route_id = $request->route_id;
            } elseif (!empty($request->salesman_id)) {
                $route_id = getRouteBySalesman($request->salesman_id);
            }

            $creditnote = CreditNote::where('uuid', $uuid)->first();
            //Delete old record
            CreditNoteDetail::where('credit_note_id', $creditnote->id)->delete();

            $creditnote->customer_id            = (!empty($request->customer_id)) ? $request->customer_id : null;
            $creditnote->invoice_id             = (!empty($request->invoice_id)) ? $request->invoice_id : null;
            $creditnote->salesman_id            = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $creditnote->delivery_driver_id     = (!empty($request->delivery_driver_id)) ? $request->delivery_driver_id : null;
            $creditnote->credit_note_date       = date('Y-m-d', strtotime($request->credit_note_date));
            $creditnote->payment_term_id        = $request->payment_term_id;
            $creditnote->storage_location_id    = $request->storage_location_id;
            $creditnote->warehouse_id           = $request->warehouse_id;
            $creditnote->route_id               = $route_id;
            $creditnote->return_type            = (!empty($request->return_type)) ? $request->return_type : "badReturn";
            $creditnote->customer_reference_number = $request->customer_reference_number;
            $creditnote->total_qty              = $request->total_qty;
            $creditnote->total_gross            = $request->total_gross;
            $creditnote->total_discount_amount  = $request->total_discount_amount;
            $creditnote->total_net              = $request->total_net;
            $creditnote->total_vat              = $request->total_vat;
            $creditnote->total_excise           = $request->total_excise;
            $creditnote->grand_total            = $request->grand_total;
            $creditnote->customer_amount        = (isset($request->customer_amount)) ? $request->customer_amount : 0;

            if ($request->delivery_driver_image_1) {
                $creditnote->delivery_driver_image_1 = saveImage($creditnote->credit_note_number . 'delivery_driver_image_1', $request->merchandiser_image_1, 'delivery_driver_image');
            }

            if ($request->delivery_driver_image_2) {
                $creditnote->delivery_driver_image_2 = saveImage($creditnote->credit_note_number . 'delivery_driver_image_2', $request->delivery_driver_image_2, 'delivery_driver_image');
            }

            if ($request->delivery_driver_image_3) {
                $creditnote->delivery_driver_image_3 = saveImage($creditnote->credit_note_number . 'delivery_driver_image_3', $request->delivery_driver_image_1, 'delivery_driver_image');
            }

            if ($request->delivery_driver_image_4) {
                $creditnote->delivery_driver_image_4 = saveImage($creditnote->credit_note_number . 'delivery_driver_image_4', $request->delivery_driver_image_4, 'delivery_driver_image');
            }

            if ($request->is_exchange == 1) {
                $creditnote->pending_credit     = 0;
            } else {
                if (isset($request->order_type_id) && $request->order_type_id == 1) {
                    $creditnote->pending_credit     = 0;
                } else {
                    $creditnote->pending_credit     = $request->grand_total;
                }
            }
            $creditnote->order_type_id          = (!empty($request->order_type_id)) ? $request->order_type_id : 2;
            $creditnote->current_stage          = $current_stage;
            $creditnote->source                 = $request->source;
            $creditnote->reason                 = $request->reason;
            $creditnote->status                 = $status;
            $creditnote->approval_status        = ($creditnote->approval_status == "Requested") ? "Requested" : "Updated";
            $creditnote->picking_date           = (isset($request->picking_date)) ? $request->picking_date : NULL;
            $creditnote->approval_date          = (isset($request->approval_date)) ? $request->approval_date : NULL;
            $creditnote->lob_id                 = (!empty($request->lob_id)) ? $request->lob_id : null;
            $creditnote->save();

            if ($isActivate = checkWorkFlowRule('Credit Note', 'edit', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Credit Note', $request, $creditnote);
            }

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    $creditnoteDetail = new CreditNoteDetail;
                    $creditnoteDetail->credit_note_id       = $creditnote->id;
                    $creditnoteDetail->item_id              = $item['item_id'];
                    $creditnoteDetail->item_condition       = (isset($item['item_condition'])) ? $item['item_condition'] : 1;
                    $creditnoteDetail->item_uom_id          = $item['item_uom_id'];
                    $creditnoteDetail->discount_id          = $item['discount_id'];
                    $creditnoteDetail->is_free              = $item['is_free'];
                    $creditnoteDetail->is_item_poi          = $item['is_item_poi'];
                    $creditnoteDetail->promotion_id         = $item['promotion_id'];
                    $creditnoteDetail->item_qty             = $item['item_qty'];
                    $creditnoteDetail->item_price           = $item['item_price'];
                    $creditnoteDetail->item_gross           = $item['item_gross'];
                    $creditnoteDetail->item_discount_amount = $item['item_discount_amount'];
                    $creditnoteDetail->item_net             = $item['item_net'];
                    $creditnoteDetail->item_vat             = $item['item_vat'];
                    $creditnoteDetail->item_excise          = $item['item_excise'];
                    $creditnoteDetail->item_grand_total     = $item['item_grand_total'];
                    $creditnoteDetail->batch_number         = $item['batch_number'];
                    $creditnoteDetail->reason               = $item['reason'];
                    $creditnoteDetail->item_expiry_date     = (!empty($item['item_expiry_date'])) ? date('Y-m-d', strtotime($item['item_expiry_date'])) : null;
                    if ($request->source == 1) {
                        $invoice = Invoice::where('invoice_number', $item['invoice_number'])->first();
                        $creditnoteDetail->invoice_number = (!empty($item['invoice_number'])) ? $item['invoice_number'] : 0;
                        $creditnoteDetail->invoice_id = (!empty($invoice)) ? $invoice->id : null;
                    } else {
                        $creditnoteDetail->invoice_id = (!empty($item['invoice_number'])) ? $item['invoice_number'] : null;
                    }
                    $creditnoteDetail->invoice_total = (!empty($item['invoice_total'])) ? $item['invoice_total'] : null;
                    $creditnoteDetail->save();
                }
            }

            create_action_history("CreditNote", $creditnote->id, auth()->user()->id, "update", "Credit Note updated by " . auth()->user()->firstname . " " . auth()->user()->lastname);

            if ($request->source == 1) {
                $this->delieryDriverToSupervisorApprovalNotificaiton($creditnote);
            }

            DB::commit();

            $creditnote->getSaveData();

            return prepareResult(true, $creditnote, [], "Credit note updated successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ["error" => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /*
    *   sending Notification
    */
    public function delieryDriverToSupervisorApprovalNotificaiton($credit_note)
    {
        $deliveryDriver = SalesmanInfo::with('salesmanSupervisor')
            ->where('user_id', request()->user()->id)
            ->first();

        if (!is_object($deliveryDriver->salesmanSupervisor)) {
            return;
        }

        $customerInfo = $credit_note->customerInfo;
        $c_name = $credit_note->customerInfo->user->getName();
        $name = $deliveryDriver->user->getName();
        $message = "$name has requested to picking item from customer $customerInfo->customer_code - $c_name.";

        $dataNofi = array(
            'uuid'          => $credit_note->uuid,
            'user_id'       => $credit_note->salesman_id,
            'other'         => $credit_note->salesmaninfo->supervisor_id,
            'message'       => $message,
            'status'        => 1,
            'title'         => "Delivery Driver Return From Supervisor",
            'type'          => "Delivery Driver Return",
            'noti_type'     => "Delivery Driver Return",
            'status'        => $credit_note->status,
            'reason'        => $credit_note->reason,
            'customer_id'   => $credit_note->customer_id,
            'lat'           => $credit_note->salesmaninfo->salesman_lat,
            'long'          => $credit_note->salesmaninfo->salesman_long
        );

        $device_detail = DeviceDetail::where('user_id', $credit_note->salesman_id)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        saveNotificaiton($dataNofi);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], ['error' => "Error while validating credit note."], "Error while validating credit note.", $this->unauthorized);
        }

        $creditnote = CreditNote::where('uuid', $uuid)
            ->first();

        if (is_object($creditnote)) {
            $invoiceId = $creditnote->id;
            $creditnote->delete();
            if ($creditnote) {
                CreditNoteDetail::where('credit_note_id', $invoiceId)->delete();
            }
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        } else {
            return prepareResult(true, [], [], "Record not found.", $this->not_found);
        }

        return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param array int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating invoice", $this->unprocessableEntity);
        }

        $action = $request->action;
        $uuids = $request->credit_note_ids;

        if (empty($action)) {
            return prepareResult(false, [], ['errpr' => "Please provide valid action parameter value."], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            foreach ($uuids as $uuid) {
                CreditNote::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0
                ]);
            }
            $creditnote = $this->index();
            return prepareResult(true, $creditnote, [], "Credit note status updated", $this->success);
        } elseif ($action == 'delete') {
            foreach ($uuids as $uuid) {
                $creditnote = CreditNote::where('uuid', $uuid)
                    ->first();
                $creditnoteId = $creditnote->id;
                $creditnote->delete();
                if ($creditnote) {
                    CreditNoteDetail::where('credit_note_id', $creditnoteId)->delete();
                }
            }
            $creditnote = $this->index();
            return prepareResult(true, $creditnote, [], "Credit note deleted success", $this->success);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'customer_id' => 'required|integer|exists:users,id',
                'credit_note_date' => 'required|date',
                // 'payment_term_id' => 'required|integer|exists:payment_terms,id',
                'credit_note_number' => 'required',
                'total_qty' => 'required',
                'total_vat' => 'required',
                'total_net' => 'required',
                'total_excise' => 'required',
                'grand_total' => 'required',

            ]);
        }

        if ($type == 'bulk-action') {
            $validator = Validator::make($input, [
                'action' => 'required',
                'credit_note_ids' => 'required'
            ]);
        }

        if ($type == 'return_reverse') {
            $validator = Validator::make($input, [
                'return_id'         => 'required|integer|exists:credit_notes,id',
                'reason_id'         => 'required|integer|exists:reason_types,id',
                'salesman_id'       => 'required|integer|exists:users,id'
            ]);
        }

        if ($type == 'truck-update') {
            $validator = Validator::make($input, [
                'credit_note_id'    => 'required|integer|exists:credit_notes,id',
                'salesman_id'       => 'required|integer|exists:users,id'
            ]);
        }

        if ($type == "notes") {
            $validator = Validator::make($input, [
                'credit_note_id' => 'required|integer|exists:credit_notes,id',
                'salesman_id' => 'required|integer|exists:users,id',
                'credit_note_number' => 'required'
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function getcustomerinvoice($user_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
        if (!$user_id) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one route id."], "Error while validating customer invoice.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error while validating customer invoice.", $this->unauthorized);
        }
        $invoices = Invoice::with('invoices')
            ->where('customer_id', $user_id)
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $invoices, [], "Invoices listing", $this->success);
    }

    public function getinvoiceitem($invoice_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
        if (!$invoice_id) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one invoice id."], "Error while validating customer invoice.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error while validating invoice item.", $this->unauthorized);
        }
        $invoicedetail = InvoiceDetail::with(
            'item:id,item_name,item_code,lower_unit_uom_id,lower_unit_item_upc,lower_unit_item_price',
            'item.itemMainPrice',
            'itemUom:id,name,code',
            'invoice'
        )
            ->where('invoice_id', $invoice_id)
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $invoicedetail, [], "Invoice detail listing", $this->success);
    }

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id   = $work_flow_rule_id;
        $createObj->module_name         = $module_name;
        $createObj->raw_id              = $obj->id;
        $createObj->request_object      = $request->all();
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
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $mappingarray = array("Credit Note Number", "Customer Name", "Invoice Number", "Credit Note Date", "Route", "Total Gross", "Total Discount Amount", "Total Net", "Total Vat", "Total Excise", "Grand Total", "Pending Credit", "Status", "Item Code", "Item Uom", "Item Qty", "Item Price", "Item Vat", "Item Net", "Item Grand Total");

        return prepareResult(true, $mappingarray, [], "Customer Mapping Field.", $this->success);
    }


    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'creditnote_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate Credit Note import", $this->unauthorized);
        }
        $errors = array();
        try {
            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('creditnote_file')->store('import');
            $filename = storage_path("app/" . $file);
            $fp = fopen($filename, "r");
            $content = fread($fp, filesize($filename));
            $lines = explode("\n", $content);
            $heading_array_line = isset($lines[0]) ? $lines[0] : '';
            $heading_array = explode(",", trim($heading_array_line));
            fclose($fp);

            if (!$heading_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }
            if (!$map_key_value_array) {
                return prepareResult(false, [], [], "Import file and mapping field not match!", $this->success);
            }

            $import = new CreditnoteImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            $succussrecords = 0;
            $successfileids = 0;

            if ($import->successAllRecords()) {
                $succussrecords = count($import->successAllRecords());
                $data = json_encode($import->successAllRecords());
                $fileName = time() . '_datafile.txt';
                File::put(storage_path() . '/app/tempimport/' . $fileName, $data);

                $importtempfiles = new ImportTempFile;
                $importtempfiles->FileName = $fileName;
                $importtempfiles->save();
                $successfileids = $importtempfiles->id;
            }
            $errorrecords = 0;
            $errror_array = array();
            if ($import->failures()) {
                foreach ($import->failures() as $failure_key => $failure) {
                    if ($failure->row() != 1) {
                        $failure->row(); // row that went wrong
                        $failure->attribute(); // either heading key (if using heading row concern) or column index
                        $failure->errors(); // Actual error messages from Laravel validator
                        $failure->values(); // The values of the row that has failed.

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
                            $error_result = array();
                            $error_row_loop = 0;
                            foreach ($map_key_value_array as $map_key_value_array_key => $map_key_value_array_value) {
                                $error_result[$map_key_value_array_value] = isset($failure->values()[$error_row_loop]) ? $failure->values()[$error_row_loop] : '';
                                $error_row_loop++;
                            }
                            $errror_array[] = array(
                                'errormessage' => "There was an error on row " . $failure->row() . ". " . $error_msg,
                                'errorresult' => $error_result, //$failure->values(),
                            );
                        }
                    }
                }
                $errorrecords = count($errror_array);
            }
            $errors = $errror_array;
            $result['successrecordscount'] = $succussrecords;
            $result['errorrcount'] = $errorrecords;
            $result['successfileids'] = $successfileids;
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                if ($failure->row() != 1) {
                    info($failure->row());
                    info($failure->attribute());
                    $failure->row(); // row that went wrong
                    $failure->attribute(); // either heading key (if using heading row concern) or column index
                    $failure->errors(); // Actual error messages from Laravel validator
                    $failure->values(); // The values of the row that has failed.
                    $errors[] = $failure->errors();
                }
            }

            return prepareResult(true, [], $errors, "Failed to validate bank import", $this->success);
        }
        return prepareResult(true, $result, $errors, "Credit Note successfully imported", $this->success);
    }

    public function finalimport(Request $request)
    {
        $importtempfile = ImportTempFile::select('FileName')
            ->where('id', $request->successfileids)
            ->first();

        if ($importtempfile) {
            $data = File::get(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
            $finaldata = json_decode($data);
            if ($finaldata) :
                foreach ($finaldata as $row) :
                    if (isset($row[0]) && $row[0] != 'Credit Note Number') {
                        $status = 1;
                        $current_stage = 'Approved';

                        $creditnote_id = 0;
                        $creditnote_exist = CreditNote::where('credit_note_number', $row[0])->first();

                        $Invoice = null;
                        if (isset($row[2]) && $row[2]) {
                            $Invoice = Invoice::where('invoice_number', $row[2])->first();
                        }

                        if (is_object($creditnote_exist)) {
                            $creditnote_id = $creditnote_exist->id;
                            $creditnote = $creditnote_exist;
                        } else {
                            // $user = User::where('firstname', 'like', '%' . $row[1] . '%')->first();

                            // if (is_object($user)) {
                            //     $CustomerInfo = CustomerInfo::where('user_id', $user->id)->first();
                            //     if (is_object($CustomerInfo)) {
                            //         $customer_id = $CustomerInfo->user_id;
                            //         $payment_term_id = model($CustomerInfo->paymentTerm, 'id');
                            //     } else {
                            //         $customer_id = 0;
                            //         $payment_term_id = 0;
                            //     }
                            // } else {
                            //     $customer_id = 0;
                            //     $payment_term_id = 0;
                            // }

                            $CustomerInfo = CustomerInfo::where('customer_code', $row[1])->first();

                            if (is_object($CustomerInfo)) {
                                $customer_id = $CustomerInfo->user_id;
                                $payment_term_id = model($CustomerInfo->paymentTerm, 'id');
                            } else {
                                $customer_id = 0;
                                $payment_term_id = 0;
                            }

                            $route = Route::where('route_name', 'like', '%' . $row[4] . '%')->first();
                            $salesmanInfo = null;
                            if (is_object($route)) {
                                $salesmanInfo = SalesmanInfo::where('route_id', $route->id)->first();
                            }

                            $creditnote = new CreditNote;
                            $creditnote->customer_id                = $customer_id;
                            $creditnote->salesman_id                = (is_object($salesmanInfo)) ? $salesmanInfo->user_id : null;
                            $creditnote->invoice_id                 = (is_object($Invoice)) ? $Invoice->id : null;
                            $creditnote->route_id                   = (is_object($route)) ? $route->id : null;
                            $creditnote->trip_id                    = null;
                            $creditnote->credit_note_number         = $row[0];
                            $creditnote->credit_note_date           = date('Y-m-d', strtotime($row[3]));
                            // payment_term_id get based on customer
                            $creditnote->payment_term_id            = $payment_term_id;
                            $creditnote->return_type                = "badReturn";
                            if (isset($row[15]) && $row[15] != "") {
                                $creditnote->total_qty              += $row[15];
                            } else {
                                $creditnote->total_qty              = 0;
                            }
                            $creditnote->total_gross                = $row[5];
                            $creditnote->total_discount_amount      = $row[6];
                            $creditnote->total_net                  = $row[7];
                            $creditnote->total_vat                  = $row[8];
                            $creditnote->total_excise               = $row[9];
                            $creditnote->grand_total                = $row[10];
                            $creditnote->pending_credit             = $row[11];
                            $creditnote->reason                     = "";
                            $creditnote->source                     = 3;
                            $creditnote->status                     = ($row[12] == "Yes") ? 1 : 0;
                            $creditnote->is_exchange                = 0;
                            $creditnote->exchange_number            = null;
                            $creditnote->credit_note_comment        = null;
                            $creditnote->current_stage              = "Approved";
                            $creditnote->current_stage_comment      = null;
                            $creditnote->approval_status            = "Created";
                            $creditnote->lob_id                     = null;
                            $creditnote->save();
                            $creditnote_id  = $creditnote->id;
                            $creditnote->oddo_credit_id = $creditnote_id;
                            $creditnote->save();
                        }

                        if ($row[13] != "" && $row[14] != "") {
                            $item = Item::where('item_code', 'like', "%" . $row[13] . '%')->first();
                            $itemUOM = explode('[', $row[14]);

                            if (count($itemUOM) > 1) {
                                $item_uom = ItemUom::where('name', 'like', "%" . $itemUOM . "%")->first();
                            } else {
                                $item_uom = ItemUom::where('name', 'like', "%" . $row[14] . "%")->first();
                            }

                            if (is_object($item) && is_object($item_uom)) {
                                $result = null;
                                if (is_object($item) && is_object($item_uom) && isset($row[15])) {
                                    $result = getItemDetails2($item->id, $item_uom->id, $row[15], true);
                                }

                                $creditnoteDetail = new CreditNoteDetail;
                                $creditnoteDetail->credit_note_id           = $creditnote_id;
                                $creditnoteDetail->item_id                  = (is_object($item)) ? $item->id : 0;
                                $creditnoteDetail->item_condition           = 2;
                                $creditnoteDetail->item_uom_id              = (is_object($item_uom)) ? $item_uom->id : 0;
                                $creditnoteDetail->discount_id              = null;
                                $creditnoteDetail->is_free                  = 0;
                                $creditnoteDetail->is_item_poi              = 0;
                                $creditnoteDetail->promotion_id             = null;
                                $creditnoteDetail->item_qty                 = $row[15];
                                $creditnoteDetail->lower_unit_qty           = (isset($result['Qty'])) ? $result['Qty'] : 0;
                                $creditnoteDetail->item_price               = $row[16];
                                $creditnoteDetail->item_gross               = $row[16];
                                $creditnoteDetail->item_discount_amount     = 0;
                                $creditnoteDetail->item_vat                 = $row[17];
                                $creditnoteDetail->item_net                 = $row[18];
                                $creditnoteDetail->item_excise              = 0;
                                $creditnoteDetail->item_grand_total         = $row[19];
                                $creditnoteDetail->batch_number             = "";
                                $creditnoteDetail->reason                   = "";
                                $creditnoteDetail->item_expiry_date         = "";
                                $creditnoteDetail->invoice_id               = (!empty($Invoice)) ? $Invoice->id : null;
                                $creditnoteDetail->invoice_total            = (!empty($Invoice)) ? $Invoice->grand_total : "0.00";
                                $creditnoteDetail->save();
                            }
                        }
                    }

                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Credit Note successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function import2(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'creditnote_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate delivery import", $this->unauthorized);
        }

        Excel::import(new CreditnoteImport, request()->file('creditnote_file'));
        return prepareResult(true, [], [], "Credit note successfully imported", $this->success);
    }

    /**
     * Display load quenty count listing of the resource. based on the current and route id
     *
     * @return \Illuminate\Http\Response
     */
    public function getLoadquantity(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $ListingFee = Transaction::select('transactions.id', 'transactions.route_id', 'transactions.transaction_date')
            ->with(['loadquantity'])
            ->where('route_id', $request->route_id)
            ->whereDate('transaction_date', Carbon::today())
            ->orderBy('transactions.id', 'desc')
            ->get();

        $ListingFee_array = array();
        if (is_object($ListingFee)) {
            foreach ($ListingFee as $key => $ListingFee1) {
                $ListingFee_array[] = $ListingFee[$key];
            }
        }

        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($ListingFee_array[$offset])) {
                    $data_array[] = $ListingFee_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($ListingFee_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($ListingFee_array);
        } else {
            $data_array = $ListingFee_array;
        }

        return prepareResult(true, $data_array, [], "listing fee details", $this->success, $pagination);
    }

    /**
     *
     *   This function is get the list of which approval status is request
     *   @param Salesman Info user_id
     *   @return Credit Note list
     *
     *   Created By : Hardik Solanki
     *
     */

    public function creditNoteRequeustedList($salesman_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $creditNote = CreditNote::with(
            'creditNoteDetails',
            'storageocation',
            'warehouse',
            'salesman:id,firstname,lastname',
                'driver:id,firstname,lastname',
                'driver.salesmaninfo:id,user_id,salesman_code',
                'salesman.salesmaninfo:id,user_id,salesman_code'
        )
            ->whereHas('creditNoteDetails', function ($q) use ($salesman_id) {
                $q->where('salesman_id', $salesman_id);
            })
            ->where('approval_status', "Truck Allocated")
            // ->where('credit_note_date', date('Y-m-d'))
            ->get();

        if (count($creditNote)) {
            return prepareResult(true, $creditNote, [], "Request Credit Note list", $this->success);
        }
        return prepareResult(false, [], ["error" => "There are not request credit note."], "There are not request credit note.", $this->unprocessableEntity);
        // return prepareResult(true, [], [], "There are not request credit note.", $this->success);
    }


    /**
     *
     *   This function is get the list of which approval status is request
     *   @param Salesman Info user_id
     *   @return Credit Note list
     *
     *   Created By : Hardik Solanki
     *
     */

    public function creditNoteRequeustAccepted($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $creditNote = CreditNote::with('creditNoteDetails')
            ->where('uuid', $uuid)
            ->whereIn('approval_status', ["Requested", "Truck Allocated"])
            ->first();

        if (is_object($creditNote)) {
            $creditNote->approval_status = "Completed";
            $creditNote->save();

            $creditNoteDetails = $creditNote->creditNoteDetails;

            if (count($creditNoteDetails)) {
                $route = Route::where('id', $creditNote->route_id)->first();
                if ($route) {
                    $storageLocation = Storagelocation::where('route_id', $route->id)
                        ->where('loc_type', 2)
                        ->first();

                    if ($storageLocation) {
                        foreach ($creditNoteDetails as $crd) {
                            $conversation = getItemDetails2($crd->item_id, $crd->item_uom_id, $crd->item_qty);

                            $routelocation_detail = StoragelocationDetail::where('storage_location_id', $storageLocation->id)
                                ->where('item_id', $crd->item_id)
                                ->where('item_uom_id', $conversation['UOM'])
                                ->first();

                            if (is_object($routelocation_detail)) {
                                $routelocation_detail->qty = ($routelocation_detail->qty + $conversation['Qty']);
                                $routelocation_detail->save();
                            } else {
                                $routestoragedetail = new StoragelocationDetail;
                                $routestoragedetail->storage_location_id = $storageLocation->id;
                                $routestoragedetail->item_id      = $crd->item_id;
                                $routestoragedetail->item_uom_id  = $conversation['UOM'];
                                $routestoragedetail->qty          = $conversation['Qty'];
                                $routestoragedetail->save();
                            }
                        }
                    }
                }
            }

            // send to JDE
            // $this->postReturnInJDE($creditNote->id);

            return prepareResult(true, $creditNote, [], "Request Accepted", $this->success);
        }
        return prepareResult(false, [], ["error" => "There are not requested credit note."], "There are not request credit note.", $this->unprocessableEntity);
        // return prepareResult(true, [], [], "There are not request credit note.", $this->success);
    }

    /**
     * Credit note import update salesman and date
     *
     * @param Request $request
     * @return list
     */
    public function updateImport(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $validator = \Validator::make($request->all(), [
            'return_update_file' => 'required|mimes:csv,xlsx,xls,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate credit note import", $this->unauthorized);
        }

        $fileName = $_FILES["return_update_file"]["tmp_name"];
        $errors = array();

        if ($_FILES["return_update_file"]["size"] > 0) {

            $file = fopen($fileName, "r");
            $cn = array();
            $item_array = array();
            $credit_note_error = array();
            $customer_code_array = array();
            $credit_note_detail_array = array();
            $salesman_code_array = array();

            while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {

                if (isset($row[0]) && $row[0] != 'Return No') {

                    if ($row[0] == '') {
                        $errors[] = "Return Number is not added.";
                    }

                    if ($row[1] == '') {
                        $errors[] = "Cusotmer is not added.";
                    }

                    if ($row[4] == '') {
                        $errors[] = "Item code is not added.";
                    }

                    if ($row[11] == '') {
                        $errors[] = "Vehicel is not added.";
                    }

                    if ($row[13] == '') {
                        $errors[] = "Delivery Driver is not added.";
                    }


                    if (count($errors) > 0) {
                        return prepareResult(false, [], $errors, "CreditNote not imported", $this->unprocessableEntity);
                    }

                    $credit_note = CreditNote::where('credit_note_number', trim($row[0]))
                        // ->where('approval_status', '!=', 'Truck Allocated')
                        ->first();

                    if (!$credit_note) {
                        if (!in_array($row[0], $credit_note_error)) {
                            if (!in_array($row[0], $credit_note_error)) {
                                $credit_note_error[] = $row[0];
                                $errors[] = "Credit note does not exist " . $row[0];
                            }
                        }
                    }

                    if ($credit_note && $credit_note->approval_status != 'Truck Allocated') {
                        if ($credit_note->approval_status == 'Truck Allocated') {
                            return prepareResult(false, [], [], "Credit note already truck assigned.", $this->unprocessableEntity);
                        }

                        $customerInfo = CustomerInfo::where('customer_code', $row[1])->first();

                        if (!$customerInfo) {
                            if (!in_array($row[1], $customer_code_array)) {
                                $customer_code_array[] = $row[1];
                                $errors[] = "Customer does not match " . $row[1];
                            }
                        }

                        if (is_object($customerInfo) && count($customer_code_array) < 1) {
                            $salemsnaInfo = SalesmanInfo::where('salesman_code', 'like', "%" . $row[13] . "%")
                                ->first();

                            if (!$salemsnaInfo) {
                                if (!in_array($row[13], $salesman_code_array)) {
                                    $salesman_code_array[] = $row[13];
                                    $errors[] = "Salesman does not exist " . $row[13];
                                }
                            }


                            if (is_object($salemsnaInfo)) {
                                $item = Item::where('item_code', $row[4])->first();

                                if (!$item) {
                                    if (!in_array($row[4], $item_array)) {
                                        $item_array[] = $row[4];
                                        $errors[] = "Item does not exit " . $row[4];
                                    }
                                }

                                if (!in_array($credit_note->credit_note_number, $cn)) {
                                    $cn[] = $credit_note->credit_note_number;
                                }

                                if ($item) {

                                    $credit_note_detail = CreditNoteDetail::where('credit_note_id', $credit_note->id)
                                        ->where('item_id', $item->id)
                                        ->first();

                                    if (!$credit_note_detail) {
                                        if (!in_array($credit_note->credit_note_number, $credit_note_detail_array)) {
                                            $credit_note_detail_array[] = $credit_note->credit_note_number;
                                            $errors[] = "Credit note does not exit : " . $credit_note->credit_note_number;
                                        }
                                    }


                                    if ($credit_note_detail) {
                                        $van = Van::where('van_code', 'like', "%$row[11]%")->first();

                                        $credit_note_detail->salesman_id                  = $salemsnaInfo->user_id;
                                        $credit_note_detail->template_credit_note_id      = $credit_note->id;
                                        $credit_note_detail->van_id                       = (!empty($van)) ? $van->id : null;
                                        $credit_note_detail->template_sold_to_outlet_id   = $customerInfo->user_id;
                                        $credit_note_detail->template_item_id             = $item->id;
                                        $credit_note_detail->template_driver_id           = $salemsnaInfo->user_id;
                                        $credit_note_detail->template_credit_note_number  = $credit_note->credit_note_number;
                                        $credit_note_detail->template_sold_to_outlet_code = $customerInfo->customer_code;
                                        $credit_note_detail->template_sold_to_outlet_name = $customerInfo->user->getName();
                                        $credit_note_detail->template_return_request_date = Carbon::parse($credit_note->created_at)->format('Y-m-d');
                                        $credit_note_detail->template_item_name           = $item->item_name;
                                        $credit_note_detail->template_item_code           = $item->item_code;
                                        $credit_note_detail->template_total_value_in_case = $row[6];
                                        $credit_note_detail->template_total_amount        = $row[7];
                                        $credit_note_detail->template_delivery_sequnce    = $row[8];
                                        $credit_note_detail->template_trip                = $row[9];
                                        $credit_note_detail->template_trip_sequnce        = $row[10];
                                        $credit_note_detail->template_vechicle            = $row[11];
                                        $credit_note_detail->template_driver_name         = $row[12];
                                        $credit_note_detail->template_driver_code         = $row[13];
                                        $credit_note_detail->template_is_last_trip        = $row[14];
                                        $credit_note_detail->save();

                                        $this->sendNotificationToDeliveryDriver($credit_note_detail);
                                    }
                                }
                            }
                        }

                        $return_details = CreditNoteDetail::where('credit_note_id', $credit_note->id)
                            ->whereNull('template_sold_to_outlet_code')
                            ->first();

                        if (!is_object($return_details)) {
                            $credit_note->approval_status = "Truck Allocated";
                            $credit_note->save();
                        }
                    }
                }
            }

            if (count($errors)) {
                return prepareResult(false, [], $errors, "Credit note not imported", $this->unprocessableEntity);
            } else {
                return prepareResult(true, [], [], "Credit note successfully imported", $this->success);
            }
        }
    }


    private function sendNotificationToDeliveryDriver($credit_note_detail)
    {
        $nofi = Notifications::where('user_id', $credit_note_detail->salesman_id)
            ->where('sender_id', $credit_note_detail->id)
            ->first();

        if (!$nofi) {
            $customerInfo = CustomerInfo::where('user_id', $credit_note_detail->template_sold_to_outlet_id)->first();

            $message = "You have to tomorrow return " . $credit_note_detail->credit_note_number . " to " . $customerInfo->user->getName() . " - " . $customerInfo->customer_code;

            $dataNofi = array(
                'uuid'          => $credit_note_detail->uuid,
                'user_id'       => $credit_note_detail->salesman_id,
                'type'          => "Return To Customer",
                'other'         => $credit_note_detail->salesman_id,
                'message'       => $message,
                'status'        => 1,
                'title'         => "Return To Cusotmer",
                'type'          => "Return",
                'noti_type'     => "Return",
                'status'        => 1,
                'reason'        => '',
                'customer_id'   => '',
                'lat'           => '',
                'long'          => ''
            );

            $device_detail = DeviceDetail::where('user_id', $credit_note_detail->salesman_id)
                ->orderBy('id', 'desc')
                ->first();

            if (is_object($device_detail)) {
                $t = $device_detail->device_token;
                sendNotificationAndroid($dataNofi, $t);
            }

            saveNotificaiton($dataNofi);
        }
    }

    /**
     * This function is return the credit note detail based on credit note id
     *
     * @param [type] $id
     * @return void
     */
    public function getCreditNoteByID($id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$id) {
            return prepareResult(false, [], ["error" => "Please add credit note id"], "Please add credit note id.", $this->unauthorized);
        }

        $cnd = CreditNoteDetail::with(
            'item:id,item_name,item_code',
            'itemUom:id,name,code',
        )
            ->where('credit_note_id', $id)
            ->get();

        return prepareResult(true, $cnd, [], "Credit note details", $this->success);
    }

    /**
     * This function is use for the supervisor approved to merchandiser request
     *
     * @param [type] $uuid
     * @return void
     */
    /**
     * This function is use for the supervisor approved to merchandiser request
     *
     * @param [type] $uuid
     * @return void
     */
    public function supervisorApprovalNotification(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$request->uuid) {
            return prepareResult(false, [], ['error' => "Error while validating credit notes."], "Error while validating credit notes.", $this->unauthorized);
        }

        $credit_note = CreditNote::where('uuid', $request->uuid)->first();
        if ($credit_note) {
            $salesman = SalesmanInfo::where('user_id', $request->salesman_id)->first();

            if ($salesman->salesman_role_id != 4) {
                $salesman_id = $salesman->user_id;
                $credit_note->merchandiser_status = $request->status;
                $credit_note->save();

                $salesman_supervisor = $salesman->salesmanSupervisor->getName();

                $salesmanSupervisor = $salesman->salesmanSupervisor;

                $message = "The return requested has been $request->status by $salesman_supervisor.";

                $title = "Return From Merchandiser";
            }

            if ($salesman->salesman_role_id == 4) {
                $salesman_id = $salesman->user_id;
                $credit_note->delivery_driver_status = $request->status;
                $credit_note->save();

                $salesmanSupervisor = $salesman->salesmanSupervisor;

                $salesman_supervisor = $salesman->salesmanSupervisor->getName();

                $message = "The return requested has been $request->status by $salesman_supervisor.";

                $title = "Return From Delivery Driver";
            }


            $dataNofi = array(
                'uuid'          => $credit_note->uuid,
                'user_id'       => $salesman_id,
                'other'         => $salesmanSupervisor->id,
                'message'       => $message,
                'status'        => 1,
                'title'         => $title,
                'type'          => "Credit Note",
                'noti_type'     => "Credit Note",
                'status'        => $credit_note->status,
                'reason'        => $credit_note->reason,
                'customer_id'   => $credit_note->customer_id,
                'lat'           => $salesman->salesman_lat,
                'long'          => $salesman->salesman_long
            );

            $device_detail = DeviceDetail::where('user_id', $salesman_id)
                ->orderBy('id', 'desc')
                ->first();

            if (is_object($device_detail)) {
                $t = $device_detail->device_token;
                sendNotificationAndroid($dataNofi, $t);
            }

            saveNotificaiton($dataNofi);

            return prepareResult(true, $credit_note, [], "Request $request->status", $this->success);
        }
    }

    /**
     * This is the credit note revese it means in case driver is not ready for pick up
     * at that time this api call
     *
     * @param Request $request
     * @return void
     */
    public function return_reverse(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "return_reverse");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating credit note", $this->unprocessableEntity);
        }

        $credit_note = CreditNote::where('id', $request->return_id)->first();
        $credit_note->reason_id = $request->reason_id;
        $credit_note->approval_status = 'Created';
        $credit_note->save();

        CreditNoteDetail::where('credit_note_id', $request->return_id)
            ->update([
                'template_credit_note_id'         => null,
                'template_sold_to_outlet_id'      => null,
                'template_item_id'                => null,
                'template_driver_id'              => null,
                'template_credit_note_number'     => null,
                'template_sold_to_outlet_code'    => null,
                'template_sold_to_outlet_name'    => null,
                'template_return_request_date'    => null,
                'template_item_name'              => null,
                'template_item_code'              => null,
                'template_total_value_in_case'    => null,
                'template_total_amount'           => null,
                'template_delivery_sequnce'       => null,
                'template_trip'                   => null,
                'template_trip_sequnce'           => null,
                'template_vechicle'               => null,
                'template_driver_name'            => null,
                'template_driver_code'            => null,
                'template_is_last_trip'           => null,
            ]);

        if ($request->salesman_id) {
            $salesman = SalesmanInfo::where('user_id', $request->salesman_id)->first();

            $salesman_supervisor = $salesman->salesmanSupervisor->getName();
            $salesmanSupervisor = $salesman->salesmanSupervisor;
            $message = "The return requested has been $salesman_supervisor.";
            $title = "Credit Note Reverse";

            $dataNofi = array(
                'uuid'          => $credit_note->uuid,
                'user_id'       => $salesmanSupervisor->id,
                'other'         => $request->salesman_id,
                'message'       => $message,
                'status'        => 1,
                'title'         => $title,
                'type'          => "Credit Note Reverse",
                'noti_type'     => "Credit Note Reverse",
                'reason'        => model($credit_note->reason, 'name'),
                'customer_id'   => $credit_note->customer_id,
                'lat'           => $salesman->salesman_lat,
                'long'          => $salesman->salesman_long
            );

            $device_detail = DeviceDetail::where('user_id', $salesmanSupervisor->id)
                ->orderBy('id', 'desc')
                ->first();

            if (is_object($device_detail)) {
                $t = $device_detail->device_token;
                sendNotificationAndroid($dataNofi, $t);
            }
            saveNotificaiton($dataNofi);

            $data = array(
                'uuid'          => $credit_note->uuid,
                'user_id'       => "56841",
                'other'         => $request->salesman_id,
                'message'       => $message,
                'status'        => 1,
                'title'         => $title,
                'type'          => "Credit Note Reverse",
                'noti_type'     => "Credit Note Reverse",
                'reason'        => model($credit_note->reason, 'name'),
                'customer_id'   => $credit_note->customer_id,
                'lat'           => $salesman->salesman_lat,
                'long'          => $salesman->salesman_long
            );
            // send to SC Users
            saveNotificaiton($data);
        }
        return prepareResult(true, [], [], "Credit Note Reversed Successfully", $this->success);
    }

    public function creditNoteNotes(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "notes");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating credit note", $this->unprocessableEntity);
        }

        if (is_array($request->details)) {

            foreach ($request->details as $detail) {
                $credit_note_notes = new CreditNoteNote();
                $credit_note_notes->credit_note_id         = $request->credit_note_id;
                $credit_note_notes->salesman_id            = $request->salesman_id;
                $credit_note_notes->item_uom_id            = $detail['item_uom_id'];
                $credit_note_notes->item_id                = $detail['item_id'];
                $credit_note_notes->qty                    = $detail['qty'];
                $credit_note_notes->reason_id              = $detail['reason_id'];
                $credit_note_notes->credit_note_number     = $request->credit_note_number;
                $credit_note_notes->save();

                if ($credit_note_notes) {

                    $dd = CreditNoteDetail::where('credit_note_id', $credit_note_notes->credit_note_id)
                        ->where('item_id', $credit_note_notes->item_id)
                        ->where('salesman_id', $credit_note_notes->salesman_id)
                        ->where('item_uom_id', $credit_note_notes->item_uom_id)
                        ->first();

                    if ($dd) {

                        if ($dd->item_qty == $credit_note_notes->qty) {
                            $s = "full";
                        } else {
                            $s = "partial";
                        }

                        $balance_open_qty = 0;
                        $credit_note_status = "";
                        $balance_open_qty = $dd->open_qty - $credit_note_notes->qty;

                        if ($balance_open_qty > 0) {
                            $credit_note_status = "Partial-Returned";
                        } else {
                            $credit_note_status = "Returned";
                        }

                        $dd->credit_note_status     = $credit_note_status;
                        $dd->invoiced_qty           = $credit_note_notes->qty;
                        $dd->open_qty               = $balance_open_qty;
                        $dd->return_status          = $s;
                        $dd->credit_note_notes_id   = $credit_note_notes->id;
                        $dd->save();

                        $d = CreditNote::where('id', $credit_note_notes->credit_note_id)
                            ->first();

                        $credit_note_detail = CreditNoteDetail::where('return_status', 'partial')
                            ->first();
                        if ($credit_note_detail) {
                            $ds = "partial";
                        } else {
                            $ds = "full";
                        }
                        $d->return_status = $ds;
                        $d->save();
                    }
                }
            }
        }
        return prepareResult(true, [], [], "Credit note notes", $this->success);
    }

    public function postReturnInJDE($id)
    {
        if (!$id) {
            return;
        }

        // $response = Curl::to('https://devmobiato.nfpc.net/merchandising/odbc_order_return_posting.php')
        //     ->withData(array('orderid' => $id))
        //     ->returnResponseObject()
        //     ->get();

        return prepareResult(true, [], [], "Credit Note posted in JDE.", $this->success);
    }

    public function updateTruck(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "truck-update");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating credit note", $this->unprocessableEntity);
        }

        $s_info = SalesmanInfo::find($request->salesman_id);

        $creditNote = CreditNote::find($request->credit_note_id);

        if ($creditNote) {

              
            if($creditNote->approval_status != "Truck Allocated")
            {
                $merchandId = $creditNote->salesman_id;
            }else{
                $merchandId = $creditNote->delivery_driver_id;
            }

            $creditNote->update([
                'salesman_id' => ($s_info) ? $s_info->user_id : $request->salesman_id
            ]);

            $cds =  CreditNoteDetail::where('credit_note_id', $creditNote->id)
                ->get();

            foreach ($cds as $key => $cd) {
                $van = Van::find($request->van_id);
                $cd->salesman_id                    = ($s_info) ? $s_info->user_id : $request->salesman_id;
                $cd->template_credit_note_id        = $creditNote->id;
                $cd->van_id                         = $request->van_id;
                $cd->template_sold_to_outlet_id     = $creditNote->customer_id;
                $cd->template_item_id               = $cd->item_id;
                $cd->template_driver_id             = $request->van_id;
                $cd->template_credit_note_number    = $creditNote->credit_note_number;
                $cd->template_sold_to_outlet_code   = model($creditNote->customer, 'id');
                $cd->template_sold_to_outlet_name   = model($creditNote->customerInfo, 'customer_code');
                $cd->template_return_request_date   = Carbon::parse($creditNote->created_at)->format('Y-m-d');
                $cd->template_item_name             = model($cd->item, 'item_name');
                $cd->template_item_code             = model($cd->item, 'item_code');
                $cd->template_total_value_in_case   = $cd->item_qty;
                $cd->template_total_amount          = $creditNote->grand_total;
                $cd->template_delivery_sequnce      = $key + 1;
                $cd->template_trip                  = 1;
                $cd->template_trip_sequnce          = 1;
                $cd->template_vechicle              = ($van) ? $van->id : null;
                $cd->template_driver_name           = model($creditNote->salesman, 'firstname');
                $cd->template_driver_code           = model($creditNote->salesmanInfo, 'salesman_code');
                $cd->template_is_last_trip          = 1;
                $cd->save();
            }

            $creditnotes = CreditNote::where('id', $request->credit_note_id)->first();
            $creditnotes->delivery_driver_id   = $merchandId;
            $creditnotes->approval_status   = "Truck Allocated";
            $creditnotes->truck_allocated_date   = now();
            $creditnotes->save();

            // $creditNote->update([
            //     'approval_status' => "Truck Allocated"
            // ]);

            // $creditNote->update([
            //     'truck_allocated_date' =>now()
            // ]);

            $salesmanInfo = SalesmanInfo::where('user_id', $request->salesman_id)->first();
            $name = $s_info->user->getName();
            $s_code = $s_info->salesman_code;

            $customerInfo = $creditNote->customerInfo;
            $customerGRV = $creditNote->customer_reference_number;
            $message = "Customer " . $customerInfo->customer_code . ' ' . $customerInfo->user->getName() . " GRV requisition no : " . $customerGRV . " has been allocated to the Driver - " . $s_code . ' ' . $name;

        $dataNofi = array(
            'uuid'          => $creditNote->uuid,
            'user_id'       => $merchandId,
            'type'          => "Return",
            'other'         => $merchandId,
            'message'       => $message,
            'status'        => 1,
            'title'         => "Grv Allocated to Driver",
            'noti_type'     => "GRV Allocated",
            'reason'        => "",
            'customer_id'   => $creditNote->customer_id
        );

        $device_detail = DeviceDetail::where('user_id', $merchandId)
            ->orderBy('id', 'desc')
            ->first();

        if (is_object($device_detail)) {
            $t = $device_detail->device_token;
            sendNotificationAndroid($dataNofi, $t);
        }

        if($merchandId)
        {
            saveNotificaiton($dataNofi);
        }

            return prepareResult(true, $creditNote, [], "Credit Note imported.", $this->success);
        }

        return prepareResult(false, [], ["error" => "Credit Note not found."], "Credit Note not found..", $this->unprocessableEntity);
    }

    /***
     * This api is update the credit note to the mobile
     */
    public function updateCreditNote(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unprocessableEntity);
        }

        if (!is_array($request->items)) {
            return prepareResult(false, [], ["error" => "Credit Note details not found"], "Credit Note details not found.", $this->unprocessableEntity);
        }

        $cr = CreditNote::where('uuid', $uuid)->first();

        if ($cr) {
            $total_qty              = 0;
            $total_gross            = 0;
            $total_discount_amount  = 0;
            $total_net              = 0;
            $total_vat              = 0;
            $total_excise           = 0;
            $grand_total            = 0;

            foreach ($request->items as $item) {

                $creditnoteDetail = CreditNoteDetail::find($item['id']);

                if ($creditnoteDetail) {
                    $creditnoteDetail->item_qty             = $item['item_qty'];
                    $creditnoteDetail->item_gross           = $item['item_gross'];
                    $creditnoteDetail->item_discount_amount = $item['item_discount_amount'];
                    $creditnoteDetail->item_net             = $item['item_net'];
                    $creditnoteDetail->item_vat             = $item['item_vat'];
                    $creditnoteDetail->item_excise          = $item['item_excise'];
                    $creditnoteDetail->item_grand_total     = $item['item_grand_total'];
                    $creditnoteDetail->is_deleted           = $item['is_deleted'];
                    $creditnoteDetail->reason_id            = $item['reason_id'];
                    $creditnoteDetail->save();

                    $total_qty              = $total_qty + $creditnoteDetail->item_qty;
                    $total_gross            = $total_gross + $creditnoteDetail->item_gross;
                    $total_discount_amount  = $total_discount_amount + $creditnoteDetail->item_discount_amount;
                    $total_net              = $total_net + $creditnoteDetail->item_net;
                    $total_vat              = $total_vat + $creditnoteDetail->item_vat;
                    $total_excise           = $total_excise + $creditnoteDetail->item_excise;
                    $grand_total            = $grand_total + $creditnoteDetail->item_grand_total;
                }
            }

            $cr->update([
                'total_qty'             => $total_qty,
                'total_gross'           => $total_gross,
                'total_discount_amount' => $total_discount_amount,
                'total_net'             => $total_net,
                'total_vat'             => $total_vat,
                'total_excise'          => $total_excise,
                'grand_total'           => $grand_total,
                'picking_date'          => now(),
                'approval_status'       => "Completed"
            ]);

            // $this->postReturnInJDE($cr->id);

            return prepareResult(true, $cr, [], "Credit note updated.", $this->success);
        }


        return prepareResult(false, [], ["error" => "Credit note not found."], "Credit note not found.", $this->unprocessableEntity);
    }

    public function postWithSap(Request $request){
        $creditnotes_query = CreditNote::with(
                'customerinfo:id,user_id,customer_code',
                'creditNoteDetails',
                'creditNoteDetails.item:id,item_name,item_code',
                'creditNoteDetails.itemUom:id,name,code',
            )->where('organisation_id', auth()->user()->organisation_id)->where('id', $request->id);
        
        $all_creditnotes = $creditnotes_query->where('is_sap_updated', 0)->orderBy('id', 'desc')->get();
        
        $processedData = array();
        $creditNotId   = array();
        foreach ($all_creditnotes as $creditnote) {
            $creditNotId[] = $creditnote->id;
            foreach ($creditnote->CreditNoteDetail as $key => $cnd) {
                $CreditNoteDetail = CreditNoteDetail::with('itemUom', 'item')->where('credit_note_id', $cnd->credit_note_id)->first();
                $processedData[] = [
                    'DOCTYPE'     => "ZRGI",
                    'SOLDTOPARTY' => $creditnote->customerinfo->customer_code,
                    'CUSTOMERLPO' => $creditnote->customer_reference_number,
                    'MATERIAL'    => $CreditNoteDetail->item->item_name ?? '',
                    'QUANTITY'    => $CreditNoteDetail->item_qty,
                    'UOM'         => $CreditNoteDetail->itemUom->name ?? '',
                    'CURRENCY'    => '',
                    'PRICE'       =>  $CreditNoteDetail->item_price,
                    'USAGE'       => 52,
                ];
            }
            
        }
        
        $postField = ['RETURNDATA' => $processedData];
        if(count($processedData) > 0){
            $curl = curl_init();                           // Cert Password
            // curl_setopt($curl, CURLOPT_URL, 'my_addr');
            // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            // $pem=realpath("cert_dev__cert_out.pem");
            // if(!$pem || !is_readable($pem)){
            //     die("error: myfile.pem is not readable! realpath: \"{$pem}\" - working dir: \"".getcwd()."\" effective user: ".print_r(posix_getpwuid(posix_geteuid()),true));
            // }
            //curl_setopt($curl, CURLOPT_SSLCERT, $pem);
            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://albathadevapi.prod.apimanagement.eu10.hana.ondemand.com/10/returnorder',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>json_encode($postField),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'APIKey: bIPDOOpDYdKlDtn4dGIULxPC8Tn6yWb6'
              ),
              //CURLOPT_SSLCERTTYPE       => 'PEM'
            ));

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                
                return prepareResult(false, $error_msg, [], "Credit note not updated.", $this->success);
            }
            
            curl_close($curl);
            $xml = simplexml_load_string($response);
            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
            CreditNote::whereIn('id', $creditNotId)->update(['is_sap_updated'=>1, 'sap_response'=>$json]);
            return prepareResult(true, $array, [], "Credit note updated.", $this->success);

        }else{

            return prepareResult(true, [], [], "Credit note already updated.", $this->success);
        }
        
    }

    public function storeData($id){

        $creditnotes_query = CreditNote::with(
            'customerinfo:id,user_id,customer_code',
            'creditNoteDetails',
            'creditNoteDetails.item:id,item_name,item_code',
            'creditNoteDetails.itemUom:id,name,code',
        )->where('organisation_id', auth()->user()->organisation_id)->where('id', $id);
        
        $all_creditnotes = $creditnotes_query->where('is_sap_updated', 0)->orderBy('id', 'desc')->get();
        $processedData = array();
        $creditNotId   = array();
        foreach ($all_creditnotes as $creditnote) {
            $creditNotId[] = $creditnote->id;
            foreach ($creditnote->CreditNoteDetail as $key => $cnd) {
                $CreditNoteDetail = CreditNoteDetail::with('itemUom', 'item')->where('credit_note_id', $cnd->credit_note_id)->first();
                $processedData[] = [
                    'DOCTYPE'     => "ZRGI",
                    'SOLDTOPARTY' => $creditnote->customerinfo->customer_code,
                    'CUSTOMERLPO' => $creditnote->customer_reference_number,
                    'MATERIAL'    => $CreditNoteDetail->item->item_name ?? '',
                    'QUANTITY'    => $CreditNoteDetail->item_qty,
                    'UOM'         => $CreditNoteDetail->itemUom->name ?? '',
                    'CURRENCY'    => '',
                    'PRICE'       =>  $CreditNoteDetail->item_price,
                    'USAGE'       => 52,
                ];
            }
            
        }

        $postField = ['RETURNDATA' => $processedData];
        if(count($processedData) > 0){
            $curl = curl_init();                           // Cert Password
            // curl_setopt($curl, CURLOPT_URL, 'my_addr');
            // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            // $pem=realpath("cert_dev__cert_out.pem");
            // if(!$pem || !is_readable($pem)){
            //     die("error: myfile.pem is not readable! realpath: \"{$pem}\" - working dir: \"".getcwd()."\" effective user: ".print_r(posix_getpwuid(posix_geteuid()),true));
            // }
            // curl_setopt($curl, CURLOPT_SSLCERT, $pem);
            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://albathadevapi.prod.apimanagement.eu10.hana.ondemand.com/10/returnorder',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>json_encode($postField),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'APIKey: bIPDOOpDYdKlDtn4dGIULxPC8Tn6yWb6'
              ),
              //CURLOPT_SSLCERTTYPE       => 'PEM'
            ));

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                
                return prepareResult(false, $error_msg, [], "Credit note not updated.", $this->success);
            }
            
            curl_close($curl);
            $xml = simplexml_load_string($response);
            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
            CreditNote::whereIn('id', $creditNotId)->update(['is_sap_updated'=>1, 'sap_response'=>$json]);
            return prepareResult(true, $array, [], "Credit note updated.", $this->success);

        }
    }

    public function testGuzzlePostWithSap(Request $request){
        try{
                $client = new Client();
                $headers = [
                  'Content-Type' => 'application/json'
                ];
                $body = '{
                  "RETURNDATA": [
                    {
                      "DOCTYPE": "ZRGI",
                      "SOLDTOPARTY": "3090",
                      "CUSTOMERLPO": "test1",
                      "MATERIAL": "LISTERINE COOL MINT 250ML",
                      "QUANTITY": "10.00",
                      "UOM": "EA",
                      "CURRENCY": "",
                      "PRICE": "10.50",
                      "USAGE": 52
                    }
                  ]
                }';
                // $Nbody = json_encode($body);
                $URI = 'https://albathadev.it-cpi001-rt.cfapps.eu10.hana.ondemand.com/http/mobiato/returnorder';
                $response = $client->post($URI, [
                'connect_timeout' => 650,
                // add these
                'cert' => realpath("cert_dev__cert_out.pem")
            ], $body);

            }
            catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return $e->getResponse()->getBody()->getContents();
        }
        /*$request = new Request('POST', 'https://albathadev.it-cpi001-rt.cfapps.eu10.hana.ondemand.com/http/mobiato/returnorder', $headers, $body);
        $res = $client->sendAsync($request)->wait();*/
        dd($res);
        $creditnotes_query = CreditNote::with(
                'customerinfo:id,user_id,customer_code',
                'creditNoteDetails',
                'creditNoteDetails.item:id,item_name,item_code',
                'creditNoteDetails.itemUom:id,name,code',
            )->where('organisation_id', auth()->user()->organisation_id)->where('id', $request->id);
        
        $all_creditnotes = $creditnotes_query->where('is_sap_updated', 0)->orderBy('id', 'desc')->get();
        $processedData = array();
        $creditNotId   = array();
        foreach ($all_creditnotes as $creditnote) {
            $creditNotId[] = $creditnote->id;
            foreach ($creditnote->CreditNoteDetail as $key => $cnd) {
                $CreditNoteDetail = CreditNoteDetail::with('itemUom', 'item')->where('credit_note_id', $cnd->credit_note_id)->first();
                $processedData[] = [
                    'DOCTYPE'     => "ZRGI",
                    'SOLDTOPARTY' => $creditnote->customerinfo->customer_code,
                    'CUSTOMERLPO' => $creditnote->customer_reference_number,
                    'MATERIAL'    => $CreditNoteDetail->item->item_name ?? '',
                    'QUANTITY'    => $CreditNoteDetail->item_qty,
                    'UOM'         => $CreditNoteDetail->itemUom->name ?? '',
                    'CURRENCY'    => '',
                    'PRICE'       =>  $CreditNoteDetail->item_price,
                    'USAGE'       => 52,
                ];
            }
            
        }
        
        $postField = ['RETURNDATA' => $processedData];
        if(count($processedData) > 0){
            $curl = curl_init();                           // Cert Password
            curl_setopt($curl, CURLOPT_URL, 'my_addr');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            $pem=realpath("cert_dev__cert_out.pem");
            if(!$pem || !is_readable($pem)){
                die("error: myfile.pem is not readable! realpath: \"{$pem}\" - working dir: \"".getcwd()."\" effective user: ".print_r(posix_getpwuid(posix_geteuid()),true));
            }
            curl_setopt($curl, CURLOPT_SSLCERT, $pem);
            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://albathadev.it-cpi001-rt.cfapps.eu10.hana.ondemand.com/http/mobiato/returnorder',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>json_encode($postField),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
              ),
              CURLOPT_SSLCERTTYPE       => 'PEM'
            ));

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                
                return prepareResult(false, $error_msg, [], "Credit note not updated.", $this->success);
            }
            
            curl_close($curl);
            $xml = simplexml_load_string($response);
            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
            CreditNote::whereIn('id', $creditNotId)->update(['is_sap_updated'=>1, 'sap_response'=>$json]);
            return prepareResult(true, $array, [], "Credit note updated.", $this->success);

        }else{

            return prepareResult(true, [], [], "Credit note already updated.", $this->success);
        }
        
    }
}
