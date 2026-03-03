import Link from "@tiptap/extension-link";
import Image from "@tiptap/extension-image";
import {Table} from "@tiptap/extension-table";
import TableRow from "@tiptap/extension-table-row";
import TableHeader from "@tiptap/extension-table-header";
import TableCell from "@tiptap/extension-table-cell";
import {CellSelection, cellAround} from "prosemirror-tables";
import {Editor, mergeAttributes} from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";
import Underline from "@tiptap/extension-underline";
import Highlight from "@tiptap/extension-highlight";
import TextAlign from "@tiptap/extension-text-align";
import Typography from "@tiptap/extension-typography";
import {ColorHighlighter} from "./ColourHighter.js";
import {SmileyReplacer} from "./SmileyReplacer.js";
import {Small} from "./Small.js";
import {ExtraSmall} from "./ExtraSmall.js";
import {Box} from "./Box.js";
import {Spoiler} from "./Spoiler.js";

let cachedLinkOptions = null;

const fetchLinkOptions = async (linkOptionsUrl) => {
    if (cachedLinkOptions !== null) {
        return cachedLinkOptions;
    }

    const response = await axios.get(linkOptionsUrl);
    cachedLinkOptions = Array.isArray(response?.data?.pages) ? response.data.pages : [];

    return cachedLinkOptions;
}

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const coerceInteger = (value, fallback = 0) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) ? parsed : fallback;
}

const isExternalUrl = (value) => {
    const url = String(value || '').trim();

    if (url === '') {
        return false;
    }

    if (url.startsWith('/')) {
        return false;
    }

    return /^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(url) || /^[a-z][a-z0-9+.-]*:/i.test(url);
}

const getImageElement = (element) => {
    if (!element) {
        return null;
    }

    if (element.tagName === 'IMG') {
        return element;
    }

    return element.querySelector('img');
}

const getAttributeFromElementOrImage = (element, attribute, fallback = null) => {
    if (!element) {
        return fallback;
    }

    const direct = element.getAttribute(attribute);
    if (direct !== null && direct !== '') {
        return direct;
    }

    const imageElement = getImageElement(element);
    const nested = imageElement?.getAttribute(attribute);

    return nested !== null && nested !== '' ? nested : fallback;
}

const getCaptionText = (element) => {
    if (!element) {
        return '';
    }

    if (element.tagName === 'FIGURE') {
        return String(element.querySelector('figcaption')?.textContent || '').trim();
    }

    if (element.getAttribute('data-type') === 'sm-image') {
        return String(element.querySelector('.sm-editor-image__caption')?.textContent || element.getAttribute('data-caption') || '').trim();
    }

    return String(element.getAttribute('data-caption') || '').trim();
}

const clampPercent = (value, min = 0, max = 100) => Math.max(min, Math.min(max, Number(value) || 0));

const getCropValues = (attrs = {}) => {
    const crop = {
        top: clampPercent(attrs.cropTop, 0, 95),
        right: clampPercent(attrs.cropRight, 0, 95),
        bottom: clampPercent(attrs.cropBottom, 0, 95),
        left: clampPercent(attrs.cropLeft, 0, 95),
    };

    const maxTotal = 90;
    if (crop.left + crop.right > maxTotal) {
        const scale = maxTotal / (crop.left + crop.right);
        crop.left = Math.round(crop.left * scale);
        crop.right = Math.round(crop.right * scale);
    }

    if (crop.top + crop.bottom > maxTotal) {
        const scale = maxTotal / (crop.top + crop.bottom);
        crop.top = Math.round(crop.top * scale);
        crop.bottom = Math.round(crop.bottom * scale);
    }

    return crop;
}

const getNaturalSize = (attrs = {}) => ({
    width: Math.max(0, coerceInteger(attrs.naturalWidth, 0)),
    height: Math.max(0, coerceInteger(attrs.naturalHeight, 0)),
});

const withNaturalSize = (attrs = {}, width = 0, height = 0) => ({
    ...attrs,
    naturalWidth: Math.max(getNaturalSize(attrs).width, Math.max(0, coerceInteger(width, 0))),
    naturalHeight: Math.max(getNaturalSize(attrs).height, Math.max(0, coerceInteger(height, 0))),
});

const imageFrameStyleFromAttrs = (attrs = {}, { showFullImage = false } = {}) => {
    const styles = ['position:relative', 'display:block', 'width:100%'];
    const natural = getNaturalSize(attrs);
    if (showFullImage) {
        if (natural.width > 0 && natural.height > 0) {
            styles.push(`aspect-ratio:${natural.width}/${natural.height}`);
        }
    } else {
        const crop = getCropValues(attrs);
        const visibleWidthRatio = (100 - crop.left - crop.right) / 100;
        const visibleHeightRatio = (100 - crop.top - crop.bottom) / 100;

        if (visibleWidthRatio > 0 && visibleHeightRatio > 0 && natural.width > 0 && natural.height > 0) {
            styles.push(`aspect-ratio:${(natural.width * visibleWidthRatio)}/${(natural.height * visibleHeightRatio)}`);
        }
    }

    return styles.join('; ');
}

const imageLayoutStyles = (attrs = {}) => {
    const styles = [];
    const width = Math.max(5, Math.min(100, coerceInteger(attrs.widthPercent, 100)));
    const alignment = String(attrs.alignment || 'center');
    const crop = getCropValues(attrs);
    const visibleWidthRatio = Math.max(0.05, (100 - crop.left - crop.right) / 100);
    const renderedWidth = width * visibleWidthRatio;
    const margins = {
        top: Math.max(0, coerceInteger(attrs.marginTop, 12)),
        right: Math.max(0, coerceInteger(attrs.marginRight, 12)),
        bottom: Math.max(0, coerceInteger(attrs.marginBottom, 12)),
        left: Math.max(0, coerceInteger(attrs.marginLeft, 12)),
    };
    const maxWidth = Math.max(0, margins.left + margins.right);

    styles.push(`width:${renderedWidth}%`, 'max-width:calc(100% - ' + maxWidth + 'px)', 'height:auto', 'box-sizing:border-box');
    styles.push(`margin-top:${margins.top}px`, `margin-bottom:${margins.bottom}px`);

    if (alignment === 'left') {
        styles.push('display:block', 'float:left', 'clear:none', `margin-left:${margins.left}px`, `margin-right:${margins.right}px`);
    } else if (alignment === 'right') {
        styles.push('display:block', 'float:right', 'clear:none', `margin-left:${margins.left}px`, `margin-right:${margins.right}px`);
    } else if (alignment === 'center') {
        styles.push('display:block', 'margin-left:auto', 'margin-right:auto');
    } else {
        styles.push('display:block', 'margin-left:auto', 'margin-right:auto');
    }

    return styles.join('; ');
}

