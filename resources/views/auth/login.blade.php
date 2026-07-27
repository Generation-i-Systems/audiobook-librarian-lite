@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Sign in') }}</div>
                    <div class="card-body">
                        <p class="mb-0">
                            AbLibrarian Lite is used through the mobile app — there's no web sign-in here. Scan the
                            QR code below from the app to connect it to this server, then sign in from the app.
                        </p>
                    </div>
                </div>

                <x-app-connect-qr
                    :connect-url="$appConnectUrl"
                    :api-url="$appConnectApiUrl"
                />
            </div>
        </div>
    </div>
@endsection
