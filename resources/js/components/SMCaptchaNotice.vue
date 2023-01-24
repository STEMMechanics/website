<template>
    <div class="captcha-notice">
        This site is protected by reCAPTCHA and the Google
        <a href="https://policies.google.com/privacy">Privacy Policy</a> and
        <a href="https://policies.google.com/terms">Terms of Service</a> apply.
    </div>
</template>

<style lang="scss">
.captcha-notice {
    color: $secondary-color;
    font-size: 65%;
    line-height: 1.2rem;
    margin-bottom: map-get($spacer, 4);
}
</style>
