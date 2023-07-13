<template>
    <div class="sm-html">
        <bubble-menu
            :editor="editor"
            :should-show="bubbleMenuShow"
            :tippy-options="{ hideOnClick: false }"
            v-if="editor">
            <button @click.prevent="setImageSize('small')">small</button>
            <button @click.prevent="setImageSize('medium')">medium</button>
            <button @click.prevent="setImageSize('large')">large</button>
            <button @click.prevent="setImageSize('scaled')">original</button>
            <button @click.prevent="editor.commands.deleteSelection()">
                remove
            </button>
        </bubble-menu>
        <div
            v-if="editor"
            class="flex flex-wrap bg-white border border-gray rounded-t-2">
            <div class="flex px-1 relative border-r">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 -960 960 960"
                    class="absolute right-1 top-1.5 h-6 pointer-events-none">
                    <path
                        d="M480-360 280-559h400L480-360Z"
                        fill="currentColor" />
                </svg>
                <select
                    class="appearance-none pl-3 pr-7 text-xs outline-none select-none bg-white"
                    @change="updateNode">
                    <option
                        value="paragraph"
                        :selected="editor.isActive('paragraph')">
                        Paragraph
                    </option>
                    <option value="small" :selected="editor.isActive('small')">
                        Small
                    </option>
                    <option
                        value="h1"
                        :selected="editor.isActive('heading', { level: 1 })">
                        Heading 1
                    </option>
                    <option
                        value="h2"
                        :selected="editor.isActive('heading', { level: 2 })">
                        Heading 2
                    </option>
                    <option
                        value="h3"
                        :selected="editor.isActive('heading', { level: 3 })">
                        Heading 3
                    </option>
                    <option
                        value="h4"
                        :selected="editor.isActive('heading', { level: 4 })">
                        Heading 4
                    </option>
                    <option
                        value="h5"
                        :selected="editor.isActive('heading', { level: 5 })">
                        Heading 5
                    </option>
                    <option
                        value="h6"
                        :selected="editor.isActive('heading', { level: 6 })">
                        Heading 6
                    </option>
                    <option value="info" :selected="editor.isActive('info')">
                        Info
                    </option>
                    <option
                        value="success"
                        :selected="editor.isActive('success')">
                        Success
                    </option>
                    <option
                        value="warning"
                        :selected="editor.isActive('warning')">
                        Warning
                    </option>
                    <option
                        value="danger"
                        :selected="editor.isActive('danger')">
                        Danger
                    </option>
                </select>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="editor.chain().focus().toggleBold().run()"
                    :disabled="!editor.can().chain().focus().toggleBold().run()"
                    title="bold"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('bold')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M13.5,15.5H10V12.5H13.5A1.5,1.5 0 0,1 15,14A1.5,1.5 0 0,1 13.5,15.5M10,6.5H13A1.5,1.5 0 0,1 14.5,8A1.5,1.5 0 0,1 13,9.5H10M15.6,10.79C16.57,10.11 17.25,9 17.25,8C17.25,5.74 15.5,4 13.25,4H7V18H14.04C16.14,18 17.75,16.3 17.75,14.21C17.75,12.69 16.89,11.39 15.6,10.79Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().toggleItalic().run()"
                    :disabled="
                        !editor.can().chain().focus().toggleItalic().run()
                    "
                    title="italic"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('italic')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,4V7H12.21L8.79,15H6V18H14V15H11.79L15.21,7H18V4H10Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleUnderline().run()
                    "
                    :disabled="
                        !editor.can().chain().focus().toggleUnderline().run()
                    "
                    title="underline"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('underline')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M5,21H19V19H5V21M12,17A6,6 0 0,0 18,11V3H15.5V11A3.5,3.5 0 0,1 12,14.5A3.5,3.5 0 0,1 8.5,11V3H6V11A6,6 0 0,0 12,17Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().toggleStrike().run()"
                    :disabled="
                        !editor.can().chain().focus().toggleStrike().run()
                    "
                    title="strike"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('strike')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M23,12V14H18.61C19.61,16.14 19.56,22 12.38,22C4.05,22.05 4.37,15.5 4.37,15.5L8.34,15.55C8.37,18.92 11.5,18.92 12.12,18.88C12.76,18.83 15.15,18.84 15.34,16.5C15.42,15.41 14.32,14.58 13.12,14H1V12H23M19.41,7.89L15.43,7.86C15.43,7.86 15.6,5.09 12.15,5.08C8.7,5.06 9,7.28 9,7.56C9.04,7.84 9.34,9.22 12,9.88H5.71C5.71,9.88 2.22,3.15 10.74,2C19.45,0.8 19.43,7.91 19.41,7.89Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleHighlight().run()
                    "
                    :disabled="
                        !editor.can().chain().focus().toggleHighlight().run()
                    "
                    title="highlight"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('highlight')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M18.5,1.15C17.97,1.15 17.46,1.34 17.07,1.73L11.26,7.55L16.91,13.2L22.73,7.39C23.5,6.61 23.5,5.35 22.73,4.56L19.89,1.73C19.5,1.34 19,1.15 18.5,1.15M10.3,8.5L4.34,14.46C3.56,15.24 3.56,16.5 4.36,17.31C3.14,18.54 1.9,19.77 0.67,21H6.33L7.19,20.14C7.97,20.9 9.22,20.89 10,20.12L15.95,14.16"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="
                        editor.chain().focus().setTextAlign('left').run()
                    "
                    title="align left"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive({ textAlign: 'left' })
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M3,3H21V5H3V3M3,7H15V9H3V7M3,11H21V13H3V11M3,15H15V17H3V15M3,19H21V21H3V19Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().setTextAlign('center').run()
                    "
                    title="align center"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive({ textAlign: 'center' })
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M3,3H21V5H3V3M7,7H17V9H7V7M3,11H21V13H3V11M7,15H17V17H7V15M3,19H21V21H3V19Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().setTextAlign('right').run()
                    "
                    title="align right"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive({ textAlign: 'right' })
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M3,3H21V5H3V3M9,7H21V9H9V7M3,11H21V13H3V11M9,15H21V17H9V15M3,19H21V21H3V19Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().setTextAlign('justify').run()
                    "
                    title="align right"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive({ textAlign: 'justify' })
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M3,3H21V5H3V3M3,7H21V9H3V7M3,11H21V13H3V11M3,15H21V17H3V15M3,19H21V21H3V19Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="setLink()"
                    title="link"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('link')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M3.9,12C3.9,10.29 5.29,8.9 7,8.9H11V7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12M8,13H16V11H8V13M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.71 18.71,15.1 17,15.1H13V17H17A5,5 0 0,0 22,12A5,5 0 0,0 17,7Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().unsetLink().run()"
                    title="unlink"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                        'text-gray-6',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M17,7H13V8.9H17C18.71,8.9 20.1,10.29 20.1,12C20.1,13.43 19.12,14.63 17.79,15L19.25,16.44C20.88,15.61 22,13.95 22,12A5,5 0 0,0 17,7M16,11H13.81L15.81,13H16V11M2,4.27L5.11,7.38C3.29,8.12 2,9.91 2,12A5,5 0 0,0 7,17H11V15.1H7C5.29,15.1 3.9,13.71 3.9,12C3.9,10.41 5.11,9.1 6.66,8.93L8.73,11H8V13H10.73L13,15.27V17H14.73L18.74,21L20,19.74L3.27,3L2,4.27Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="setImage()"
                    title="image"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('image')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M19,19H5V5H19M19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M13.96,12.29L11.21,15.83L9.25,13.47L6.5,17H17.5L13.96,12.29Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="setLink()"
                    title="gallery"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('gallery')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M21,17H7V3H21M21,1H7A2,2 0 0,0 5,3V17A2,2 0 0,0 7,19H21A2,2 0 0,0 23,17V3A2,2 0 0,0 21,1M3,5H1V21A2,2 0 0,0 3,23H19V21H3M15.96,10.29L13.21,13.83L11.25,11.47L8.5,15H19.5L15.96,10.29Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="
                        editor.chain().focus().toggleBulletList().run()
                    "
                    title="bullet list"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('bulletList')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M7,5H21V7H7V5M7,13V11H21V13H7M4,4.5A1.5,1.5 0 0,1 5.5,6A1.5,1.5 0 0,1 4,7.5A1.5,1.5 0 0,1 2.5,6A1.5,1.5 0 0,1 4,4.5M4,10.5A1.5,1.5 0 0,1 5.5,12A1.5,1.5 0 0,1 4,13.5A1.5,1.5 0 0,1 2.5,12A1.5,1.5 0 0,1 4,10.5M7,19V17H21V19H7M4,16.5A1.5,1.5 0 0,1 5.5,18A1.5,1.5 0 0,1 4,19.5A1.5,1.5 0 0,1 2.5,18A1.5,1.5 0 0,1 4,16.5Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleOrderedList().run()
                    "
                    title="ordered list"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('orderedList')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M7,13V11H21V13H7M7,19V17H21V19H7M7,7V5H21V7H7M3,8V5H2V4H4V8H3M2,17V16H5V20H2V19H4V18.5H3V17.5H4V17H2M4.25,10A0.75,0.75 0 0,1 5,10.75C5,10.95 4.92,11.14 4.79,11.27L3.12,13H5V14H2V13.08L4,11H2V10H4.25Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="
                        editor.chain().focus().toggleCodeBlock().run()
                    "
                    title="code block"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('codeBlock')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M14.6,16.6L19.2,12L14.6,7.4L16,6L22,12L16,18L14.6,16.6M9.4,16.6L4.8,12L9.4,7.4L8,6L2,12L8,18L9.4,16.6Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().toggleBlockquote().run()
                    "
                    title="blockquote"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('blockquote')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,7L8,11H11V17H5V11L7,7H10M18,7L16,11H19V17H13V11L15,7H18Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().setHorizontalRule().run()
                    "
                    title="horizontal rule"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'bg-white',
                        'hover-bg-gray-3',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path d="M19,13H5V11H19V13Z" fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="
                        editor.chain().focus().unsetSuperscript().run();
                        editor.chain().focus().toggleSubscript().run();
                    "
                    title="subscript"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('subscript')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M16,7.41L11.41,12L16,16.59L14.59,18L10,13.41L5.41,18L4,16.59L8.59,12L4,7.41L5.41,6L10,10.59L14.59,6L16,7.41M21.85,21.03H16.97V20.03L17.86,19.23C18.62,18.58 19.18,18.04 19.56,17.6C19.93,17.16 20.12,16.75 20.13,16.36C20.14,16.08 20.05,15.85 19.86,15.66C19.68,15.5 19.39,15.38 19,15.38C18.69,15.38 18.42,15.44 18.16,15.56L17.5,15.94L17.05,14.77C17.32,14.56 17.64,14.38 18.03,14.24C18.42,14.1 18.85,14 19.32,14C20.1,14.04 20.7,14.25 21.1,14.66C21.5,15.07 21.72,15.59 21.72,16.23C21.71,16.79 21.53,17.31 21.18,17.78C20.84,18.25 20.42,18.7 19.91,19.14L19.27,19.66V19.68H21.85V21.03Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().unsetSubscript().run();
                        editor.chain().focus().toggleSuperscript().run();
                    "
                    title="Superscript"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        editor.isActive('superscript')
                            ? ['bg-sky-6', 'text-white']
                            : ['bg-white', 'text-gray-6'],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M16,7.41L11.41,12L16,16.59L14.59,18L10,13.41L5.41,18L4,16.59L8.59,12L4,7.41L5.41,6L10,10.59L14.59,6L16,7.41M21.85,9H16.97V8L17.86,7.18C18.62,6.54 19.18,6 19.56,5.55C19.93,5.11 20.12,4.7 20.13,4.32C20.14,4.04 20.05,3.8 19.86,3.62C19.68,3.43 19.39,3.34 19,3.33C18.69,3.34 18.42,3.4 18.16,3.5L17.5,3.89L17.05,2.72C17.32,2.5 17.64,2.33 18.03,2.19C18.42,2.05 18.85,2 19.32,2C20.1,2 20.7,2.2 21.1,2.61C21.5,3 21.72,3.54 21.72,4.18C21.71,4.74 21.53,5.26 21.18,5.73C20.84,6.21 20.42,6.66 19.91,7.09L19.27,7.61V7.63H21.85V9Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="editor.chain().focus().setHardBreak().run()"
                    title="hard break"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M10,11A4,4 0 0,1 6,7A4,4 0 0,1 10,3H18V5H16V21H14V5H12V21H10V11Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="
                        editor.chain().focus().unsetAllMarks().run();
                        editor.chain().focus().clearNodes().run();
                    "
                    title="Clear formatting"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M6,5V5.18L8.82,8H11.22L10.5,9.68L12.6,11.78L14.21,8H20V5H6M3.27,5L2,6.27L8.97,13.24L6.5,19H9.5L11.07,15.34L16.73,21L18,19.73L3.55,5.27L3.27,5Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
            <div class="flex p-1 border-r">
                <button
                    @click.prevent="editor.chain().focus().undo().run()"
                    title="Undo"
                    :disabled="!editor.can().chain().focus().undo().run()"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                        [
                            'disabled-text-gray',
                            'hover-disabled-bg-transparent',
                            'disabled-cursor-not-allowed',
                        ],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M12.5,8C9.85,8 7.45,9 5.6,10.6L2,7V16H11L7.38,12.38C8.77,11.22 10.54,10.5 12.5,10.5C16.04,10.5 19.05,12.81 20.1,16L22.47,15.22C21.08,11.03 17.15,8 12.5,8Z"
                            fill="currentColor" />
                    </svg>
                </button>
                <button
                    @click.prevent="editor.chain().focus().redo().run()"
                    title="Redo"
                    :disabled="!editor.can().chain().focus().redo().run()"
                    :class="[
                        'flex',
                        'flex-items-center',
                        'p-1',
                        'hover-bg-gray-3',
                        'bg-white',
                        [
                            'disabled-text-gray',
                            'hover-disabled-bg-transparent',
                            'disabled-cursor-not-allowed',
                        ],
                    ]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        viewBox="0 0 24 24">
                        <path
                            d="M18.4,10.6C16.55,9 14.15,8 11.5,8C6.85,8 2.92,11.03 1.54,15.22L3.9,16C4.95,12.81 7.95,10.5 11.5,10.5C13.45,10.5 15.23,11.22 16.62,12.38L13,16H22V7L18.4,10.6Z"
                            fill="currentColor" />
                    </svg>
                </button>
            </div>
        </div>
        <EditorContent
            :editor="editor"
            class="rounded-b-2 bg-white p-4 border-x border-b border-gray h-128 overflow-auto sm-editor" />
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from "vue";
import { useEditor, EditorContent, BubbleMenu, isActive } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Underline from "@tiptap/extension-underline";
import TextAlign from "@tiptap/extension-text-align";
import Highlight from "@tiptap/extension-highlight";
import { Info } from "../extensions/info";
import { Success } from "../extensions/success";
import { Warning } from "../extensions/warning";
import { Danger } from "../extensions/danger";
import Subscript from "@tiptap/extension-subscript";
import Superscript from "@tiptap/extension-superscript";
import Link from "@tiptap/extension-link";
import Image from "@tiptap/extension-image";
import { Small } from "../extensions/small";
import { openDialog } from "./SMDialog";
import SMDialogMedia from "./dialogs/SMDialogMedia.vue";
import { Media, MediaCollection } from "../helpers/api.types";
import { api } from "../helpers/api";
import { extractFileNameFromUrl } from "../helpers/url";
import { mediaGetVariantUrl } from "../helpers/media";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
});

