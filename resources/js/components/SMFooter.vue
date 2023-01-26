<template>
    <SMContainer :full="true" class="footer">
        <SMRow class="social">
            <SMColumn class="align-items-center">
                <ul>
                    <li>
                        <a href="https://facebook.com/stemmechanics"
                            ><font-awesome-icon icon="fa-brands fa-facebook"
                        /></a>
                    </li>
                    <li>
                        <a href="https://mastodon.au/@stemmechanics"
                            ><font-awesome-icon icon="fa-brands fa-mastodon"
                        /></a>
                    </li>
                    <li>
                        <a href="https://www.youtube.com/@stemmechanics"
                            ><font-awesome-icon icon="fa-brands fa-youtube"
                        /></a>
                    </li>
                    <li>
                        <a href="https://twitter.com/stemmechanics"
                            ><font-awesome-icon icon="fa-brands fa-twitter"
                        /></a>
                    </li>
                    <li>
                        <a href="https://github.com/stemmechanics"
                            ><font-awesome-icon icon="fa-brands fa-github"
                        /></a>
                    </li>
                    <li>
                        <a href="https://discord.gg/yNzk4x7mpD"
                            ><font-awesome-icon icon="fa-brands fa-discord"
                        /></a>
                    </li>
                </ul>
            </SMColumn>
        </SMRow>
        <SMRow>
            <SMColumn
                width="350px"
                class="align-items-center justify-content-center align-items-center">
                <router-link to="/" class="brand"></router-link>
            </SMColumn>
            <SMColumn class="footer-acknowledgement">
                <p>
                    STEMMechanics Australia acknowledges the Traditional Owners
                    of Country throughout Australia and the continuing
                    connection to land, cultures and communities. We pay our
                    respect to Aboriginal and Torres Strait Islander cultures;
                    and to Elders both past, present and emerging.
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
.footer {
    flex: 0;
    align-items: center;
    margin-top: calc(map-get($spacer, 5) * 2);
    font-size: 80%;
    background-color: #f8f8f8;
    padding: 0 0 map-get($spacer, 5) 0;

    .social {
        font-size: 200%;
        max-width: 100%;
        border-top: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        background-color: #efefef;
        padding: map-get($spacer, 2) 0 !important;
        margin-bottom: map-get($spacer, 5);

        a {
            vertical-align: middle;
            color: #777777;
        }
    }

    .brand {
        display: inline-block;
        background-image: url("/img/logo.png");
        background-position: left top;
        background-repeat: no-repeat;
        background-size: contain;
        width: 16.5rem;
        height: 3rem;
    }

    a,
    a:visited {
        color: $primary-color-dark;

        &:hover {
            color: $primary-color-darker;
        }
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

    .footer-acknowledgement {
        p {
            padding: 0;
            margin: 0 0 0.5rem 0;
        }
    }
}

@media only screen and (max-width: 768px) {
    .footer {
        .row:first-of-type {
            padding-bottom: 0;
        }

        .footer-acknowledgement {
            align-items: center;
            text-align: center;
        }

        .footer-links {
            align-items: center;
            justify-content: center;
        }
    }
}

@media only screen and (max-width: 640px) {
    .footer {
        .footer-acknowledgement {
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
</style>
