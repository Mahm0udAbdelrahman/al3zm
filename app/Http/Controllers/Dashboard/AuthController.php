<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Dashboard\Auth\LoginRequest;
use Illuminate\Support\Facades\{Session,Auth};

class AuthController extends Controller
{
    public function login()
    {
        return view('dashboard.pages.auth.login');
    }

    public function loginAction(LoginRequest $loginRequest)
    {
        if (Auth::attempt($loginRequest->validated()))
        {
            Session::flash('message', ['type' => 'success', 'text' => __('Welcome Home!')]);
            return redirect()->route('Admin.home');
        }
        Session::flash('message', ['type' => 'error', 'text' => __('Invalid credentials!')]);
        return redirect()->back();
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
