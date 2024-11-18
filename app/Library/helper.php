<?php

use App\Model\ActionHistory;
use App\Model\BrandChannel;
use App\Model\CodeSetting;
use App\Model\CodeSettingPrd;
use App\Model\CustomerInfoPrd;
use App\Model\CustomerGroupBasedPricingPrd;
use App\Model\CustomerBasedPricingPrd;
use App\Model\ItemBasePricePrd;
use App\Model\Currency;
use App\Model\CustomerInfo;
use App\Model\CustomerMerchandiser;
use App\Model\CustomerRoute;
use App\Model\CustomFieldValueSave;
use App\Model\Item;
use App\Model\ItemMainPrice;
use App\Model\ItemPrd;
use App\Model\ItemMainPricePrd;
use App\Model\ItemUom;
use App\Model\ItemUomPrd;
use App\Model\MerchandiserUpdated;
use App\Model\Notifications;
use App\Model\OrderDeliveryLog;
use App\Model\OrganisationRole;
use App\Model\OrganisationRoleAttached;
use App\Model\PDPCustomer;
use App\Model\PDPItem;
use App\Model\RequestLog;
use App\Model\Route;
use App\Model\SalesmanInfo;
use App\Model\SalesmanNumberRange;
use App\Model\Storagelocation;
use App\Model\StoragelocationPrd;
use App\Model\UserChannelAttached;
use App\Model\WorkFlowObject;
use App\Model\WorkFlowObjectPrd;
use App\Model\WorkFlowObjectAction;
use App\Model\WorkFlowRule;
use App\Model\WorkFlowRulePrd;
use App\Model\WorkFlowRuleApprovalRole;
use App\Model\WorkFlowRuleModule;
use App\Model\WorkFlowRuleModulePrd;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Clue\StreamFilter\fun;

function pre($array, $exit = true)
{
    echo '<pre>';
    print_r($array);
    echo '</pre>';

    if ($exit) {
        exit();
    }
}

function prepareResult($status, $data, $errors, $msg, $status_code, $pagination = array())
{
    return response()->json(['status' => $status, 'data' => $data, 'message' => $msg, 'errors' => $errors, 'pagination' => $pagination], $status_code);
}

function getUser()
{
    return auth('api')->user();
}

function checkPermission($permissionName)
{
    if (!auth('api')->user()->can($permissionName) && auth('api')->user()->hasRole('superadmin')) {
        return false;
    }
    return true;
}

function combination_key($key)
{
    if (!count($key)) {
        return;
    }

    $combine = array();

    foreach ($key as $k) {
        if ($k == 1) {
            $combine[] = 'Country';
        }
        if ($k == 2) {
            $combine[] = 'Region';
        }
        if ($k == 3) {
            $combine[] = 'Area';
        }
        if ($k == 4) {
            $combine[] = 'Sub Area';
        }
        if ($k == 5) {
            $combine[] = 'Branch/Depot';
        }
        if ($k == 6) {
            $combine[] = 'Route';
        }
        if ($k == 7) {
            $combine[] = 'Sales Organisations';
        }
        if ($k == 8) {
            $combine[] = 'Channels';
        }
        if ($k == 9) {
            $combine[] = 'Sub Channels';
        }
        if ($k == 10) {
            $combine[] = 'Customer Groups';
        }
        if ($k == 11) {
            $combine[] = 'Customer';
        }
        if ($k == 12) {
            $combine[] = 'Material';
        }

        return $combine;
    }
}

function getDay($number)
{
    if ($number == 1) {
        return "Monday";
    }
    if ($number == 2) {
        return "Tuesday";
    }
    if ($number == 3) {
        return "Wednesday";
    }
    if ($number == 4) {
        return "Thursday";
    }
    if ($number == 5) {
        return "Friday";
    }
    if ($number == 6) {
        return "Saturday";
    }
    if ($number == 7) {
        return "Sunday";
    }
}

function nextComingNumber2($model, $variableName, $feildName, $code)
{
    if (CodeSetting::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->first();
        if ($getNumber) {
            return $getNumber['next_coming_number_' . $variableName];
        }
    }

    //Not found case : manual code entry
    return $code;
}

function updateNextComingNumber($model, $variableName)
{
    if (CodeSetting::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)
            ->where('is_code_auto_' . $variableName, true)
            ->first();

        preg_match_all('!\d+!', $getNumber['next_coming_number_' . $variableName], $newNumber);

        if (substr_count($getNumber['next_coming_number_' . $variableName], 0) >= 1) {
            if (substr($newNumber[0][0], 0, 1) != 0) {
                if (preg_match('/[^0-9]/', $getNumber['prefix_code_' . $variableName])) {
                    $nextNumber = $getNumber['prefix_code_' . $variableName] . ($newNumber[0][0] + 1);
                } else {
                    $nextNumber = ($newNumber[0][0] + 1);
                }
            } else {
                $charCount = strlen($getNumber['start_code_' . $variableName]);
                if ($charCount > 3) {
                    $tChar = $charCount - substr_count($getNumber['start_code_' . $variableName], 0);
                } else {
                    $tChar = 1;
                }
                $count0 = substr_count($getNumber['start_code_' . $variableName], 0) + $tChar;
                $value2 = substr($getNumber['next_coming_number_' . $variableName], $charCount, $count0);
                $value2 = $value2 + 1;
                $nextNumber = $getNumber['prefix_code_' . $variableName] . sprintf('%0' . $count0 . 's', $value2);
            }
        } else {
            if ($getNumber['prefix_code_' . $variableName]) {
                $nextNumber = $getNumber['prefix_code_' . $variableName] . ($newNumber[0][0] + 1);
            } else {
                $nextNumber = $getNumber['prefix_code_' . $variableName] . (0 + 1);
            }
        }

        $updateNextNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)
            ->where('is_code_auto_' . $variableName, true)
            ->update([
                'next_coming_number_' . $variableName => $nextNumber,
            ]);

        return $updateNextNumber;
    }
}

function updateNextComingNumberPrd($model, $variableName)
{
    if (CodeSettingPrd::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSettingPrd::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)
            ->where('is_code_auto_' . $variableName, true)
            ->first();

        preg_match_all('!\d+!', $getNumber['next_coming_number_' . $variableName], $newNumber);

        if (substr_count($getNumber['next_coming_number_' . $variableName], 0) >= 1) {
            if (substr($newNumber[0][0], 0, 1) != 0) {
                if (preg_match('/[^0-9]/', $getNumber['prefix_code_' . $variableName])) {
                    $nextNumber = $getNumber['prefix_code_' . $variableName] . ($newNumber[0][0] + 1);
                } else {
                    $nextNumber = ($newNumber[0][0] + 1);
                }
            } else {
                $charCount = strlen($getNumber['start_code_' . $variableName]);
                if ($charCount > 3) {
                    $tChar = $charCount - substr_count($getNumber['start_code_' . $variableName], 0);
                } else {
                    $tChar = 1;
                }
                $count0 = substr_count($getNumber['start_code_' . $variableName], 0) + $tChar;
                $value2 = substr($getNumber['next_coming_number_' . $variableName], $charCount, $count0);
                $value2 = $value2 + 1;
                $nextNumber = $getNumber['prefix_code_' . $variableName] . sprintf('%0' . $count0 . 's', $value2);
            }
        } else {
            if ($getNumber['prefix_code_' . $variableName]) {
                $nextNumber = $getNumber['prefix_code_' . $variableName] . ($newNumber[0][0] + 1);
            } else {
                $nextNumber = $getNumber['prefix_code_' . $variableName] . (0 + 1);
            }
        }

        $updateNextNumber = CodeSettingPrd::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->update([
            'next_coming_number_' . $variableName => $nextNumber,
        ]);

        return $updateNextNumber;
    }
}

function nextComingNumber($model, $variableName, $feildName, $code)
{
    if (CodeSetting::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->first();

        if ($getNumber && $getNumber['prefix_code_' . $variableName]) {
            return $getNumber['next_coming_number_' . $variableName];
        } else {
            return $code;
        }
    }

    //Not found case : manual code entry
    return $code;
}