const emits = defineEmits(["update:modelValue"]);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        TextAlign.configure({
            types: [
                "heading",
                "paragraph",
                "info",
                "success",
                "warning",
                "danger",
            ],
        }),
        Highlight,
        Info,
        Success,
        Warning,
        Danger,
        Small,
        Subscript,
        Superscript,
        Link.configure({
            openOnClick: false,
        }),
        Image,
        BubbleMenu,
    ],
    onUpdate: () => {
        emits("update:modelValue", editor.value.getHTML());
    },
});

const bubbleMenuShow = ({ editor, view, state, oldState, from, to }) => {
    return isActive(state, "image");
};

const updateNode = (event) => {
    if (event.target.value) {
        switch (event.target.value) {
            case "paragraph":
                editor.value.chain().focus().setParagraph().run();
                break;
            case "small":
                editor.value.chain().focus().setSmall().run();
                break;
            case "h1":
                editor.value.chain().focus().setHeading({ level: 1 }).run();
                break;
            case "h2":
                editor.value.chain().focus().setHeading({ level: 2 }).run();
                break;
            case "h3":
                editor.value.chain().focus().setHeading({ level: 3 }).run();
                break;
            case "h4":
                editor.value.chain().focus().setHeading({ level: 4 }).run();
                break;
            case "h5":
                editor.value.chain().focus().setHeading({ level: 5 }).run();
                break;
            case "h6":
                editor.value.chain().focus().setHeading({ level: 6 }).run();
                break;
            case "info":
                editor.value.chain().focus().toggleInfo().run();
                break;
            case "success":
                editor.value.chain().focus().toggleSuccess().run();
                break;
            case "warning":
                editor.value.chain().focus().toggleWarning().run();
                break;
            case "danger":
                editor.value.chain().focus().toggleDanger().run();
                break;
        }
    }
};

