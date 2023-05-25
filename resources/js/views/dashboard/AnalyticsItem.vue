<template>
    <SMPage :page-error="pageError" permission="admin/analytics">
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-analytics-list' }"
            back-title="Back to Analytics" />
        <SMContainer class="flex-grow-1">
            <div>{{ sessionData.ip }}</div>
            <div>{{ sessionData.useragent }}</div>
            <div>{{ sessionData.created_at }}</div>
            <div>{{ sessionData.ended_at }}</div>
            <div v-for="request of sessionData.requests" :key="request.id">
                <div>{{ request.type }}</div>
                <div>{{ request.path }}</div>
                <div>{{ request.created_at }}</div>
            </div>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";
import { api } from "../../helpers/api";
import {
    EmptyObject,
    Session,
    SessionRequestCollection,
} from "../../helpers/api.types";
import SMMastHead from "../../components/SMMastHead.vue";

type SessionOrEmpty = Session | EmptyObject;

const route = useRoute();
let pageError = ref(200);
const pageHeading = `Session ${route.params.id}`;
const sessionData = ref<SessionOrEmpty>({});

/**
 * Load the page data.
 */
const loadData = async () => {
    try {
        if (route.params.id) {
            // form.loading(true);
            let result = await api.get({
                url: "/analytics/{id}",
                params: {
                    id: route.params.id,
                },
            });

            const data = result.data as SessionRequestCollection;

            if (data && data.session) {
                sessionData.value = data.session;
            } else {
                pageError.value = 404;
            }
        }
    } catch (error) {
        pageError.value = error.status;
    } finally {
        // form.loading(false);
    }
};

loadData();
</script>

<style lang="scss"></style>