const imageStyleFromAttrs = (attrs = {}) => `${imageLayoutStyles(attrs)}; object-fit:${attrs.cropMode === 'cover' ? 'cover' : 'contain'}`
const imageInnerStyleFromAttrs = (attrs = {}, { showFullImage = false } = {}) => {
    if (showFullImage) {
        return [
            'display:block',
            'max-width:100%',
            'width:100%',
            'height:100%',
            'object-fit:contain',
            'transform:none',
            'transform-origin:top left',
        ].join('; ');
    }

    const crop = getCropValues(attrs);
    const visibleWidthRatio = Math.max(0.05, (100 - crop.left - crop.right) / 100);
    const widthPercent = 100 / visibleWidthRatio;

    return [
        'display:block',
        'max-width:none',
        `width:${widthPercent}%`,
        'height:auto',
        `transform:translate(${-crop.left}%, ${-crop.top}%)`,
        'transform-origin:top left',
    ].join('; ');
}

const imageWrapperStyleFromAttrs = (attrs = {}) => imageLayoutStyles(attrs)
const cropShadeStyle = (edge, crop) => {
    if (edge === 'top') {
        return `left:0; top:0; width:100%; height:${crop.top}%;`;
    }

    if (edge === 'right') {
        return `top:${crop.top}%; right:0; width:${crop.right}%; height:${Math.max(0, 100 - crop.top - crop.bottom)}%;`;
    }

    if (edge === 'bottom') {
        return `left:0; bottom:0; width:100%; height:${crop.bottom}%;`;
    }

    return `top:${crop.top}%; left:0; width:${crop.left}%; height:${Math.max(0, 100 - crop.top - crop.bottom)}%;`;
}

const cropFocusStyle = (crop) => `left:${crop.left}%; top:${crop.top}%; width:${Math.max(0, 100 - crop.left - crop.right)}%; height:${Math.max(0, 100 - crop.top - crop.bottom)}%;`

const getCellSelectionAnchor = (state) => {
    if (state.selection instanceof CellSelection) {
        return state.selection.$anchorCell;
    }

    return cellAround(state.selection.$anchor);
}

const getResolvedCellFromDom = (view, cellElement) => {
    if (!(cellElement instanceof HTMLElement)) {
        return null;
    }

    const candidates = [
        [cellElement, 0],
        [cellElement.firstChild, 0],
        [cellElement.lastChild, 0],
    ];

    for (const [node, offset] of candidates) {
        if (!node) {
            continue;
        }

        try {
            const pos = view.posAtDOM(node, offset);
            const $cell = cellAround(view.state.doc.resolve(pos));

            if ($cell) {
                return $cell;
            }
        } catch (_error) {
            // Ignore DOM positions that ProseMirror can't resolve.
        }
    }

    return null;
}

const applyCellSelection = (view, $anchorCell, $headCell) => {
    if (!$anchorCell || !$headCell) {
        return false;
    }

    const selection = new CellSelection($anchorCell, $headCell);
    const currentSelection = view.state.selection;

    if (
        currentSelection instanceof CellSelection
        && currentSelection.$anchorCell.pos === selection.$anchorCell.pos
        && currentSelection.$headCell.pos === selection.$headCell.pos
    ) {
        return false;
    }

    view.dispatch(view.state.tr.setSelection(selection));

    return true;
}

const isCellHandleHit = (event, cellElement) => {
    if (!(cellElement instanceof HTMLElement)) {
        return false;
    }

    const rect = cellElement.getBoundingClientRect();
    const handleSize = Math.min(18, Math.max(10, Math.min(rect.width, rect.height) * 0.22));
    const offsetX = event.clientX - rect.left;
    const offsetY = event.clientY - rect.top;

    return offsetX >= 0 && offsetY >= 0 && offsetX <= handleSize && offsetY <= handleSize;
}

const createImageNodeView = ({node, editor, getPos}) => {
    const wrapper = document.createElement('span');
    wrapper.className = 'tiptap-image-node';
    wrapper.contentEditable = 'false';

    const frame = document.createElement('span');
    frame.className = 'tiptap-image-node__frame';

    const media = document.createElement('span');
    media.className = 'tiptap-image-node__media';

    const image = document.createElement('img');
    image.className = 'tiptap-image-node__image';

    const caption = document.createElement('span');
    caption.className = 'tiptap-image-node__caption';

    const actions = document.createElement('div');
    actions.className = 'tiptap-image-node__actions';

    const editButton = document.createElement('button');
    editButton.type = 'button';
    editButton.className = 'tiptap-image-node__button';
    editButton.textContent = 'Edit';

    const resizeHandle = document.createElement('button');
    resizeHandle.type = 'button';
    resizeHandle.className = 'tiptap-image-node__resize';
    resizeHandle.setAttribute('aria-label', 'Resize image');

    actions.append(editButton);
    media.append(image, actions, resizeHandle);
    frame.append(media, caption);
    wrapper.append(frame);

    let isSelected = false;
    let dragState = null;

    const currentPos = () => typeof getPos === 'function' ? getPos() : null;

    const selectImageNode = () => {
        const pos = currentPos();
        if (typeof pos !== 'number') {
            return;
        }

        editor.chain().focus().setNodeSelection(pos).run();
    };

    const syncFromNode = (currentNode) => {
        const layoutAttrs = withNaturalSize(currentNode.attrs, image.naturalWidth, image.naturalHeight);

        image.src = currentNode.attrs.src || '';
        image.alt = currentNode.attrs.alt || '';
        image.title = currentNode.attrs.title || '';
        caption.textContent = String(currentNode.attrs.caption || '').trim();
        caption.hidden = caption.textContent === '';
        wrapper.style.cssText = imageWrapperStyleFromAttrs(layoutAttrs);
        frame.style.cssText = 'display:block;';
        media.style.cssText = `${imageFrameStyleFromAttrs(layoutAttrs)}; overflow:hidden; border-radius:0.375rem;`;
        image.style.cssText = imageInnerStyleFromAttrs(layoutAttrs);
        wrapper.classList.toggle('selected', isSelected);

        if ((!currentNode.attrs.naturalWidth || !currentNode.attrs.naturalHeight) && layoutAttrs.naturalWidth > 0 && layoutAttrs.naturalHeight > 0) {
            editor.commands.updateAttributes('image', {
                naturalWidth: layoutAttrs.naturalWidth,
                naturalHeight: layoutAttrs.naturalHeight,
            });
        }
    };

    image.addEventListener('load', () => {
        if (image.naturalWidth > 0 && image.naturalHeight > 0) {
            editor.commands.updateAttributes('image', {
                naturalWidth: image.naturalWidth,
                naturalHeight: image.naturalHeight,
            });
        }
    });

    const beginResize = (event) => {
        event.preventDefault();
        event.stopPropagation();
        selectImageNode();

        const availableWidth = wrapper.parentElement?.clientWidth || editor.view.dom.clientWidth || 1;
        const startingWidth = media.getBoundingClientRect().width;
        const crop = getCropValues(node.attrs);
        const visibleWidthRatio = Math.max(0.05, (100 - crop.left - crop.right) / 100);

        dragState = {
            type: 'resize',
            startX: event.clientX,
            startWidth: startingWidth,
            availableWidth,
            visibleWidthRatio,
        };

        document.body.classList.add('image-resize-active');
    };

    const continueResize = (event) => {
        if (!dragState || dragState.type !== 'resize') {
            return;
        }

        const widthPixels = Math.max(32, dragState.startWidth + (event.clientX - dragState.startX));
        const widthPercent = Math.max(5, Math.min(100, Math.round(((widthPixels / dragState.availableWidth) * 100) / dragState.visibleWidthRatio)));

        wrapper.style.cssText = imageWrapperStyleFromAttrs({
            ...node.attrs,
            widthPercent,
        });
    };

    const endResize = (event) => {
        if (!dragState || dragState.type !== 'resize') {
            return;
        }

        const widthPixels = Math.max(32, dragState.startWidth + (event.clientX - dragState.startX));
        const widthPercent = Math.max(5, Math.min(100, Math.round(((widthPixels / dragState.availableWidth) * 100) / dragState.visibleWidthRatio)));
        dragState = null;
        document.body.classList.remove('image-resize-active');
        editor.commands.updateAttributes('image', { widthPercent });
    };

    wrapper.addEventListener('click', (event) => {
        event.preventDefault();
        selectImageNode();
    });

    wrapper.addEventListener('dblclick', (event) => {
        event.preventDefault();
        selectImageNode();
        editorConfigureImage(editor);
    });

    editButton.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        selectImageNode();
        editorConfigureImage(editor);
    });

    resizeHandle.addEventListener('mousedown', beginResize);
    const handlePointerMove = (event) => {
        continueResize(event);
    };
    const handlePointerUp = (event) => {
        if (dragState?.type === 'resize') {
            endResize(event);
        }
    };
    window.addEventListener('mousemove', handlePointerMove);
    window.addEventListener('mouseup', handlePointerUp);

    syncFromNode(node);

    return {
        dom: wrapper,
        update(updatedNode) {
            if (updatedNode.type.name !== node.type.name) {
                return false;
            }

            node = updatedNode;
            syncFromNode(updatedNode);
            return true;
        },
        selectNode() {
            isSelected = true;
            wrapper.classList.add('selected');
        },
        deselectNode() {
            isSelected = false;
            wrapper.classList.remove('selected');
        },
        destroy() {
            window.removeEventListener('mousemove', handlePointerMove);
            window.removeEventListener('mouseup', handlePointerUp);
            document.body.classList.remove('image-resize-active');
        },
    };
}

