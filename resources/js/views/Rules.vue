<template>
    <SMMastHead title="Rules" />
    <div class="max-w-4xl mx-auto px-4">
        <p class="pt-16">
            We want to make sure everyone has fun and stays safe while using our
            platforms and services. That's why we have some rules to follow.
        </p>
        <p>
            These are some of the things we <strong>DO NOT</strong> allow
            anywhere:
        </p>
        <ul class="list-disc">
            <li>Don't spam or use bad words too much.</li>
            <li>Don't be mean to others or say hateful things.</li>
            <li>Don't share inappropriate, adult or violent stuff.</li>
            <li>
                Don't tell people where you live or give them your real name.
            </li>
            <li>Don't advertise things, but it's okay to talk about them.</li>
            <li>
                Don't send anything that could harm someone's computer or
                property.
            </li>
            <li>Don't try to get out of trouble by doing something sneaky.</li>
            <li>Don't bully or be mean to others.</li>
        </ul>

        <SMHeader text="Discord Server" class="pt-16 pb-2" />
        <ul class="list-disc">
            <li>
                Please follow Discord's
                <a href="https://discord.com/terms">Terms of Service</a>.
            </li>
        </ul>

        <SMHeader text="Minecraft" class="pt-16 pb-2" />
        <ul class="list-disc">
            <li>Don't beg for things from others.</li>
            <li>Use must use a Microsoft account to play on the server.</li>
            <li>
                Don't use hacks to cheat like Jesus, Camera, and Dupes (but
                mini-maps are okay).
            </li>
            <li>
                Don't destroy other people's buildings, except in the Survival
                game mode.
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";
import SMHeader from "../components/SMHeader.vue";
</script>