function updateNextComingNumber2($model, $variableName)
{
    if (CodeSetting::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->first();
        preg_match_all('!\d+!', $getNumber['next_coming_number_' . $variableName], $newNumber);
        $nextNumber = $getNumber['prefix_code_' . $variableName] . ($newNumber[0][0] + 1);

        $updateNextNumber = CodeSetting::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->update([
            'next_coming_number_' . $variableName => $nextNumber,
        ]);
        return $updateNextNumber;
    }
}

/**
 * This function is calculate the price base on customer and item base price
 *
 */
function item_apply_price($request)
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
                    return customerGroupBasePrice($request, $qty, $item, $item_price_objs);
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
                    return customerGroupBasePrice($request, $qty, $item, $item_price_objs);
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

            return itemPriceSet($qty, $price, $item, $request);
        }

        // return itemPriceSet($qty, $price, $item, $request);
    }

    if (count($item_price_objs) < 1) {
        return customerGroupBasePrice($request, $qty, $item, $item_price_objs);

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

function customerGroupBasePrice($request, $qty, $item, $item_price_objs)
{
    $cgbp = CustomerGroupBasedPricingPrd::where('item_id', $item->id)
        ->where('item_uom_id', $request->item_uom_id)
        ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        ->orderBy('updated_at', 'desc')
        ->first();

    // requested uom and item
    if ($cgbp) {
        $price = $cgbp->price;
        return itemPriceSet($qty, $price, $item, $request);
    }

    $cgbps = CustomerGroupBasedPricingPrd::where('item_id', $item->id)
        ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        ->orderBy('updated_at', 'desc')
        ->get();

    if (count($cgbps)) {
        $cgbp = $cgbps->first();

        $cusotmer_price = $cgbp->price;
        $cusotmer_lower_price = 0;

        if ($cgbp->item_uom_id == $item->lower_unit_uom_id) {
            $cusotmer_lower_price = $cusotmer_price;
        } else {
            $item_main_price = ItemMainPricePrd::where('item_id', $cgbp->item_id)
                ->where('item_uom_id', $cgbp->item_uom_id)
                ->first();

            if ($item_main_price) {
                $upc = $item_main_price->item_upc;
                if ($upc < 1) {
                    $cusotmer_lower_price = $cusotmer_price / 1;
                } else {
                    $cusotmer_lower_price = $cusotmer_price / $upc;
                }
            } else {
                $item_price_objs = ItemMainPricePrd::where('item_id', $request->item_id)
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
                    ->orderBy('updated_at', 'desc')
                    ->get();

                if (count($item_price_objs)) {
                    return itemBasePrice($request, $qty, $item, $item_price_objs);
                }
            }
        }

        return itemPriceSet($qty, $price, $item, $request);
    }

    return itemBasePrice($request, $qty, $item, $item_price_objs);
}

function itemBasePrice($request, $qty, $item, $item_price_objs)
{
    $item_price_obj = ItemBasePricePrd::where('item_id', $request->item_id)
        ->where('item_uom_id', $request->item_uom_id)
        // ->where('start_date', '<=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        // ->where('end_date', '>=', ($request->delivery_date) ? $request->delivery_date : date('Y-m-d'))
        ->orderBy('updated_at', 'desc')
        ->first();

    if ($item_price_obj) {
        $price = $item_price_obj->price;
        return itemPriceSet($qty, $price, $item, $request);
    }

    if (!$item_price_obj) {
        $item_price_obj = $item_price_objs->first();
        if (!$item_price_obj) {
            return itemPriceSet($qty, 0, $item, $request);
        }
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
    return itemPriceSet($qty, $price, $item, $request);
}

function conevertQtyForRFGen($item_id, $item_qty, $item_uom_id, $is_rfgen = false)
{
    // (i.e request uom is CT)
    $request_item_mp = ItemMainPrice::where('item_id', $item_id)
        ->where('item_uom_id', $item_uom_id)
        ->first();

    if ($is_rfgen === true) {
        if (in_array($item_id, [
            '2023',
            '2024',
            '2028',
            '2153',
            '2507',
            '2518',
            '2551',
            '2552',
            '2860',
            '2861'
        ])) {
            $item_mp = ItemMainPrice::where('item_id', $item_id)
                ->where('item_shipping_uom', 1)
                ->first();

            return ($request_item_mp->item_upc * $item_qty) / $item_mp->item_upc;
        }
    }

    // (i.e secondry uom is OT)
    $item_mp = ItemMainPrice::where('item_id', $item_id)
        ->where('is_secondary', 1)
        ->first();

    if ($item_mp && $request_item_mp) {
        if ($item_mp->item_upc > 0 && $item_mp->item_uom_id != $item_uom_id) {
            return ($request_item_mp->item_upc * $item_qty) / $item_mp->item_upc;
        }
    }

    return $item_qty;
}

function itemPriceSet($qty, $price, $item, $request)
{
    $item_price = $price;
    $total_price = $item_price + (($item->is_item_excise == 1) ? exciseConvert($item->item_excise, $item, $request) : 0);

    $net_gross = $qty * $item_price;
    $item_gross = $qty * $total_price;

    $item_excise = ($item->is_item_excise == 1) ? exciseConvert($item->item_excise, $item, $request) : 0;
    $net_excise = $qty * ($item->is_item_excise == 1) ? exciseConvert($item->item_excise, $item, $request) : 0;

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
    $std_object->item_gross             = round($item_gross, 2);
    $std_object->net_gross              = round($net_gross, 2);
    $std_object->net_excise             = round($net_excise, 2);
    $std_object->discount               = 0;
    $std_object->discount_percentage    = 0;
    $std_object->discount_id            = 0;
    $std_object->is_free                = false;
    $std_object->is_item_poi            = false;
    $std_object->promotion_id           = null;
    $std_object->total_net              = round($total_net, 2);
    $std_object->total_excise           = round($item_excise, 2);
    $std_object->total_vat              = round($item_vat, 2);
    $std_object->total                  = round($total, 2);

    return $std_object;
}

function nextComingNumberPrd($model, $variableName, $feildName, $code)
{
    if (CodeSettingPrd::where('is_code_auto_' . $variableName, true)->count() > 0) {
        $getNumber = CodeSettingPrd::select('prefix_code_' . $variableName, 'start_code_' . $variableName, 'next_coming_number_' . $variableName)->where('is_code_auto_' . $variableName, true)->first();

        if ($getNumber && $getNumber['prefix_code_' . $variableName]) {
            return $getNumber['next_coming_number_' . $variableName];
        } else {
            return $code;
        }
    }

    //Not found case : manual code entry
    return $code;
}

function checkWorkFlowRule($moduleName, $eventName)
{
    $getModuleId = WorkFlowRuleModule::select('id')
        ->where('name', $moduleName)
        ->first();

    if ($getModuleId) {
        $checkActivate = WorkFlowRule::select('id')
            ->where('work_flow_rule_module_id', $getModuleId->id)
            ->where('event_trigger', 'like', "%" . $eventName . "%")
            ->where('status', 1)
            ->first();

        if ($checkActivate) {
            return $checkActivate->id;
        }
    }
    return false;
}
function checkWorkFlowRule2($moduleName, $eventName)
{
    $getModuleId = WorkFlowRuleModulePrd::select('id')
        ->where('name', $moduleName)
        ->first();

    if ($getModuleId) {
        $checkActivate = WorkFlowRulePrd::select('id')
            ->where('work_flow_rule_module_id', $getModuleId->id)
            ->where('event_trigger', 'like', "%" . $eventName . "%")
            ->where('status', 1)
            ->first();

        if ($checkActivate) {
            return $checkActivate->id;
        }
    }
    return false;
}

function codeExist($object, $code_key, $code)
{
    $obj = new $object;
    $data = $obj->where($code_key, $code)->first();
    if (is_object($data)) {
        return true;
    }
    return false;
}

function savecustomField($record_id, $module_id, $custom_field_id, $custom_field_value)
{
    $custom_field_value_save = new CustomFieldValueSave;
    $custom_field_value_save->record_id = $record_id;
    $custom_field_value_save->module_id = $module_id;
    $custom_field_value_save->custom_field_id = $custom_field_id;
    $custom_field_value_save->custom_field_value = $custom_field_value;
    $custom_field_value_save->save();
}

function getLowerQtyBaseOnSecondryUom($item_id, $uom, $qty)
{
    $item = ItemMainPrice::where('item_id', $item_id)->where('item_uom_id', $uom)->first();

    if ($item) {
        $qtys = $item->item_upc * $qty;

        return array('item_id' => $item_id, 'UOM' => $uom, 'Qty' => $qtys);
    }

    return array('item_id' => $item_id, 'UOM' => $uom, 'Qty' => $qty);
}

function getLowerQtyUom($item_id, $uom, $qty)
{
    $item = Item::select('lower_unit_uom_id', 'lower_unit_item_upc')
        ->find($item_id);

    if ($item) {
        if ($item->lower_unit_uom_id == $uom) {
            return array('item_id' => $item_id, 'UOM' => $uom, 'Qty' => $qty);
        }

        $qty = round($item->lower_unit_item_upc * $qty, 2);

        return array('item_id' => $item_id, 'UOM' => $item->lower_unit_uom_id, 'Qty' => $qty, 'item_upc' => $item->lower_unit_item_upc);
    } else {
        return array('item_id' => $item_id, 'UOM' => $uom, 'Qty' => $qty, 'item_upc' => 1);
    }
}

function getItemDetails($itemid, $uom, $qty)
{
    $itemDeails = Item::select('lower_unit_uom_id', 'lower_unit_item_upc')
        ->where('id', $itemid)->first();

    if ($itemDeails['lower_unit_uom_id'] != $uom) {
        $qtys = $itemDeails['lower_unit_item_upc'] * $qty;

        $result = array('ItemId' => $itemid, 'UOM' => $itemDeails['lower_unit_uom_id'], 'Qty' => $qtys);
    } else {
        $result = array('ItemId' => $itemid, 'UOM' => $itemDeails['lower_unit_uom_id'], 'Qty' => $qty);
    }
    return $result;
}

function getItemDetails2($itemid, $uom, $qty, $lower = false)
{
    $item = Item::select('lower_unit_uom_id', 'lower_unit_item_upc')
        ->where('id', $itemid)
        ->first();

    if (is_object($item)) {
        if ($item->lower_unit_uom_id != $uom) {
            $item_main_price = ItemMainPrice::where('item_id', $itemid)
                ->where('item_uom_id', $uom)
                ->where('is_secondary', 1)
                ->first();

            if ($lower && !is_object($item_main_price)) {
                $qtys = $item->item_upc * $qty;
            } else {
                if (!$item_main_price) {
                    $imp = ItemMainPrice::where('item_id', $itemid)
                        ->where('item_uom_id', $uom)
                        ->first();
                    $qtys = 0;
                    if ($imp) {
                        $qtys = $imp->item_upc * $qty;
                    }
                    return array('ItemId' => $itemid, 'UOM' => $uom, 'Qty' => $qtys);
                } else {
                    $qtys = $item_main_price->item_upc * $qty;
                }
            }

            $result = array('ItemId' => $itemid, 'UOM' => $item->lower_unit_uom_id, 'Qty' => $qtys);
        } else {
            $result = array('ItemId' => $itemid, 'UOM' => $uom, 'Qty' => $qty);
        }
    } else {
        $result = array('ItemId' => $itemid, 'UOM' => $uom, 'Qty' => $qty);
    }

    return $result;
}

function GetWorkFlowRuleObject($moduleName)
{
    $workFlowRules = WorkFlowObject::select(
        'work_flow_objects.id as id',
        'work_flow_objects.uuid as uuid',
        'work_flow_objects.work_flow_rule_id',
        'work_flow_objects.module_name',
        'work_flow_objects.request_object',
        'work_flow_objects.currently_approved_stage',
        'work_flow_objects.raw_id',
        'work_flow_rules.work_flow_rule_name',
        'work_flow_rules.description',
        'work_flow_rules.event_trigger',
        'work_flow_rules.is_or'
    )
        ->withoutGlobalScope('organisation_id')
        ->join('work_flow_rules', function ($join) {
            $join->on('work_flow_objects.work_flow_rule_id', '=', 'work_flow_rules.id');
        })
        ->where('work_flow_objects.organisation_id', auth()->user()->organisation_id)
        ->where('status', '1')
        ->where('is_approved_all', '0')
        ->where('is_anyone_reject', '0')
        ->where('work_flow_objects.module_name', $moduleName)
        //->where('work_flow_objects.raw_id',$users[$key]->id)
        ->get();

    $results = [];
    foreach ($workFlowRules as $key => $obj) {
        $userIds = [];

        if ($obj->currently_approved_stage > 0) {
            $checkCondition = WorkFlowRuleApprovalRole::skip($obj->currently_approved_stage)
                ->where('work_flow_rule_id', $obj->work_flow_rule_id)
                ->orderBy('id', 'ASC')
                ->limit(100);
            $checkCondition->limit = null;
            $getResults = $checkCondition->get();
        } else {
            $checkCondition = WorkFlowRuleApprovalRole::where('work_flow_rule_id', $obj->work_flow_rule_id)
                ->orderBy('id', 'ASC')
                ->limit(100);
            $checkCondition->limit = null;
            $getResults = $checkCondition->get();
        }

        if ($obj->is_or == 1) {

            foreach ($getResults as $getResult) {

                if (is_object($getResult) && $getResult->workFlowRuleApprovalUsers->count() > 0) {
                    //User based approval
                    foreach ($getResult->workFlowRuleApprovalUsers as $prepareUserId) {
                        $WorkFlowObjectAction = WorkFlowObjectAction::where('work_flow_object_id', $obj->id)->get();

                        if (is_object($WorkFlowObjectAction)) {
                            $id_arr = [];

                            foreach ($WorkFlowObjectAction as $action) {
                                $id_arr[] = $action->user_id;
                            }

                            if (!in_array($prepareUserId->user_id, $id_arr)) {
                                $userIds[] = $prepareUserId->user_id;
                            }

                            if (request()->user()->usertype == 1) {
                                $userIds[] = request()->user()->id;
                            }
                        } else {
                            $userIds[] = $prepareUserId->user_id;
                        }
                    }

                    if (in_array(auth()->id(), $userIds)) {
                        $results[] = [
                            'object' => $obj,
                            'Action' => 'User',
                        ];
                    }
                } else {
                    //Roles based approval
                    if (is_object($getResult) && $getResult->organisation_role_id == auth()->user()->role_id) {
                        $results[] = [
                            'object' => $obj,
                            'Action' => 'Role',
                        ];
                    }
                }
            }
        } else {

            $getResult = $checkCondition->where('work_flow_rule_id', $obj->work_flow_rule_id)
                ->orderBy('id', 'ASC')
                ->first();

            if (is_object($getResult) && $getResult->workFlowRuleApprovalUsers->count() > 0) {
                //User based approval
                foreach ($getResult->workFlowRuleApprovalUsers as $prepareUserId) {
                    $WorkFlowObjectAction = WorkFlowObjectAction::where('work_flow_object_id', $obj->id)->get();

                    if (is_object($WorkFlowObjectAction)) {
                        $id_arr = [];

                        foreach ($WorkFlowObjectAction as $action) {
                            $id_arr[] = $action->user_id;
                        }

                        if (!in_array($prepareUserId->user_id, $id_arr)) {
                            $userIds[] = $prepareUserId->user_id;
                        }

                        if (request()->user()->usertype == 1) {
                            $userIds[] = request()->user()->id;
                        }
                    } else {
                        $userIds[] = $prepareUserId->user_id;
                    }
                }

                if (in_array(auth()->id(), $userIds)) {
                    $results[] = [
                        'object' => $obj,
                        'Action' => 'User',
                    ];
                }
            } else {
                //Roles based approval
                if (is_object($getResult) && $getResult->organisation_role_id == auth()->user()->role_id) {
                    $results[] = [
                        'object' => $obj,
                        'Action' => 'Role',
                    ];
                }
            }
        }
    }
    return $results;
}

function create_action_history($module, $module_id, $user_id, $action, $comment)
{
    $action_history = new ActionHistory;
    $action_history->module = $module;
    $action_history->module_id = $module_id;
    $action_history->user_id = $user_id;
    $action_history->action = $action;
    $action_history->comment = $comment;
    $action_history->save();
}

/**
 * output value if found in object or array
 * @param  [object/array] $model             Eloquent model, object or array
 * @param  [string] $key
 * @param  [boolean] $alternative_value
 * @return [type]
 */
function model($model, $key, $alternative_value = null, $type = 'object', $pluck = false)
{
    if ($pluck) {
        $count = $model;
        $array = array();
        if ($count && count($count)) {
            $array = $count->pluck($key)->toArray();
        }

        if (count($array)) {
            return implode(',', $array);
        }

        return $alternative_value;
    }

    if ($type == 'object') {
        if (isset($model->$key)) {
            return $model->$key;
        }
    }

    if ($type == 'array') {
        if (isset($model[$key]) && $model[$key]) {
            return $model[$key];
        }
    }

    return $alternative_value;
}

function convertToCurrency($number)
{
    $no = round($number);
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety',
    );
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural;
        } else {
            $str[] = null;
        }
    }

    $Rupees = implode(' ', array_reverse($str));
    $paise = ($decimal) ? "And Paise " . ($words[$decimal - $decimal % 10]) . " " . ($words[$decimal % 10]) : '';
    return ($Rupees ? 'Rupees ' . $Rupees : '') . $paise . " Only";
}