const CustomImage = Image.extend({
    inline() {
        return true;
    },

    group() {
        return 'inline';
    },

    addAttributes() {
        return {
            ...this.parent?.(),
            src: {
                default: null,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element);
                    return imageElement?.getAttribute('src') || element.getAttribute('src') || null;
                },
                renderHTML: (attributes) => attributes.src ? { src: attributes.src } : {},
            },
            alt: {
                default: null,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element);
                    return imageElement?.getAttribute('alt') || element.getAttribute('alt') || null;
                },
                renderHTML: (attributes) => attributes.alt ? { alt: attributes.alt } : {},
            },
            title: {
                default: null,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element);
                    return imageElement?.getAttribute('title') || element.getAttribute('title') || null;
                },
                renderHTML: (attributes) => attributes.title ? { title: attributes.title } : {},
            },
            widthPercent: {
                default: 100,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element) || element;
                    const width = getAttributeFromElementOrImage(element, 'data-width-percent')
                        || imageElement.style.width.replace('%', '');
                    return Math.max(5, Math.min(100, coerceInteger(width, 100)));
                },
                renderHTML: (attributes) => ({
                    'data-width-percent': attributes.widthPercent,
                }),
            },
            alignment: {
                default: 'center',
                parseHTML: (element) => {
                    const alignment = getAttributeFromElementOrImage(element, 'data-alignment', 'center');
                    return ['left', 'center', 'right'].includes(alignment) ? alignment : 'center';
                },
                renderHTML: (attributes) => ({
                    'data-alignment': attributes.alignment,
                }),
            },
            caption: {
                default: '',
                parseHTML: (element) => getCaptionText(element),
                renderHTML: () => ({}),
            },
            naturalWidth: {
                default: 0,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element) || element;
                    return coerceInteger(getAttributeFromElementOrImage(element, 'data-natural-width') || imageElement.getAttribute('width'), 0);
                },
                renderHTML: (attributes) => ({
                    'data-natural-width': attributes.naturalWidth,
                }),
            },
            naturalHeight: {
                default: 0,
                parseHTML: (element) => {
                    const imageElement = getImageElement(element) || element;
                    return coerceInteger(getAttributeFromElementOrImage(element, 'data-natural-height') || imageElement.getAttribute('height'), 0);
                },
                renderHTML: (attributes) => ({
                    'data-natural-height': attributes.naturalHeight,
                }),
            },
            cropTop: {
                default: 0,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-crop-top'), 0),
                renderHTML: (attributes) => ({
                    'data-crop-top': attributes.cropTop,
                }),
            },
            cropRight: {
                default: 0,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-crop-right'), 0),
                renderHTML: (attributes) => ({
                    'data-crop-right': attributes.cropRight,
                }),
            },
            cropBottom: {
                default: 0,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-crop-bottom'), 0),
                renderHTML: (attributes) => ({
                    'data-crop-bottom': attributes.cropBottom,
                }),
            },
            cropLeft: {
                default: 0,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-crop-left'), 0),
                renderHTML: (attributes) => ({
                    'data-crop-left': attributes.cropLeft,
                }),
            },
            textWrap: {
                default: false,
                parseHTML: (element) => getAttributeFromElementOrImage(element, 'data-text-wrap') === 'true',
                renderHTML: (attributes) => ({
                    'data-text-wrap': attributes.textWrap ? 'true' : 'false',
                }),
            },
            marginTop: {
                default: 12,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-margin-top'), 12),
                renderHTML: (attributes) => ({
                    'data-margin-top': attributes.marginTop,
                }),
            },
            marginRight: {
                default: 12,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-margin-right'), 12),
                renderHTML: (attributes) => ({
                    'data-margin-right': attributes.marginRight,
                }),
            },
            marginBottom: {
                default: 12,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-margin-bottom'), 12),
                renderHTML: (attributes) => ({
                    'data-margin-bottom': attributes.marginBottom,
                }),
            },
            marginLeft: {
                default: 12,
                parseHTML: (element) => coerceInteger(getAttributeFromElementOrImage(element, 'data-margin-left'), 12),
                renderHTML: (attributes) => ({
                    'data-margin-left': attributes.marginLeft,
                }),
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'span[data-type="sm-image"]',
                priority: 1000,
            },
            {
                tag: 'figure[data-type="sm-image"]',
                priority: 1000,
            },
            {
                tag: 'img[src]',
                getAttrs: (element) => {
                    if (element.closest('span[data-type="sm-image"], figure[data-type="sm-image"]')) {
                        return false;
                    }

                    return {};
                },
            },
        ];
    },

    renderHTML({ node, HTMLAttributes }) {
        const attrs = node.attrs || {};
        const caption = String(attrs.caption || '').trim();
        const figureAttributes = mergeAttributes(this.options.HTMLAttributes, {
            'data-type': 'sm-image',
            'data-width-percent': attrs.widthPercent,
            'data-alignment': attrs.alignment,
            'data-margin-top': attrs.marginTop,
            'data-margin-right': attrs.marginRight,
            'data-margin-bottom': attrs.marginBottom,
            'data-margin-left': attrs.marginLeft,
            'data-natural-width': attrs.naturalWidth,
            'data-natural-height': attrs.naturalHeight,
            'data-crop-top': attrs.cropTop,
            'data-crop-right': attrs.cropRight,
            'data-crop-bottom': attrs.cropBottom,
            'data-crop-left': attrs.cropLeft,
            'data-caption': caption,
            class: 'sm-editor-image',
            style: imageLayoutStyles(attrs),
        });

        const imageAttributes = mergeAttributes(HTMLAttributes, {
            src: attrs.src,
            alt: attrs.alt,
            title: attrs.title,
            style: imageInnerStyleFromAttrs(attrs),
        });

        const children = [
            ['span', { class: 'sm-editor-image__frame', style: imageFrameStyleFromAttrs(attrs) },
                ['img', mergeAttributes(this.options.HTMLAttributes, imageAttributes)],
            ],
        ];

        if (caption !== '') {
            children.push(['span', { class: 'sm-editor-image__caption' }, caption]);
        }

        return ['span', figureAttributes, ...children];
    },

    addNodeView() {
        return (props) => createImageNodeView(props);
    },
});

