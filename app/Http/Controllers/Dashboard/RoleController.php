<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\{DB,Session};
use App\Http\Requests\Dashboard\Role\{RoleRequest,UpdateRoleRequest,PermissionRequest};

class RoleController extends Controller
{
    public function index(Request $request)
    {

        $roles = Role::paginate(10);
        return view('dashboard.pages.roles.index',compact('roles'));
    }

    public function create()
    {

        $permissions = Permission::all();
        $groupedPermissions = $permissions->groupBy(function ($perm) {
    return explode('-', $perm->name)[0];
});

        return view('dashboard.pages.roles.create',compact('permissions','groupedPermissions'));
    }

    public function store(RoleRequest $request, PermissionRequest $permissionRequest)
    {
        if ($request->select_all) {
            $permissions = json_decode($request->select_all);
        } else {
            $permissions = $permissionRequest->permission_name;
        }

        $role = Role::create($request->validated());
        $role->syncPermissions($permissions);

        Session::flash('message', ['type' => 'success', 'text' => __('Role created successfully')]);
        return redirect()->route('Admin.roles.index');
    }

    public function edit(Role $role)
    {

        $ids = $role->permissions->pluck('id')->toArray();
        $permissions = Permission::all();
        $permissionNum = $role->permissions->count();
          $groupedPermissions = $permissions->groupBy(function ($perm) {
    return explode('-', $perm->name)[0];
});
        return view('dashboard.pages.roles.edit', compact('role','permissions','permissionNum','ids','groupedPermissions'));
    }

    public function update(UpdateRoleRequest $request,PermissionRequest $req, Role $role)
    {


        if ($request->select_all) {
            $permissions = json_decode($request->select_all);
        } else {
            $permissions = $req->permission_name;
        }
        $role->update(['name' => $request->name]);
        $role->syncPermissions($permissions);

        Session::flash('message', ['type' => 'success', 'text' => __('Role updated successfully')]);
        return redirect()->route('Admin.roles.index');
    }

    public function destroy($id)
    {
       $role = Role::findOrFail($id);
       $role->revokePermissionTo($role->permissions);
       $role->delete();
       return redirect()->route('Admin.roles.index');
    }
}
