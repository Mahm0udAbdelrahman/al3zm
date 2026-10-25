<?php
namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use App\Models\Nurse;
use App\Models\Banner;
use App\Models\Course;
use App\Models\Patient;
use App\Models\Analysis;
use App\Models\RaysType;
use App\Models\BloodType;
use App\Models\BloodGroup;
use App\Models\OrderPatient;
use App\Models\AnalysisCategory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller
{
    public function index()
    {

        $user = User::count();
        return view('dashboard.pages.home', compact('user'));
    }

    public function confirmDelete($model, $id)
    {
        $data = app('App\\Models\\' . ucfirst($model))->find($id);
        if ($model == 'role') {
            $data->revokePermissionTo($data->permissions);
        }

        if ($model == 'user') {
            DB::table('model_has_roles')->where('model_id', $id)->delete();

        }

        $data->delete();
        Session::flash('message', ['type' => 'success', 'text' => __('Deleted successfully')]);
        return redirect()->back();
    }

}