const setLink = () => {
    const previousUrl = editor.value.getAttributes("link").href;
    const url = window.prompt("URL", previousUrl);

    // cancelled
    if (url === null) {
        return;
    }

    // empty
    if (url === "") {
        editor.value.chain().focus().extendMarkRange("link").unsetLink().run();
        return;
    }

    // update link
    editor.value
        .chain()
        .focus()
        .extendMarkRange("link")
        .setLink({ href: url })
        .run();
};

const setImage = async () => {
    let result = await openDialog(SMDialogMedia, {
        allowUpload: true,
        allowUrl: true,
    });
    if (result) {
        const mediaResult = result as Media;
        editor.value
            .chain()
            .focus()
            .setImage({
                src: mediaResult.url,
                title: mediaResult.title,
                alt: mediaResult.description,
            })
            .run();
    }
};

onBeforeUnmount(() => {
    editor.value.destroy();
});

watch(
    () => props.modelValue,
    (newValue) => {
        const isSame = editor.value.getHTML() === newValue;

        if (isSame) {
            return;
        }

        editor.value.commands.setContent(newValue, false);
    },
);

const getImageSize = async () => {
    let size = "default";

    if (!editor.value.view.state.selection.node) {
        return "unknown";
    }

    const src = editor.value.view.state.selection.node.attrs.src;
    const fileName = extractFileNameFromUrl(src);

    let r = await api
        .get({
            url: "/media",
            params: {
                variants: extractFileNameFromUrl(src),
            },
        })
        .then((result) => {
            if (result.data) {
                const data = result.data as MediaCollection;
                if (data.media.length > 0 && data.media[0].variants) {
                    for (const [key, value] of Object.entries(
                        data.media[0].variants,
                    )) {
                        if (value === fileName) {
                            size = key;
                            console.log(size);
                            break;
                        }
                    }
                }
            }
            console.log("final", size);
            return size;
        })
        .catch((error) => {
            console.log(error);
            return "xx";
        });

    console.log(r);

    return size;
};

