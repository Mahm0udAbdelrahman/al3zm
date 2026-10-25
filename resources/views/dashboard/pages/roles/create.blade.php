@extends('dashboard.layouts.app')
@section('title', __('Add Role'))

@section('content')
    <style>
        .permission-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 20px;
        }

        .permission-sidebar {
            background: #0f172a;
            border-radius: 16px;
            padding: 15px;
            height: fit-content;
        }

        .permission-sidebar button {
            width: 100%;
            background: transparent;
            border: 0;
            color: #cbd5f5;
            padding: 12px 14px;
            border-radius: 12px;
            text-align: left;
            margin-bottom: 6px;
            transition: .2s;
            font-weight: 600;
        }

        .permission-sidebar button.active,
        .permission-sidebar button:hover {
            background: #1e293b;
            color: #fff;
        }

        .permission-content {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgb(0 0 0 / 0.1);
        }

        .permission-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            margin-bottom: 10px;
            transition: .2s;
        }

        .permission-row:hover {
            box-shadow: 0 8px 18px rgba(0, 0, 0, .08);
            transform: translateY(-2px);
        }

        .permission-row span {
            font-weight: 500;
            color: #111827;
        }

        .form-switch .form-check-input {
            width: 42px;
            height: 22px;
            cursor: pointer;
        }


        .permission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .permission-header h5 {
            margin: 0;
            font-weight: 700;
            color: #111827;
        }

        .permission-header .form-switch label {
            font-weight: 600;
            user-select: none;
            cursor: pointer;
        }
    </style>

    <div class="pc-container">
        <div class="pc-content">

            <div class="page-header mb-4">
                <h5>{{ __('Add Role') }}</h5>
            </div>

            <form method="POST" action="{{ route('Admin.roles.store') }}">
                @csrf


                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <label class="fw-bold mb-2">{{ __('Role Name') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}">
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>


                <div class="card shadow-sm">
                    <div class="card-body permission-layout">


                        <div class="permission-sidebar">
                            @foreach ($groupedPermissions as $group => $perms)
                                <button type="button" class="{{ $loop->first ? 'active' : '' }}"
                                    data-target="group-{{ $group }}">
                                    <i class="fas fa-layer-group me-2"></i>
                                    {{ strtoupper($group) }}
                                </button>
                            @endforeach
                        </div>


                        <div class="permission-content">
                            @foreach ($groupedPermissions as $group => $perms)
                                <div class="permission-group {{ $loop->first ? '' : 'd-none' }}"
                                    id="group-{{ $group }}">


                                    <div class="permission-header">
                                        <h5>{{ ucfirst($group) }} {{ __('Permissions') }}</h5>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input select-group" type="checkbox"
                                                id="select-group-{{ $group }}" data-group="{{ $group }}">
                                            <label class="form-check-label"
                                                for="select-group-{{ $group }}">{{ __('Select All') }}</label>
                                        </div>
                                    </div>


                                    @foreach ($perms as $permission)
                                        <div class="permission-row">
                                            <span>{{ ucfirst(str_replace('-', ' ', $permission->name)) }}</span>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input permission-checkbox" type="checkbox"
                                                    name="permission_name[]" value="{{ $permission->name }}"
                                                    id="perm-{{ $permission->id }}" data-group="{{ $group }}">
                                                <label class="form-check-label" for="perm-{{ $permission->id }}"></label>
                                            </div>
                                        </div>
                                    @endforeach

                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>

                <button class="btn btn-primary w-100 mt-4" type="submit">
                    <i class="fas fa-save me-2"></i> {{ __('Save') }}
                </button>
            </form>

        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const sidebarButtons = document.querySelectorAll('.permission-sidebar button');
            const groups = document.querySelectorAll('.permission-group');

            sidebarButtons.forEach(button => {
                button.addEventListener('click', () => {

                    sidebarButtons.forEach(b => b.classList.remove('active'));

                    groups.forEach(g => g.classList.add('d-none'));

                    button.classList.add('active');

                    const targetId = button.getAttribute('data-target');
                    document.getElementById(targetId).classList.remove('d-none');
                });
            });


            document.querySelectorAll('.select-group').forEach(selectGroupCheckbox => {
                selectGroupCheckbox.addEventListener('change', function() {
                    const group = this.dataset.group;
                    const checked = this.checked;
                    document.querySelectorAll(`.permission-checkbox[data-group="${group}"]`)
                        .forEach(cb => cb.checked = checked);
                });
            });


            document.querySelectorAll('.permission-group').forEach(groupDiv => {
                const group = groupDiv.id.replace('group-', '');
                const groupCheckbox = document.querySelector(`.select-group[data-group="${group}"]`);
                const permissionCheckboxes = groupDiv.querySelectorAll('.permission-checkbox');

                permissionCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        const allChecked = [...permissionCheckboxes].every(c => c.checked);
                        groupCheckbox.checked = allChecked;
                    });
                });
            });

        });
    </script>
@endpush