function customPaginate($page, $limit, $component_array)
{
    $data_array = array();
    $offset = ($page - 1) * $limit;
    for ($i = 0; $i < $limit; $i++) {
        if (isset($component_array[$offset])) {
            $data_array[] = $component_array[$offset];
        }
        $offset++;
    }

    // $data_array = $data_array;
    $pagination['total_pages'] = ceil(count($component_array) / $limit);
    $pagination['current_page'] = (int) $page;
    $pagination['total_records'] = count($component_array);

    return array("data" => $data_array, "pagination" => $pagination);
}

function chnageCurrencyFormat($amount)
{
    $currency = Currency::where('default_currency', 1)->first();

    if ($currency->decimal_digits = 2) {
        if ($currency->format == "1,234,567.89") {
            $amount = number_format($amount, $currency->decimal_digits, ',', '.');
        } else if ($currency->format == "1.234.567,89") {
            $amount = number_format($amount, $currency->decimal_digits, '.', ',');
        } else {
            $amount = number_format($amount, $currency->decimal_digits, ' ', ',');
        }
    } else if ($currency->decimal_digits = 3) {
        if ($currency->format == "1,234,567.899") {
            $amount = number_format($amount, $currency->decimal_digits, ',', '.');
        } else if ($currency->format == "1.234.567,899") {
            $amount = number_format($amount, $currency->decimal_digits, '.', ',');
        } else {
            $amount = number_format($amount, $currency->decimal_digits, ' ', ',');
        }
    } else {
        if ($currency->format == "1,234,567") {
            $amount = number_format($amount, $currency->decimal_digits, ',', '.');
        } else if ($currency->format == "1.234.567") {
            $amount = number_format($amount, $currency->decimal_digits, '.', ',');
        } else {
            $amount = number_format($amount, $currency->decimal_digits, ' ', ',');
        }
    }
    return $amount;
}

