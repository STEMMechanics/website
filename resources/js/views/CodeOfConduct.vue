<template>
    <SMMastHead title="Code of Conduct" />
    <SMContainer>
        <div class="container-text">
            <p>
                STEMMechanics supports the international community open to
                everyone without discrimination. We want this community to be a
                safe and welcoming place for both newcomers and current members.
                Everyone should feel comfortable and accepted regardless of
                their personal background and affiliation our projects and
                workshops.
            </p>
            <h3>Philosophy</h3>
            <p>
                If you have a question or would like help with a project, you
                can send it our way using the form on this page or be emailing
                <a href="mailto:hello@stemmechanics.com.au"
                    >hello@stemmechanics.com.au</a
                >.
            </p>
            <h3>Application</h3>
            <p>
                This Code of Conduct applies to all users, contributors and
                participants who engage with the STEMMechanics workshops,
                projects and its community platforms.
            </p>
            <h3>Expectations</h3>
            <ul>
                <li>
                    Politeness is expected at all times. Be kind and courteous.
                </li>
                <li>
                    Always assume positive intent from others. Be aware that
                    differences in culture and English proficiency make written
                    communication more difficult than face-to-face communication
                    and that your interpretation of messages may not be the one
                    the author intended. Conversely, if someone asks you to
                    rephrase something you said, be ready to do so without
                    feeling judged.
                </li>
                <li>
                    Feedback is always welcome but keep your criticism
                    constructive. We encourage you to open discussions,
                    proposals, issues, and bug reports. Use the community
                    platforms to discuss improvements, not to vent out
                    frustration. Similarly, when other users offer you feedback
                    please accept it gracefully.
                </li>
            </ul>
            <h3>Code of Conduct team</h3>
            <ul>
                <li>James Collins, james@stemmechanics.com.au</li>
                <ul>
                    <li>
                        GitHub / Discord / Reddit:
                        <span class="italic">nomadjimbob</span>
                    </li>
                    <li>Languages: English</li>
                </ul>
            </ul>
        </div>
    </SMContainer>
</template>

<script setup lang="ts">
import SMMastHead from "../components/SMMastHead.vue";
</script>

<style lang="scss">
.container-text {
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.4em;

    h3 {
        margin-top: 60px;
    }
}

.sm-contact-socials {
    list-style-type: none;
    padding: 0;
    display: flex;
    font-size: 200%;
    justify-content: center;

    li {
        margin: 0 16px;
    }
}

.address {
    margin-top: 60px;
}
</style>
