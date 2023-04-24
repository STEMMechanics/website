<template>
    <SMMastHead title="Rules" />
    <SMContainer narrow>
        <template #inner>
            <p>
                We want to make sure everyone has fun and stays safe while using
                our platforms and services. That's why we have some rules to
                follow.
            </p>
            <p>
                These are some of the things we <strong>DO NOT</strong> allow
                anywhere:
            </p>
            <ul>
                <li><strong>Don't spam or use bad words too much.</strong></li>
                <li>
                    <strong
                        >Don't be mean to others or say hateful things.</strong
                    >
                </li>
                <li>
                    <strong
                        >Don't share inappropriate, adult or violent
                        stuff.</strong
                    >
                </li>
                <li>
                    <strong
                        >Don't tell people where you live or give them your real
                        name.</strong
                    >
                </li>
                <li>
                    <strong
                        >Don't advertise things, but it's okay to talk about
                        them.</strong
                    >
                </li>
                <li>
                    <strong
                        >Don't send anything that could harm someone's computer
                        or property.</strong
                    >
                </li>
                <li>
                    <strong
                        >Don't try to get out of trouble by doing something
                        sneaky.</strong
                    >
                </li>
                <li><strong>Don't bully or be mean to others.</strong></li>
            </ul>

            <h3>Discord Server</h3>
            <ul>
                <li>
                    Please follow Discord's
                    <a href="https://discord.com/terms">Terms of Service</a>.
                </li>
            </ul>

            <h3>Minecraft</h3>
            <ul>
                <li>Don't beg for things from others.</li>
                <li>Use must use a Microsoft account to play on the server.</li>
                <li>
                    Don't use hacks to cheat like Jesus, Camera, and Dupes (but
                    mini-maps are okay).
                </li>
                <li>
                    Don't destroy other people's buildings, except in the
                    Survival game mode.
                </li>
            </ul>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";
</script>

<style lang="scss">
.page-rules {
    h3 {
        margin-top: 60px;
    }

    h4 {
        margin-top: 30px;
    }
}
</style>