function saveImage($image_name, $image, $folder_name)
{
    $destinationPath = 'uploads/' . $folder_name . '/';
    $getBaseType = explode(',', $image);
    $getExt = explode(';', $image);
    $image = str_replace($getBaseType[0] . ',', '', $image);
    $image = str_replace(' ', '+', $image);
    $fileName = $image_name . '-' . time() . '.' . basename($getExt[0]);
    \File::put($destinationPath . $fileName, base64_decode($image));
    return URL('/') . '/' . $destinationPath . $fileName;
}

function saveImage2($image_name, $image, $folder_name)
{
    if (!empty($image)) {
        $destinationPath = 'uploads/' . $folder_name . '/';
        // if (!file_exists($destinationPath)) {
        //    mkdir( $destinationPath,0777,false );
        // }
        $percent = 0.5;

        $image_name = $image_name;
        $image = $image;
        // $data = base64_decode($image);
        $data = imagecreatefromjpeg($image);
        $img = \Image::make($data);
        $getBaseType = explode(',', $image);
        $getExt = explode(';', $image);
        $image = str_replace($getBaseType[0] . ',', '', $image);
        $image = str_replace(' ', '+', $image);
        $fileName = $image_name . '-' . time() . '.' . basename($getExt[0]);

        $img->resize(80, 80, function ($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath . '/' . $image_name . ".jpg");

        return URL('/') . '/' . $destinationPath . $image_name . ".jpg";
    } else {
        return null;
    }
}

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance(
    $latitudeFrom,
    $longitudeFrom,
    $latitudeTo,
    $longitudeTo,
    $earthRadius = 6371000
) {
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

function distance($lat1, $lon1, $lat2, $lon2, $unit)
{
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return 0;
    } else {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            $miless = ($miles * 1.609344);
            return ($miless / 0.00062137);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
}

function timeCalculate($start_time, $end_time, $type = null)
{
    // $start_datetime = new DateTime($start_time);
    // $end_datetime = new DateTime($end_time);

    $start_datetime = Carbon::parse($start_time);
    $end_datetime = Carbon::parse($end_time);

    $time = $start_datetime->diffInSeconds($end_datetime);
    return gmdate('H:i:s', $time);
}

function getHours($sec)
{
    $seconds = $sec;
    $hours = floor($seconds / 3600);
    $seconds -= $hours * 3600;
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;

    return "$hours:$minutes:$seconds";

    // return floor($minutes / 60).':'.($minutes -   floor($minutes / 60) * 60);
}

function weekOfMonth($date)
{
    //Get the first day of the month.
    $firstOfMonth = strtotime(date("Y-m-01", $date));
    //Apply above formula.
    return weekOfYear($date) - weekOfYear($firstOfMonth) + 1;
}

function weekOfYear($date)
{
    $weekOfYear = intval(date("W", $date));
    if (date('n', $date) == "1" && $weekOfYear > 51) {
        // It's the last week of the previos year.
        $weekOfYear = 0;
    }
    return $weekOfYear;
}

function updateMerchandiser($og_id, $merchandiser_id, $is_customer = false)
{
    if ($is_customer) {
        $merchandiser = CustomerMerchandiser::where('customer_id', $merchandiser_id)->first();
        $merchandiser_id = $merchandiser->merchandiser_id;
    }
    MerchandiserUpdated::where('merchandiser_id', $merchandiser_id)->delete();
    MerchandiserUpdated::create([
        'organisation_id' => $og_id,
        'merchandiser_id' => $merchandiser_id,
        'is_updated' => 1,
    ]);
}

function getSalesman($customer = false, $loginUserId = null)
{
    $all_data = array();
    if ($loginUserId) {
        $login_user_id = $loginUserId;
    } else {
        $login_user_id = request()->user()->id;
    }

    $oruser = OrganisationRoleAttached::where('user_id', $login_user_id)->first();

    if (is_object($oruser)) {

        if (!empty($oruser->last_role_id)) {

            $last_role_id_array = explode(',', $oruser->last_role_id);
             $salesmans = SalesmanInfo::whereIn('salesman_supervisor', $last_role_id_array)->where('status', 1)->get();
            //$salesmans = SalesmanInfo::whereIn('salesman_supervisor', $last_role_id_array)->get();

            if (count($salesmans)) {
                $all_data = $salesmans->pluck('user_id')->toArray();
                if ($customer) {

                    if (count($all_data)) {
                        $customerMerchandiser = CustomerMerchandiser::whereIn('merchandiser_id', $all_data)->get();

                        if (count($customerMerchandiser)) {
                            $all_data = $customerMerchandiser->pluck('customer_id')->toArray();
                        }
                    }
                }
            }
        }
    } else {

        //$salesmanInfos = SalesmanInfo::where('salesman_supervisor', $login_user_id)->get();
         $salesmanInfos = SalesmanInfo::where('salesman_supervisor', $login_user_id)->where('status', 1)->get();
        if (count($salesmanInfos)) {
            $all_data = $salesmanInfos->pluck('user_id')->toArray();

            if ($customer) {

                if (count($all_data)) {
                    $customerMerchandiser = CustomerMerchandiser::whereIn('merchandiser_id', $all_data)->get();

                    if (count($customerMerchandiser)) {
                        $all_data = $customerMerchandiser->pluck('customer_id')->toArray();
                    }
                }
            }
        } else {
            $salesmanInfos = SalesmanInfo::get();
            $all_data = $salesmanInfos->pluck('user_id')->toArray();
 

            if ($customer) {

                if (count($all_data)) {

                    $customerMerchandiser = CustomerMerchandiser::whereIn('merchandiser_id', $all_data)
                        ->get();
                    
                    if (count($customerMerchandiser)) {
                        $all_data = $customerMerchandiser->pluck('customer_id')->toArray();
                    }
                }
            }
        }
    }

    return $all_data;
}

/**
 * Send a notification to mobile
 * @param  [string] $notification_id  access token
 * @param  [string] $title
 * @param  [string] $message
 * @param  [int] $id
 * @param  [string] $type default basic
 * @return [boolan]
 */
function send_notification_FCM($notification_id, $title, $message, $id, $type = "basic")
{

    $accesstoken = "AAAAm4d1Vu0:APA91bF4i3_G_0qvFfQOyPVR3i8XwfZwNxReZCzFYjQNWDLVHEUw_y-wv8IjPRZh5CDVRDtYM6Wklt1eEkY-SM2dseYSQ-_LE0ybq15ms81KGsrrD3ts51bRnW0oM9XEZA-ADxDP2o7j";

    $URL = 'https://fcm.googleapis.com/fcm/send';

    $post_data = '{
            "to" : "' . $notification_id . '",
            "data" : {
              "body" : "",
              "title" : "' . $title . '",
              "type" : "' . $type . '",
              "id" : "' . $id . '",
              "message" : "' . $message . '",
            },

            "notification" : {
                 "body" : "' . $message . '",
                 "title" : "' . $title . '",
                  "type" : "' . $type . '",
                 "id" : "' . $id . '",
                 "message" : "' . $message . '",
                "icon" : "new",
                "sound" : "default"
                },
          }';
    // pre($post_data);

    $crl = curl_init();

    $headr = array();
    $headr[] = 'Content-type: application/json';
    $headr[] = 'Authorization: ' . $accesstoken;
    curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($crl, CURLOPT_URL, $URL);
    curl_setopt($crl, CURLOPT_HTTPHEADER, $headr);

    curl_setopt($crl, CURLOPT_POST, true);
    curl_setopt($crl, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);

    $rest = curl_exec($crl);

    if ($rest === false) {
        // throw new Exception('Curl error: ' . curl_error($crl));
        //pre('Curl error: ' . curl_error($crl));
        $result_noti = 0;
    } else {

        $result_noti = 1;
    }

    curl_close($crl);
    //pre($result_noti);
    return $result_noti;
}

// function sendNotificationAndroid($data, $reg_id)
// {
//     $fcmMsg = array(
//         'body' => $data['message'],
//         'title' => $data['title'],
//         'noti_type' => $data['noti_type'],
//         'message' => $data['message'],
//         'sender_id' => (!empty($data['sender_id'])) ? $data['sender_id'] : null,
//         'uuid' => (!empty($data['uuid'])) ? $data['uuid'] : null,
//         'type' => (!empty($data['type'])) ? $data['type'] : null,
//         'status' => (isset($data['status'])) ? $data['status'] : null,
//         'reason' => (isset($data['reason'])) ? $data['reason'] : null,
//         'customer_id' => (isset($data['customer_id'])) ? $data['customer_id'] : null,
//         'lat' => (isset($data['lat'])) ? $data['lat'] : null,
//         'long' => (isset($data['long'])) ? $data['long'] : null,
//         'sound' => "default",
//         'color' => "#203E78",
//     );

//     $fcmFields = array(
//         'to' => $reg_id,
//         'priority' => 'high',
//         // 'notification' => $fcmMsg,
//         'data' => $fcmMsg,
//     );

//     // old key
//     // 'Authorization: key=AAAAm4d1Vu0:APA91bF4i3_G_0qvFfQOyPVR3i8XwfZwNxReZCzFYjQNWDLVHEUw_y-wv8IjPRZh5CDVRDtYM6Wklt1eEkY-SM2dseYSQ-_LE0ybq15ms81KGsrrD3ts51bRnW0oM9XEZA-ADxDP2o7j',

//     // new key
//     // 'Authorization: key=AAAA8tDwzTk:APA91bFVM2Gu5O8JCm5IDrgH1tw0azAuBCALwm7RwBUHv7DM30UK0mn2GU4Z3QgKblhZDpVvfDuoBCX5oX1ilXgpCbZPijluC4qw1uqWPE_mEiBod_ymbtfj6X52fvhNS6VHA4YVY516',

//     $headers = array(
//         'Authorization: key=AAAAm4d1Vu0:APA91bF4i3_G_0qvFfQOyPVR3i8XwfZwNxReZCzFYjQNWDLVHEUw_y-wv8IjPRZh5CDVRDtYM6Wklt1eEkY-SM2dseYSQ-_LE0ybq15ms81KGsrrD3ts51bRnW0oM9XEZA-ADxDP2o7j',
//         'Content-Type: application/json',
//     );

//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmFields));
//     $result = curl_exec($ch);
//     if (curl_errno($ch)) {
//         $error_msg = curl_error($ch);
//         pre($error_msg);
//     }
//     curl_close($ch);
//     return $result . "\n\n";
// }

function sendNotificationAndroid($data, $reg_id)
{
    // Path to the service account key file
    $serviceAccountPath = storage_path('app/key/nfpc-presales-live-firebase-adminsdk-ofhlt-78b0226ac6.json');
    
    // Get an access token
    $accessToken = getAccessToken($serviceAccountPath);

    if (!$accessToken) {
        return 'Error: Unable to fetch access token.';
    }

    $notification = [
        'title' => $data['title'],
        'body' => $data['message']
    ];

    $extraData = [
        'noti_type' => (string)$data['noti_type'],
        'message' => (string)$data['message'],
        'salesman_comment' => isset($data['salesman_comment']) ? (string)$data['salesman_comment'] : '',
        'uuid' => isset($data['uuid']) ? (string)$data['uuid'] : null,
        'type' => isset($data['type']) ? (string)$data['type'] : null,
        'status' => isset($data['status']) ? (string)$data['status'] : null,
        'reason' => isset($data['reason']) ? (string)$data['reason'] : null,
        'customer_id' => isset($data['customer_id']) ? (string)$data['customer_id'] : null,
        'lat' => isset($data['lat']) ? (string)$data['lat'] : null,
        'long' => isset($data['long']) ? (string)$data['long'] : null,
        'no_of_days_approved' => isset($data['no_of_days_approved']) ? (string)$data['no_of_days_approved'] : null,
        'approved_amount' => isset($data['approved_amount']) ? (string)$data['approved_amount'] : null,
        'sound' => 'default',
        'color' => '#203E78'
    ];

    $payload = [
        'message' => [
            'token' => $reg_id,
            'notification' => $notification,
            'data' => $extraData,
            'android' => [
                'priority' => 'high'
            ]
        ]
    ];

    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/v1/projects/nfpc-presales-live/messages:send', $payload);

        $result = json_decode($response->getBody(), true);
        return json_encode($result);
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function getAccessToken($serviceAccountPath)
{
    // Read and decode the service account file
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

    // Check if the service account file was successfully read and decoded
    if (!$serviceAccount) {
        return false;
    }

    $now = time();
    $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtClaim = base64_encode(json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]));

    // Sign the JWT with the private key from the service account
    $privateKey = $serviceAccount['private_key'];
    $jwtSignature = '';
    openssl_sign($jwtHeader . '.' . $jwtClaim, $jwtSignature, $privateKey, 'SHA256');
    $jwt = $jwtHeader . '.' . $jwtClaim . '.' . base64_encode($jwtSignature);

    // Use cURL to request an access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === false) {
        return false;
    }

    $resultData = json_decode($result, true);
    return $resultData['access_token'] ?? false;
}

