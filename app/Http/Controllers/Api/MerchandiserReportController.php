<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Exports\MerchandiserCompetitorInfoExport;
use App\Exports\MerchandiserCustomerActivityExport;
use App\Exports\MerchandiserCustomerExport;
use App\Exports\MerchandiserCustomerVisitExport;
use App\Exports\MerchandiserMultipleSheets;
use App\Exports\MerchandiserOrderReturnExport;
use App\Exports\MerchandiserOrderSummaryExport;
use App\Exports\MerchandiserShareOfShelfExport;
use App\Exports\MerchandiserStockAvailabilityExport;
use App\Exports\MSLByCustomerExport;
use App\Exports\MSLBySalesmanExport;
use App\Exports\SalesmanLoginLogExport;
use App\Http\Controllers\Controller;
use App\Model\Item;
use App\Model\AssetTrackingPost;
use App\Model\AssignInventoryPost;
use App\Model\CampaignPicture;
use App\Model\CompetitorInfo;
use App\Model\ComplaintFeedback;
use App\Model\CreditNote;
use App\Model\CustomerActivity;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerVisit;
use App\Model\Distribution;
use App\Model\DistributionPostImage;
use App\Model\DistributionStock;
use App\Model\Order;
use App\Model\PlanogramPost;
use App\Model\SalesmanInfo;
use App\Model\ShareOfShelf;
use App\Model\Survey;
use App\Model\SurveyQuestionAnswer;
use App\Model\Trip;
use App\Model\TimeSheetReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use URL;
use Illuminate\Support\Facades\DB;
use App\Model\JourneyPlan;
use App\Model\JourneyPlanDay;
use App\Model\JourneyPlanCustomer;
use App\Model\JourneyPlanWeek;
use App\Model\SalesmanLoginLog;
use DateTime;
use App\Exports\UserLoginLogExport;
use App\Model\Channel;
use App\Model\DistributionModelStock;
use App\Model\DistributionModelStockDetails;
use App\Model\UserLoginLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use App\User;

class MerchandiserReportController extends Controller
{
    private $type = "merchandising";

    public function reports(Request $request)
    {
        // $this->type = config('app.current_domain');
        // $this->type = "merchandising";

        if ($this->type = "merchandising") {
            $salesman_type = 2;
        } else {
            $salesman_type = 3;
        }

        $merchandiser_ids = $this->getMerchandiser($salesman_type);

        if ($request->module == "competitor-info") {
            return $this->competitorInfo($request, $merchandiser_ids);
        }

        if ($request->module == "new-customer") {
            return $this->newCustomer($request, $merchandiser_ids);
        }

        if ($request->module == "order-summary") {
            return $this->orderSumamry($request, $merchandiser_ids);
        }

        if ($request->module == "close-visit") {
            return $this->closeVisit($request, $merchandiser_ids);
        }

        if ($request->module == "order-return") {
            return $this->orderReturn($request, $merchandiser_ids);
        }

        if ($request->module == "visit-summary") {
            return $this->visitSummary($request, $merchandiser_ids);
        }

        if ($request->module == "photos") {
            return $this->photos($request, $merchandiser_ids);
        }

        if ($request->module == "sos") {
            return $this->sos($request, $merchandiser_ids);
        }

        if ($request->module == "task-answer") {
            return $this->taskAnswer($request, $merchandiser_ids);
        }

        if ($request->module == "task-summary") {
            return $this->taskSummary($request, $merchandiser_ids);
        }

        if ($request->module == "planogram") {
            return $this->planogram($request, $merchandiser_ids);
        }

        if ($request->module == "stock-availability") {
            return $this->stockAvailability($request, $merchandiser_ids);
        }

        if ($request->module == "time-sheet") {
            //return $this->timeSheet($request, $merchandiser_ids);
            return $this->timeSheetReport($request, $merchandiser_ids);
        }

        if ($request->module == "store-summary") {
            return $this->storeSummary($request, $merchandiser_ids);
        }

        if ($request->module == "route-visit") {
            return $this->routeVisit($request, $merchandiser_ids);
        }

        if ($request->module == "salesman-login-log") {
            return $this->salesmanLoginLog($request);
        }

        if ($request->module == "attendance") {
            return $this->attendance($request);
        }

        if ($request->module == "user-login-log") {
            return $this->userLoginLog($request);
        }

        if ($request->module == "msl-by-customer") {

            if (!is_array($request->customer_id)) {
                return prepareResult(false, [], ['error' => "Please select customer id."], "Please select customer id.", $this->unprocessableEntity);
            }

            if (sizeof($request->customer_id) < 1) {
                return prepareResult(false, [], ['error' => "Please select customer id."], "Please select customer id.", $this->unprocessableEntity);
            }

            //return $this->MSLByCustomer($request);
            return $this->maxMSLByCustomer($request);
        }

        if ($request->module == "msl-by-channel") {

            if (!is_array($request->channel_id)) {
                return prepareResult(false, [], ['error' => "Please select channel id."], "Please select channel id.", $this->unprocessableEntity);
            }

            if (sizeof($request->channel_id) < 1) {
                return prepareResult(false, [], ['error' => "Please select channel id."], "Please select channel id.", $this->unprocessableEntity);
            }

            return $this->MSLByChannel($request);
        }

        if ($request->module == "msl-by-salesman") {
            //$data = $this->MSLBySalesman($request);
            //$data = $this->maxMSLBySalesman($request);
            $data = $this->testMaxMSLBySalesman($request);
            if (!$data) {
                return prepareResult(false, [], ['error' => "Please select salesman id."], "Please select salesman id.", $this->unprocessableEntity);
            }
            return $data;
        }

        if ($request->module == "msl-by-customer-detail") {
            //$data = $this->MSLByCustomerDetail($request);
            $data = $this->newMSLByCustomerDetail($request);
            if (!$data) {
                return prepareResult(false, [], ['error' => "Please select customer id."], "Please select customer id.", $this->unprocessableEntity);
            }
            return $data;
        }

        if ($request->module == "msl-by-salesman-detail") {
            //$data = $this->MSLBySalesmanDetail($request);
            $data = $this->newMSLBySalesmanDetail($request);
            if (!$data) {
                return prepareResult(false, [], ['error' => "Please select salesman id."], "Please select salesman id.", $this->unprocessableEntity);
            }
            return $data;
        }

        if ($request->module == "msl-by-channel-detail") {
            $data = $this->MSLByChannelDetail($request);
            if (!$data) {
                return prepareResult(false, [], ['error' => "Please select channel id."], "Please select channel id.", $this->unprocessableEntity);
            }
            return $data;
        }
    }

