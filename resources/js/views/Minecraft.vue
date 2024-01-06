<template>
    <SMMastHead title="STEMCraft" />
    <div class="max-w-4xl mx-auto px-4">
        <SMHeader id="connect" text="Join us on STEMCraft" class="pt-16 pb-2" />
        <div class="flex flex-col md:flex-row gap-4 flex-items-center">
            <div
                class="h-75 w-full md:w-75 bg-norepeat bg-center bg-cover rounded-2 border-1"
                style="
                    background-image: url(&quot;/assets/vareal.webp&quot;);
                "></div>
            <div class="flex-1">
                <p>
                    Howdy Minecraft fans, we invite you to join us on our own
                    Minecraft server,
                    <a href="https://www.stemcraft.com.au/">STEMCraft</a>.
                </p>
                <p>
                    STEMCraft offers a unique blend of survival gameplay,
                    captivating mini-games, and an array of custom items and
                    mobs that will keep you immersed in a thrilling gaming
                    experience.
                </p>
                <p>
                    And best of all, the server can be customized by YOU!
                    STEMCraft goes beyond mere entertainment—it is designed with
                    an educational focus. We have incorporated special features
                    to support our workshops, from coding new in commands in
                    Java, customized mobs using 3D editing tools such as Blender
                    and designing unique items for players to interact with.
                </p>
                <p>
                    Jump over to the
                    <a href="https://www.stemcraft.com.au/">STEMCraft</a>
                    website for more information.
                </p>
            </div>
        </div>
        <SMHeader text="Goodbye Drustcraft" class="pt-16 pb-2" />
        <p>
            STEMMechanics launched the Drustcraft server three years ago and
            since then, players have had countless enjoyable experiences. Cities
            were built, bosses defeated, and most importantly, a tight-knit
            community formed.
        </p>
        <p>
            Maintaining the server design became overwhelming and took away the
            fun of playing Minecraft. Hence, in January, the decision was made
            to shut down Drustcraft and offer a more straightforward Minecraft
            server, retaining the beloved elements of Drustcraft like
            mini-games, bosses, and survival. Join us on the new STEMMechanics
            Minecraft server, where the Drustcraft community awaits.
        </p>
        <SMHeader text="So long Cairns Minecraft" class="pt-16 pb-2" />
        <p>
            After seven incredible years of operation, the Cairns Minecraft
            server officially closed its virtual doors in May 2022. This
            close-knit online community, which brought together gamers from
            around the region, renowned for its fantastic builds, lively
            competitions, and unique events. Throughout its existence, players
            forged genuine friendships, collaborated on awe-inspiring projects,
            and pushed the boundaries of creativity in the world of Minecraft.
            Although the server's closure marked the end of an era, the
            cherished memories and invaluable experiences shared by its members
            will forever remain etched in the hearts of the Cairns Minecraft
            community.
        </p>
        <SMAttachments class="mt-8" :model-value="downloads" />
    </div>
</template>

<script setup lang="ts">
import SMAttachments from "../components/SMAttachments.vue";
import SMMastHead from "../components/SMMastHead.vue";
import SMHeader from "../components/SMHeader.vue";

const downloads = [
    {
        id: "1",
        title: "Cairns Minecraft - Survival",
        name: "2103-cm-survival.zip",
        url: "https://cdn.stemmechanics.com.au/2103-cm-survival.zip",
        size: 6098565968,
    },
    {
        id: "2",
        title: "Cairns Minecraft - Creative",
        name: "2205-cm-creative-complete.zip",
        url: "https://cdn.stemmechanics.com.au/2205-cm-creative-complete.zip",
        size: 6712439230,
    },
    {
        id: "3",
        title: "Cairns Minecraft - Creative (Worlds Only)",
        name: "2205-cm-creative.zip",
        url: "https://cdn.stemmechanics.com.au/2205-cm-creative.zip",
        size: 3585899092,
    },
];
</script>
