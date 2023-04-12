<template>
    <SMContainer :full="true" class="sm-footer">
        <SMRow class="sm-social">
            <SMColumn class="align-items-center">
                <ul>
                    <li>
                        <a href="https://facebook.com/stemmechanics"
                            ><ion-icon name="logo-facebook"></ion-icon
                        ></a>
                    </li>
                    <li>
                        <a href="https://mastodon.au/@stemmechanics"
                            ><ion-icon name="logo-mastodon"></ion-icon
                        ></a>
                    </li>
                    <li>
                        <a href="https://www.youtube.com/@stemmechanics"
                            ><ion-icon name="logo-youtube"></ion-icon
                        ></a>
                    </li>
                    <li>
                        <a href="https://twitter.com/stemmechanics"
                            ><ion-icon name="logo-twitter"></ion-icon
                        ></a>
                    </li>
                    <li>
                        <a href="https://github.com/stemmechanics"
                            ><ion-icon name="logo-github"></ion-icon
                        ></a>
                    </li>
                    <li>
                        <a href="https://discord.gg/yNzk4x7mpD"
                            ><ion-icon name="logo-discord"></ion-icon
                        ></a>
                    </li>
                </ul>
            </SMColumn>
        </SMRow>
        <SMRow>
            <SMColumn
                width="350px"
                class="align-items-center justify-content-center align-items-center">
                <router-link to="/" class="sm-logo-link">
                    <img
                        src="/assets/logo-dark.png"
                        width="270"
                        height="40"
                        alt="STEMMechanics" />
                </router-link>
            </SMColumn>
            <SMColumn class="sm-footer-text">
                <p>
                    STEMMechanics Australia acknowledges the Traditional Owners
                    of Country throughout Australia and the continuing
                    connection to land, cultures and communities. We pay our
                    respect to Aboriginal and Torres Strait Islander cultures;
                    and to Elders both past, present and emerging.
                </p>
                <p class="small">
                    This site is protected by reCAPTCHA and the Google
                    <a href="https://policies.google.com/privacy"
                        >Privacy Policy</a
                    >
                    and
                    <a href="https://policies.google.com/terms"
                        >Terms of Service</a
                    >
                    apply.
                </p>
            </SMColumn>
        </SMRow>
        <SMRow>
            <SMColumn
                width="350px"
                class="justify-content-center align-items-center copyright"
                >Made with ❤️ - Copyright &copy; 2023</SMColumn
            >
            <SMColumn class="justify-content-center sm-footer-links">
                <ul>
                    <li>
                        <router-link :to="{ name: 'contact' }"
                            >Contact Us</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'terms' }"
                            >Terms &amp; Conditions</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'privacy' }"
                            >Privacy Policy</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'rules' }">Rules</router-link>
                    </li>
                </ul>
            </SMColumn>
        </SMRow>
    </SMContainer>
</template>

<style lang="scss" scoped>
.sm-footer {
    flex: 0;
    align-items: center;
    font-size: 80%;
    background-color: var(--footer-color);
    color: var(--footer-color-text);
    padding: 0 0 24px 0;
    margin-top: 48px;

    a,
    a:visited {
        color: var(--footer-color-text);
        text-decoration-color: var(--footer-color-text);

        &:hover {
            color: var(--footer-color-text);
            filter: brightness(115%);
        }
    }

    .sm-social {
        font-size: 200%;
        max-width: 100%;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 8px 0 !important;
        margin-bottom: 24px;

        a {
            vertical-align: middle;
            display: flex;
        }
    }

    .sm-logo-link:hover {
        filter: none;
    }

    ul {
        display: flex;
        flex-direction: row;
        margin: 0 -1rem;
        padding: 0;
        list-style-type: none;

        li {
            padding: 0;
            margin-left: 1rem;
            margin-right: 1rem;
            margin-bottom: 0;
            text-align: center;
        }
    }

    .sm-footer-text {
        p {
            padding: 0;
            margin: 0 0 0.5rem 0;
        }

        p:last-of-type {
            margin-bottom: 0;
        }
    }
}

@media only screen and (max-width: 768px) {
    .sm-footer {
        .row:first-of-type {
            padding-bottom: 0;
        }

        .sm-footer-text {
            align-items: center;
            text-align: center;
        }

        .sm-footer-links {
            align-items: center;
            justify-content: center;
        }
    }
}

@media only screen and (max-width: 864px) {
    .sm-footer {
        .sm-footer-text {
            padding: 0 1rem;
        }
        .sm-footer-links ul {
            flex-direction: column;

            li {
                text-align: center;
                margin-bottom: 0.5rem;
            }
        }
    }
}

@media only screen and (max-width: 400px) {
    .sm-footer ul li {
        margin-left: 0.5rem;
        margin-right: 0.5rem;
    }
}
</style>
