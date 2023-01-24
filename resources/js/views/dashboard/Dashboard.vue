<template>
    <SMContainer class="dashboard mx-auto">
        <h1>Dashboard</h1>
        <div class="boxes">
            <router-link to="/dashboard/details" class="box">
                <font-awesome-icon icon="fa-solid fa-user-pen" />
                <h2>My Details</h2>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/posts')"
                to="/dashboard/posts"
                class="box">
                <font-awesome-icon icon="fa-regular fa-newspaper" />
                <h2>Posts</h2>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/users')"
                :to="{ name: 'user-list' }"
                class="box">
                <font-awesome-icon icon="fa-solid fa-users" />
                <h2>Users</h2>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/events')"
                to="/dashboard/events"
                class="box">
                <font-awesome-icon icon="fa-regular fa-calendar" />
                <h2>Events</h2>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/courses')"
                to="/dashboard/courses"
                class="box">
                <font-awesome-icon icon="fa-solid fa-graduation-cap" />
                <h2>{{ courseBoxTitle }}</h2>
            </router-link>
            <router-link
                v-if="userStore.permissions.includes('admin/media')"
                to="/dashboard/media"
                class="box">
                <font-awesome-icon icon="fa-solid fa-photo-film" />
                <h2>Media</h2>
            </router-link>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useUserStore } from "../../store/UserStore";

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
.dashboard {
    max-width: 1000px;
}

.boxes {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    margin: -#{map-get($spacer, 3)};

    .box {
        display: flex;
        flex-basis: map-get($spacer, 5) * 4.5;
        flex-direction: column;
        background-color: #eee;
        border: 3px solid #eee;
        border-radius: 12px;
        padding: map-get($spacer, 5) map-get($spacer, 4);
        margin: map-get($spacer, 3);
        font-size: map-get($spacer, 3);
        color: $font-color;
        transition: background-color 0.3s, border 0.3s;
        text-align: center;

        h2 {
            margin-top: map-get($spacer, 2);
            margin-bottom: 0;
        }

        svg {
            font-size: map-get($spacer, 5);
        }

        &:hover {
            border: 3px solid $font-color;
            text-decoration: none;
            background-color: #fff;
            color: $font-color;
        }
    }
}
</style>
