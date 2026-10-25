<header class="pc-header">
    <div class="header-wrapper flex max-sm:px-[15px] px-[25px] grow"><!-- [Mobile Media Block] start -->
        <div class="me-auto pc-mob-drp">
            <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
                <!-- ======= Menu collapse Icon ===== -->
                <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
                    <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="sidebar-hide">
                        <i data-feather="menu"></i>
                    </a>
                </li>
                <li class="pc-h-item pc-sidebar-popup lg:hidden">
                    <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="mobile-collapse">
                        <i data-feather="menu"></i>
                    </a>
                </li>
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle me-0" data-pc-toggle="dropdown" href="#" role="button"
                        aria-haspopup="false" aria-expanded="false">
                        <i data-feather="search"></i>
                    </a>
                    <div class="dropdown-menu pc-h-dropdown drp-search">
                        <form class="px-2 py-1">
                            <input type="search" class="form-control !border-0 !shadow-none"
                                placeholder="Search here. . ." />
                        </form>
                    </div>
                </li>
            </ul>
        </div>
        <!-- [Mobile Media Block end] -->
        <div class="ms-auto">
            <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle me-0" data-pc-toggle="dropdown" href="#" role="button"
                        aria-haspopup="false" aria-expanded="false">
                        <i data-feather="sun"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
                            <i data-feather="moon"></i>
                            <span>Dark</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change('light')">
                            <i data-feather="sun"></i>
                            <span>Light</span>
                        </a>
                        <a href="#!" class="dropdown-item" onclick="layout_change_default()">
                            <i data-feather="settings"></i>
                            <span>Default</span>
                        </a>
                    </div>
                </li>
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle me-0" data-pc-toggle="dropdown" href="#" role="button"
                        aria-haspopup="false" aria-expanded="false">
                        <i data-feather="settings"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-user"></i>
                            <span>My Account</span>
                        </a>
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-settings"></i>
                            <span>Settings</span>
                        </a>
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-headset"></i>
                            <span>Support</span>
                        </a>
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-lock"></i>
                            <span>Lock Screen</span>
                        </a>
                        <a href="#!" class="dropdown-item">
                            <i class="ti ti-power"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </li>
                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle me-0 position-relative" data-pc-toggle="dropdown"
                        href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <i data-feather="bell"></i>
                        @if (Auth::user()->unreadNotifications()->count())
                            <span class="badge bg-danger text-black rounded-full z-10 absolute right-0 top-0"
                                style="font-size: 10px; min-width: 18px; height: 18px; line-height: 18px; text-align: center;">
                                {{ Auth::user()->unreadNotifications()->count() }}
                            </span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown p-2"
                        style="width: 380px; max-height: 450px; overflow-y: auto; border-radius: 8px;">
                        <div class="dropdown-header bg-primary text-black text-center py-2">
                            <strong>{{ __('Notifications') }}</strong>
                        </div>
                        <div class="dropdown-body header-notification-scroll relative py-2 px-3">
                            @forelse(auth()->user()->notifications()->orderBy('created_at', 'desc')->take(5)->get() as $notification)
                                <a href="{{ $notification->data['url'] ?? '#' }}?notification_id={{ $notification->id }}"
                                    class="d-flex gap-3 align-items-start mark-as-read mb-3 text-decoration-none"
                                    data-id="{{ $notification->id }}"
                                    style="color: {{ $notification->read_at ? '#6c757d' : '#212529' }};">
                                    <i class="fas fa-bell text-primary fs-5 flex-shrink-0"></i>
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between">
                                            <p class="mb-1 {{ !$notification->read_at ? 'fw-bold' : '' }}">
                                                {{ $notification->data['message'] ?? 'No message' }}
                                            </p>
                                            <small class="text-muted" style="white-space: nowrap;">
                                                {{ $notification->created_at->diffForHumans() }}
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="text-center text-muted py-3">
                                    {{ __('No new notifications') }}
                                </div>
                            @endforelse
                        </div>
                        <div class="text-center p-2 border-top">
                            <a href="{{ route('Admin.notifications.markAllRead') }}"
                                class="btn btn-sm btn-outline-primary me-2">{{ __('Read All') }}</a>
                            {{--  <a href="{{ route('Admin.notifications') }}"
                                class="btn btn-sm btn-primary">{{ __('Index All') }}</a>  --}}
                        </div>
                    </div>
                </li>

                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle me-0 position-relative d-flex align-items-center gap-2"
                        href="javascript:;" role="button" data-pc-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false" style="font-size: 1.2rem;">
                        <i class="fas fa-globe" style="font-size: 1rem;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pc-h-dropdown p-2"
                        style="width: 280px; max-height: 450px; overflow-y: auto; border-radius: 8px;">
                        <div class="dropdown-header bg-primary text-black text-center py-2">
                            <strong>{{ __('Language') }}</strong>
                        </div>
                        <div class="dropdown-body header-notification-scroll relative py-2 px-3">
                            @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                                <a href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}"
                                    class="d-flex align-items-center gap-3 py-2 dropdown-item text-decoration-none
                   {{ App::getLocale() == $localeCode ? 'active fw-bold text-primary' : 'text-dark' }}">
                                    @if (App::getLocale() == $localeCode)
                                        <i class="fas fa-check text-success flex-shrink-0"></i>
                                    @else
                                        <span style="width: 16px;"></span>
                                    @endif
                                    <span>{{ $properties['native'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </li>




                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-pc-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" data-pc-auto-close="outside" aria-expanded="false">
                        <i data-feather="user"></i>
                    </a>
                    <div
                        class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown p-2 overflow-hidden">
                        <div class="dropdown-header flex items-center justify-between py-4 px-5 bg-primary-500">
                            <div class="flex mb-1 items-center">
                                <div class="shrink-0">
                                    <img src="{{ asset('dashboard/assets/images/user/avatar-2.jpg') }}"
                                        alt="user-image" class="w-10 rounded-full" />
                                </div>
                                <div class="grow ms-3">
                                    <h6 class="mb-1 text-white"> {{ auth()->user()->name ?? 'User' }}</h6>
                                    <span class="text-white"> {{ auth()->user()->email ?? 'user@gmail.com' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-body py-4 px-5">
                            <div class="profile-notification-scroll position-relative"
                                style="max-height: calc(100vh - 225px)">
                                <a href="#" class="dropdown-item">
                                    <span>
                                        <svg class="pc-icon text-muted me-2 inline-block">
                                            <use xlink:href="#custom-setting-outline"></use>
                                        </svg>
                                        <span>Settings</span>
                                    </span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <span>
                                        <svg class="pc-icon text-muted me-2 inline-block">
                                            <use xlink:href="#custom-share-bold"></use>
                                        </svg>
                                        <span>Share</span>
                                    </span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <span>
                                        <svg class="pc-icon text-muted me-2 inline-block">
                                            <use xlink:href="#custom-lock-outline"></use>
                                        </svg>
                                        <span>Change Password</span>
                                    </span>
                                </a>
                                <div class="grid my-3">
                                    <form method="POST" action="{{ route('Admin.logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-primary flex items-center justify-center">
                                            <svg class="pc-icon me-2 w-[22px] h-[22px]">
                                                <use xlink:href="#custom-logout-1-outline"></use>
                                            </svg>
                                            Logout
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</header>
