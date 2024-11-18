<?php

use App\Model\OrganisationRoleHasPermission;
use Illuminate\Database\Seeder;

class OrganisationRoleHasPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for ($p = 225; $p < 240; $p++) {
            $orhp = new OrganisationRoleHasPermission;
            $orhp->software_id = 3;
            // if ($p < 182) {
            //     $orhp->module_name = "Master";
            // }
            // if ($p > 181 && $p < 186) {
            //     $orhp->module_name = "Dashboard";
            // }
            // if ($p > 186 && $p < 204) {
            //     $orhp->module_name = "Reports";
            // }
            $orhp->module_name = "Master";
            $orhp->organisation_role_id = 2;
            $orhp->permission_id = $p;
            $orhp->save();
        }
    }
}
