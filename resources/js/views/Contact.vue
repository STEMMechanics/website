<template>
    <SMMastHead title="Contact us" />
    <div class="max-w-4xl mx-auto px-4">
        <SMHeader text="Questions & Support" class="pt-16 pb-2" />
        <p>
            If you have a question or would like help with a project, you can
            send it our way using the form on this page or be emailing
            <a href="mailto:hello@stemmechanics.com.au"
                >hello@stemmechanics.com.au</a
            >.
        </p>
        <p>
            You can find us on various social media platforms, and if you join
            our
            <a href="https://discord.gg/yNzk4x7mpD">Discord</a>
            server, you'll have the opportunity to connect with our team,
            participants, and other individuals who share similar interests.
        </p>
        <SMSocialIcons />
        <SMHeader text="Wanting a workshop?" class="pt-16 pb-2" />
        <p>
            We provide both public and private workshops as well as run events
            on behalf of your organisation. If you would like to discuss a
            potential opportunity, send us an email at
            <a href="mailto:hello@stemmechanics.com.au"
                >hello@stemmechanics.com.au</a
            >.
        </p>
        <SMHeader text="Where are you located?" class="pt-16 pb-2" />
        <p>
            We do not have a physical address as our workshops are delivered
            across Queensland. Visit the
            <router-link :to="{ name: 'workshops' }">workshops</router-link>
            page for each specific location.
        </p>
        <p>Official mail can be sent to the following postal address:</p>
        <div class="mt-8 text-center">
            <p class="mt-4">
                STEMMechanics<br />PO Box 36<br />Edmonton, QLD, 4869<br />Australia
            </p>
            <p class=""><strong>ABN: </strong>15 772 281 735</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";
import SMSocialIcons from "../components/SMSocialIcons.vue";
import SMHeader from "../components/SMHeader.vue";
</script>
