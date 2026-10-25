@extends('dashboard.layouts.app')
@section('title', __('Roles'))
@section('content')
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">

        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="page-header-title">
                    <h5 class="mb-0 font-medium">{{ __('Roles') }}</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('Admin.home') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item" aria-current="page">{{ __('Roles') }}</li>
                </ul>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <div class="grid grid-cols-12 gap-x-6">
            <div class="col-span-12">
                <div class="card">

                    <div class="card-header flex justify-between items-center">
                        <h5>{{ __('Roles List') }}</h5>

                        @can('roles-create')
                        <a href="{{ route('Admin.roles.create') }}" class="btn btn-primary">
                            <i class="fas fa-add"></i> {{ __('Add Role') }}
                        </a>
                        @endcan
                    </div>

                    <div class="card-body">
                        <div class="table-responsive text-center">
                            <table id="example2" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Permissions Count') }}</th>
                                        <th>{{ __('Users Count') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($roles as $role)
                                    <tr>
                                        <td>{{ $role->id }}</td>
                                        <td>{{ $role->name }}</td>
                                        <td>{{ count($role->permissions) }}</td>
                                        <td>{{ count($role->users) }}</td>
                                        <td>
                                            <div class="flex justify-center gap-2">

                                                @if($role->name !== 'admin')
                                                @can('roles-delete')
                                                <button type="button" class="btn btn-danger delete-role-btn"
                                                    data-id="{{ $role->id }}">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                                @endcan
                                                @endif
                                                @if($role->name !== 'admin')
                                                @can('roles-update')
                                                <a href="{{ route('Admin.roles.edit', $role) }}" class="btn btn-info">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5">{{ __('No data available!') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <div class="mt-3" style="direction: ltr;">
                                {!! $roles->withQueryString()->links('pagination::bootstrap-5') !!}
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
<!-- [ Main Content ] end -->
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.delete-role-btn').forEach(button => {
        button.addEventListener('click', function() {
            let id = this.getAttribute('data-id');

            Swal.fire({
                title: '{{ __("Are you sure?") }}',
                text: "{{ __('Do you want to delete this item') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC143C',
                cancelButtonColor: '#696969',
                cancelButtonText: "{{ __('Cancel') }}",
                confirmButtonText: '{{ __("Yes, delete it!") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.action = '{{ url("/admin/roles") }}/' + id;
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
