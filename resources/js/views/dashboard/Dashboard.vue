<template>
    <SMMastHead title="Dashboard" />
    <div class="boxes">
        <router-link to="/dashboard/details" class="box">
            <ion-icon name="location-outline" />
            <h2>My Details</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/posts')"
            :to="{ name: 'dashboard-post-list' }"
            class="box">
            <ion-icon name="newspaper-outline" />
            <h2>Posts</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/users')"
            :to="{ name: 'dashboard-user-list' }"
            class="box">
            <ion-icon name="people-outline" />
            <h2>Users</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/events')"
            :to="{ name: 'dashboard-event-list' }"
            class="box">
            <ion-icon name="calendar-outline" />
            <h2>Events</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/courses')"
            :to="{ name: 'dashboard-event-list' }"
            class="box">
            <ion-icon name="school-outline" />
            <h2>{{ courseBoxTitle }}</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/media')"
            :to="{ name: 'dashboard-media-list' }"
            class="box">
            <ion-icon name="film-outline" />
            <h2>Media</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/media')"
            :to="{ name: 'dashboard-media-list' }"
            class="box"
            style="background-image: url('/img/minecraft.png')">
            <img src="/img/minecraft-grass-block.png" />
            <h2>Minecraft</h2>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('logs/discord')"
            :to="{ name: 'dashboard-discord-bot-logs' }"
            class="box">
            <ion-icon name="logo-discord" />
            <h2>Discord Bot Logs</h2>
        </router-link>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useUserStore } from "../../store/UserStore";
import SMMastHead from "../../components/SMMastHead.vue";

const userStore = useUserStore();

const courseBoxTitle = computed(() => {
    if (userStore.permissions.includes("admin/courses")) {
        return "Courses";
    } else {
        return "My Courses";
    }
});
</script>

<style lang="scss">
.boxes {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    margin: -#{map-get($spacer, 3)};

    .box {
        display: flex;
        flex-basis: map-get($spacer, 5) * 4.5;
        flex-direction: column;
        border-radius: 12px;
        border: 2px solid $primary-color-dark;
        background-color: #f8f8f8;
        background-repeat: no-repeat;
        background-size: cover;
        background-position: center;
        padding: map-get($spacer, 5) map-get($spacer, 4);
        margin: map-get($spacer, 3);
        font-size: map-get($spacer, 3);
        color: $primary-color-dark !important;
        margin-bottom: map-get($spacer, 5);
        transition: all 0.2s ease-in-out;
        align-items: center;
        text-align: center;

        h2 {
            margin-top: map-get($spacer, 2);
            margin-bottom: 0;
        }

        ion-icon {
            font-size: map-get($spacer, 5);
        }

        img {
            height: map-get($spacer, 5);
        }

        &:hover {
            text-decoration: none;
            background-color: $primary-color-lighter;
            border-color: $primary-color-darker;
            color: $primary-color-darker !important;
            box-shadow: 0 0 14px rgba(0, 0, 0, 0.25);
            transform: scale(1.01);
        }
    }
}
</style>
