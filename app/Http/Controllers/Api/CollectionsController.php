<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Invoice;
use App\Model\Collection;
use App\Model\CollectionDetails;
use App\Model\SalesmanNumberRange;
use App\Model\WorkFlowObject;
use App\Model\CreditNote;
use App\Model\DebitNote;
use App\Model\WorkFlowRuleApprovalUser;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Str;


class CollectionsController extends Controller
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

        if (!checkPermission('collection-list')) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$this->user->can('collection-list') && $this->user->role_id != '1') {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        $collection_query = Collection::with(
            'invoice',
            'customer:id,firstname,lastname',
            'customer.customerInfo:id,user_id,customer_code',
            'route:id,route_name,route_code',
            'salesman:id,firstname,lastname',
            'salesman.salesmanInfo:id,user_id,salesman_code',
            'collectiondetails.customer:id,firstname,lastname',
            'collectiondetails.customer.customerInfo:id,user_id,customer_code',
            'collectiondetails.invoice:id,invoice_number,total_net,grand_total',
            'collectiondetails.debit_note:id,debit_note_number,total_net,grand_total',
            'collectiondetails.credit_note:id,credit_note_number,total_net,grand_total',
            'lob',
            'collectiondetails.lob:id,name'
        );
        // ->orWhereHas('collectiondetails', function ($query) {
        //     $query->orWhere('type', '1');
        // })->with('collectiondetails.invoice:id,invoice_number,total_net,grand_total')
        // ->orWhereHas('collectiondetails', function ($user) {
        //     $user->orWhere('type', '2');
        // })->with('collectiondetails.debit_note:id,debit_note_number,total_net,grand_total')
        // ->orWhereHas('collectiondetails', function ($user) {
        //     $user->orWhere('type', '3');
        // })->with('collectiondetails.credit_note:id,credit_note_number,total_net,grand_total');

        if ($request->date) {
            $collection_query->whereDate('created_at', $request->date);
        }

        if ($request->collection_code) {
            $collection_query->where('collection_number', 'like', '%' . $request->collection_code . '%');
        }

        if ($request->payemnt_type) {
            $collection_query->where('payemnt_type', $request->payemnt_type);
        }

        if ($request->id) {
            $collection_query->where('id', $request->id);
        }

        if ($request->customer_name) {
            $name = $request->customer_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $collection_query->whereHas('customer', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $collection_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman) {
            $name = $request->salesman;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $collection_query->whereHas('salesman', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $collection_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $customer_code = $request->customer_code;
            $collection_query->whereHas('customer.customerInfo', function ($q) use ($customer_code) {
                $q->where('customer_code', 'like', '%' . $customer_code . '%');
            });
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $collection_query->whereHas('salesman.salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', $salesman_code);
            });
        }

        if ($request->route_code) {
            $route_code = $request->route_code;
            $collection_query->whereHas('route', function ($q) use ($route_code) {
                $q->where('route_code', 'like', $route_code);
            });
        }

        if ($request->route_name) {
            $route_name = $request->route_name;
            $collection_query->whereHas('route', function ($q) use ($route_name) {
                $q->where('route_name', 'like', '%' . $route_name . '%');
            });
        }

        if ($request->approval_status) {
            $collection_query->where('current_stage', 'like', "%" . $request->approval_status . "%");
        }

        if ($request->current_stage) {
            $collection_query->where('current_stage', '=', $request->current_stage);
        }

        if ($request->erp_status) {

            if ($request->erp_status == "Not Posted") {
                $collection_query->where('is_sync', 0)
                    ->whereNull('erp_id');
            }

            if ($request->erp_status == "Failed") {
                $collection_query->whereNotNull('erp_id')
                    ->where('is_sync', 0);
            }

            if ($request->erp_status == "Posted") {
                $collection_query->where('is_sync', 1)
                    ->where('erp_status', '!=', "Cancelled");
            }
        }

        if ($request->status) {
            $collection_query->where('collection_status', 'like', "%" . $request->approval_status . "%");
        }

        $all_collection = $collection_query->orderBy('created_at', 'desc')->paginate($request->page_size);
        $collection = $all_collection->items();

        $pagination = array();
        $pagination['total_pages'] = $all_collection->lastPage();
        $pagination['current_page'] = (int)$all_collection->perPage();
        $pagination['total_records'] = $all_collection->total();

        $results = GetWorkFlowRuleObject('Collection');
        $approve_need_collection = array();
        $approve_need_collection_detail_object_id = array();
        if (count($results) > 0) {
            foreach ($results as $raw) {
                $approve_need_collection[] = $raw['object']->raw_id;
                $approve_need_collection_detail_object_id[$raw['object']->raw_id] = $raw['object']->uuid;
            }
        }

        // approval
        $collection_array = array();
        if (is_object(collect($collection))) {
            foreach ($collection as $key => $collection1) {
                if (in_array($collection[$key]->id, $approve_need_collection)) {
                    $collection[$key]->need_to_approve = 'yes';
                    if (isset($approve_need_collection_detail_object_id[$collection[$key]->id])) {
                        $collection[$key]->objectid = $approve_need_collection_detail_object_id[$collection[$key]->id];
                    } else {
                        $collection[$key]->objectid = '';
                    }
                } else {
                    $collection[$key]->need_to_approve = 'no';
                    $collection[$key]->objectid = '';
                }

                if ($collection[$key]->current_stage == 'Approved' || request()->user()->usertype == 1 || in_array($collection[$key]->id, $approve_need_collection)) {
                    $collection_array[] = $collection[$key];
                }
            }
        }

        return prepareResult(true, $collection_array, [], "Collection listing", $this->success, $pagination);
    }

    public function pendinginvoice(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();

        $invoices_results = array();
        if ($request->lod_id != "" && $request->customer_id != "" && $request->start_date != "" && $request->end_date != "") {
            $invoices_results = DB::select('call sp_get_pending_invoice_customer(?,?,?,?)', array($request->customer_id, $request->lod_id, $request->start_date, $request->end_date));
        }

        if ($request->lod_id != "" && $request->customer_id != "" && $request->start_date == "") {
            $invoices_results = DB::select('call sp_get_pending_invoice_customer_lobid(?,?)', array($request->customer_id, $request->lod_id));
        }

        if ($request->customer_id != "" && $request->lod_id == "") {
            $invoices_results = DB::select('call sp_get_pending_invoice_customer_id(?)', array($request->customer_id));
        }

        return prepareResult(true, $invoices_results, [], "Invoices listing", $this->success);
    }

    public function routependinginvoice(Request $request, $id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }
        $current_organisation_id = request()->user()->organisation_id;

        $invoices = array();
        $invoices = DB::select('call sp_get_pending_invoice(?,?)', array($id, $current_organisation_id));

        return prepareResult(true, $invoices, [], "Invoices listing", $this->success);
    }

    public function chequeAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "cheque-action");
        $collection_status  = $input['collection_status'];
        $collection_id      = $input['collection_id'];

        $collection = Collection::with('collectiondetails')->find($collection_id);

        if ($collection_status == 'Bounce') {
            $collection->collection_status = "Bounce";
            $collection->save();

            $collectiondetail = $collection->collectiondetails;
            if (count($collectiondetail) > 0) {
                $collectiondetail->each(function ($detail, $key) {
                    if ($detail->type == 1) {
                        $getData = Invoice::find($detail->invoice_id);
                    }

                    if ($detail->type == 2) {
                        $getData = DebitNote::find($detail->invoice_id);
                    }

                    if ($detail->type == 3) {
                        $getData = CreditNote::find($detail->invoice_id);
                    }
                    // Minus the PDC Amount and Pending Credit is Status Release
                    $getData->pdc_amount        = $getData->pdc_amount - $detail->amount;
                    $getData->pending_credit    = $getData->pending_credit + $detail->amount;

                    $getData->save();
                });
            }
        }

        if ($collection_status == 'Release') {
            $collection->collection_status = "Posted";
            $collection->save();

            $collectiondetail = $collection->collectiondetails;
            if (count($collectiondetail) > 0) {
                $collectiondetail->each(function ($detail, $key) {
                    if ($detail->type == 1) {
                        $getData = Invoice::find($detail->invoice_id);
                    }

                    if ($detail->type == 2) {
                        $getData = DebitNote::find($detail->invoice_id);
                    }

                    if ($detail->type == 3) {
                        $getData = CreditNote::find($detail->invoice_id);
                    }
                    // Minus the PDC Amount and Pending Credit is Status Release
                    $getData->pdc_amount        = $getData->pdc_amount - $detail->amount;
                    $getData->pending_credit    = $getData->pending_credit - $detail->amount;

                    $getData->save();
                });
            }
        }

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating collection.", $this->unprocessableEntity);
        }

        $collection = $this->editDate(null, $input['collection_id']);

        $data = array(
            'ref' => $collection->collection_number,
            'status' => Str::lower($collection_status)
        );

        $response = Curl::to('http://nellaracorp.dyndns.org:1214/api/set/chequestate')
            ->withData(array('params' => $data))
            ->asJson(true)
            ->post();

        if (isset($response['state'])) {
            $data = json_decode($response['state']);
            if ($data->response['state'] == "success") {
                $collection->pdc_status = $collection->id;
                $collection->save();
            }
        }

        return prepareResult(true, $collection, [], "Collections detail", $this->success);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function chequeActionOdoo($uuid, Request $request)
    {
        $collection = $this->editDate($uuid);

        // Save Request Log
        requestLog($collection, request()->user()->id, 'Collection', 'odoo-cheque-post');

        $data = array(
            'ref' => $collection->collection_number,
            'status' => Str::lower($request->collection_status)
        );

        $response = Curl::to('http://nellaracorp.dyndns.org:1214/api/set/chequestate')
            ->withData(array('params' => $data))
            ->asJson(true)
            ->post();

        if (isset($response['state'])) {
            $data = json_decode($response['state']);
            if ($data->response['state'] == "success") {
                $collection->pdc_status = $collection->id;
                $collection->save();

                return prepareResult(true, $collection, [], "PDC Status changed", $this->success);
            }
        }
        return prepareResult(false, [], ["error" => "PDC not Status changed."], "PDC not Status changed.", $this->unprocessableEntity);
        // return prepareResult(false, [], [], "PDC not Status changed", $this->unprocessableEntity);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $pending_amount = 0.0;
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!checkPermission('collection-add')) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();

        if ($request->collection_type == '1') {
            $validate = $this->validations($input, "add");
            if ($validate["error"]) {
                return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating collection", $this->unprocessableEntity);
            }
        } else {
            $validate = $this->validations($input, "addchequ");
            if ($validate["error"]) {
                return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating collection", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items."], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "You have to pass atleast one salesman."], "You have to pass atleast one salesman.", $this->unprocessableEntity);
            // return prepareResult(false, [], 'You have to pass atleast one salesman.', "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } else if (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        DB::beginTransaction();
        try {
            $status = 1;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;

            if ($isActivate = checkWorkFlowRule('Collection', 'create', $current_organisation_id)) {
                $status = 0;
                $current_stage = 'Pending';
                // $this->createWorkFlowObject($isActivate, 'Collection', $request);
            }

            $collection = new Collection;
            $collection->invoice_id          = (!empty($request->invoice_id)) ? $request->invoice_id : null;
            $collection->customer_id         = (!empty($request->customer_id)) ? $request->customer_id : null;
            $collection->salesman_id         = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $collection->route_id             = (!empty($route_id)) ? $route_id : null;
            $collection->payemnt_type         = (!empty($request->collection_type)) ? $request->collection_type : null;
            $collection->discount             = (!empty($request->discount)) ? $request->discount : '0.00';
            if ($request->source == 1) {
                $repeat_number = codeCheck('Collection', 'collection_number', $request->collection_number);
                if (is_object($repeat_number)) {
                    return prepareResult(false, [], ["error" => "This collection number " . $request->collection_number . " is already added."], "This collection number is already added.", $this->unprocessableEntity);
                }

                $collection->collection_number = $request->collection_number;
            } else {
                $collection->collection_number = nextComingNumber('App\Model\Collection', 'collection', 'collection_number', $request->collection_number);
            }

            $collection->collection_type        = (!empty($request->payemnt_type)) ? $request->payemnt_type : null;
            $collection->invoice_amount         = (!empty($request->invoice_amount)) ? $request->invoice_amount : null;
            $collection->cheque_number          = (!empty($request->cheque_number)) ? $request->cheque_number : null;
            $collection->cheque_date            = (!empty($request->cheque_date)) ? $request->cheque_date : null;
            $collection->bank_info              = (!empty($request->bank_info)) ? $request->bank_info : null;
            $collection->transaction_number     = (!empty($request->transaction_number)) ? $request->transaction_number : null;
            $collection->allocate_amount        = (!empty($request->allocate_amount)) ? $request->allocate_amount : "0.00";
            $collection->shelf_rent             = (!empty($request->shelf_rent)) ? $request->shelf_rent : "0.00";
            // $collection->collection_status = (!empty($request->payemnt_type) && ($request->payemnt_type == '1')) ? 'Created' : null;
            // $collection->collection_status = (!empty($request->payemnt_type) && ($request->payemnt_type == '2')) ? 'Created' : null;
            $collection->status                 = $status;

            if ($request->cash_type == 1) {
                $collection->collection_status     = "Posted";
                $collection->current_stage         = "Approved";
            } else {
                $collection->collection_status     = "Created";
                $collection->current_stage         = $current_stage;
            }

            $collection->source             = $request->source;
            $collection->lob_id             = (!empty($request->lob_id)) ? $request->lob_id : null;
            $collection->status             = $status;
            $collection->save();

            if ($request->cash_type == 1) {
                $collection->oddo_collection_id = $collection->id;
                $collection->save();
            } else {
                if ($isActivate = checkWorkFlowRule('Collection', 'create', $current_organisation_id)) {
                    $this->createWorkFlowObject($isActivate, 'Collection', $request, $collection);
                }
            }

            $this->saveCollectionDetails($request->items, $collection, $request);

            if (is_object($collection) && $collection->source == 1) {
                $user = User::find($request->user()->id);
                if (is_object($user)) {
                    $salesmanInfo = $user->salesmanInfo;
                    if (is_object($salesmanInfo)) {
                        updateMobileNumberRange($salesmanInfo, 'collection_from', $request->collection_number);
                    }
                }
            }

            DB::commit();

            if ($request->source != 1) {
                updateNextComingNumber('App\Model\Collection', 'collection');
            }

            // $this->collectionPostOdoo($collection->uuid);

            return prepareResult(true, $collection, [], "Collection added successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }


    /**
     * This is the collection detail function
     */


    private function saveCollectionDetails($items, $collection, $request)
    {
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item['type'] == 1) {
                    $invoice = Invoice::find($item['invoice_id']);

                    if (is_object($invoice)) {
                        $pending_amount = $invoice->grand_total - $item['amount'];
                        if ($invoice->grand_total <= $item['amount']) {
                            $invoice->payment_received = '1';
                        } else {
                            $invoice->payment_received = '0';
                        }

                        if ($request->cash_type == 1) {
                            $invoice->pending_credit = "0";
                            $invoice->save();
                        } else {
                            $invoice->pending_credit = $invoice->pending_credit - $item['amount'];
                        }
                        // $invoice->save();
                    } else {
                        $pending_amount = 0.00;
                    }
                } else if ($item['type'] == 2) {
                    $invoice = DebitNote::find($item['invoice_id']);
                    if (is_object($invoice)) {
                        $pending_amount = $invoice->grand_total - $item['amount'];
                        $invoice->pending_credit = $invoice->pending_credit - $item['amount'];
                        // $invoice->save();
                    } else {
                        $pending_amount = 0.00;
                    }
                } else if ($item['type'] == 3) {
                    $invoice = CreditNote::find($item['invoice_id']);
                    if (is_object($invoice)) {
                        $pending_amount = $invoice->grand_total - $item['amount'];
                        $invoice->pending_credit = $invoice->pending_credit - $item['amount'];
                        // $invoice->save();
                    } else {
                        $pending_amount = 0.00;
                    }
                }

                $collectiondetail = new CollectionDetails;
                $collectiondetail->collection_id = $collection->id;
                $collectiondetail->customer_id = (!empty($item['customer_id'])) ? $item['customer_id'] : null;
                $collectiondetail->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
                $collectiondetail->invoice_id = $item['invoice_id'];
                $collectiondetail->amount = $item['amount'];
                $collectiondetail->type = $item['type'];
                $collectiondetail->pending_amount = $pending_amount;
                $collectiondetail->save();
            }
        }
    }


    /*
    * This functio is used only for collection post
    */

    public function collectionPostOdoo($uuid)
    {
        $collection = $this->editDate($uuid);

        // Save Request Log
        requestLog($collection, request()->user()->id, 'Collection', 'odoo-post');

        $response = Curl::to('http://nellaracorp.dyndns.org:1214/api/create/payment')
            ->withData(array('params' => $collection))
            ->asJson(true)
            ->post();

        if (isset($response['result'])) {
            $data = json_decode($response['result']);
            if ($data->response[0]->state == "success") {
                $collection->oddo_collection_id = $data->response[0]->inv_id;
                $collection->odoo_failed_response = null;
            } else {
                $collection->odoo_failed_response = $response['result'];
            }
        }

        if (isset($response['error'])) {
            $collection->odoo_failed_response = $response['error'];
        }

        unset($collection->collection_date);

        $collection->save();

        if (!empty($collection->oddo_collection_id)) {
            return prepareResult(true, $collection, [], "Collection posted sucessfully", $this->success);
        }
        return prepareResult(false, [], ["error" => "Collection not posted."], "Collection not posted.", $this->unprocessableEntity);

        // return prepareResult(false, $collection, [], "Collection not posted", $this->unprocessableEntity);
    }

    private function editDate($uuid = null, $id = null)
    {
        $cq = Collection::select(
            'id',
            'uuid',
            'organisation_id',
            'invoice_id',
            'customer_id',
            'salesman_id',
            'route_id',
            'collection_number',
            'payemnt_type',
            'invoice_amount',
            'discount',
            'collection_status',
            'cheque_number',
            'cheque_date',
            'bank_info',
            'transaction_number',
            'current_stage',
            'current_stage_comment',
            'oddo_collection_id',
            'odoo_failed_response',
            'lob_id',
            'status',
            'source',
            'created_at',
            'allocate_amount as allocated_amount',
            'shelf_rent as shelfRent_amount',
            // 'created_at as collection_date'
        )
            ->with(
                'invoice',
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code',
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'lob:id,name',
                'route:id,route_name,route_code',
                'collectiondetails',
                'collectiondetails.customer:id,firstname,lastname',
                'collectiondetails.customer.customerInfo:id,user_id,customer_code',
                'collectiondetails.invoice:id,grand_total,invoice_number,total_net',
                'collectiondetails.debit_note:id,debit_note_number,total_net,grand_total',
                'collectiondetails.credit_note:id,credit_note_number,total_net,grand_total',
                'collectiondetails.lob:id,name'
            );

        if ($id) {
            $collection =  $cq->find($id);
            if (is_object($collection)) {
                $collection->collection_date = Carbon::parse($collection->created_at)->format('Y-m-d');
            }
        } else {
            $collection = $cq->where('uuid', $uuid)
                ->first();
            if (is_object($collection)) {
                $collection->collection_date = Carbon::parse($collection->created_at)->format('Y-m-d');
            }
        }

        return $collection;
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

        if (!checkPermission('collection-detail')) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating collection.", $this->unauthorized);
        }

        $collection = $this->editDate($uuid);

        if (!is_object($collection)) {
            return prepareResult(false, [], ["error" => "Oops!!!, something went wrong, please try again."], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unprocessableEntity);
        }

        return prepareResult(true, $collection, [], "Collection Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        if (!checkPermission('collection-edit')) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        $input = $request->json()->all();

        if ($request->payemnt_type == '1') {

            $validate = $this->validations($input, "add");
            if ($validate["error"]) {
                return prepareResult(false, [], $validate['errors']->first(), "Error while validating collection", $this->unprocessableEntity);
            }
        } else {

            $validate = $this->validations($input, "addchequ");
            if ($validate["error"]) {
                return prepareResult(false, [], $validate['errors']->first(), "Error while validating collection", $this->unprocessableEntity);
            }
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one items."], "Error Please add atleast one items.", $this->unprocessableEntity);
            // return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if ($request->source == 1 && !$request->salesman_id) {
            return prepareResult(false, [], ["error" => "Error Please add atleast one salesman."], "Error Please add atleast one salesman.", $this->unprocessableEntity);
            // return prepareResult(false, [], 'You have to pass salesman', "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (!empty($request->route_id)) {
            $route_id = $request->route_id;
        } else if (!empty($request->salesman_id)) {
            $route_id = getRouteBySalesman($request->salesman_id);
        }

        DB::beginTransaction();
        try {
            $status = $request->status;
            $current_stage = 'Approved';
            $current_organisation_id = request()->user()->organisation_id;
            if ($isActivate = checkWorkFlowRule('Collection', 'create', $current_organisation_id)) {
                $current_stage = 'Pending';
                //$this->createWorkFlowObject($isActivate, 'Collection',$request);
            }

            $collection = Collection::where('uuid', $uuid)->first();
            $collection->invoice_id             = (!empty($request->invoice_id)) ? $request->invoice_id : null;
            $collection->customer_id            = (!empty($request->customer_id)) ? $request->customer_id : null;
            $collection->salesman_id            = (!empty($request->salesman_id)) ? $request->salesman_id : null;
            $collection->route_id               = (!empty($route_id)) ? $route_id : null;
            $collection->route_id               = (!empty($request->route_id)) ? $request->route_id : null;
            $collection->payemnt_type           = (!empty($request->payemnt_type)) ? $request->payemnt_type : null;
            $collection->discount               = (!empty($request->discount)) ? $request->discount : "0.00";
            $collection->collection_type        = (!empty($request->collection_type)) ? $request->collection_type : null;
            $collection->invoice_amount         = (!empty($request->invoice_amount)) ? $request->invoice_amount : null;
            $collection->cheque_number          = (!empty($request->cheque_number)) ? $request->cheque_number : null;
            $collection->cheque_date            = (!empty($request->cheque_date)) ? $request->cheque_date : null;
            $collection->bank_info              = (!empty($request->bank_info)) ? $request->bank_info : null;
            $collection->transaction_number     = (!empty($request->transaction_number)) ? $request->transaction_number : null;
            $collection->allocate_amount        = (!empty($request->allocate_amount)) ? $request->allocate_amount : "0.00";
            $collection->shelf_rent             = (!empty($request->shelf_rent)) ? $request->shelf_rent : "0.00";
            $collection->status                 = $status;
            $collection->current_stage          = $current_stage;
            $collection->source                 = $request->source;
            $collection->lob_id                 = (!empty($request->lob_id)) ? $request->lob_id : null;
            $collection->current_stage          = $current_stage;
            $collection->status                 = $status;
            $collection->save();

            if ($isActivate = checkWorkFlowRule('Collection', 'edit', $current_organisation_id)) {
                $this->createWorkFlowObject($isActivate, 'Collection', $request, $collection);
            }

            CollectionDetails::where('collection_id', $collection->id)->forceDelete();

            $this->saveCollectionDetails($request->items, $collection, $request);

            // if ($request->collection_type == '1') {

            //     if (is_array($request->items) && sizeof($request->items) < 1) {
            //         return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
            //     }

            //     if (is_array($request->items)) {
            //         foreach ($request->items as $item) {

            //             $invoice = Invoice::where('customer_id', $request->customer_id)
            //                 ->orderBy('id', 'ASC')
            //                 ->first();

            //             if ($invoice) {
            //                 $pending_amount = $invoice->grand_total - $request->invoice_amount;
            //                 $collectiondetail = new CollectionDetails();
            //                 $collectiondetail->collection_id = $collection->id;
            //                 $collectiondetail->customer_id = (!empty($item['customer_id'])) ? $item['customer_id'] : null;
            //                 $collectiondetail->lob_id = (!empty($request->lob_id)) ? $request->lob_id : null;
            //                 $collectiondetail->invoice_id = $item['invoice_id'];
            //                 $collectiondetail->amount = $item['amount'];
            //                 $collectiondetail->pending_amount = $pending_amount;
            //                 $collectiondetail->save();

            //                 $invoice = Invoice::find($item['invoice_id']);
            //                 if ($invoice->grand_total <= $item['amount']) {
            //                     $invoice->payment_received = '1';
            //                 }
            //                 $invoice->save();
            //             }
            //         }
            //     }
            // } else if ($request->collection_type == '2') {
            //     $invoice = Invoice::where('customer_id', $request->customer_id)->orderBy('id', 'DESC')->first();
            //     if ($invoice) {
            //         $pending_amount = $invoice->grand_total - $request->invoice_amount;
            //         $collectiondetail = new CollectionDetails();
            //         $collectiondetail->collection_id = $collection->id;
            //         $collectiondetail->invoice_id = $invoice->id;
            //         $collectiondetail->amount = $request->invoice_amount;
            //         $collectiondetail->pending_amount = $pending_amount;
            //         $collectiondetail->save();
            //         if ($invoice->grand_total <= $request->invoice_amount) {
            //             $invoice_update = Invoice::find($invoice->id);
            //             $invoice_update->payment_received = '1';
            //             $invoice_update->save();
            //         }
            //     }
            // } else if ($request->collection_type == '3') {
            //     $invoice = Invoice::where('customer_id', $request->customer_id)->orderBy('id', 'ASC')->first();
            //     if ($invoice) {
            //         $pending_amount = $invoice->grand_total - $request->invoice_amount;
            //         $collectiondetail = new CollectionDetails();
            //         $collectiondetail->collection_id = $collection->id;
            //         $collectiondetail->invoice_id = $invoice->id;
            //         $collectiondetail->amount = $request->invoice_amount;
            //         $collectiondetail->pending_amount = $pending_amount;
            //         $collectiondetail->save();
            //         if ($invoice->grand_total <= $request->invoice_amount) {
            //             $invoice_update = Invoice::find($invoice->id);
            //             $invoice_update->payment_received = '1';
            //             $invoice_update->save();
            //         }
            //     }
            // }

            DB::commit();

            $collection->getSaveData();

            return prepareResult(true, $collection, [], "Collection updated successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                // 'invoice_id' => 'required|integer|exists:invoices,id',
                'customer_id' => 'required|integer',
                'collection_type' => 'required',
                'payemnt_type' => 'required',
                'invoice_amount' => 'required'
            ]);
        }

        if ($type == "addchequ") {
            $validator = Validator::make($input, [
                // 'invoice_id' => 'required|integer|exists:invoices,id',
                'customer_id' => 'required|integer',
                'collection_type' => 'required',
                'payemnt_type' => 'required',
                'invoice_amount' => 'required',
                'cheque_date' => 'required',
                'cheque_number' => 'required',
                'bank_info' => 'required',
            ]);
        }


        if ($type == 'bulk-action') {
            $validator = Validator::make($input, [
                'action' => 'required',
                'collection_ids' => 'required'
            ]);
        }
        if ($type == "cheque-action") {
            $validator = Validator::make($input, [
                'collection_status' => 'required',
                'collection_id' => 'required'
            ]);
        }
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function customerPayment($customer_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        if (!$customer_id) {
            return prepareResult(false, [], [], "Error while validating customer id.", $this->unauthorized);
        }

        $collection = Collection::select(
            'id',
            'uuid',
            'customer_id',
            'collection_number',
            'invoice_amount',
            DB::raw("CASE
                        WHEN payemnt_type=1 THEN 'Cash'
                        WHEN payemnt_type=2 THEN 'Cheque'
                        WHEN payemnt_type=3 THEN 'NEFT'
                        ELSE ''
                    END As payment_mode")
        )
            //            ->with('collectiondetails')
            ->where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->get();

        return prepareResult(true, $collection, [], "Customer Collection listing", $this->success);
    }

    /**
     * Get price specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $item_id , $item_uom_id, $item_qty
     * @return \Illuminate\Http\Response
     */

    public function createWorkFlowObject($work_flow_rule_id, $module_name, Request $request, $obj)
    {
        $createObj = new WorkFlowObject;
        $createObj->work_flow_rule_id = $work_flow_rule_id;
        $createObj->module_name = $module_name;
        $createObj->raw_id = (isset($obj->id) ? $obj->id : null);
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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $action
     * @param string $status
     * @param string $uuid
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        // if (!checkPermission('collection-bulk-action')) {
        // return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        // }

        $input = $request->json()->all();
        $validate = $this->validations($input, "bulk-action");

        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating collection.", $this->unprocessableEntity);
        }

        $action = $request->action;

        if (empty($action)) {
            return prepareResult(false, [], [], "Please provide valid action parameter value.", $this->unprocessableEntity);
        }

        if ($action == 'active' || $action == 'inactive') {
            $uuids = $request->collection_ids;

            foreach ($uuids as $uuid) {
                Collection::where('uuid', $uuid)->update([
                    'status' => ($action == 'active') ? 1 : 0
                ]);
            }

            $collection = $this->index();
            return prepareResult(true, $collection, [], "collection status updated", $this->success);
        } else if ($action == 'delete') {
            $uuids = $request->collection_ids;
            foreach ($uuids as $uuid) {
                collection::where('uuid', $uuid)->delete();
            }

            $collection = $this->index();
            return prepareResult(true, $collection, [], "collection deleted success", $this->success);
        }
    }

    public function grouppendinginvoice(Request $request)
    {
        $current_organisation_id = request()->user()->organisation_id;
        $input = $request->json()->all();
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "You do not have the required authorization"], "You do not have the required authorization.", $this->forbidden);
        }

        $invoices = array();
        $lob_id = $input['lob_id'];

        if ($input['lob_id'] != "" && $input['start_date'] != "" && $input['end_date'] != "") {

            $invoices = DB::select('call sp_get_pending_invoice_group_date(?,?,?,?)', array($input['lob_id'], $input['start_date'], $input['end_date'], $current_organisation_id));
        } else if (!empty($input['lob_id'])) {
            $invoices = DB::select('call sp_get_pending_invoice_group(?,?)', array($input['lob_id'], $current_organisation_id));
        }

        return prepareResult(true, $invoices, [], "Invoices listing", $this->success);
    }
}