const editorToggleLink = async (editor, linkOptionsUrl = '/link-options') => {
    const previousUrl = String(editor.getAttributes('link').href || '').trim();
    const previousTarget = String(editor.getAttributes('link').target || '').trim();
    const previousOpenInNewWindow = editor.getAttributes('link').openInNewWindow;
    const pages = await fetchLinkOptions(linkOptionsUrl);
    const hasMatchingPage = pages.some((page) => page.path === previousUrl);
    const selectedMode = hasMatchingPage || previousUrl === '' ? 'internal' : 'manual';
    const selectOptions = pages.map((page) => {
        const selected = page.path === previousUrl ? ' selected' : '';

        return `<option value="${escapeHtml(page.path)}"${selected}>${escapeHtml(page.title)} (${escapeHtml(page.path)})</option>`;
    }).join('');

    const result = await Swal.fire({
        title: 'Insert Link',
        focusConfirm: false,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Apply',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'sm-editor-dialog',
        },
        html: `
            <div class="space-y-4 text-left">
                <label class="flex items-start gap-3">
                    <input type="radio" name="link-mode" value="internal" ${selectedMode === 'internal' ? 'checked' : ''}>
                    <span>
                        <span class="block font-semibold">Select an existing page</span>
                        <span class="block text-sm text-gray-500">Recommended for links within this site.</span>
                    </span>
                </label>
                <div id="link-internal-container">
                    <select id="link-internal-select" class="sm-editor-field">
                        <option value="">Choose a page</option>
                        ${selectOptions}
                    </select>
                </div>
                <label class="flex items-start gap-3">
                    <input type="radio" name="link-mode" value="manual" ${selectedMode === 'manual' ? 'checked' : ''}>
                    <span>
                        <span class="block font-semibold">Enter a URL manually</span>
                        <span class="block text-sm text-gray-500">Use this for external sites or custom URLs.</span>
                    </span>
                </label>
                <input id="link-manual-url" class="sm-editor-field" placeholder="https://example.com" value="${escapeHtml(hasMatchingPage ? '' : previousUrl)}">
                <label class="flex items-start gap-3">
                    <input id="link-new-window" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color" ${previousOpenInNewWindow === true || (previousOpenInNewWindow === null && (previousTarget === '_blank' || (previousTarget === '' && isExternalUrl(previousUrl)))) ? 'checked' : ''}>
                    <span>
                        <span class="block font-semibold">Open in a new window</span>
                        <span class="block text-sm text-gray-500">Enabled by default for external links.</span>
                    </span>
                </label>
                <button id="link-clear-button" type="button" class="text-sm text-red-600 hover:text-red-700">Remove link</button>
            </div>
        `,
        didOpen: () => {
            const popup = Swal.getPopup();
            if (!popup) {
                return;
            }

            const internalSelect = popup.querySelector('#link-internal-select');
            const manualInput = popup.querySelector('#link-manual-url');
            const newWindowInput = popup.querySelector('#link-new-window');
            const updateNewWindowDefault = () => {
                if (!newWindowInput) {
                    return;
                }

                if (previousOpenInNewWindow === true) {
                    newWindowInput.checked = true;
                    return;
                }

                if (previousOpenInNewWindow === false) {
                    newWindowInput.checked = false;
                    return;
                }

                const mode = popup.querySelector('input[name="link-mode"]:checked')?.value || 'internal';
                const candidateUrl = mode === 'internal'
                    ? String(internalSelect?.value || '').trim()
                    : String(manualInput?.value || '').trim();

                newWindowInput.checked = isExternalUrl(candidateUrl);
            };

            popup.querySelectorAll('input[name="link-mode"]').forEach((radio) => {
                radio.addEventListener('change', updateNewWindowDefault);
            });
            internalSelect?.addEventListener('change', updateNewWindowDefault);
            manualInput?.addEventListener('input', updateNewWindowDefault);
            popup.querySelector('#link-clear-button')?.addEventListener('click', () => {
                popup.dataset.clearLink = '1';
                Swal.clickConfirm();
            });
            updateNewWindowDefault();
        },
        preConfirm: () => {
            const popup = Swal.getPopup();
            if (popup?.dataset.clearLink === '1') {
                return { clear: true };
            }

            const mode = popup?.querySelector('input[name="link-mode"]:checked')?.value || 'internal';
            const internalUrl = String(popup?.querySelector('#link-internal-select')?.value || '').trim();
            const manualUrl = String(popup?.querySelector('#link-manual-url')?.value || '').trim();
            const url = mode === 'internal' ? internalUrl : manualUrl;

            if (url === '') {
                Swal.showValidationMessage('Select a page or enter a URL.');
                return false;
            }

            return {
                url,
                openInNewWindow: Boolean(popup?.querySelector('#link-new-window')?.checked),
            };
        }
    });

    if (!result.isConfirmed) {
        return;
    }

    if (result.value?.clear) {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    const url = String(result.value?.url || '').trim();
    if (url === '') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    const openInNewWindow = Boolean(result.value?.openInNewWindow);
    editor.chain().focus().extendMarkRange('link').setLink({
        href: url,
        target: openInNewWindow ? '_blank' : null,
        rel: openInNewWindow ? 'noopener noreferrer' : null,
        openInNewWindow,
    }).run();
}

