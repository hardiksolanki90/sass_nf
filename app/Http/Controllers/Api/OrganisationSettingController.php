<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\OrganisationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrganisationSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
            return prepareResult(false, [], ["error" => "Unauthorized access"], "Unauthorized access.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating order type", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {

            $os = new OrganisationSetting();
            $os->user_id = $request->user_id;
            $os->main_price_active = $request->main_price_active;
            $os->save();
            DB::commit();
            return prepareResult(true, $os, [], "Organisation setting added successfully", $this->created);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  uuid  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], ["error" => "Unauthorized access"], "Unauthorized access.", $this->unauthorized);
        }

        $os =  OrganisationSetting::where('uuid', $uuid)->first();

        return prepareResult(true, $os, [], "Organisation setting successfully", $this->success);
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
            return prepareResult(false, [], ["error" => "Unauthorized access"], "Unauthorized access.", $this->unauthorized);
        }

        $input = $request->json()->all();
        $validate = $this->validations($input, "add");

        if ($validate["error"]) {
            return prepareResult(false, [], ['error' => $validate['errors']->first()], "Error while validating order type", $this->unprocessableEntity);
        }

        DB::beginTransaction();
        try {

            $os =  OrganisationSetting::where('uuid', $uuid)->first();
            $os->user_id = $request->user_id;
            $os->main_price_active = $request->main_price_active;
            $os->save();
            DB::commit();
            return prepareResult(true, $os, [], "Organisation setting updated successfully", $this->success);
        } catch (\Exception $exception) {
            DB::rollback();
            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        } catch (\Throwable $exception) {
            DB::rollback();
            return prepareResult(false, [], ['error' => $exception->getMessage()], "Oops!!!, something went wrong, please try again.", $this->internal_server_error);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    private function validations($input, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "add") {
            $validator = Validator::make($input, [
                'user_id'          => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        }

        return ["error" => $error, "errors" => $errors];
    }
}
