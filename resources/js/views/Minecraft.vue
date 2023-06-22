<template>
    <SMMastHead title="Minecraft Server" />
    <div class="max-w-4xl mx-auto px-4">
        <SMHeader
            id="connect"
            text="Connecting to our Minecraft Server"
            class="pt-16 pb-2" />
        <p>
            By connecting to our Minecraft server, you agree to follow our
            <router-link :to="{ name: 'code-of-conduct' }"
                >Code of Conduct</router-link
            >
            and our
            <router-link :to="{ name: 'rules' }">Server Rules</router-link>.
        </p>
        <ol class="list-decimal mt-8">
            <li>
                Open up your Minecraft on your computer and make sure you are
                using version 1.19.3
            </li>
            <li>Click Multiplayer</li>
            <li>Click Add Server</li>
            <li>Enter Server Name STEMMechanics</li>
            <li>Enter Server Address mc.stemmech.com.au</li>
            <li>
                We have a custom resourcepack which you can enable before
                joining
            </li>
            <li>Click Done</li>
            <li>Join the Server!</li>
        </ol>
        <SMHeader text="Java or Bedrock" class="pt-16 pb-2" />
        <p>
            Regrettably, our support is exclusively for the Java edition of
            Minecraft. The reason for this is that we rely on the features
            unique to the Java edition, such as Resource Packs, Custom Items,
            and Modelling, which are not available in Bedrock (Tablet) edition.
        </p>
        <p>
            It's worth noting that if you have the Bedrock version, you may be
            eligible to acquire the Java version for free from
            <a href="https://minecraft.net">Minecraft.net</a>. However, please
            keep in mind that the Java version is solely compatible with Windows
            or Mac operating systems.
        </p>
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
        <SMAttachments class="mt-8" :attachments="downloads" />
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