const CustomLink = Link.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            openInNewWindow: {
                default: null,
                parseHTML: (element) => {
                    const explicit = element.getAttribute('data-open-in-new-window');
                    if (explicit === 'true') {
                        return true;
                    }
                    if (explicit === 'false') {
                        return false;
                    }

                    return element.getAttribute('target') === '_blank' ? true : null;
                },
                renderHTML: (attributes) => {
                    if (attributes.openInNewWindow === true) {
                        return { 'data-open-in-new-window': 'true' };
                    }

                    if (attributes.openInNewWindow === false) {
                        return { 'data-open-in-new-window': 'false' };
                    }

                    return {};
                },
            },
        }
    },

    addKeyboardShortcuts() {
        return {
            'Mod-k': () => editorToggleLink(this.editor),
            'Mod-Alt-4': () => this.editor.chain().setSmall().focus().run(),
            'Mod-Alt-5': () => this.editor.chain().setExtraSmall().focus().run(),
        }
    }
});

const editorInsertImage = (editor) => {
    SMMediaPicker.open([], {
        require_mime_type: 'image/*',
        allow_multiple: false,
        allow_uploads: true,
    }, (value) => {
        if (!value) {
            return;
        }

        SM.mediaDetails(value, (details) => {
            if (!details || !details.url) {
                return;
            }

            editor.chain().focus().setImage({
                src: details.url,
                alt: details.title || details.name || '',
                title: details.title || details.name || '',
                caption: '',
                widthPercent: 100,
                alignment: 'center',
                textWrap: false,
                marginTop: 12,
                marginRight: 12,
                marginBottom: 12,
                marginLeft: 12,
            }).run();
        });
    });
}

