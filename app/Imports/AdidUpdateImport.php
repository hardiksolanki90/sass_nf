<?php

namespace App\Imports;

use App\User;
use Maatwebsite\Excel\Concerns\ToModel;

class AdidUpdateImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if (isset($row[0]) && $row[0] != 'AD ID') {
            $user = User::where('email', 'like', "%" . $row[1] . "%")->first();
            if (is_object($user)) {
                $user->ad_id = $row[0];
                $user->save();
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }
}
