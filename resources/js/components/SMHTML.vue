<template>
    <component :is="computedContent"></component>
</template>

<script setup lang="ts">
import DOMPurify from "dompurify";
import { computed } from "vue";
import { ImportMetaExtras } from "../../../import-meta";
// import SMImageGallery from "./SMImageGallery.vue";

const props = defineProps({
    html: {
        type: String,
        default: "",
        required: true,
    },
});

/**
 * Return the html as a component, relative links as router-link and sanitized.
 */
const computedContent = computed(() => {
    let html = "";

    // Sanitize HTML
    html = DOMPurify.sanitize(props.html);

    // Convert nl to <br>
    html = html.replaceAll("\n", "<br />");

    // Convert local links to router-links
    const regexHref = new RegExp(
        `<a ([^>]*?)href="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }(.*?>.*?)</a>`,
        "ig",
    );
    html = html.replace(regexHref, '<router-link $1to="$2</router-link>');

    // Convert image galleries to SMImageGallery component
    // const regexGallery =
    //     /<div.*?class="tinymce-gallery".*?>\s*((?:<div class="tinymce-gallery-item" style="background-image: url\('.*?'\);">.*?<\/div>\s*)*)<\/div>/gi;

    // const matches = [...html.matchAll(regexGallery)];
    // for (const match of matches) {
    //     const images = match[1]; // Extract the captured group from the match
    //     const imageSrcs = images
    //         .match(/style="background-image: url\('(.*?)'\)/gi)
    //         .map((m) => m.match(/background-image: url\('(.*?)'\)/i)[1]);
    //     const smImageGallery = `<SMImageGallery :images='${JSON.stringify(
    //         imageSrcs
    //     )}' />`;
    //     html = html.replace(images, smImageGallery);
    // }

    // Update local images to use at most the large size
    const regexImg = new RegExp(
        `<img ([^>]*?)src="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }/uploads/([^"]*?)"`,
        "ig",
    );
    html = html.replace(
        regexImg,
        `<img $1src="${
            (import.meta as ImportMetaExtras).env.APP_URL
        }/uploads/$2?size=large"`,
    );

    return { template: `<div class="sm-html">${html}</div>` };
});
</script>
