{{--
    "Continue with Facebook" — Facebook JavaScript SDK.
    Renders only when Facebook login is enabled AND both app ID and app secret
    are configured (managed from Admin → Settings → Social Login; .env is a
    fallback). FB.login() returns a short-lived user access token, which we POST
    to `login.facebook` for server-side verification via the Graph API.
--}}
@php($fbAppId = config('services.facebook.app_id'))
@php($fbEnabled = config('services.facebook.enabled'))
@php($fbVersion = config('services.facebook.graph_version', 'v21.0'))

@if ($fbEnabled && $fbAppId)
    <div class="mt-3">
        <button type="button" id="facebook-signin-btn"
                class="btn-lg flex w-full items-center justify-center gap-2 rounded-xl bg-[#1877F2] font-semibold text-white transition hover:bg-[#0f65d8] disabled:opacity-60"
                disabled>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.017 1.792-4.684 4.533-4.684 1.312 0 2.686.235 2.686.235v2.965h-1.513c-1.491 0-1.956.93-1.956 1.886v2.242h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073Z"/>
            </svg>
            Continue with Facebook
        </button>
    </div>

    <form id="facebook-signin-form" action="{{ route('login.facebook') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="access_token" id="facebook-token-input">
    </form>

    @push('scripts')
        <script>
            window.fbAsyncInit = function () {
                FB.init({
                    appId: {{ Illuminate\Support\Js::from($fbAppId) }},
                    cookie: true,
                    xfbml: false,
                    version: {{ Illuminate\Support\Js::from($fbVersion) }}
                });
                var btn = document.getElementById('facebook-signin-btn');
                if (btn) { btn.disabled = false; }
            };

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('#facebook-signin-btn');
                if (!btn || typeof FB === 'undefined') { return; }
                FB.login(function (response) {
                    if (response && response.authResponse && response.authResponse.accessToken) {
                        var input = document.getElementById('facebook-token-input');
                        var form = document.getElementById('facebook-signin-form');
                        if (input && form) {
                            input.value = response.authResponse.accessToken;
                            form.submit();
                        }
                    }
                }, { scope: 'public_profile,email' });
            });
        </script>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
    @endpush
@endif
