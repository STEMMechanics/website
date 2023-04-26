<template>
    <SMHero class="hero-offset" />

    <SMContainer class="about align-items-center">
        <template #inner>
            <h2>Join the Fun!</h2>
            <p></p>
            <p>
                To meet the demands of a constantly evolving world, it is
                essential to nurture a new generation of scientists, engineers,
                and leaders who are skilled in problem-solving. Science and
                technology offer endless possibilities for innovation and
                progress, and it is through STEM education that we can equip the
                next generation with the tools they need to tackle these
                challenges.
            </p>
            <p>
                STEMMechanics is a family-run business that is committed to
                providing accessible and inclusive STEM education to all. We
                offer a wide range of STEM courses, after-school clubs, and
                themed workshops across Queensland, both to the general public
                and to private groups.
            </p>
        </template>
    </SMContainer>
    <SMContainer class="workshops align-items-center">
        <template #inner>
            <SMRow>
                <SMColumn class="align-items-center flex-basis-55">
                    <h2>Build skills while having a great time</h2>
                    <p>
                        Our online and in-person workshops are filled with
                        engaging and exciting activities that kids will love.
                        They will have fun, make new friends, and gain valuable
                        skills that they can use throughout their lives.
                    </p>
                    <SMButton
                        :to="{ name: 'workshops' }"
                        label="Explore Workshops" />
                </SMColumn>
                <SMColumn
                    class="align-items-center justify-content-center flex-basis-45">
                    <img src="/assets/home-green-screen.webp" />
                </SMColumn>
            </SMRow>
        </template>
    </SMContainer>
    <SMContainer full class="minecraft">
        <SMContainer>
            <h2>Play Minecraft with us</h2>
            <p>
                We invite you to join us on our
                <router-link :to="{ name: 'minecraft' }"
                    >Minecraft server</router-link
                >
                where you can participate in weekly challenges and mini-games.
            </p>
            <p class="minecraft-education">
                <img
                    src="/assets/home-minecraft-edu.webp"
                    height="96"
                    width="96"
                    class="minecraft-image" />
                We also offer workshops for
                <a
                    href="https://education.minecraft.net/en-us/discover/what-is-minecraft"
                    target="_blank"
                    >Minecraft Education</a
                >, where you can learn to make it rain rabbits or grow flowers
                wherever you walk, all without the need for a school account.
            </p>
            <p class="pt-5">
                <img
                    src="/assets/home-minecraft-address.webp"
                    height="70"
                    class="minecraft-address" />
            </p>
        </SMContainer>
    </SMContainer>
    <SMContainer class="support align-items-center">
        <template #inner>
            <h2>And the support doesn't stop!</h2>
            <SMRow>
                <SMColumn
                    class="align-items-center justify-content-center flex-basis-45">
                    <div class="support-image">
                        <img src="/assets/home-discord.webp" />
                    </div>
                </SMColumn>
                <SMColumn class="align-items-center flex-basis-55">
                    <p>
                        Though the workshop has come to a close, we remain
                        available to assist you via email and Discord with any
                        projects you undertake at home. We are always happy to
                        help.
                    </p>
                    <div class="button-row">
                        <SMButton
                            type="primary"
                            to="https://discord.gg/yNzk4x7mpD"
                            label="Join Discord" />
                        <SMButton
                            :to="{ name: 'contact' }"
                            label="Contact Us" />
                    </div>
                </SMColumn>
            </SMRow>
        </template>
    </SMContainer>
</template>

<script setup lang="ts">
import SMButton from "../components/SMButton.vue";
import SMHero from "../components/SMHero.vue";
</script>

<style lang="scss">
.page-home {
    .hero-offset {
        margin-top: -80px;
    }

    .about .container-inner {
        margin: 64px 32px 32px;
        padding: 0 90px 64px 90px;
        background-color: var(--accent-1-color);
        color: var(--accent-1-color-text);
        border-radius: 24px;
        max-width: 960px;

        h2 {
            font-size: 400%;
            text-align: center;
            color: var(--accent-1-color-text);
        }

        p {
            font-size: 125%;
        }
    }

    .workshops .container-inner {
        margin: 64px 24px;
        max-width: 960px;

        h2 {
            font-size: 300%;
            text-align: center;
        }

        p {
            font-size: 125%;
            max-width: #{calc(map-get($spacing, 6) * 16)};
            margin: 16px auto 32px auto;
        }

        img {
            border-radius: 50%;
            height: #{calc(map-get($spacing, 5) * 15)};
            width: #{calc(map-get($spacing, 5) * 15)};
        }

        .button {
            background-color: var(--accent-1-color);
            color: var(--accent-1-color-text);
        }
    }

    .minecraft {
        margin-top: 64px;
        background-image: url("/assets/home-minecraft.webp");
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        padding: 48px;
        color: #f8f8f8;

        h2 {
            font-size: 300%;
            text-align: center;
            color: #f8f8f8;
        }

        p {
            font-size: 125%;
            text-align: center;
            margin: 24px auto;
        }

        .minecraft-education {
            text-align: left;

            .minecraft-image {
                float: left;
                margin-top: 24px;
                margin-right: 48px;
            }
        }

        .minecraft-address {
            width: 100%;
            height: 100%;
        }
    }

    .support .container-inner {
        margin: 64px 32px 32px;
        padding: 0 90px 64px 90px;
        color: var(--accent-2-color-text);
        background-color: var(--accent-2-color);
        border-radius: 24px;
        max-width: 960px;

        .row {
            gap: 30px;
        }

        .support-image {
            display: block;
        }

        img {
            margin: 32px 0;
            border-radius: 24px;
            width: 320px;
            transform: rotateZ(-10deg);
        }

        h2 {
            font-size: 300%;
            text-align: center;
            margin-bottom: 16px;
            color: var(--accent-2-color-text);
        }

        p {
            font-size: 125%;
        }

        .button-row {
            display: flex;
            width: 100%;
            margin-top: 16px;

            flex-direction: column;
            gap: 15px;
        }
    }
}

@media only screen and (max-width: 768px) {
    .page-home {
        .about {
            padding: 0;

            .container-inner {
                margin: 0;
                padding: 0 32px;
                border-radius: 0;
            }
        }

        .workshops {
            margin-top: 0;
            margin-bottom: 0;

            .row {
                gap: 30px;
            }
        }

        .minecraft {
            margin: 0;
            padding: 32px;

            .minecraft-education {
                text-align: center;

                .minecraft-image {
                    float: none;
                    display: block;
                    margin: 0 auto #{map-get($spacing, 3)} auto;
                }
            }
        }

        .support {
            padding: 0;

            .container-inner {
                margin: 0;
                padding: 32px;
                border-radius: 0;

                .row {
                    gap: 30px;
                }
            }
        }
    }
}
</style>
