<template>
    <SMPage full class="home">
        <SMCarousel>
            <SMCarouselSlide
                v-for="(slide, index) in slides"
                :key="index"
                :title="slide.title"
                :content="slide.content"
                :image="slide.image"
                :url="slide.url"
                :cta="slide.cta"></SMCarouselSlide>
        </SMCarousel>
        <SMContainer class="about">
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
        </SMContainer>
        <SMContainer class="workshops">
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
                        :to="{ name: 'workshop-list' }"
                        label="Explore Workshops" />
                </SMColumn>
                <SMColumn
                    class="align-items-center justify-content-center flex-basis-45">
                    <img src="/img/green-screen.jpg" />
                </SMColumn>
            </SMRow>
        </SMContainer>
        <SMContainer class="support">
            <h2>And the support doesn't stop!</h2>
            <SMRow>
                <SMColumn
                    class="align-items-center justify-content-center flex-basis-45">
                    <img src="/img/discord.jpg" />
                </SMColumn>
                <SMColumn class="align-items-center flex-basis-55">
                    <p>
                        Though the workshop has come to a close, we remain
                        available to assist you via email and Discord with any
                        projects you undertake at home. We are always happy to
                        help.
                    </p>
                    <div class="button-row">
                        <a href="https://discord.gg/yNzk4x7mpD">Join Discord</a>
                        <router-link :to="{ name: 'contact' }"
                            >Contact Us</router-link
                        >
                    </div>
                </SMColumn>
            </SMRow>
        </SMContainer>
        <SMContainer full class="minecraft">
            <SMContainer>
                <h2>Play Minecraft with us</h2>
                <p>
                    We invite you to join us on our Minecraft servers,
                    supporting both Bedrock and Java clients, where you can
                    participate in weekly challenges and mini-games.
                </p>
                <p class="minecraft-education">
                    <img
                        src="/img/minecraft-edu.png"
                        height="96"
                        width="96"
                        class="minecraft-image" />
                    We also offer workshops for
                    <a
                        href="https://education.minecraft.net/en-us/discover/what-is-minecraft"
                        target="_blank"
                        >Minecraft Education</a
                    >, where you can learn to make it rain rabbits or grow
                    flowers wherever you walk, all without the need for a school
                    account.
                </p>
                <p class="pt-5">
                    <img
                        src="/img/minecraft-address.png"
                        height="70"
                        class="minecraft-address" />
                </p>
            </SMContainer>
        </SMContainer>
        <SMContainer class="subscribe">
            <h2>Be the first to know</h2>
            <p>
                Sign up for our mailing list to receive expert tips and tricks,
                as well as updates on upcoming workshops.
            </p>
            <SMDialog class="p-0" no-shadow>
                <SMForm v-model="form" @submit.prevent="handleSubscribe">
                    <div class="form-row">
                        <SMInput control="email" />
                        <SMButton type="submit" label="Subscribe" />
                    </div>
                </SMForm>
            </SMDialog>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { excerpt } from "../helpers/string";
import { SMDate } from "../helpers/datetime";
import SMInput from "../components/SMInput.vue";
import SMButton from "../components/SMButton.vue";
import SMCarousel from "../components/SMCarousel.vue";
import SMCarouselSlide from "../components/SMCarouselSlide.vue";
import SMForm from "../components/SMForm.vue";
import SMDialog from "../components/SMDialog.vue";
import SMPage from "../components/SMPage.vue";
import { useReCaptcha } from "vue-recaptcha-v3";
import { FormObject, FormControl } from "../helpers/form";
import { And, Email, Required } from "../helpers/validate";
import { api } from "../helpers/api";

const slides = ref([]);
const { executeRecaptcha, recaptchaLoaded } = useReCaptcha();
const form = reactive(
    FormObject({
        email: FormControl("", And([Required(), Email()])),
    })
);

const handleLoad = async () => {
    slides.value = [];
    let posts = [];
    let events = [];

    api.get({
        url: "/posts",
        params: {
            limit: 3,
        },
    }).then((response) => {
        if (response.data.posts) {
            response.data.posts.forEach((post) => {
                posts.push({
                    title: post.title,
                    content: excerpt(post.content, 200),
                    image: post.hero,
                    url: { name: "post-view", params: { slug: post.slug } },
                    cta: "Read More...",
                });
            });
        }
    });

    try {
        let result = await api.get({
            url: "/events",
            params: {
                limit: 3,
                end_at:
                    ">" +
                    new SMDate("now").format("yyyy-MM-dd HH:mm:ss", {
                        utc: true,
                    }),
            },
        });

        if (result.data.events) {
            result.data.events.forEach((event) => {
                events.push({
                    title: event.title,
                    content: excerpt(event.content, 200),
                    image: event.hero,
                    url: { name: "workshop-view", params: { id: event.id } },
                    cta: "View Workshop",
                });
            });
        }
    } catch (error) {
        /* empty */
    }

    for (let i = 1; i <= Math.max(posts.length, events.length); i++) {
        if (i <= posts.length) {
            slides.value.push(posts[i - 1]);
        }
        if (i <= events.length) {
            slides.value.push(events[i - 1]);
        }
    }
};

