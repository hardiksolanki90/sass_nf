<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\ItemBranchPlant;
use Illuminate\Http\Request;

class ItemBranchPlantController extends Controller
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

        $ibp_query = ItemBranchPlant::with(
            'lob:id,name',
            'item:id,item_name,item_code',
            'storagelocation:id,name,code'
        );

        if ($request->branch_plant) {
            $cc = $request->branch_plant;
            $ibp_query->whereHas('storagelocation', function ($q) use ($cc) {
                $q->where('code', $cc);
            });
        }

        if ($request->item_code) {
            $cc = $request->item_code;
            $ibp_query->whereHas('item', function ($q) use ($cc) {
                $q->where('item_code', $cc);
            });
        }

        if ($request->item_name) {
            $cc = $request->item_name;
            $ibp_query->whereHas('item', function ($q) use ($cc) {
                $q->where('item_name', 'like', "%$cc%");
            });
        }

        if ($request->sales_org) {
            $cc = $request->sales_org;
            $ibp_query->whereHas('lob', function ($q) use ($cc) {
                $q->where('lob_code', $cc);
            });
        }

        if ($request->status) {
            $ibp_query->where('status', $request->status);
        }

        $all_ibps = $ibp_query->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $ibps = $all_ibps->items();

        $pagination = array();
        $pagination['total_pages'] = $all_ibps->lastPage();
        $pagination['current_page'] = (int)$all_ibps->perPage();
        $pagination['total_records'] = $all_ibps->total();

        return prepareResult(true, $ibps, [], "Item Branch Plant", $this->success, $pagination);
    }

    public function indexMobile()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $item_ids = userChannelItems(request()->user()->id);

        $ibps = ItemBranchPlant::with(
            'lob:id,name',
            'item:id,item_name,item_code',
            'storagelocation:id,name,code'
        )
            ->whereIn('item_id', $item_ids)
            ->get();

        return prepareResult(true, $ibps, [], "Item Branch Plant", $this->success);
    }
}