function requestLog($request, $model, $action)
{
    return RequestLog::create([
        'user_id' => $request->user()->id,
        'request' => $request->all(),
        'component' => $model,
        'action' => $action,
    ]);
}

/**
 * data is array
 * @param  [string] user_id  logged in user id
 * @param  [string] $url
 * @param  [string] $status
 *
 */
function saveNotificaiton($data)
{
    $nofitication = new Notifications();
    $nofitication->uuid = $data['uuid'];
    $nofitication->user_id = $data['user_id'];
    $nofitication->type = $data['type'];
    $nofitication->sender_id = (isset($data['sender_id'])) ? $data['sender_id'] : null;
    $nofitication->message = $data['message'];
    $nofitication->status = $data['status'];
    $nofitication->save();

    return true;
}

/**
 * this function use for save mail send data
 *
 * @param [array] $data
 * @return void
 */
function mailTrack($data)
{
    // $mail_track = MailTrack::create([
    //     'user_id' => $data['user_id'],
    //     'email' => $data['email'],
    //     'subject' => $data['subject'],
    //     'message' => $data['message'],
    // ]);

    // return $mail_track;
}

/**
 * This function is use for getting customer user_id
 *
 * @param [array] $channel_ids
 * @return void
 */
function channelCustomers($channel_ids)
{
    $customer_ids = array();
    if (is_array($channel_ids)) {
        $customerInfos = CustomerInfo::select('user_id')->whereIn('channel_id', $channel_ids)->get();
    } else {
        $customerInfos = CustomerInfo::select('user_id')->where('channel_id', $channel_ids)->get();
    }
    // $customerLob = CustomerLob::select('customer_info_id')->whereIn('channel_id', $channel_ids)->get();
    // $customerInfos2 = new Collection();
    // if (count($customerLob)) {
    //     $customerInfos2 = CustomerInfo::whereIn('id', $customerLob)->get();
    // }
    // $customer_id_user_id = $customerInfos->merge($customerInfos2);
    // if ($customer_id_user_id->count()) {
    //     $customer_ids = $customer_id_user_id->pluck('user_id')->toArray();
    // }
    if ($customerInfos->count()) {
        $customer_ids = $customerInfos->pluck('user_id')->toArray();
    }

    return $customer_ids;
}