const handleSubscribe = async () => {
    form.loading(true);
    form.message();

    try {
        await recaptchaLoaded();
        const captcha = await executeRecaptcha("submit");

        await api.post({
            url: "/subscriptions",
            body: {
                email: form.email.value,
                captcha_token: captcha,
            },
        });

        form.email.value = "";
        form.message("Your email address has been subscribed.", "success");
    } catch (err) {
        form.apiErrors(err);
    }

    form.loading(false);
};

handleLoad();
</script>

<style lang="scss">
.home {
    margin-top: -3.25rem !important;
    background-color: #fff;

    h2 {
        font-weight: 1000;
        text-align: center;
        margin: 0;
    }

    .about {
        margin-top: 5rem;
        margin-left: 2rem;
        margin-right: 2rem;
        background-color: #3d4e5d;
        color: rgb(230, 245, 235);
        border-radius: 24px;
        padding: 4rem 8rem;
        width: auto;
        align-self: center;

        h2 {
            font-size: 400%;
        }

        p {
            font-size: 125%;
            line-height: 150%;
        }
    }

    .workshops {
        margin: 8rem auto;
        align-self: center;

        h2 {
            font-size: 300%;
        }

        p {
            font-size: 125%;
            line-height: 150%;
            max-width: 32rem;
            text-align: center;
            margin: 1rem auto 2rem auto;
        }

        img {
            border-radius: 50rem;
            height: 20rem;
            width: 20rem;
        }
    }

    .support {
        background-color: #e6f5eb;
        color: rgb(56, 79, 95);
        border-radius: 24px;
        padding: 4rem 5rem;
        margin-left: 2rem;
        margin-right: 2rem;
        width: auto;
        align-self: center;

        img {
            border-radius: 24px;
            height: 80%;
            width: 80%;
            transform: rotateZ(-10deg);
        }

        h2 {
            font-size: 300%;
            text-align: left;
            text-align: center;
            margin-bottom: 1rem;
        }

        p {
            font-size: 125%;
            line-height: 150%;
        }

        .button-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: 1rem;

            a {
                font-weight: bold;
                color: inherit;
                border: 2px solid rgb(56, 79, 95);
                border-radius: 24px;
                padding: 0.5rem 1.5rem;
                transition: color 0.2s ease-in-out, border 0.2s ease-in-out,
                    background 0.2s ease-in-out;

                &:hover {
                    text-decoration: none;
                    background-color: rgb(56, 79, 95);
                    color: #e6f5eb;
                }
            }
        }
    }

    .minecraft {
        margin-top: 4rem;
        background-image: url("/img/minecraft.png");
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        padding: 4rem;
        color: #fff;

        h2 {
            font-size: 300%;
        }

        p {
            font-size: 125%;
            line-height: 150%;
            text-align: center;
            max-width: 44rem;
            margin: 1rem auto;
        }

        .minecraft-education {
            text-align: left;

            .minecraft-image {
                float: left;
                margin-top: 1rem;
                margin-right: 2rem;
            }
        }

        .minecraft-address {
            width: 100%;
            height: 100%;
        }
    }

    .subscribe {
        margin: 6rem auto 0 auto;
        align-self: center;

        h2 {
            font-size: 200%;
        }

        p {
            text-align: center;
            font-size: 120%;
            line-height: 140%;
            margin: 1rem auto;
        }

        .form-row {
            background-color: #eee;
            border-radius: 24px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 600px;
            margin: 1rem auto;
        }
    }
}

@media only screen and (max-width: 1024px) {
    .home {
        .about {
            padding: 4rem;
        }

        .support {
            padding: 4rem;
        }
    }
}

@media only screen and (max-width: 896px) {
    .home {
        .support {
            .row {
                flex-direction: column;
            }
        }
    }
}

@media only screen and (max-width: 768px) {
    .home {
        .about {
            margin-top: 2rem;
            margin-left: 0;
            margin-right: 0;
            border-radius: 0;
        }

        .workshops {
            margin-top: 4rem;
            margin-bottom: 4rem;
        }

        .support {
            margin-left: 0;
            margin-right: 0;
            border-radius: 0;
        }

        .minecraft {
            margin-top: 0;
            padding-left: 1rem;
            padding-right: 1rem;

            .minecraft-education {
                text-align: center;

                .minecraft-image {
                    float: none;
                    display: block;
                    margin: 0 auto 1rem auto;
                }
            }
        }
    }
}

@media only screen and (max-width: 640px) {
    .home {
        .about {
            padding: 2rem;

            h2 {
                font-size: 300%;
            }

            p {
                font-size: 100%;
                line-height: 150%;
            }
        }

        .workshops,
        .support,
        .minecraft,
        .subscribe {
            padding: 2rem;

            h2 {
                font-size: 200%;
            }

            p {
                font-size: 100%;
            }
        }
    }
}
</style>
