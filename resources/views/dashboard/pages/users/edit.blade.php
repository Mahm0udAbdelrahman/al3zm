@extends('dashboard.layouts.app')
@section('title', __('Edit User'))

@section('content')
    <div class="pc-container">
        <div class="pc-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">{{ __('Edit User') }}</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('Admin.home') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('Admin.users.index') }}">{{ __('Users') }}</a></li>
                        <li class="breadcrumb-item" aria-current="page">{{ __('Edit User') }}</li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="row mb-5">
                <div class="col-12">
                    <form method="post" action="{{ route('Admin.users.update', $user->id) }}" enctype="multipart/form-data"
                        class="p-3 rounded shadow-lg bg-white">
                        @csrf
                        @method('PUT')

                        <div class="card border-0">
                            <div class="card-header bg-primary text-white rounded-top">
                                <h5 class="mb-0">{{ __('Edit User') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">


                                    <div class="col-md-6">
                                        <label for="name" class="form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" id="name" class="form-control"
                                            value="{{ old('name', $user->name) }}"
                                            placeholder="{{ __('Enter the user name') }}">
                                        @error('name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label">{{ __('Email') }}</label>
                                        <input type="email" name="email" id="email" class="form-control"
                                            value="{{ old('email', $user->email) }}"
                                            placeholder="{{ __('Enter the user email') }}">
                                        @error('email')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">{{ __('Phone') }}</label>
                                        <input type="number" name="phone" id="phone" class="form-control"
                                            value="{{ old('phone', $user->phone) }}"
                                            placeholder="{{ __('Enter the user Phone') }}">
                                        @error('phone')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                      <div class="col-md-6">
                                        <label for="image" class="form-label">{{ __('Image') }}</label>
                                        <input type="file" name="image" value="{{ old('image') }}" id="image"
                                            class="form-control" placeholder="{{ __('Enter the user image') }}">
                                        @error('image')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="is_active" class="form-label">{{ __('Is Active') }}</label>
                                        <select class="form-select" name="is_active" id="is_active">
                                            <option value="" disabled>{{ __('Choose is_active...') }}</option>
                                            <option value="0" {{ old('is_active', $user->is_active) == 0 ? 'selected' : '' }}>{{ __('UnActive') }}</option>
                                            <option value="1" {{ old('is_active', $user->is_active) == 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                        </select>
                                        @error('is_active')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="role_id" class="form-label">{{ __('Role') }}</label>
                                        <select class="form-select" name="role_id" id="role_id">
                                            <option value="" disabled>{{ __('Choose the user role') }}</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" {{ old('role_id', $user->roles->first()?->id) == $role->id ? 'selected' : '' }}>
                                                    {{ $role->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('role_id')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                           <div class="col-md-6">
                                        <label for="password" class="form-label">{{ __('Password') }}</label>
                                        <input type="password" name="password" value="{{ old('password') }}" id="password"
                                            class="form-control" placeholder="{{ __('Enter the user password') }}">
                                        @error('password')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>



                                </div>
                            </div>
                            <div class="card-footer text-end bg-light rounded-bottom">
                                <button type="submit" class="btn btn-primary px-4">{{ __('Update') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
