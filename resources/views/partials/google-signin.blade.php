{{--
    "Continue with Google" — Google Identity Services (GIS).
    Renders only when social login is enabled AND a web client ID is configured
    (both managed from Admin → Settings → Social Login; .env is a fallback).
    The GIS button returns an ID token via the JS callback, which we drop into a
    CSRF-protected form and POST to `login.google` for server-side verification.
--}}
@php($googleClientId = config('services.google.web_client_id'))
@php($googleEnabled = config('services.google.web_enabled'))

@if ($googleEnabled && $googleClientId)
    <div class="mt-4 flex flex-col items-center">
        <div id="g_id_onload"
             data-client_id="{{ $googleClientId }}"
             data-callback="handleGoogleCredential"
             data-auto_prompt="false"></div>
        <div class="g_id_signin"
             data-type="standard"
             data-theme="outline"
             data-size="large"
             data-text="continue_with"
             data-shape="rectangular"
             data-logo_alignment="left"
             data-width="320"></div>
    </div>

    <form id="google-signin-form" action="{{ route('login.google') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="credential" id="google-credential-input">
    </form>

    @push('scripts')
        <script>
            window.handleGoogleCredential = function (response) {
                var input = document.getElementById('google-credential-input');
                var form = document.getElementById('google-signin-form');
                if (input && form && response && response.credential) {
                    input.value = response.credential;
                    form.submit();
                }
            };
        </script>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    @endpush
@endif
