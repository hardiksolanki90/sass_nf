<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use App\Imports\PortfolioImport;
use App\Imports\PortfolioImport2;
use App\Imports\PortfolioManagementImport;
use App\Model\Channel;
use App\Model\CustomerInfo;
use App\Model\DistributionStock;
use App\Model\ImportTempFile;
use App\Model\Item;
use App\Model\PortfolioManagement;
use App\Model\PortfolioManagementChannel;
use App\Model\PortfolioManagementCustomer;
use App\Model\PortfolioManagementItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\User; 

class PortfolioManagementController extends Controller
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

        $portfolio_management_query = PortfolioManagement::select('id', 'uuid', 'organisation_id', 'name', 'code', 'start_date', 'end_date')
            ->with(
                'portfolioManagementCustomer:id,portfolio_management_id,user_id',
                'portfolioManagementCustomer.user:id,firstname,lastname',
                'portfolioManagementCustomer.user.customerInfo:id,user_id,customer_code',
                'portfolioManagementItem:id,portfolio_management_id,item_id,listing_fees,store_price',
                'portfolioManagementItem.item:id,item_name,item_code'
            )->where('organisation_id',auth()->user()->organisation_id);

        if ($request->code) {
            $portfolio_management_query->where('code', $request->code);
        }

        if ($request->name) {
            $portfolio_management_query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->start_date) {
            $portfolio_management_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $portfolio_management_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }

        $portfolio_management = $portfolio_management_query->orderBy('id', 'desc')
            ->get();

        $portfolio_management_array = array();
        if (is_object($portfolio_management)) {
            foreach ($portfolio_management as $key => $portfolio_management1) {
                $portfolio_management_array[] = $portfolio_management[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($portfolio_management_array[$offset])) {
                    $data_array[] = $portfolio_management_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($portfolio_management_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($portfolio_management_array);
        } else {
            $data_array = $portfolio_management_array;
        }

        return prepareResult(true, $portfolio_management, [], "Portfolio Management listing", $this->success);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();

        $validate = $this->validations($input, "add");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating Portfolio management", $this->success);
        }

        if (is_array($request->items) && sizeof($request->items) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one items.", $this->unprocessableEntity);
        }

        if (is_array($request->customers) && sizeof($request->customers) < 1) {
            return prepareResult(false, [], [], "Error Please add atleast one customer.", $this->unprocessableEntity);
        }

        $check_code = codeCheck('PortfolioManagement', 'code', $request->code);;
        if ($check_code) {
            return prepareResult(false, [], ['error' => "This portfolio code $request->code already added."], "This portfolio code $request->code already added.", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $portfolio_management = new PortfolioManagement;
            $portfolio_management->name         = $request->name;
            $portfolio_management->code         = nextComingNumber('App\Model\PortfolioManagement', 'portfolio', 'code', $request->code);
            $portfolio_management->start_date   = $request->start_date;
            $portfolio_management->end_date     = $request->end_date;
            $portfolio_management->save();

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    //save PortfolioManagementItem
                    $portfolio_management_item = new PortfolioManagementItem;
                    $portfolio_management_item->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_item->item_id                 = $item['item_id'];
                    $portfolio_management_item->store_price             = $item['store_price'];
                    $portfolio_management_item->listing_fees            = $item['listing_fees'];
                    $portfolio_management_item->plu                     = (!empty($item['plu'])) ? $item['plu'] : null;
                    $portfolio_management_item->save();
                }
            }

            if (is_array($request->channel)) {
                foreach ($request->channel as $channel) {
                    $portfolio_management_channel = new PortfolioManagementChannel();
                    $portfolio_management_channel->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_channel->channel_id = $channel;
                    $portfolio_management_channel->save();
                }
            }

            if (is_array($request->customers)) {
                foreach ($request->customers as $user) {
                    //save PortfolioManagementCustomer
                    $portfolio_management_customer = new PortfolioManagementCustomer;
                    $portfolio_management_customer->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_customer->user_id = $user['customer_id'];
                    $portfolio_management_customer->save();
                }
            }

            \DB::commit();
            updateNextComingNumber('App\Model\PortfolioManagement', 'portfolio');

            $portfolio_management->getSaveData();

            return prepareResult(true, $portfolio_management, [], "Portfolio management added successfully", $this->success);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Portfolio Management", $this->unauthorized);
        }

        $portfolio_management = PortfolioManagement::where('uuid', $uuid)
            ->select('id', 'uuid', 'organisation_id', 'name', 'code', 'start_date', 'end_date')
            ->with(
                'portfolioManagementChannel:id,portfolio_management_id,channel_id',
                'portfolioManagementChannel.channel:id,name',
                'portfolioManagementCustomer:id,portfolio_management_id,user_id',
                'portfolioManagementCustomer.user:id,firstname,lastname',
                'portfolioManagementCustomer.user.customerInfo:id,user_id,customer_code',
                'portfolioManagementItem:id,portfolio_management_id,item_id,listing_fees,store_price',
                'portfolioManagementItem.item:id,item_name'
            )
            ->first();

        if (!is_object($portfolio_management)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $portfolio_management, [], "Portfolio Management Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating Portfolio Management", $this->success);
        }

        \DB::beginTransaction();
        try {
            $portfolio_management = PortfolioManagement::where('uuid', $uuid)
                ->first();

            if (!is_object($portfolio_management)) {
                return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
            }

            PortfolioManagementItem::where('portfolio_management_id', $portfolio_management->id)
                ->delete();
            PortfolioManagementCustomer::where('portfolio_management_id', $portfolio_management->id)
                ->delete();
            PortfolioManagementChannel::where('portfolio_management_id', $portfolio_management->id)
                ->delete();

            $portfolio_management->name         = $request->name;
            $portfolio_management->code         = $request->code;
            $portfolio_management->start_date   = $request->start_date;
            $portfolio_management->end_date     = $request->end_date;
            $portfolio_management->save();

            if (is_array($request->channel)) {
                foreach ($request->channel as $channel) {
                    $portfolio_management_channel = new PortfolioManagementChannel();
                    $portfolio_management_channel->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_channel->channel_id = $channel;
                    $portfolio_management_channel->save();
                }
            }

            if (is_array($request->items)) {
                foreach ($request->items as $item) {
                    //save PortfolioManagementItem
                    $portfolio_management_item = new PortfolioManagementItem;
                    $portfolio_management_item->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_item->item_id                 = $item['item_id'];
                    $portfolio_management_item->store_price             = $item['store_price'];
                    $portfolio_management_item->listing_fees            = $item['listing_fees'];
                    $portfolio_management_item->plu                     = (!empty($item['plu'])) ? $item['plu'] : null;
                    $portfolio_management_item->save();
                }
            }

            if (is_array($request->customers)) {
                foreach ($request->customers as $user) {
                    //save PortfolioManagementCustomer
                    $portfolio_management_customer = new PortfolioManagementCustomer;
                    $portfolio_management_customer->portfolio_management_id = $portfolio_management->id;
                    $portfolio_management_customer->user_id = $user['customer_id'];
                    $portfolio_management_customer->save();
                }
            }

            \DB::commit();

            $portfolio_management->getSaveData();

            return prepareResult(true, $portfolio_management, [], "Portfolio Management updated successfully", $this->success);
        } catch (\Exception $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            \DB::rollback();
            return prepareResult(false, [], $exception->getMessage(), "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Portfolio Management", $this->unauthorized);
        }

        $portfolio_management = PortfolioManagement::where('uuid', $uuid)
            ->first();

        if (is_object($portfolio_management)) {
            $portfolio_management->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }
        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                'name' => 'required',
                'code' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }
        if ($type == 'add_merchandiser_msls') {
            $validator = \Validator::make($input, [
                'customer_id' => 'required',
                'date' => 'required'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    /**
     * This function is import the portfolio directly
     * 
     */

    public function importPortfolio(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }

        // $validator = \Validator::make($request->all(), [
        //     'delivery_update_file' => 'required|mimes:csv,xlsx,xls'
        // ]);

        // if ($validator->fails()) {
        //     $error = $validator->messages()->first();
        //     return prepareResult(false, [], $error, "Failed to validate delivery import", $this->unauthorized);
        // }

        // Excel::import(new PortfolioImport, request()->file('portfolio_file'));
        Excel::import(new PortfolioImport2, request()->file('portfolio_file'));

        return prepareResult(true, [], [], "Portfolio successfully imported", $this->success);
    }


    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $mappingarray = array("Name", "Code", "Start Date", "End Date", "Customer Code", "Channel Name", "Status", "Item Code", "Price");

        return prepareResult(true, $mappingarray, [], "Customer Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'portfolio_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate customer import", $this->unauthorized);
        }
        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('portfolio_file')->store('import');
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


            $import = new PortfolioManagementImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            $succussrecords = 0;
            $successfileids = 0;
            if ($import->successAllRecords()) {
                $succussrecords = count($import->successAllRecords());
                $data = json_encode($import->successAllRecords());
                $fileName = time() . '_datafile.txt';
                \File::put(storage_path() . '/app/tempimport/' . $fileName, $data);

                $importtempfiles = new ImportTempFile();
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
                        //print_r($failure->errors());

                        $error_msg = isset($failure->errors()[0]) ? $failure->errors()[0] : '';
                        if ($error_msg != "") {
                            //$errror_array['errormessage'][] = array("There was an error on row ".$failure->row().". ".$error_msg);
                            //$errror_array['errorresult'][] = $failure->values();
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
        return prepareResult(true, $result, $errors, "Customer successfully imported", $this->success);
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
                    $status = ($row[6] == "Yes") ? 1 : 0;

                    $name           = $row[0];
                    $code           = $row[1];
                    $start_date     = $row[2];
                    $end_date       = $row[3];
                    $customer_infos = CustomerInfo::where('customer_code', $row[4])->first();
                    $channel        = Channel::where('name', 'like', '%' . $row[5] . '%')->first();
                    $item           = Item::where('item_code', $row[7])->first();
                    $item_price     = $row[8];

                    $skipduplicate = $request->skipduplicate;

                    if ($skipduplicate) {
                        $portfolio_management = PortfolioManagement::where('name', 'like', '%' . $name . '%')->first();

                        if (is_object($portfolio_management)) {
                            continue;
                        }

                        $portfolio_management = new PortfolioManagement();
                        $portfolio_management->name         = $name;
                        $portfolio_management->code         = $code;
                        $portfolio_management->start_date   = Carbon::parse($start_date)->format('Y-m-d');
                        $portfolio_management->end_date     = Carbon::parse($end_date)->format('Y-m-d');
                        $portfolio_management->status       = $status;
                        $portfolio_management->save();

                        if ($customer_infos) {
                            $portfolio_management_customer = PortfolioManagementCustomer::where('portfolio_management_id', $portfolio_management->id)
                                ->where('user_id', $customer_infos->user_id)
                                ->first();

                            if (!$portfolio_management_customer) {
                                $portfolio_management_customer = new PortfolioManagementCustomer();
                                $portfolio_management_customer->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_customer->user_id = $customer_infos->user_id;
                                $portfolio_management_customer->save();
                            }
                        }

                        if ($channel) {
                            $portfolio_management_channel = PortfolioManagementChannel::where('portfolio_management_id', $portfolio_management->id)
                                ->where('channel_id', $channel->id)
                                ->first();

                            if (!$portfolio_management_channel) {
                                $portfolio_management_channel = new PortfolioManagementChannel();
                                $portfolio_management_channel->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_channel->channel_id = $channel->id;
                                $portfolio_management_channel->save();
                            }
                        }

                        if ($item) {
                            $portfolio_management_item = PortfolioManagementItem::where('portfolio_management_id', $portfolio_management->id)
                                ->where('item_id', $item->id)
                                ->first();

                            if (!$portfolio_management_item) {
                                $portfolio_management_item = new PortfolioManagementItem();
                                $portfolio_management_item->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_item->item_id         = $item->id;
                                $portfolio_management_item->listing_fees    = $item_price;
                                $portfolio_management_item->store_price     = $item_price;
                                $portfolio_management_item->save();
                            }
                        }
                    } else {

                        $portfolio_management = PortfolioManagement::where('name', 'like', '%' . $name . '%')->first();
                        if (!$portfolio_management) {
                            $portfolio_management = new PortfolioManagement();
                        }

                        $portfolio_management->name         = $name;
                        $portfolio_management->code         = $code;
                        $portfolio_management->start_date   = Carbon::parse($start_date)->format('Y-m-d');
                        $portfolio_management->end_date     = Carbon::parse($end_date)->format('Y-m-d');
                        $portfolio_management->status       = $status;
                        $portfolio_management->save();

                        if ($customer_infos) {
                            $portfolio_management_customer = PortfolioManagementCustomer::where('portfolio_management_id', $portfolio_management->id)
                                ->where('user_id', $customer_infos->user_id)
                                ->first();

                            if (!$portfolio_management_customer) {
                                $portfolio_management_customer = new PortfolioManagementCustomer();
                                $portfolio_management_customer->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_customer->user_id = $customer_infos->user_id;
                                $portfolio_management_customer->save();
                            }
                        }

                        if ($channel) {
                            $portfolio_management_channel = PortfolioManagementChannel::where('portfolio_management_id', $portfolio_management->id)
                                ->where('channel_id', $channel->id)
                                ->first();

                            if (!$portfolio_management_channel) {
                                $portfolio_management_channel = new PortfolioManagementChannel();
                                $portfolio_management_channel->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_channel->channel_id = $channel->id;
                                $portfolio_management_channel->save();
                            }
                        }

                        if ($item) {
                            $portfolio_management_item = PortfolioManagementItem::where('portfolio_management_id', $portfolio_management->id)
                                ->where('item_id', $item->id)
                                ->first();

                            if (!$portfolio_management_item) {
                                $portfolio_management_item = new PortfolioManagementItem();
                                $portfolio_management_item->portfolio_management_id = $portfolio_management->id;
                                $portfolio_management_item->item_id         = $item->id;
                                $portfolio_management_item->listing_fees    = $item_price;
                                $portfolio_management_item->store_price     = $item_price;
                                $portfolio_management_item->save();
                            }
                        }
                    }

                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Customer successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    public function addMerchandiserMSL(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        }

        $portfolio_managements = PortfolioManagement::where('organisation_id', auth()->user()->organisation_id)->get();
        foreach ($portfolio_managements as $key => $pm) {
            $customer_infos     = CustomerInfo::where('customer_code', $pm->code)->first();
            $total_msl_item     = PortfolioManagementItem::where(['customer_id'=>$customer_infos->id])->groupBy('item_id')->get();
            $total_msl_item     = count($total_msl_item);
           
            $merchandisercheck  = DB::table('merchandiser_msls')->whereDate('date', date('Y-m-d'))->where([
                'customer_code'     => $customer_infos->customer_code,
                'customer_id'       => $customer_infos->user_id,
            ])->first();
            if (is_null($merchandisercheck)) {
                $merchandisercheck = DB::table('merchandiser_msls')->insert(
                    [
                        'date'              => date('Y-m-d'),
                        'customer_code'     => $customer_infos->customer_code,
                        'customer_id'       => $customer_infos->user_id,
                        'customer_name'     => $customer_infos->user->firstname.' '.$customer_infos->user->lastname,
                        'total_msl_item'    => $total_msl_item,
                        'msl_item_perform'  => 0,
                        'msl_percentage'    => 0,
                        'merchandiser_id'   => 0,
                        'merchandiser_name' => '',
                        'created_at'        => NOW(),
                        'updated_at'        => NOW()
                    ]
                );
            }
        }

        return prepareResult(true, $merchandisercheck, [], "Merchandiser msl added successfully", $this->success);
        

        // $merchandiser_msl = new MerchandiserMsl;
        // //$merchandiser_msl->organisation_id   = $request->auth()->user()->organisation_id;
        // $merchandiser_msl->date              = $request->date;
        // $merchandiser_msl->customer_code     = $distribution_stock->CustomerInfo->customer_code;
        // $merchandiser_msl->customer_id       = $distribution_stock->CustomerInfo->id;
        // $merchandiser_msl->customer_name     = $distribution_stock->customer->firstname;
        // $merchandiser_msl->total_msl_item    = $total_msl_item;
        // $merchandiser_msl->msl_item_perform  = $msl_item_perform;
        // $merchandiser_msl->msl_percentage    = $msl_item_perform/$total_msl_item;
        // $merchandiser_msl->merchandiser_id   = $distribution_stock->salesman->id;
        // $merchandiser_msl->merchandiser_name = $distribution_stock->salesman->firstname.$distribution_stock->salesman->lastname;
        // $merchandiser_msl->save();

    }

    public function addMerchandiserMslCompliance(Request $request){
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        }

        $merchandiser_msl   = DB::table('merchandiser_msls')->whereDate('date',$request->date)->groupBy('merchandiser_id')->get();
        //dd($merchandiser_msl);
        $merchandiser_msl_compliance = [];
        foreach ($merchandiser_msl as $key => $value) {
            $salesman_info  = DB::table('salesman_infos')->where('user_id', $value->merchandiser_id)->first();
            if (is_null($salesman_info)) {
                continue;
            }
            $total_msl_item = DB::table('merchandiser_msls')->whereDate('date',$request->date)->where('merchandiser_id',$value->merchandiser_id)->max('total_msl_item');


            $msl_item_perform = DB::table('merchandiser_msls')->whereDate('date',$request->date)->where('merchandiser_id',$value->merchandiser_id)->max('msl_item_perform');

            $merchandiser_msl_compliance = DB::table('merchandiser_msl_compliances')->whereDate('date',$request->date)->where('merchandiser_id',$value->merchandiser_id)->first();

            $devide = $total_msl_item == 0 ? 1 : $total_msl_item;
            $percentage = round(($msl_item_perform/$devide)*100);
            //dd($percentage);

            if (is_null($merchandiser_msl_compliance)) {
                $merchandiser_msl_compliance = DB::table('merchandiser_msl_compliances')->insert([
                    'date'              => $request->date,
                    'merchandiser_id'   => $value->merchandiser_id,
                    'merchandiser_code' => $salesman_info->salesman_code,
                    'merchandiser_name' => $value->merchandiser_name,
                    'total_msl_item'    => $total_msl_item,
                    'msl_check_item'    => $msl_item_perform,
                    'msl_percentage'    => $percentage,
                    'created_at'        => NOW(),
                    'updated_at'        => NOW()
                ]);
            }else{
                $merchandiser_msl_compliance = DB::table('merchandiser_msl_compliances')->where('id',$merchandiser_msl_compliance->id)->update([
                    'date'              => $request->date,
                    'merchandiser_id'   => $value->merchandiser_id,
                    'merchandiser_code' => $salesman_info->salesman_code,
                    'merchandiser_name' => $value->merchandiser_name,
                    'total_msl_item'    => $total_msl_item,
                    'msl_check_item'    => $msl_item_perform,
                    'msl_percentage'    => $percentage,
                    'updated_at'        => NOW()
                ]);
            }
        }
        //dd($merchandiser_msl_compliance);
        return prepareResult(true, $merchandiser_msl_compliance, [], "Merchandiser msl compliance added successfully", $this->success);

    }

    public function dateWiseAddMerchandiserMSL(Request $request)
    { 
        //dd('test22');
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ['error' => "Unauthorized access"], "Unauthorized access", $this->unauthorized);
        }

        $portfolio_managements = PortfolioManagement::where('organisation_id', auth()->user()->organisation_id)->get();
        foreach ($portfolio_managements as $key => $pm) {
            $customer_infos     = CustomerInfo::where('customer_code', $pm->code)->first();
            $total_msl_item     = PortfolioManagementItem::where(['customer_id'=>$customer_infos->id])->groupBy('item_id')->get();
            $total_msl_item     = count($total_msl_item);
           
            $merchandisercheck  = DB::table('merchandiser_msls')->whereDate('date', $request->date)->where([
                'customer_code'     => $customer_infos->customer_code,
                'customer_id'       => $customer_infos->user_id,
            ])->first();

            $msl_item_perform     = DistributionStock::where(['customer_id'=>$customer_infos->user_id])->groupBy('item_id')->whereDate('created_at', $request->date)->get();
            $msl_item_perform     = count($msl_item_perform);
            $devide             = $total_msl_item == 0 ? 1 : $total_msl_item;
            $percentage         = round(($msl_item_perform/$devide)*100);

            $distribution_stock     = DistributionStock::where(['customer_id'=>$customer_infos->user_id])->groupBy('item_id')->whereDate('created_at', $request->date)->first();

            if(is_null($distribution_stock) || is_null($distribution_stock->salesman)){
                $customer_merchandisers = DB::table('customer_merchandisers')->where('customer_id', $customer_infos->user_id)->first();



                //$salesman_infos     = DB::table('salesman_infos')->where('id', $customer_merchandisers->merchandiser_id ?? 0)->first();

                $salesman_name      = User::where('id', $customer_merchandisers->merchandiser_id ?? 0)->where('usertype', 3)->first();
                $salesman_id        = $salesman_name->id ?? 0; 
                $salesman_firstname = $salesman_name->firstname ?? '';
                $salesman_lastname  = $salesman_name->lastname ?? '';
                $salesman_name      = $salesman_firstname.' '.$salesman_lastname;
            }else{
                $salesman_id        = $distribution_stock->salesman->user_id ?? 0; 
                $salesman_firstname = $distribution_stock->salesman->firstname ?? '';
                $salesman_lastname  = $distribution_stock->salesman->lastname ?? '';
                $salesman_name      = $salesman_firstname.' '.$salesman_lastname;
            }
            if (is_null($merchandisercheck)) {

                $merchandisercheck = DB::table('merchandiser_msls')->insert(
                    [
                        'date'              => $request->date,
                        'customer_code'     => $customer_infos->customer_code,
                        'customer_id'       => $customer_infos->user_id,
                        'customer_name'     => $customer_infos->user->firstname.' '.$customer_infos->user->lastname,
                        'total_msl_item'    => $total_msl_item,
                        'msl_item_perform'  => $msl_item_perform,
                        'msl_percentage'    => $percentage,
                        'merchandiser_id'   => $salesman_id,
                        'merchandiser_name' => $salesman_name,
                        'created_at'        => NOW(),
                        'updated_at'        => NOW()
                    ]
                );
            }else{
                $merchandisercheck = DB::table('merchandiser_msls')->where('id', $merchandisercheck->id)->update(
                    [
                        'date'              => $request->date,
                        'customer_code'     => $customer_infos->customer_code,
                        'customer_id'       => $customer_infos->user_id,
                        'customer_name'     => $customer_infos->user->firstname.' '.$customer_infos->user->lastname,
                        'total_msl_item'    => $total_msl_item,
                        'msl_item_perform'  => $msl_item_perform,
                        'msl_percentage'    => $percentage,
                        'merchandiser_id'   => $salesman_id,
                        'merchandiser_name' => $salesman_name,
                        'created_at'        => NOW(),
                        'updated_at'        => NOW()
                    ]
                );
            }
        }

        return prepareResult(true, $merchandisercheck, [], "Merchandiser msl added successfully", $this->success);
    }
}
