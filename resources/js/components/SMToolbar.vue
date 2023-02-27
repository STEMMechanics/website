<template>
    <div class="sm-toolbar">
        <div class="sm-toolbar-column sm-toolbar-column-left">
            <slot name="left"></slot>
        </div>
        <div class="sm-toolbar-column sm-toolbar-column-right">
            <slot name="right"></slot>
        </div>
    </div>
</template>

<style lang="scss">
.sm-toolbar {
    display: flex;
    justify-content: space-between;
    margin-bottom: map-get($spacer, 2);

    .sm-toolbar-column {
        display: flex;
        flex-direction: row;
        align-items: center;

        &.sm-toolbar-column-left {
            justify-content: flex-start;
        }

        input {
            margin-bottom: 0;
        }

        // &.form-footer-column-left, &.form-footer-column-right {
        //             a, button {
        //                 margin-left: map-get($spacer, 1);
        //                 margin-right: map-get($spacer, 1);

        //                 &:first-of-type {
        //                     margin-left: 0;
        //                 }

        //                 &:last-of-type {
        //                     margin-right: 0;
        //                 }
        //             }
        //         }

        &.sm-toolbar-column-right {
            justify-content: flex-end;
        }
        //     }
        // }

        // @media screen and (max-width: 768px) {
        //     .form-footer {
        //         flex-direction: column-reverse;

        //         .form-footer-column {
        //             &.form-footer-column-left, &.form-footer-column-right {
        //                 display: flex;
        //                 flex-direction: column-reverse;
        //                 justify-content: center;

        //                 & > * {
        //                     display: block;
        //                     width: 100%;
        //                     text-align: center;

        //                     margin-top: map-get($spacer, 1);
        //                     margin-bottom: map-get($spacer, 1);
        //                     margin-left: 0 !important;
        //                     margin-right: 0  !important;
        //                 }
        //             }

        //             &.form-footer-column-left {
        //                 margin-bottom: -#{map-get($spacer, 1) / 2};
        //             }

        //             &.form-footer-column-right {
        //                 margin-top: -#{map-get($spacer, 1) / 2};
        //             }
        //         }
    }
}
</style>