/**
 * This function use for the get the channel_id based on login user id
 * There are manu type ASM,NSM,Supervisor, Sales Analysis
 * @param [array] $user_id (Login User id)
 * @return object of User channel attchaned
 */

function userChannelItems($user_id)
{
    $channel_ids = '';
    $item_ids = array();
    $user = User::find($user_id);
    if ($user) {
        $org_role = OrganisationRole::find($user->role_id);

        if ($org_role) {

            if ($org_role->name == "Sales Analyst") {

                // $channel_ids = UserChannelAttached::select('user_channel_id')
                //     ->where('user_id', $user->id)
                //     ->first();

                // if (is_object($channel_ids)) {
                //     $items = Item::select('id')->where('channel_id', $channel_ids->channel_id)->get();
                //     if (count($items)) {
                //         $item_ids = $items->pluck('id')->toArray();
                //     }
                // }

                $item_ids = itemsData($user->id);
            }

            if ($org_role->name = "NSM") {
                $Sales_Analyst_User = User::select('id')
                    ->where('role_id', 8)
                    ->where('id', $user->parent_id)
                    ->first();

                if ($Sales_Analyst_User) {
                    $item_ids = itemsData($Sales_Analyst_User->id);
                }
            }

            if ($org_role->name = "ASM") {
                $nsm_user = User::select('parent_id')
                    ->where('role_id', 6)
                    ->where('id', $user->parent_id)
                    ->first();

                if (is_object($nsm_user)) {
                    $Sales_Analyst_User = User::select('id')
                        ->where('role_id', 8)
                        ->where('id', $nsm_user->parent_id)
                        ->first();

                    if (is_object($Sales_Analyst_User)) {
                        $item_ids = itemsData($Sales_Analyst_User->id);
                    }
                }
            }

            if ($org_role->name = "Supervisor") {
                $asm_user = User::select('parent_id')
                    ->where('role_id', 7)
                    ->where('id', $user->parent_id)
                    ->first();

                if (is_object($asm_user)) {
                    $nsm_user = User::select('parent_id')
                        ->where('role_id', 6)
                        ->where('id', $asm_user->parent_id)
                        ->first();

                    if (is_object($nsm_user)) {
                        $Sales_Analyst_User = User::select('id')
                            ->where('role_id', 8)
                            ->where('id', $nsm_user->parent_id)
                            ->first();

                        if (is_object($Sales_Analyst_User)) {

                            $item_ids = itemsData($Sales_Analyst_User->id);
                        }
                    }
                }
            }
        }

        if ($user->usertype == 3) {
            $salesmanInfo = $user->salesmanInfo;

            if (is_object($salesmanInfo)) {

                $asm_users = User::select('id')
                    ->where('role_id', 7)
                    ->get();

                $asm_org = OrganisationRoleAttached::select('user_id')
                    ->with('user')
                    ->where('last_role_id', 'like', "%$salesmanInfo->salesman_supervisor%")
                    ->whereIn('user_id', $asm_users)
                    ->first();

                if (is_object($asm_org)) {
                    $nsm_user = User::select('parent_id')
                        ->where('role_id', 6)
                        ->where('id', $asm_org->user->parent_id)
                        ->first();

                    if (is_object($nsm_user)) {
                        $Sales_Analyst_User = User::select('id')
                            ->where('role_id', 8)
                            ->where('id', $nsm_user->parent_id)
                            ->first();
                        if (is_object($Sales_Analyst_User)) {
                            // $channel_ids = UserChannelAttached::select('user_channel_id')
                            //     ->where('user_id', $Sales_Analyst_User->id)
                            //     ->first();
                            // if (is_object($channel_ids)) {
                            //     $items = Item::select('id')
                            //         ->where('channel_id', $channel_ids->channel_id)
                            //         ->get();
                            //     if (count($items)) {
                            //         $item_ids = $items->pluck('id')->toArray();
                            //     }
                            // }

                            $item_ids = itemsData($Sales_Analyst_User->id);
                        }
                    }
                }
            }
        }
    }
    return $item_ids;
}

function itemsData($user_id)
{
    $item_ids = array();

    $uca = UserChannelAttached::select('user_channel_id')
        ->where('user_id', $user_id)
        ->first();

    if (is_object($uca)) {
        $brandChannel = BrandChannel::select('brand_id')
            ->where('user_channel_id', $uca->user_channel_id)
            ->get();

        if (count($brandChannel)) {
            $brand_ids = $brandChannel->pluck('brand_id')->toArray();
            $items = Item::select('id')
                ->whereIn('brand_id', $brand_ids)
                ->get();
            if (count($items)) {
                $item_ids = $items->pluck('id')->toArray();
            }
        }
    }
    return $item_ids;
}

function codeCheck($obj, $number, $req_number, $date_key = null)
{
    $module_path = 'App\\Model\\' . $obj;
    $module_query = $module_path::where($number, $req_number)->where('organisation_id', auth()->user()->organisation_id);
    // if (isset($date_key)) {
    //     if ($date_key == "created_at") {
    //         $module_query->whereDate($date_key, date('Y-m-d'));
    //     } else {
    //         $module_query->where($date_key, date('Y-m-d'));
    //     }
    // }
    $module = $module_query->first();

    return $module;
}

