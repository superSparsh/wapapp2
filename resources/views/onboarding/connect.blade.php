<x-auth.onboarding-layout
    :step="2"
    title="Connect Your WhatsApp"
    subtitle="You’ll now connect your WhatsApp Business account. It’s quick — just press the button below."
>
    <div class="flex flex-col gap-6" data-onboarding-connect>
        <div class="rounded-xl border border-border bg-elevated p-6">
            <p class="fd-label mb-4">Connect WhatsApp Business API</p>
            <p class="fd-page-note mb-6 !opacity-100">
                Click the button below to authorize WhatsApp Business connection via Meta.
                Facebook requires HTTPS for this step.
            </p>
            <x-ui.button
                type="button"
                id="onboarding-connect-btn"
                class="!w-auto px-8"
                disabled
            >
                Loading…
            </x-ui.button>
        </div>

        <div
            id="onboarding-connect-status"
            class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-4"
        >
            <span class="size-3 shrink-0 rounded-full bg-amber-400" data-status-dot></span>
            <p class="text-sm font-medium text-text-subtle" data-status-text>Status: Waiting to connect</p>
        </div>
    </div>

    <x-slot:footer>
        <div class="flex gap-4">
            <x-ui.link-button
                href="{{ route('onboarding.business') }}"
                variant="outline"
                class="flex-1 rounded-xl border border-border bg-elevated p-3.5 text-base font-extrabold text-primary-2"
            >
                Back
            </x-ui.link-button>
        </div>
    </x-slot:footer>

    @push('scripts')
        <script>
            window.__ONBOARDING_CONNECT__ = {
                appId: @json($appId),
                embedUrl: @json($embedUrl),
                finishUrl: @json($finishUrl),
                csrfToken: @json(csrf_token()),
            };
        </script>
        <script src="https://connect.facebook.net/en_US/sdk.js" async defer crossorigin="anonymous"></script>
        <script>
            (function () {
                const config = window.__ONBOARDING_CONNECT__ || {};
                const btn = document.getElementById('onboarding-connect-btn');
                const statusText = document.querySelector('[data-status-text]');
                const statusDot = document.querySelector('[data-status-dot]');
                const statusBox = document.getElementById('onboarding-connect-status');
                let sdkReady = false;
                let submitting = false;

                function setStatus(text, kind) {
                    if (statusText) statusText.textContent = text;
                    if (statusDot) {
                        statusDot.className = 'size-3 shrink-0 rounded-full ' + (
                            kind === 'ok' ? 'bg-green-500' :
                            kind === 'err' ? 'bg-red-500' : 'bg-amber-400'
                        );
                    }
                    if (statusBox) {
                        statusBox.className = 'flex items-center gap-3 rounded-xl border p-4 ' + (
                            kind === 'ok' ? 'border-green-500 bg-green-50' :
                            kind === 'err' ? 'border-red-300 bg-red-50' :
                            'border-border bg-elevated'
                        );
                    }
                }

                function toast(message, type) {
                    const payload = { message: message, type: type || 'info' };
                    if (typeof window.showAppToast === 'function') {
                        window.showAppToast(payload);
                        return;
                    }
                    if (type === 'success' && typeof window.showSuccessToast === 'function') {
                        window.showSuccessToast(message);
                        return;
                    }
                    if (type === 'error' && typeof window.showErrorToast === 'function') {
                        window.showErrorToast(message);
                        return;
                    }
                    if (type === 'warning' && typeof window.showWarningToast === 'function') {
                        window.showWarningToast(message);
                        return;
                    }
                    const el = document.createElement('div');
                    el.className = 'mb-4 rounded-xl border border-border bg-elevated px-4 py-3 text-sm font-medium text-text-primary';
                    el.textContent = message;
                    const root = document.querySelector('[data-onboarding-connect]');
                    if (root) root.prepend(el);
                    else if (type === 'error') alert(message);
                }

                function initFb() {
                    const appId = config.appId || localStorage.getItem('FB_APP_ID');
                    if (!appId || !window.FB) {
                        return false;
                    }

                    try {
                        window.FB.init({
                            appId: appId,
                            cookie: true,
                            xfbml: false,
                            version: 'v17.0',
                        });
                        sdkReady = true;
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Connect with WhatsApp';
                        }
                        setStatus('Status: Ready to connect', 'pending');
                        return true;
                    } catch (e) {
                        console.error(e);
                        return false;
                    }
                }

                const wait = setInterval(function () {
                    if (initFb()) clearInterval(wait);
                }, 200);
                setTimeout(function () {
                    clearInterval(wait);
                    if (!sdkReady && btn) {
                        btn.textContent = 'Connect unavailable';
                        setStatus('Status: Facebook SDK or App ID missing', 'err');
                    }
                }, 8000);

                window.addEventListener('message', async function (event) {
                    if (event.origin !== 'https://www.facebook.com') return;
                    try {
                        const data = JSON.parse(event.data);
                        if (data.type !== 'WA_EMBEDDED_SIGNUP' || data.event !== 'FINISH') return;

                        const wabaId = data.data && data.data.waba_id;
                        const phoneNumberId = data.data && data.data.phone_number_id;
                        if (!wabaId || submitting) return;

                        submitting = true;
                        if (btn) {
                            btn.disabled = true;
                            btn.textContent = 'Connecting…';
                        }
                        setStatus('Status: Saving connection…', 'pending');

                        const response = await fetch(config.embedUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                waba_id: wabaId,
                                phone_number_id: phoneNumberId,
                            }),
                        });

                        const payload = await response.json().catch(function () { return {}; });

                        if (response.ok && (payload.status === '200' || payload.msg === 'success')) {
                            setStatus('Status: Connected', 'ok');
                            toast('Your WhatsApp account is now connected!', 'success');
                            window.location.href = payload.redirect || config.finishUrl;
                            return;
                        }

                        submitting = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Connect with WhatsApp';
                        }
                        setStatus('Status: Connection failed', 'err');
                        toast(payload.msg || payload.error || 'Something went wrong while saving your details.', 'error');
                    } catch (e) {
                        // ignore non-JSON messages
                    }
                });

                if (btn) {
                    btn.addEventListener('click', function () {
                        if (!sdkReady || !window.FB) {
                            toast('Please wait… loading Facebook tools.', 'error');
                            return;
                        }
                        if (!window.location.protocol.startsWith('https')) {
                            toast('Facebook requires HTTPS. Please switch to https:// for this step.', 'warning');
                        }

                        window.FB.login(
                            function (response) {
                                if (!response.authResponse) {
                                    toast('Login cancelled.', 'warning');
                                }
                            },
                            {
                                scope: 'business_management,whatsapp_business_management',
                                extras: {
                                    feature: 'whatsapp_embedded_signup',
                                    version: 2,
                                    sessionInfoVersion: 1,
                                },
                            }
                        );
                    });
                }
            })();
        </script>
    @endpush
</x-auth.onboarding-layout>
