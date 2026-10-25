<?php
namespace App\Services\Dashboard;

use App\Models\User;
use App\Traits\HasImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    use HasImage;
    public function __construct(public User $user)
    {}

    public function index()
    {

        return $this->user->whereNot('id', auth()->user()->id)->latest()->paginate(10);
    }

    public function create()
    {
        return Role::select(['id', 'name'])->get();
    }

    public function store($data)
    {
        $data['password'] = Hash::make($data['password']);

        if (isset($data['image'])) {
            $data['image'] = $this->saveImage($data['image'], 'user');
        } else {
            $data['image'] = asset('public/default/default.png');
        }

        $user = $this->user->create($data);

        if (isset($data['role_id'])) {
            DB::table('model_has_roles')->insert([
                'model_type' => 'App\\Models\\User',
                'model_id'   => $user->id,
                'role_id'    => $data['role_id'],
            ]);
        }

        return $user;
    }

    public function show($id)
    {
        return $this->user->findOrFail($id);
    }

    public function update($id, $data)
    {
        $user = $this->user->findOrFail($id);

        $data['password'] = $data['password'] ? Hash::make($data['password']) : $user->password;
        if (isset($data['image'])) {
            $data['image'] = $this->saveImage($data['image'], 'user');
        } else {
            $data['image'] = $user->image;
        }
        $user->update($data);
        if (isset($data['role_id']) && ! empty($data['role_id'])) {
            $criteria   = ['model_id' => $user->id];
            $attributes = [
                'model_type' => 'App\\Models\\User',
                'model_id'   => $user->id,
                'role_id'    => $data['role_id'],
            ];
            DB::table('model_has_roles')->updateOrInsert($criteria, $attributes);
        } else {
            DB::table('model_has_roles')->where('model_id', $user->id)->delete();
        }

        return $user;

    }

    public function destroy($id)
    {
        $user = $this->user->findOrFail($id);

        $user->delete();

        return $user;
    }

    public function bulkDelete($ids)
    {
        return $this->user->whereIn('id', $ids)->delete();
    }

}
