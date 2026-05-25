@include('filament-forms::components.hidden')

@if(config('recaptcha.enabled') && config('recaptcha.site_key'))
@once('recaptcha-preload')
<link rel="preconnect" href="https://www.google.com">
<link rel="preconnect" href="https://www.gstatic.com" crossorigin>
@endonce

@once('recaptcha-script')
<script src="https://www.google.com/recaptcha/enterprise.js?render={{ config('recaptcha.site_key') }}&hl={{ app()->getLocale() }}" async defer></script>
<script>window._recaptchaSiteKey = '{{ config('recaptcha.site_key') }}';</script>
@endonce

<div
    x-data="{
        _loading: false,
        error: null,
        init() {
            let tokenReady = false;
            this.$wire.$intercept('{{ $getSubmitMethod() }}', ({ action }) => {
                if (tokenReady) {
                    tokenReady = false;
                    return;
                }

                if (this._loading) {
                    action.cancel();
                    return;
                }

                action.cancel();
                this._loading = true;
                this.error = null;

                new Promise((resolve, reject) => {
                    grecaptcha.enterprise.ready(() => {
                        grecaptcha.enterprise.execute(
                            window._recaptchaSiteKey,
                            { action: '{{ $getRecaptchaAction() }}' }
                        ).then(resolve).catch(reject);
                    });
                })
                .then(captchaToken => {
                    this.$wire.set('{{ $getStatePath() }}', captchaToken, false);
                    this._loading = false;
                    tokenReady = true;
                    this.$wire['{{ $getSubmitMethod() }}']();
                })
                .catch(err => {
                    this._loading = false;
                    this.error = '{{ addslashes(__('filament-panels::auth/pages/login.messages.failed')) }}';
                    console.error('reCAPTCHA failed', err);
                });
            });
        }
    }"
>
    <template x-if="error">
        <p x-text="error" class="mt-2 text-sm text-danger-600 dark:text-danger-400"></p>
    </template>
</div>
@endif
