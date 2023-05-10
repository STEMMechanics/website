<template>
    <SMContainer :full="true" class="page-footer">
        <SMRow class="social">
            <SMColumn class="align-items-center">
                <SMSocialIcons />
            </SMColumn>
        </SMRow>
        <SMRow class="align-items-center">
            <SMColumn class="align-items-center logo">
                <router-link to="/" class="logo-link">
                    <img
                        class="dark:d-none"
                        src="/assets/logo.webp"
                        width="270"
                        height="40"
                        alt="STEMMechanics" />
                    <img
                        class="light:d-none"
                        src="/assets/logo-dark.webp"
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
            </SMColumn>
        </SMRow>
        <SMRow class="align-items-center">
            <SMColumn class="align-items-center copyright"
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
                    <li>
                        <a href="https://status.stemmechanics.com.au/"
                            >Status</a
                        >
                    </li>
                </ul>
            </SMColumn>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import SMSocialIcons from "../components/SMSocialIcons.vue";
</script>

<style lang="scss">
.page-footer {
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
        font-size: 115%;
        max-width: 100%;
        border-top: 1px solid var(--footer-color-border);
        border-bottom: 1px solid var(--footer-color-border);
        padding: 8px 0 !important;
        margin-bottom: 24px;

        a {
            color: var(--footer-color-text);
            vertical-align: middle;
            display: flex;
        }
    }

    .copyright,
    .logo {
        max-width: 350px;
    }

    .logo-link:hover {
        filter: none;
    }

    .footer-links ul {
        display: flex;
        flex-direction: row;
        padding: 0;
        list-style-type: none;
        flex-wrap: wrap;
        gap: 15px 30px;
        justify-content: center;

        li {
            margin: 0;
            text-align: center;
            white-space: nowrap;
        }
    }

    .footer-text {
        p {
            padding: 0;
            margin: 0 0 #{map-get($spacing, 2)} 0;
        }

        p:last-of-type {
            margin-bottom: 0;
        }
    }
}

@media only screen and (max-width: 768px) {
    .page-footer {
        .row:first-of-type {
            padding-bottom: 0;
        }

        .footer-text {
            align-items: center;
            text-align: center;
            padding: 0 8px;
        }

        .footer-links {
            align-items: center;
            justify-content: center;
        }

        .copyright,
        .logo {
            max-width: none;
            margin: 16px 0;
        }
    }
}

@media only screen and (max-width: 864px) {
    .footer {
        .footer-text {
            padding: 0 #{map-get($spacing, 3)};
        }
        .footer-links ul {
            flex-direction: column;

            li {
                text-align: center;
                margin-bottom: #{map-get($spacing, 2)};
            }
        }
    }
}

@media only screen and (max-width: 400px) {
    .footer-links ul {
        flex-direction: column;
        margin-top: 0;

        li {
            margin-top: #{map-get($spacing, 1)};
            margin-bottom: #{map-get($spacing, 1)};
        }
    }
}
</style>
