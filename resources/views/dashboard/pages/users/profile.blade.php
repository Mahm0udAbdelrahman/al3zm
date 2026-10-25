@extends('dashboard.layouts.app')
@section('title', __('Profile'))

@section('content')
    <main class="main-wrapper">
        <div class="main-content">
            <div class="row mb-5">
                <div class="col-12">
                    <form method="post" action="{{ route('Admin.updateProfile') }}" class="p-4 rounded shadow-lg bg-white" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="card border-0">
                            <div class="card-header bg-primary text-white rounded-top">
                                <h5 class="mb-0">{{ __('Update Profile') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label for="name" class="form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" placeholder="{{ __('Enter your name') }}">
                                        @error('name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label">{{ __('Email') }}</label>
                                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" placeholder="{{ __('Enter your email') }}">
                                        @error('email')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">{{ __('Phone') }}</label>
                                        <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="{{ __('Enter your phone number') }}">
                                        @error('phone')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="country_id" class="form-label">{{ __('Country') }}</label>
                                        <select class="form-select" name="country_id" id="country_id">
                                            <option disabled>{{ __('Choose Country...') }}</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}" {{ $user->country_id == $country->id ? 'selected' : '' }}>
                                                    {{ $country->{'name_'.app()->getLocale()} }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('country_id')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="image" class="form-label">{{ __('Image') }}</label>
                                        <input type="file" name="image" id="image" class="form-control">
                                        @if ($user->image)
                                            <img src="{{ asset('uploads/'.$user->image) }}" alt="User Image" class="mt-2 rounded" width="80">
                                        @endif
                                        @error('image')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password" class="form-label">{{ __('Password') }}</label>
                                        <input type="password" name="password" id="password" class="form-control" placeholder="{{ __('Enter your password') }}">
                                        @error('password')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="{{ __('Confirm your password') }}">
                                        @error('password_confirmation')
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
    </main>
@endsection
