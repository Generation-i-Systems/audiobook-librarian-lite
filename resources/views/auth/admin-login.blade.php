@extends('layouts.app')

@section('content')
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">Administrator sign in</h1>
                        <p class="text-body-secondary">Use an administrator account to review and manage users.</p>
                        <form method="POST" action="{{ route('admin.login.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
                            </div>
                            <div class="form-check mb-4">
                                <input id="remember" name="remember" value="1" type="checkbox" class="form-check-input">
                                <label for="remember" class="form-check-label">Remember me</label>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Sign in</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
