<template>
    <div class="sm-form-footer">
        <div class="sm-form-footer-column sm-form-footer-column-left">
            <slot name="left"></slot>
        </div>
        <div class="sm-form-footer-column sm-form-footer-column-right">
            <slot name="right"></slot>
        </div>
    </div>
</template>

<style lang="scss">
.sm-form-footer {
    display: flex;
    flex: 1;

    .sm-form-footer-column {
        display: flex;
        align-items: center;

        &.sm-form-footer-column-left,
        &.sm-form-footer-column-right {
            a,
            button {
                margin-left: map-get($spacer, 1);
                margin-right: map-get($spacer, 1);

                &:first-of-type {
                    margin-left: 0;
                }

                &:last-of-type {
                    margin-right: 0;
                }
            }
        }

        &.sm-form-footer-column-right {
            flex: 1;
            justify-content: flex-end;
        }
    }
}

@media screen and (max-width: 768px) {
    .sm-form-footer {
        flex-direction: column-reverse;

        .sm-form-footer-column {
            &.sm-form-footer-column-left,
            &.sm-form-footer-column-right {
                display: flex;
                flex-direction: column-reverse;
                justify-content: center;

                & > * {
                    display: block;
                    width: 100%;
                    text-align: center;

                    margin-top: map-get($spacer, 1);
                    margin-bottom: map-get($spacer, 1);
                    margin-left: 0 !important;
                    margin-right: 0 !important;
                }
            }

            &.sm-form-footer-column-left {
                margin-bottom: -#{calc(map-get($spacer, 1) / 2)};
            }

            &.sm-form-footer-column-right {
                margin-top: -#{calc(map-get($spacer, 1) / 2)};
            }
        }
    }
}
</style>
