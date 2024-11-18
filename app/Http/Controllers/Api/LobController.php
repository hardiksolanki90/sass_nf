<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Lob;
use Illuminate\Http\Request;

class LobController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $lob = Lob::orderBy('name', 'asc')->get();

        $lob_array = array();
        if (is_object($lob)) {
            foreach ($lob as $key => $lob1) {
                $lob_array[] = $lob[$key];
            }
        }

        $data_array = array();
        $page = (isset($request->page)) ? $request->page : '';
        $limit = (isset($request->page_size)) ? $request->page_size : '';
        $pagination = array();
        if ($page != '' && $limit != '') {
            $offset = ($page - 1) * $limit;
            for ($i = 0; $i < $limit; $i++) {
                if (isset($lob_array[$offset])) {
                    $data_array[] = $lob_array[$offset];
                }
                $offset++;
            }

            $pagination['total_pages'] = ceil(count($lob_array) / $limit);
            $pagination['current_page'] = (int)$page;
            $pagination['total_records'] = count($lob_array);
        } else {
            $data_array = $lob_array;
        }

        return prepareResult(true, $data_array, [], "Market promotion listing", $this->success, $pagination);
    }
}
