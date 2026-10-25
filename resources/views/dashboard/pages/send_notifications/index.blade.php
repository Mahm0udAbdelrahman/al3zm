@extends('dashboard.layouts.app')
@section('title', __('Send Notification'))

@section('content')
    <div class="pc-container">
        <div class="pc-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">{{ __('Send Notification') }}</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('Admin.home') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item" aria-current="page">{{ __('Send Notification') }}</li>
                    </ul>
                </div>
            </div>

            <!-- Content -->
            <div class="row mb-5">
                <div class="col-12 col-xl-12">
                    <div class="card">

                        <!-- Add User Button -->

                        <div class="card-header flex justify-between items-center">
                            <h5>{{ __('Send Notifications List') }}</h5>

                            @can('send_notifications-create')
                                <a href="{{ route('Admin.send_notifications.create') }}" class="btn btn-primary">
                                    <i class="fas fa-add"></i> {{ __('Add Send Notification') }}
                                </a>
                            @endcan
                        </div>
                        <!-- Table -->
                        <div class="card-body">
                            <div class="table-responsive text-center">
                                <table id="example2" class="table table-striped table-bordered">



                                    <thead>
                                        <tr>
                                            <th>{{ __('ID') }}</th>
                                            <th>{{ __('Topic') }}</th>
                                            <th>{{ __('Title') }}</th>
                                            <th>{{ __('Body') }}</th>
                                            <th>{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @forelse($data as $send_notification)
                                            <tr>
                                                <td>{{ $loop->index + 1 }}</td>
                                                <td>{{ $send_notification->topic }}</td>
                                                <td>{{ $send_notification->title }}</td>
                                                <td>{{ $send_notification->body }}</td>

                                                <td>
                                                    @can('send_notifications-delete')
                                                        <button type="button" class="btn btn-danger w-25 delete-user-btn"
                                                            data-id="{{ $send_notification->id }}">
                                                            <i class="far fa-trash-alt"></i>
                                                        </button>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8">{{ __('Nothing!') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                </table>




                                <!-- Pagination -->
                                <div class="mt-3" style="padding:5px;direction: ltr;">
                                    {!! $data->withQueryString()->links('pagination::bootstrap-5') !!}
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', function() {
                    let id = this.getAttribute('data-id');

                    Swal.fire({
                        title: '{{ __('Are you sure?') }}',
                        text: "{{ __('Do you want to delete this item') }}",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#DC143C',
                        cancelButtonColor: '#696969',
                        cancelButtonText: "{{ __('Cancel') }}",
                        confirmButtonText: '{{ __('Yes, delete it!') }}'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            let form = document.createElement('form');
                            form.action = '{{ url('/admin/send_notifications') }}/' + id;
                            form.method = 'POST';
                            form.style.display = 'none';

                            let csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = '{{ csrf_token() }}';

                            let methodInput = document.createElement('input');
                            methodInput.type = 'hidden';
                            methodInput.name = '_method';
                            methodInput.value = 'DELETE';

                            form.appendChild(csrfInput);
                            form.appendChild(methodInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush
