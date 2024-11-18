<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CustomerInfo;
use App\Model\Distribution;
use App\Model\DistributionCustomer;
use App\Model\ImportTempFile;
use App\Model\Planogram;
use App\Model\PlanogramImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;
use App\Imports\PlanogramImport;
use App\Model\PlanogramCustomer;
use App\Model\PlanogramDistribution;
use App\User;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

use File;
use URL;

class PlanogramController extends Controller
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

        $planogram_query = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status')
            ->with(
                'planogramCustomer:id,planogram_id,customer_id',
                'planogramCustomer.customer:id,firstname,lastname',
                'planogramCustomer.customer.customerInfo:id,user_id,customer_code',
                'planogramCustomer.planogramDistribution',
                'planogramCustomer.planogramDistribution.distribution:id,name',
                'planogramCustomer.planogramDistribution.planogramImages'

            );

        if ($request->name) {
            $planogram_query->where('name', $request->name);
        }

        if ($request->start_date) {
            $planogram_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $planogram_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }

        $planogram = $planogram_query->orderBy('id', 'desc')
            ->get();

        $planogram_array = array();
        if (is_object($planogram)) {
            foreach ($planogram as $key => $planogram1) {
                $planogram_array[] = $planogram[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($planogram_array[$offset])) {
                    $data_array[] = $planogram_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($planogram_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($planogram_array);
        } else {
            $data_array = $planogram_array;
        }

        return prepareResult(true, $data_array, [], "Planogram listing", $this->success, $pagination);

        // return prepareResult(true, $planogram, [], "Planogram listing", $this->success);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating planogram", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {
            $planogram = new Planogram;
            $planogram->name = $request->name;
            $planogram->start_date = $request->start_date;
            $planogram->end_date = $request->end_date;
            $planogram->status = $request->status;
            $planogram->save();

            if (is_array($request->customer_distribution) && sizeof($request->customer_distribution) >= 1) {
                foreach ($request->customer_distribution as $customer) {
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram->id;
                    $pc->customer_id = $customer['customer_id'];
                    $pc->save();

                    if (is_array($customer['distribution']) && sizeof($customer['distribution']) >= 1) {
                        foreach ($customer['distribution'] as $distribution) {
                            $pd = new PlanogramDistribution;
                            $pd->planogram_id = $planogram->id;
                            $pd->distribution_id = $distribution['distribution_id'];
                            $pd->customer_id = $pc->customer_id;
                            $pd->planogram_customer_id = $pc->id;
                            $pd->save();

                            if (is_array($distribution['images']) && sizeof($distribution['images']) >= 1) {
                                foreach ($distribution['images'] as $image) {
                                    $pi = new PlanogramImage;
                                    $image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
                                    $pi->planogram_id = $planogram->id;
                                    $pi->planogram_distribution_id = $pd->id;
                                    $pi->image_string           = $image_string;
                                    $pi->save();
                                }
                            }
                        }
                    }
                }
            }

            // if (is_array($request->customer_ids) && sizeof($request->customer_ids) >= 1) {
            //     foreach ($request->customer_ids as $customer) {
            //     }
            // }

            // if (is_array($request->customer_distribution) && sizeof($request->customer_distribution) >= 1) {
            //     foreach ($request->customer_distribution as $cdKey => $cd) {
            //         if (is_array($cd['distribution']) && sizeof($cd['distribution']) >= 1) {
            //             foreach ($cd['distribution'] as $dKey => $distribution) {
            //                 $pd = new PlanogramDistribution;
            //                 $pd->planogram_id = $planogram->id;
            //                 $pd->distribution_id = $distribution['distribution_id'];
            //                 $pd->save();
            //                 if (is_array($distribution['images']) && sizeof($distribution['images']) >= 1) {
            //                     foreach ($distribution['images'] as $image) {
            //                         $pi = new PlanogramImage;
            //                         $image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                         $pi->planogram_id = $planogram->id;
            //                         $pi->planogram_distribution_id = $pd->id;
            //                         $pi->image_string           = $image_string;
            //                         $pi->save();
            //                     }
            //                 }
            //             }
            //         }
            //     }
            // }

            \DB::commit();

            $planogram->getData();

            return prepareResult(true, $planogram, [], "Planogram added successfully", $this->created);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating reason", $this->unauthorized);
        }

        $planogram = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status')
            ->with(
                'planogramCustomer:id,planogram_id,customer_id',
                'planogramCustomer.customer:id,firstname,lastname',
                'planogramCustomer.customer.customerInfo:id,user_id,customer_code',
                'planogramCustomer.planogramDistribution',
                'planogramCustomer.planogramDistribution.planogramImages'

            )
            ->where('uuid', $uuid)
            ->first();

        if (!is_object($planogram)) {
            return prepareResult(false, [], [], "Oops!!!, something went wrong, please try again.", $this->unauthorized);
        }

        return prepareResult(true, $planogram, [], "Planogram Edit", $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating planogram", $this->unprocessableEntity);
        }

        \DB::beginTransaction();
        try {

            $planogram = Planogram::where('uuid', $uuid)->first();
            PlanogramDistribution::where('planogram_id', $planogram->id)->forceDelete();
            PlanogramCustomer::where('planogram_id', $planogram->id)->forceDelete();
            // $PlanogramImage = PlanogramImage::where('planogram_id', $planogram->id)->get();
            // if (count($PlanogramImage)) {
            //     foreach ($PlanogramImage as $image) {
            //         unlink($image);
            //     }
            // }

            $planogram->name = $request->name;
            $planogram->start_date = $request->start_date;
            $planogram->end_date = $request->end_date;
            $planogram->status = $request->status;
            $planogram->save();

            if (is_array($request->customer_distribution) && sizeof($request->customer_distribution) >= 1) {
                foreach ($request->customer_distribution as $customer) {
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram->id;
                    $pc->customer_id = $customer['customer_id'];
                    $pc->save();

                    updateMerchandiser($request->user()->organisation_id, $customer['customer_id'], true);

                    if (is_array($customer['distribution']) && sizeof($customer['distribution']) >= 1) {
                        foreach ($customer['distribution'] as $distribution) {

                            $pd = new PlanogramDistribution;
                            $pd->planogram_id = $planogram->id;
                            $pd->distribution_id = $distribution['distribution_id'];
                            $pd->customer_id = $customer['customer_id'];
                            $pd->planogram_customer_id = $pc->id;
                            $pd->save();

                            if (is_array($distribution['images']) && sizeof($distribution['images']) >= 1) {
                                foreach ($distribution['images'] as $image) {
                                    if ($image) {
                                        $pi = new PlanogramImage;
                                        $image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
                                        $pi->planogram_id = $planogram->id;
                                        $pi->planogram_distribution_id = $pd->id;
                                        $pi->image_string           = $image_string;
                                        $pi->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }

            \DB::commit();

            $planogram->getData();

            return prepareResult(true, $planogram, [], "Planogram update successfully", $this->success);
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
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "Error while validating Reason", $this->unauthorized);
        }

        $planogram = Planogram::where('uuid', $uuid)
            ->first();

        if (is_object($planogram)) {
            $PlanogramImage = PlanogramImage::where('planogram_id', $planogram->id)->orderBy('id', 'desc')->get();
            // if (count($PlanogramImage)) {
            //     foreach ($PlanogramImage as $image) {
            //         unlink($image);
            //     }
            // }
            PlanogramDistribution::where('planogram_id', $planogram->id)->delete();
            PlanogramCustomer::where('planogram_id', $planogram->id)->delete();

            $planogram->delete();
            return prepareResult(true, [], [], "Record delete successfully", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    public function planogramCustomerList(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        $customer_ids = $request->customer_ids;

        $user = User::select('id', 'firstname', 'lastname')
            ->with(
                'disctributionCustomer:id,customer_id,distribution_id',
                'disctributionCustomer.distribution:id,name,start_date,end_date'
            )
            ->whereIn('id', $customer_ids)
            ->orderBy('id', 'desc')
            ->get();
        // pre($user);

        // $disctribution_customer = DistributionCustomer::select('id', 'distribution_id', 'customer_id')
        //     // ->groupBy('customer_id')
        //     ->with('distribution:id,name', 'customer:id,firstname,lastname')
        //     ->whereIn('customer_id', $customer_ids)
        //     ->get();

        // $disctribution_customer = Distribution::select('id', 'name')
        // // ->groupBy('customer_id')
        // ->with('distributionCustomer:id,customer_id,distribution_id')
        // ->whereHas('distributionCustomer', function ($q) use ($customer_ids) {
        //     $q->whereIn('customer_id', $customer_ids);
        // })->get();
        // ->with('distribution:id,name', 'customer:id,firstname,lastname')
        // ->get();

        // $planograCustomer = PlanogramCustomer::select('id', 'customer_id')
        //     ->with(
        //         'customer:id,firstname,lastname',
        //         'disctributionCustomer:id,customer_id,distribution_id',
        //         'disctributionCustomer.distribution:id,name'
        //     )
        //     ->whereIn('customer_id', $customer_ids)
        //     ->get();

        if (count($user)) {
            foreach ($user as $key => $u) {
                if ($u->disctributionCustomer->count()) {
                    $discCustomers = array();
                    foreach ($u->disctributionCustomer as $discCustomer) {
                        if (is_object($discCustomer->distribution)) {
                            if ($discCustomer->distribution->start_date <= date('Y-m-d') && $discCustomer->distribution->end_date >= date('Y-m-d')) {
                                $discCustomers[] = $discCustomer->distribution;
                                // if (count($discCustomers)) {
                                //     // pre($u->id, false);
                                //     foreach ($discCustomers as $dks => $dic) {
                                //         // pre($user_id, false);
                                //         $discCustomers[$dks]->id_customer = $discCustomer->customer_id;
                                //     }
                                // }
                            }
                        }
                    }
                    $user[$key]->distribution = $discCustomers;
                }
                unset($user[$key]->disctributionCustomer);
            }
        }

        if (count($user)) {
            return prepareResult(true, $user, [], "Destination Customer listing", $this->success);
        }

        return prepareResult(true, [], [], "Destination Customer listing", $this->success);
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = \Validator::make($input, [
                // 'customer_id' => 'required|integer|exists:users,id',
                'name' => 'required',
                'start_date' => 'required|date',
                'end_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function planogramImage($planogram_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$planogram_id) {
            return prepareResult(false, [], [], "Error while validating planogram", $this->unauthorized);
        }

        $planogram = PlanogramImage::where('planogram_id', $planogram_id)
            ->orderBy('id', 'desc')
            ->get();

        if (is_object($planogram)) {
            return prepareResult(true, $planogram, [], "Planogram Image listing", $this->success);
        }

        return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
    }

    public function planogramMerchandiserbyCustomer($merchandiser_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "Unauthorized access", $this->unauthorized);
        }

        if (!$merchandiser_id) {
            return prepareResult(false, [], [], "Error while validating planogram", $this->unauthorized);
        }

        // 'planogramCustomer:id,planogram_id,customer_id',
        // 'planogramCustomer.customer:id,firstname,lastname',
        // 'planogramCustomer.planogramDistribution',
        // 'planogramCustomer.planogramDistribution.distribution:id,name',
        // 'planogramCustomer.planogramDistribution.planogramImages'

        // $planogramCustomer = PlanogramCustomer::select('id', 'planogram_id', 'customer_id')
        //     ->with(
        //         'customer:id,firstname,lastname',
        //         'customer.customerInfo:id,merchandiser_id,user_id',
        //         'customer.customerInfo.merchandiser:id,firstname,lastname',
        //         'planogram',
        //         'planogramDistribution:id,planogram_id,distribution_id',
        //         'planogramDistribution.distribution:id,name,start_date,end_date',
        //         'planogramDistribution.planogramImages'
        //     )
        //     ->whereHas('customer.customerInfo', function ($q) use ($merchandiser_id) {
        //         $q->where('merchandiser_id', $merchandiser_id);
        //     })
        //     ->whereHas('planogram', function ($q) {
        //         $q->where('start_date', '<=', date('Y-m-d'));
        //         $q->where('end_date', '>=', date('Y-m-d'));
        //     })
        //     ->get();

        $customer_info = CustomerInfo::select('id', 'user_id')
            ->with(
                'user:id,firstname,lastname',
                'customerMerchandiser',
                'customerMerchandiser.salesman:id,firstname,lastname',
                'planogramCustomer',
                'planogramCustomer.planogram',
                'planogramCustomer.planogramDistribution',
                'planogramCustomer.planogramDistribution.distribution'
                // 'planogramCustomer.planogramDistribution.planogramImages'
            )
            ->whereHas('customerMerchandiser', function ($h) use ($merchandiser_id) {
                $h->where('merchandiser_id', $merchandiser_id);
            })
            ->whereHas('planogramCustomer.planogram', function ($q) {
                $q->where('start_date', '<=', date('Y-m-d'));
                $q->where('end_date', '>=', date('Y-m-d'));
            })
            ->orderBy('id', 'desc')
            ->get();

        // $customer_info = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status')
        // ->with(
        //     'planogramCustomer:id,planogram_id,customer_id',
        //     'planogramCustomer.customer:id,firstname,lastname',
        //     'planogramCustomer.customer.customerInfo:id,user_id,merchandiser_id',
        //     'planogramCustomer.planogramDistribution',
        //     'planogramCustomer.planogramDistribution.distribution:id,name'

        // )
        // ->where('start_date', '<=', date('Y-m-d'))
        // ->where('end_date', '>=', date('Y-m-d'))
        // ->whereHas('planogramCustomer.customer.customerInfo', function ($q) use ($merchandiser_id) {
        //     $q->where('merchandiser_id', $merchandiser_id);
        // })
        // ->get();

        $customer_info_array = array();
        if (is_object($customer_info)) {
            foreach ($customer_info as $key => $customerInfo) {
                if (count($customerInfo->planogramCustomer)) {
                    foreach ($customerInfo->planogramCustomer as $pc => $planogram_customer) {
                        if (count($planogram_customer->planogramDistribution)) {
                            foreach ($planogram_customer->planogramDistribution as $pldKey => $planogram_distribution) {
                                $planograImage = PlanogramImage::where('planogram_id', $planogram_distribution->planogram_id)
                                    ->where('planogram_distribution_id', $planogram_distribution->id)
                                    ->get();
                                $customer_info[$key]->planogramCustomer[$pc]->planogramDistribution[$pldKey]->images = $planograImage;
                            }
                        }
                    }
                }
                $customer_info_array[] = $customer_info[$key];
            }
        }
        $data_array = array();
        $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        $pagination = array();

        if ($page && $limit) {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($customer_info_array[$offset])) {
                    $data_array[] = $customer_info_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($customer_info_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($customer_info_array);
        } else {
            $data_array = $customer_info_array;
        }

        return prepareResult(true, $data_array, [], "Planogram customer listing", $this->success, $pagination);

        // $merge_all_data = array();
        // foreach ($customer_info as $custKey => $customer) {
        //     // 1 customer
        //     $merge_data = new stdClass;
        //     $merge_data->cusotmer_id = $customer->user_id;
        //     $merge_data->user = $customer->user;
        //     $merge_data->merchandiser = $customer->merchandiser;
        //     $planograImage = array();
        //     foreach ($customer->planogram as $aicKey => $planogram) {
        //         // 2 planogram
        //         $distribution_image = array();
        //         $merge_data->planogram = $planogram;
        //         $planogram_array = $planogram->planogramImage()->groupBy('distribution_id')->pluck('distribution_id')->toArray();

        //         if (count($planogram->planogramImage) > 0) {
        //             // 3 planogram Images
        //             foreach ($planogram->planogramImage as $pikey => $image) {
        //                 if (in_array($image->distribution_id, $planogram_array)) {

        //                     $distribution_image['distribution'] = $image->distribution;
        //                     $distribution_image['distribution']->images = array();
        //                     $distribution_image['distribution']['images'] = $image;

        //                     unset($image->distribution);
        //                 }
        //             }
        //             $planograImage[] = $distribution_image;
        //         }
        //         $merge_data->planogram->planograImages = $planograImage;
        //         unset($merge_data->planogram->planogram_image);
        //         // $merge_data->planograImages = $planograImage;
        //     }
        //     $merge_all_data[] = $merge_data;
        // }

        // $data_array = array();
        // $page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
        // $limit = (isset($_REQUEST['page_size'])) ? $_REQUEST['page_size'] : '';
        // $pagination = array();

        // if ($page && $limit) {
        //     $offset = ($page - 1) * $limit;
        //     for ($i = 0; $i < $limit; $i++) {
        //         if (isset($merge_all_data[$offset])) {
        //             $data_array[] = $merge_all_data[$offset];
        //         }
        //         $offset++;
        //     }

        //     $pagination['total_pages'] = ceil(count($merge_all_data) / $limit);
        //     $pagination['current_page'] = (int)$page;
        //     $pagination['total_records'] = count($merge_all_data);
        // } else {
        //     $data_array = $merge_all_data;
        // }

        // return prepareResult(true, $data_array, [], "Planogram customer listing", $this->success, $pagination);
    }

    public function getmappingfield()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $mappingarray = array("Name", "Start date", "End date", "Customer code", "Status", "Distribution name", "Image", "Image2", "Image3", "Image4");

        return prepareResult(true, $mappingarray, [], "Planogram Mapping Field.", $this->success);
    }

    public function import(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $validator = Validator::make($request->all(), [
            'planogram_file' => 'required|mimes:xlsx,xls,csv,txt'
        ]);

        if ($validator->fails()) {
            $error = $validator->messages()->first();
            return prepareResult(false, [], $error, "Failed to validate region import", $this->unauthorized);
        }
        $errors = array();
        try {

            $map_key_value = $request->map_key_value;
            $map_key_value_array = json_decode($map_key_value, true);
            $file = request()->file('planogram_file')->store('import');
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
            /*$file_data = fopen(storage_path("app/".$file), "r");
            $row_counter = 1;
            while(!feof($file_data)) {
               if($row_counter == 1){
                    echo fgets($file_data). "<br>";
               }
               $row_counter++;
            }
            fclose($file_data);
            */
            //exit;

            $import = new PlanogramImport($request->skipduplicate, $map_key_value_array, $heading_array);
            $import->import($file);

            //print_r($import);
            //exit;
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
                    //echo $failure_key.'--------'.$failure->row().'||';
                    //print_r($failure);
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
                                //'attribute' => $failure->attribute(),//$failure->values(),
                                //'error_result' => $error_result,
                                //'map_key_value_array' => $map_key_value_array,
                            );
                        }
                    }
                }
                $errorrecords = count($errror_array);
            }
            //echo '<pre>';
            //print_r($import->failures());
            //echo '</pre>';
            $errors = $errror_array;
            $result['successrecordscount'] = $succussrecords;
            $result['errorrcount'] = $errorrecords;
            $result['successfileids'] = $successfileids;


            //}
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

                    $customer = CustomerInfo::where('customer_code', $row[3])->first();
                    $distribution = Distribution::where('name', $row[5])->first();
                    $planogram = Planogram::where('name', $row[0])->first();
                    $current_organisation_id = request()->user()->organisation_id;

                    if (is_object($planogram)) {
                        $planogram->name = $row[0];
                        $planogram->start_date  = Carbon::createFromFormat('d/m/Y', $row[1])->format('Y-m-d');
                        $planogram->end_date = Carbon::createFromFormat('d/m/Y', $row[2])->format('Y-m-d');
                        $planogram->status  = $row[4];
                        $planogram->save();

                        $planogram_customer = PlanogramCustomer::where('planogram_id', $planogram->id)->first();
                        $planogram_customer->planogram_id = $planogram->id;
                        $planogram_customer->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                        $planogram_customer->save();

                        $rowCount = 6;
                        for ($i = 0; $i < 4; $i++) {
                            if (isset($row[$rowCount])) {
                                $planogram_image = PlanogramImage::where('planogram_id', $planogram->id)->first();
                                $planogram_image->planogram_id = $planogram->id;
                                $planogram_image->planogram_distribution_id = (is_object($distribution)) ? $distribution->id : 0;
                                $planogram_image->image_string = $row[$rowCount];

                                $planogram_image->save();
                                $rowCount++;
                            }
                        }
                    } else {

                        if (!is_object($customer) or !is_object($distribution)) {
                            if (!is_object($customer)) {
                                return prepareResult(false, [], [], "customer not exist", $this->unauthorized);
                            }
                            if (!is_object($distribution)) {
                                return prepareResult(false, [], [], "distribution not exists", $this->unauthorized);
                            }
                        } else {
                            $planogram = new Planogram;
                            $planogram->organisation_id = $current_organisation_id;
                            $planogram->name = $row[0];
                            $planogram->start_date  = Carbon::createFromFormat('d/m/Y', $row[1])->format('Y-m-d');
                            $planogram->end_date = Carbon::createFromFormat('d/m/Y', $row[2])->format('Y-m-d');
                            $planogram->status  = $row[4];
                            $planogram->save();

                            $planogram_customer = new PlanogramCustomer;
                            $planogram_customer->planogram_id = $planogram->id;
                            $planogram_customer->customer_id = (is_object($customer)) ? $customer->user_id : 0;
                            $planogram_customer->save();

                            $rowCount = 6;
                            for ($i = 0; $i < 4; $i++) {
                                if (isset($row[$rowCount])) {
                                    $planogram_image = new PlanogramImage;
                                    $planogram_image->planogram_id = $planogram->id;
                                    $planogram_image->planogram_distribution_id = (is_object($distribution)) ? $distribution->id : 0;
                                    $planogram_image->image_string = $row[$rowCount];

                                    $planogram_image->save();
                                    $rowCount++;
                                }
                            }
                        }
                    }
                endforeach;
                unlink(storage_path() . '/app/tempimport/' . $importtempfile->FileName);
                \DB::table('import_temp_files')->where('id', $request->successfileids)->delete();
            endif;
            return prepareResult(true, [], [], "Planogram successfully imported", $this->success);
        } else {
            return prepareResult(false, [], [], "Error while import file.", $this->unauthorized);
        }
    }

    

    public function addCustomerPlanogram(Request $request)
    {
        //die('comes');
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "User not authenticate"], "User not authenticate.", $this->unauthorized);
        }
 
           //hyper market customer total 371 
           /* $customer_code = [284,3009,3010,3029,3090,6671,8431,10352,10435,10544,10564,10585,10592,10665,10700,10723,10967,10968,10973,11094,11096,11143,11431,11448,11467,11472,11495,11750,11803,11897,11970,12050,13240,13269,13271,13299,13300,13358,13359,13360,13366,13411,13412,13413,13415,13416,13419,13420,13452,13494,13615,13792,13793,13801,13976,14079,14121,18583,19139,19263,19403,20590,20912,21548,21594,29574,30452,31534,31636,32216,32225,33001,33123,33458,37764,38610,39718,40128,40364,40370,40796,40997,41762,41766,41990,42082,42517,43891,44172,44440,44668,45389,45644,45827,46151,46669,46686,47730,48179,48965,49763,51718,52076,52888,52931,53033,53093,53680,53871,54172,54831,54881,56018,56020,56266,56826,56841,57221,57417,57431,58045,58270,58299,58341,58900,58931,59321,59322,59515,59961,60072,60496,60497,60498,60499,60500,60502,60557,60691,61101,61527,61673,62505,63248,64074,64083,64824,64946,65414,66216,66302,66592,66605,66689,66787,67082,67185,67416,67935,68012,68696,69161,69299,69587,69823,69900,70182,70652,70653,70854,71309,71334,71829,72799,72898,73475,73806,74337,74703,74730,74874,75212,75969,76023,76533,76559,77569,77715,78377,78518,78639,78909,79626,79800,80276,80322,80392,80978,81095,81372,81390,81404,82299,82792,82804,82907,83228,83257,83330,83679,83772,84097,85871,85877,86439,87148,87281,87398,88099,88315,88677,88878,88882,89264,89547,89551,89553,89555,89558,89560,89722,90670,90750,91079,91433,92296,92297,92299,92484,92500,92900,93053,93200,93353,94065,95175,95187,95358,95617,96200,96227,96254,96444,96452,96532,96652,98056,98148,98301,98475,99031,99369,99396,99465,99887,104632,104639,105010,105017,105706,105821,106520,106769,106813,106878,107088,107097,107098,107144,107892,107910,108194,108365,108590,108710,109134,109702,110083,110285,110578,110675,110681,110864,111007,111065,111211,111259,111927,111972,112669,112670,112866,112880,112896,113363,113756,113904,113907,113918,114040,114113,114195,114370,114485,114823,114895,114948,115318,115636,115681,115695,115733,116192,116193,116308,116544,116564,117183,117269,117283,117404,117572,117610,117920,117950,117992,118128,118170,118218,118340,118521,118733,118787,119103,119364,119365,119534,119642,119690,119695,119757,119758,119981,120015,120422,120433,120611,120623,120708,121297,121927,122066,122342,122468,122800,123082,123514,124766,125701,126657,127802]; */

            //large groceries 
           /* $customer_code = [1549,10292,10354,10399,10473,10490,10504,10524,10616,10641,10642,10655,10680,10694,10722,10732,10738,10786,10788,10798,10868,10912,10921,10934,11043,11090,11269,11310,11354,11362,11392,11402,11433,11441,11455,11486,11502,11623,11653,11660,11679,11802,11832,11839,11852,11864,11967,12068,14124,18598,19040,21031,21307,27642,32210,32380,34646,34771,34786,34951,35017,35821,36053,36228,37508,40633,41896,42863,43011,43424,44701,47466,47479,48575,49057,52071,54017,54515,54880,57147,58243,59262,61569,62614,68135,70715,70940,75199,75473,76375,77306,78632,80143,80147,80628,82053,82935,91622,92444,92723,92788,92979,92992,93348,94356,94397,94538,94692,95105,95157,95160,95171,95173,95237,96035,96191,96298,96734,97153,97154,97185,97206,97213,97219,97224,97252,97258,97265,97270,97273,97278,97289,97295,97308,97322,97332,97361,97362,97405,97407,97421,97438,97443,97459,97470,97492,97494,97497,97536,97584,97600,97638,97639,97662,97692,97745,97785,97846,97859,97889,97911,97932,97935,97944,97962,97980,97981,97997,98781,99054,99067,99084,99592,104896,104918,104919,104920,104967,104968,104988,104989,105045,105087,105113,105649,105718,105803,105809,105861,105922,105926,105928,105932,105941,105966,106251,106299,106389,106990,107515,107625,108373,108430,110687,110770,111051,112431,113442,113903,114192,114220,114255,114541,114557,114702,114725,115086,115280,115394,115682,116098,116163,116397,116499,116624,116899,117108,117475,117993,118197,118589,118778,119063,119064,119101,119702,120065,120244,120403,121008,121395,121464,122171,122172,122380,122824,122864,123468,124049,124157,124990,126542,126641,126821,127158,127182,127184,127367,128626,129533,129713,129722]; */

            // mini market 
          /*  $customer_code = [856,8231,10434,10514,10549,10584,10600,10631,10640,10688,10690,10692,10813,10830,10856,10893,10905,10994,11000,11053,11061,11151,11197,11293,11357,11388,11390,11393,11395,11429,11432,11501,11640,11739,11741,11843,11849,11936,11942,11960,11975,11985,12060,12077,13264,13275,13325,13342,13355,13361,13364,13370,13404,13426,13429,13451,13454,13852,15103,15122,15183,15210,15318,15434,15698,15719,15732,15917,15925,15930,16084,16095,16179,16195,16221,16292,16358,16384,16417,16576,16610,16670,16713,16884,17104,17132,17184,17297,17389,17391,17444,17502,17639,17701,17882,18620,19105,19387,19475,20514,20527,20577,20855,21355,21433,28257,29365,29760,30068,30154,31241,31249,31964,31995,32206,32672,33122,33191,33227,33472,33497,33504,33509,33515,33578,33579,33580,33583,33584,33588,33611,33628,33633,33635,33650,33654,33658,33663,33674,33682,33686,33691,33694,33695,33697,33701,33703,33705,33723,33731,33750,33757,33769,33819,33857,33865,33868,33872,33874,33878,33889,33892,33894,33895,33897,33903,33905,33933,33972,34034,34047,34058,34082,34169,34181,34224,34302,34307,34320,34404,34479,34516,34552,34580,34623,34652,34781,34794,34806,34827,34870,34877,34894,34898,34906,34922,34926,34930,34981,35010,35037,35148,35194,35224,35240,35249,35293,35294,35295,35320,35332,35343,35355,35416,35418,35422,35437,35440,35454,35533,35535,35544,35546,35675,35676,35689,35700,35702,35709,35739,35740,35741,35753,35757,35776,35796,35797,35799,35800,35801,35806,35811,35812,35813,35822,35823,35829,35848,35849,35852,35855,35868,35879,35920,35921,35927,35933,35936,35974,35993,36003,36004,36006,36007,36023,36026,36037,36070,36074,36075,36081,36130,36143,36231,36240,36308,36323,37400,37580,38195,38202,38206,38219,38220,38241,38261,38273,38279,38286,38307,38371,38391,38632,38852,38965,39170,39298,39443,39614,40884,40991,40992,40993,40995,40996,40998,41302,41595,41646,41860,41876,41894,42150,42223,42535,42553,42793,43022,43071,43187,43276,43458,43717,44163,44164,44231,44313,44447,44457,44516,44533,44559,44659,44720,44738,44742,44763,44784,45265,45981,46032,46113,46284,46285,46329,46389,46511,47270,47376,47514,47635,47699,47700,47784,47803,47804,47808,48256,48263,48429,48727,48843,48909,48910,49008,49146,49149,49161,49179,49184,49312,49331,49333,49335,49683,49700,49840,49856,50055,50062,50335,50393,50549,50555,50556,50557,50558,50986,51326,51397,52036,52037,52126,52447,52786,52866,52914,53116,53316,53317,53335,53396,53397,53398,53413,53584,53588,53836,53839,53872,54030,54130,54142,54143,54152,54237,54685,54882,54929,55187,55465,55493,55604,55927,56395,56675,56793,56832,56834,56933,56950,57118,57119,57160,57189,57212,57473,57504,57523,57554,57632,57870,58137,58162,58897,58964,59000,59073,59074,59075,59156,59157,59273,59323,59457,59775,59780,60045,60053,60055,60058,60063,60069,60070,60501,60694,60696,60950,60959,61244,61376,61571,62296,62300,62310,62311,62323,62324,62325,62331,62333,62335,62336,62337,62338,62341,62345,62349,62352,62387,62423,62436,62480,62483,62531,62533,62539,62540,62551,62652,62654,62657,62661,62663,62666,62667,62766,62788,63055,63347,63393,63396,63433,63621,63928,63963,64073,64168,64288,64326,64370,64389,64392,64407,64484,64485,64497,64556,64566,64567,64569,64574,64576,64584,64668,64746,64949,65108,65109,65123,65124,65125,65161,65231,65232,65242,65271,65272,65274,65350,65351,65443,65464,65478,65500,65532,65537,65589,65605,65606,65692,65784,65812,65821,65879,65942,65947,65949,66062,66077,66078,66138,66179,66460,66469,66471,66627,66690,66707,66769,66770,66797,66822,66836,66859,66941,66950,66990,66992,66993,67039,67061,67064,67068,67070,67072,67176,67179,67233,67251,67252,67268,67280,67297,67339,67356,67359,67363,67457,67621,67649,67673,67684,67746,67769,67783,67784,67868,68063,68179,68230,68448,68449,68476,68700,68735,68738,68739,68838,68999,69042,69138,69143,69440,69443,69465,69504,69564,69619,69624,69625,69626,69722,69839,69846,69942,70216,70221,70300,70414,70416,70528,70579,70666,70667,70722,70947,70979,71017,71087,71110,71143,71227,71229,71420,71490,71493,71642,71645,71787,72163,72188,72193,72235,72371,72670,72772,73019,73086,73087,73234,73289,73583,73584,73590,73859,73917,73958,74254,74255,74284,74330,74406,74549,74734,74792,75022,75023,75136,75416,75458,75459,75460,75531,75653,75692,75693,75694,75750,75814,76006,76016,76150,76669,76702,76703,76733,76734,76945,77013,77039,77054,77083,77255,77281,77294,77295,77328,77570,77571,77576,77624,77682,77701,77704,77705,77712,77713,77717,77733,77734,77741,77743,77749,77783,77784,77785,77786,77788,77789,77790,77791,77792,77796,77808,77818,77874,77881,77886,77978,78003,78005,78010,78012,78016,78023,78054,78087,78118,78296,78299,78301,78302,78303,78320,78555,78641,78642,78646,78730,78797,78822,78846,78847,78910,78916,78919,78936,78937,78938,78964,79094,79106,79118,79223,79224,79236,79257,79259,79260,79288,79321,79322,79323,79325,79331,79332,79375,79420,79421,79467,79516,79520,79567,79618,79620,79622,79723,79850,79907,79935,79936,79999,80007,80019,80020,80021,80022,80110,80134,80183,80248,80274,80304,80328,80409,80464,80485,80505,80574,80615,80625,80644,80648,80679,80747,80813,80838,80839,80910,81141,81175,81181,81191,81192,81193,81270,81343,81344,81349,81351,81499,81694,81700,81751,81753,81781,81797,81875,81884,81911,81912,81913,81926,82037,82038,82068,82105,82144,82152,82157,82220,82221,82237,82239,82539,82540,82561,82607,82621,82640,82796,82805,82807,82846,82968,83002,83055,83056,83095,83142,83193,83262,83363,83374,83413,83416,83442,83545,83551,83644,83698,83700,83754,83776,83778,83946,83986,83995,84036,84046,84047,84184,84263,84305,84309,84399,84511,84542,84553,84651,84718,84719,84798,84891,84907,84923,85115,85271,85272,85293,85331,85412,85413,85414,85444,85447,85449,85450,85451,85454,85455,85457,85508,85543,85684,85868,85980,85986,86000,86009,86074,86124,86239,86274,86275,86348,86356,86674,86681,86682,86705,86863,86872,86874,86879,86903,86966,87088,87097,87098,87102,87122,87123,87129,87131,87132,87140,87158,87221,87232,87285,87354,87379,87386,87487,87541,87586,87596,87620,87626,87746,87758,87800,87857,87938,87940,87943,87994,88005,88008,88011,88013,88051,88088,88090,88140,88181,88186,88187,88189,88190,88202,88203,88204,88215,88234,88236,88259,88264,88269,88298,88305,88310,88314,88324,88335,88345,88351,88358,88371,88382,88385,88418,88421,88461,88476,88740,88745,88806,88847,88881,88913,88947,89001,89004,89172,89307,89324,89342,89347,89349,89354,89356,89383,89394,89532,89561,89607,89608,89662,89664,89667,89721,89783,89784,89785,89791,89795,89866,89867,89873,89882,89905,89942,89953,89956,89958,89975,89984,89995,90005,90009,90016,90022,90046,90071,90074,90157,90203,90204,90243,90277,90278,90289,90363,90364,90476,90490,90524,90530,90591,90604,90616,90642,90657,90663,90755,90756,90804,90807,90846,90868,90877,90917,90918,90950,90983,90987,91044,91059,91253,91309,91318,91405,91406,91443,91556,91560,91672,91778,91814,91834,91923,91939,91953,92015,92029,92036,92039,92054,92068,92079,92096,92145,92170,92289,92292,92298,92305,92309,92313,92316,92321,92322,92374,92396,92398,92435,92452,92456,92487,92494,92509,92518,92574,92614,92623,92650,92655,92704,92745,92780,92786,92847,92922,92959,92978,93010,93055,93078,93089,93106,93127,93153,93154,93155,93196,93203,93242,93407,93497,93695,93762,93796,93814,93874,94055,94067,94081,94113,94168,94227,94376,94377,94378,94401,94407,94416,94425,94558,94559,94602,94604,94605,94622,94645,94675,94680,94761,94781,94782,94783,94784,94785,94902,94933,94947,94951,94996,94998,95024,95058,95060,95099,95264,95324,95326,95331,95361,95363,95462,95464,95481,95492,95531,95579,95586,95587,95609,95623,95654,95706,95707,95710,95743,95745,95777,95799,95890,95893,95903,95944,96006,96073,96075,96163,96312,96333,96401,96414,96457,96530,96533,96547,96576,96710,96727,96731,96732,96738,96748,96818,96826,96865,96911,96926,96988,97032,98050,98051,98058,98074,98098,98157,98201,98203,98287,98413,98464,98546,98552,98650,98728,98729,98789,98797,98798,98852,98899,98954,98988,98999,99111,99119,99138,99160,99163,99166,99232,99268,99322,99328,99398,99427,99438,99448,99469,99479,99571,99572,99583,99650,99662,99675,99692,99771,99776,99779,99784,99788,99816,99861,99862,99870,99886,99888,99890,104711,104714,104780,104782,104783,104829,104842,104888,104890,104900,104985,105038,105039,105040,105042,105149,105517,105608,105609,105613,105614,105615,105686,105688,105750,105838,105859,105908,106242,106297,106390,106481,106482,106498,106770,106897,106933,106967,106993,106994,107026,107058,107085,107095,107133,107145,107218,107339,107340,107521,107522,107539,107592,107746,107750,107760,107812,107983,108122,108179,108196,108257,108284,108457,108607,108690,108691,108704,108752,108769,108969,109061,109068,109144,109156,109201,109371,109403,109419,109468,109472,109473,109562,109686,109701,109735,109808,109848,109860,109876,109943,109959,109987,110067,110068,110100,110155,110231,110241,110357,110386,110390,110391,110435,110442,110486,110576,110577,110655,110737,110762,110774,110861,110863,110924,110968,110992,111004,111213,111257,111258,111308,111327,111373,111918,111940,111962,111963,111964,111965,112148,112291,112293,112397,112399,112429,112602,112622,112673,112680,112729,112854,112985,113001,113002,113020,113022,113037,113046,113054,113093,113220,113304,113356,113573,113692,113707,113715,113716,113730,113778,113789,113879,113880,113892,113910,113917,113940,114000,114026,114055,114112,114131,114158,114197,114203,114239,114264,114369,114404,114448,114462,114506,114595,114618,114766,114822,114827,114885,114889,114911,114925,114945,115049,115050,115165,115181,115306,115331,115332,115400,115430,115637,115651,115743,115745,115783,115795,115809,115827,115871,115936,115937,115984,115987,115988,115990,115996,115997,116052,116090,116099,116175,116265,116287,116368,116417,116418,116483,116492,116591,116617,116642,116722,116751,116758,116759,116772,116773,116791,116871,116897,116926,116993,117007,117021,117022,117038,117110,117149,117186,117187,117227,117241,117242,117386,117387,117388,117395,117405,117416,117492,117529,117565,117594,117661,117712,117713,117742,117921,117923,117924,117938,117980,118026,118137,118158,118189,118240,118295,118368,118442,118445,118453,118465,118466,118493,118494,118554,118595,118623,118630,118647,118649,118655,118657,118668,118684,118689,118691,118696,118697,118711,118713,118714,118731,118751,118777,118817,118824,118845,119190,119273,119275,119372,119413,119424,119549,119591,119592,119593,119605,119616,119631,119632,119668,119729,119732,119753,119808,119810,119895,119909,119924,119925,119971,119993,119995,120011,120012,120013,120057,120058,120059,120079,120179,120206,120226,120227,120251,120279,120301,120310,120322,120327,120328,120379,120387,120436,120529,120564,120565,120579,120609,120610,120635,120661,120723,120724,120737,120789,120816,120817,120942,120971,120972,121013,121117,121136,121155,121157,121158,121179,121192,121205,121214,121218,121224,121235,121240,121241,121259,121281,121290,121293,121294,121295,121296,121301,121308,121312,121401,121492,121493,121494,121506,121595,121658,121677,121859,121860,121871,121921,121966,121972,121999,122019,122090,122146,122198,122209,122218,122219,122220,122331,122343,122409,122456,122503,122507,122548,122549,122550,122567,122622,122637,122711,122757,122922,122987,123004,123005,123038,123106,123133,123226,123244,123275,123315,123348,123373,123460,123463,123464,123466,123498,123500,123502,123557,123587,123663,123728,123737,123740,123743,123793,123929,123952,123986,124047,124048,124102,124126,124127,124156,124178,124208,124209,124266,124267,124324,124336,124345,124355,124361,124397,124647,124648,124649,124650,124701,124705,124726,124756,124767,124768,124926,124927,125015,125119,125120,125390,125413,125478,125491,125543,125590,125686,125776,125880,125905,125915,125997,126006,126098,126210,126228,126252,126350,126370,126371,126377,126378,126381,126385,126459,126518,126541,126558,126643,126677,126707,126708,126719,126721,126736,126752,126755,126776,126790,126807,126809,126810,126824,126869,126887,126888,126934,126965,127042,127071,127073,127078,127079,127081,127102,127134,127186,127188,127189,127190,127192,127193,127200,127204,127307,127308,127309,127319,127320,127321,127323,127365,127368,127369,127377,127429,127430,127433,127434,127437,127438,127495,127506,127507,127508,127509,127517,127575,127610,127613,127751,127794,127795,127801,127803,127832,127887,127888,127927,127938,127946,127947,127960,127972,127973,127981,127990,128006,128028,128035,128091,128092,128174,128195,128214,128217,128237,128265,128283,128299,128316,128317,128318,128324,128351,128428,128433,128452,128465,128534,128623,128624,128625,128628,128641,128661,128679,128680,128703,128727,128799,128800,128810,128816,128818,128819,128846,128909,128913,128937,128938,128939,128940,128947,129043,129047,129048,129049,129050,129051,129205,129220,129223,129277,129309,129341,129355,129402,129411,129412,129440,129465,129485,129500,129513,129514,129534,129537,129544,129545,129546,129547,129586,129600,129626,129633,129724]; */

        //Small Groceries = SMG
        /* $customer_code = [10566,11063,11065,11067,11068,11069,11072,11073,11075,11078,11082,11091,11095,11100,11120,11134,11148,11593,11595,11597,11925,11926,11927,11929,11930,13018,13245,13281,13282,13283,13284,13285,13286,13287,13288,13290,13291,13292,13294,13301,13302,13303,13305,13312,13313,13314,13315,13380,13421,13434,13437,13438,13439,13441,13496,13497,13498,13726,13775,13870,14008,14037,14145,14881,21343,21380,21529,27639,30381,31967,37253,37254,37255,37262,37263,37264,37265,37266,37267,37271,37272,37273,37274,37275,37276,37277,37278,37293,37296,37297,37326,37328,37356,37357,37358,37360,37361,37362,37467,37616,37760,37777,37778,37779,37780,37781,37782,37783,37890,37921,37922,38076,38351,38616,38777,38994,39046,39125,39222,39223,39609,39873,40147,40148,40363,40521,42023,42354,43000,45032,46328,47108,47646,47738,47997,48418,48944,51024,51911,56060,56105,56418,56419,56964,57244,57281,57650,58113,58201,58419,58907,58909,58910,61362,61363,61364,61365,61367,61378,61379,61381,61382,61383,61384,61385,61386,61387,61388,61389,61390,61391,61392,61393,61394,61395,61396,61397,61398,61399,61400,61401,61402,61403,61404,61405,61406,61411,61412,61413,61414,61415,61416,61417,61418,61419,61420,61421,61422,61423,61424,61425,61426,61427,61428,61429,61430,61436,61437,61438,61439,61440,61441,61442,61443,61444,61445,61446,61447,61448,61449,61450,61451,61452,61453,61454,61500,61868,61869,62261,62685,64184,65390,65391,65392,65393,65394,66211,67009,67010,67018,67019,67020,67021,67022,67201,67231,67232,67647,67870,68388,68941,68942,68944,68945,68947,69217,69218,69219,69220,69221,69222,69223,69225,69226,69228,69229,69230,69231,69232,69233,69234,69235,69734,69735,69816,69817,70217,70295,71427,71453,71631,71720,71863,72379,72705,72716,72771,72908,72909,72910,72925,73012,73013,73014,74840,74975,75126,75295,75462,75463,75549,75550,76129,76149,76183,76257,76349,76353,76604,76924,76925,76926,76951,76952,76954,77076,77081,77082,77171,77187,77188,77189,77190,77233,77271,77289,77462,77484,77488,77577,77595,77596,77639,77806,77914,77916,77917,78066,78155,78166,78436,78977,79199,79373,79777,79790,80109,80300,80334,80428,80658,80734,80743,80837,80992,81142,81154,81506,81957,82006,82008,82009,82055,82057,82058,82059,82062,82064,82160,82806,82901,82913,82914,82966,82991,83494,83663,83664,83665,83824,83825,83826,83827,83828,84075,84799,84864,84946,85054,85073,85470,85480,86847,87250,87287,87288,87486,87488,87533,87534,87578,87579,87580,87581,87582,87583,87584,87585,87713,87828,87965,87966,87967,87996,88009,88082,88132,88133,88240,88268,88342,88343,88428,88485,88787,88959,88960,89049,89097,89098,89158,89854,89855,89952,90162,90362,90903,91070,91156,91248,91330,91366,91776,92220,92251,92295,92585,92611,92618,92760,93875,95325,95421,95788,95900,96531,96737,96866,97034,98076,98329,98509,98715,99320,99591,99594,99644,99812,99882,99883,99884,104637,104640,104691,104692,105071,105541,105611,105612,105943,106680,106783,107450,108588,108865,109294,109295,109296,110306,110307,110536,110710,110716,110717,110718,110719,110720,110721,110781,110782,112667,113043,114381,114382,114384,115319,115556,116113,116194,116452,116453,116508,116522,116523,116526,116528,116529,116530,116616,116660,116661,116662,116663,116771,117006,117100,117106,117107,117761,118289,118467,118699,118700,119195,119611,120122,120161,120294,120405,120924,120925,120926,120927,120928,120943,120944,120945,120955,121001,121002,121015,121016,121258,121518,122149,122156,122469,123441,124430,124993,125545,126019,126020,126907,127275,129680]; */

        //Super market  =  SM
        $customer_code = [3132,10347,10478,10485,10494,10516,10590,10634,10644,10645,10651,10726,10728,10765,10859,10936,11039,11046,11081,11089,11106,11121,11129,11132,11290,11299,11302,11360,11401,11404,11415,11430,11440,11450,11608,11703,11768,11817,11841,11853,11863,11908,11920,12051,12052,12078,13169,13265,13267,13272,13274,13276,13277,13279,13280,13297,13341,13343,13344,13351,13352,13353,13356,13377,13401,13414,13417,13428,13442,13446,13449,13456,13528,13947,13970,14123,16065,17392,17780,19503,20633,20887,21025,21163,21235,21629,30372,31635,31701,31770,32699,32876,33756,33758,34220,34233,37014,37481,38287,38289,38940,39050,39452,40164,40885,41301,41874,42382,42755,42987,43069,43273,43283,43540,43567,43761,44394,44700,44866,44943,45041,45211,45381,45382,47511,47590,47698,48001,48888,48936,48945,48952,49379,49671,49766,49769,50161,51077,52033,52034,52137,52194,52278,52456,52561,53080,53496,54149,54150,54151,54206,54702,54848,55622,55624,55641,55671,56230,56410,56484,56519,56788,56794,57170,57485,57911,57912,58114,58115,58158,58757,59142,59158,59245,59253,59256,59257,59478,59634,59635,59759,60067,60258,60320,60549,60659,61098,61139,61256,61257,61258,61285,61464,61855,61971,62343,62344,62347,62613,62935,63082,63308,63389,63391,63403,63581,63927,64187,64948,65286,65982,66196,66407,66409,66588,66590,66591,66789,67062,67076,67077,67180,67237,67340,67358,67360,67361,67362,67364,67365,67366,67367,67368,67369,67370,67417,67427,67445,67448,67612,68049,68056,68057,68170,68325,68342,68393,68409,68509,68513,68530,68551,68669,68809,68839,69068,69082,69182,69456,69477,69822,69897,69898,70419,70716,71197,71267,71291,71310,71634,71771,71792,71793,71795,72171,72336,72358,72726,73175,73385,73586,74069,74150,74402,74408,74566,74809,74821,75231,75345,75378,75474,75476,75506,75582,75597,75598,75681,75794,76076,76131,76207,76306,76331,76527,76919,76948,77075,77134,77201,77231,77288,77313,77314,77663,77683,77759,77825,77911,78078,78287,78288,78290,78554,78640,78661,78696,78799,78943,79248,79254,79280,79290,79383,79384,79415,79422,79423,79770,79791,79849,80406,80491,80492,80632,80720,80739,80989,81011,81080,81168,81196,81336,81337,81475,81682,81812,81824,81969,81985,82100,82941,83050,83099,83137,83140,83174,83467,83468,83517,83534,83674,83759,83888,84014,84065,84154,84187,84418,84463,84648,84704,84734,84796,84908,85006,85113,85124,85147,85232,85503,85505,85757,85857,85952,85998,86066,86114,86175,86285,86296,86352,86488,86561,86878,87124,87406,87432,87506,87655,87771,87969,88053,88101,88108,88213,88217,88405,88587,88588,88771,88884,89271,89321,89552,89554,89556,89557,89559,89616,89792,89885,89910,90043,90096,90617,90620,90624,90693,90836,90837,90936,90944,90945,91050,91078,91102,91177,91182,91185,91659,91660,91680,91810,91832,91833,91848,91849,91975,92050,92071,92142,92210,92430,92431,92491,92501,92661,92822,92823,92824,92825,92838,92875,92958,92988,93009,93011,93054,93083,93152,93246,93328,93333,93334,93447,93620,93706,93756,94002,94036,94040,94070,94084,94134,94235,94261,94338,94349,94358,94379,94380,94454,94735,94760,94762,94786,94796,94828,94901,94914,95025,95037,95059,95082,95100,95186,95482,95505,95626,95649,95812,95862,95915,96143,96151,96164,96183,96283,96291,96389,96513,96544,96548,96560,96820,96858,96910,96982,97035,98300,98355,98415,98474,98619,98639,98645,98896,98897,98981,98993,98998,99022,99080,99190,99205,99243,99292,99307,99308,99359,99361,99383,99446,99590,99612,99684,99691,99787,99813,99814,99838,99876,99877,99881,99889,104649,104693,104702,104713,104723,104758,104772,104811,104862,104887,104938,104981,105029,105115,105122,105526,105601,105630,105657,105677,105678,105687,105698,105711,105725,105748,105805,105832,105841,105895,105906,106359,106493,106496,106583,106711,106724,106833,106934,107057,107086,107087,107096,107154,107326,107358,107487,107553,107558,107626,107782,107891,107924,107984,108062,108081,108216,108376,108709,108892,108894,108895,108982,109305,109396,109435,109440,109471,109891,109892,109893,110001,110002,110211,110234,110280,110360,110488,110489,110490,110492,110542,110579,110580,110713,110769,110904,110905,110922,110993,111087,111088,111200,111307,111355,111389,111401,111928,111973,111985,112176,112292,112398,112420,112427,112428,112430,112469,112501,112505,112506,112541,112617,112623,112645,112686,112687,112755,112833,112855,113016,113257,113303,113339,113364,113433,113484,113519,113544,113612,113754,113777,113920,114015,114027,114028,114029,114030,114031,114193,114194,114196,114240,114241,114254,114463,114502,114511,114553,114569,114633,114691,114703,114704,114758,114759,114760,114912,114923,114941,115075,115213,115229,115320,115336,115395,115538,115652,115711,115768,115789,115840,115855,115863,115872,115879,115882,116013,116044,116076,116077,116078,116160,116201,116306,116367,116513,116569,116622,116659,116670,116676,116677,116704,116737,116760,116870,116949,116966,116994,117025,117036,117037,117039,117040,117098,117099,117159,117160,117184,117185,117191,117204,117235,117289,117337,117406,117524,117571,117573,117629,117763,117910,117943,117979,117994,118013,118075,118136,118138,118162,118167,118219,118238,118290,118363,118443,118468,118612,118613,118614,118667,118704,118712,118734,118760,118843,118999,119000,119009,119051,119076,119194,119247,119274,119371,119453,119531,119533,119594,119604,119617,119633,119634,119669,119674,119693,119694,119821,119974,119994,120014,120028,120048,120086,120103,120104,120121,120150,120162,120295,120296,120297,120302,120321,120435,120490,120561,120567,120578,120580,120662,120709,120895,120923,120941,120968,120969,120970,121040,121233,121234,121239,121317,121318,121346,121436,121474,121491,121651,121676,121922,121964,121970,122011,122013,122049,122128,122129,122157,122210,122216,122217,122279,122429,122455,122467,122505,122636,122705,122753,122841,122865,122873,122874,122893,122895,122921,122977,123103,123165,123191,123192,123216,123217,123218,123273,123274,123443,123621,123719,123720,123739,123763,123764,123976,123987,124003,124044,124050,124344,124381,124383,124598,124730,124977,125038,125225,125231,125479,125480,125559,125724,125870,125884,125908,125909,125931,125932,125998,126057,126125,126132,126268,126368,126369,126372,126373,126375,126376,126429,126460,126472,126516,126543,126544,126545,126620,126729,126751,126753,126754,126769,126866,126867,126874,126906,126968,127075,127077,127091,127185,127194,127195,127197,127198,127199,127201,127300,127366,127564,127577,127631,127635,127666,127667,127760,127761,127806,127821,128097,128098,128213,128296,128323,128347,128672,128701,128765,128794,128840,128850,129052,129464,129511,129512];
        
         if($request->data){ 
            // foreach ($request->data as $route) {
            //     //pre($route);
            //     $getInfoSTP = CustomerInfo::select('user_id')->where('customer_code', $route['Customer'])->first();
                
            //     if (is_object($getInfoSTP)) {
            //         $pdp_promotion_item = new PlanogramCustomer;

            //             $pc = new PlanogramCustomer;
            //             $pc->planogram_id = '19';
            //             $pc->customer_id = $getInfoSTP->user_id;
            //             $pc->save();


                            
            //                     $pd = new PlanogramDistribution;
            //                     $pd->planogram_id = '19';
            //                     $pd->distribution_id ='40';
            //                     $pd->customer_id = $getInfoSTP->user_id;
            //                     $pd->planogram_customer_id = $pc->id;
            //                     $pd->save();

                                
            //                                 $pi = new PlanogramImage;
            //                                 //$image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                                 $pi->planogram_id = '19';
            //                                 $pi->planogram_distribution_id = $pd->id;
            //                                 $pi->image_string           = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/84526067421480-1691178326.jpeg';
            //                                 $pi->save();


            //                     $pd = new PlanogramDistribution;
            //                     $pd->planogram_id = '19';
            //                     $pd->distribution_id ='57';
            //                     $pd->customer_id = $getInfoSTP->user_id;
            //                     $pd->planogram_customer_id = $pc->id;
            //                     $pd->save();

                                
            //                                 $pi = new PlanogramImage;
            //                                 //$image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                                 $pi->planogram_id = '19';
            //                                 $pi->planogram_distribution_id = $pd->id;
            //                                 $pi->image_string           = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/84526067421480-1691178326.jpeg';
            //                                 $pi->save();

            //                     $pd = new PlanogramDistribution;
            //                     $pd->planogram_id = '19';
            //                     $pd->distribution_id ='58';
            //                     $pd->customer_id = $getInfoSTP->user_id;
            //                     $pd->planogram_customer_id = $pc->id;
            //                     $pd->save();

                                
            //                                 $pi = new PlanogramImage;
            //                                 //$image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                                 $pi->planogram_id = '19';
            //                                 $pi->planogram_distribution_id = $pd->id;
            //                                 $pi->image_string           = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/84526067421480-1691178326.jpeg';
            //                                 $pi->save();

            //                                 $pd = new PlanogramDistribution;
            //                     $pd->planogram_id = '19';
            //                     $pd->distribution_id ='59';
            //                     $pd->customer_id = $getInfoSTP->user_id;
            //                     $pd->planogram_customer_id = $pc->id;
            //                     $pd->save();

                                
            //                                 $pi = new PlanogramImage;
            //                                 //$image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                                 $pi->planogram_id = '19';
            //                                 $pi->planogram_distribution_id = $pd->id;
            //                                 $pi->image_string           = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/84526067421480-1691178326.jpeg';
            //                                 $pi->save();

            //                                 $pd = new PlanogramDistribution;
            //                     $pd->planogram_id = '19';
            //                     $pd->distribution_id ='60';
            //                     $pd->customer_id = $getInfoSTP->user_id;
            //                     $pd->planogram_customer_id = $pc->id;
            //                     $pd->save();

                                
            //                                 $pi = new PlanogramImage;
            //                                 //$image_string = saveImage(Str::slug(rand(100000000000, 99999999999999)), $image, 'planogram-image');
            //                                 $pi->planogram_id = '19';
            //                                 $pi->planogram_distribution_id = $pd->id;
            //                                 $pi->image_string           = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/84526067421480-1691178326.jpeg';
            //                                 $pi->save();

            //     }
            // }
         }else{
            //optimized code
            foreach ($customer_code as $cs_code) {
                $getInfoSTP = CustomerInfo::select('user_id')->where('customer_code', $cs_code)->first();
            
                if (is_object($getInfoSTP)) {
                    $customer_id = $getInfoSTP->user_id;
                    //$distribution_ids = [61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74];
            
                    $planogramData = [ 
                                 
                            ['id' => '143', 'distribution_id' => '203','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Abaya Care.png'],
                                    
                            ['id' => '156', 'distribution_id' => '216','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SMG DAC Base _ Gold.png'],
                                    
                            ['id' => '157', 'distribution_id' => '217','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM DAC Rim Blocks.png'],
                                    
                            ['id' => '144', 'distribution_id' => '204','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Fa - Deodorants Option 1.png'], 
                                    
                            ['id' => '145', 'distribution_id' => '205','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Fa - Deodorants Option 2.png'],
                                    
                            ['id' => '146', 'distribution_id' => '206','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SMG Pert - Shampoo.png'],
                                    
                            ['id' => '147', 'distribution_id' => '207','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Palette ICC Option 1.png'],
                                    
                            ['id' => '148', 'distribution_id' => '208','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Palette ICC Option 2.png'],
                                    
                            ['id' => '149', 'distribution_id' => '209','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Pallete - PNC Option 1.png'],
                                    
                            ['id' => '150', 'distribution_id' => '210','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Pallete - PNC Option 2.png'],
                                    
                            ['id' => '154', 'distribution_id' => '214','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Persil Liquids Option 1.png'],
                            
                            ['id' => '155', 'distribution_id' => '215','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Persil Liquids Option 2.png'],
                            
                            ['id' => '153', 'distribution_id' => '213','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Pert - Shampoo Option 1.png'],
                            
                            ['id' => '151', 'distribution_id' => '211','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Styling - GOT2BTAFTSYOSS.png'],
                            
                            ['id' => '162', 'distribution_id' => '222','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Body Lotion Planogram.png'],
                            
                            ['id' => '165', 'distribution_id' => '225','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley EDT Men Women Planogram.png'],
                            
                            ['id' => '161', 'distribution_id' => '221','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Hair Cream Planogram.png'],
                            
                            ['id' => '164', 'distribution_id' => '224','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Planogram Men Deo Roll On.png'],
                            
                            
                            ['id' => '163', 'distribution_id' => '223','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Planogram Women Deo Roll On.png'],
                            
                            ['id' => '160', 'distribution_id' => '223','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Soap Planogram.png'],
                            
                            ['id' => '158', 'distribution_id' => '218','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Talc Planogram Option 1.png'],
                            
                            ['id' => '159', 'distribution_id' => '219','image' => 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/SM Yardley Talc Planogram Option 2.png'],
                                     
                        //https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketNeutrogena.png
                    ];
            
                    foreach ($planogramData as $planogramItem) {
                        $planogram_id = $planogramItem['id'];
                        $image_string = $planogramItem['image'];
                        $distribution_id = $planogramItem['distribution_id'];
            
                        $pc = new PlanogramCustomer;
                        $pc->planogram_id = $planogram_id;
                        $pc->customer_id = $customer_id;
                        $pc->save();
            
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
            
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                }
            }
            

            return prepareResult(true, [], [], "Planogram image and customers added successfully", $this->success);

            die("end of code");
            /// below code is also working but not optimized and have 1 issue 
            /* foreach ($customer_code as $cs_code) {
                //pre($route);
                $getInfoSTP = CustomerInfo::select('user_id')->where('customer_code', $cs_code)->first();
                
                //Name and distribution ID 
                //Hypermarket Adult Face&Body = 61, Hypermarket Baby  = 62
                //code optimized 02sep ryz
 
                //20
                if (is_object($getInfoSTP)) {
                    $planogram_id = '20';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = ''; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 

                //21
                if (is_object($getInfoSTP)) {
                    $planogram_id = '21';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketBaby.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //22
                if (is_object($getInfoSTP)) {
                    $planogram_id = '22';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketBabyWipes.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //23
                if (is_object($getInfoSTP)) {
                    $planogram_id = '23';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketBodywash.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //24
                if (is_object($getInfoSTP)) {
                    $planogram_id = '24';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketBodywashAntibac.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //25
                if (is_object($getInfoSTP)) {
                    $planogram_id = '25';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketCarefree.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //26
                if (is_object($getInfoSTP)) {
                    $planogram_id = '26';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = ''; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //27
                if (is_object($getInfoSTP)) {
                    $planogram_id = '27';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketClear_Clear2.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //28
                if (is_object($getInfoSTP)) {
                    $planogram_id = '28';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketClean_Clear1.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //29
                if (is_object($getInfoSTP)) {
                    $planogram_id = '29';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketHandwashAntibac.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //30
                if (is_object($getInfoSTP)) {
                    $planogram_id = '30';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = ''; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //31
                if (is_object($getInfoSTP)) {
                    $planogram_id = '31';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketKids.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //32
                if (is_object($getInfoSTP)) {
                    $planogram_id = '32';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketListerine.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                //33
                if (is_object($getInfoSTP)) {
                    $planogram_id = '33';
                    $customer_id = $getInfoSTP->user_id;
                    $image_string = 'https://devmobiato.nfpc.net/merchandising/public/uploads/planogram-image/HypermarketListerine.png'; 
                    $distribution_ids = [61,62,63,64,65,66,67,68,69,70,71,72,73,74];  
                
                    $pc = new PlanogramCustomer;
                    $pc->planogram_id = $planogram_id;
                    $pc->customer_id = $customer_id;
                    $pc->save();
                    foreach ($distribution_ids as $distribution_id) {
                
                        $pd = new PlanogramDistribution;
                        $pd->planogram_id = $planogram_id;
                        $pd->distribution_id = $distribution_id;
                        $pd->customer_id = $customer_id;
                        $pd->planogram_customer_id = $pc->id;
                        $pd->save();
                
                        $pi = new PlanogramImage;
                        $pi->planogram_id = $planogram_id;
                        $pi->planogram_distribution_id = $pd->id;
                        $pi->image_string = $image_string;
                        $pi->save();
                    }
                } 
                 
            } */
         }


        return prepareResult(true, [], [], "Record Added successfully", $this->success);

    }

    public function testList(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $planogram_query = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status')->with(
                'planogramCustomer:id,planogram_id,customer_id',
                'planogramCustomer.customer:id,firstname,lastname',
                'planogramCustomer.customer.customerInfo:id,user_id,customer_code',
                'planogramCustomer.planogramDistribution',
                'planogramCustomer.planogramDistribution.distribution:id,name',
                'planogramCustomer.planogramDistribution.planogramImages'

            );

        if ($request->name) {
            $planogram_query->where('name', $request->name);
        }

        if ($request->start_date) {
            $planogram_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $planogram_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }
        $perpage = $request->page_size ? $request->page_size : 10;
        $planogram = $planogram_query->orderBy('id', 'desc')
            ->paginate($perpage);
          //  dd($planogram);
        $planogram_array = array();
        if (is_object($planogram)) {
            foreach ($planogram as $key => $planogram1) {
                $planogram_array[] = $planogram[$key];
            }
        }
        $data_array = array();
        foreach ($planogram as $key => $data) {
            if (isset($planogram_array[$key])) {
                $data_array[] = $planogram_array[$key];
            }
        }

        
        // $page = (isset($request->page)) ? $request->page : '';
        // $limit = (isset($request->page_size)) ? $request->page_size : '';
        // $pagination = array();
        // if ($page != '' && $limit != '') {
        //     $offset = ($page - 1) * $limit;
        //     for ($i = 0; $i < $limit; $i++) {
        //         if (isset($planogram_array[$offset])) {
        //             $data_array[] = $planogram_array[$offset];
        //         }
        //         $offset++;
        //     }

        //     $pagination['total_pages'] = ceil(count($planogram_array) / $limit);
        //     $pagination['current_page'] = (int)$page;
        //     $pagination['total_records'] = count($planogram_array);
        // } else {
        //     $data_array = $planogram_array;
        // }

        $pagination['total_pages'] = $planogram->lastPage();
        $pagination['current_page'] = $planogram->currentPage();
        $pagination['total_records'] = $planogram->total();

        return prepareResult(true, $data_array, [], "Planogram listing", $this->success, $pagination);

        // return prepareResult(true, $planogram, [], "Planogram listing", $this->success);
    }

    public function planogramList(Request $request)
    { 
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $planogram_query = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status');

        if ($request->name) {
            $planogram_query->where('name', $request->name);
        }

        if ($request->start_date) {
            $planogram_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $planogram_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }
        $perpage = $request->page_size ? $request->page_size : 10;
        $planogram = $planogram_query->orderBy('id', 'desc')
            ->paginate($perpage);

        $pagination['total_pages'] = $planogram->lastPage();
        $pagination['current_page'] = $planogram->currentPage();
        $pagination['total_records'] = $planogram->total();

        return prepareResult(true, $planogram, [], "Planogram listing", $this->success, $pagination);

        // return prepareResult(true, $planogram, [], "Planogram listing", $this->success);
    }

    public function planogramCustomerListDetails(Request $request)
    {  
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $planogram_query = Planogram::select('id', 'uuid', 'organisation_id', 'name', 'start_date', 'end_date', 'status')
            ->with(
                'planogramCustomer:id,planogram_id,customer_id',
                'planogramCustomer.customer:id,firstname,lastname',
                'planogramCustomer.customer.customerInfo:id,user_id,customer_code',
                'planogramCustomer.planogramDistribution',
                'planogramCustomer.planogramDistribution.distribution:id,name',
                'planogramCustomer.planogramDistribution.planogramImages'

            )->where('id', $request->id);

        if ($request->name) {
            $planogram_query->where('name', $request->name);
        }

        if ($request->start_date) {
            $planogram_query->where('start_date', date('Y-m-d', strtotime($request->start_date)));
        }

        if ($request->end_date) {
            $planogram_query->where('end_date', date('Y-m-d', strtotime($request->end_date)));
        }
        $perpage = $request->page_size ? $request->page_size : 10;
        $planogram = $planogram_query->orderBy('id', 'desc')
            ->paginate($perpage);

        $pagination['total_pages'] = $planogram->lastPage();
        $pagination['current_page'] = $planogram->currentPage();
        $pagination['total_records'] = $planogram->total();

        return prepareResult(true, $planogram, [], "Planogram listing", $this->success, $pagination);

        // return prepareResult(true, $planogram, [], "Planogram listing", $this->success);
    }


    // code for al batha 
    public function addCustomerPlanogram_albatha(Request $request)
    { 
        //GENERAL TRADE A customers
        //$customer_code = [10544 ,10352 ,112880 ,96254 ,42082 ,83257 ,40364 ,40370 ,126132 ,13452 ,118521 ,109134 ,81372 ,66302 ,13358 ,13359 ,81812 ,13360 ,75212 ,11750 ,127751 ,95175 ,84097 ,82907 ,108590 ,11803 ,117269 ,61101];
        
        // GTB TRADE B 
        $customer_code = [88405,121474 ,10723 ,122217 ,10765 ,85505 ,95812 ,53680 ,12078 ,44700 ,10435 ,54206 ,71793 ,10585 ,52456 ,92500 ,47803 ,10634 ,104639 ,52034 ,99876,82804,21163 ,54702 ,48945 ,69082,74703,90945 ,116367 ,87281 ,88878,130237 ,127199 ,127365 ,127195 ,127198 ,127366 ,127197 ,105841 ,67935,80632 ,60258,61258 ,99022,95915 ,122864 ,112428 ,119669,115733,122128,119821,114941 ,126125,11299 ,68509,78518 ,71334 ,91079 ,85877 ,112669 ,92707 ,55622 ,63928 ,47511 ,44866 ,11401 ,114429 ,11430 ,11431,92210,76306 ,122977 ,130238 ,127564,126906,66407 ,11703,72799,99080 ,93083 ,11768 ,116622,66689 ,98148 ,98148,91810 ,85857 ,88882 ,123082 ,45211 ,111087 ,80978 ,13428 ,119103,41762 ,78799,81824 ,94338 ,41990,121297 ,19139,55624 ,72898];
 
         if($request->data){  
         }else{
            //optimized code
            foreach ($customer_code as $cs_code) {
                $getInfoSTP = CustomerInfo::select('user_id')->where('customer_code', $cs_code)->first();
            
                if (is_object($getInfoSTP)) {
                    $customer_id = $getInfoSTP->user_id;
                    //$distribution_ids = [61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74];
            
                    $planogramItem = ['id' => '170', 'distribution_id' => '208','image' => 'https://sfa.gulfinternational.com/dev/public/uploads/planogram-image/Toiletery Modern Trade A'];
                               

                    $pc = PlanogramCustomer::create(['planogram_id' => $planogramItem['id'], 'customer_id' => $customer_id]);

                    $pd = PlanogramDistribution::create(['planogram_id' => $planogramItem['id'], 'distribution_id' => $planogramItem['distribution_id'], 'customer_id' => $customer_id, 'planogram_customer_id' => $pc->id]);

                    PlanogramImage::create(['planogram_id' => $planogramItem['id'], 'planogram_distribution_id' => $pd->id, 'image_string' => $planogramItem['image']]); 
                    
                }
            } 
            return prepareResult(true, [], [], "Planogram image and customers added successfully", $this->success); 
             
         }


        return prepareResult(true, [], [], "Record Added successfully", $this->success);

    }

}
