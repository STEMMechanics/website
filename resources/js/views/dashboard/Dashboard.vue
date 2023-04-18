<template>
    <SMMastHead title="Dashboard" />
    <SMContainer>
        <div class="cards">
            <router-link to="/dashboard/details" class="admin-card details">
                <ion-icon name="location-outline" />
                <h3>My Details</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/posts')"
                :to="{ name: 'dashboard-post-list' }"
                class="admin-card posts">
                <ion-icon name="newspaper-outline" />
                <h3>Posts</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/users')"
                :to="{ name: 'dashboard-user-list' }"
                class="admin-card users">
                <ion-icon name="people-outline" />
                <h3>Users</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/events')"
                :to="{ name: 'dashboard-event-list' }"
                class="admin-card events">
                <ion-icon name="calendar-outline" />
                <h3>Events</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/courses')"
                :to="{ name: 'dashboard-event-list' }"
                class="admin-card courses">
                <ion-icon name="school-outline" />
                <h3>{{ courseBoxTitle }}</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/media')"
                :to="{ name: 'dashboard-media-list' }"
                class="admin-card media">
                <ion-icon name="film-outline" />
                <h3>Media</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/media')"
                :to="{ name: 'dashboard-media-list' }"
                class="admin-card minecraft"
                style="background-image: url('/img/minecraft.png')">
                <img src="/img/minecraft-grass-block.png" />
                <h3>Minecraft</h3>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('logs/discord')"
                :to="{ name: 'dashboard-discord-bot-logs' }"
                class="admin-card discord">
                <ion-icon name="logo-discord" />
                <h3>Discord Bot Logs</h3>
            </router-link>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useUserStore } from "../../store/UserStore";
import SMMastHead from "../../components/SMMastHead.vue";
import SMContainer from "../../components/SMContainer.vue";

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
.page-dashboard {
    .cards {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        justify-content: center;

        .admin-card {
            display: flex;
            flex-direction: column;
            flex-basis: 224px;
            align-items: center;
            color: var(--base-color-text);
            border-radius: 10px;
            background-color: var(--base-color-light);
            text-decoration: none;
            box-shadow: var(--base-shadow);
            padding: 32px;

            ion-icon {
                font-size: 64px;
            }

            img {
                height: 64px;
            }

            &.minecraft {
                color: #eee;
            }
        }
    }
}
</style>
