<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @php
        $templatePdf = isset($template) && ! isset($workshop);
        $resolvedTemplate = $templatePdf ? $template : $workshop->pickListTemplate;
        $documentName = $templatePdf ? $resolvedTemplate->name : $workshop->title;
    @endphp
    <title>{{ $templatePdf ? 'Workshop Template' : 'Workshop Plan' }} - {{ $documentName }}</title>
    <style>
        @include('pdf.partials.styling')
        body { line-height: 1.15; }
        .pick-details { border-collapse: collapse; }
        .label { display: inline-block; width: 80px; color: #777; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .value { display: inline-block; color: #333; font-size: 12px; margin-bottom: 0 }
        .section-title { color: #1da1e6; font-weight: 700; font-size: 14px; margin: 14px 0 8px; text-transform: uppercase; }
        .items-grid { width: 100%; border-collapse: collapse; margin-top: 2px; table-layout: fixed; }
        .items-grid td { width: 33.33%; vertical-align: middle; padding: 0 10px 0 0; }
        .line { height: 40px; font-size: 12px; }
        .box { display: inline-block; width: 12px; height: 12px; border: 1px solid #666; margin-right: 8px; margin-top: -2px; vertical-align: text-top }
        .type-note { margin: -6px 0 0 22px; font-size: 9px; color: #666; line-height: 1.25; }
        .type-note p, .notes-body p { margin: 0 0 3px; }
        .type-note ul, .notes-body ul { margin: 0 0 3px 14px; padding: 0; }
        .type-note li, .notes-body li { margin: 0 0 2px; }
        .notes-wrap { margin-top: 12px; }
        .workshop-notes { margin-top: 4px; }
        .workshop-notes .section-title { margin-top: 4px; margin-bottom: 3px; }
        .notes-body { font-size: 11px; color: #333; line-height: 1.35; }
        .plan-page { page-break-before: always; }
        .tasks-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .tasks-grid td { width: 33.333%; padding: 0 10px 0 0; vertical-align: top; }
        .tasks-grid td:last-child { padding-right: 0; }
        .task { margin-bottom: 9px; page-break-inside: avoid; }
        .task-name { font-size: 12px; font-weight: 700; line-height: 1.15; }
        .task-timeframe { margin: 0 0 0 22px; font-size: 9px; line-height: 1.1; color: #666; }
        .task-notes { margin-top: 4px; font-size: 10px; color: #555; white-space: pre-line; }
        .run-sheet { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #333; line-height: 1.15; }
        .run-sheet h1, .run-sheet h2, .run-sheet h3 { margin: 10px 0 5px; }
        .run-sheet p { margin: 0 0 7px; }
        .run-sheet ul, .run-sheet ol { margin: 0 0 7px 18px; padding: 0; }
        .run-sheet-title { margin-bottom: 3px; }
        .run-sheet > :first-child { margin-top: 0; }
        .drawing { max-width: 100%; max-height: 430px; margin-top: 10px; }
    </style>
</head>
<body>
    @include('pdf.partials.workshop-pick-list-page', [
        'workshop' => $workshop ?? null,
        'template' => $templatePdf ? $resolvedTemplate : null,
        'participants' => $participants,
        'calculatedItems' => $calculatedItems,
        'pickListNotes' => $templatePdf ? '' : ($pickListNotes ?? ''),
        'documentTitle' => $templatePdf ? 'Workshop Template' : 'Workshop Pick List',
    ])

    @php
        $tasks = $resolvedTemplate?->tasks ?? collect();
        $runSheet = trim((string) (($templatePdf ?? false)
            ? ($resolvedTemplate?->run_sheet ?? '')
            : ($workshop->workshop_run_sheet ?? $resolvedTemplate?->run_sheet ?? '')));
        $templateDrawing = trim((string) ($resolvedTemplate?->run_sheet_drawing_data ?? ''));
        $workshopNotes = '';
        $hasPlanPage = $tasks->isNotEmpty() || $runSheet !== '' || $templateDrawing !== '' || $workshopNotes !== '' || $workshopDrawingPath;
        $taskReminderTimeframe = static function ($task): string {
            if (! $task->reminder_enabled || $task->reminder_offset_days === null || trim((string) $task->reminder_time) === '') {
                return '';
            }

            $days = abs((int) $task->reminder_offset_days);
            $relative = $days === 0
                ? 'On the workshop day'
                : $days.' day'.($days === 1 ? '' : 's').' '.((int) $task->reminder_offset_days < 0 ? 'before' : 'after').' the workshop';
            $time = \Carbon\Carbon::createFromFormat('H:i', (string) $task->reminder_time)?->format('g:ia') ?? (string) $task->reminder_time;

            return $relative.' at '.$time;
        };
    @endphp

    @if($hasPlanPage)
        <div class="plan-page">
            <table class="header">
                <tr>
                    <td class="logo-wrap">
                        @if(file_exists(public_path('invoice-logo.png')))<img class="logo" src="{{ public_path('invoice-logo.png') }}" alt="Logo" />@endif
                    </td>
                    <td class="headline" style="vertical-align: middle"><div>Tasks &amp; Run Sheet</div><div class="document-subtitle">{{ $documentName }}</div></td>
                </tr>
            </table>

            @if($tasks->isNotEmpty())
                <div class="section-title">Tasks</div>
                @php
                    $taskColumnCount = 3;
                    $tasksPerColumn = (int) ceil($tasks->count() / $taskColumnCount);
                    $taskColumns = $tasks->chunk(max(1, $tasksPerColumn));
                @endphp
                <table class="tasks-grid">
                    <tr>
                        @foreach($taskColumns as $taskColumn)
                            <td>
                                @foreach($taskColumn as $task)
                                    <div class="task">
                                        <div class="task-name"><span class="box"></span>{{ $task->name }}</div>
                                        @if($taskReminderTimeframe($task) !== '')<div class="task-timeframe">{{ $taskReminderTimeframe($task) }}</div>@endif
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                        @for($column = $taskColumns->count(); $column < $taskColumnCount; $column++)
                            <td></td>
                        @endfor
                    </tr>
                </table>
            @endif

            @if($runSheet !== '' || $templateDrawing !== '' || $workshopNotes !== '' || $workshopDrawingPath)
                <div class="section-title run-sheet-title">Run Sheet</div>
                @if($runSheet !== '')<div class="run-sheet">{!! $runSheet !!}</div>@endif
                @if($templateDrawing !== '')<img class="drawing" src="{{ $templateDrawing }}" alt="Template run sheet drawing">@endif
                @if($workshopNotes !== '')
                    <div class="section-title">Workshop-specific Notes</div>
                    <div class="task-notes">{{ $workshopNotes }}</div>
                @endif
                @if($workshopDrawingPath)<img class="drawing" src="{{ $workshopDrawingPath }}" alt="Workshop run sheet drawing">@endif
            @endif
        </div>
    @endif
</body>
</html>
