# Contributing

Thanks for taking the time to contribute.

## Workflow

- Create a feature branch from `main`.
- Keep changes focused and small when possible.
- Follow existing project conventions.

## Local Setup

```bash
composer install
npm install
npm run build
php artisan migrate
```

## Code Style

- PHP: Laravel Pint (`composer pint`)
- JS/CSS: follow existing conventions in the repo

## UI Components

Use the shared components before adding control markup or copying classes from another screen.

| Purpose | Component and options |
| --- | --- |
| Action or navigation button | `x-ui.button`; `href` renders a link, otherwise the default is `type="button"`. Set `type="submit"` for form submission. |
| Button size | `size="small"`, `size="large"`, `size="icon"` or `size="icon-small"`. Size is separate from native button type and colour. |
| Button appearance | `color="primary"`, `"secondary"`, `"danger"`, etc.; `variant="soft"` for a subtle background, `variant="text"` for an action without a background, or `variant="plain"` for a custom control. |
| Table row action | `x-ui.row-action` inside `x-ui.row-actions`; supply an action `label` and `icon`. |
| Labelled form field | `x-ui.input`, `x-ui.select`, `x-ui.password`, `x-ui.money-input` or `x-ui.file-upload`. |
| Control in an existing label or table cell | `x-ui.input-control`, `x-ui.select-control` or `x-ui.textarea-control`; the caller supplies its accessible label and feedback. |
| Status or category | `x-ui.badge`; use the existing semantic colours consistently. |
| Save or action feedback | `x-ui.feedback` and the existing notification helpers; report success, errors and actions with no changes. |
| List attention counts | `x-ui.preset-views`; supply `count` and `attention` with a route to the affected items. |

```blade
<x-ui.button size="small" color="primary-outline" x-on:click="addItem()">Add item</x-ui.button>
<x-ui.button size="icon-small" variant="soft" color="danger" icon="trash" label="Remove item" x-on:click="removeItem()" />
<x-ui.button type="submit">Save changes</x-ui.button>
```

- Icon buttons need an accessible `label` or `aria-label`. A tooltip alone is insufficient. Mark decorative icons `aria-hidden="true"`.
- Shared fields use `App\Support\UiField`, `x-ui.field-label` and `x-ui.field-feedback` to connect labels, help and errors. Preserve those relationships in new field components, including Alpine repeated rows.
- `no-label` hides a supplied label visually while retaining it for screen readers. Keep labels concise and place longer explanations in `info`.
- Use `x-bind:*` and `x-on:*` for Alpine attributes on Blade components. Pass conditional state with bound props such as `:disabled="$disabled"`; do not insert `@if` or `@disabled` into a component's opening tag.
- Inside component attribute values, encode JavaScript data with `{{ \Illuminate\Support\Js::from($value) }}`. Blade leaves `@js(...)` and `@json(...)` directives as literal text in these attributes, causing browser syntax errors.
- Pass computed attribute bags through bound props such as `:label-attributes="$field->labelAttributes()"` or `:control-attributes="$field->controlAttributes()"`. Echoing a computed bag in a component's opening tag prevents Blade from compiling that component.
- Prefer `data-submit-on-change` for filter controls that submit their form. Shared JavaScript uses `requestSubmit()` so native form behaviour is retained.
- Keep disabled controls visibly disabled, with the default cursor and blocked activation. Disabled styling is not a substitute for server authorization.
- Keep formatting toolbars keyboard accessible: one Tab stop, arrow navigation, named controls and `aria-pressed` on format toggles. Tooltips must also work on focus and dismiss with Escape.
- Preserve the available desktop width. Use compact widths for scalar inputs, flexible space for names and notes, and wrapping layouts on smaller screens.
- Show list attention counts in tabs and the relevant status on each row. Avoid alert panels that repeat those counts and destinations. Use alert panels when they add guidance or describe a problem without a matching tab. Tab labels and filter options must describe the same condition; stock attention is labelled `Low Stock` and includes zero stock.
- Preserve form names, Alpine bindings and `data-*` hooks when replacing markup. Keep specialized drag handles and embedded controls at their required dimensions.

Reference: [W3C form labels](https://www.w3.org/WAI/tutorials/forms/labels/) and [button behaviour](https://www.w3.org/WAI/ARIA/apg/patterns/button/).

## Pull Requests

Please include:

- A clear summary of what changed and why.
- Screenshots for UI changes.
- Notes for any migrations or env changes.

## Reporting Issues

Include:

- Steps to reproduce
- Expected vs actual behavior
- Environment details
