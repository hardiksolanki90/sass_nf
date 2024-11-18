<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\UserLoginLog;
use App\User;
use Illuminate\Support\Collection;

class LogDetailController extends Controller
{
    public function getUserLogsDetail($user_id)
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $get_user             = User::find($user_id);
        $user_login_log_query = UserLoginLog::where('user_id', $user_id)->select('created_at', 'user_id', 'ip', 'browser')
            ->with(
                'user:id,firstname,lastname'
            );

        $user_login_log = $user_login_log_query->get();

        $userLogCollection = new Collection();

        foreach ($user_login_log as $key => $value) {
            $userLogCollection->push((object) [
                'date'      => $value->created_at,
                'user_name' => $value->user->firstname . ' ' . $value->user->lastname,
                'ip'        => $value->ip,
                'browser'   => $value->browser,
            ]);
        }
        $username = $get_user->firstname . ' ' . $get_user->lastname;

        return prepareResult(true, $userLogCollection, [], "Login details for " . $username . " listing", $this->success);

    }
}
