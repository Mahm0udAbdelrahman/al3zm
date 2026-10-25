<?php

namespace App\Http\Controllers\Dashboard;

use App\Traits\HasImage;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\Dashboard\User\{StoreUserRequest, UpdateUserRequest};
use App\Services\Dashboard\UserService;

class UserController extends Controller
{
    public function __construct(public UserService $userService) {}
    use HasImage;
    public function index(Request $request)
    {
        $users = $this->userService->index();
        return view('dashboard.pages.users.index', compact('users'));
    }
    public function create()
    {
        $roles = $this->userService->create();
        return view('dashboard.pages.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $storeUserRequest)
    {

        $data = $storeUserRequest->validated();
        $this->userService->store($data);

        Session::flash('message', ['type' => 'success', 'text' => __('User created successfully')]);
        return redirect()->route('Admin.users.index');
    }

    public function show($id)
    {
        $user = $this->userService->show($id);
        return view('dashboard.pages.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = $this->userService->create();
        return view('dashboard.pages.users.edit', compact('user', 'roles'));
    }

    public function update($id, UpdateUserRequest $updateUserRequest)
    {

        $data = $updateUserRequest->validated();
        $this->userService->update($id, $data);
        Session::flash('message', ['type' => 'success', 'text' => __('User updated successfully')]);
        return redirect()->route('Admin.users.index');
    }

    public function destroy(string $id)
    {
        $this->userService->destroy($id);

        return redirect()->route('Admin.users.index')->with('success', 'User Successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
        ]);

        $this->userService->bulkDelete($request->ids);

        return redirect()->back()->with('success', 'Users deleted successfully');
    }
}
