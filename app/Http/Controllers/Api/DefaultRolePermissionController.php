<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Model\PermissionGroup;

class DefaultRolePermissionController extends Controller
{
    public function index()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $defaultRoles = Role::select('id', 'name')->whereNotIn('name', ['superadmin', 'admin'])->get();

        return prepareResult(true, $defaultRoles, [], "Default Roles list", $this->success);
    }

    public function rolesPermission()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $defaultRoles = Role::select('id', 'name')->whereNotIn('name', ['superadmin', 'admin'])->with('permissions:id,name,group_id')->get();

        return prepareResult(true, $defaultRoles, [], "Default Roles with permissions list", $this->success);
    }

    public function groupPermissions()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $allPermissions = PermissionGroup::select('id', 'name', 'module_name')
            ->whereNotIn('id', ['1', '2'])
            ->with('permissions:id,name,group_id')
            ->orderBy('module_name', 'asc')
            ->get();

        $data = array();

        foreach ($allPermissions as $key => $permissions) {
            if ($permissions->module_name == "Dashboard") {
                $data['dashboard']['module'] = $permissions->module_name;
                $data['dashboard']['submodules'][] = $permissions;
            }

            if ($permissions->module_name == "Master") {
                $data['master']['module'] = $permissions->module_name;
                $data['master']['submodules'][] = $permissions;
            }

            if ($permissions->module_name == "Reports") {
                $data['reports']['module'] = $permissions->module_name;
                $data['reports']['submodules'][] = $permissions;
            }
        }

        return prepareResult(true, array($data), [], "Group wise permission list", $this->success);
    }

    public function permissions()
    {
        if (!$this->isAuthorized) {
            return prepareResult(false, [], [], "User not authenticate", $this->unauthorized);
        }

        $defaultRoles = Permission::select('id', 'name')->whereNotIn('group_id', ['1', '2'])->get();

        return prepareResult(true, $defaultRoles, [], "All permissions list", $this->success);
    }
}