function getRouteBySalesman($salesman_id)
{
    if (empty($salesman_id)) {
        return null;
    }

    $salesmanInfo = SalesmanInfo::where('user_id', $salesman_id)->first();
    if (is_object($salesmanInfo)) {
        return $salesmanInfo->route_id;
    }

    return null;
}

function getRouteByVan($van_id)
{
    if (empty($van_id)) {
        return null;
    }

    $route = Route::where('van_id', $van_id)->first();
    if (is_object($route)) {
        return $route->id;
    }

    return null;
}

function getWarehuseBasedOnStorageLoacation($storage_location_id, $object = true)
{
    if (!$storage_location_id) {
        return null;
    }

    $warehouse = Storagelocation::where('id', $storage_location_id)->first();
    if ($warehouse) {
        if ($object) {
            return $warehouse->warehouse;
        }
        return $warehouse->warehouse_id;
    }

    return null;
}
function getWarehuseBasedOnStorageLoacation2($storage_location_id, $object = true)
{
    if (!$storage_location_id) {
        return null;
    }

    $warehouse = StoragelocationPrd::where('id', $storage_location_id)->first();
    if ($warehouse) {
        if ($object) {
            return $warehouse->warehouse;
        }
        return $warehouse->warehouse_id;
    }

    return null;
}

function getWarehouseByRoute($route_id)
{
    if (!$route_id) {
        return null;
    }

    $sl = Storagelocation::where('route_id', $route_id)
        ->where('loc_type', 2)
        ->first();
    if ($sl) {
        return $sl->id;
    }
    return null;
}

/**
 * This function is update the number range of salesman
 *
 * @param object $salesmanInfo
 * @param string $param // comes from table key
 * @param string $key // comes from request of key
 * @return void
 */
function updateMobileNumberRange($salesmanInfo, $param, $key)
{
    if ($salesmanInfo->salesmanRole->name == "Merchandiser") {
        $smr = SalesmanNumberRange::where('salesman_id', $salesmanInfo->id)
            ->first();
    } else {
        $smr = SalesmanNumberRange::where('route_id', $salesmanInfo->route_id)
            ->first();
    }

    if ($smr->$param < $key) {
        $smr->$param = $key;
        $smr->save();
    }
}

function getItemUPC($item_id, $item_uom_id)
{
    $item = Item::where('id', $item_id)
        ->where('lower_unit_uom_id', $item_uom_id)
        ->first();

    if (is_object($item)) {
        return $item->lower_unit_item_upc;
    }

    $item = Item::find($item_id);
    if ($item) {
        $item_main_price = ItemMainPrice::where('item_id', $item_id)
            ->where('item_uom_id', $item_uom_id)
            ->first();
        if (is_object($item_main_price)) {
            return $item_main_price->item_upc;
        }
    }
}

/**
 * get the salesman ids
 * @param type is string
 * @param ids is supervisor, or other ids
 *
 */
function getSalesmanIds($type, $ids)
{
    $id = array();
    $salesman_info_query = SalesmanInfo::select('id', 'user_id', 'region_id', 'salesman_supervisor');
    if ($type == "supervisor") {
        $salesman_info_query->where('salesman_supervisor', $ids);
    }

    if ($type == "region") {
        $salesman_info_query->where('region_id', $ids);
    }

    $salesman_infos = $salesman_info_query->get();

    if (count($salesman_infos)) {
        $id = $salesman_infos->pluck('user_id')->toArray();
    }

    return $id;
}

function setSalesmanNumberRange($code)
{
    $lenth = Str::length($code);

    $zero = '';
    if ($lenth < 7) {
        $sub_lenth = 6 - $lenth;
        for ($i = 1; $i <= $sub_lenth; $i++) {
            $zero .= "0";
        }
    }

    return $zero . $code;
}

function qtyConversion($item_id, $item_uom_id, $qty)
{
    $item = Item::find($item_id);
    if (!$item) {
        return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
    }

    // Lower uom and request uom is same then we convert in to the secondary uom
    // 145 lower == input 145
    if ($item->lower_unit_uom_id == $item_uom_id) {

        $main_price = ItemMainPrice::where('item_id', $item->id)
            ->where('is_secondary', 1)
            ->first();

        if ($main_price) {
            if ($qty > 0 && $main_price->item_upc > 0) {
                $qtys = $qty / $main_price->item_upc;

                return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qtys);
            }
        }
        return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
    }

    return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
}
function qtyConversion2($item_id, $item_uom_id, $qty)
{
    $item = ItemPrd::find($item_id);
    if (!$item) {
        return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
    }

    // Lower uom and request uom is same then we convert in to the secondary uom
    // 145 lower == input 145
    if ($item->lower_unit_uom_id == $item_uom_id) {

        $main_price = ItemMainPricePrd::where('item_id', $item->id)
            ->where('is_secondary', 1)
            ->first();

        if ($main_price) {
            if ($qty > 0 && $main_price->item_upc > 0) {
                $qtys = $qty / $main_price->item_upc;

                return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qtys);
            }
        }
        return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
    }

    return array('ItemId' => $item_id, 'UOM' => $item_uom_id, 'Qty' => $qty);
}
function get_invoice_sum($customer_id, $start_date, $end_date = "")
{
    if ($start_date != '' && $end_date != '') {
        $inv_total = DB::table('invoices')
            ->where('customer_id', $customer_id)
            ->whereBetween('invoices.invoice_due_date', [$start_date, $end_date])
            ->sum('invoices.grand_total');
        return $inv_total;
    } else if ($start_date != '' && $end_date == '') {
        $inv_total = DB::table('invoices')
            ->where('customer_id', $customer_id)
            ->whereDate('invoices.invoice_due_date', '>', $start_date)
            ->sum('invoices.grand_total');
        return $inv_total;
    } else {
        return 0;
    }
}

function getItemQtyByUom($itemid, $uom, $qty, $lower = false)
{
    $item = Item::select('lower_unit_uom_id', 'lower_unit_item_upc')
        ->where('id', $itemid)
        ->first();

    if (is_object($item)) {

        if ($item->lower_unit_uom_id != $uom) {
            $item_main_price = ItemMainPrice::where('item_id', $itemid)
                ->where('item_uom_id', $uom)
                ->first();

            if ($lower && !is_object($item_main_price)) {
                $qtys = $qty;
                if ($qty > 0 && $item->item_upc > 0) {
                    $qtys = $qty / $item->item_upc;
                }
            } else {
                $qtys = $qty;

                if ($qty > 0 && $item_main_price->item_upc > 0) {
                    $qtys = $qty / $item_main_price->item_upc;
                }
            }

            $result = array('ItemId' => $itemid, 'UOM' => $item->lower_unit_uom_id, 'Qty' => $qtys);
        } else {
            $result = array('ItemId' => $itemid, 'UOM' => $uom, 'Qty' => $qty);
        }
    } else {
        $result = array('ItemId' => $itemid, 'UOM' => $uom, 'Qty' => $qty);
    }

    return $result;
}

function getCustomerCode($user_id)
{
    $customer = CustomerInfo::select('id', 'customer_code', 'uer_id')
        ->where('user_id', $user_id)
        ->first();

    if (is_object($customer)) {
        return $customer->customer_code;
    }
    return null;
}

/**
 * This function is use for the get route cusotmer to salesman
 *
 * @param $salesman_id is array and int
 *
 */
function getRouteCustomer($salesman_id)
{
    $customer_routes = array();
    $customers = array();

    if (is_array($salesman_id)) {
        $salesman_info = SalesmanInfo::whereIn('user_id', $salesman_id)->get();
        if (count($salesman_info)) {
            $salesman_route_ids = $salesman_info->pluck('route_id')->toArray();
            $customer_routes = CustomerRoute::whereIn('route_id', $salesman_route_ids)->get();
            if (count($customer_routes)) {
                $customers = $customer_routes->pluck('customer_id')->toArray();
            }
        }
    } else {
        $salesman_info = SalesmanInfo::where('user_id', $salesman_id)->first();
        if (is_object($salesman_info)) {
            $customer_routes = CustomerRoute::where('route_id', $salesman_info->route_id)->get();
            if (count($customer_routes)) {
                $customers = $customer_routes->pluck('customer_id')->toArray();
            }
        }
    }

    return $customers;
}


