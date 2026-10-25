@extends('dashboard.layouts.app')
@section('title', __('Show User'))

@section('content')
<div class="pc-container">
    <div class="pc-content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-block">
                <div class="page-header-title">
                    <h5 class="mb-0 font-medium">{{ __('Show User') }}</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('Admin.home') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('Admin.users.index') }}">{{ __('Users') }}</a></li>
                    <li class="breadcrumb-item" aria-current="page">{{ $user->name }}</li>
                </ul>
            </div>
        </div>

        <!-- User Details -->
        <div class="row mb-5">
            <div class="col-12 col-xl-8 offset-xl-2">
                <div class="card p-4 bg-white shadow-lg rounded">

                    <h4 class="mb-4">{{ __('User Details') }}</h4>

                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <td>{{ $user->name }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Email') }}</th>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Phone') }}</th>
                                <td>{{ $user->phone }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Role') }}</th>
                                <td>{{ $user->roles->first()?->name ?? __('No Role') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Active') }}</th>
                                <td>{{ $user->is_active == 1 ? __('Active') : __('Unactive') }}</td>
                            </tr>
                            <!-- Add more fields here if needed -->
                            <tr>
                                <th>{{ __('Created At') }}</th>
                                <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Updated At') }}</th>
                                <td>{{ $user->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-4">
                        <a href="{{ route('Admin.users.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back to Users') }}
                        </a>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
@endsection
