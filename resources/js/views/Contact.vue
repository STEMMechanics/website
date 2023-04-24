<template>
    <SMMastHead title="Contact us" />
    <SMContainer>
        <div class="container-text">
            <h3>Questions & Support</h3>
            <p>
                If you have a question or would like help with a project, you
                can send it our way using the form on this page or be emailing
                <a href="mailto:hello@stemmechanics.com.au"
                    >hello@stemmechanics.com.au</a
                >.
            </p>
            <p>
                You can find us on various social media platforms, and if you
                join our
                <a href="https://discord.gg/yNzk4x7mpD">Discord</a>
                server, you'll have the opportunity to connect with our team,
                participants, and other individuals who share similar interests.
            </p>
            <SMSocialIcons />
            <h3>Wanting a workshop?</h3>
            <p>
                We provide both public and private workshops as well as run
                events on behalf of your organisation. If you would like to
                discuss a potential opportunity, send us an email at
                <a href="mailto:hello@stemmechanics.com.au"
                    >hello@stemmechanics.com.au</a
                >.
            </p>
            <h3>Where are you located?</h3>
            <p>
                We do not have a physical address as our workshops are delivered
                across Queensland. Visit the
                <router-link :to="{ name: 'workshops' }">workshops</router-link>
                page for each specific location.
            </p>
            <p>Official mail can be sent to the following postal address:</p>
            <div class="address text-center">
                <p class="font-size-90">
                    STEMMechanics<br />PO Box 36<br />Edmonton, QLD, 4869<br />Australia
                </p>
                <p class="font-size-90"><strong>ABN: </strong>15 772 281 735</p>
            </div>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";
import SMSocialIcons from "../components/SMSocialIcons.vue";
</script>

<style lang="scss">
.container-text {
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.4em;

    h3 {
        margin-top: 60px;
    }
}

.address {
    margin-top: 60px;
}
</style>
