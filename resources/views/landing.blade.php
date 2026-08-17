@extends('layouts.app')

@section('content')
    <main class="container py-5">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <section class="row align-items-center g-5 py-4">
            <div class="col-lg-8">
                <p class="text-uppercase text-primary fw-semibold mb-2">AbLibrarian Lite</p>
                <h1 class="display-5 fw-bold">A lightweight companion for your audiobook library.</h1>
                <p class="lead text-body-secondary mt-3">
                    AbLibrarian Lite connects the AbLibrarian mobile app to a library server, so approved members can
                    browse and listen with the access their administrator assigns.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="{{ route('register') }}">Request an account</a>
                    <a class="btn btn-outline-primary btn-lg" href="https://www.ablibrarian.com/" target="_blank" rel="noopener noreferrer">Visit ablibrarian.com</a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h4">Already registered?</h2>
                        <p class="mb-3">Use the AbLibrarian app to connect to this server and sign in once your account is approved.</p>
                        <a class="btn btn-outline-secondary w-100" href="{{ route('login') }}">Connect the mobile app</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4 mt-2" aria-label="Project information">
            <div class="col-md-6">
                <article class="border rounded-3 h-100 p-4">
                    <h2 class="h4">Part of AbLibrarian</h2>
                    <p>
                        Learn about the AbLibrarian ecosystem, the mobile app, and how a library administrator can host
                        a private collection.
                    </p>
                    <a href="https://www.ablibrarian.com/" target="_blank" rel="noopener noreferrer">Learn more about AbLibrarian</a>
                </article>
            </div>
            <div class="col-md-6">
                <article class="border rounded-3 h-100 p-4">
                    <h2 class="h4">Open source</h2>
                    <p>Review the source, report issues, or contribute to this Lite server on GitHub.</p>
                    <a href="https://github.com/Generation-i-Systems/audiobook-librarian-lite" target="_blank" rel="noopener noreferrer">View this project on GitHub</a>
                </article>
            </div>
        </section>

        <section class="border-top mt-5 pt-4" aria-labelledby="administrators-heading">
            <h2 id="administrators-heading" class="h4">Administrators</h2>
            <p class="mb-3">Sign in to review new account requests, choose each member’s access level, and manage users.</p>
            <a class="btn btn-dark" href="{{ route('admin.login') }}">Administrator sign in</a>
        </section>
    </main>
@endsection
