<template>
    <SMMastHead title="Dashboard" />
    <div
        class="max-w-7xl mx-auto px-4 grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8 mt-12">
        <router-link
            to="/dashboard/details"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M222-255q63-44 125-67.5T480-346q71 0 133.5 23.5T739-255q44-54 62.5-109T820-480q0-145-97.5-242.5T480-820q-145 0-242.5 97.5T140-480q0 61 19 116t63 109Zm257.814-195Q422-450 382.5-489.686q-39.5-39.686-39.5-97.5t39.686-97.314q39.686-39.5 97.5-39.5t97.314 39.686q39.5 39.686 39.5 97.5T577.314-489.5q-39.686 39.5-97.5 39.5Zm.654 370Q398-80 325-111.5q-73-31.5-127.5-86t-86-127.266Q80-397.532 80-480.266T111.5-635.5q31.5-72.5 86-127t127.266-86q72.766-31.5 155.5-31.5T635.5-848.5q72.5 31.5 127 86t86 127.032q31.5 72.532 31.5 155T848.5-325q-31.5 73-86 127.5t-127.032 86q-72.532 31.5-155 31.5ZM480-140q55 0 107.5-16T691-212q-51-36-104-55t-107-19q-54 0-107 19t-104 55q51 40 103.5 56T480-140Zm0-370q34 0 55.5-21.5T557-587q0-34-21.5-55.5T480-664q-34 0-55.5 21.5T403-587q0 34 21.5 55.5T480-510Zm0-77Zm0 374Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">My Details</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/articles')"
            :to="{ name: 'dashboard-article-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M277-279h275v-60H277v60Zm0-171h406v-60H277v60Zm0-171h406v-60H277v60Zm-97 501q-24 0-42-18t-18-42v-600q0-24 18-42t42-18h600q24 0 42 18t18 42v600q0 24-18 42t-42 18H180Zm0-60h600v-600H180v600Zm0-600v600-600Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Articles</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/users')"
            :to="{ name: 'dashboard-user-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M400-485q-66 0-108-42t-42-108q0-66 42-108t108-42q66 0 108 42t42 108q0 66-42 108t-108 42ZM80-164v-94q0-35 17.5-63t50.5-43q72-32 133.5-46T400-424h23q-6 14-9 27.5t-5 32.5h-9q-58 0-113.5 12.5T172-310q-16 8-24 22.5t-8 29.5v34h269q5 18 12 32.5t17 27.5H80Zm587 44-10-66q-17-5-34.5-14.5T593-222l-55 12-25-42 47-44q-2-9-2-25t2-25l-47-44 25-42 55 12q12-12 29.5-21.5T657-456l10-66h54l10 66q17 5 34.5 14.5T795-420l55-12 25 42-47 44q2 9 2 25t-2 25l47 44-25 42-55-12q-12 12-29.5 21.5T731-186l-10 66h-54Zm27-121q36 0 58-22t22-58q0-36-22-58t-58-22q-36 0-58 22t-22 58q0 36 22 58t58 22ZM400-545q39 0 64.5-25.5T490-635q0-39-25.5-64.5T400-725q-39 0-64.5 25.5T310-635q0 39 25.5 64.5T400-545Zm0-90Zm9 411Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Users</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/events')"
            :to="{ name: 'dashboard-event-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M180-80q-24 0-42-18t-18-42v-620q0-24 18-42t42-18h65v-60h65v60h340v-60h65v60h65q24 0 42 18t18 42v301h-60v-111H180v430h319v60H180Zm709-219-71-71 29-29q8.311-8 21.156-8Q881-407 889-399l29 29q8 8.311 8 21.156Q926-336 918-328l-29 29ZM559-40v-71l216-216 71 71L630-40h-71ZM180-630h600v-130H180v130Zm0 0v-130 130Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Events</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/courses')"
            :to="{ name: 'dashboard-event-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M479-120 189-279v-240L40-600l439-240 441 240v317h-60v-282l-91 46v240L479-120Zm0-308 315-172-315-169-313 169 313 172Zm0 240 230-127v-168L479-360 249-485v170l230 127Zm1-240Zm-1 74Zm0 0Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">{{ courseBoxTitle }}</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/media')"
            :to="{ name: 'dashboard-media-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M220-240q-24 0-42-18t-18-42v-520q0-24 18-42t42-18h245l60 60h335q24 0 42 18t18 42v460q0 24-18 42t-42 18H220Zm0-60h640v-460H500l-60-60H220v520Zm590 180H100q-24 0-42-18t-18-42v-580h60v580h710v60ZM334-411h412L614-587 504-441l-79-86-91 116ZM220-300v-520 520Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Media</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('admin/media')"
            :to="{ name: 'dashboard-analytics-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M120-120v-76l60-60v136h-60Zm165 0v-236l60-60v296h-60Zm165 0v-296l60 61v235h-60Zm165 0v-235l60-60v295h-60Zm165 0v-396l60-60v456h-60ZM120-356v-85l280-278 160 160 280-281v85L560-474 400-634 120-356Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Analytics</h3>
        </router-link>
        <!-- <router-link
                v-if="userStore.permissions.includes('admin/media')"
                :to="{ name: 'dashboard-media-list' }"
                class="admin-card minecraft"
                style="background-image: url('/img/minecraft.png')">
                <img src="/img/minecraft-grass-block.png" />
                <h3>Minecraft</h3>
            </router-link> -->
        <router-link
            v-if="userStore.permissions.includes('admin/shortlinks')"
            :to="{ name: 'dashboard-shortlink-list' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 -960 960 960"
                class="h-18 mb-4">
                <path
                    d="M450-280H280q-83 0-141.5-58.5T80-480q0-83 58.5-141.5T280-680h170v60H280q-58.333 0-99.167 40.765-40.833 40.764-40.833 99Q140-422 180.833-381q40.834 41 99.167 41h170v60ZM325-450v-60h310v60H325Zm185 170v-60h170q58.333 0 99.167-40.765 40.833-40.764 40.833-99Q820-538 779.167-579 738.333-620 680-620H510v-60h170q83 0 141.5 58.5T880-480q0 83-58.5 141.5T680-280H510Z"
                    fill="currentColor" />
            </svg>
            <h3 class="font-normal text-2xl">Shortlinks</h3>
        </router-link>
        <router-link
            v-if="userStore.permissions.includes('logs/discord')"
            :to="{ name: 'dashboard-discord-bot-logs' }"
            class="bg-white border-1 border-rounded-xl text-black decoration-none hover:shadow-md transition min-w-48 p-8 text-center">
            <svg
                viewBox="0 -28.5 256 256"
                xmlns="http://www.w3.org/2000/svg"
                class="h-18 mb-4">
                <g>
                    <path
                        d="M216.856339,16.5966031 C200.285002,8.84328665 182.566144,3.2084988 164.041564,0 C161.766523,4.11318106 159.108624,9.64549908 157.276099,14.0464379 C137.583995,11.0849896 118.072967,11.0849896 98.7430163,14.0464379 C96.9108417,9.64549908 94.1925838,4.11318106 91.8971895,0 C73.3526068,3.2084988 55.6133949,8.86399117 39.0420583,16.6376612 C5.61752293,67.146514 -3.4433191,116.400813 1.08711069,164.955721 C23.2560196,181.510915 44.7403634,191.567697 65.8621325,198.148576 C71.0772151,190.971126 75.7283628,183.341335 79.7352139,175.300261 C72.104019,172.400575 64.7949724,168.822202 57.8887866,164.667963 C59.7209612,163.310589 61.5131304,161.891452 63.2445898,160.431257 C105.36741,180.133187 151.134928,180.133187 192.754523,160.431257 C194.506336,161.891452 196.298154,163.310589 198.110326,164.667963 C191.183787,168.842556 183.854737,172.420929 176.223542,175.320965 C180.230393,183.341335 184.861538,190.991831 190.096624,198.16893 C211.238746,191.588051 232.743023,181.531619 254.911949,164.955721 C260.227747,108.668201 245.831087,59.8662432 216.856339,16.5966031 Z M85.4738752,135.09489 C72.8290281,135.09489 62.4592217,123.290155 62.4592217,108.914901 C62.4592217,94.5396472 72.607595,82.7145587 85.4738752,82.7145587 C98.3405064,82.7145587 108.709962,94.5189427 108.488529,108.914901 C108.508531,123.290155 98.3405064,135.09489 85.4738752,135.09489 Z M170.525237,135.09489 C157.88039,135.09489 147.510584,123.290155 147.510584,108.914901 C147.510584,94.5396472 157.658606,82.7145587 170.525237,82.7145587 C183.391518,82.7145587 193.761324,94.5189427 193.539891,108.914901 C193.539891,123.290155 183.391518,135.09489 170.525237,135.09489 Z"
                        fill="currentColor"
                        fill-rule="nonzero"></path>
                </g>
            </svg>
            <h3 class="font-normal text-2xl">Discord</h3>
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
.page-dashboard a:not([role="button"]) {
    color: rgba(55, 65, 81);
}
</style>