function smallItemApplyPrice($customer_id, $item_id, $item_uom_id, $qty)
{
    $lower_uom = true;
    $item = Item::find($item_id);
    if (!$item) {
        return array('status' => false, 'error' => 'The selected item id is invalid.');
    }

    $item_excise = $item->item_excise;

    $itemPrice = Item::where('id', $item_id)
        ->where('lower_unit_uom_id', $item_uom_id)
        ->first();

    if (!$itemPrice) {
        $lower_uom = false;

        $itemPrice = ItemMainPrice::where('item_id', $item_id)
            ->where('item_uom_id', $item_uom_id)
            ->first();
    }

    $pdp_customer = PDPCustomer::where('customer_id', $customer_id)
        ->whereHas('priceDiscoPromoPlan', function ($q) {
            $q->where('start_date', '<=', date('Y-m-d'))
                ->where('end_date', '>=', date('Y-m-d'))
                ->where('status', 1);
        })
        ->first();

    if ($pdp_customer) {
        $pdp_item = PDPItem::where('item_id', $item_id)
            ->where('item_uom_id', $item_uom_id)
            ->whereHas('priceDiscoPromoPlan', function ($q) {
                $q->where('start_date', '<=', date('Y-m-d'))
                    ->where('end_date', '>=', date('Y-m-d'))
                    ->where('status', 1);
            })
            ->first();

        if (!$pdp_item) {
            $pdp_item = PDPItem::where('item_id', $item_id)
                ->whereHas('priceDiscoPromoPlan', function ($q) {
                    $q->where('start_date', '<=', date('Y-m-d'))
                        ->where('end_date', '>=', date('Y-m-d'))
                        ->where('status', 1);
                })
                ->first();

            if ($lower_uom) {
                $item_price = $itemPrice->lower_unit_uom_id * $qty;
            } else {
                if (!$itemPrice) {
                    $item_price = 0;
                } else {
                    $item_price = $itemPrice->item_upc * $qty;
                }
            }

            $item_exsies = ($item_excise > 0) ? $item_excise : 0;
            $totla_price = $item_price + $item_exsies;
            $item_gross = $qty * $totla_price;
            $net_gross = $qty * round($item_price, 2);
            $net_excise = $qty * round($item_exsies, 2);
            $total_net = $item_gross - 0;
            $item_vat = ($total_net * 5) / 100;
            $total = $total_net + $item_vat;
        } else {
            $item_exsies = ($item_excise > 0) ? $item_excise : 0;
            $item_price = $pdp_item->price;
            $totla_price = $pdp_item->price + $item_exsies;
            $item_gross = $qty * $totla_price;
            $net_gross = $qty * round($item_price, 2);
            $net_excise = $qty * round($item_exsies, 2);
            $total_net = $item_gross - 0;
            $item_vat = ($total_net * 5) / 100;
            $total = $total_net + $item_vat;
        }
    }


    $std_object = new stdClass;
    $std_object->item_qty = $qty;
    $std_object->item_price = number_format(round($item_price, 2), 2);
    $std_object->totla_price = number_format(round($totla_price, 2), 2);
    $std_object->item_gross = number_format($item_gross, 2);
    $std_object->net_gross = number_format($net_gross, 2);
    $std_object->net_excise = number_format($net_excise, 2);
    $std_object->discount = 0;
    $std_object->discount_percentage = 0;
    $std_object->discount_id = 0;
    $std_object->total_net = number_format($total_net, 2);
    $std_object->is_free = false;
    $std_object->is_item_poi = false;
    $std_object->promotion_id = null;
    $std_object->total_excise = number_format($item_excise, 2);
    $std_object->total_vat = number_format($item_vat, 2);
    $std_object->total = number_format($total, 2);

    // return $std_object;
    return array('status' => true, 'item' => $std_object);
}

function saveOrderDeliveryLog($data)
{
    OrderDeliveryLog::create([
        'created_user'          => ($data['created_user']) ? $data['created_user'] : NULL,
        'order_id'              => ($data['order_id']) ? $data['order_id'] : NULL,
        'delviery_id'           => ($data['delviery_id']) ? $data['delviery_id'] : NULL,
        'updated_user'          => ($data['updated_user']) ? $data['updated_user'] : NULL,
        'previous_request_body' => ($data['previous_request_body']) ? $data['previous_request_body'] : NULL,
        'request_body'          => ($data['request_body']) ? $data['request_body'] : NULL,
        'action'                => ($data['action']) ? $data['action'] : NULL,
        'status'                => ($data['status']) ? $data['status'] : NULL
    ]);
}

/**
 * Return CTN Quantity
 *
 * @param Object $detail
 * @return float
 */
function CTNQuantityOld(Object $detail): float
{
    $im = ItemMainPrice::where('item_id', $detail->item_id)
        ->where('is_secondary', 1)
        ->first();
    // as per the discussiton if main price with is_secondary != 1 then you same qty
    if ($im) {
        if ($im->item_uom_id === $detail->item_uom_id) {
            return $detail->item_qty;
        } else {
            return $detail->item_qty / $im->item_upc;
        }
    } else {
        return $detail->item_qty;
    }
}

function exciseConversation($item_excise, $item, $request)
{
    // 5 CT !== 7 OT
    // 5 CT !== 7 OT
    if ($request->item_uom_id !== $item->item_excise_uom_id) {

        $item_p = ItemMainPricePrd::where('item_id', $item->id)
            ->where('item_uom_id', $item->item_excise_uom_id)
            ->first();

        if ($item_p) {
            $pc_e = $item_excise / (($item_p->item_upc > 0) ? $item_p->item_upc : 1);

            $item_pr = ItemMainPricePrd::where('item_id', $item->id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

                if ($item_pr) {
                    return $pc_e * (($item_pr->item_upc > 0) ? $item_pr->item_upc : 1);
                }
            return $pc_e;
        }
    }
    return $item_excise;
}

function exciseConvert($item_excise, $item, $request)
{
    // 5 CT !== 7 OT
    if ($request->item_uom_id !== $item->item_excise_uom_id) {

        $item_p = ItemMainPricePrd::where('item_id', $item->id)
            ->where('item_uom_id', $item->item_excise_uom_id)
            ->first();

        if ($item_p) {
            $pc_e = $item_excise / (($item_p->item_upc > 0) ? $item_p->item_upc : 1);

            $item_pr = ItemMainPricePrd::where('item_id', $item->id)
                ->where('item_uom_id', $request->item_uom_id)
                ->first();

                if ($item_pr) {
                    return $pc_e * (($item_pr->item_upc > 0) ? $item_pr->item_upc : 1);
                }
            return $pc_e;
        }
    }
    return $item_excise;
}

/**
 * Return CTN Quantity
 *
 * @param Object $detail
 * @return float
 */
function CTNQuantity(Object $detail): float
{
    if (isset($detail->qty)) {
        $detail->item_qty = $detail->qty;
    }
    $uom = ItemUom::find($detail->item_uom_id);
    $ctUom = ItemUom::where('name', 'CT')->first();
    if ($uom && $uom->name == "CT") {
        return $detail->item_qty;
    }

    $ct_im = ItemMainPrice::where('item_id', $detail->item_id)
        ->where('item_uom_id', $ctUom->id)
        ->first();

    $oth_im = ItemMainPrice::where('item_id', $detail->item_id)
        ->where('item_uom_id', $detail->item_uom_id)
        ->first();

    // as per the discussiton if main price with is_secondary != 1 then you same qty
    if ($ct_im && $oth_im) {
        $oth_qty = $oth_im->item_upc * $detail->item_qty;
        if ($oth_qty > 0) {
            return $oth_qty / $ct_im->item_upc;
        } else {
            return $oth_qty;
        }
    } else {
        return $detail->item_qty;
    }
}
