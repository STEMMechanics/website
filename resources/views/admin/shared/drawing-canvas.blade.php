@php($heightClass = $heightClass ?? 'h-[72vh] min-h-[480px]')

<div class="rounded-xl border border-gray-200 bg-gray-50 p-2">
    <div class="flex flex-wrap items-center gap-1" role="toolbar" aria-label="Drawing tools">
        @foreach([
            ['select', 'fa-arrow-pointer', 'Select and transform'],
            ['draw', 'fa-pen', 'Draw'],
            ['erase', 'fa-eraser', 'Erase'],
            ['line', 'fa-slash', 'Line'],
            ['rectangle', 'fa-regular fa-square', 'Rectangle'],
            ['circle', 'fa-regular fa-circle', 'Circle'],
            ['text', 'fa-font', 'Text'],
            ['pan', 'fa-hand', 'Pan'],
        ] as [$tool, $icon, $label])
            <button type="button" x-bind:class="canvasToolButtonClass('{{ $tool }}')" x-on:click="setCanvasTool('{{ $tool }}')" title="{{ $label }}" aria-label="{{ $label }}">
                <i class="{{ str_contains($icon, 'fa-regular') ? $icon : 'fa-solid '.$icon }}"></i>
            </button>
        @endforeach

        <span class="mx-1 h-6 border-l border-gray-300" aria-hidden="true"></span>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-bind:disabled="!canvasCanUndo" x-on:click="undoCanvas()" title="Undo" aria-label="Undo"><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-bind:disabled="!canvasCanRedo" x-on:click="redoCanvas()" title="Redo" aria-label="Redo"><i class="fa-solid fa-rotate-right"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="zoomCanvasIn()" title="Zoom in" aria-label="Zoom in"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="zoomCanvasOut()" title="Zoom out" aria-label="Zoom out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="resetCanvasView()" title="Reset view" aria-label="Reset view"><i class="fa-solid fa-arrows-to-dot"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="exportCanvasPng()" title="Export PNG" aria-label="Export PNG"><i class="fa-solid fa-file-arrow-down"></i></button>
        <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="clearCanvasDrawing()" title="Clear drawing" aria-label="Clear drawing"><i class="fa-solid fa-trash-can"></i></button>

        <span class="mx-1 h-6 border-l border-gray-300" aria-hidden="true"></span>
        <label class="flex h-9 items-center gap-1 rounded-md border border-gray-300 bg-white px-2" title="Colour">
            <i class="fa-solid fa-palette text-gray-500" aria-hidden="true"></i>
            <input type="color" class="h-6 w-7 cursor-pointer border-0 bg-transparent p-0" x-model="canvasColor" x-on:input="setCanvasColor($event.target.value)" aria-label="Drawing colour">
        </label>
        <label class="flex h-9 items-center gap-2 rounded-md border border-gray-300 bg-white px-2" title="Stroke width">
            <i class="fa-solid fa-minus text-gray-500" aria-hidden="true"></i>
            <input type="range" min="1" max="48" step="1" class="w-24" x-model="canvasBrushSize" x-on:input="setCanvasBrushSize($event.target.value)" aria-label="Drawing line width">
            <span class="w-8 text-right text-xs text-gray-600" x-text="canvasBrushSize + 'px'"></span>
        </label>
        <span class="ml-auto text-xs text-gray-500">Zoom <span x-text="canvasZoomPercent + '%'"></span></span>
    </div>
</div>

<div x-show="canvasError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="canvasError"></div>
<div class="mt-3 rounded-xl border border-gray-300 bg-white p-2 shadow-sm">
    <div class="mb-2 text-xs text-gray-500">Draw with Apple Pencil, touch, or mouse. Use Select to move, resize, or rotate objects. Cmd/Ctrl-Z, X, C, and V support undo, cut, copy, and paste.</div>
    <div x-ref="pickListCanvasViewport" class="relative {{ $heightClass }} w-full overflow-hidden rounded-lg border border-dashed border-gray-300 bg-white" style="touch-action:none;overscroll-behavior:contain">
        <canvas x-ref="pickListCanvas" class="absolute inset-0 block h-full w-full"></canvas>
    </div>
</div>
