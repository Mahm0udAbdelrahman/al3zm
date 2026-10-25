@extends('dashboard.layouts.app')
@section('title', __('Add Send Notification'))

@section('content')
    <div class="pc-container">
        <div class="pc-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">{{ __('Add Send Notification') }}</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('Admin.home') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('Admin.users.index') }}">{{ __('Send Notifications') }}</a></li>
                        <li class="breadcrumb-item" aria-current="page">{{ __('Add Send Notification') }}</li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="row mb-5">
                <div class="col-12">
                    <form method="post" action="{{ route('Admin.send_notifications.store') }}"
                        enctype="multipart/form-data" class="p-3 rounded shadow-lg bg-white">
                        @csrf
                        <div class="card border-0">
                            <div class="card-header bg-primary text-white rounded-top">
                                <h5 class="mb-0">{{ __('Add Send Notification') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="topic" class="form-label">{{ __('Topic') }}</label>
                                        <select class="form-select" name="topic" id="topic">
                                            <option value="" {{ old('topic') === null ? 'selected' : '' }}>
                                                {{ __('Choose topic...') }}</option>
                                            <option value="customer" {{ old('topic') === 'customer' ? 'selected' : '' }}>
                                                {{ __('Customer') }}</option>
                                            <option value="supplier" {{ old('topic') === 'supplier' ? 'selected' : '' }}>
                                                {{ __('Supplier') }}</option>
                                            <option value="representative"
                                                {{ old('topic') === 'representative' ? 'selected' : '' }}>
                                                {{ __('representative') }}</option>
                                        </select>
                                        @error('topic')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="title" class="form-label">{{ __('Title') }}</label>
                                        <input type="text" name="title" id="title" value="{{ old('title') }}"
                                            class="form-control" placeholder="{{ __('Enter the user title') }}">
                                        @error('title')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="body" class="form-label fw-semibold">{{ __('Body') }}</label>
                                        <textarea name="body" id="body" class="form-control" rows="4"
                                            placeholder="{{ __('Enter English Message') }}">{{ old('body') }}</textarea>
                                        @error('body')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>




                                </div>
                            </div>
                            <div class="card-footer text-end bg-light rounded-bottom">
                                <button type="submit" class="btn btn-primary px-4">{{ __('Save') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