    private function userLoginLog($request)
    {
        $columns = [
            'Date', 'User Name', 'IP Address', 'Browser'
        ];

        $user_login_log_query = UserLoginLog::select('created_at', 'user_id', 'ip', 'browser')
            ->with(
                'user:id,firstname,lastname'
            );

        if ($request->start_date && !$request->end_date) {

            $user_login_log_query->whereDate('created_at', $request->start_date);
        }

        if (!$request->start_date && $request->end_date) {
            $end_date = Carbon::parse($request->end_date)->format('Y-m-d');
            $user_login_log_query->whereDate('created_at', $end_date);
        }

        if ($request->start_date && !$request->end_date) {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $user_login_log_query->whereDate('created_at', $start_date);
        }

        if ($request->start_date && $request->end_date) {
            $sdate = Carbon::parse($request->start_date)->format('Y-m-d');
            $edate = Carbon::parse($request->end_date)->subDays()->format('Y-m-d');
            if ($sdate == $edate) {
                $user_login_log_query->whereDate('created_at', $sdate);
            } else {
                $user_login_log_query->whereBetween('created_at', [$sdate, $edate]);
            }
        }

        $user_login_log = $user_login_log_query->get();

        $userLogCollection = new Collection();

        foreach ($user_login_log as $key => $value) {
            $userLogCollection->push((object)[
                'date' => $value->created_at,
                'user_name' => $value->user->firstname . ' ' . $value->user->lastname,
                'ip' => $value->ip,
                'browser' => $value->browser
            ]);
        }

        if ($request->export == 0) {
            return prepareResult(true, $userLogCollection, [], "User login log listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_user_login_log.' . $request->export_type;

            Excel::store(new UserLoginLogExport($userLogCollection), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function timeSheet($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Visit Date',
            'Merchandiser Code',
            'Merchandiser Name',
            'Check In Time',
            'Check Out Time',
            'Total Time',
            'Start Of Work',
            'End Of Work',
            'Customer Code',
            'Customer Name',
            'Total Time',
            'Start Time',
            'End Time',
            'Activity Name',
            'Activity Action',
            'Total Time',
            'Start Time',
            'End Time'
        ];

        $all_salesman = getSalesman();

        $trip_query = Trip::select('id', 'route_id', 'salesman_id', 'trip_start', 'trip_start_date', 'trip_start_time', 'trip_end', 'trip_end_date', 'trip_end_time')
            ->with(
                'salesman:id,firstname,lastname',
                'salesman.salesmanInfo:id,user_id,salesman_code',
                'customerVisit',
                'customerVisit.customerActivity'
            )
            ->whereHas('customerVisit', function ($q) {
                $q->where('shop_status', 'open')
                    ->whereNull('reason');
            })
            ->whereHas('customerVisit.customerActivity', function ($q) {
                $q->whereNotNull('id');
            });

        if (count($all_salesman)) {
            $trip_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $trip = $trip_query->whereDate('trip_start', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $trip = $trip_query->whereBetween('trip_start', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $trip = $trip_query->whereDate('trip_start', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $export_report  = array();
        $trip_data      = array();
        if (count($trip)) {
            foreach ($trip as $key => $t) {
                $customer_visits = CustomerVisit::with(
                    'customer:id,firstname,lastname',
                    'customer.customerInfo:id,user_id,customer_code',
                    'customerActivity:id,customer_visit_id,customer_id,activity_name,activity_action,start_time,end_time,total_time',
                    'customerActivity.customer:id,firstname,lastname'
                )
                    ->where('trip_id', $t->id)
                    ->where('shop_status', 'open')
                    ->whereNull('reason')
                    ->whereHas('customerActivity', function ($q) {
                        $q->whereNotNull('id');
                    })
                    ->orderBy('id', 'desc')
                    ->get();

                $trip_data[$t->id]                    = new \stdClass();
                $trip_data[$t->id]->tripid            = $t->id;
                $trip_data[$t->id]->tripStartDate   = $t->trip_start_date;
                $trip_data[$t->id]->merchandiserCode  = (is_object($t->salesman->salesmanInfo)) ? $t->salesman->salesmanInfo->salesman_code : "";
                $trip_data[$t->id]->merchandiserName  = $t->salesman->getName();
                $trip_data[$t->id]->check_in_time     = $t->trip_start_time;
                $trip_data[$t->id]->check_out_time    = $t->trip_end_time;
                $trip_data[$t->id]->totalTripTime     = timeCalculate($t->trip_start_time, $t->trip_end_time);

                if (count($customer_visits) >= 1) {
                    $trip[$key]->tripid = $t->id;
                    $trip[$key]->tripStartDate = $t->trip_start_date;
                    $trip[$key]->merchandiserCode = (is_object($t->salesman->salesmanInfo)) ? $t->salesman->salesmanInfo->salesman_code : "";
                    $trip[$key]->merchandiserName = $t->salesman->getName();
                    $trip[$key]->check_in_time = $t->trip_start_time;
                    $trip[$key]->check_out_time = $t->trip_end_time;
                    $trip[$key]->totalTripTime = timeCalculate($t->trip_start_time, $t->trip_end_time);

                    if (count($customer_visits) < 2) {
                        $firstCustomerVisit = $customer_visits->first();
                        $start_work = $firstCustomerVisit->start_time;
                        $end_work = $firstCustomerVisit->end_time;
                    } else {
                        $firstCustomerVisit = $customer_visits->first();
                        $lastCustomerVisit = $customer_visits->last();
                        $start_work = $firstCustomerVisit->start_time;
                        $end_work = $lastCustomerVisit->end_time;
                    }

                    $trip[$key]->start_work = $start_work;
                    $trip[$key]->end_work = $end_work;
                    $trip[$key]->totalCustomerVisitTime = timeCalculate($start_work, $end_work);
                    $trip[$key]->customerVisits = $customer_visits;

                    $trip_data[$t->id]->start_work    = $start_work;
                    $trip_data[$t->id]->end_work      = $end_work;

                    if (isset($t->customerVisits)) {
                        foreach ($t->customerVisits as $vKey => $visit) {
                            $customer_report                    = new \stdClass();
                            $customer_report->customer_code     = isset($visit->customer->customerInfo->customer_code) ? $visit->customer->customerInfo->customer_code : "";
                            $customer_report->customer_name     = $visit->customer->getName();
                            $customer_report->total_time        = timeCalculate($visit->start_time, $visit->end_time);
                            $customer_report->start_time        = $visit->start_time;
                            $customer_report->end_time          = $visit->end_time;

                            if (count($visit->customerActivity)) {
                                foreach ($visit->customerActivity as $caKey => $activity) {
                                    $customer_activity                   = new \stdClass();
                                    $customer_activity->activity_name    = $activity->activity_name;
                                    $customer_activity->activity_action  = $activity->activity_action;
                                    $customer_activity->total_time       = $activity->total_time;
                                    $customer_activity->start_time       = $activity->start_time;
                                    $customer_activity->end_time         = $activity->end_time;

                                    if (!isset($customer_report->customer_activity)) {
                                        $customer_report->customer_activity = array();
                                    }
                                    $customer_report->customer_activity[] = $customer_activity;
                                }
                            }

                            if (!isset($trip_data[$visit->trip_id]->customer)) {
                                $trip_data[$visit->trip_id]->customer = array();
                            }

                            $trip_data[$visit->trip_id]->customer[] = $customer_report;
                        }
                    }
                }

                // if (count($t->customerVisit)) {
                //     foreach ($t->customerVisit as $customer_visit) {
                //     }
                // } else {
                //     $trip[$key]->trip_start_date = $t->trip_start;
                //     $trip[$key]->merchandiserCode = (is_object($t->salesman->salesmanInfo)) ? $t->salesman->salesmanInfo->salesman_code : "";
                //     $trip[$key]->merchandiserName = $t->salesman->getName();
                //     $trip[$key]->check_in_time = $t->trip_start_time;
                //     $trip[$key]->check_out_time = $t->trip_end_time;
                //     $trip[$key]->totalTime = 0;
                //     $trip[$key]->customer_activity = [];
                //     $trip[$key]->start_work = "00:00";
                //     $trip[$key]->end_work = "00:00";
                // }


                unset($trip[$key]->id);
                unset($trip[$key]->route_id);
                unset($trip[$key]->salesman_id);
                unset($trip[$key]->trip_start);
                unset($trip[$key]->trip_start_date);
                unset($trip[$key]->trip_start_time);
                unset($trip[$key]->trip_end);
                unset($trip[$key]->trip_end_date);
                unset($trip[$key]->trip_end_time);
                unset($trip[$key]->trip_status);
                unset($trip[$key]->trip_from);
                unset($trip[$key]->salesman);
                unset($trip[$key]->customerVisit);
            }
        }

        $excel_data = array();
        if (isset($trip_data) && $request->export == 1) {
            foreach ($trip_data as $trip_row) {
                foreach ($trip_row->customer as $customer) {
                    if (isset($customer->customer_activity) && count($customer->customer_activity)) {
                        foreach ($customer->customer_activity as $activity_row) {
                            $excel_data[] = array(
                                $trip_row->tripid,
                                $trip_row->tripStartDate,
                                $trip_row->merchandiserCode,
                                $trip_row->merchandiserName,
                                $trip_row->check_in_time,
                                $trip_row->check_out_time,
                                $trip_row->totalTripTime,
                                $trip_row->start_work,
                                $trip_row->end_work,
                                $customer->customer_code,
                                $customer->customer_name,
                                $customer->total_time,
                                $customer->start_time,
                                $customer->end_time,
                                $activity_row->activity_name,
                                $activity_row->activity_action,
                                $activity_row->total_time,
                                $activity_row->start_time,
                                $activity_row->end_time
                            );
                        }
                    }
                }
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $trip, [], "Timesheet listing", $this->success);
        } else {
            $export_report  = collect($excel_data);
            $file_name      = $request->user()->organisation_id . '_time_sheet.' . $request->export_type;

            Excel::store(new MerchandiserCustomerActivityExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function taskSummary($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Visit Date',
            'Customer Name',
            'Customer Code',
            'Merchandiser Name',
            'Task Title',
            'Task Started',
            'Task Ended',
            'Task Unplanned',
            'Task Completed'
        ];

        $all_salesman = getSalesman(true);

        $customer_activity_query = CustomerActivity::select('id', 'customer_visit_id', 'customer_id', 'activity_name', 'activity_action', 'start_time', 'end_time', 'created_at')
            ->with(
                'user:id,firstname,lastname',
                'CustomerVisit',
                'CustomerVisit.salesman:id,firstname,lastname,email'
            )
            ->whereHas('CustomerVisit', function ($q) use ($merchandiser_ids) {
                $q->whereIn('salesman_id', $merchandiser_ids);
            });

        if (count($all_salesman)) {
            $customer_activity_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $start_date         = $request->start_date;
            $customer_activity  = $customer_activity_query->whereHas('CustomerVisit', function ($q) use ($start_date) {
                $q->whereDate('date', $start_date);
            })
                ->orderBy('id', 'desc')
                ->get();
        } else if ($request->end_date) {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $customer_activity = $customer_activity_query->whereHas('CustomerVisit', function ($q) use ($start_date, $end_date) {
                $q->whereBetween('date', [$start_date, $end_date]);
            })
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $customer_activity = $customer_activity_query->whereHas('CustomerVisit', function ($q) use ($start_date) {
                $q->whereDate('date', $start_date);
            })
                ->orderBy('id', 'desc')
                ->get();
        }
        $activity_data      = array();
        if (count($customer_activity)) {
            foreach ($customer_activity as $key => $cv) {
                if ($cv->CustomerVisit) {
                    $customer_activity[$key]->visitDate = $cv->CustomerVisit->date;
                    if (is_object($cv->CustomerVisit->salesman)) {
                        $customer_activity[$key]->merchandiserName = $cv->CustomerVisit->salesman->getName();
                        $customer_activity[$key]->merchandiserEmail = $cv->CustomerVisit->salesman->email;
                    } else {
                        $customer_activity[$key]->merchandiserName = 'N/A';
                        $customer_activity[$key]->merchandiserEmail = "N/A";
                    }
                    if (is_object($cv->CustomerVisit->customer)) {
                        $customer_activity[$key]->customerName = $cv->CustomerVisit->customer->getName();
                        if (is_object($cv->CustomerVisit->customer->customerInfo)) {
                            $customer_activity[$key]->customerCode = $cv->CustomerVisit->customer->customerInfo->customer_code;
                        } else {
                            $customer_activity[$key]->customerCode = "N/A";
                        }
                    } else {
                        $customer_activity[$key]->customerName = 'N/A';
                        $customer_activity[$key]->customerCode = 'N/A';
                    }
                    $customer_activity[$key]->taskTitle = $cv->activity_name;
                    $customer_activity[$key]->startTime = $cv->start_time;
                    $customer_activity[$key]->endTime = $cv->end_time;
                    $customer_activity[$key]->unplanned = $cv->CustomerVisit->is_sequnece;
                    $customer_activity[$key]->taskCompleted = timeCalculate($cv->start_time, $cv->end_time);
                } else {
                    $customer_activity[$key]->visitDate = "N/A";
                    $customer_activity[$key]->merchandiserName = 'N/A';
                    $customer_activity[$key]->merchandiserEmail = "N/A";
                    $customer_activity[$key]->customerName = 'N/A';
                    $customer_activity[$key]->customerCode = 'N/A';
                    $customer_activity[$key]->taskTitle = 'N/A';
                    $customer_activity[$key]->startTime = 'N/A';
                    $customer_activity[$key]->endTime = 'N/A';
                    $customer_activity[$key]->unplanned = "N/A";
                    $customer_activity[$key]->taskCompleted = "N/A";
                }

                if ($request->export == 0) {
                    $customer_activity[$key]->createdAt = $cv->created_at;
                }

                $activity_data[$cv->id]                     = new \stdClass();
                $activity_data[$cv->id]->activityId         = $cv->id;
                $activity_data[$cv->id]->visitDate          = $customer_activity[$key]->visitDate;
                $activity_data[$cv->id]->customerName       = $customer_activity[$key]->customerName;
                $activity_data[$cv->id]->customerCode       = $customer_activity[$key]->customerCode;
                $activity_data[$cv->id]->merchandiserName   = $customer_activity[$key]->merchandiserName;
                $activity_data[$cv->id]->taskTitle          = $customer_activity[$key]->taskTitle;
                $activity_data[$cv->id]->startTime          = $customer_activity[$key]->startTime;
                $activity_data[$cv->id]->endTime            = $customer_activity[$key]->endTime;
                $activity_data[$cv->id]->unplanned          = $customer_activity[$key]->unplanned;
                if ($customer_activity[$key]->unplanned == 1) {
                    $activity_data[$cv->id]->unplanned  = "Yes";
                } else if ($customer_activity[$key]->unplanned == 0) {
                    $activity_data[$cv->id]->unplanned  = "No";
                }
                $activity_data[$cv->id]->taskCompleted      = $customer_activity[$key]->taskCompleted;

                unset($customer_activity[$key]->customer_id);
                unset($customer_activity[$key]->customer_visit_id);
                unset($customer_activity[$key]->salesman_id);
                unset($customer_activity[$key]->is_sequnece);
                unset($customer_activity[$key]->completed_task);
                unset($customer_activity[$key]->start_time);
                unset($customer_activity[$key]->end_time);
                unset($customer_activity[$key]->date);
                unset($customer_activity[$key]->created_at);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $customer_activity, [], "Visit summary listing", $this->success);
        } else {
            $file_name      = $request->user()->organisation_id . '_task_summary.' . $request->export_type;
            $export_report  = collect($activity_data);
            Excel::store(new MerchandiserCustomerActivityExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function taskAnswer($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Date',
            'Task Name',
            'Survey Name',
            'Customer Name',
            'Customer Code',
            'Answer Complete',
            'Questions',
            'Answer Type',
            'Answer',
            'Date',
            'Question',
            'Answer',
        ];

        $all_salesman = getSalesman();

        $survey_question_answer_query = SurveyQuestionAnswer::select('id', 'survey_id', 'survey_type_id', 'salesman_id', 'customer_id', 'customer_name', 'created_at')
            ->with(
                'survey',
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'survey.distribution:id,name',
                'survey.surveyQuestion',
                'surveyQuestionAnswerDetail',
                'surveyQuestionAnswerDetail.surveyQuestion'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $survey_question_answer_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $start_date = $request->start_date;
            $survey_question_answer = $survey_question_answer_query->whereDate('created_at', $start_date)->orderBy('id', 'desc')->get();
            // $survey_question_answer = $survey_question_answer_query->whereHas('survey', function ($q) use ($start_date) {
            //     $q->whereDate('created_at', $start_date);
            // })->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $survey_question_answer = $survey_question_answer_query->whereBetween('created_at', [$start_date, $end_date])->orderBy('id', 'desc')->get();

            // $survey_question_answer = $survey_question_answer_query->whereHas('survey', function ($q) use ($start_date, $end_date) {
            //     $q->whereBetween('created_at', [$start_date, $end_date]);
            // })->get();
        } else {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $survey_question_answer = $survey_question_answer_query->whereDate('created_at', $start_date)->get();
            // $survey_question_answer = $survey_question_answer_query->whereHas('survey', function ($q) use ($start_date) {
            //     $q->whereDate('created_at', $start_date);
            // })->get();
        }

        $survey_data      = array();
        if (count($survey_question_answer)) {
            foreach ($survey_question_answer as $key => $sqa) {
                if (is_object($sqa->surveyType)) {
                    $survey_question_answer[$key]->taskName = $sqa->surveyType->survey_name;
                } else {
                    $survey_question_answer[$key]->taskName = "N/A";
                }
                if (is_object($sqa->survey)) {
                    $survey_question_answer[$key]->surveyName = $sqa->survey->name;
                } else {
                    $survey_question_answer[$key]->surveyName = "N/A";
                }

                if (is_object($sqa->customer)) {
                    $survey_question_answer[$key]->customerName = $sqa->customer->getName();
                    if (is_object($sqa->customer->customerInfo)) {
                        $survey_question_answer[$key]->customerCode = $sqa->customer->customerInfo->customer_code;
                    } else {
                        $survey_question_answer[$key]->customerCode = 'N/A';
                    }
                } else {
                    if ($sqa->customer_name) {
                        $survey_question_answer[$key]->customerName = $sqa->customer_name;
                    } else {
                        $survey_question_answer[$key]->customerName = "N/A";
                    }
                    $survey_question_answer[$key]->customerCode = 'N/A';
                }

                if (is_object($sqa->salesman)) {
                    $survey_question_answer[$key]->merchandiserName = $sqa->salesman->getName();
                } else {
                    $survey_question_answer[$key]->merchandiserName = 'N/A';
                }

                if (is_object($sqa->survey)) {
                    if (count($sqa->survey->surveyQuestion)) {
                        $survey_question_answer[$key]->question = count($sqa->survey->surveyQuestion);
                    } else {
                        $survey_question_answer[$key]->question = 'N/A';
                    }
                    if (count($sqa->surveyQuestionAnswerDetail)) {
                        $survey_question_answer[$key]->answerComplete = count($sqa->surveyQuestionAnswerDetail);
                    } else {
                        $survey_question_answer[$key]->answerComplete = 'N/A';
                    }
                } else {
                    $survey_question_answer[$key]->question = 'N/A';
                    $survey_question_answer[$key]->answerComplete = 'N/A';
                }
                $survey_question_answer[$key]->answerType = 'N/A';
                $survey_question_answer[$key]->answer = 'N/A';

                if ($request->export == 0) {
                    $survey_question_answer[$key]->createdAt = $sqa->created_at;
                }
                $survey_data[$sqa->id]                  = new \stdClass();
                $survey_data[$sqa->id]->id              = $sqa->id;
                $survey_data[$sqa->id]->date            = date("Y-m-d", strtotime($sqa->created_at));
                $survey_data[$sqa->id]->taskName        = $survey_question_answer[$key]->taskName;
                $survey_data[$sqa->id]->surveyName      = $survey_question_answer[$key]->surveyName;
                $survey_data[$sqa->id]->customerName    = $survey_question_answer[$key]->customerName;
                $survey_data[$sqa->id]->customerCode    = $survey_question_answer[$key]->customerCode;
                $survey_data[$sqa->id]->answerComplete  = $survey_question_answer[$key]->answerComplete;
                $survey_data[$sqa->id]->question        = $survey_question_answer[$key]->question;
                $survey_data[$sqa->id]->answerType      = $survey_question_answer[$key]->answerType;
                $survey_data[$sqa->id]->answer          = $survey_question_answer[$key]->answer;
                if (isset($sqa->surveyQuestionAnswerDetail)) {
                    foreach ($sqa->surveyQuestionAnswerDetail as $vKey => $details) {
                        $survey_details             = new \stdClass();
                        $survey_details->date       = date("Y-m-d", strtotime($sqa->created_at));
                        $survey_details->question   = $details->surveyQuestion->question;
                        $survey_details->answer     = $details->answer;
                        if (!isset($survey_data[$sqa->id]->survey_detail)) {
                            $survey_data[$sqa->id]->survey_detail = array();
                        }
                        $survey_data[$sqa->id]->survey_detail[] = $survey_details;
                    }
                }

                unset($survey_question_answer[$key]->customer_id);
                unset($survey_question_answer[$key]->customer_name);
                unset($survey_question_answer[$key]->survey_type_id);
                unset($survey_question_answer[$key]->survey_id);
                unset($survey_question_answer[$key]->salesman_id);
                unset($survey_question_answer[$key]->is_sequnece);
                unset($survey_question_answer[$key]->completed_task);
                unset($survey_question_answer[$key]->start_time);
                unset($survey_question_answer[$key]->end_time);
                unset($survey_question_answer[$key]->latitude);
                unset($survey_question_answer[$key]->longitude);
                unset($survey_question_answer[$key]->date);
                unset($survey_question_answer[$key]->created_at);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $survey_question_answer, [], "Task answer listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_task_answer.' . $request->export_type;

            $excel_data = array();
            if (isset($survey_data)) {
                foreach ($survey_data as $survey_row) {
                    foreach ($survey_row->survey_detail as $detail_row) {
                        $excel_data[] = array(
                            $survey_row->id,
                            $survey_row->date,
                            $survey_row->taskName,
                            $survey_row->surveyName,
                            $survey_row->customerName,
                            $survey_row->customerCode,
                            $survey_row->answerComplete,
                            $survey_row->question,
                            $survey_row->answerType,
                            $survey_row->answer,
                            $detail_row->date,
                            $detail_row->question,
                            $detail_row->answer,
                        );
                    }
                }
            }
            $export_report  = collect($excel_data);

            Excel::store(new MerchandiserCustomerVisitExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function photos($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Visit Date',
            'Customer Code',
            'Customer Name',
            'Merchandiser Name',
            'Customer Address'
        ];

        $distribution_post_image = [
            'Distribution Name',
            'task title'
        ];

        $planogram_post = [
            'Planogram name',
            'distribution name',
            'task title',
            'Before Capture',
            'After capture'
        ];

        $asset = [
            'Asset Name',
            'task title',
            'Refrence Photo'
        ];

        $all_salesman = getSalesman(true);

        $distribution_post_image_query = DistributionPostImage::select('id', 'distribution_id', 'customer_id', 'salesman_id', 'image1', 'image2', 'image3', 'image4', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'distribution:id,name',
                'customer.customerInfo'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $distribution_post_image_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date) {
            $distribution_post_image = $distribution_post_image_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $distribution_post_image = $distribution_post_image_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($distribution_post_image)) {
            foreach ($distribution_post_image as $key => $dpi) {
                $distribution_post_image[$key]->visitDate = date('Y-m-d', strtotime($dpi->created_at));

                if (is_object($dpi->customer)) {
                    if (is_object($dpi->customer->customerInfo)) {
                        $distribution_post_image[$key]->customerCode = $dpi->customer->customerInfo->customer_code;
                    } else {
                        $distribution_post_image[$key]->customerCode = "N/A";
                    }
                    $distribution_post_image[$key]->customerName = $dpi->customer->getName();
                } else {
                    $distribution_post_image[$key]->customerCode = 'N/A';
                    $distribution_post_image[$key]->customerName = 'N/A';
                }

                if (is_object($dpi->salesman)) {
                    $distribution_post_image[$key]->merchandiserName = $dpi->salesman->getName();
                } else {
                    $distribution_post_image[$key]->merchandiserName = 'N/A';
                }

                // if (is_object($dpi->customer)) {
                //     $distribution_post_image[$key]->customerName = $dpi->customer->getName();
                //     $lat = $dpi->customer->customer_address_1_lat;
                //     $long = $dpi->customer->customer_address_1_long;
                // } else {
                //     $distribution_post_image[$key]->customerName = 'N/A';
                //     $lat = 0;
                //     $long = 0;
                // }

                if (is_object($dpi->distribution)) {
                    $distribution_post_image[$key]->distributionName = model($dpi->distribution, 'name');
                    $distribution_post_image[$key]->task_title = 'Distribution';
                } else {
                    $distribution_post_image[$key]->distributionName = 'N/A';
                    $distribution_post_image[$key]->task_title = 'Distribution';
                }

                $distribution_post_image[$key]->createdAt = $dpi->created_at;

                unset($distribution_post_image[$key]->customer_id);
                unset($distribution_post_image[$key]->salesman_id);
                unset($distribution_post_image[$key]->customer_code);
                unset($distribution_post_image[$key]->distribution_id);
                unset($distribution_post_image[$key]->created_at);
                unset($distribution_post_image[$key]->customer);
                unset($distribution_post_image[$key]->distribution);
                unset($distribution_post_image[$key]->salesman);
            }
        }


        $planogram_post_query = PlanogramPost::select('id', 'planogram_id', 'distribution_id', 'customer_id', 'salesman_id', 'description', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'distribution:id,name',
                'customer.customerInfo',
                'planogram',
                'planogramPostBeforeImage:id,planogram_post_id,image_string',
                'planogramPostAfterImage:id,planogram_post_id,image_string'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $planogram_post_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date) {
            $planogram_post = $planogram_post_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $planogram_post = $planogram_post_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($planogram_post)) {
            foreach ($planogram_post as $key => $p) {
                $planogram_post[$key]->visitDate = date('Y-m-d', strtotime($p->created_at));

                if (is_object($p->customer)) {
                    if (is_object($p->customer->customerInfo)) {
                        $planogram_post[$key]->customerCode = $p->customer->customerInfo->customer_code;
                    } else {
                        $planogram_post[$key]->customerCode = "N/A";
                    }
                    $planogram_post[$key]->customerName = $p->customer->getName();
                } else {
                    $planogram_post[$key]->customerCode = 'N/A';
                    $planogram_post[$key]->customerName = 'N/A';
                }

                if (is_object($p->salesman)) {
                    $planogram_post[$key]->merchandiserName = $p->salesman->getName();
                } else {
                    $planogram_post[$key]->merchandiserName = 'N/A';
                }

                if (is_object($p->planogram)) {
                    $planogram_post[$key]->planogramName = model($p->planogram, 'name');
                } else {
                    $planogram_post[$key]->planogramName = 'N/A';
                }

                if (is_object($p->distribution)) {
                    $planogram_post[$key]->distributionName = model($p->distribution, 'name');
                } else {
                    $planogram_post[$key]->distributionName = 'N/A';
                }

                // if (count($p->planogramPostBeforeImage)) {
                //     foreach ($p->planogramPostBeforeImage as $k => $ppi) {
                //         $img = "image_" . $k;
                //         $planogram_post[$key]->$img = $ppi->image_string;
                //     }
                // }
                //
                // if (count($p->planogramPostAfterImage)) {
                //     foreach ($p->planogramPostAfterImage as $k => $ppi) {
                //         $img = "image_" . $k;
                //         $planogram_post[$key]->$img = $ppi->image_string;
                //     }
                // }

                unset($planogram_post[$key]->customer_id);
                unset($planogram_post[$key]->salesman_id);
                unset($planogram_post[$key]->planogram_id);
                unset($planogram_post[$key]->customer_code);
                unset($planogram_post[$key]->distribution_id);
                unset($planogram_post[$key]->created_at);
                unset($planogram_post[$key]->customer);
                unset($planogram_post[$key]->distribution);
                unset($planogram_post[$key]->salesman);
                unset($planogram_post[$key]->planogram);
                // unset($planogram_post[$key]->planogramPostBeforeImage);
                // unset($planogram_post[$key]->planogramPostAfterImage);
            }
        }

        $all_salesmans = getSalesman();

        $asset_tracking_query = AssetTrackingPost::select('id', 'asset_tracking_id', 'salesman_id', 'feedback', 'created_at')
            ->with(
                'salesman:id,firstname,lastname,email',
                'assetTracking:id,title',
                'assetTrackingPostImage'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $asset_tracking_query->whereIn('salesman_id', $all_salesmans);
        }
        if ($request->end_date) {
            $asset_tracking = $asset_tracking_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $asset_tracking = $asset_tracking_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($asset_tracking)) {
            foreach ($asset_tracking as $key => $at) {
                $asset_tracking[$key]->visitDate = date('Y-m-d', strtotime($at->created_at));

                if (is_object($at->salesman)) {
                    if (isset($p->salesman->id)) {
                        $asset_tracking[$key]->merchandiserName = $p->salesman->getName();
                    }
                } else {
                    $asset_tracking[$key]->merchandiserName = 'N/A';
                }

                if (is_object($at->assetTracking)) {
                    $asset_tracking[$key]->assetName = model($at->assetTracking, 'title');
                } else {
                    $asset_tracking[$key]->assetName = 'N/A';
                }

                if (count($at->assetTrackingPostImage)) {
                    foreach ($at->assetTrackingPostImage as $k => $ppi) {
                        $img = "image_" . $k;
                        $asset_tracking[$key]->$img = $ppi->image_string;
                    }
                }

                unset($asset_tracking[$key]->salesman_id);
                unset($asset_tracking[$key]->asset_tracking_id);
                unset($asset_tracking[$key]->created_at);
                unset($asset_tracking[$key]->asset_tracking);
                unset($asset_tracking[$key]->assetTrackingPostImage);
                unset($asset_tracking[$key]->salesman);
                unset($asset_tracking[$key]->asset_tracking);
            }
        }

        $campaign_picture_query = CampaignPicture::select('id', 'campaign_id', 'salesman_id', 'customer_id', 'created_at')
            ->with(
                'salesman:id,firstname,lastname,email',
                'customer:id,firstname,lastname,email',
                'customer.customerInfo',
                'campaignPictureImage'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        // $all_salesman = getSalesman(true);
        if (count($all_salesman)) {
            $campaign_picture_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date) {
            $campaign_picture = $campaign_picture_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $campaign_picture = $campaign_picture_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($campaign_picture)) {
            foreach ($campaign_picture as $key => $cp) {
                $campaign_picture[$key]->visitDate = date('Y-m-d', strtotime($cp->created_at));

                if (is_object($cp->customer)) {
                    if (is_object($cp->customer->customerInfo)) {
                        $campaign_picture[$key]->customerCode = $cp->customer->customerInfo->customer_code;
                    } else {
                        $campaign_picture[$key]->customerCode = "N/A";
                    }
                    $campaign_picture[$key]->customerName = $cp->customer->getName();
                } else {
                    $campaign_picture[$key]->customerCode = 'N/A';
                    $campaign_picture[$key]->customerName = 'N/A';
                }

                if (is_object($cp->salesman)) {
                    $campaign_picture[$key]->merchandiserName = $cp->salesman->getName();
                } else {
                    $campaign_picture[$key]->merchandiserName = 'N/A';
                }

                $campaign_picture[$key]->campaign_code = $cp->campaign_id;

                if (count($cp->campaignPictureImage)) {
                    foreach ($cp->campaignPictureImage as $k => $ppi) {
                        $img = "image_" . $k;
                        $campaign_picture[$key]->$img = $ppi->image_string;
                    }
                }

                unset($campaign_picture[$key]->customer_id);
                unset($campaign_picture[$key]->salesman_id);
                unset($campaign_picture[$key]->customer_code);
                unset($campaign_picture[$key]->campaign_id);
                unset($campaign_picture[$key]->created_at);
                unset($campaign_picture[$key]->customer);
                unset($campaign_picture[$key]->salesman);
                unset($campaign_picture[$key]->campaignPictureImage);
            }
        }

        $complaint_feedback_query = ComplaintFeedback::select('id', 'salesman_id', 'customer_id', 'complaint_id', 'title', 'created_at')
            ->with(
                'salesman:id,firstname,lastname,email',
                'customer:id,firstname,lastname,email',
                'customer.customerInfo',
                'complaintFeedbackImage'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $complaint_feedback_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date) {
            $complaint_feedback = $complaint_feedback_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $complaint_feedback = $complaint_feedback_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($complaint_feedback)) {
            foreach ($complaint_feedback as $key => $cf) {
                $complaint_feedback[$key]->visitDate = date('Y-m-d', strtotime($cf->created_at));

                if (is_object($cf->customer)) {
                    if (is_object($cf->customer->customerInfo)) {
                        $complaint_feedback[$key]->customerCode = $cf->customer->customerInfo->customer_code;
                    } else {
                        $complaint_feedback[$key]->customerCode = "N/A";
                    }
                    $complaint_feedback[$key]->customerName = $cf->customer->getName();
                } else {
                    $complaint_feedback[$key]->customerCode = 'N/A';
                    $complaint_feedback[$key]->customerName = 'N/A';
                }

                if (is_object($cf->salesman)) {
                    $complaint_feedback[$key]->merchandiserName = $cf->salesman->getName();
                } else {
                    $complaint_feedback[$key]->merchandiserName = 'N/A';
                }

                $complaint_feedback[$key]->Name = $cf->title;
                $complaint_feedback[$key]->campaignCode = $cf->complaint_id;

                if (count($cf->complaintFeedbackImage)) {
                    foreach ($cf->complaintFeedbackImage as $k => $ppi) {
                        $img = "image_" . $k;
                        $complaint_feedback[$key]->$img = $ppi->image_string;
                    }
                }

                unset($complaint_feedback[$key]->customer_id);
                unset($complaint_feedback[$key]->salesman_id);
                unset($complaint_feedback[$key]->customer_code);
                unset($complaint_feedback[$key]->complaint_id);
                unset($complaint_feedback[$key]->created_at);
                unset($complaint_feedback[$key]->title);
                unset($complaint_feedback[$key]->customer);
                unset($complaint_feedback[$key]->salesman);
                unset($complaint_feedback[$key]->complaintFeedbackImage);
            }
        }

        $competitor_info_query = CompetitorInfo::select('id', 'salesman_id', 'company', 'item', 'price', 'brand', 'note', 'created_at')
            ->with(
                'salesman:id,firstname,lastname,email',
                'competitorInfoImage'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesmans)) {
            $distribution_post_image_query->whereIn('salesman_id', $all_salesmans);
        }

        if ($request->end_date) {
            $competitor_info = $competitor_info_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $competitor_info = $competitor_info_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if (count($competitor_info)) {
            foreach ($competitor_info as $key => $ci) {
                $competitor_info[$key]->visitDate = date('Y-m-d', strtotime($ci->created_at));

                if (is_object($ci->salesman)) {
                    $competitor_info[$key]->merchandiserName = $ci->salesman->getName();
                } else {
                    $competitor_info[$key]->merchandiserName = 'N/A';
                }

                if (count($ci->competitorInfoImage)) {
                    foreach ($ci->competitorInfoImage as $k => $ppi) {
                        $img = "image_" . $k;
                        $competitor_info[$key]->$img = $ppi->image_string;
                    }
                }

                unset($competitor_info[$key]->salesman_id);
                unset($competitor_info[$key]->customer_code);
                unset($competitor_info[$key]->created_at);
                unset($competitor_info[$key]->salesman);
                unset($competitor_info[$key]->competitorInfoImage);
            }
        }


        $data = array(
            'distribution_post_image' => $distribution_post_image,
            'planogram_post' => $planogram_post,
            'asset_tracking' => $asset_tracking,
            'campaign_picture' => $campaign_picture,
            'complaint_feedback' => $complaint_feedback,
            'competitor_info' => $competitor_info,
        );


        if ($request->export == 0) {
            return prepareResult(true, $data, [], "Visit summary listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_visit_summary.' . $request->export_type;

            Excel::store(new MerchandiserCustomerVisitExport($data, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function visitSummary($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Merchandiser Name',
            'Merchandiser Email',
            'Customer Name',
            'Address',
            'Merchandiser Latitude',
            'Merchandiser Longitude',
            'Role',
            'Created Date',
            'Visit Date'
        ];

        $all_salesman = getSalesman(true);

        $customer_visit_query = CustomerVisit::select('id', 'customer_id', 'salesman_id', 'latitude', 'longitude', 'start_time', 'end_time', 'is_sequnece', 'date', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'customer.customerInfo'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $customer_visit_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $customer_visit = $customer_visit_query->whereBetween('date', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $visit_data = array();
        if (count($customer_visit)) {
            foreach ($customer_visit as $key => $cv) {
                $customer_visit[$key]->visitDate = $cv->date;
                if (is_object($cv->salesman)) {
                    $customer_visit[$key]->salesmanName = $cv->salesman->getName();
                    $customer_visit[$key]->salesmanEmail = $cv->salesman->email;
                } else {
                    $customer_visit[$key]->salesmanName = 'N/A';
                    $customer_visit[$key]->salesmanEmail = "N/A";
                }

                if (is_object($cv->customer)) {
                    $customer_visit[$key]->customerName = $cv->customer->getName();
                    $lat = $cv->customer->customer_address_1_lat;
                    $long = $cv->customer->customer_address_1_long;
                } else {
                    $customer_visit[$key]->customerName = 'N/A';
                    $lat = 0;
                    $long = 0;
                }

                // $customer_visit[$key]->startTime = $cv->start_time;
                // $customer_visit[$key]->endTime = $cv->end_time;
                // $customer_visit[$key]->proximity = distance($lat, $customer_visit[$key]->latitude, $long, $customer_visit[$key]->longitude, 'K') . " Meters";

                if ($this->type = "merchandising") {
                    $customer_visit[$key]->role = 'merchandising';
                } else {
                    $customer_visit[$key]->role = 'vansales';
                }

                $customer_visit[$key]->unplanned = $cv->is_sequnece;

                if (is_object($cv->customer)) {
                    if (is_object($cv->customer->customerInfo)) {
                        $customer_visit[$key]->address = $cv->customer->customerInfo->customer_address_1;
                    } else {
                        $customer_visit[$key]->address = 'N/A';
                    }
                } else {
                    $customer_visit[$key]->address = 'N/A';
                }
                $customer_visit[$key]->merchandiser_latitude = $cv->latitude;
                $customer_visit[$key]->merchandiser_longitude = $cv->longitude;

                if ($request->export == 0) {
                    $customer_visit[$key]->createdAt = $cv->created_at;
                }

                $visit_data[$cv->id]                = new \stdClass();
                $visit_data[$cv->id]->orderid       = $cv->id;
                $visit_data[$cv->id]->salesmanName  = $customer_visit[$key]->salesmanName;
                $visit_data[$cv->id]->salesmanEmail = $customer_visit[$key]->salesmanEmail;
                $visit_data[$cv->id]->customerName  = $customer_visit[$key]->customerName;
                $visit_data[$cv->id]->address       = $customer_visit[$key]->address;
                $visit_data[$cv->id]->lat           = $customer_visit[$key]->merchandiser_latitude;
                $visit_data[$cv->id]->long          = $customer_visit[$key]->merchandiser_longitude;
                $visit_data[$cv->id]->role          = $customer_visit[$key]->role;
                $visit_data[$cv->id]->createdAt     = date('Y-m-d', strtotime($cv->created_at));
                $visit_data[$cv->id]->visitDate     = $customer_visit[$key]->visitDate;

                unset($customer_visit[$key]->customer_id);
                unset($customer_visit[$key]->salesman_id);
                unset($customer_visit[$key]->is_sequnece);
                unset($customer_visit[$key]->completed_task);
                unset($customer_visit[$key]->start_time);
                unset($customer_visit[$key]->end_time);
                unset($customer_visit[$key]->latitude);
                unset($customer_visit[$key]->longitude);
                unset($customer_visit[$key]->date);
                unset($customer_visit[$key]->created_at);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $customer_visit, [], "Visit summary listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_visit_summary.' . $request->export_type;

            $export_report  = collect($visit_data);
            Excel::store(new MerchandiserCustomerVisitExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function closeVisit($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Visit Date',
            'Merchandiser Name',
            'Customer Name',
            'location',
            'total tasks',
            'completed tasks',
            'start time',
            'end time',
            'proximity'
        ];

        $all_salesman = getSalesman(true);

        $customer_visit_query = CustomerVisit::select('id', 'customer_id', 'salesman_id', 'latitude', 'longitude', 'start_time', 'end_time', 'date', 'completed_task', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname',
                'customer.customerInfo'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $customer_visit_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $customer_visit = $customer_visit_query->whereBetween('date', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        }
        $visit_data      = array();
        if (count($customer_visit)) {
            foreach ($customer_visit as $key => $cv) {
                $customer_visit[$key]->visitDate = $cv->date;
                if (is_object($cv->salesman)) {
                    $customer_visit[$key]->salesmanName = $cv->salesman->getName();
                } else {
                    $customer_visit[$key]->salesmanName = 'N/A';
                }
                if (is_object($cv->customer)) {
                    $customer_visit[$key]->customerName = $cv->customer->getName();
                    $lat = $cv->customer->customer_address_1_lat;
                    $long = $cv->customer->customer_address_1_long;
                } else {
                    $customer_visit[$key]->customerName = 'N/A';
                    $lat = 0;
                    $long = 0;
                }

                if (is_object($cv->customer)) {
                    if (is_object($cv->customer->customerInfo)) {
                        $customer_visit[$key]->location = $cv->customer->customerInfo->customer_address_1;
                    } else {
                        $customer_visit[$key]->location = 'N/A';
                    }
                } else {
                    $customer_visit[$key]->location = 'N/A';
                }

                $customer_visit[$key]->total_task = 9;
                $customer_visit[$key]->completedTask = $cv->completed_task;
                $customer_visit[$key]->startTime = $cv->start_time;
                $customer_visit[$key]->endTime = $cv->end_time;
                $customer_visit[$key]->proximity = distance($lat, $customer_visit[$key]->latitude, $long, $customer_visit[$key]->longitude, 'K') . " Meters";
                // $customer_visit[$key]->proximity = haversineGreatCircleDistance($lat, $customer_visit[$key]->latitude, $long, $customer_visit[$key]->longitude);

                if ($request->export == 0) {
                    $customer_visit[$key]->createdAt = $cv->created_at;
                }

                $visit_data[$cv->id]                = new \stdClass();
                $visit_data[$cv->id]->visitId       = $cv->id;
                $visit_data[$cv->id]->visitDate     = $customer_visit[$key]->visitDate;
                $visit_data[$cv->id]->salesmanName  = $customer_visit[$key]->salesmanName;
                $visit_data[$cv->id]->customerName  = $customer_visit[$key]->customerName;
                $visit_data[$cv->id]->location      = $customer_visit[$key]->location;
                $visit_data[$cv->id]->totalTask     = $customer_visit[$key]->total_task;
                $visit_data[$cv->id]->completedTask = $customer_visit[$key]->completedTask;
                $visit_data[$cv->id]->startTime     = $customer_visit[$key]->startTime;
                $visit_data[$cv->id]->endTime       = $customer_visit[$key]->endTime;
                $visit_data[$cv->id]->proximity     = $customer_visit[$key]->proximity;

                unset($customer_visit[$key]->customer_id);
                unset($customer_visit[$key]->salesman_id);
                unset($customer_visit[$key]->completed_task);
                unset($customer_visit[$key]->start_time);
                unset($customer_visit[$key]->end_time);
                unset($customer_visit[$key]->latitude);
                unset($customer_visit[$key]->longitude);
                unset($customer_visit[$key]->date);
                unset($customer_visit[$key]->created_at);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $customer_visit, [], "Customer Visit listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_closed_visit.' . $request->export_type;

            $export_report  = collect($visit_data);
            Excel::store(new MerchandiserCustomerVisitExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function orderSumamry($request, $merchandiser_ids)
    {
        $columns = [
            'Id',
            'Date',
            'Merchandiser Name',
            'Merchandiser Code',
            'Customer Name',
            'Customer Code',
            'Order Code',
            'Order Due Date',
            'Delivery Date',
            'Type',
            'Order Gross Total',
            'Order Discount',
            'Order Net Total',
            'Order Excise',
            'Order Vat',
            'Order total',
            'Status',
            'Date',
            'Item',
            'Item UOM',
            'Item Discount',
            'Item QTY',
            'Item Gross',
            'Item Excise',
            'Item Net',
            'Item Vat',
            'Item Price',
            'Item Grand Total',
            'Order Status',
        ];

        $all_salesmans = getSalesman(true);

        $orders_query = Order::select('id', 'customer_id', 'order_number', 'order_date', 'due_date', 'delivery_date', 'payment_term_id', 'salesman_id', 'total_gross', 'total_discount_amount', 'total_net', 'total_vat', 'total_excise', 'grand_total', 'current_stage', 'created_at')
            ->with(
                'orderDetails',
                'orderDetails.item:id,item_name',
                'orderDetails.itemUom:id,name',
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesmans)) {
            $orders_query->whereIn('customer_id', $all_salesmans);
        }

        if ($request->customer_id) {
            $orders_query = $orders_query->where('customer_id', $request->customer_id);
        } else {
            $orders_query = $orders_query->whereIn('salesman_id', $merchandiser_ids);
        }


        if ($request->end_date == $request->start_date) {
            $orders = $orders_query->whereDate('order_date', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $orders = $orders_query->whereBetween('order_date', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $orders = $orders_query->whereDate('order_date', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $export_report  = array();
        $orders_data      = array();
        if (count($orders)) {
            foreach ($orders as $key => $o) {
                $orders[$key]->id = $o->id;
                $orders[$key]->orderDate = $o->order_date;
                if (is_object($o->salesman)) {
                    $orders[$key]->salesmanName = $o->salesman->getName();
                } else {
                    $orders[$key]->salesmanName = 'N/A';
                }
                if (is_object($o->customer)) {
                    $orders[$key]->customerName = $o->customer->getName();
                    if (is_object($o->customer)) {
                        $orders[$key]->customerCode = $o->customer->customerInfo->customer_code;
                    } else {
                        $orders[$key]->customerCode = "N/A";
                    }
                } else {
                    $orders[$key]->customerName = 'N/A';
                    $orders[$key]->customerCode = "N/A";
                }
                $orders[$key]->orderCode = $o->order_number;
                $orders[$key]->dueDate = $o->due_date;
                $orders[$key]->deliveryDate = $o->delivery_date;
                if (is_object($o->paymentTerm)) {
                    $orders[$key]->type = $o->paymentTerm->name;
                } else {
                    $orders[$key]->type = "N/A";
                }

                $orders[$key]->totalGross = $o->total_gross;
                $orders[$key]->totalDiscountAmount = $o->total_discount_amount;
                $orders[$key]->netTotal = $o->total_net;
                $orders[$key]->vat = $o->total_vat;
                $orders[$key]->totalExcise = $o->total_excise;
                $orders[$key]->grandTotal = $o->grand_total;
                $orders[$key]->status = $o->current_stage;
                if ($request->export == 0) {
                    $orders[$key]->createdAt = $o->created_at;
                }

                $orders_data[$o->id]                        = new \stdClass();
                $orders_data[$o->id]->orderid               = $o->id;
                $orders_data[$o->id]->order_date            = $o->order_date;
                $orders_data[$o->id]->salesmanName          = $orders[$key]->salesmanName;
                $orders_data[$o->id]->salesmanCode          = (is_object($o->salesman->salesmanInfo)) ? $o->salesman->salesmanInfo->salesman_code : "";
                $orders_data[$o->id]->customerName          = $orders[$key]->customerName;
                $orders_data[$o->id]->customerCode          = $orders[$key]->customerCode;
                $orders_data[$o->id]->orderCode             = $o->orderCode;
                $orders_data[$o->id]->dueDate               = $o->dueDate;
                $orders_data[$o->id]->deliveryDate          = $o->deliveryDate;
                $orders_data[$o->id]->type                  = $o->type;
                $orders_data[$o->id]->totalGross            = $o->totalGross;
                $orders_data[$o->id]->totalDiscountAmount   = $o->totalDiscountAmount;
                $orders_data[$o->id]->netTotal              = $o->netTotal;
                $orders_data[$o->id]->totalExcise           = $o->totalExcise;
                $orders_data[$o->id]->vat                   = $o->vat;
                $orders_data[$o->id]->grandTotal            = $o->grandTotal;
                $orders_data[$o->id]->status                = $o->status;

                if (isset($o->orderDetails)) {
                    foreach ($o->orderDetails as $vKey => $details) {
                        $order_details                          = new \stdClass();
                        $order_details->order_date              = $details->created_at;
                        $order_details->item                    = $details->item->item_name;
                        $order_details->item_uom                = $details->itemUom->name;
                        $order_details->item_discount_amount    = $details->item_discount_amount;
                        $order_details->item_qty                = $details->item_qty;
                        $order_details->item_gross              = $details->item_gross;
                        $order_details->item_excise             = $details->item_excise;
                        $order_details->item_net                = $details->item_net;
                        $order_details->item_vat                = $details->item_vat;
                        $order_details->item_price              = $details->item_price;
                        $order_details->item_grand_total        = $details->item_grand_total;
                        $order_details->order_status            = $details->order_status;

                        if (!isset($orders_data[$o->id]->order_details)) {
                            $orders_data[$o->id]->order_details = array();
                        }
                        $orders_data[$o->id]->order_details[] = $order_details;
                    }
                }
                unset($orders[$key]->customer_id);
                unset($orders[$key]->salesman_id);
                unset($orders[$key]->order_date);
                unset($orders[$key]->order_number);
                unset($orders[$key]->delivery_date);
                unset($orders[$key]->due_date);
                unset($orders[$key]->payment_term_id);
                unset($orders[$key]->total_gross);
                unset($orders[$key]->total_discount_amount);
                unset($orders[$key]->total_vat);
                unset($orders[$key]->total_net);
                unset($orders[$key]->grand_total);
                unset($orders[$key]->total_excise);
                unset($orders[$key]->created_at);
                unset($orders[$key]->current_stage);
            }
        }

        $excel_data = array();
        if (isset($orders_data)) {
            foreach ($orders_data as $orders_row) {
                foreach ($orders_row->order_details as $order_details) {
                    $excel_data[] = array(
                        $orders_row->orderid,
                        $orders_row->order_date,
                        $orders_row->salesmanName,
                        $orders_row->salesmanCode,
                        $orders_row->customerName,
                        $orders_row->customerCode,
                        $orders_row->orderCode,
                        $orders_row->dueDate,
                        $orders_row->deliveryDate,
                        $orders_row->type,
                        $orders_row->totalGross,
                        $orders_row->totalDiscountAmount,
                        $orders_row->netTotal,
                        $orders_row->totalExcise,
                        $orders_row->vat,
                        $orders_row->grandTotal,
                        $orders_row->status,
                        $order_details->order_date,
                        $order_details->item,
                        $order_details->item_uom,
                        $order_details->item_discount_amount,
                        $order_details->item_qty,
                        $order_details->item_gross,
                        $order_details->item_excise,
                        $order_details->item_net,
                        $order_details->item_vat,
                        $order_details->item_price,
                        $order_details->item_grand_total,
                        $order_details->order_status,
                    );
                }
            }
        }
        if ($request->export == 0) {
            return prepareResult(true, $orders, [], "order summary listing", $this->success);
        } else {
            $file_name      = $request->user()->organisation_id . '_order_summary.' . $request->export_type;
            $export_report  = collect($excel_data);
            Excel::store(new MerchandiserOrderSummaryExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function newCustomer($request, $merchandiser_ids)
    {
        $columns = [
            'Id',
            'Customer Name',
            'Customer Code',
            'Location',
            'Merchandsier Name',
            'Customer Type',
            'Latitude',
            'Longitude',
            'Visit Date',
            'status'
        ];

        $all_salesman = getSalesman(true);

        $customers_query = CustomerInfo::select('id', 'user_id', 'customer_code', 'customer_type_id', 'customer_address_1', 'customer_address_2', 'customer_city', 'customer_state', 'customer_zipcode', 'customer_address_1_lat', 'customer_address_1_lang', 'customer_address_2_lat', 'customer_address_2_lang', 'created_at', 'current_stage')
            ->with(
                'user:id,firstname,lastname',
                // 'merchandiser:id,firstname,lastname'
                'customerMerchandiser:id,customer_id,merchandiser_id',
                'customerMerchandiser.salesman:id,firstname,lastname'
            )
            ->whereHas('customerMerchandiser', function ($query) use ($merchandiser_ids) {
                $query->where('merchandiser_id', $merchandiser_ids);
            });

        if (count($all_salesman)) {
            $customers_query->whereIn('user_id', $all_salesman);
        }

        // ->whereIn('merchandiser_id', $merchandiser_ids);

        if ($request->end_date) {
            $customers = $customers_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $customers = $customers_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $customers, [], "customer listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_customers.' . $request->export_type;

            if (count($customers)) {
                foreach ($customers as $key => $c) {
                    $customers[$key]->customer_name = $c->user->getName();
                    $customers[$key]->code = $c->customer_code;
                    $customers[$key]->location = $c->getAddress();

                    if (is_object($c->customerMerchandiser)) {
                        $customers[$key]->merchandsier_name = $c->customerMerchandiser->salesman->getName();
                    } else {
                        $customers[$key]->merchandsier_name = 'N/A';
                    }

                    if ($c->customer_type_id == 1) {
                        $customers[$key]->customer_type = 'Cash';
                    } else if ($c->customer_type_id == 2) {
                        $customers[$key]->customer_type = 'Credit';
                    } else {
                        $customers[$key]->customer_type = 'Bill To Bill';
                    }

                    $customers[$key]->latitude = $c->customer_address_1_lat;
                    $customers[$key]->longitude = $c->customer_address_1_lang;
                    $customers[$key]->visit_date = date('Y-m-d', $c->created_at);
                    $customers[$key]->status = $c->current_stage;

                    unset($customers[$key]->uuid);
                    unset($customers[$key]->user_id);
                    unset($customers[$key]->organisation_id);
                    unset($customers[$key]->customer_code);
                    unset($customers[$key]->customer_address_1);
                    unset($customers[$key]->customer_address_2);
                    unset($customers[$key]->customer_city);
                    unset($customers[$key]->customer_state);
                    unset($customers[$key]->customer_zipcode);
                    unset($customers[$key]->merchandiser_id);
                    unset($customers[$key]->customer_address_1_lat);
                    unset($customers[$key]->customer_address_2_lat);
                    unset($customers[$key]->customer_address_1_lang);
                    unset($customers[$key]->customer_address_2_lang);
                    unset($customers[$key]->created_at);
                    unset($customers[$key]->customer_type_id);
                    unset($customers[$key]->current_stage);
                }
            }

            Excel::store(new MerchandiserCustomerExport($customers, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function competitorInfo($request, $merchandiser_ids)
    {
        $columns = [
            'id',
            'Date',
            'Merchandiser name',
            'Merchandiser code',
            'Company',
            'Brand',
            'Item',
            'Item Code',
            'Price',
        ];

        $all_salesman = getSalesman();
        $competitor_infos_query = CompetitorInfo::select('id', 'uuid', 'organisation_id', 'salesman_id', 'company', 'brand', 'item', 'price', 'note', 'created_at')
            ->with(
                'salesman:id,firstname,lastname',
                'competitorInfoImage:id,competitor_info_id,image_string'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $competitor_infos_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $competitor_infos = $competitor_infos_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $competitor_infos = $competitor_infos_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $competitor_infos = $competitor_infos_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $competitor_infos, [], "competitor infos listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_competitor_product.' . $request->export_type;
            $competitor_data = array();
            if (count($competitor_infos)) {
                foreach ($competitor_infos as $key => $ci) {
                    $competitor_infos[$key]->salesman_name = $ci->salesman->getName();

                    $competitor_data[$ci->id]                  = new \stdClass();
                    $competitor_data[$ci->id]->id              = $ci->id;
                    $competitor_data[$ci->id]->created_at      = date('Y-m-d', strtotime($ci->created_at));
                    $competitor_data[$ci->id]->salesman_name   = $competitor_infos[$key]->salesman_name;
                    $competitor_data[$ci->id]->salesman_code   = (is_object($ci->salesman->salesmanInfo)) ? $ci->salesman->salesmanInfo->salesman_code : "";
                    $competitor_data[$ci->id]->company         = $ci->company;
                    $competitor_data[$ci->id]->brand           = $ci->brand;
                    $competitor_data[$ci->id]->item            = $ci->item;
                    $competitor_data[$ci->id]->itemCode        = "";
                    $competitor_data[$ci->id]->price           = $ci->price;

                    unset($competitor_infos[$key]->uuid);
                    unset($competitor_infos[$key]->salesman_id);
                    unset($competitor_infos[$key]->organisation_id);
                    unset($competitor_infos[$key]->created_at);
                }
            }
            $export_report  = collect($competitor_data);
            Excel::store(new MerchandiserCompetitorInfoExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function orderReturn($request, $merchandiser_ids)
    {
        $columns = [
            'Id',
            'Customer Name',
            'Is Return',
            'Order Number',
            'Order Date',
            'Order Due Date',
            'Total Discount',
            'Total Excise',
            'Total Net',
            'Total Vat',
            'Grand Total'
        ];

        $all_salesman = getSalesman(true);

        $orders_query = Order::select('id', 'customer_id', 'order_number', 'order_date', 'due_date', \DB::raw("'No' as is_return"), 'total_gross', 'total_discount_amount', 'total_net', 'total_vat', 'total_excise', 'grand_total', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $orders_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $orders = $orders_query->whereDate('order_date', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $orders = $orders_query->whereBetween('order_date', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $orders = $orders_query->whereDate('order_date', $request->start_date)->orderBy('id', 'desc')->get();
        }


        $credit_note_query = CreditNote::select('id', 'customer_id', 'credit_note_number as order_number', 'credit_note_date as order_date', \DB::raw("'' as due_date"), \DB::raw("'Yes' as is_return"), 'total_gross', 'total_discount_amount', 'total_net', 'total_vat', 'total_excise', 'grand_total', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'customer.customerInfo'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $credit_note_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->end_date) {
            $credit_note = $credit_note_query->whereBetween('credit_note_date', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $credit_note = $credit_note_query->whereDate('credit_note_date', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $orderReturn = $credit_note->merge($orders);

        if ($request->export == 0) {
            return prepareResult(true, $orders, [], "order return listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_order_return.' . $request->export_type;
            $order_data = array();
            if (count($orderReturn)) {
                foreach ($orderReturn as $key => $o) {
                    $orderReturn[$key]->id = $o->id;
                    if (is_object($o->customer)) {
                        $orderReturn[$key]->customerName = $o->customer->getName();
                        if (is_object($o->customer->customerInfo)) {
                            $orderReturn[$key]->location = $o->customer->customerInfo->customer_address_1;
                        } else {
                            $orderReturn[$key]->location = 'N/A';
                        }
                    } else {
                        $orderReturn[$key]->customerName = 'N/A';
                        $orderReturn[$key]->location = 'N/A';
                    }
                    $orderReturn[$key]->orderNumber = $o->order_number;
                    $orderReturn[$key]->isReturn = $o->is_return;
                    $orderReturn[$key]->dueDate = $o->due_date;
                    $orderReturn[$key]->orderDate = $o->order_date;
                    $orderReturn[$key]->totalGross = $o->total_gross;
                    $orderReturn[$key]->totalGross = $o->total_gross;
                    $orderReturn[$key]->totalDiscount = $o->total_discount_amount;
                    $orderReturn[$key]->totalNet = $o->total_net;
                    $orderReturn[$key]->totalVat = $o->total_vat;
                    $orderReturn[$key]->totalExcise = $o->total_excise;
                    $orderReturn[$key]->grandTotal = $o->grand_total;

                    $order_data[$o->id]                 = new \stdClass();
                    $order_data[$o->id]->orderId        = $orderReturn[$key]->id;
                    $order_data[$o->id]->customerName   = $orderReturn[$key]->customerName;
                    $order_data[$o->id]->isReturn       = $orderReturn[$key]->isReturn;
                    $order_data[$o->id]->orderNumber    = $orderReturn[$key]->orderNumber;
                    $order_data[$o->id]->orderDate      = $orderReturn[$key]->orderDate;
                    $order_data[$o->id]->dueDate        = $orderReturn[$key]->dueDate;
                    $order_data[$o->id]->totalDiscount  = $orderReturn[$key]->totalDiscount;
                    $order_data[$o->id]->totalExcise    = $orderReturn[$key]->totalExcise;
                    $order_data[$o->id]->totalNet       = $orderReturn[$key]->totalNet;
                    $order_data[$o->id]->totalVat       = $orderReturn[$key]->totalVat;
                    $order_data[$o->id]->grandTotal     = $orderReturn[$key]->grandTotal;

                    unset($orderReturn[$key]->customer_id);
                    unset($orderReturn[$key]->order_date);
                    unset($orderReturn[$key]->order_number);
                    unset($orderReturn[$key]->due_date);
                    unset($orderReturn[$key]->is_return);
                    unset($orderReturn[$key]->total_gross);
                    unset($orderReturn[$key]->total_discount_amount);
                    unset($orderReturn[$key]->total_net);
                    unset($orderReturn[$key]->total_vat);
                    unset($orderReturn[$key]->total_excise);
                    unset($orderReturn[$key]->grand_total);
                    unset($orderReturn[$key]->created_at);
                }
            }

            $export_report  = collect($order_data);
            Excel::store(new MerchandiserOrderReturnExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }

        return prepareResult(true, $orderReturn, [], "order return listing", $this->success);
    }

    private function sos($request, $merchandiser_ids)
    {
        $columns = [
            'Id',
            'Date',
            'Customer Name',
            'Customer Code',
            'Merchandiser name',
            'Display tool',
            'Category',
            'Product/Item',
            'Item Code',
            'Facing',
            'Actual Facing',
            'Score'
        ];

        $all_salesman = getSalesman(true);

        $sos_query = ShareOfShelf::select('id', 'distribution_id', 'salesman_id', 'customer_id', 'item_id', 'total_number_of_facing', 'actual_number_of_facing', 'score', 'created_at')
            ->with(
                'distribution:id,name',
                'item:id,item_code,item_name,item_major_category_id',
                'item.itemMajorCategory:id,name',
                'salesman:id,firstname,lastname',
                'customer:id,firstname,lastname',
                'customer.customerInfo'
            );

        if (count($all_salesman)) {
            $sos_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->customer_id) {
            $sos_query = $sos_query->where('customer_id', $request->customer_id);
        } else {
            $sos_query = $sos_query->whereIn('salesman_id', $merchandiser_ids);
        }

        if ($request->date) {
            $sos_query->whereDate('created_at', date('Y-m-d', strtotime($request->date)));
        }

        if ($request->salesman_name) {
            $salesman_name = $request->salesman_name;
            $exploded_name = explode(" ", $salesman_name);
            if (count($exploded_name) < 2) {
                $sos_query->whereHas('salesman', function ($q) use ($salesman_name) {
                    $q->where('firstname', 'like', '%' . $salesman_name . '%')
                        ->orWhere('lastname', 'like', '%' . $salesman_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $sos_query->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_name) {
            $customer_name = $request->customer_name;
            $exploded_name = explode(" ", $customer_name);
            if (count($exploded_name) < 2) {
                $sos_query->whereHas('customer', function ($q) use ($customer_name) {
                    $q->where('firstname', 'like', '%' . $customer_name . '%')
                        ->orWhere('lastname', 'like', '%' . $customer_name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $sos_query->whereHas('customer', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->customer_code) {
            $code = $request->customer_code;
            $sos_query->whereHas('customer.customerInfo', function ($q) use ($code) {
                $q->where('customer_code', $code);
            });
        }

        if ($request->item_name) {
            $item_name = $request->item_name;
            $sos_query->whereHas('item', function ($q) use ($item_name) {
                $q->where('item_name', $item_name);
            });
        }

        if ($request->item_code) {
            $code = $request->item_code;
            $sos_query->whereHas('item', function ($q) use ($code) {
                $q->where('item_code', $code);
            });
        }

        if ($request->end_date == $request->start_date) {
            $shareofshelf = $sos_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $shareofshelf = $sos_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $shareofshelf = $sos_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $sos_data = array();
        if (count($shareofshelf)) {
            foreach ($shareofshelf as $key => $sos) {
                $shareofshelf[$key]->id = $sos->id;
                $shareofshelf[$key]->date = date('Y-m-d', strtotime($sos->created_at));
                $shareofshelf[$key]->createdAt = $sos->created_at;
                if (is_object($sos->customer)) {
                    $shareofshelf[$key]->customerName = $sos->customer->getName();
                    if (is_object($sos->customer->customerInfo)) {
                        $shareofshelf[$key]->customerCode = $sos->customer->customerInfo->customer_code;
                    } else {
                        $shareofshelf[$key]->customerCode = 'N/A';
                    }
                } else {
                    $shareofshelf[$key]->customerName = 'N/A';
                    $shareofshelf[$key]->customerCode = 'N/A';
                }
                if (is_object($sos->salesman)) {
                    $shareofshelf[$key]->merchandiserName = $sos->salesman->getName();
                } else {
                    $shareofshelf[$key]->merchandiserName = 'N/A';
                }
                if (is_object($sos->distribution)) {
                    $shareofshelf[$key]->displayTool = $sos->distribution->name;
                } else {
                    $shareofshelf[$key]->displayTool = 'N/A';
                }

                if (is_object($sos->itemMajorCategories)) {
                    $shareofshelf[$key]->category = $sos->itemMajorCategories->name;;
                } else {
                    $shareofshelf[$key]->category = 'N/A';
                }

                if (is_object($sos->item)) {
                    if (is_object($sos->item->itemMajorCategory)) {
                        $shareofshelf[$key]->category = $sos->item->itemMajorCategory->name;
                    } else {
                        $shareofshelf[$key]->category = "N/A";
                    }
                    $shareofshelf[$key]->itemName = $sos->item->item_name;
                    $shareofshelf[$key]->itemCode = $sos->item->item_code;
                } else {
                    $shareofshelf[$key]->itemName = 'N/A';
                    $shareofshelf[$key]->itemCode = 'N/A';
                    $shareofshelf[$key]->category = "N/A";
                }
                $shareofshelf[$key]->facing = $sos->total_number_of_facing;
                $shareofshelf[$key]->actualFacing = $sos->actual_number_of_facing;
                $shareofshelf[$key]->totalScore = $sos->score;

                $sos_data[$sos->id]                     = new \stdClass();
                $sos_data[$sos->id]->sosId              = $sos->id;
                $sos_data[$sos->id]->date               = $shareofshelf[$key]->date;
                $sos_data[$sos->id]->customerName       = $shareofshelf[$key]->customerName;
                $sos_data[$sos->id]->customerCode       = $shareofshelf[$key]->customerCode;
                $sos_data[$sos->id]->merchandiserName   = $shareofshelf[$key]->merchandiserName;
                $sos_data[$sos->id]->displayTool        = $shareofshelf[$key]->displayTool;
                $sos_data[$sos->id]->category           = $shareofshelf[$key]->category;
                $sos_data[$sos->id]->itemName           = $shareofshelf[$key]->itemName;
                $sos_data[$sos->id]->itemCode           = $shareofshelf[$key]->itemCode;
                $sos_data[$sos->id]->facing             = $shareofshelf[$key]->facing;
                $sos_data[$sos->id]->actualFacing       = $shareofshelf[$key]->actualFacing;
                $sos_data[$sos->id]->score              = $shareofshelf[$key]->totalScore;

                unset($shareofshelf[$key]->organisation_id);
                unset($shareofshelf[$key]->distribution_id);
                unset($shareofshelf[$key]->customer_id);
                unset($shareofshelf[$key]->salesman_id);
                unset($shareofshelf[$key]->item_id);
                unset($shareofshelf[$key]->total_number_of_facing);
                unset($shareofshelf[$key]->actual_number_of_facing);
                unset($shareofshelf[$key]->total_gross);
                unset($shareofshelf[$key]->total_discount_amount);
                unset($shareofshelf[$key]->score);
                unset($shareofshelf[$key]->created_at);
                unset($shareofshelf[$key]->salesman);
                unset($shareofshelf[$key]->customer);
                unset($shareofshelf[$key]->item);
                unset($shareofshelf[$key]->distribution);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $shareofshelf, [], "sos listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_sos.' . $request->export_type;

            $export_report  = collect($sos_data);
            Excel::store(new MerchandiserShareOfShelfExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }

        return prepareResult(true, $shareofshelf, [], "sos listing", $this->success);
    }

    private function stockAvailability($request, $merchandiser_ids)
    {
        $columns = [
            'Date',
            'Customer',
            'Display Tool',
            'Category',
            'Item name',
            'Item Code',
            'Back Store',
            'Capacity',
            'To Fill',
            'available / Oos',
            'Good Saleable'
        ];

        $all_salesman = getSalesman(true);

        $stock_query = DistributionStock::select('id', 'distribution_id', 'customer_id', 'item_id', 'stock', 'capacity', 'is_out_of_stock', 'created_at')
            ->with(
                'distribution:id,name',
                // 'distribution.distributionModelStockDetails:id,distribution_model_stock_id,distribution_id,item_id,item_uom_id,capacity,total_number_of_facing',
                'assignInventory:id,activity_name',
                'item:id,item_code,item_name,item_major_category_id',
                'item.itemMajorCategory:id,name',
                'customer:id,firstname,lastname',
                'customer.customerInfo:id,user_id,customer_code'
            )
            ->whereIn('salesman_id', $merchandiser_ids);

        if (count($all_salesman)) {
            $stock_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->customer_id) {
            $stock_query = $stock_query->where('customer_id', $request->customer_id);
        } else {
            $stock_query = $stock_query->whereIn('salesman_id', $merchandiser_ids);
        }

        if ($request->end_date == $request->start_date) {
            $stock_availability = $stock_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $stock_availability = $stock_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $stock_availability = $stock_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $stock_data = array();
        if (count($stock_availability)) {
            foreach ($stock_availability as $key => $sa) {
                $stock_availability[$key]->date = date('Y-m-d', strtotime($sa->created_at));
                $stock_availability[$key]->createdAt = $sa->created_at;
                if (is_object($sa->customer)) {
                    $stock_availability[$key]->customerName = $sa->customer->getName();
                    if (is_object($sa->customer->customerInfo)) {
                        $stock_availability[$key]->customerCode = $sa->customer->customerInfo->customer_code;
                    } else {
                        $stock_availability[$key]->customerCode = $sa->customer->customerInfo->customer_code;
                    }
                } else {
                    $stock_availability[$key]->customerName = 'N/A';
                    $stock_availability[$key]->customerCode = 'N/A';
                }
                if (is_object($sa->distribution)) {
                    $stock_availability[$key]->displayTool = $sa->distribution->name;
                    // if (is_object($sa->distribution->distributionModelStockDetails)) {
                    //     $capacity = $sa->distribution->distributionModelStockDetails[0]->capacity;
                    // } else {
                    //     $capacity = 0.00;
                    // }
                } else {
                    $stock_availability[$key]->displayTool = 'N/A';
                    // $capacity = 0.00;
                }

                if (is_object($sa->assignInventory)) {
                    $stock_availability[$key]->backStore = $sa->assignInventory->activity_name;
                } else {
                    $stock_availability[$key]->backStore = 'N/A';
                }
                if (is_object($sa->item)) {
                    if (is_object($sa->item->itemMajorCategory)) {
                        $stock_availability[$key]->category = $sa->item->itemMajorCategory->name;
                    } else {
                        $stock_availability[$key]->category = "N/A";
                    }
                    $stock_availability[$key]->itemName = $sa->item->item_name;
                    $stock_availability[$key]->itemCode = $sa->item->item_code;
                } else {
                    $stock_availability[$key]->itemName = 'N/A';
                    $stock_availability[$key]->itemCode = 'N/A';
                    $stock_availability[$key]->category = "N/A";
                }

                $capacity = $sa->capacity;
                $goodSaleable = $sa->stock;
                $toFill = $capacity - $goodSaleable;
                $stock_availability[$key]->capacity = $capacity;
                $stock_availability[$key]->goodSaleable = $goodSaleable;
                $stock_availability[$key]->toFill = $toFill;
                $stock_availability[$key]->availbleOos = ($sa->is_out_of_stock == 1 ? "Out of Stock" : "Available");

                $stock_data[$sa->id]                = new \stdClass();
                $stock_data[$sa->id]->date          = $stock_availability[$key]->date;
                $stock_data[$sa->id]->customerName  = $stock_availability[$key]->customerName;
                $stock_data[$sa->id]->customerCode  = $stock_availability[$key]->customerCode;
                $stock_data[$sa->id]->displayTool   = $stock_availability[$key]->displayTool;
                $stock_data[$sa->id]->category      = $stock_availability[$key]->category;
                $stock_data[$sa->id]->itemName      = $stock_availability[$key]->itemName;
                $stock_data[$sa->id]->itemCode      = $stock_availability[$key]->itemCode;
                $stock_data[$sa->id]->backStore     = $stock_availability[$key]->backStore;
                $stock_data[$sa->id]->capacity      = $stock_availability[$key]->capacity;
                $stock_data[$sa->id]->toFill        = $stock_availability[$key]->toFill;
                $stock_data[$sa->id]->availbleOos   = $stock_availability[$key]->availbleOos;
                $stock_data[$sa->id]->goodSaleable  = $stock_availability[$key]->goodSaleable;

                unset($stock_availability[$key]->id);
                unset($stock_availability[$key]->distribution_id);
                unset($stock_availability[$key]->customer_id);
                unset($stock_availability[$key]->item_id);
                unset($stock_availability[$key]->stock);
                unset($stock_availability[$key]->is_out_of_stock);
                unset($stock_availability[$key]->created_at);
                unset($stock_availability[$key]->customer);
                unset($stock_availability[$key]->item);
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $stock_availability, [], "stock availability listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_stock_availability.' . $request->export_type;

            $export_report  = collect($stock_data);
            Excel::store(new MerchandiserStockAvailabilityExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }

        return prepareResult(true, $sa, [], "stock availability listing", $this->success);
    }

    private function planogram($request, $merchandiser_ids)
    {

        $columns = [
            'Date',
            'Customer',
            'Display Tool',
            'Planogram name',
            'Before Capture',
            'After capture'
        ];

        $all_salesman = getSalesman();

        $planogram_post_query = PlanogramPost::select('id', 'planogram_id', 'distribution_id', 'customer_id', 'salesman_id', 'description', 'feedback', 'score', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'distribution:id,name',
                'customer.customerInfo',
                'planogram',
                'planogram.planogramImage',
                'planogramPostBeforeImage',
                'planogramPostAfterImage'
            );

        if (count($all_salesman)) {
            $planogram_post_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->customer_id) {
            $planogram_post_query = $planogram_post_query->where('customer_id', $request->customer_id);
        } else {
            $planogram_post_query = $planogram_post_query->whereIn('salesman_id', $merchandiser_ids);
        }

        if ($request->end_date == $request->start_date) {
            $planogram_post = $planogram_post_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $planogram_post = $planogram_post_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $planogram_post = $planogram_post_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }

        if ($request->export == 0) {
            return prepareResult(true, $planogram_post, [], "planogram listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_planogram.' . $request->export_type;

            if (count($planogram_post)) {
                foreach ($planogram_post as $key => $p) {
                    $planogram_post[$key]->date = date('Y-m-d', strtotime($p->created_at));

                    if (is_object($p->customer)) {
                        $planogram_post[$key]->customerName = $p->customer->getName();
                    } else {
                        $planogram_post[$key]->customerName = 'N/A';
                    }
                    if (is_object($p->distribution)) {
                        $planogram_post[$key]->displayTool = $p->distribution->name;
                    } else {
                        $planogram_post[$key]->displayTool = 'N/A';
                    }
                    if (is_object($p->planogram)) {
                        $planogram_post[$key]->planogramName = model($p->planogram, 'name');
                    } else {
                        $planogram_post[$key]->planogramName = 'N/A';
                    }

                    if (count($p->planogramPostImage)) {
                        foreach ($p->planogramPostImage as $k => $ppi) {
                            $img = "image_" . $k;
                            $planogram_post[$key]->$img = $ppi->image_string;
                        }
                    }

                    unset($planogram_post[$key]->id);
                    unset($planogram_post[$key]->description);
                    unset($planogram_post[$key]->customer_id);
                    unset($planogram_post[$key]->salesman_id);
                    unset($planogram_post[$key]->planogram_id);
                    unset($planogram_post[$key]->customer_code);
                    unset($planogram_post[$key]->distribution_id);
                    unset($planogram_post[$key]->created_at);
                    unset($planogram_post[$key]->customer);
                    unset($planogram_post[$key]->distribution);
                    unset($planogram_post[$key]->salesman);
                    unset($planogram_post[$key]->planogram);
                    unset($planogram_post[$key]->planogramPostImage);
                }
            }
            Excel::store(new MerchandiserCustomerVisitExport($planogram_post, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
        //return prepareResult(true, $sa, [], "stock availability listing", $this->success);
    }

    private function taskCompleted($request, $merchandiser_ids)
    {
        $columns = [
            'Date',
            'Task Name',
            'Start Time',
            'End Time',
            'Merchandiser',
            'Time Spent'
        ];

        $all_salesman = getSalesman(true);

        $task_query = CustomerActivity::select('id', 'customer_visit_id', 'customer_id', 'activity_name', 'start_time', 'end_time', 'created_at')
            ->with(
                'CustomerVisit.salesman:id,firstname,lastname'
            );

        if (count($all_salesman)) {
            $task_query->whereIn('customer_id', $all_salesman);
        }

        if ($request->customer_id) {
            $task_query = $task_query->where('customer_id', $request->customer_id);
        }

        if ($request->end_date == $request->start_date) {
            $tasks = $task_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $tasks = $task_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $tasks = $task_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }


        if ($request->export == 0) {
            return prepareResult(true, $tasks, [], "tasks listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_task_completed.' . $request->export_type;

            Excel::store(new MerchandiserCustomerVisitExport($tasks, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
        return prepareResult(true, $sa, [], "tasks listing", $this->success);
    }

    private function complaint($request, $merchandiser_ids)
    {
        $columns = [
            'Date',
            'Task Name',
            'Start Time',
            'End Time',
            'Merchandiser',
            'Time Spent'
        ];

        $all_salesman = getSalesman();

        $complaint_query = ComplaintFeedback::select('id', 'salesman_id', 'customer_id', 'complaint_id', 'item_id', 'title', 'description',  'type', 'status', 'created_at')
            ->with(
                'customer:id,firstname,lastname',
                'item:id,item_code,item_name',
                'item.itemMajorCategory:id,name'
            );

        if (count($all_salesman)) {
            $complaint_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->customer_id) {
            $complaint_query = $complaint_query->where('customer_id', $request->customer_id);
        }

        if ($request->end_date) {
            $complaints = $complaint_query->whereBetween('created_at', [$request->start_date, $request->end_date])->orderBy('id', 'desc')->get();
        } else {
            $complaints = $complaint_query->whereDate('created_at', $request->start_date)->orderBy('id', 'desc')->get();
        }


        if ($request->export == 0) {
            return prepareResult(true, $complaints, [], "complaints listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_complaints.' . $request->export_type;

            Excel::store(new MerchandiserCustomerVisitExport($complaints, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
        return prepareResult(true, $sa, [], "complaints listing", $this->success);
    }

    private function storeSummary($request, $merchandiser_ids)
    {
        if ($request->export == 0) {
            $orders             = $this->orderSumamry($request, $merchandiser_ids);
            $shareOfShelf       = $this->sos($request, $merchandiser_ids);
            $planogram          = $this->planogram($request, $merchandiser_ids);
            $stockAvailability  = $this->stockAvailability($request, $merchandiser_ids);
            $taskCompleted      = $this->taskCompleted($request, $merchandiser_ids);
            $complaint          = $this->complaint($request, $merchandiser_ids);

            $data = array(
                'orders' => json_decode($orders->content(), true)['data'],
                'shareOfShelf' => json_decode($shareOfShelf->content(), true)['data'],
                'planogram' => json_decode($planogram->content(), true)['data'],
                'stockAvailability' => json_decode($stockAvailability->content(), true)['data'],
                'taskCompleted' => json_decode($taskCompleted->content(), true)['data'],
                'complaint' => json_decode($complaint->content(), true)['data']
            );
            return prepareResult(true, $data, [], "store summary listing", $this->success);
        } else {
            $request->export            = 0;
            $file_name                  = $request->user()->organisation_id . '_store_summary.' . $request->export_type;

            $planogram          = $this->planogram($request, $merchandiser_ids);
            $planogram          = json_decode($planogram->content(), true)['data'];
            $planogram_fields   = [
                'Date',
                'Customer Name',
                'Distribution Name',
                'Ref Image 1',
                'Ref Image 2',
                'Ref Image 3',
                'Ref Image 4',
                'Ref Image 5',
                'Befor',
                'After',
                'Feedback',
                'score',
            ];
            $planogram_data = array();
            if (!empty($planogram)) {
                foreach ($planogram as $pRow) {
                    $planogram_data[$pRow['id']]                    = new \stdClass();
                    $planogram_data[$pRow['id']]->date              = date("Y-m-d", strtotime($pRow['created_at']));
                    $planogram_data[$pRow['id']]->customer          = "";
                    $planogram_data[$pRow['id']]->distribution      = "";
                    $planogram_data[$pRow['id']]->refPlanogram0     = "";
                    $planogram_data[$pRow['id']]->refPlanogram1     = "";
                    $planogram_data[$pRow['id']]->refPlanogram2     = "";
                    $planogram_data[$pRow['id']]->refPlanogram3     = "";
                    $planogram_data[$pRow['id']]->refPlanogram4     = "";
                    $planogram_data[$pRow['id']]->before            = "";
                    $planogram_data[$pRow['id']]->after             = "";
                    $planogram_data[$pRow['id']]->feedback          = $pRow['feedback'];
                    $planogram_data[$pRow['id']]->score             = $pRow['score'];
                    if (isset($pRow['customer']['firstname']) && isset($pRow['customer']['firstname'])) {
                        $planogram_data[$pRow['id']]->customer  = $pRow['customer']['firstname'] . " " . $pRow['customer']['lastname'];
                    }
                    if (isset($pRow['distribution']['name'])) {
                        $planogram_data[$pRow['id']]->distribution  = $pRow['distribution']['name'];
                    }
                    if (isset($pRow['planogram_post_image']) && !empty($pRow['planogram_post_image'])) {
                        foreach ($pRow['planogram_post_image'] as $bRow => $aRow) {
                            switch ($bRow) {
                                case 0:
                                    $planogram_data[$pRow['id']]->before    = $aRow['image_string'];
                                    break;
                                case 1:
                                    $planogram_data[$pRow['id']]->after     = $aRow['image_string'];
                                    break;
                                default:
                                    break;
                            }
                        }
                    }

                    if (isset($pRow['planogram']['planogram_image']) && !empty($pRow['planogram']['planogram_image'])) {
                        foreach ($pRow['planogram']['planogram_image'] as $kRow => $iRow) {
                            switch ($kRow) {
                                case 0:
                                    $planogram_data[$pRow['id']]->refPlanogram0 = $iRow['image_string'];
                                    break;
                                case 1:
                                    $planogram_data[$pRow['id']]->refPlanogram1 = $iRow['image_string'];
                                    break;
                                case 2:
                                    $planogram_data[$pRow['id']]->refPlanogram2 = $iRow['image_string'];
                                    break;
                                case 3:
                                    $planogram_data[$pRow['id']]->refPlanogram3 = $iRow['image_string'];
                                    break;
                                case 4:
                                    $planogram_data[$pRow['id']]->refPlanogram4 = $iRow['image_string'];
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }

            $taskCompleted              = $this->taskCompleted($request, $merchandiser_ids);
            $taskCompleted              = json_decode($taskCompleted->content(), true)['data'];
            $task_completed_fields      = [
                'Date',
                'Activity Name',
                'Start Time',
                'End Time',
            ];

            $task_completed_data        = array();
            if (!empty($taskCompleted)) {
                foreach ($taskCompleted as $tRow) {
                    $task_completed_data[$tRow['id']]                   = new \stdClass();
                    $task_completed_data[$tRow['id']]->date             = date("Y-m-d", strtotime($tRow['created_at']));
                    $task_completed_data[$tRow['id']]->activity_name    = $tRow['activity_name'];
                    $task_completed_data[$tRow['id']]->start_time       = $tRow['start_time'];
                    $task_completed_data[$tRow['id']]->end_time         = $tRow['end_time'];
                }
            }

            $stockAvailability          = $this->stockAvailability($request, $merchandiser_ids);
            $stockAvailability          = json_decode($stockAvailability->content(), true)['data'];
            $stock_availability_fields  = [
                'Date',
                'Customer',
                'Display tool',
                'Available',
                'Item',
                'Item code',
                'Stock',
            ];
            $stock_availability_data    = array();
            if (!empty($stockAvailability)) {
                foreach ($stockAvailability as $sKey => $sRow) {
                    $stock_availability_data[$sKey]               = new \stdClass();
                    $stock_availability_data[$sKey]->date         = $sRow['date'];
                    $stock_availability_data[$sKey]->customerName = $sRow['customerName'];
                    $stock_availability_data[$sKey]->displayTool  = $sRow['displayTool'];
                    $stock_availability_data[$sKey]->availbleOos  = $sRow['availbleOos'];
                    $stock_availability_data[$sKey]->itemName     = $sRow['itemName'];
                    $stock_availability_data[$sKey]->itemCode     = $sRow['itemCode'];
                    $stock_availability_data[$sKey]->backStore    = $sRow['backStore'];
                }
            }

            $orders         = $this->orderSumamry($request, $merchandiser_ids);
            $orders         = json_decode($orders->content(), true)['data'];
            $orders_fields  = [
                'Date',
                'Customer',
                'Customer Code',
                'Delivery date',
                'Due date',
                'Grand total',
                'Order date',
                'Salesman',
                'Discount',
                'Excise',
                'Grose',
                'Type',
                'Vat',
            ];
            $orders_data    = array();
            if (!empty($orders)) {
                foreach ($orders as $oRow) {
                    $orders_data[$oRow['id']]                       = new \stdClass();
                    $orders_data[$oRow['id']]->createdAt            = date("Y-m-d", strtotime($oRow['createdAt']));
                    $orders_data[$oRow['id']]->customerName         = $oRow['customerName'];
                    $orders_data[$oRow['id']]->customerCode         = $oRow['customerCode'];
                    $orders_data[$oRow['id']]->deliveryDate         = $oRow['deliveryDate'];
                    $orders_data[$oRow['id']]->dueDate              = $oRow['dueDate'];
                    $orders_data[$oRow['id']]->grandTotal           = $oRow['grandTotal'];
                    $orders_data[$oRow['id']]->orderDate            = $oRow['orderDate'];
                    $orders_data[$oRow['id']]->salesmanName         = $oRow['salesmanName'];
                    $orders_data[$oRow['id']]->totalDiscountAmount  = $oRow['totalDiscountAmount'];
                    $orders_data[$oRow['id']]->totalExcise          = $oRow['totalExcise'];
                    $orders_data[$oRow['id']]->totalGross           = $oRow['totalGross'];
                    $orders_data[$oRow['id']]->type                 = $oRow['type'];
                    $orders_data[$oRow['id']]->vat                  = $oRow['vat'];
                }
            }

            $complaint          = $this->complaint($request, $merchandiser_ids);
            $complaint          = json_decode($complaint->content(), true)['data'];
            $complaint_fields   = [
                'Date',
                'Complaint id',
                'Customer',
                'Item',
                'Item Code',
                'Title',
                'Type',
                'description',
            ];

            $complaint_data = array();
            if (!empty($complaint)) {
                foreach ($complaint as $cRow) {
                    $complaint_data[$cRow['id']]                = new \stdClass();
                    $complaint_data[$cRow['id']]->created_at     = date("Y-m-d", strtotime($cRow['created_at']));
                    $complaint_data[$cRow['id']]->complaint_id  = $cRow['complaint_id'];
                    $complaint_data[$cRow['id']]->customer      = "";
                    $complaint_data[$cRow['id']]->item          = "";
                    $complaint_data[$cRow['id']]->item_code     = "";
                    $complaint_data[$cRow['id']]->title         = $cRow['title'];
                    $complaint_data[$cRow['id']]->type          = $cRow['type'];
                    $complaint_data[$cRow['id']]->description   = $cRow['description'];
                    if (isset($cRow['customer']['firstname']) && isset($cRow['customer']['firstname'])) {
                        $complaint_data[$cRow['id']]->customer  = $cRow['customer']['firstname'] . " " . $cRow['customer']['lastname'];
                    }
                    if (isset($cRow['item']['item_name'])) {
                        $complaint_data[$cRow['id']]->item  = $cRow['item']['item_name'];
                    }
                    if (isset($cRow['item']['item_code'])) {
                        $complaint_data[$cRow['id']]->item_code  = $cRow['item']['item_code'];
                    }
                }
            }

            $shareOfShelf       = $this->sos($request, $merchandiser_ids);
            $shareOfShelf       = json_decode($shareOfShelf->content(), true)['data'];
            $share_shelf_fields = [
                'Date',
                'Customer',
                'Customer Code',
                'Merchandiser Name',
                'Display Tool',
                'Item Name',
                'Item Code',
                'Category',
                'Facing',
                'Actual Facing',
                'Total Score',
            ];
            $share_shelf_data   = array();
            if (!empty($shareOfShelf)) {
                foreach ($shareOfShelf as $ssRow) {
                    $share_shelf_data[$ssRow['id']]                     = new \stdClass();
                    $share_shelf_data[$ssRow['id']]->date               = $ssRow['date'];
                    $share_shelf_data[$ssRow['id']]->customerName       = $ssRow['customerName'];
                    $share_shelf_data[$ssRow['id']]->customerCode       = $ssRow['customerCode'];
                    $share_shelf_data[$ssRow['id']]->merchandiserName   = $ssRow['merchandiserName'];
                    $share_shelf_data[$ssRow['id']]->displayTool        = $ssRow['displayTool'];
                    $share_shelf_data[$ssRow['id']]->itemName           = $ssRow['itemName'];
                    $share_shelf_data[$ssRow['id']]->itemCode           = $ssRow['itemCode'];
                    $share_shelf_data[$ssRow['id']]->category           = $ssRow['category'];
                    $share_shelf_data[$ssRow['id']]->facing             = $ssRow['facing'];
                    $share_shelf_data[$ssRow['id']]->actualFacing       = $ssRow['actualFacing'];
                    $share_shelf_data[$ssRow['id']]->totalScore         = $ssRow['totalScore'];
                }
            }

            $data_multipal = array(
                new MerchandiserCustomerVisitExport(collect($task_completed_data), $task_completed_fields, "Task Completed"),
                new MerchandiserCustomerVisitExport(collect($stock_availability_data), $stock_availability_fields, "Stock Availibility"),
                new MerchandiserCustomerVisitExport(collect($orders_data), $orders_fields, "Orders"),
                new MerchandiserCustomerVisitExport(collect($complaint_data), $complaint_fields, "Complaint"),
                new MerchandiserCustomerVisitExport(collect($share_shelf_data), $share_shelf_fields, "SOS"),
                new MerchandiserCustomerVisitExport(collect($planogram_data), $planogram_fields, "Planogram"),
            );

            $export = new MerchandiserMultipleSheets($data_multipal);
            Excel::store($export, $file_name, '', $this->extensions($request->export_type));

            //            Excel::store(new MerchandiserCustomerVisitExport($data, $columns), $file_name, '', $this->extensions($request->export_type));
            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function getMerchandiser($salesman_type)
    {
        $salesman_info = SalesmanInfo::with('user:firstname,lastname,id')
            ->where('salesman_type_id', $salesman_type)
            ->get()
            ->pluck('user_id')
            ->toArray();

        return $salesman_info;
    }

    private function extensions($extensions_type)
    {
        if ($extensions_type == 'XLSX') {
            return \Maatwebsite\Excel\Excel::XLSX;
        } else if ($extensions_type == 'CSV') {
            return \Maatwebsite\Excel\Excel::CSV;
        } else if ($extensions_type == 'PDF') {
            return \Maatwebsite\Excel\Excel::MPDF;
        } else if ($extensions_type == 'XLS') {
            return \Maatwebsite\Excel\Excel::XLS;
        }
    }

    private function attendance($request)
    {
        $columns = [
            'Date',
            'Merchandiser Code',
            'Merchandiser Name',
            'JP',
            'Check In',
            'Check Out'
        ];

        $all_salesman = getSalesman();


        $begin = new DateTime($request->start_date);
        $end   = new DateTime($request->end_date);

        $salesmanInfos = SalesmanInfo::select('id', 'user_id', 'salesman_code')
            ->with(
                'user:id,firstname,lastname',
                'trip:id,salesman_id,trip_start_date,trip_start_time,trip_end_time'
            )
            ->whereIn('user_id', $all_salesman)
            ->get();

        $mergeData = array();
        for ($i = $begin; $i <= $end; $i->modify('+1 day')) {
            if (count($salesmanInfos)) {
                foreach ($salesmanInfos as $salesman) {

                    $firstOfMonth   = date("Y-m-01", strtotime(model($salesman, 'trip_start_date')));
                    $week =  intval(date("W", strtotime(model($salesman, 'trip_start_date')))) - intval(date("W", strtotime($firstOfMonth))) + 2;
                    $week = weekOfMonth(strtotime(model($salesman, 'trip_start_date')));

                    $journey_plan_days = '';
                    $jp = JourneyPlan::select('id', 'name', 'merchandiser_id')
                        ->where('merchandiser_id', $salesman->user_id)
                        ->first();

                    if (is_object($jp)) {
                        $jpWeek = JourneyPlanWeek::select('id', 'journey_plan_id', 'week_number')
                            ->where('journey_plan_id', $jp->id)
                            ->where('week_number', 'week' . $week)
                            ->first();

                        $day = date('l', strtotime(model($salesman, 'trip_start_date')));

                        $journey_plan_days = JourneyPlanDay::select('id', 'journey_plan_id')
                            ->where('journey_plan_id', $jp->id)
                            ->where('journey_plan_week_id', $jpWeek->id)
                            ->where('day_name', $day)
                            ->first();
                    }


                    $is_jp = "No";
                    if (is_object($journey_plan_days)) {
                        $is_jp = "Yes";
                    }

                    $mergeData[] = array(
                        'date' => $i->format("Y-m-d"),
                        'merchandiser_name' => model($salesman->user, 'firstname') . ' ' . model($salesman->user, 'lastname'),
                        'merchandiser_code' => model($salesman, 'salesman_code'),
                        'jp' => $is_jp,
                        'checkin' => model($salesman->trip, 'trip_start_time'),
                        'checkout' => model($salesman->trip, 'trip_end_time')
                    );
                }
            }
        }

        if ($request->export == 0) {
            return prepareResult(true, $mergeData, [], "Salesman attendance listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_attendance.' . $request->export_type;

            Excel::store(new AttendanceExport($mergeData, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function routeVisit($request, $merchandiser_ids)
    {
        $columns = [
            'Date',
            'Merchandiser Code',
            'Merchandiser Name',
            'JP',
            'Planned Visited',
            'Total Visited',
            'JP%',
            'UnPlanned Calls',
            'Unplanned calls%',
            'Strike Calls',
            'Strike Calls%',
        ];

        $all_salesman = getSalesman();

        $customer_visit_query = CustomerVisit::select([DB::raw("SUM(CASE WHEN journey_plan_id > 0 THEN 1 ELSE 0 END) as total_journey"), DB::raw("SUM(CASE WHEN is_sequnece = '1' THEN 1 ELSE 0 END) as planed_journey"), DB::raw("SUM(CASE WHEN is_sequnece = '0' THEN 1 ELSE 0 END) as un_planed_journey"), 'id', 'customer_id', 'journey_plan_id', 'salesman_id', 'latitude', 'longitude', 'start_time', 'end_time', 'is_sequnece', 'date', 'created_at'])
            ->with(
                'customer:id,firstname,lastname',
                'salesman:id,firstname,lastname,email',
                'customer.customerInfo'
            )
            ->groupBy('salesman_id', 'customer_visits.date', 'customer_id');

        if (count($all_salesman)) {
            $customer_visit_query->whereIn('salesman_id', $all_salesman);
        }

        if ($request->end_date == $request->start_date) {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        } else if ($request->end_date) {
            $end_date = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
            $customer_visit = $customer_visit_query->whereBetween('date', [$request->start_date, $end_date])->orderBy('id', 'desc')->get();
        } else {
            $customer_visit = $customer_visit_query->whereDate('date', $request->start_date)->orderBy('id', 'desc')->get();
        }

        $customer_visit = $customer_visit_query->orderBy('id', 'desc')->get();
        $export_report  = array();
        $visit_report   = array();

        if (count($customer_visit)) {
            foreach ($customer_visit as $key => $visit) {
                $jp = 0;
                $salesman_id = $visit->salesman_id;
                $journey_plans = JourneyPlan::select([DB::raw('group_concat(id) as plan_ids')])
                    ->where('is_merchandiser', 1)
                    ->where('id', $visit->journey_plan_id)
                    ->where('merchandiser_id', $salesman_id)
                    ->orderBy('id', 'desc')
                    ->get();

                foreach ($journey_plans as $j_plan) {
                    if (isset($j_plan->plan_ids) && ($j_plan->plan_ids != '')) {
                        $plan_id = explode(',', $j_plan->plan_ids);
                        if (!empty($plan_id)) {
                            $day = date('l', strtotime($visit->date));
                            // $week = (int)date('W', strtotime($visit->date));
                            $firstOfMonth   = date("Y-m-01", strtotime($visit->date));
                            $week           =  intval(date("W", strtotime($visit->date))) - intval(date("W", strtotime($firstOfMonth))) + 2;

                            $week = weekOfMonth(strtotime($visit->date));

                            $journey_plan_week = JourneyPlanWeek::select([DB::raw('group_concat(id) as week_ids')])
                                ->whereIn('journey_plan_id', $plan_id)
                                ->where('week_number', "week" . $week)
                                ->first();

                            if (!empty($journey_plan_week)) {
                                $week_ids = explode(',', $journey_plan_week['week_ids']);

                                $journey_plan_days = JourneyPlanDay::select('id', 'journey_plan_id')
                                    ->whereIn('journey_plan_id', $plan_id)
                                    ->whereIn('journey_plan_week_id', $week_ids)
                                    ->where('day_name', $day)
                                    ->orderBy('id', 'desc')
                                    ->orderBy('id', 'desc')
                                    ->get();
                                foreach ($journey_plan_days as $jp_day) {
                                    $jp += JourneyPlanCustomer::where('journey_plan_id', $jp_day->journey_plan_id)
                                        ->where('journey_plan_day_id', $jp_day->id)
                                        ->count();
                                }
                            }
                        }
                    }
                }

                $strike_calls           = 0;
                $strike_calls_percent   = 0;
                $total_customers        = 0;
                $total_visit_customers  = 0;
                if ($salesman_id > 0) {
                    // $m_customers = CustomerInfo::select([DB::raw('COUNT(DISTINCT user_id) as customers')])->where('merchandiser_id', $salesman_id)->first();
                    $m_customers = CustomerMerchandiser::select([DB::raw('COUNT(DISTINCT customer_id) as customers')])
                        ->where('merchandiser_id', $salesman_id)
                        ->first();

                    if (isset($m_customers->customers) && $m_customers->customers > 0) {
                        $total_customers = $m_customers->customers;
                    }
                    //                    $visit_customers = CustomerVisit::select([DB::raw('COUNT(DISTINCT customer_id) as visit_customers')])->where('customer_visits.salesman_id', $salesman_id)->first();
                    //                    if (isset($visit_customers->visit_customers) && $visit_customers->visit_customers > 0) {
                    //                        $total_visit_customers = $visit_customers->visit_customers;
                    //                    }
                    //                    if ($total_customers > 0) {
                    //                        $strike_calls           = $total_customers - $total_visit_customers;
                    //                        $strike_calls_percent   = ($strike_calls > 0) ? $total_visit_customers / $total_customers * 100 : 0;
                    //                    }
                }

                if (!isset($visit_report[$visit->date][$salesman_id])) {
                    $visit_report[$visit->date][$salesman_id]   = new \stdClass();
                }

                $visit_report[$visit->date][$salesman_id]->id                     = $visit->id;
                $visit_report[$visit->date][$salesman_id]->date                   = $visit->date;
                $visit_report[$visit->date][$salesman_id]->journeyPlan            = $jp;

                if (!isset($visit_report[$visit->date][$salesman_id]->totalJourney)) {
                    $visit_report[$visit->date][$salesman_id]->totalJourney = 0;
                }
                $visit_report[$visit->date][$salesman_id]->totalJourney           += 1;
                //                $visit_report[$visit->date][$salesman_id]->planedJourney          = $jp;
                if (!isset($visit_report[$visit->date][$salesman_id]->planedJourney)) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney = 0;
                }

                if ($visit->is_sequnece == 1) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney += 1;
                }

                // change by hardik
                if ($jp < $visit_report[$visit->date][$salesman_id]->planedJourney) {
                    $visit_report[$visit->date][$salesman_id]->planedJourney = $jp;
                }

                $visit_report[$visit->date][$salesman_id]->journeyPlanPercent     = ($visit_report[$visit->date][$salesman_id]->planedJourney > 0 && $jp > 0) ? (round(($visit_report[$visit->date][$salesman_id]->planedJourney / $jp), 2) * 100) . '%' : 0;

                if (!isset($visit_report[$visit->date][$salesman_id]->unPlanedJourney)) {
                    $visit_report[$visit->date][$salesman_id]->unPlanedJourney = 0;
                }

                if ($visit->is_sequnece == 0) {
                    $visit_report[$visit->date][$salesman_id]->unPlanedJourney += 1;
                }

                // Change my hardik
                $visit_report[$visit->date][$salesman_id]->unPlanedJourney = $visit_report[$visit->date][$salesman_id]->totalJourney - $visit_report[$visit->date][$salesman_id]->planedJourney;

                $visit_report[$visit->date][$salesman_id]->unPlanedJourneyPercent = ($visit_report[$visit->date][$salesman_id]->totalJourney > 0) ? (round(($visit_report[$visit->date][$salesman_id]->unPlanedJourney / $visit_report[$visit->date][$salesman_id]->totalJourney), 2) * 100) . '%' : 0;
                $visit_report[$visit->date][$salesman_id]->totalCustomers         = $total_customers;
                $visit_report[$visit->date][$salesman_id]->strike_calls           = "";
                $visit_report[$visit->date][$salesman_id]->strike_calls_percent   = "";
                $visit_report[$visit->date][$salesman_id]->merchandiserCode       = (is_object($visit->salesman->salesmanInfo)) ? $visit->salesman->salesmanInfo->salesman_code : "";
                $visit_report[$visit->date][$salesman_id]->merchandiserName       = $visit->salesman->getName();
            }
        }

        $final_report   = array();
        $report_data    = array();
        $count          = 0;
        $date_wise_report = array();

        $startDate  = date('Y-m-d', strtotime($request->start_date));
        $endDate    = date('Y-m-d', strtotime($request->end_date));

        while ($startDate <= $endDate) {
            $report_date = $startDate;
            if (isset($visit_report[$report_date])) {
                $date_wise_report[$report_date] = $visit_report[$report_date];
            }
            $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
        }
        foreach ($date_wise_report as $visit_date => $report) {
            foreach ($report as $key => $row) {
                if (isset($row->totalCustomers)) {
                    $strike_calls           = $row->totalCustomers - $row->totalJourney;
                    $strike_calls_percent   = 0;
                    if ($row->totalCustomers > 0) {
                        $strike_calls_percent   = ($strike_calls > 0) ? round($row->totalJourney / $row->totalCustomers, 2) * 100 : 0;
                    }
                    $report[$key]->strike_calls          = $strike_calls;
                    $report[$key]->strike_calls_percent  = ROUND($strike_calls_percent, 2) . "%";
                }
                $final_report[$count]   = $row;

                $temp                         = new \stdClass();
                $temp->date                   = $visit_date;
                $temp->merchandiserCode       = $row->merchandiserCode;
                $temp->merchandiserName       = $row->merchandiserName;
                $temp->journeyPlan            = ($row->journeyPlan == 0) ? "0" : $row->journeyPlan;
                $temp->totalJourney           = ($row->totalJourney == 0) ? "0" : $row->totalJourney;
                $temp->planedJourney          = ($row->planedJourney == 0) ? "0" : $row->planedJourney;
                $temp->journeyPlanPercent     = ($row->journeyPlanPercent == 0) ? "0" : $row->journeyPlanPercent;
                $temp->unPlanedJourney        = ($row->unPlanedJourney == 0) ? "0" : $row->unPlanedJourney;
                $temp->unPlanedJourneyPercent = ($row->unPlanedJourneyPercent == 0) ? "0" : $row->unPlanedJourneyPercent;
                $temp->strike_calls           = ($strike_calls == 0) ? "0" : $strike_calls;
                $temp->strike_calls_percent   = ($strike_calls_percent == 0) ? "0%" : $strike_calls_percent . "%";

                $report_data[] = $temp;

                $count++;
            }
        }


        if ($request->export == 0) {
            $final_report = collect($final_report);
            return prepareResult(true, $final_report, [], "Route visit", $this->success);
        } else {
            $export_report  = collect($report_data);
            $file_name      = $request->user()->organisation_id . '_journey_plan_compliance.' . $request->export_type;

            Excel::store(new MerchandiserCustomerActivityExport($export_report, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function salesmanLoginLog($request)
    {
        $columns = [
            'User Id', 'IP', 'Device token', 'Vesion', 'Device Name', 'IMEI Number'
        ];

        $salesman_login_log_query = SalesmanLoginLog::select('id', 'user_id', 'ip', 'device_token', 'vesion', 'device_name', 'imei_number', 'created_at')
            ->with(
                'user:id,firstname,lastname',
                'user.salesmanInfo:id,user_id,salesman_code'
            );

        if ($request->start_date && !$request->end_date) {
            $start_date = Carbon::parse($request->start_date)->format('Y-m-d');
            $salesman_login_log_query->whereDate('created_at', $start_date);
        }

        if (!$request->start_date && $request->end_date) {
            $start_date = Carbon::parse($request->end_date)->format('Y-m-d');
            $salesman_login_log_query->whereDate('created_at', $request->end_date);
        }

        if ($request->start_date && $request->end_date) {
            $sdate = Carbon::parse($request->start_date)->format('Y-m-d');
            $edate = Carbon::parse($request->end_date)->subDays()->format('Y-m-d');
            // $edate = date('Y-m-d', strtotime('+1 days', strtotime($request->end_date)));
            if ($request->start_date == $request->end_date) {
                $salesman_login_log_query->whereDate('created_at', $sdate);
            } else {
                $salesman_login_log_query->whereBetween('created_at', [$sdate, $edate]);
            }
        }

        $salesman_login_log = $salesman_login_log_query->orderBy('created_at', 'desc')->get();

        if ($request->export == 0) {
            return prepareResult(true, $salesman_login_log, [], "Salesman login log listing", $this->success);
        } else {
            $file_name = $request->user()->organisation_id . '_merchandiser_login_log.' . $request->export_type;
            $data = array();
            if (count($salesman_login_log)) {
                foreach ($salesman_login_log as $key => $log) {
                    $salesman_login_log[$key]->date = date('Y-m-d', strtotime($log->created_at));

                    if (is_object($log->user)) {
                        $salesman_login_log[$key]->merchandiserName = $log->user->getName();
                        if (is_object($log->user->salesmanInfo)) {
                            $salesman_login_log[$key]->merchandisercode = $log->user->salesmanInfo->salesman_code;
                        } else {
                            $salesman_login_log[$key]->merchandisercode = "N/A";
                        }
                    } else {
                        $salesman_login_log[$key]->merchandiserName = 'N/A';
                        $salesman_login_log[$key]->merchandisercode = "N/A";
                    }
                    $salesman_login_log[$key]->deviceversion = $log->vesion;
                    $salesman_login_log[$key]->deviceName = $log->device_name;
                    $salesman_login_log[$key]->deviceIMEINumber  = $log->imei_number;

                    unset($salesman_login_log[$key]->id);
                    unset($salesman_login_log[$key]->organisation_id);
                    unset($salesman_login_log[$key]->ip);
                    unset($salesman_login_log[$key]->user_id);
                    unset($salesman_login_log[$key]->device_token);
                    unset($salesman_login_log[$key]->vesion);
                    unset($salesman_login_log[$key]->device_name);
                    unset($salesman_login_log[$key]->imei_number);
                    unset($salesman_login_log[$key]->created_at);
                }
            }

            Excel::store(new SalesmanLoginLogExport($salesman_login_log, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function MSLByCustomer($request)
    {
        $StartDate = $request->start_date;
        $EndDate   = $request->end_date;

        // $begin = new DateTime($StartDate);
        // if ($StartDate == $EndDate) {
        //     $e = Carbon::parse($EndDate)->addDay(1)->format('Y-m-d');
        //     $end = new DateTime($e);
        // } else {
        //     $end = new DateTime($EndDate);
        // }
        // $interval = DateInterval::createFromDateString('1 day');
        // $period = new DatePeriod($begin, $interval, $end);

        $customer_ids = $request->customer_id;
        $channel_user_item_ids = userChannelItems($request->user()->id);

        $msl = new Collection();
        if ($request->export == 0) {
            // $dis = Distribution::select('id')->get();
            // foreach ($customer_ids as $customer_id) {
            //     $dms = DistributionModelStock::select('id')
            //         ->whereIn('distribution_id', $dis)
            //         ->where('customer_id', $customer_id)
            //         ->get();

            //     if (count($dms)) {
            //         $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
            //             ->whereIn('distribution_model_stock_id', $dms)
            //             ->where('is_deleted', 0)
            //             ->first();

            //         // $ds = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
            //         //     ->where('customer_id', $customer_id)
            //         //     ->whereIn('distribution_id', $dis)
            //         //     ->where('is_out_of_stock', 1)
            //         //     ->whereBetween('created_at', [$StartDate, $EndDate])
            //         //     ->first();

            //         $ds_q = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
            //             ->whereIn('customer_id', $customer_ids)
            //             ->whereIn('distribution_id', $dis)
            //             ->where('is_out_of_stock', 1);
            //         if ($StartDate == $EndDate) {
            //             $ds_q->whereDate('created_at', $StartDate);
            //         } else {
            //             $ds_q->whereBetween('created_at', [$StartDate, $EndDate]);
            //         }
            //         $ds = $ds_q->first();

            //         $customerInfo = CustomerInfo::where('user_id', $customer_id)->first();

            //         $Total_MSL_Item            = $dmsd->total_msl_item;
            //         $Total_Out_Of_Stock_Item   = $ds->total_out_of_stock;

            //         $per = 0;
            //         if ($Total_MSL_Item != 0 && $Total_Out_Of_Stock_Item != 0) {
            //             $per = round($Total_Out_Of_Stock_Item / $Total_MSL_Item * 100, 2);
            //         }

            //         $msl->push((object) [
            //             'date'                      => $StartDate,
            //             'customer_code'             => model($customerInfo, 'customer_code'),
            //             'customer_name'             => $customerInfo->user->getName(),
            //             'Total_MSL_Item'            => ($Total_MSL_Item > 0) ? $Total_MSL_Item : '0',
            //             'Total_Out_Of_Stock_Item'   => ($Total_Out_Of_Stock_Item > 0) ? $Total_Out_Of_Stock_Item : '0',
            //             'Msl_Percentage'            => ($per > 0) ? $per : '0',
            //             'customer_id'               => ($request->export == 0) ? model($customerInfo, 'user_id') : "",
            //         ]);
            //     }
            // }

            foreach ($customer_ids as $customer_id) {
                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->where('customer_id', $customer_id);

                if ($StartDate != '' && $EndDate != '') {
                    if ($StartDate == $EndDate) {
                        $visit->whereDate('date', $StartDate);
                    } else {
                        $visit->whereBetween('date', [$StartDate, $EndDate]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                $out_of_stock_count = array();
                $item_count = array();

                foreach ($visits as $v) {
                    $ds = DistributionStock::select(
                        DB::raw(
                            '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                        ),
                        DB::raw(
                            'COUNT(item_id) as item_count'
                        )
                    )
                        ->where('customer_id', $v->customer_id)
                        ->whereDate('created_at', $v->dates)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->first();

                    $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                    $item_count[]         = $ds->item_count; // item count
                }

                $customerInfo = CustomerInfo::where('user_id', $customer_id)->first();

                $total_msl_out_of_stock_items = array_sum($out_of_stock_count);
                $total_msl_items = array_sum($item_count);

                $per = 0;

                if ($total_msl_items != 0) {
                    $per = round(($total_msl_items - $total_msl_out_of_stock_items) / $total_msl_items * 100, 2);
                }

                $customer_max_msl = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer_id)->max('total_msl_item');
            

                $customer_max_msl_check = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer_id)->max('msl_item_perform');
                $devide = $customer_max_msl == 0 ? 1 : $customer_max_msl;
                $percentage = round(($customer_max_msl_check/$devide)*100);

                $msl->push((object) [
                    'date'                      => $StartDate,
                    'customer_code'             => model($customerInfo, 'customer_code'),
                    'customer_name'             => $customerInfo->user->getName(),
                    //'Total_MSL_Item'            => ($total_msl_items > 0) ? $total_msl_items : '0',
                    'Total_MSL_Item'            => ($total_msl_items > 0) ? $total_msl_items : '0',
                    'Total_MSL_Item'            => $customer_max_msl,
                    'Msl_Check_Item'            => $customer_max_msl_check,
                    'Total_Out_Of_Stock_Item'   => ($total_msl_out_of_stock_items > 0) ? $total_msl_out_of_stock_items : '0',
                    //'Msl_Percentage'            => ($per > 0) ? $per : '0',
                    'Msl_Percentage'            => $percentage,
                    'customer_id'               => ($request->export == 0) ? model($customerInfo, 'user_id') : "",
                ]);
            }
            //dd($customerInfo->user);

            return prepareResult(true, $msl, [], "MSL By Customer listing", $this->success);
        } else {
            $columns = [
                "Date",
                "Customer Code",
                "Customer Name",
                // "Customer Channel",
                // "Merchandiser Code",
                // "Merchandiser Name",
                // "Item code",
                // "Item Name",
                // "Item Brand",
                // "Item Category",
                // "Model capacity",
                "Out Of Stock Item",
                'Total MSL Item',
                'Msl Check Item',
                'Msl Percentage'
            ];
            // foreach ($period as $dt) {
            //     $date = $dt->format("Y-m-d");

            //     $dis = Distribution::select('id')->get();
            //     foreach ($customer_ids as $customer) {
            //         $dms = DistributionModelStock::select('id', 'customer_id')
            //             ->whereIn('distribution_id', $dis)
            //             ->where('customer_id', $customer)
            //             ->get();
            //         if (count($dms)) {
            //             $d_m_s_ids = $dms->pluck('id')->toArray();
            //             $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $d_m_s_ids)
            //                 ->where('is_deleted', 0)
            //                 ->get();

            //             if (count($dmsd)) {
            //                 foreach ($dmsd as $d) {

            //                     $ds = DistributionStock::where('customer_id', $customer)
            //                         ->whereIn('distribution_id', $dis)
            //                         ->where('is_out_of_stock', 1)
            //                         ->where('item_id', $d->item_id)
            //                         ->whereDate('created_at', $date)
            //                         ->first();

            //                     $customerInfo = CustomerInfo::where('user_id', $customer)->first();

            //                     $channel = "N/A";
            //                     if (is_object($customerInfo->channel)) {
            //                         $channel = $customerInfo->channel->name;
            //                     }

            //                     $msl->push((object) [
            //                         "Date"              => $date,
            //                         "Customer_Code"     => model($customerInfo, 'customer_code'),
            //                         "Customer_Name"     => $customerInfo->user->getName(),
            //                         "Customer_Channel"  => $channel,
            //                         "Item_code"         => (is_object($d->item)) ? $d->item->item_code : "N/A",
            //                         "Item_Name"         => (is_object($d->item)) ? $d->item->item_name : "N/A",
            //                         "Model_capacity"    => (isset($d->capacity)) ? $d->capacity : "0",
            //                         "Out_Of_Stock_Item" => (isset($ds->is_out_of_stock)) ? "Yes" : "No",
            //                         "Check_in"          => (is_object($ds)) ? "Yes" : "No"
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
            // }


            // $msls = DB::table('distribution_stocks')
            //     ->select(
            //         DB::raw("DATE_FORMAT(distribution_stocks.created_at, '%d-%m-%Y') as date"),
            //         'customer_infos.customer_code as customer_code',
            //         'c_user.firstname as customer_name',
            //         'channels.name as channel_name',
            //         'salesman_infos.salesman_code as salesman_code',
            //         's_user.firstname as salesman_name',
            //         'items.item_code as item_code',
            //         'items.item_name as item_name',
            //         'brands.brand_name as brand',
            //         'imc.name as category',
            //         'distribution_stocks.capacity as Model_capacity',
            //         'distribution_stocks.is_out_of_stock as out_of_stock'
            //     )
            //     ->join('customer_infos', 'customer_infos.user_id', '=', 'distribution_stocks.customer_id')
            //     ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
            //     ->join('users as c_user', 'c_user.id', '=', 'customer_infos.user_id')
            //     ->join('salesman_infos', 'salesman_infos.user_id', '=', 'distribution_stocks.salesman_id')
            //     ->join('users as s_user', 's_user.id', '=', 'salesman_infos.user_id')
            //     ->join('items', 'items.id', '=', 'distribution_stocks.item_id')
            //     ->join('brands', 'brands.id', '=', 'items.brand_id')
            //     ->join('item_major_categories as imc', 'imc.id', '=', 'items.item_major_category_id')
            //     ->whereIn('distribution_stocks.customer_id', $customer_ids)
            //     ->whereBetween('distribution_stocks.created_at', [$StartDate, $EndDate])
            //     ->get();

            // $ds_item_ids = DistributionStock::select('item_id')
            //     ->whereIn('customer_id', $customer_ids)
            //     ->whereBetween('created_at', [$StartDate, $EndDate])
            //     ->get();

            // $dms = DistributionModelStock::select('id', 'customer_id')
            //     ->whereIn('customer_id', $customer_ids)
            //     ->get();

            // foreach ($msls as $key => $msl) {
            //     if (isset($msl->out_of_stock) && $msl->out_of_stock == 1) {
            //         $msls[$key]->out_of_stock    = "Yes";
            //     } else {
            //         $msls[$key]->out_of_stock    = "No";
            //     }
            //     $msls[$key]->msl_performance = "Yes";
            // }

            // $dmsd = array();
            // if (count($dms)) {
            //     $d_m_s_ids = $dms->pluck('id')->toArray();
            //     $dmsd = DB::table('distribution_model_stock_details')
            //         ->select(
            //             DB::raw("$StartDate as date"),
            //             'ci.customer_code as customer_code',
            //             'c_user.firstname as customer_name',
            //             'channels.name as channel_name',
            //             'si.salesman_code as salesman_code',
            //             's_user.firstname as salesman_name',
            //             'items.item_code as item_code',
            //             'items.item_name as item_name',
            //             'brands.brand_name as brand',
            //             'imc.name as category',
            //             'distribution_model_stock_details.capacity',
            //         )
            //         ->join('distribution_model_stocks as ds', 'ds.id', '=', 'distribution_model_stock_details.distribution_model_stock_id')
            //         ->join('customer_infos as ci', 'ci.user_id', '=', 'ds.customer_id')
            //         ->join('users as c_user', 'c_user.id', '=', 'ci.user_id')
            //         ->join('channels', 'channels.id', '=', 'ci.channel_id')
            //         ->join('customer_merchandisers as cm', 'cm.customer_id', '=', 'ci.user_id')
            //         ->join('salesman_infos as si', 'si.user_id', '=', 'cm.merchandiser_id')
            //         ->join('users as s_user', 's_user.id', '=', 'cm.merchandiser_id')
            //         ->join('items', 'items.id', '=', 'distribution_model_stock_details.item_id')
            //         ->join('brands', 'brands.id', '=', 'items.brand_id')
            //         ->join('item_major_categories as imc', 'imc.id', '=', 'items.item_major_category_id')
            //         ->whereIn('distribution_model_stock_id', $d_m_s_ids)
            //         ->where('distribution_model_stock_details.is_deleted', 0)
            //         ->whereNotIn('item_id', $ds_item_ids)
            //         ->get();
            // }

            // if (count($dmsd)) {
            //     foreach ($dmsd as $key => $msl) {
            //         if (isset($msl->out_of_stock) && $msl->out_of_stock == 1) {
            //             $dmsd[$key]->out_of_stock    = "Yes";
            //         } else {
            //             $dmsd[$key]->out_of_stock    = "No";
            //         }
            //         $dmsd[$key]->msl_performance = "No";
            //     }
            // }

            // $final_array = $msls->merge($dmsd);


            $final_array = new Collection();

            foreach ($customer_ids as $customer_id) {
                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->where('customer_id', $customer_id);

                if ($StartDate != '' && $EndDate != '') {
                    if ($StartDate == $EndDate) {
                        $visit->whereDate('date', $StartDate);
                    } else {
                        $visit->whereBetween('date', [$StartDate, $EndDate]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                $out_of_stock_count = array();
                $item_count = array();

                foreach ($visits as $v) {
                    $ds = DistributionStock::select(
                        DB::raw(
                            '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                        ),
                        DB::raw(
                            'COUNT(item_id) as item_count'
                        )
                    )
                        ->where('customer_id', $v->customer_id)
                        ->whereDate('created_at', $v->dates)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->first();

                    $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                    $item_count[]         = $ds->item_count; // item count
                }

                $customerInfo = CustomerInfo::where('user_id', $customer_id)->first();

                $total_msl_out_of_stock_items = array_sum($out_of_stock_count);
                $total_msl_items = array_sum($item_count);

                $per = 0;

                if ($total_msl_items != 0) {
                    $per = round(($total_msl_items - $total_msl_out_of_stock_items) / $total_msl_items * 100, 2);
                }

                $customer_max_msl = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer_id)->max('total_msl_item');
            

                $customer_max_msl_check = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer_id)->max('msl_item_perform');
                $devide = $customer_max_msl == 0 ? 1 : $customer_max_msl;
                $percentage = round(($customer_max_msl_check/$devide)*100);

                $final_array->push((object) [
                    'date'                      => $StartDate,
                    'customer_code'             => model($customerInfo, 'customer_code'),
                    'customer_name'             => $customerInfo->user->getName(),
                    'Total_Out_Of_Stock_Item'   => ($total_msl_out_of_stock_items > 0) ? $total_msl_out_of_stock_items : '0',
                    'Total_MSL_Item'            => $customer_max_msl,
                    'Msl_Check_Item'            => $customer_max_msl_check,
                    
                    'Msl_Percentage'            => $percentage,
                    //'customer_id'               => ($request->export == 0) ? model($customerInfo, 'user_id') : "",
                ]);
            }

            // $customer_merchadiser = CustomerMerchandiser::select('customer_id')
            //     ->whereIn('customer_id', $customer_ids)
            //     ->get();

            // $visit = CustomerVisit::select('customer_id')
            //     ->whereIn('customer_id', $customer_merchadiser);
            // if ($StartDate == $EndDate) {
            //     $visit->where('date', $StartDate);
            // } else {
            //     $visit->whereBetween('date', [$StartDate, $EndDate]);
            // }
            // $visits = $visit->groupBy('customer_id')->get();

            // $visit = DB::table('customer_visits')
            //     ->select(
            //         DB::raw('customer_id, max(date) as dates')
            //     )
            //     ->whereIn('customer_id', $customer_ids);

            // if ($StartDate != '' && $EndDate != '') {
            //     if ($StartDate == $EndDate) {
            //         $visit->whereDate('date', $StartDate);
            //     } else {
            //         $visit->whereBetween('date', [$StartDate, $EndDate]);
            //     }
            // }

            // $visits = $visit->groupBy('customer_id')
            //     ->orderBy('dates', 'desc')
            //     ->get();

            // if (count($visits)) {
            //     foreach ($visits as $v) {
            //         $ds = DistributionStock::where('customer_id', $v->customer_id)
            //             ->whereDate('created_at', $v->dates)
            //             ->whereIn('item_id', $channel_user_item_ids)
            //             ->get();

            //         $customerInfo = CustomerInfo::where('user_id', $v->customer_id)->first();

            //         if (count($ds)) {
            //             foreach ($ds as $d) {
            //                 $salesmanInfo = SalesmanInfo::where('user_id', $d->salesman_id)->first();
            //                 $item = Item::find($d->item_id);

            //                 $final_array->push((object) [
            //                     'date'                      => $StartDate,
            //                     'customer_code'             => model($customerInfo, 'customer_code'),
            //                     'customer_name'             => $customerInfo->user->getName(),
            //                     'channel_name'              => $customerInfo->channel->name,
            //                     'salesman_code'             => model($salesmanInfo, 'salesman_code'),
            //                     'salesman_name'             => is_object($salesmanInfo) ? $salesmanInfo->user->getName() : "N/A",
            //                     'item_code'                 => $item->item_code,
            //                     'item_name'                 => $item->item_name,
            //                     'brand_name'                => $item->brand->brand_name,
            //                     'category'                  => $item->itemMajorCategory->name,
            //                     'capacity'                  => is_object($d) ? $d->capacity : '0',
            //                     'out_of_stock'              => ($d->is_out_of_stock == 1) ? "Yes" : "No",
            //                     'msl_performance'           => is_object($d) ? "Yes" : "No"
            //                 ]);
            //             }
            //         }
            //     }
            // }

            // if (count($visits)) {

            //     foreach ($visits as $v) {

            //         $dms = DistributionModelStock::select('id')
            //             ->where('customer_id', $v->customer_id)
            //             ->get();

            //         if (count($dms)) {

            //             $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $dms)
            //                 ->where('is_deleted', 0)
            //                 ->get();
            //             if (count($dmsd)) {
            //                 foreach ($dmsd as $dd) {
            //                     // stock
            //                     $ds_q = DistributionStock::where('customer_id', $v->customer_id)
            //                         ->where('item_id', $dd->item_id);
            //                     if ($StartDate == $EndDate) {
            //                         $ds_q->whereDate('created_at', $StartDate);
            //                     } else {
            //                         $ds_q->whereBetween('created_at', [$StartDate, $EndDate]);
            //                     }
            //                     $ds = $ds_q->first();

            //                     $customerInfo = CustomerInfo::where('user_id', $v->customer_id)->first();

            //                     if (isset($ds->salesman_id)) {
            //                         $salesmanInfo = SalesmanInfo::where('user_id', $ds->salesman_id)->first();
            //                     } else {
            //                         $cm = CustomerMerchandiser::where('customer_id', $v->customer_id)
            //                             ->first();
            //                         if ($cm) {
            //                             $salesmanInfo = SalesmanInfo::where('user_id', $cm->merchandiser_id)->first();
            //                         }
            //                     }

            //                     $item = Item::find($dd->item_id);

            //                     $final_array->push((object) [
            //                         'date'                      => $StartDate,
            //                         'customer_code'             => model($customerInfo, 'customer_code'),
            //                         'customer_name'             => $customerInfo->user->getName(),
            //                         'channel_name'              => $customerInfo->channel->name,
            //                         'salesman_code'             => model($salesmanInfo, 'salesman_code'),
            //                         'salesman_name'             => is_object($salesmanInfo) ? $salesmanInfo->user->getName() : "N/A",
            //                         'item_code'                 => $item->item_code,
            //                         'item_name'                 => $item->item_name,
            //                         'brand_name'                => $item->brand->brand_name,
            //                         'category'                  => $item->itemMajorCategory->name,
            //                         'capacity'                  => is_object($ds) ? $ds->capacity : $dd->capacity,
            //                         'out_of_stock'              => isset($ds->is_out_of_stock) ? "Yes" : "No",
            //                         'msl_performance'           => is_object($ds) ? "Yes" : "No"
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
            // }

            $file_name = $request->user()->organisation_id . time() . '_msl_by_cusotmer.' . $request->export_type;
            Excel::store(new MSLByCustomerExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function MSLByChannel($request)
    {
        $StartDate = $request->start_date;
        $EndDate   = $request->end_date;

        // $begin = new DateTime($StartDate);
        // if ($StartDate == $EndDate) {
        //     $e = Carbon::parse($EndDate)->addDay(1)->format('Y-m-d');
        //     $end = new DateTime($e);
        // } else {
        //     $end = new DateTime($EndDate);
        // }
        // $interval = DateInterval::createFromDateString('1 day');
        // $period = new DatePeriod($begin, $interval, $end);

        $channel_ids = $request->channel_id;
        $user = request()->user();
        $all_salesman_customer = getSalesman(true, $user->id);

        $msl = new Collection();
        $channel_user_item_ids = userChannelItems($request->user()->id);

        if ($request->export == 0) {
            // foreach ($period as $dt) {
            //     $date = $dt->format("Y-m-d");

            // }

            foreach ($channel_ids as $channel_id) {
                $out_of_stock_count = array();
                $item_count = array();
                $channel = Channel::find($channel_id);
                $channel_customer_ids = channelCustomers($channel_id);
                $customer_ids = array_intersect($all_salesman_customer, $channel_customer_ids);

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->whereIn('customer_id', $customer_ids);

                if ($StartDate != '' && $EndDate != '') {
                    if ($StartDate == $EndDate) {
                        $visit->whereDate('date', $StartDate);
                    } else {
                        $visit->whereBetween('date', [$StartDate, $EndDate]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();

                if (count($visits)) {
                    foreach ($visits as $v) {

                        $ds = DistributionStock::select(
                            DB::raw(
                                '(case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end) as out_of_stock',
                            ),
                            DB::raw(
                                'COUNT(item_id) as item_count'
                            ),
                            DB::raw(
                                'round((COUNT(item_id) - (case when (is_out_of_stock > 0) then sum(is_out_of_stock) else 0 end)) / COUNT(item_id) , 2) as per'
                            )
                        )
                            ->where('customer_id', $v->customer_id)
                            ->whereDate('created_at', $v->dates)
                            ->whereIn('item_id', $channel_user_item_ids)
                            ->first();

                        $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                        $item_count[]         = $ds->item_count; // item count
                    }
                }

                $total_msl_out_of_stock_items = array_sum($out_of_stock_count);
                $total_msl_items = array_sum($item_count);

                $per = 0;
                if ($total_msl_items != 0) {
                    $per = round(($total_msl_items - $total_msl_out_of_stock_items) / $total_msl_items * 100, 2);
                }

                $msl->push((object) [
                    'date'                      => $StartDate,
                    'channel_name'              => model($channel, 'name'),
                    'Total_MSL_Item'            => ($total_msl_items > 0) ? $total_msl_items : '0',
                    'Total_Out_Of_Stock_Item'   => ($total_msl_out_of_stock_items > 0) ? $total_msl_out_of_stock_items : '0',
                    'Msl_Percentage'            => ($per > 0) ? $per : '0',
                    'channe_id'                 => ($request->export == 0) ? model($channel, 'id') : "",
                ]);
            }
            return prepareResult(true, $msl, [], "MSL By Channel listing", $this->success);
        } else {
            $columns = [
                "Date",
                "Customer Code",
                "Customer Name",
                "Customer Channel",
                "Merchandiser Code",
                "Merchandiser Name",
                "Item code",
                "Item Name",
                "Item Brand",
                "Item Category",
                "Model capacity",
                "Out Of Stock Item",
                "MSL Perform"
            ];

            $channel_ids = $request->channel_id;
            $channel_customer_ids = channelCustomers($channel_ids);
            $customer_ids = array_intersect($all_salesman_customer, $channel_customer_ids);

            $final_array = new Collection();

            // $visit = CustomerVisit::select('customer_id')
            //     ->whereIn('customer_id', $customer_ids);
            // if ($StartDate == $EndDate) {
            //     $visit->where('date', $StartDate);
            // } else {
            //     $visit->whereBetween('date', [$StartDate, $EndDate]);
            // }
            // $visits = $visit->groupBy('customer_id')->get();

            // if (count($visits)) {

            //     foreach ($visits as $v) {

            //         $dms = DistributionModelStock::select('id')
            //             ->where('customer_id', $v->customer_id)
            //             ->get();

            //         if (count($dms)) {

            //             $dmsd = DistributionModelStockDetails::whereIn('distribution_model_stock_id', $dms)
            //                 ->where('is_deleted', 0)
            //                 ->get();
            //             if (count($dmsd)) {
            //                 foreach ($dmsd as $dd) {
            //                     $ds_q = DistributionStock::where('customer_id', $v->customer_id)
            //                         ->where('item_id', $dd->item_id);
            //                     if ($StartDate == $EndDate) {
            //                         $ds_q->whereDate('created_at', $StartDate);
            //                     } else {
            //                         $ds_q->whereBetween('created_at', [$StartDate, $EndDate]);
            //                     }
            //                     $ds = $ds_q->first();

            //                     $customerInfo = CustomerInfo::where('user_id', $v->customer_id)->first();

            //                     if (isset($ds->salesman_id)) {
            //                         $salesmanInfo = SalesmanInfo::where('user_id', $ds->salesman_id)->first();
            //                     } else {
            //                         $cm = CustomerMerchandiser::where('customer_id', $v->customer_id)
            //                             ->first();
            //                         if ($cm) {
            //                             $salesmanInfo = SalesmanInfo::where('user_id', $cm->merchandiser_id)->first();
            //                         }
            //                     }

            //                     $item = Item::find($dd->item_id);

            //                     $final_array->push((object) [
            //                         'date'                      => $StartDate,
            //                         'customer_code'             => model($customerInfo, 'customer_code'),
            //                         'customer_name'             => $customerInfo->user->getName(),
            //                         'channel_name'              => $customerInfo->channel->name,
            //                         'salesman_code'             => model($salesmanInfo, 'salesman_code'),
            //                         'salesman_name'             => is_object($salesmanInfo) ? $salesmanInfo->user->getName() : "N/A",
            //                         'item_code'                 => $item->item_code,
            //                         'item_name'                 => $item->item_name,
            //                         'brand_name'                => $item->brand->brand_name,
            //                         'category'                  => $item->itemMajorCategory->name,
            //                         'capacity'                  => is_object($ds) ? $ds->capacity : $dd->capacity,
            //                         'out_of_stock'              => isset($ds->is_out_of_stock) ? "Yes" : "No",
            //                         'msl_performance'           => is_object($ds) ? "Yes" : "No"
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
            // }

            $visit = DB::table('customer_visits')
                ->select(
                    DB::raw('customer_id, max(date) as dates')
                )
                ->whereIn('customer_id', $customer_ids);

            if ($StartDate != '' && $EndDate != '') {
                if ($StartDate == $EndDate) {
                    $visit->whereDate('date', $StartDate);
                } else {
                    $visit->whereBetween('date', [$StartDate, $EndDate]);
                }
            }

            $visits = $visit->groupBy('customer_id')
                ->orderBy('dates', 'desc')
                ->get();

            if (count($visits)) {

                foreach ($visits as $v) {
                    $ds = DistributionStock::where('customer_id', $v->customer_id)
                        ->whereDate('created_at', $v->dates)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->get();

                    $customerInfo = CustomerInfo::where('user_id', $v->customer_id)->first();

                    if (count($ds)) {
                        foreach ($ds as $d) {
                            $salesmanInfo = SalesmanInfo::where('user_id', $d->salesman_id)->first();
                            $item = Item::find($d->item_id);

                            $final_array->push((object) [
                                'date'                      => $StartDate,
                                'customer_code'             => model($customerInfo, 'customer_code'),
                                'customer_name'             => $customerInfo->user->getName(),
                                'channel_name'              => $customerInfo->channel->name,
                                'salesman_code'             => model($salesmanInfo, 'salesman_code'),
                                'salesman_name'             => is_object($salesmanInfo) ? $salesmanInfo->user->getName() : "N/A",
                                'item_code'                 => $item->item_code,
                                'item_name'                 => $item->item_name,
                                'brand_name'                => $item->brand->brand_name,
                                'category'                  => $item->itemMajorCategory->name,
                                'capacity'                  => is_object($d) ? $d->capacity : '0',
                                'out_of_stock'              => ($d->is_out_of_stock == 1) ? "Yes" : "No",
                                'msl_performance'           => is_object($d) ? "Yes" : "No"
                            ]);
                        }
                    }
                }
            }

            // $msls = DB::table('distribution_stocks')
            //     ->select(
            //         DB::raw("DATE_FORMAT(distribution_stocks.created_at, '%d-%m-%Y') as date"),
            //         'customer_infos.customer_code as customer_code',
            //         'c_user.firstname as customer_name',
            //         'channels.name as channel_name',
            //         'salesman_infos.salesman_code as salesman_code',
            //         's_user.firstname as salesman_name',
            //         'items.item_code as item_code',
            //         'items.item_name as item_name',
            //         'brands.brand_name as brand',
            //         'imc.name as category',
            //         'distribution_stocks.capacity as Model_capacity',
            //         'distribution_stocks.is_out_of_stock as out_of_stock'
            //     )
            //     ->join('customer_infos', 'customer_infos.user_id', '=', 'distribution_stocks.customer_id')
            //     ->join('channels', 'channels.id', '=', 'customer_infos.channel_id')
            //     ->join('users as c_user', 'c_user.id', '=', 'customer_infos.user_id')
            //     ->join('salesman_infos', 'salesman_infos.user_id', '=', 'distribution_stocks.salesman_id')
            //     ->join('users as s_user', 's_user.id', '=', 'salesman_infos.user_id')
            //     ->join('items', 'items.id', '=', 'distribution_stocks.item_id')
            //     ->join('brands', 'brands.id', '=', 'items.brand_id')
            //     ->join('item_major_categories as imc', 'imc.id', '=', 'items.item_major_category_id')
            //     // ->whereIn('distribution_stocks.customer_id', $channel_customer_ids)
            //     ->whereIn('channels.id', $channel_ids)
            //     ->whereBetween('distribution_stocks.created_at', [$StartDate, $EndDate])
            //     ->get();

            // $ds_item_ids = DistributionStock::select('item_id')
            //     ->whereIn('customer_id', $channel_customer_ids)
            //     ->whereBetween('created_at', [$StartDate, $EndDate])
            //     ->get();

            // $dms = DistributionModelStock::select('id', 'customer_id')
            //     ->whereIn('customer_id', $channel_customer_ids)
            //     ->get();

            // foreach ($msls as $key => $msl) {
            //     if (isset($msl->out_of_stock) && $msl->out_of_stock == 1) {
            //         $msls[$key]->out_of_stock    = "Yes";
            //     } else {
            //         $msls[$key]->out_of_stock    = "No";
            //     }
            //     $msls[$key]->msl_performance = "Yes";
            // }

            // $dmsd = array();
            // if (count($dms)) {
            //     $d_m_s_ids = $dms->pluck('id')->toArray();
            //     $dmsd = DB::table('distribution_model_stock_details')
            //         ->select(
            //             DB::raw("$StartDate as date"),
            //             'ci.customer_code as customer_code',
            //             'c_user.firstname as customer_name',
            //             'channels.name as channel_name',
            //             'si.salesman_code as salesman_code',
            //             's_user.firstname as salesman_name',
            //             'items.item_code as item_code',
            //             'items.item_name as item_name',
            //             'distribution_model_stock_details.capacity',
            //         )
            //         ->join('distribution_model_stocks as ds', 'ds.id', '=', 'distribution_model_stock_details.distribution_model_stock_id')
            //         ->join('customer_infos as ci', 'ci.user_id', '=', 'ds.customer_id')
            //         ->join('users as c_user', 'c_user.id', '=', 'ci.user_id')
            //         ->join('channels', 'channels.id', '=', 'ci.channel_id')
            //         ->join('customer_merchandisers as cm', 'cm.customer_id', '=', 'ci.user_id')
            //         ->join('salesman_infos as si', 'si.user_id', '=', 'cm.merchandiser_id')
            //         ->join('users as s_user', 's_user.id', '=', 'cm.merchandiser_id')
            //         ->join('items', 'items.id', '=', 'distribution_model_stock_details.item_id')
            //         // ->whereIn('distribution_model_stock_id', $d_m_s_ids)
            //         ->whereIn('channels.id', $channel_ids)
            //         ->where('distribution_model_stock_details.is_deleted', 0)
            //         ->whereNotIn('item_id', $ds_item_ids)
            //         ->get();
            // }

            // if (count($dmsd)) {
            //     foreach ($dmsd as $key => $msl) {
            //         if (isset($msl->out_of_stock) && $msl->out_of_stock == 1) {
            //             $dmsd[$key]->out_of_stock    = "Yes";
            //         } else {
            //             $dmsd[$key]->out_of_stock    = "No";
            //         }
            //         $dmsd[$key]->msl_performance = "No";
            //     }
            // }

            // $final_array = $msls->merge($dmsd);

            $file_name = $request->user()->organisation_id . time() . '_msl_by_channel.' . $request->export_type;
            Excel::store(new MSLByCustomerExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function MSLBySalesman($request)
    {
        $StartDate = $request->start_date;
        $EndDate   = $request->end_date;

        if (!is_array($request->salesman_id)) {
            return false;
        }
        if (sizeof($request->salesman_id) < 1) {
            return false;
        }

        // $begin = new DateTime($StartDate);
        // if ($StartDate == $EndDate) {
        //     $e = Carbon::parse($EndDate)->addDay(1)->format('Y-m-d');
        //     $end = new DateTime($e);
        // } else {
        //     $end = new DateTime($EndDate);
        // }
        // $interval = DateInterval::createFromDateString('1 day');
        // $period = new DatePeriod($begin, $interval, $end);

        $salesman_ids = $request->salesman_id;
        $channel_user_item_ids = userChannelItems($request->user()->id);

        $msl = new Collection();
        if ($request->export == 0) {
            // $dis = Distribution::select('id')->get();

            foreach ($salesman_ids as $salesman_id) {

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->where('salesman_id', $salesman_id);

                if ($StartDate != '' && $EndDate != '') {
                    if ($StartDate == $EndDate) {
                        $visit->whereDate('date', $StartDate);
                    } else {
                        $visit->whereBetween('date', [$StartDate, $EndDate]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();


                // $customer_merchadiser = CustomerMerchandiser::select('customer_id')
                //     ->where('merchandiser_id', $salesman_id)
                //     ->get();

                // $visit = CustomerVisit::select('customer_id')
                //     ->whereIn('customer_id', $customer_merchadiser)
                //     ->where('salesman_id', $salesman_id);
                // if ($StartDate == $EndDate) {
                //     $visit->where('date', $StartDate);
                // } else {
                //     $visit->whereBetween('date', [$StartDate, $EndDate]);
                // }
                // $visits = $visit->groupBy('customer_id')->get();

                // $dms = DistributionModelStock::select('id')
                //     ->whereIn('distribution_id', $dis)
                //     ->whereIn('customer_id', $visits)
                //     ->get();

                // if (count($dms)) {

                // $dmsd = DistributionModelStockDetails::select(DB::raw('COUNT(item_id) as total_msl_item'))
                //     ->whereIn('distribution_model_stock_id', $dms)
                //     ->where('is_deleted', 0)
                //     ->first();

                // $ds_q = DistributionStock::select(DB::raw('COUNT(is_out_of_stock) as total_out_of_stock'))
                //     ->where('salesman_id', $salesman_id)
                //     ->whereIn('distribution_id', $dis)
                //     ->where('is_out_of_stock', 1);
                // if ($StartDate == $EndDate) {
                //     $ds_q->whereDate('created_at', $StartDate);
                // } else {
                //     $ds_q->whereBetween('created_at', [$StartDate, $EndDate]);
                // }
                // $ds = $ds_q->first();

                $out_of_stock_count = array();
                $item_count = array();

                foreach ($visits as $v) {
                    $ds = DistributionStock::select(
                        DB::raw(
                            '(sum(is_out_of_stock > 0)) as out_of_stock',
                        ),
                        DB::raw(
                            'COUNT(item_id) as item_count'
                        )
                    )
                        ->where('customer_id', $v->customer_id)
                        ->where('salesman_id', $salesman_id)
                        ->whereDate('created_at', $v->dates)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->first();

                    $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                    $item_count[]         = $ds->item_count; // item count

                    // $Total_MSL_Item            = isset($ds->item_count) ? $ds->item_count : '0';
                    // $Total_Out_Of_Stock_Item   = isset($ds->out_of_stock) ? $ds->out_of_stock : '0';

                    // $per = 0;
                    // if ($Total_MSL_Item != 0 && $Total_Out_Of_Stock_Item != 0) {
                    //     $per = round(($Total_MSL_Item - $Total_Out_Of_Stock_Item) / $Total_MSL_Item * 100);
                    // }
                }

                $salesmanInfo = SalesmanInfo::where('user_id', $salesman_id)->first();

                $total_msl_out_of_stock_items = array_sum($out_of_stock_count);
                $total_msl_items = array_sum($item_count);

                $per = 0;
                if ($total_msl_items != 0) {
                    $per = round(($total_msl_items - $total_msl_out_of_stock_items) / $total_msl_items * 100, 2);
                }
                $msldata = DB::table('merchandiser_msl_compliances')->where('merchandiser_id',$salesman_id)->whereDate('date', $StartDate)->first();
                //dd($msl);
                $msl->push((object) [
                    'date'                      => $StartDate,
                    'salesman_code'             => model($salesmanInfo, 'salesman_code'),
                    'salesman_name'             => $salesmanInfo->user->getName(),
                    //'Total_MSL_Item'            => ($total_msl_items > 0) ? $total_msl_items : '0',
                    
                    'Total_Out_Of_Stock_Item'   => ($total_msl_out_of_stock_items > 0) ? $total_msl_out_of_stock_items : '0',
                    //'Msl_Percentage'            => ($per > 0) ? $per : '0',
                    'Total_MSL_Item'            => $msldata->total_msl_item ?? 0,
                    'Msl_Check_Item'            => $msldata->msl_check_item ?? 0,
                    'Msl_Percentage'            => $msldata->msl_percentage ?? 0,
                    'salesman_id'               => ($request->export == 0) ? model($salesmanInfo, 'user_id') : "",
                ]);
                // }
            }

            return prepareResult(true, $msl, [], "MSL By Salesman listing", $this->success);
        } else {
            $columns = [
                "Date",
                // "Customer Code",
                // "Customer Name",
                // "Customer Channel",
                "Merchandiser Code",
                "Merchandiser Name",
                // "Item code",
                // "Item Name",
                // "Brand Name",
                // "Category Name",
                // "Model capacity",
                "Out Of Stock",
                // "MSL Perform",
                'Total MSL Item',
                'Msl Percentage',
                'Msl CHeck Item'
            ];

            $final_array = new Collection();

            foreach ($salesman_ids as $salesman_id) {

                $visit = DB::table('customer_visits')
                    ->select(
                        DB::raw('customer_id, max(date) as dates')
                    )
                    ->where('salesman_id', $salesman_id);

                if ($StartDate != '' && $EndDate != '') {
                    if ($StartDate == $EndDate) {
                        $visit->whereDate('date', $StartDate);
                    } else {
                        $visit->whereBetween('date', [$StartDate, $EndDate]);
                    }
                }

                $visits = $visit->groupBy('customer_id')
                    ->orderBy('dates', 'desc')
                    ->get();
                $out_of_stock_count = array();
                $item_count = array();
                foreach ($visits as $v) {
                    $ds = DistributionStock::select(
                        DB::raw(
                            '(sum(is_out_of_stock > 0)) as out_of_stock',
                        ),
                        DB::raw(
                            'COUNT(item_id) as item_count'
                        )
                    )
                        ->where('customer_id', $v->customer_id)
                        ->where('salesman_id', $salesman_id)
                        ->whereDate('created_at', $v->dates)
                        ->whereIn('item_id', $channel_user_item_ids)
                        ->first();

                    $out_of_stock_count[] = $ds->out_of_stock; // Out of stock count
                    $item_count[]         = $ds->item_count; // item count
                }
                $salesmanInfo = SalesmanInfo::where('user_id', $salesman_id)->first();
                $total_msl_out_of_stock_items = array_sum($out_of_stock_count);
                $total_msl_items = array_sum($item_count);

                $per = 0;
                if ($total_msl_items != 0) {
                    $per = round(($total_msl_items - $total_msl_out_of_stock_items) / $total_msl_items * 100, 2);
                }
                $msldata = DB::table('merchandiser_msl_compliances')->where('merchandiser_id',$salesman_id)->whereDate('date', $StartDate)->first();
                //dd($msl);
                $final_array->push((object) [
                    'date'                      => $StartDate,
                    'salesman_code'             => model($salesmanInfo, 'salesman_code'),
                    'salesman_name'             => $salesmanInfo->user->getName(),
                    //'Total_MSL_Item'            => ($total_msl_items > 0) ? $total_msl_items : '0',
                    
                    'Total_Out_Of_Stock_Item'   => ($total_msl_out_of_stock_items > 0) ? $total_msl_out_of_stock_items : '0',
                    //'Msl_Percentage'            => ($per > 0) ? $per : '0',
                    'Total_MSL_Item'            => $msldata->total_msl_item ?? '0',
                    'Msl_Check_Item'            => $msldata->msl_check_item ?? '0',
                    'Msl_Percentage'            => $msldata->msl_percentage ?? '0',
                    //'salesman_id'               => ($request->export == 0) ? model($salesmanInfo, 'user_id') : "",
                ]);
                // }
            }

            // $visit = DB::table('customer_visits')
            //     ->select(
            //         DB::raw('customer_id, max(date) as dates')
            //     )
            //     ->whereIn('salesman_id', $salesman_ids);

            // if ($StartDate != '' && $EndDate != '') {
            //     if ($StartDate == $EndDate) {
            //         $visit->whereDate('date', $StartDate);
            //     } else {
            //         $visit->whereBetween('date', [$StartDate, $EndDate]);
            //     }
            // }

            // $visits = $visit->groupBy('customer_id')
            //     ->orderBy('dates', 'desc')
            //     ->get();

            // if (count($visits)) {

            //     foreach ($visits as $v) {

            //         $ds = DistributionStock::where('customer_id', $v->customer_id)
            //             ->whereIn('salesman_id', $salesman_ids)
            //             ->whereDate('created_at', $v->dates)
            //             ->whereIn('item_id', $channel_user_item_ids)
            //             ->get();

            //         $customerInfo = CustomerInfo::where('user_id', $v->customer_id)->first();

            //         if (count($ds)) {
            //             foreach ($ds as $d) {
            //                 $salesmanInfo = SalesmanInfo::where('user_id', $d->salesman_id)->first();
            //                 $item = Item::find($d->item_id);
            //                 $msl = DB::table('merchandiser_msl_compliances')->where('merchandiser_id',$d->salesman_id)->whereDate('date',$StartDate)->first();
            //                 $final_array->push((object) [
            //                     'date'                      => $StartDate,
            //                     'customer_code'             => model($customerInfo, 'customer_code'),
            //                     'customer_name'             => $customerInfo->user->getName(),
            //                     'channel_name'              => $customerInfo->channel->name,
            //                     'salesman_code'             => model($salesmanInfo, 'salesman_code'),
            //                     'salesman_name'             => is_object($salesmanInfo) ? $salesmanInfo->user->getName() : "N/A",
            //                     'item_code'                 => $item->item_code,
            //                     'item_name'                 => $item->item_name,
            //                     'brand_name'                => $item->brand->brand_name,
            //                     'category'                  => $item->itemMajorCategory->name,
            //                     'capacity'                  => is_object($d) ? $d->capacity : '0',
            //                     'out_of_stock'              => ($d->is_out_of_stock == 1) ? "Yes" : "No",
            //                     'msl_performance'           => is_object($d) ? "Yes" : "No",
            //                     'Total_MSL_Item'            => $msl->total_msl_item,
            //                     'Msl_Check_Item'            => $msl->msl_check_item,
            //                     'Msl_Percentage'            => $msl->msl_percentage,
            //                 ]);
            //             }
            //         }
            //         // }
            //         // }
            //         // }
            //     }
            // }
            $file_name = $request->user()->organisation_id . time() . '_msl_by_salesman.' . $request->export_type;
            Excel::store(new MSLBySalesmanExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }


    private function MSLBySalesmanDetail($request)
    {

        if (!$request->salesman_id) {
            return false;
        }

        $date = $request->date;

        $msld = new Collection();

        $ds = DistributionStock::where('salesman_id', $request->salesman_id)
            ->whereDate('created_at', $date)
            ->get();

        $salesmanInfo = SalesmanInfo::where('user_id', $request->salesman_id)->first();
        foreach ($ds as $d) {
            $customerInfo = CustomerInfo::where('user_id', $d->customer_id)->first();

            $msld->push((object) [
                'date'          => $date,
                'salesman_code' => model($salesmanInfo, 'salesman_code'),
                'salesman_name' => $salesmanInfo->user->getName(),
                'customer_code' => model($customerInfo, 'customer_code'),
                'customer_name' => $customerInfo->user->getName(),
                'item_code'     => model($d->item, 'item_code'),
                'item_name'     => model($d->item, 'item_name'),
                'model_qty'     => isset($d->capacity) ? model($d, 'capacity') : "0",
                'is_check'      => "Yes",
                'out_of_stock'  => ($d->is_out_of_stock == 1) ? "Yes" : "No"
            ]);
        }

        return prepareResult(true, $msld, [], "MSL By salesman detail listing", $this->success);
    }

    private function MSLByCustomerDetail($request)
    {

        if (!$request->customer_id) {
            return false;
        }

        $date = $request->date;
        $msld = new Collection();

        $ds = DistributionStock::where('customer_id', $request->customer_id)
            ->whereDate('created_at', $date)
            ->get();

        $customerInfo = CustomerInfo::where('user_id', $request->customer_id)->first();
        if (count($ds)) {
            foreach ($ds as $d) {
                $msld->push((object) [
                    'date'          => $date,
                    'customer_code' => model($customerInfo, 'customer_code'),
                    'customer_name' => $customerInfo->user->getName(),
                    'item_code'     => model($d->item, 'item_code'),
                    'item_name'     => model($d->item, 'item_name'),
                    'model_qty'     => isset($d->capacity) ? model($d, 'capacity') : "0",
                    'is_check'      => (is_object($d)) ? "Yes" : "No",
                    'out_of_stock'  => ($d->is_out_of_stock == 1) ? "Yes" : "No",
                ]);
            }
        }

        return prepareResult(true, $msld, [], "MSL By customer detail listing", $this->success);
    }

    private function MSLByChannelDetail($request)
    {

        if (!$request->channel_id) {
            return false;
        }

        $date = $request->date;

        $customer_ids = channelCustomers(array($request->channel_id));


        $msld = new Collection();

        $ds = DistributionStock::whereIn('customer_id', $customer_ids)
            ->whereDate('created_at', $date)
            ->get();

        $channel = Channel::find($request->channel_id);
        if (count($ds)) {
            foreach ($ds as $d) {
                $msld->push((object) [
                    'date'          => $date,
                    'channel_name' => model($channel, 'name'),
                    'item_code'     => model($d->item, 'item_code'),
                    'item_name'     => model($d->item, 'item_name'),
                    'model_qty'     => isset($d->capacity) ? model($d, 'capacity') : "0",
                    'is_check'      => (is_object($d)) ? "Yes" : "No",
                    'out_of_stock'  => ($d->is_out_of_stock == 1) ? "Yes" : "No",
                ]);
            }
        }

        return prepareResult(true, $msld, [], "MSL By channel detail listing", $this->success);
    }

    private function maxMSLBySalesman($request)
    {
        $StartDate    = $request->start_date;
        $EndDate      = $request->end_date;
        $salesman_ids = $request->salesman_id;

        if ($request->export == 0) {
            $mslArray = [];
                foreach ($salesman_ids as $key => $salesman) {
                    $array                    = [];
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->orderBy('msl_item_perform','desc')->first();
                    $salesman_infos           = DB::table('salesman_infos')->where('id', $salesman ?? 0)->first();

                    $array['date']            = $max_msl_check->date;
                    $array['salesman_code']   = ($salesman_infos) ? $salesman_infos->salesman_code : '';
                    $array['salesman_name']   = $max_msl_check->merchandiser_name;
                    $array['Total_MSL_Item']  = $max_msl_check->total_msl_item;
                    $array['Msl_Check_Item']  = $max_msl_check->msl_item_perform;
                    $array['Msl_Percentage']  = $max_msl_check->msl_percentage;
                    $array['salesman_id']     = $salesman;
                    $mslArray[] = $array;
                }
            return prepareResult(true, $mslArray, [], "MSL By Salesman listing", $this->success);
        } else {
            $columns = [
                "Date",
                "Merchandiser Code",
                "Merchandiser Name",
                "Customer Code",
                "Customer Name",
                'Total MSL Item',
                'Msl Percentage',
                'Msl CHeck Item'
            ];
            $final_array = new Collection();
                foreach ($salesman_ids as $key => $salesman) {
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->orderBy('msl_item_perform','desc')->first();
                    $salesman_infos           = DB::table('salesman_infos')->where('id', $salesman ?? 0)->first();

                        $final_array->push((object) [
                        'date'                      => $max_msl_check->date,
                        'salesman_code'             => ($salesman_infos) ? $salesman_infos->salesman_code : '',
                        'salesman_name'             => $max_msl_check->merchandiser_name,
                        'customer_code'             => $max_msl_check->customer_code,
                        'customer_name'             => $max_msl_check->customer_name,
                        'Total_MSL_Item'            => $max_msl_check->total_msl_item,
                        'Msl_Check_Item'            => $max_msl_check->msl_item_perform,
                        'Msl_Percentage'            => $max_msl_check->msl_percentage
                    ]);
                }

            $file_name = $request->user()->organisation_id . time() . '_msl_by_salesman.' . $request->export_type;
            Excel::store(new MSLBySalesmanExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function maxMSLByCustomer($request)
    { 
        $StartDate    = $request->start_date;
        $EndDate      = $request->end_date;
        $customer_ids = $request->customer_id;

        if ($request->export == 0) {
            $mslArray = [];
                foreach ($customer_ids as $key => $customer) {
                    $array                    = [];
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer)->orderBy('msl_item_perform','desc')->first();
                    if($max_msl_check){
                        $array['date']            = $max_msl_check->date;
                        $array['customer_code']   = $max_msl_check->customer_code;
                        $array['customer_name']   = $max_msl_check->customer_name;
                        $array['Total_MSL_Item']  = $max_msl_check->total_msl_item;
                        $array['Msl_Check_Item']  = $max_msl_check->msl_item_perform;
                        $array['Msl_Percentage']  = $max_msl_check->msl_percentage;
                        $array['customer_id']     = $customer;
                        $mslArray[] = $array;
                    }else{
                        $customer_infos     = CustomerInfo::where('user_id', $customer)->first();
                        $array['date']            = $request->start_date;
                        $array['customer_code']   = $customer_infos->customer_code;
                        $array['customer_name']   = $customer_infos->user->firstname.' '.$customer_infos->user->lastname;
                        $array['Total_MSL_Item']  = 0;
                        $array['Msl_Check_Item']  = 0;
                        $array['Msl_Percentage']  = 0;
                        $array['customer_id']     = $customer;
                        $mslArray[] = $array;
                    }
                    
                }
            return prepareResult(true, $mslArray, [], "MSL By Customer listing", $this->success);
        } else {
            $columns = [
                "Date",
                "Merchandiser Code",
                "Merchandiser Name",
                "Customer Code",
                "Customer Name",
                'Total MSL Item',
                'Msl Check Item',
                'Msl Percentage'
            ];
            $final_array = new Collection();
                 foreach ($customer_ids as $key => $customer) {
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer)->orderBy('msl_item_perform','desc')->first();
                    
                    if($max_msl_check){
                        $salesman_infos           = DB::table('salesman_infos')->where('id', $max_msl_check->merchandiser_id ?? 0)->first();
                        $final_array->push((object) [
                            'date'            => $max_msl_check->date,
                            'salesman_code'   => ($salesman_infos) ? $salesman_infos->salesman_code : '',
                            'salesman_name'   => $max_msl_check->merchandiser_name,
                            'customer_code'   => $max_msl_check->customer_code,
                            'customer_name'   => $max_msl_check->customer_name,
                            'Total_MSL_Item'  => $max_msl_check->total_msl_item,
                            'Msl_Check_Item'  => $max_msl_check->msl_item_perform,
                            'Msl_Percentage'  => $max_msl_check->msl_percentage,
                        ]);
                    }else{
                        $customer_infos     = CustomerInfo::where('user_id', $customer)->first();
                        $csm           = DB::table('customer_merchandisers')->where('customer_id', $customer)->first();

                        $salesman_infos           = DB::table('salesman_infos')->where('id', $csm->merchandiser_id ?? 0)->first();
                        $salesman_name      = User::where('id', $salesman_infos->user_id ?? 0)->where('usertype', 3)->first();

                        $salesman_firstname       = $salesman_name->firstname ?? '';
                        $salesman_lastname        =  $salesman_name->lastname ?? '';
                        $salesman_name            = $salesman_firstname.' '.$salesman_lastname;
                        $customer_name      = User::where('id', $customer_infos->user_id ?? 0)->where('usertype', 3)->first();

                        $customer_firstname       = $customer_name->firstname ?? '';
                        $customer_lastname        =  $customer_name->lastname ?? '';
                        $customer_name            = $customer_firstname.' '.$customer_lastname;
                        $final_array->push((object) [
                            'date'            => $request->start_date,
                            'salesman_code'   => ($salesman_infos) ? $salesman_infos->salesman_code : '',
                            'salesman_name'   => $salesman_name,
                            'customer_code'   => $customer_infos->customer_code,
                            'customer_name'   => $customer,
                            'Total_MSL_Item'  => 0,
                            'Msl_Check_Item'  => 0,
                            'Msl_Percentage'  => 0,
                        ]);
                    }
                    
                }

            $file_name = $request->user()->organisation_id . time() . '_msl_by_cusotmer.' . $request->export_type;
            Excel::store(new MSLByCustomerExport($final_array, $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

    private function testMaxMSLBySalesman($request)
    {
        $StartDate    = $request->start_date;
        $EndDate      = $request->end_date;
        $salesman_ids = $request->salesman_id;
        if($salesman_ids)
        {

        }else{
            $merssalesman = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->groupBy('merchandiser_id')->get();
            if (count($merssalesman)) {
                $salesman_ids = $merssalesman->pluck('merchandiser_id')->toArray();
            }
            
            //$salesman_ids = getSalesman(); 
        }

        if ($request->export == 0) {
            $mslArray = [];
                foreach ($salesman_ids as $key => $salesman) {
                    $array                    = [];
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->orderBy('msl_item_perform','desc')->first();
                    $salesman_infos           = DB::table('salesman_infos')->where('user_id', $salesman ?? 0)->first();

                    $allCustomer = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->groupBy('customer_id')->orderBy('msl_item_perform','desc')->get();
                    $TMItem = 0;
                    $TMPerform = 0;
                    foreach ($allCustomer as $key => $customer) {                       
                        $customer_max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer->customer_id)->orderBy('msl_item_perform','desc')->first();
                        $TMItem+=$customer_max_msl_check->total_msl_item;
                        $TMPerform+=$customer_max_msl_check->msl_item_perform;
                    }
                    if($max_msl_check){
                        $array['date']            = $max_msl_check->date;
                        $array['salesman_code']   = ($salesman_infos) ? $salesman_infos->salesman_code : '';
                        $array['salesman_name']   = $max_msl_check->merchandiser_name;
                        $array['Total_MSL_Item']  = $TMItem;
                        $array['Msl_Check_Item']  = $TMPerform;
                        $array['Msl_Percentage']  = ($TMItem>0) ? round(($TMPerform/$TMItem)*100) : 0;
                        $array['salesman_id']     = $salesman;
                    }else{
                        $salesman_name      = User::where('id', $salesman_infos->user_id ?? 0)->where('usertype', 3)->first();
                        $salesman_firstname       = $salesman_name->firstname ?? '';
                        $salesman_lastname        =  $salesman_name->lastname ?? '';
                        $salesman_name            = $salesman_firstname.' '.$salesman_lastname;
                        $array['date']            = $request->start_date;
                        $array['salesman_code']   = ($salesman_infos) ? $salesman_infos->salesman_code : '';
                        $array['salesman_name']   = $salesman_name;
                        $array['Total_MSL_Item']  = 0;
                        $array['Msl_Check_Item']  = 0;
                        $array['Msl_Percentage']  = 0;
                        $array['salesman_id']     = $salesman;
                    }
                    
                    $mslArray[] = $array;
                }
            return prepareResult(true, $mslArray, [], "MSL By Salesman listing", $this->success);
        } else {
            $columns = [
                "Date",
                "Merchandiser Code",
                "Merchandiser Name",
                "Customer Code",
                "Customer Name",
                'Total MSL Item',
                'Msl Available',
                'Msl Percentage',
            ];
            $final_array = new Collection();
                foreach ($salesman_ids as $key => $salesman) {
                    $max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->orderBy('msl_item_perform','desc')->first();
                    $salesman_infos           = DB::table('salesman_infos')->where('user_id', $salesman ?? 0)->first();
                    $allCustomer = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->groupBy('customer_id')->orderBy('msl_item_perform','desc')->get();
                    // ->groupBy('customer_id')
                    foreach ($allCustomer as $key => $customer) {
                       
                        $customer_max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])
                                ->where('customer_id', $customer->customer_id)
                                ->where('merchandiser_id', $salesman)
                                ->orderBy('msl_item_perform','desc')->get();
                                foreach ($customer_max_msl_check as $key => $customer_max_msl_checkd) {
                                    if($max_msl_check){ //added on 18 aprl
                                        $final_array->push((object) [
                                            'date'            => $customer_max_msl_checkd->date,
                                            'salesman_code'   => ($salesman_infos) ? $salesman_infos->salesman_code : '',
                                            'salesman_name'   => $customer_max_msl_checkd->merchandiser_name,
                                            'customer_code'   => $customer_max_msl_checkd->customer_code,
                                            'customer_name'   => $customer_max_msl_checkd->customer_name,
                                            'Total_MSL_Item'  => $customer_max_msl_checkd->total_msl_item,
                                            'Msl_Check_Item'  => $customer_max_msl_checkd->msl_item_perform,
                                            'Msl_Percentage'  => $customer_max_msl_checkd->msl_percentage,
                                        ]);
                                    }
                                }
                    }

                        
                }

                $final_array = collect($final_array)->sortBy('date');
                $data = array();
                
                foreach ($final_array as $key => $value) {
                    $data[] = $value;
                }

            $file_name = $request->user()->organisation_id . time() . '_msl_by_salesman.' . $request->export_type;
            Excel::store(new MSLBySalesmanExport(collect($data), $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

          
    private function newMSLBySalesmanDetail($request){
        if (!$request->salesman_id) {
            return false;
        }

        $StartDate = $request->start_date;
        $EndDate   = $request->end_date;
        $salesman  = $request->salesman_id;

        //$msld = new Collection();

        // $ds = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $request->salesman_id)->orderBy('msl_item_perform','desc')->get();

        //$salesmanInfo = SalesmanInfo::where('user_id', $request->salesman_id)->first();
        // foreach ($ds as $d) {
        //     $customerInfo = CustomerInfo::where('user_id', $d->customer_id)->first();
        //     $msld->push((object) [
        //         'date'                => $d->date,
        //         'salesman_code'       => model($salesmanInfo, 'salesman_code'),
        //         'salesman_name'       => $salesmanInfo->user->getName(),
        //         'customer_code'       => model($customerInfo, 'customer_code'),
        //         'customer_name'       => $customerInfo->user->getName(),
        //         'msl_item_perform'    => $d->msl_item_perform,
        //         'total_msl_item'      => $d->total_msl_item,
        //         'msl_percentage'      => $d->msl_percentage,
                
        //     ]);
        // }

        $final_array = new Collection();
        $max_msl_check  = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->orderBy('msl_item_perform','desc')->first();

        $salesman_infos = DB::table('salesman_infos')->where('user_id', $salesman ?? 0)->first();

        $allCustomer = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('merchandiser_id', $salesman)->groupBy('customer_id')->orderBy('msl_item_perform','desc')->get();
        foreach ($allCustomer as $key => $customer) {
           
            $customer_max_msl_check            = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $customer->customer_id)->orderBy('msl_item_perform','desc')->first();
            $final_array->push((object) [
                'date'            => $customer_max_msl_check->date,
                'salesman_code'   => ($salesman_infos) ? $salesman_infos->salesman_code : '',
                'salesman_name'   => $customer_max_msl_check->merchandiser_name,
                'customer_code'   => $customer_max_msl_check->customer_code,
                'customer_name'   => $customer_max_msl_check->customer_name,
                'total_msl_item'  => $customer_max_msl_check->total_msl_item,
                'msl_item_perform'=> $customer_max_msl_check->msl_item_perform,
                'msl_percentage'  => $customer_max_msl_check->msl_percentage,
            ]);
        }
        $final_array = collect($final_array)->sortBy('date');
        $data = array();
        
        foreach ($final_array as $key => $value) {
            $data[] = $value;
        }
        //dd($final_array);
        return prepareResult(true, $data, [], "MSL By salesman detail listing", $this->success);
    }


    private function newMSLByCustomerDetail($request){
        if (!$request->customer_id) {
            return false;
        }
        
        $StartDate = $request->start_date;
        $EndDate   = $request->end_date;
        $msld = new Collection();

        $ds = DB::table('merchandiser_msls')->whereBetween('date', [$StartDate, $EndDate])->where('customer_id', $request->customer_id)->orderBy('msl_item_perform','desc')->get();

        $customerInfo = CustomerInfo::where('user_id', $request->customer_id)->first();
        foreach ($ds as $d) {
            $salesmanInfo = SalesmanInfo::where('user_id', $d->merchandiser_id)->first();
            $msld->push((object) [
                'date'                => $d->date,
                'salesman_code'       => model($salesmanInfo, 'salesman_code'),
                'salesman_name'       => $salesmanInfo->user->getName(),
                'customer_code'       => model($customerInfo, 'customer_code'),
                'customer_name'       => $customerInfo->user->getName(),
                'msl_item_perform'    => $d->msl_item_perform,
                'total_msl_item'      => $d->total_msl_item,
                'msl_percentage'      => $d->msl_percentage,
                
            ]);
        }

        return prepareResult(true, $msld, [], "MSL By customer detail listing", $this->success);

        
    }

    private function timeSheetReport($request)
    {
     
       
        $StartDate    = $request->start_date;
        $EndDate      = $request->end_date;
        //$salesman_ids = $request->salesman_id;
        if(empty($request->salesman_id)){
            $salesman = SalesmanInfo::where("region_id", $request->region_id)->pluck("user_id");
        }else{
            $salesman = $request->salesman_id;
        }
        
        if ($request->export == 0) {
                $final_array = new Collection();
                $array                    = [];
                $dayStart =NULL;
                $total_time_spend =NULL;

                $timeSheetReport = TimeSheetReport::whereBetween('gl_date', [$StartDate, $EndDate])->orderBy('id','asc')->Wherein('salesman_id',$salesman)->get();
           
                foreach ($timeSheetReport as $key => $timeSheet) {
                    $gl_date = '';
                    if(is_null($timeSheet->day_end) && is_null($timeSheet->check_in)){
                        $gl_date = $timeSheet->gl_date;
                    }

                    $customer_code = '';
                    if($timeSheet->day_start && $timeSheet->entry_type=='1'){
                        
                        $customer_code = 'Begin Trip';
                        $dayStart =  date('h:i a', strtotime($timeSheet->day_start));
                        
                        
                    }else if($timeSheet->customer_code){
                        $customer_code = $timeSheet->customer_code;
                    }

                    if($timeSheet->day_end && $timeSheet->entry_type=='3'){
                        $dayStart = '';
                    }


                    if($timeSheet->check_in || $timeSheet->day_end){

                        $startTime = Carbon::parse($timeSheet->check_in);
                        $endTime   = Carbon::parse($timeSheet->check_out);
                        $timeDifference = $startTime->diff($endTime);
                        $hours = $timeDifference->h; // Hours
                        $minutes = $timeDifference->i; // Minutes
                        $seconds = $timeDifference->s; // Seconds
                       

                        $today_check_in = Carbon::parse($timeSheet->check_in);
                        $check_out = Carbon::parse($timeSheet->check_out);
                        $total_time_spend = $today_check_in->diff($check_out)->format('%H:%I:%S');

                      /*   $total_time_spend=$hours.':'.$minutes.':'.$seconds; */
                        $dayStart = '';
                    }
                   
                    if($timeSheet->check_in=='' || $timeSheet->check_out==''){
                        $total_time_spend=null;
                    }
                    $final_array->push((object) [
                        'gl_date'          => $gl_date,
                        'transaction_date' => $timeSheet->transaction_date,
                        'salesman_code'    => $timeSheet->salesman_code,
                        'salesman_name'    => $timeSheet->salesman_name,
                        'customer_code'    => $customer_code,
                        'customer_name'    => $timeSheet->customer_name,
                        'entry_typ'        => $timeSheet->entry_type,
                        'day_start'        => $dayStart,
                        'check_in'         => $timeSheet->check_in ? date('h:i:s a', strtotime($timeSheet->check_in)) : '',
                        'check_out'        => $timeSheet->check_out ? date('h:i:s a', strtotime($timeSheet->check_out)) : '',
                        'day_end'          => $timeSheet->day_end ? date('h:i:s a', strtotime($timeSheet->day_end)) : '',
                        'total_spend'      => $total_time_spend ?  $total_time_spend: '',
                    ]);
                    $total_time_spend=null;
                }

               
            return prepareResult(true, $final_array, [], "Time Sheet Report listing", $this->success);
        } else {
            $columns = [
                "GL Date",        
                "Transaction Date",
                "Merchandiser Code",
                "Merchandiser Name",
                "Customer Code",
                "Customer Name",
                "Day Start",  
                "Check In",      
                "Check Out",      
                "Day End",        
            ];
            $final_array = new Collection();
            $timeSheetReport = TimeSheetReport::whereBetween('gl_date', [$StartDate, $EndDate])->orderBy('salesman_id','asc')->get();
            foreach ($timeSheetReport as $key => $timeSheet) {
                $gl_date = '';
                if(is_null($timeSheet->day_end) && is_null($timeSheet->check_in)){
                    $gl_date = $timeSheet->gl_date;
                }

                $customer_code = '';
                if($timeSheet->customer_code!='' && is_null($timeSheet->day_end)){
                    $customer_code = $timeSheet->customer_code;
                }else if($timeSheet->day_start){
                    $customer_code = 'Begin Trip';
                }

                $dayStart =  date('h:i a', strtotime($timeSheet->day_start));
                if($timeSheet->check_in || $timeSheet->day_end){
                    $dayStart = '';
                }

                $final_array->push((object) [
                    'gl_date'          => $gl_date,
                    'transaction_date' => $timeSheet->transaction_date,
                    'salesman_code'    => $timeSheet->salesman_code,
                    'salesman_name'    => $timeSheet->salesman_name,
                    'customer_code'    => $customer_code,
                    'customer_name'    => $timeSheet->customer_name,
                    'day_start'        => $dayStart,
                    'check_in'         => $timeSheet->check_in ? date('h:i a', strtotime($timeSheet->check_in)) : '',
                    'check_out'        => $timeSheet->check_out ? date('h:i a', strtotime($timeSheet->check_out)) : '',
                    'day_end'          => $timeSheet->day_end ? date('h:i a', strtotime($timeSheet->day_end)) : '',
                ]);
            }
            $file_name = $request->user()->organisation_id . time() . '_msl_by_salesman.' . $request->export_type;
            Excel::store(new MSLBySalesmanExport(collect($final_array), $columns), $file_name, '', $this->extensions($request->export_type));

            $result['file_url'] = str_replace('public/', '', URL::to('/storage/app/' . $file_name));
            return prepareResult(true, $result, [], "Data successfully exported", $this->success);
        }
    }

}