const editorConfigureImage = async (editor) => {
    const attributes = editor.getAttributes('image');
    if (!attributes?.src) {
        return;
    }

    const crop = getCropValues(attributes);
    const detectedNaturalSize = {
        width: Math.max(0, coerceInteger(attributes.naturalWidth, 0)),
        height: Math.max(0, coerceInteger(attributes.naturalHeight, 0)),
    };
    let continueCropDrag = null;
    let endCropDrag = null;

    const result = await Swal.fire({
        title: 'Edit Image',
        focusConfirm: false,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Apply',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'sm-editor-dialog',
        },
        html: `
            <div class="space-y-4 text-left">
                <div class="sm-editor-tabs" role="tablist" aria-label="Image editor tabs">
                    <button type="button" class="sm-editor-tab is-active" data-image-tab-button="details" role="tab" aria-selected="true">Details</button>
                    <button type="button" class="sm-editor-tab" data-image-tab-button="crop" role="tab" aria-selected="false">Crop</button>
                </div>
                <div data-image-tab-panel="details" class="space-y-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Width</span>
                        <input id="image-width" type="range" min="5" max="100" step="5" value="${escapeHtml(attributes.widthPercent || 100)}" class="w-full">
                        <span id="image-width-output" class="mt-1 block text-xs text-gray-500">${escapeHtml(attributes.widthPercent || 100)}%</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Position</span>
                        <select id="image-alignment" class="sm-editor-field">
                            <option value="left"${attributes.alignment === 'left' ? ' selected' : ''}>Left</option>
                            <option value="center"${attributes.alignment === 'center' ? ' selected' : ''}>Center</option>
                            <option value="right"${attributes.alignment === 'right' ? ' selected' : ''}>Right</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium">Caption</span>
                        <input id="image-caption" type="text" value="${escapeHtml(attributes.caption || '')}" class="sm-editor-field" placeholder="Optional caption">
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Margin Top (px)</span>
                            <input id="image-margin-top" type="number" min="0" step="1" value="${escapeHtml(attributes.marginTop ?? 12)}" class="sm-editor-field">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Margin Right (px)</span>
                            <input id="image-margin-right" type="number" min="0" step="1" value="${escapeHtml(attributes.marginRight ?? 12)}" class="sm-editor-field">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Margin Bottom (px)</span>
                            <input id="image-margin-bottom" type="number" min="0" step="1" value="${escapeHtml(attributes.marginBottom ?? 12)}" class="sm-editor-field">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Margin Left (px)</span>
                            <input id="image-margin-left" type="number" min="0" step="1" value="${escapeHtml(attributes.marginLeft ?? 12)}" class="sm-editor-field">
                        </label>
                    </div>
                </div>
                <div data-image-tab-panel="crop" class="space-y-3 hidden">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">Crop</div>
                            <div class="text-xs text-gray-500">Adjust the visible area with live preview.</div>
                        </div>
                        <button id="image-crop-reset" type="button" class="text-sm text-primary-color hover:text-primary-color-dark">Reset crop</button>
                    </div>
                    <div id="image-crop-preview" class="sm-image-crop-preview">
                        <img id="image-crop-preview-image" src="${escapeHtml(attributes.src)}" alt="${escapeHtml(attributes.alt || '')}" class="sm-image-crop-preview__image">
                        <div id="image-crop-preview-top" class="sm-image-crop-preview__shade"></div>
                        <div id="image-crop-preview-right" class="sm-image-crop-preview__shade"></div>
                        <div id="image-crop-preview-bottom" class="sm-image-crop-preview__shade"></div>
                        <div id="image-crop-preview-left" class="sm-image-crop-preview__shade"></div>
                        <div id="image-crop-preview-focus" class="sm-image-crop-preview__focus">
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--n" data-crop-handle="n" aria-label="Resize crop top"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--ne" data-crop-handle="ne" aria-label="Resize crop top right"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--e" data-crop-handle="e" aria-label="Resize crop right"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--se" data-crop-handle="se" aria-label="Resize crop bottom right"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--s" data-crop-handle="s" aria-label="Resize crop bottom"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--sw" data-crop-handle="sw" aria-label="Resize crop bottom left"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--w" data-crop-handle="w" aria-label="Resize crop left"></button>
                            <button type="button" class="sm-image-crop-preview__handle sm-image-crop-preview__handle--nw" data-crop-handle="nw" aria-label="Resize crop top left"></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Crop Top</span>
                            <input id="image-crop-top" type="range" min="0" max="90" step="1" value="${Math.round(crop.top)}" class="w-full">
                            <span id="image-crop-top-output" class="mt-1 block text-xs text-gray-500">${Math.round(crop.top)}%</span>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Crop Right</span>
                            <input id="image-crop-right" type="range" min="0" max="90" step="1" value="${Math.round(crop.right)}" class="w-full">
                            <span id="image-crop-right-output" class="mt-1 block text-xs text-gray-500">${Math.round(crop.right)}%</span>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Crop Bottom</span>
                            <input id="image-crop-bottom" type="range" min="0" max="90" step="1" value="${Math.round(crop.bottom)}" class="w-full">
                            <span id="image-crop-bottom-output" class="mt-1 block text-xs text-gray-500">${Math.round(crop.bottom)}%</span>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium">Crop Left</span>
                            <input id="image-crop-left" type="range" min="0" max="90" step="1" value="${Math.round(crop.left)}" class="w-full">
                            <span id="image-crop-left-output" class="mt-1 block text-xs text-gray-500">${Math.round(crop.left)}%</span>
                        </label>
                    </div>
                </div>
            </div>
        `,
        didOpen: () => {
            const popup = Swal.getPopup();
            const widthInput = popup?.querySelector('#image-width');
            const widthOutput = popup?.querySelector('#image-width-output');
            const selectOnFocus = popup?.querySelectorAll('#image-caption, #image-margin-top, #image-margin-right, #image-margin-bottom, #image-margin-left');
            const tabButtons = popup?.querySelectorAll('[data-image-tab-button]');
            const tabPanels = popup?.querySelectorAll('[data-image-tab-panel]');
            const cropInputs = {
                top: popup?.querySelector('#image-crop-top'),
                right: popup?.querySelector('#image-crop-right'),
                bottom: popup?.querySelector('#image-crop-bottom'),
                left: popup?.querySelector('#image-crop-left'),
            };
            const cropOutputs = {
                top: popup?.querySelector('#image-crop-top-output'),
                right: popup?.querySelector('#image-crop-right-output'),
                bottom: popup?.querySelector('#image-crop-bottom-output'),
                left: popup?.querySelector('#image-crop-left-output'),
            };
            const cropPreview = {
                root: popup?.querySelector('#image-crop-preview'),
                image: popup?.querySelector('#image-crop-preview-image'),
                top: popup?.querySelector('#image-crop-preview-top'),
                right: popup?.querySelector('#image-crop-preview-right'),
                bottom: popup?.querySelector('#image-crop-preview-bottom'),
                left: popup?.querySelector('#image-crop-preview-left'),
                focus: popup?.querySelector('#image-crop-preview-focus'),
                handles: popup?.querySelectorAll('[data-crop-handle]'),
            };
            let cropDragState = null;
            const setActiveTab = (tab) => {
                tabButtons?.forEach((button) => {
                    const active = button.getAttribute('data-image-tab-button') === tab;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                tabPanels?.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.getAttribute('data-image-tab-panel') !== tab);
                });
            };
            const updateCropPreview = () => {
                const previewAttrs = withNaturalSize(attributes, detectedNaturalSize.width, detectedNaturalSize.height);
                const nextCrop = getCropValues({
                    cropTop: cropInputs.top?.value,
                    cropRight: cropInputs.right?.value,
                    cropBottom: cropInputs.bottom?.value,
                    cropLeft: cropInputs.left?.value,
                });
                cropInputs.top.value = String(Math.round(nextCrop.top));
                cropInputs.right.value = String(Math.round(nextCrop.right));
                cropInputs.bottom.value = String(Math.round(nextCrop.bottom));
                cropInputs.left.value = String(Math.round(nextCrop.left));

                cropOutputs.top.textContent = `${Math.round(nextCrop.top)}%`;
                cropOutputs.right.textContent = `${Math.round(nextCrop.right)}%`;
                cropOutputs.bottom.textContent = `${Math.round(nextCrop.bottom)}%`;
                cropOutputs.left.textContent = `${Math.round(nextCrop.left)}%`;

                cropPreview.root.style.cssText = imageFrameStyleFromAttrs(previewAttrs, { showFullImage: true });
                cropPreview.image.style.cssText = imageInnerStyleFromAttrs(previewAttrs, { showFullImage: true });
                cropPreview.top.style.cssText = cropShadeStyle('top', nextCrop);
                cropPreview.right.style.cssText = cropShadeStyle('right', nextCrop);
                cropPreview.bottom.style.cssText = cropShadeStyle('bottom', nextCrop);
                cropPreview.left.style.cssText = cropShadeStyle('left', nextCrop);
                cropPreview.focus.style.cssText = cropFocusStyle(nextCrop);
            };
            const beginCropDrag = (event) => {
                if (!cropPreview.root || !cropPreview.focus) {
                    return;
                }

                event.preventDefault();

                const rootRect = cropPreview.root.getBoundingClientRect();
                const currentCrop = getCropValues({
                    cropTop: cropInputs.top?.value,
                    cropRight: cropInputs.right?.value,
                    cropBottom: cropInputs.bottom?.value,
                    cropLeft: cropInputs.left?.value,
                });

                cropDragState = {
                    mode: 'move',
                    startX: event.clientX,
                    startY: event.clientY,
                    rootRect,
                    crop: currentCrop,
                    width: Math.max(0, 100 - currentCrop.left - currentCrop.right),
                    height: Math.max(0, 100 - currentCrop.top - currentCrop.bottom),
                };
            };
            const beginCropResize = (direction) => (event) => {
                if (!cropPreview.root) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const rootRect = cropPreview.root.getBoundingClientRect();
                const currentCrop = getCropValues({
                    cropTop: cropInputs.top?.value,
                    cropRight: cropInputs.right?.value,
                    cropBottom: cropInputs.bottom?.value,
                    cropLeft: cropInputs.left?.value,
                });

                cropDragState = {
                    mode: 'resize',
                    direction,
                    startX: event.clientX,
                    startY: event.clientY,
                    rootRect,
                    crop: currentCrop,
                };
            };
            continueCropDrag = (event) => {
                if (!cropDragState) {
                    return;
                }

                const deltaXPercent = ((event.clientX - cropDragState.startX) / cropDragState.rootRect.width) * 100;
                const deltaYPercent = ((event.clientY - cropDragState.startY) / cropDragState.rootRect.height) * 100;
                let nextTop = cropDragState.crop.top;
                let nextRight = cropDragState.crop.right;
                let nextBottom = cropDragState.crop.bottom;
                let nextLeft = cropDragState.crop.left;

                if (cropDragState.mode === 'move') {
                    const maxLeft = 100 - cropDragState.width;
                    const maxTop = 100 - cropDragState.height;
                    nextLeft = clampPercent(cropDragState.crop.left + deltaXPercent, 0, maxLeft);
                    nextTop = clampPercent(cropDragState.crop.top + deltaYPercent, 0, maxTop);
                    nextRight = Math.max(0, 100 - cropDragState.width - nextLeft);
                    nextBottom = Math.max(0, 100 - cropDragState.height - nextTop);
                } else {
                    const minSize = 5;
                    const direction = cropDragState.direction || '';

                    if (direction.includes('n')) {
                        nextTop = clampPercent(cropDragState.crop.top + deltaYPercent, 0, 100 - cropDragState.crop.bottom - minSize);
                    }
                    if (direction.includes('s')) {
                        nextBottom = clampPercent(cropDragState.crop.bottom - deltaYPercent, 0, 100 - nextTop - minSize);
                    }
                    if (direction.includes('w')) {
                        nextLeft = clampPercent(cropDragState.crop.left + deltaXPercent, 0, 100 - cropDragState.crop.right - minSize);
                    }
                    if (direction.includes('e')) {
                        nextRight = clampPercent(cropDragState.crop.right - deltaXPercent, 0, 100 - nextLeft - minSize);
                    }
                }

                cropInputs.left.value = String(Math.round(nextLeft));
                cropInputs.top.value = String(Math.round(nextTop));
                cropInputs.right.value = String(Math.round(nextRight));
                cropInputs.bottom.value = String(Math.round(nextBottom));
                updateCropPreview();
            };
            endCropDrag = () => {
                cropDragState = null;
            };

            widthInput?.addEventListener('input', () => {
                widthOutput.textContent = `${widthInput.value}%`;
            });
            selectOnFocus?.forEach((field) => {
                field.addEventListener('focus', () => {
                    if (String(field.value || '').trim() !== '') {
                        field.select();
                    }
                });
            });
            tabButtons?.forEach((button) => {
                button.addEventListener('click', () => {
                    setActiveTab(button.getAttribute('data-image-tab-button'));
                });
            });
            Object.values(cropInputs).forEach((field) => field?.addEventListener('input', updateCropPreview));
            popup?.querySelector('#image-crop-reset')?.addEventListener('click', () => {
                cropInputs.top.value = '0';
                cropInputs.right.value = '0';
                cropInputs.bottom.value = '0';
                cropInputs.left.value = '0';
                updateCropPreview();
            });
            cropPreview.focus?.addEventListener('mousedown', beginCropDrag);
            cropPreview.handles?.forEach((handle) => {
                handle.addEventListener('mousedown', beginCropResize(handle.getAttribute('data-crop-handle')));
            });
            window.addEventListener('mousemove', continueCropDrag);
            window.addEventListener('mouseup', endCropDrag);
            cropPreview.image?.addEventListener('load', () => {
                detectedNaturalSize.width = cropPreview.image.naturalWidth || detectedNaturalSize.width;
                detectedNaturalSize.height = cropPreview.image.naturalHeight || detectedNaturalSize.height;
                updateCropPreview();
            });
            if (cropPreview.image?.complete) {
                detectedNaturalSize.width = cropPreview.image.naturalWidth || detectedNaturalSize.width;
                detectedNaturalSize.height = cropPreview.image.naturalHeight || detectedNaturalSize.height;
            }
            setActiveTab('details');
            updateCropPreview();
        },
        willClose: () => {
            if (continueCropDrag) {
                window.removeEventListener('mousemove', continueCropDrag);
            }
            if (endCropDrag) {
                window.removeEventListener('mouseup', endCropDrag);
            }
        },
        preConfirm: () => {
            const nextCrop = getCropValues({
                cropTop: document.getElementById('image-crop-top')?.value,
                cropRight: document.getElementById('image-crop-right')?.value,
                cropBottom: document.getElementById('image-crop-bottom')?.value,
                cropLeft: document.getElementById('image-crop-left')?.value,
            });

            return {
                widthPercent: Math.max(5, Math.min(100, coerceInteger(document.getElementById('image-width')?.value, 100))),
                alignment: String(document.getElementById('image-alignment')?.value || 'center'),
                caption: String(document.getElementById('image-caption')?.value || '').trim(),
                naturalWidth: detectedNaturalSize.width,
                naturalHeight: detectedNaturalSize.height,
                cropTop: Math.round(nextCrop.top),
                cropRight: Math.round(nextCrop.right),
                cropBottom: Math.round(nextCrop.bottom),
                cropLeft: Math.round(nextCrop.left),
                marginTop: Math.max(0, coerceInteger(document.getElementById('image-margin-top')?.value, 0)),
                marginRight: Math.max(0, coerceInteger(document.getElementById('image-margin-right')?.value, 0)),
                marginBottom: Math.max(0, coerceInteger(document.getElementById('image-margin-bottom')?.value, 0)),
                marginLeft: Math.max(0, coerceInteger(document.getElementById('image-margin-left')?.value, 0)),
            };
        },
    });

    if (!result.isConfirmed) {
        return;
    }

    editor.chain().focus().updateAttributes('image', result.value).run();
}

