<template>
    <a :href="computedHref" :target="props.target" rel="noopener"
        ><slot></slot
    ></a>
</template>

<script setup lang="ts">
// import axios from 'axios'
import { computed } from "vue";
import { useUserStore } from "../store/UserStore";

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    target: {
        type: String,
        default: "",
    },
});

const userStore = useUserStore();

const computedHref = computed(() => {
    const url = new URL(props.href);
    if (url.pathname.startsWith("/api/") && userStore.token) {
        return props.href + "?token=" + encodeURIComponent(userStore.token);
    }

    return props.href;
});

// const handleClick = async (event) => {
//     const url = new URL(props.href)
//     if(url.pathname.startsWith('/api/')) {
//         console.log('api')
//         event.preventDefault()

//         axios.get(props.href, {responseType: 'blob'})
//             .then(response => {
//                 const blob = new Blob([response.data], { type: response.data.type })
//                 const href = URL.createObjectURL(blob)
//                 const link = document.createElement('a')
//                 link.setAttribute('href', href)
//                 link.setAttribute('target', props.target)
//                 document.body.appendChild(link)
//                 link.click()
//                 document.body.removeChild(link)
//                 URL.revokeObjectURL(href)
//             }).catch(e => {
//                 console.log(e)
//             })
//     }

//     console.log('finish')
// }
</script>
