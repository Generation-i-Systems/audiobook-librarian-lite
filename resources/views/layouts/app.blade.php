<?php
?>
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Audiobook Librarian') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
        integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Alpine.js for modern reactive components -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- jQuery (maintained for legacy compatibility) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}" />

    <!-- Bootstrap 5 CSS/JS restored for dropdowns, modals, and all Bootstrap-dependent features -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        crossorigin="anonymous">

    <!-- jQuery UI override: ensure autocomplete dropdown is above Bootstrap elements -->
    <link rel="stylesheet" href="/css/jquery-ui-overrides.css">

    <!-- Bootstrap dropdown fix: ensure dropdown-menu is above navbar and other elements -->
    <link rel="stylesheet" href="/css/bootstrap-dropdown-zfix.css">

    <!-- Scripts -->
    @if (!app()->environment('testing'))
        @vite([
            'resources/sass/app.scss',
            'resources/js/app.js',
            'resources/js/global-ajax-auth.js'
        ])
    @endif

    <link rel="stylesheet" href="/css/pagination-fix.css">

    <!-- jQuery and jQuery UI (for autocomplete) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- Popper.js must be loaded globally before Bootstrap 5 JS for dropdowns to work -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        crossorigin="anonymous"></script>
    <!-- Bootstrap 5 JS (must be after jQuery if both are used, but before custom scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>

    @stack('styles')
</head>

<body>
    <div id="app">
        @if(config('database.default') === 'mysql_devel')
            <div class="bg-warning text-dark text-center py-1 fw-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                DEVELOPMENT ENVIRONMENT - Using Database: {{ config('database.default') }}
            </div>
        @endif
        <nav class="navbar navbar-expand-md navbar-dark bg-primary shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    <img src="{{ asset('images/ablibrarian_logo_horizontal_white.svg') }}" alt="Audiobook Librarian"
                        height="30">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            @if(request()->is('admin/*') && Auth::user()->is_admin)
                                <li class="nav-item">
                                    <a class="nav-link" style="color:white" href="{{ route('admin.users.index') }}">Users</a>
                                </li>
                            @else
                                <!-- Public Links (Show on public pages, only if routed) -->
                            @endif
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (\Illuminate\Support\Facades\Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" style="color:white" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (\Illuminate\Support\Facades\Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" style="color:white"
                                        href="{{ route('register') }}">{{ __('Request an account') }}</a>
                                </li>
                            @endif
                            <li class="nav-item">
                                <a class="nav-link" style="color:white" href="{{ route('admin.login') }}">{{ __('Admin sign in') }}</a>
                            </li>
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre
                                    style="color:white">
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    @if (\Illuminate\Support\Facades\Route::has('profile.index'))
                                        <a class="dropdown-item" href="{{ route('profile.index') }}">
                                            {{ __('Profile') }}
                                        </a>
                                    @endif

                                    @if (Auth::user()->is_admin)
                                        @if(!request()->is('admin/*'))
                                            <a class="dropdown-item" href="{{ route('admin.users.index') }}">
                                                Manage users
                                            </a>
                                        @endif
                                    @endif

                                    @if (\Illuminate\Support\Facades\Route::has('admin.logout'))
                                        <form action="{{ route('admin.logout') }}" method="POST">
                                            @csrf
                                            <button class="dropdown-item" type="submit">{{ __('Logout') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            <div class="container">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>
            @yield('content')
        </main>
    </div>

    @stack('scripts')

    <!-- Force re-initialize Bootstrap dropdowns after DOM ready to fix event delegation conflicts -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                bootstrap.Dropdown.getOrCreateInstance(el);
            });
        });
    </script>
    <!-- Diagnostic test button to force open dropdown -->

    <!-- Patch for Bootstrap 5 dropdown vs. jQuery UI autocomplete conflicts -->
    <script>
        // Ensure autocomplete popup is always above dropdowns
        $(document).on('autocompleteopen', function () {
            setTimeout(function () {
                $('.ui-autocomplete').css('z-index', 2000);
            }, 10);
        });
        // Prevent clicking inside autocomplete from closing dropdown
        $(document).on('mousedown', '.ui-autocomplete', function (e) {
            e.stopPropagation();
        });
    </script>

    <script>
        function refreshCsrfAndLogout() {
            // Get fresh CSRF token
            fetch('/csrf-token', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(response => response.json())
            .then(data => {
                // Update the CSRF token in the logout form
                const tokenInput = document.querySelector('#logout-form input[name="_token"]');
                if (tokenInput && data.csrf_token) {
                    tokenInput.value = data.csrf_token;
                }
                // Submit the form
                document.getElementById('logout-form').submit();
            })
            .catch(error => {
                console.error('CSRF refresh failed:', error);
                // Fall back to original logout attempt
                document.getElementById('logout-form').submit();
            });
        }
    </script>

</body>

</html>