document.addEventListener('alpine:init', () => {
    Alpine.data('editor', (content, linkOptionsUrl = '/link-options') => {
        let editor // Alpine's reactive engine automatically wraps component properties in proxy objects. Attempting to use a proxied editor instance to apply a transaction will cause a "Range Error: Applying a mismatched transaction", so be sure to unwrap it using Alpine.raw(), or simply avoid storing your editor as a component property, as shown in this example.
        let tableSelectionDragCleanup = null;

        return {
            updatedAt: Date.now(), // force Alpine to rerender on selection change
            content: SM.decodeHtml(content),
            setExternalContent(html = '', options = {}) {
                const nextContent = SM.decodeHtml(html);
                this.content = nextContent;

                if (editor) {
                    editor.commands.setContent(nextContent, false);
                    if (options.focusEnd) {
                        editor.chain().focus('end').run();
                    }
                    this.updatedAt = Date.now();
                }
            },
            init() {
                const _this = this

                editor = new Editor({
                    element: this.$refs.element,
                    extensions: [
                        StarterKit.configure({
                            link: false,
                            underline: false,
                        }),
                        Highlight,
                        CustomLink.configure({
                            openOnClick: false,
                        }),
                        Underline,
                        CustomImage,
                        Table.configure({
                            resizable: true,
                        }),
                        TableRow,
                        TableHeader,
                        TableCell,
                        TextAlign.configure({
                            types: ['heading', 'paragraph', 'small', 'extraSmall', 'box'],
                        }),
                        Typography,
                        ColorHighlighter,
                        SmileyReplacer,
                        Small,
                        ExtraSmall,
                        Box,
                        Spoiler
                    ],
                    content: content,
                    onCreate({/* editor */}) {
                        _this.updatedAt = Date.now()
                    },
                    onUpdate({editor}) {
                        _this.updatedAt = Date.now()
                        _this.content = editor.getHTML()
                    },
                    onSelectionUpdate({/* editor */}) {
                        _this.updatedAt = Date.now()
                    },
                    editorProps: {
                        attributes: {
                            class: 'tiptap content',
                        },
                        handleClickOn(view, _pos, node, nodePos, event, direct) {
                            const tableRole = node?.type?.spec?.tableRole;
                            const target = event.target instanceof Element ? event.target : null;
                            const cellElement = target?.closest?.('td, th');

                            if (!direct || !cellElement || !isCellHandleHit(event, cellElement) || (tableRole !== 'cell' && tableRole !== 'header_cell')) {
                                return false;
                            }

                            const $headCell = view.state.doc.resolve(nodePos);
                            const $anchorCell = getCellSelectionAnchor(view.state) || $headCell;

                            event.preventDefault();
                            window.getSelection?.()?.removeAllRanges?.();
                            applyCellSelection(view, $anchorCell, $headCell);

                            return true;
                        },
                        handleDOMEvents: {
                            selectstart(view, event) {
                                const target = event.target instanceof Element ? event.target : null;
                                const cellElement = target?.closest?.('td, th');

                                if (!cellElement || !view.dom.contains(cellElement) || !isCellHandleHit(event, cellElement)) {
                                    return false;
                                }

                                event.preventDefault();
                                window.getSelection?.()?.removeAllRanges?.();

                                return true;
                            },
                            dragstart(view, event) {
                                const target = event.target instanceof Element ? event.target : null;
                                const cellElement = target?.closest?.('td, th');

                                if (!cellElement || !view.dom.contains(cellElement) || !isCellHandleHit(event, cellElement)) {
                                    return false;
                                }

                                event.preventDefault();

                                return true;
                            },
                            mousedown(view, event) {
                                const target = event.target instanceof Element ? event.target : null;
                                const cellElement = target?.closest?.('td, th');

                                if (!cellElement || !view.dom.contains(cellElement) || !isCellHandleHit(event, cellElement)) {
                                    return false;
                                }

                                const $headCell = getResolvedCellFromDom(view, cellElement);
                                const $anchorCell = getCellSelectionAnchor(view.state) || $headCell;

                                if (!$headCell || !$anchorCell) {
                                    return false;
                                }

                                event.preventDefault();
                                window.getSelection?.()?.removeAllRanges?.();
                                applyCellSelection(view, $anchorCell, $headCell);

                                tableSelectionDragCleanup?.();

                                const updateSelectionFromPoint = (clientX, clientY) => {
                                    const hoveredElement = document.elementFromPoint(clientX, clientY);
                                    const hoveredCell = hoveredElement instanceof Element ? hoveredElement.closest('td, th') : null;

                                    if (!hoveredCell || !view.dom.contains(hoveredCell)) {
                                        return;
                                    }

                                    const $hoveredCell = getResolvedCellFromDom(view, hoveredCell);
                                    if ($hoveredCell) {
                                        applyCellSelection(view, $anchorCell, $hoveredCell);
                                    }
                                };

                                const handleMouseMove = (moveEvent) => {
                                    updateSelectionFromPoint(moveEvent.clientX, moveEvent.clientY);
                                };

                                const handleMouseUp = () => {
                                    tableSelectionDragCleanup?.();
                                };

                                window.addEventListener('mousemove', handleMouseMove);
                                window.addEventListener('mouseup', handleMouseUp, {once: true});

                                tableSelectionDragCleanup = () => {
                                    window.removeEventListener('mousemove', handleMouseMove);
                                    window.removeEventListener('mouseup', handleMouseUp);
                                    tableSelectionDragCleanup = null;
                                };

                                return true;
                            }
                        }
                    }
                })
            },
            isLoaded() {
                return editor
            },
            isActive(type, opts = {}) {
                return editor.isActive(type, opts)
            },
            toggleHeading(opts) {
                editor.chain().toggleHeading(opts).focus().run()
            },
            toggleBold() {
                editor.chain().toggleBold().focus().run()
            },
            toggleItalic() {
                editor.chain().toggleItalic().focus().run()
            },
            toggleUnderline() {
                editor.chain().toggleUnderline().focus().run()
            },
            toggleStrike() {
                editor.chain().toggleStrike().focus().run()
            },
            setParagraph() {
                editor.chain().setParagraph().focus().run()
            },
            toggleCode() {
                editor.chain().toggleCode().focus().run()
            },
            toggleBulletList() {
                editor.chain().toggleBulletList().focus().run()
            },
            toggleOrderedList() {
                editor.chain().toggleOrderedList().focus().run()
            },
            toggleBlockquote() {
                editor.chain().toggleBlockquote().focus().run()
            },
            toggleCodeBlock() {
                editor.chain().toggleCodeBlock().focus().run()
            },
            insertTable() {
                editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
            },
            addColumnBefore() {
                editor.chain().focus().addColumnBefore().run()
            },
            addColumnAfter() {
                editor.chain().focus().addColumnAfter().run()
            },
            deleteColumn() {
                editor.chain().focus().deleteColumn().run()
            },
            addRowBefore() {
                editor.chain().focus().addRowBefore().run()
            },
            addRowAfter() {
                editor.chain().focus().addRowAfter().run()
            },
            deleteRow() {
                editor.chain().focus().deleteRow().run()
            },
            mergeCells() {
                editor.chain().focus().mergeCells().run()
            },
            splitCell() {
                editor.chain().focus().splitCell().run()
            },
            toggleHeaderRow() {
                editor.chain().focus().toggleHeaderRow().run()
            },
            toggleHeaderColumn() {
                editor.chain().focus().toggleHeaderColumn().run()
            },
            deleteTable() {
                editor.chain().focus().deleteTable().run()
            },
            toggleLink() {
                editorToggleLink(editor, linkOptionsUrl)
            },
            clearLink() {
                editor.chain().focus().extendMarkRange('link').unsetLink().run()
            },
            insertImage() {
                if (editor.isActive('image')) {
                    editorConfigureImage(editor)
                    return
                }

                editorInsertImage(editor)
            },
            toggleHighlight() {
                editor.chain().toggleHighlight().focus().run()
            },
            toggleSpoiler() {
                editor.chain().toggleSpoiler().focus().run()
            },
            toggleSubscript() {
                editor.chain().toggleSubscript().focus().run()
            },
            toggleSuperscript() {
                editor.chain().toggleSuperscript().focus().run()
            },
            undo() {
                editor.chain().undo().focus().run()
            },
            redo() {
                editor.chain().redo().focus().run()
            },
            unsetAllMarks() {
                editor.chain().focus().unsetAllMarks().run()
            },
            clearNodes() {
                editor.chain().focus().clearNodes().run()
            },
            clearNotes() {
                editor.chain().focus().clearNodes().run()
            },
            setHorizontalRule() {
                editor.chain().focus().setHorizontalRule().run()
            },
            setHardBreak() {
                editor.chain().focus().setHardBreak().run()
            },
            setTextAlign(value) {
                editor.chain().setTextAlign(value).focus().run()
            },
            setSmall() {
                editor.chain().focus().setSmall().run()
            },
            setExtraSmall() {
                editor.chain().focus().setExtraSmall().run()
            },
            toggleBox(opts) {
                editor.chain().toggleBox(opts).focus().run()
            },
            canTable(command, _updatedAt = null) {
                if (!editor) {
                    return false
                }

                if (!editor.isActive('table')) {
                    return false
                }

                const chain = editor.can().chain()

                switch (command) {
                    case 'addColumnBefore':
                        return true
                    case 'addColumnAfter':
                        return true
                    case 'deleteColumn':
                        return true
                    case 'addRowBefore':
                        return true
                    case 'addRowAfter':
                        return true
                    case 'deleteRow':
                        return true
                    case 'mergeCells':
                        return chain.mergeCells().run()
                    case 'splitCell':
                        return chain.splitCell().run()
                    case 'toggleHeaderRow':
                        return true
                    case 'toggleHeaderColumn':
                        return true
                    case 'deleteTable':
                        return true
                    default:
                        return false
                }
            },
        }
    })
})