const setImageSize = (size: string): void => {
    const { selection } = editor.value.view.state;
    const src = editor.value.view.state.selection.node.attrs.src;

    api.get({
        url: "/media",
        params: {
            variants: extractFileNameFromUrl(src),
        },
    })
        .then((result) => {
            console.log(result);
            /*
            large
            medium
            scaled
            small,
            thumb
            xlarge
            xxlarge
            */
            const newSrc = mediaGetVariantUrl(result.data.media[0], size);
            const transaction = editor.value.view.state.tr.setNodeMarkup(
                selection.from,
                undefined,
                { src: newSrc },
            );
            editor.value.view.dispatch(transaction);
        })
        .catch((error) => {
            console.log(error);
        });
};
</script>

<style lang="scss">
.tippy-content div {
    display: flex !important;
    justify-content: center;
    align-items: center;

    button {
        color: rgba(255, 255, 255, 1);
        appearance: none;
        background-color: rgba(0, 0, 0, 1);
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;

        &:hover {
            background-color: rgba(60, 60, 60, 1);
        }

        &:first-child {
            border-top-left-radius: 0.5rem;
            border-bottom-left-radius: 0.5rem;
        }

        &:last-child {
            border-top-right-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
    }
}

// .tippy-arrow {
//     height: 0.75rem;
//     width: 0.75rem;
//     z-index: -1;

//     &::after {
//         display: block;
//         content: "";
//         background-color: rgba(0, 0, 0, 1);
//         height: 100%;
//         width: 100%;
//         transform: translateY(-50%) rotate(45deg);
//     }
// }
</style>
