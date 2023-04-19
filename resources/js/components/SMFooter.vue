<template>
    <SMContainer :full="true" class="footer">
        <SMRow class="social">
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
                <router-link to="/" class="logo-link">
                    <img
                        class="dark:d-none"
                        src="/assets/logo.png"
                        width="270"
                        height="40"
                        alt="STEMMechanics" />
                    <img
                        class="light:d-none"
                        src="/assets/logo-dark.png"
                        width="270"
                        height="40"
                        alt="STEMMechanics" />
                </router-link>
            </SMColumn>
            <SMColumn class="footer-text">
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
            <SMColumn class="justify-content-center footer-links">
                <ul>
                    <li>
                        <router-link :to="{ name: 'contact' }"
                            >Contact Us</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'code-of-conduct' }"
                            >Code of Conduct</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'terms-and-conditions' }"
                            >Terms &amp; Conditions</router-link
                        >
                    </li>
                    <li>
                        <router-link :to="{ name: 'privacy' }"
                            >Privacy Policy</router-link
                        >
                    </li>
                </ul>
            </SMColumn>
        </SMRow>
    </SMContainer>
</template>

<style lang="scss" scoped>
.footer {
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

    .social {
        font-size: 200%;
        max-width: 100%;
        border-top: 1px solid var(--footer-color-border);
        border-bottom: 1px solid var(--footer-color-border);
        padding: 8px 0 !important;
        margin-bottom: 24px;

        a {
            vertical-align: middle;
            display: flex;
        }
    }

    .logo-link:hover {
        filter: none;
    }

    ul {
        display: flex;
        flex-direction: row;
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

    .footer-text {
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
    .footer {
        .row:first-of-type {
            padding-bottom: 0;
        }

        .footer-text {
            align-items: center;
            text-align: center;
        }

        .footer-links {
            align-items: center;
            justify-content: center;
        }
    }
}

@media only screen and (max-width: 864px) {
    .footer {
        .footer-text {
            padding: 0 1rem;
        }
        .footer-links ul {
            flex-direction: column;

            li {
                text-align: center;
                margin-bottom: 0.5rem;
            }
        }
    }
}

@media only screen and (max-width: 400px) {
    .footer ul li {
        margin-left: 0.5rem;
        margin-right: 0.5rem;
    }
}
</style>
