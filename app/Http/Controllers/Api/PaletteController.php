<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Palette;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaletteController extends Controller
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

        $palette = Palette::select('id', 'uuid', 'date', 'type', 'salesman_id', 'item_id', 'qty')
            ->with(
                'item:id,item_code,item_name',
                'salesman:id,firstname,lastname',
                'salesmanInfo:id,user_id,salesman_code',
            );

        if ($request->salesman_name) {
            $name = $request->salesman_name;
            $exploded_name = explode(" ", $name);
            if (count($exploded_name) < 2) {
                $palette->whereHas('salesman', function ($q) use ($name) {
                    $q->where('firstname', 'like', '%' . $name . '%')
                        ->orWhere('lastname', 'like', '%' . $name . '%');
                });
            } else {
                foreach ($exploded_name as $n) {
                    $palette->whereHas('salesman', function ($q) use ($n) {
                        $q->where('firstname', 'like', '%' . $n . '%')
                            ->orWhere('lastname', 'like', '%' . $n . '%');
                    });
                }
            }
        }

        if ($request->salesman_code) {
            $salesman_code = $request->salesman_code;
            $palette->whereHas('salesmanInfo', function ($q) use ($salesman_code) {
                $q->where('salesman_code', 'like', '%' . $salesman_code . '%');
            });
        }


        if ($request->item_code) {
            $item_code = $request->item_code;
            $palette->whereHas('item', function ($q) use ($item_code) {
                $q->where('item_code', 'like', '%' . $item_code . '%');
            });
        }

        if ($request->item_name) {
            $item_name = $request->item_name;
            $palette->whereHas('item', function ($q) use ($item_name) {
                $q->where('item_name', 'like', '%' . $item_name . '%');
            });
        }

        $all_palette = $palette->orderBy('id', 'desc')->paginate((!empty($request->page_size)) ? $request->page_size : 10);
        $palettes = $all_palette->items();

        $pagination = array();
        $pagination['total_pages'] = $all_palette->lastPage();
        $pagination['current_page'] = (int)$all_palette->perPage();
        $pagination['total_records'] = $all_palette->total();

        return prepareResult(true, $palettes, [], "Palettes listing", $this->success, $pagination);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexBySalesman($salesman_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$salesman_id) {
            return prepareResult(false, [], [], "Salesman not found.", $this->unprocessableEntity);
        }

        $Palette = Palette::select('id', 'uuid', 'date', 'type', 'salesman_id', 'item_id', 'qty')
            ->with(
                'item:id,item_code,item_name',
                'salesman:id,firstname,lastname',
                'salesmanInfo:id,user_id,salesman_code',
            )
            ->where('salesman_id', $salesman_id)
            ->where('approval_status', 1)
            ->get();

        return prepareResult(true, $Palette, [], "Palettes listing", $this->success);
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
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating palette", $this->unprocessableEntity);
        }

        $palette = new Palette();
        $palette->date          = $request->date;
        $palette->salesman_id   = $request->salesman_id;
        $palette->item_id       = $request->item_id;
        $palette->qty           = $request->qty;
        $palette->type          = $request->type;
        $palette->approval_status = 1;
        $palette->is_accepted   = 0;
        $palette->save();

        return prepareResult(true, $palette, [], "Palette added successfully", $this->created);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Model\Palette  $palette
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $palette_q = Palette::selectRaw(
            "SUM(CASE WHEN type = 'add' THEN qty ELSE 0 END) AS total_allocated, " .
                "SUM(CASE WHEN type = 'return' THEN qty ELSE 0 END) AS total_return, 
            SUM(CASE WHEN type = 'add' THEN qty ELSE 0 END) - SUM(CASE WHEN type = 'return' THEN qty ELSE 0 END) as pending,
            item_id,
            items.item_code,
            items.item_name,
            concat(users.firstname,' ', users.lastname) as salesman,
            date, 
            salesman_infos.user_id as salesman_id, 
            salesman_infos.salesman_code"

        )
            ->join('salesman_infos', function ($join) {
                $join->on('palettes.salesman_id', '=', 'salesman_infos.user_id');
            })
            ->join('users', function ($join) {
                $join->on('palettes.salesman_id', '=', 'users.id');
            })
            ->join('items', function ($join) {
                $join->on('palettes.item_id', '=', 'items.id');
            })
            ->withoutGlobalScope('organisation_id')
            ->where('palettes.organisation_id', $request->user()->organisation_id)
            ->where('approval_status', 1);
        if ($request->salesman_id) {
            $palette_q->where('salesman_id', $request->salesman_id);
        }

        $palette = $palette_q->groupBy('salesman_id')
            ->get();

        // $palette_query = Palette::select(
        //     'date',
        //     'salesman_infos.user_id as salesman_id',
        //     'salesman_infos.salesman_code',
        //     'items.item_code',
        //     DB::raw('concat(users.firstname, " ", users.lastname) as salesman'),
        //     // DB::raw('IF(type = Return, SUM(qty), 0) as total_retun'),
        //     DB::raw("(CASE WHEN type = 'add' THEN SUM(qty) ELSE 0 END) AS total_allocated"),
        //     DB::raw("(CASE WHEN type = 'return' THEN SUM(qty) ELSE 0 END) AS total_return"),
        // )
        //     ->join('salesman_infos', function ($join) {
        //         $join->on('palettes.salesman_id', '=', 'salesman_infos.user_id');
        //     })
        //     ->join('users', function ($join) {
        //         $join->on('palettes.salesman_id', '=', 'users.id');
        //     })
        //     ->join('items', function ($join) {
        //         $join->on('palettes.item_id', '=', 'items.id');
        //     })
        //     ->withoutGlobalScope('organisation_id')
        //     ->where('palettes.organisation_id', $request->user()->organisation_id);

        // if ($request->salesman_id) {
        //     $palette_query->where('salesman_id', $request->salesman_id);
        // }

        // $palette = $palette_query->groupBy('salesman_id')
        //     ->get();

        // if (count($palette)) {
        //     foreach ($palette as $k => $p) {
        //         $palette[$k]->pending = $p->total_allocated - $p->total_return;
        //     }
        // }

        return prepareResult(true, $palette, [], "Palette listed", $this->success);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Model\Palette  $palette
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$uuid) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $palette = Palette::with(
            'item:id,item_code,item_code',
            'salesman:id,firstname,lastname',
            'salesmanInfo:id,user_id,salesman_code',
        )
            ->where('uuid', $uuid)
            ->first();

        if ($palette) {
            return prepareResult(true, $palette, [], "Palette added successfully", $this->created);
        }

        return prepareResult(false, [], [], "Palette not found", $this->not_found);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Model\Palette  $palette
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Palette $palette)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Model\Palette  $palette
     * @return \Illuminate\Http\Response
     */
    public function destroy(Palette $palette)
    {
        //
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;

        if ($type == 'add') {
            $validator = Validator::make($input, [
                'item_id'       => 'required|integer|exists:items,id',
                'date'          => 'required|date',
                'type'          => 'required',
                'salesman_id'   => 'required|integer|exists:users,id',
            ]);
        }

        if ($type == 'pallet-by-salesman') {
            $validator = Validator::make($input, [
                'date'          => 'required|date',
                'salesman_id'   => 'required|integer|exists:users,id',
            ]);
        }

        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();
        }

        return ["error" => $error, "errors" => $errors];
    }

    public function indexPalletBySalesman(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "pallet-by-salesman");
        if ($validate["error"]) {
            return prepareResult(false, [], $validate['errors']->first(), "Error while validating palette", $this->unprocessableEntity);
        }

        $pallets = Palette::select('id', 'date', 'type', 'salesman_id', 'item_id', 'qty', 'is_accepted', 'approval_status', 'request_number')
            ->with(
                'item:id,item_code,item_name',
                'salesman:id,firstname,lastname',
                'salesmanInfo:id,user_id,salesman_code',
            )
            ->where('salesman_id', $request->salesman_id)
            ->where('date', $request->date)
            ->where('type', 'add')
            ->where('is_accepted', 0)
            ->get();

        return prepareResult(true, $pallets, [], "Palette lists", $this->success);
    }

    /**
     * This is update the status is_accepted
     *
     * @param Request $request
     * @return void
     */
    public function updatePalletStatus(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->pallet_ids) && sizeof($request->pallet_ids) < 1) {
            return prepareResult(false, [], ['error' => 'Error Please add atleast one pallet id.'], "Error Please add atleast one pallet id.", $this->unauthorized);
        }

        foreach ($request->pallet_ids as $id) {
            $pallet = Palette::find($id);
            if ($pallet) {
                $pallet->is_accepted = $request->is_accepted;
                $pallet->save();
            }
        }

        return prepareResult(true, [], [], "Pallet status updated.", $this->success);
    }

    public function indexByRetunPending($salesman_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!$salesman_id) {
            return prepareResult(false, [], [], "Salesman not found.", $this->unprocessableEntity);
        }

        $pallets = Palette::select('id', 'date', 'type', 'salesman_id', 'item_id', 'qty', 'qty as original_qty', 'is_accepted', 'approval_status', 'request_number')
            ->with(
                'item:id,item_code,item_name',
                'salesman:id,firstname,lastname',
                'salesmanInfo:id,user_id,salesman_code',
            )
            ->where('type', 'return')
            ->where('salesman_id', $salesman_id)
            ->where('approval_status', 0)
            ->get();

        return prepareResult(true, $pallets, [], "Palette lists", $this->success);
    }

    public function updatePalletApprovalStatus(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->pallets) && sizeof($request->pallets) < 1) {
            return prepareResult(false, [], ['error' => 'Error Please add atleast one pallet.'], "Error Please add atleast one pallet.", $this->unauthorized);
        }

        foreach ($request->pallets as $pallet) {
            $palletobj = Palette::find($pallet['id']);
            if ($palletobj) {
                $palletobj->approval_status = $pallet['approval_status'];
                $palletobj->qty = $pallet['qty'];
                $palletobj->save();
            }
        }

        return prepareResult(true, [], [], "Pallets updated.", $this->success);
    }

    public function storeReturn(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        if (!is_array($request->pallets) && sizeof($request->pallets) < 1) {
            return prepareResult(false, [], ['error' => 'Error Please add atleast one pallet.'], "Error Please add atleast one pallet.", $this->unauthorized);
        }

        foreach ($request->pallets as $pallet) {
            $palette = new Palette();
            $palette->date              = $pallet['date'];
            $palette->salesman_id       = $pallet['salesman_id'];
            $palette->item_id           = $pallet['item_id'];
            $palette->qty               = $pallet['qty'];
            $palette->type              = $pallet['type'];
            $palette->request_number    = $pallet['request_number'];
            $palette->is_accepted       = 1;
            $palette->approval_status   = 0;
            $palette->save();
        }

        return prepareResult(true, [], [], "Pallets return added.", $this->success);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Model\Palette  $palette
     * @return \Illuminate\Http\Response
     */
    public function showReturn(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        // IF(approval_status = 1, SUM(CASE WHEN type = 'add' THEN qty ELSE 0 END), 0) as total, " .
        //     "IF(approval_status = 1, SUM(CASE WHEN type = 'return' THEN qty ELSE 0 END), 0) as return,
        $palette = Palette::selectRaw(
            "SUM(CASE WHEN type = 'add' THEN qty ELSE 0 END) AS total_allocated, " .
                "SUM(CASE WHEN type = 'return' THEN qty ELSE 0 END) AS total_return, 
            SUM(CASE WHEN type = 'add' THEN qty ELSE 0 END) - SUM(CASE WHEN type = 'return' THEN qty ELSE 0 END) as pending,
            item_id,
            items.item_code,
            items.item_name"
        )
            ->join('items', function ($join) {
                $join->on('palettes.item_id', '=', 'items.id');
            })
            ->withoutGlobalScope('organisation_id')
            ->where('palettes.organisation_id', $request->user()->organisation_id)
            ->where('salesman_id', $request->salesman_id)
            ->where('approval_status', 1)
            ->groupBy('items.item_code')
            ->get();

        return prepareResult(true, $palette, [], "Palette listed", $this->success);
    }
}
