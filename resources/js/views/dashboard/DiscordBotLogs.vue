<template>
    <SMContainer :loading="formLoading" permission="logs/discord">
        <h1>Discord Bot Logs</h1>
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message" />
        <div>{{ logContent }}</div>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import SMButton from "../../components/SMButton.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";

let formLoading = ref(false);
let logContent = ref("");
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const loadData = async () => {
    formMessage.icon = "";
    formMessage.type = "error";
    formMessage.message = "";

    try {
        formLoading.value = true;
        let res = await axios.get(`logs/discord`);

        logContent = res.data.log;
    } catch (err) {
        formMessage.message = "Could not load log from server";
    }

    formLoading.value = false;
};

loadData();
</script>
