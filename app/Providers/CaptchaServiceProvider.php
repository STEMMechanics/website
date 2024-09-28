<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class CaptchaServiceProvider extends ServiceProvider
{
    private string $captchaKey = '6Lc6BIAUAAAAAABZzv6J9ZQ7J9Zzv6J9ZQ7J9Zzv';
    private int $timeThreshold = 750;

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::directive('captcha', function () {
            return <<<EOT
<input type="text" name="captcha" autocomplete="off" style="position:absolute;left:-9999px;top:-9999px">
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const errors = {!! json_encode(\$errors->getMessages()) !!};
        if(errors && errors.captcha && errors.captcha.length) {
            SM.alert('', errors.captcha[0], 'danger');
        }
    });
</script>
EOT;
        });

        Blade::directive('captchaScripts', function () {
            return <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.setTimeout(function() {
        const captchaList = document.querySelectorAll('input[name="captcha"]');
        captchaList.forEach(function(captcha) {
            if(captcha.value === '') {
                captcha.value = '$this->captchaKey';
            }
        });
    }, $this->timeThreshold);
});
</script>
EOT;
        });

        Validator::extend('required_captcha', function ($attribute, $value, $parameters, $validator) {
            return $value === $this->captchaKey;
        }, 'The form captcha failed to validate. Please try again.');
    }
}
