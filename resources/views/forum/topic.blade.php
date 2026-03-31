@php
    $reactionState = [];
    $allReactionPosts = collect([$firstPost])->filter()->merge($posts->getCollection());

    foreach ($allReactionPosts as $reactionPost) {
        $reactionState[(string) $reactionPost->id] = [
            'current' => $reactionPost->reactionTypeFor(auth()->user()),
            'counts' => [
                \App\Models\ForumPostReaction::TYPE_LOVE => $reactionPost->reactionCountFor(\App\Models\ForumPostReaction::TYPE_LOVE),
                \App\Models\ForumPostReaction::TYPE_LIKE => $reactionPost->reactionCountFor(\App\Models\ForumPostReaction::TYPE_LIKE),
                \App\Models\ForumPostReaction::TYPE_DISLIKE => $reactionPost->reactionCountFor(\App\Models\ForumPostReaction::TYPE_DISLIKE),
            ],
            'tooltips' => [
                \App\Models\ForumPostReaction::TYPE_LOVE => $reactionPost->reactionTooltipFor(\App\Models\ForumPostReaction::TYPE_LOVE),
                \App\Models\ForumPostReaction::TYPE_LIKE => $reactionPost->reactionTooltipFor(\App\Models\ForumPostReaction::TYPE_LIKE),
                \App\Models\ForumPostReaction::TYPE_DISLIKE => $reactionPost->reactionTooltipFor(\App\Models\ForumPostReaction::TYPE_DISLIKE),
            ],
        ];
    }

    $reactionBaseButtonClasses = 'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition';
    $reactionIdleClasses = 'border-gray-200 bg-white text-gray-700 hover:border-primary-color hover:text-primary-color';

    $modalEditPostId = trim((string) old('edit_post_id', ''));
    $modalMode = old('modal_mode', $modalEditPostId !== '' ? 'edit' : 'reply');
    $editingPost = $modalEditPostId !== ''
        ? $allReactionPosts->firstWhere('id', $modalEditPostId)
        : null;
    $modalTitle = $modalMode === 'edit' ? ($editingPost && $firstPost && (string) $editingPost->id === (string) $firstPost->id ? 'Edit Thread Post' : 'Edit Reply') : 'Reply';
    $modalSubmitLabel = $modalMode === 'edit' ? 'Save Changes' : 'Post Reply';
    $modalMethod = $modalMode === 'edit' ? 'PUT' : 'POST';
    $modalAction = $modalMode === 'edit' && $editingPost
        ? route('forum.post.update', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $editingPost->id, 'sort' => $replySort])
        : route('forum.post.store', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]);
    $modalReplyPostId = trim((string) old('reply_to_post_id', ''));
    $replyModalOpen = $errors->has('body') || $errors->has('attachments') || $errors->has('attachments.*');
    $reportModalOpen = $errors->has('reason');
    $reportAction = trim((string) old('report_action', ''));
    $reportPostId = trim((string) old('report_post_id', ''));
    $reportAuthor = trim((string) old('report_author', ''));
    $modalRemovedAttachmentIds = collect(old('removed_attachments', []))
        ->map(fn ($id): string => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $titleModalOpen = $errors->has('title');

    $topicPageConfig = [
        'reactionState' => $reactionState,
        'csrfToken' => csrf_token(),
        'replyModalOpen' => $replyModalOpen,
        'initialReplyBody' => old('body', $replyPrefillBody),
        'initialReplyPostId' => $modalReplyPostId,
        'reactionBaseButtonClasses' => $reactionBaseButtonClasses,
        'reactionIdleClasses' => $reactionIdleClasses,
        'snapshotUrl' => route('forum.topic.snapshot', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]),
        'defaultReplyAction' => route('forum.post.store', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]),
        'defaultReplyMethod' => 'POST',
        'reportModalOpen' => $reportModalOpen,
        'initialReportAction' => $reportAction,
        'initialReportPostId' => $reportPostId,
        'initialReportAuthor' => $reportAuthor,
        'titleModalOpen' => $titleModalOpen,
    ];

    $tabs = [];
    if ($category->classSession) {
        $tabs[] = [
            'title' => 'Course',
            'route' => route('class.show', $category->classSession),
        ];
        $tabs[] = [
            'title' => 'Forum',
            'route' => route('forum.category.show', $category->slug),
            'match' => 'starts_with',
        ];
    }
@endphp

<script>
    window.forumTopicPageConfig = @json($topicPageConfig);
</script>

<x-layout>
    <x-mast backRoute="forum.category.show" :backRouteParams="[$category->slug]" backTitle="{{ $category->name }}" :tabs="$tabs">{!! $topic->formattedTitle(false) !!}</x-mast>

    <x-container class="py-8" id="forum-topic-page">
        <div id="forum-topic-meta">
            @include('forum.partials.topic-meta', [
                'category' => $category,
                'topic' => $topic,
                'canReply' => $canReply,
                'commentCount' => $commentCount,
                'lastCommentPost' => $lastCommentPost,
                'lastCommentAuthorName' => $lastCommentAuthorName,
                'notificationsEnabled' => $notificationsEnabled,
                'replySort' => $replySort,
            ])
        </div>

        <div id="forum-topic-first-post">
            @if($firstPost)
                @include('forum.partials.post', [
                    'post' => $firstPost,
                    'isFirstPost' => true,
                    'replyLabel' => 'Thread Post',
                    'category' => $category,
                    'topic' => $topic,
                    'replySort' => $replySort,
                    'canReply' => $canReply,
                    'replyDraftBody' => '',
                ])
            @endif
        </div>

        <div id="forum-topic-replies" class="space-y-4">
            @include('forum.partials.replies', [
                'posts' => $posts,
                'replySort' => $replySort,
                'category' => $category,
                'topic' => $topic,
                'canReply' => $canReply,
            ])
        </div>

        <div id="forum-topic-pagination" class="mt-6">
            {{ $posts->appends(request()->query())->links() }}
        </div>

        <div id="forum-topic-reply-panel">
            @include('forum.partials.reply-panel', ['topic' => $topic, 'canReply' => $canReply])
        </div>

        <div
            id="forum-title-modal"
            class="{{ $titleModalOpen ? 'fixed' : 'hidden' }} inset-0 z-50 items-center justify-center bg-black/50 p-4"
        >
            <div class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" data-forum-title-modal-panel>
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Edit thread title</h2>
                        <p class="text-sm text-gray-500">Type markdown directly. Supports italic, bold, and strikethrough.</p>
                    </div>
                    <button type="button" class="text-gray-500 transition hover:text-gray-900" data-forum-title-close title="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('forum.topic.title.update', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]) }}" class="space-y-5 px-6 py-5">
                    @csrf
                    @method('PUT')
                    <x-ui.input
                        id="forum-title-input"
                        name="title"
                        label="Thread Title"
                        :value="old('title', $topic->title)"
                        info="Supports *italic*, **bold**, and ~~strikethrough~~."
                    />
                    <div class="flex justify-end gap-3 border-t border-gray-200 pt-5">
                        <x-ui.button type="button" color="outline" data-forum-title-close>Cancel</x-ui.button>
                        <x-ui.button type="submit">Save Title</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <div
            id="forum-reply-modal"
            class="{{ $errors->has('body') ? 'fixed' : 'hidden' }} inset-0 z-50 items-center justify-center bg-black/50 p-4"
        >
            <div class="flex max-h-[calc(100dvh-1rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" data-forum-reply-modal-panel>
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 id="forum-reply-modal-title" class="text-lg font-semibold text-gray-900">{{ $modalTitle }}</h2>
                        <p id="forum-reply-modal-description" class="text-sm text-gray-500">Use the editor to {{ $modalMode === 'edit' ? 'update your post' : 'post a reply' }}.</p>
                    </div>
                    <button type="button" class="text-gray-500 transition hover:text-gray-900" data-forum-reply-close title="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="{{ $modalAction }}" enctype="multipart/form-data" class="min-h-0 flex-1 overflow-y-auto" data-default-action="{{ route('forum.post.store', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]) }}" data-default-method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="forum-reply-modal-method" value="{{ $modalMethod === 'PUT' ? 'PUT' : '' }}">
                    <input type="hidden" name="modal_mode" id="forum-reply-modal-mode" value="{{ $modalMode }}">
                    <input type="hidden" name="edit_post_id" id="forum-reply-edit-post-id" value="{{ $editingPost?->id }}">
                    <input type="hidden" name="reply_to_post_id" id="forum-reply-to-post-id" value="{{ $modalReplyPostId }}">
                    <div class="space-y-5 px-6 py-5">
                        <x-ui.editor name="body" :value="old('body', $replyPrefillBody)" label="" :allowHeadings="false" class="forum-reply-editor border-0! mt-0" />
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 pt-4 space-y-4">
                            <input
                                id="forum-reply-attachments"
                                type="file"
                                name="attachments[]"
                                multiple
                                class="hidden"
                            />
                            <div id="forum-reply-current-attachments" class="{{ $modalMode === 'edit' && $editingPost && $editingPost->attachments->isNotEmpty() ? '' : 'hidden' }}">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attachments</div>
                                <div id="forum-reply-current-attachments-list" data-forum-attachments-list class="mt-3 space-y-2">
                                    @if($modalMode === 'edit' && $editingPost && $editingPost->attachments->isNotEmpty())
                                        @include('forum.partials.edit-post-attachments', [
                                            'post' => $editingPost,
                                            'category' => $category,
                                            'topic' => $topic,
                                            'removedAttachmentIds' => $modalRemovedAttachmentIds,
                                        ])
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <x-ui.button type="button" color="outline" id="forum-reply-add-files" class="">
                                    <i class="fa-solid fa-paperclip mr-2"></i>Add files
                                </x-ui.button>
                                <p class="text-xs text-gray-500">Max size per file: {{ \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize(auth()->user())) }}.</p>
                            </div>
                            <div id="forum-reply-removed-attachments" class="hidden">
                                @foreach($modalRemovedAttachmentIds as $attachmentId)
                                    <input type="hidden" name="removed_attachments[]" value="{{ $attachmentId }}">
                                @endforeach
                            </div>
                            <div id="forum-reply-save-progress" class="hidden rounded-2xl border border-sky-200 bg-sky-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div id="forum-reply-save-progress-label" class="text-sm font-medium text-sky-900">Saving post...</div>
                                    <div id="forum-reply-save-progress-percent" class="text-xs font-medium text-sky-700">0%</div>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-sky-100">
                                    <div id="forum-reply-save-progress-bar" class="h-full w-0 rounded-full bg-sky-600 transition-all duration-150"></div>
                                </div>
                            </div>
                            <div id="forum-reply-submit-errors" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
                            @if($errors->has('attachments') || $errors->has('attachments.*'))
                                <p class="mt-2 text-xs text-red-600">{{ $errors->first('attachments') ?: $errors->first('attachments.*') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 justify-end gap-3 border-t border-gray-300 px-6 py-5">
                        <x-ui.button type="button" color="outline" data-forum-reply-close>Cancel</x-ui.button>
                        <x-ui.button type="submit" id="forum-reply-submit-label">{{ $modalSubmitLabel }}</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <div
            id="forum-report-modal"
            class="{{ $reportModalOpen ? 'fixed' : 'hidden' }} inset-0 z-50 items-center justify-center bg-black/50 p-4"
        >
            <div class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" data-forum-report-modal-panel>
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Report post</h2>
                        <p class="text-sm text-gray-500">Let us know what needs attention about <span id="forum-report-author" class="font-medium text-gray-700">{{ $reportAuthor !== '' ? $reportAuthor : 'this post' }}</span>.</p>
                    </div>
                    <button type="button" class="text-gray-500 transition hover:text-gray-900" data-forum-report-close title="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form method="POST" action="{{ $reportAction !== '' ? $reportAction : '#' }}" id="forum-report-form" class="space-y-5 px-6 py-5">
                    @csrf
                    <input type="hidden" name="report_post_id" id="forum-report-post-id" value="{{ $reportPostId }}">
                    <input type="hidden" name="report_action" id="forum-report-action" value="{{ $reportAction }}">
                    <input type="hidden" name="report_author" id="forum-report-author-input" value="{{ $reportAuthor }}">
                    <x-ui.input
                        type="textarea"
                        label="Why are you reporting this post?"
                        name="reason"
                        rows="6"
                        value="{{ old('reason') }}"
                        info="Include enough detail for moderation to understand the issue."
                    />
                    <div class="flex justify-end gap-3 border-t border-gray-200 pt-5">
                        <x-ui.button type="button" color="outline" data-forum-report-close>Cancel</x-ui.button>
                        <x-ui.button type="submit">Send report</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </x-container>
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('forum-topic-page');
        if (!root) {
            return;
        }

        const config = window.forumTopicPageConfig || {};
        const reactionState = config.reactionState || {};
        const reactionLoading = {};
        const reactionBaseButtonClasses = config.reactionBaseButtonClasses || '';
        const reactionIdleClasses = config.reactionIdleClasses || '';
        const snapshotUrl = config.snapshotUrl || '';
        const modal = document.getElementById('forum-reply-modal');
        const reportModal = document.getElementById('forum-report-modal');
        const titleModal = document.getElementById('forum-title-modal');
        const modalTitle = document.getElementById('forum-reply-modal-title');
        const modalDescription = document.getElementById('forum-reply-modal-description');
        const replyForm = modal?.querySelector('form') || null;
        const reportForm = document.getElementById('forum-report-form');
        const titleInput = document.getElementById('forum-title-input');
        const replyMethodInput = document.getElementById('forum-reply-modal-method');
        const replyModeInput = document.getElementById('forum-reply-modal-mode');
        const replyEditPostIdInput = document.getElementById('forum-reply-edit-post-id');
        const replyToPostIdInput = document.getElementById('forum-reply-to-post-id');
        const addFilesButton = document.getElementById('forum-reply-add-files');
        const replyAttachmentsInput = document.getElementById('forum-reply-attachments');
        const currentAttachmentsContainer = document.getElementById('forum-reply-current-attachments');
        const currentAttachmentsList = document.getElementById('forum-reply-current-attachments-list');
        const removedAttachmentsContainer = document.getElementById('forum-reply-removed-attachments');
        const saveProgressContainer = document.getElementById('forum-reply-save-progress');
        const saveProgressLabel = document.getElementById('forum-reply-save-progress-label');
        const saveProgressPercent = document.getElementById('forum-reply-save-progress-percent');
        const saveProgressBar = document.getElementById('forum-reply-save-progress-bar');
        const submitErrorsContainer = document.getElementById('forum-reply-submit-errors');
        const replySubmitLabel = document.getElementById('forum-reply-submit-label');
        const reportAuthorLabel = document.getElementById('forum-report-author');
        const reportPostIdInput = document.getElementById('forum-report-post-id');
        const reportActionInput = document.getElementById('forum-report-action');
        const reportAuthorInput = document.getElementById('forum-report-author-input');
        const firstPostContainer = document.getElementById('forum-topic-first-post');
        const topicMetaContainer = document.getElementById('forum-topic-meta');
        const repliesContainer = document.getElementById('forum-topic-replies');
        const paginationContainer = document.getElementById('forum-topic-pagination');
        const replyPanelContainer = document.getElementById('forum-topic-reply-panel');
        let topicPollHandle = null;
        let replyModalSaving = false;

        const setReplyEditorContent = (html = '') => {
            window.dispatchEvent(new CustomEvent('sm-editor-set-content', {
                detail: {
                    name: 'body',
                    html,
                    focusEnd: true,
                },
            }));
        };

        const attachmentQueue = new DataTransfer();

        const formatFileSize = (bytes) => {
            const size = Number(bytes) || 0;
            if (size < 1024) {
                return `${size} B`;
            }
            if (size < 1024 * 1024) {
                return `${(size / 1024).toFixed(size >= 10240 ? 0 : 1)} KB`;
            }

            return `${(size / (1024 * 1024)).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
        };

        const escapeHtml = (value = '') => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const clearRemovedAttachments = () => {
            if (removedAttachmentsContainer) {
                removedAttachmentsContainer.innerHTML = '';
            }
        };

        const clearSubmitErrors = () => {
            if (!submitErrorsContainer) {
                return;
            }

            submitErrorsContainer.innerHTML = '';
            submitErrorsContainer.classList.add('hidden');
        };

        const setEditorDisabledState = (isDisabled) => {
            const editorRoot = modal?.querySelector('.forum-reply-editor');
            if (!editorRoot) {
                return;
            }

            editorRoot.classList.toggle('pointer-events-none', isDisabled);
            editorRoot.classList.toggle('opacity-60', isDisabled);

            editorRoot.querySelectorAll('[contenteditable], button, input, select, textarea').forEach((element) => {
                if (isDisabled) {
                    if (!Object.prototype.hasOwnProperty.call(element.dataset, 'forumPrevDisabled')) {
                        element.dataset.forumPrevDisabled = element.disabled ? '1' : '0';
                    }
                    if (element.hasAttribute('contenteditable')) {
                        if (!Object.prototype.hasOwnProperty.call(element.dataset, 'forumPrevContenteditable')) {
                            element.dataset.forumPrevContenteditable = element.getAttribute('contenteditable') ?? '';
                        }
                        element.setAttribute('contenteditable', 'false');
                    }
                    if ('disabled' in element) {
                        element.disabled = true;
                    }
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(element.dataset, 'forumPrevDisabled')) {
                    if ('disabled' in element) {
                        element.disabled = element.dataset.forumPrevDisabled === '1';
                    }
                    delete element.dataset.forumPrevDisabled;
                }

                if (Object.prototype.hasOwnProperty.call(element.dataset, 'forumPrevContenteditable')) {
                    const previous = element.dataset.forumPrevContenteditable;
                    if (previous === '') {
                        element.removeAttribute('contenteditable');
                    } else {
                        element.setAttribute('contenteditable', previous);
                    }
                    delete element.dataset.forumPrevContenteditable;
                }
            });
        };

        const renderSubmitErrors = (errors = {}) => {
            if (!submitErrorsContainer) {
                return;
            }

            const messages = Object.values(errors || {}).flat().filter(Boolean);
            if (messages.length === 0) {
                clearSubmitErrors();
                return;
            }

            submitErrorsContainer.innerHTML = `<ul class="space-y-1">${messages.map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;
            submitErrorsContainer.classList.remove('hidden');
        };

        const syncAttachmentInput = () => {
            if (!replyAttachmentsInput) {
                return;
            }

            replyAttachmentsInput.files = attachmentQueue.files;
        };

        const renderQueuedAttachments = () => {
            if (!currentAttachmentsList) {
                return;
            }

            currentAttachmentsList.querySelectorAll('[data-forum-queued-attachment-row]').forEach((row) => row.remove());

            Array.from(attachmentQueue.files || []).forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 text-sm text-gray-700';
                row.dataset.forumQueuedAttachmentRow = 'true';
                row.dataset.queuedAttachmentIndex = String(index);
                row.title = 'Still to be uploaded';
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-cloud-arrow-up text-sky-500/70';

                const content = document.createElement('div');
                content.className = 'flex-1 flex gap-3 items-center';

                const name = document.createElement('div');
                name.className = 'truncate font-medium';
                name.textContent = file.name;

                const size = document.createElement('div');
                size.className = 'text-xs text-gray-500';
                size.textContent = formatFileSize(file.size);

                content.appendChild(icon);
                content.appendChild(name);
                content.appendChild(size);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-red-600 transition hover:text-red-700';
                remove.dataset.forumQueuedAttachmentRemove = 'true';
                remove.dataset.queuedAttachmentIndex = String(index);
                remove.title = 'Remove file';
                remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';

                row.appendChild(content);
                row.appendChild(remove);
                currentAttachmentsList.appendChild(row);
            });

            syncCurrentAttachmentsVisibility();
        };

        const clearQueuedAttachments = () => {
            while (attachmentQueue.items.length > 0) {
                attachmentQueue.items.remove(0);
            }
            syncAttachmentInput();
            renderQueuedAttachments();
        };

        const addQueuedAttachments = (files = []) => {
            Array.from(files || []).forEach((file) => {
                if (file) {
                    attachmentQueue.items.add(file);
                }
            });
            syncAttachmentInput();
            renderQueuedAttachments();
        };

        const removeQueuedAttachmentAt = (index) => {
            const nextQueue = new DataTransfer();
            Array.from(attachmentQueue.files || []).forEach((file, fileIndex) => {
                if (fileIndex !== index) {
                    nextQueue.items.add(file);
                }
            });

            while (attachmentQueue.items.length > 0) {
                attachmentQueue.items.remove(0);
            }
            Array.from(nextQueue.files || []).forEach((file) => attachmentQueue.items.add(file));

            syncAttachmentInput();
            renderQueuedAttachments();
        };

        const setSavingState = (isSaving, progress = 0) => {
            replyModalSaving = isSaving;

            if (saveProgressContainer && saveProgressLabel && saveProgressPercent && saveProgressBar) {
                if (isSaving) {
                    saveProgressContainer.classList.remove('hidden');
                    saveProgressLabel.textContent = 'Saving post...';
                    saveProgressPercent.textContent = `${Math.max(0, Math.min(100, Math.round(progress)))}%`;
                    saveProgressBar.style.width = `${Math.max(0, Math.min(100, progress))}%`;
                } else {
                    saveProgressContainer.classList.add('hidden');
                    saveProgressBar.style.width = '0%';
                    saveProgressPercent.textContent = '0%';
                }
            }

            if (replySubmitLabel) {
                replySubmitLabel.disabled = isSaving;
            }
            if (addFilesButton) {
                addFilesButton.disabled = isSaving;
            }
            if (replyAttachmentsInput) {
                replyAttachmentsInput.disabled = isSaving;
            }

            modal?.querySelectorAll('[data-forum-reply-close]').forEach((button) => {
                if ('disabled' in button) {
                    button.disabled = isSaving;
                }
            });

            if (currentAttachmentsList) {
                currentAttachmentsList.querySelectorAll('button').forEach((button) => {
                    button.disabled = isSaving;
                });
            }

            setEditorDisabledState(isSaving);
        };

        const appendRemovedAttachmentInput = (attachmentId = '') => {
            if (!removedAttachmentsContainer || !attachmentId) {
                return;
            }

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_attachments[]';
            input.value = attachmentId;
            removedAttachmentsContainer.appendChild(input);
        };

        const syncCurrentAttachmentsVisibility = () => {
            if (!currentAttachmentsContainer || !currentAttachmentsList) {
                return;
            }

            const hasRows = currentAttachmentsList.querySelector('[data-forum-attachment-row], [data-forum-queued-attachment-row]') !== null;
            currentAttachmentsContainer.classList.toggle('hidden', !hasRows);
        };

        const openComposerModal = ({
            title = 'Reply',
            html = '',
            action = replyForm?.dataset.defaultAction || replyForm?.action || '',
            method = replyForm?.dataset.defaultMethod || 'POST',
            submitLabel = 'Post Reply',
            mode = 'reply',
            editPostId = '',
            replyPostId = '',
            currentAttachmentsHtml = '',
            resetRemovedAttachments = true,
        } = {}) => {
            if (!modal || !modalTitle) {
                return;
            }

            modalTitle.textContent = title;
            clearSubmitErrors();
            if (replyForm && action) {
                replyForm.action = action;
            }
            if (replyMethodInput) {
                replyMethodInput.value = method === 'PUT' ? 'PUT' : '';
            }
            if (replyModeInput) {
                replyModeInput.value = mode;
            }
            if (replyEditPostIdInput) {
                replyEditPostIdInput.value = editPostId || '';
            }
            if (replyToPostIdInput) {
                replyToPostIdInput.value = mode === 'reply' ? (replyPostId || '') : '';
            }
            if (replySubmitLabel) {
                replySubmitLabel.textContent = submitLabel;
            }
            if (currentAttachmentsContainer && currentAttachmentsList) {
                if (resetRemovedAttachments) {
                    clearRemovedAttachments();
                    clearQueuedAttachments();
                }

                const trimmedHtml = String(currentAttachmentsHtml || '').trim();
                if (mode === 'edit' && trimmedHtml !== '') {
                    currentAttachmentsList.innerHTML = trimmedHtml;
                } else {
                    currentAttachmentsList.innerHTML = '';
                }
                syncCurrentAttachmentsVisibility();
            }
            if (replyAttachmentsInput) {
                replyAttachmentsInput.value = '';
            }
            if (modalDescription) {
                modalDescription.textContent = mode === 'edit'
                    ? 'Use the editor to update your post.'
                    : 'Use the editor to post a reply.';
            }
            modal.classList.remove('hidden');
            modal.classList.add('fixed', 'flex');

            requestAnimationFrame(() => {
                setReplyEditorContent(html || '');
            });
        };

        const openReplyModal = (title = 'Reply', html = '', replyPostId = '') => {
            openComposerModal({
                title,
                html,
                submitLabel: 'Post Reply',
                mode: 'reply',
                replyPostId,
            });
        };

        const closeReplyModal = () => {
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            if (replyAttachmentsInput) {
                replyAttachmentsInput.value = '';
            }
            clearRemovedAttachments();
            clearQueuedAttachments();
            clearSubmitErrors();
            setSavingState(false);
            if (currentAttachmentsContainer && currentAttachmentsList) {
                currentAttachmentsList.innerHTML = '';
                currentAttachmentsContainer.classList.add('hidden');
            }
        };

        const openReportModal = ({
            action = '',
            author = 'this post',
            postId = '',
        } = {}) => {
            if (!reportModal || !reportForm || !action) {
                return;
            }

            reportForm.action = action;
            if (reportPostIdInput) {
                reportPostIdInput.value = postId || '';
            }
            if (reportActionInput) {
                reportActionInput.value = action;
            }
            if (reportAuthorInput) {
                reportAuthorInput.value = author || '';
            }
            if (reportAuthorLabel) {
                reportAuthorLabel.textContent = author || 'this post';
            }
            reportModal.classList.remove('hidden');
            reportModal.classList.add('fixed', 'flex');
        };

        const closeReportModal = () => {
            if (!reportModal) {
                return;
            }

            reportModal.classList.add('hidden');
            reportModal.classList.remove('flex');
        };

        const openTitleModal = () => {
            if (!titleModal) {
                return;
            }

            titleModal.classList.remove('hidden');
            titleModal.classList.add('fixed', 'flex');

            requestAnimationFrame(() => {
                titleInput?.focus();
                titleInput?.select();
            });
        };

        const closeTitleModal = () => {
            if (!titleModal) {
                return;
            }

            titleModal.classList.add('hidden');
            titleModal.classList.remove('flex');
        };

        const saveNotificationsPreference = async (form, checkbox) => {
            if (!form || !checkbox || form.dataset.loading === 'true') {
                return;
            }

            form.dataset.loading = 'true';
            checkbox.disabled = true;

            try {
                const payload = new FormData(form);
                payload.delete('notifications_enabled');
                payload.set('notifications_enabled', checkbox.checked ? '1' : '0');

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: payload,
                });

                if (!response.ok) {
                    checkbox.checked = !checkbox.checked;
                    return;
                }

                const result = await response.json();
                checkbox.checked = Boolean(result?.enabled);

                window.dispatchEvent(new CustomEvent('forum-notifications-refresh'));
            } catch (_error) {
                checkbox.checked = !checkbox.checked;
            } finally {
                delete form.dataset.loading;
                checkbox.disabled = false;
            }
        };

        const bindComposerButtons = () => {
            root.querySelectorAll('[data-forum-reply-button]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openReplyModal(
                        button.dataset.replyTitle || 'Reply',
                        button.dataset.replyBody || '',
                        button.dataset.replyPostId || ''
                    );
                });
            });

            root.querySelectorAll('[data-forum-edit-button]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const article = button.closest('article');
                    const attachmentsHtml = article?.querySelector('[data-forum-post-attachments] [data-forum-attachments-list]')?.innerHTML
                        || article?.querySelector('[data-forum-post-attachments]')?.innerHTML
                        || '';
                    openComposerModal({
                        title: button.dataset.editTitle || 'Edit Reply',
                        html: button.dataset.editBody || '',
                        action: button.dataset.editAction || replyForm?.action || '',
                        method: button.dataset.editMethod || 'PUT',
                        submitLabel: button.dataset.submitLabel || 'Save Changes',
                        mode: 'edit',
                        editPostId: button.dataset.editPostId || '',
                        currentAttachmentsHtml: attachmentsHtml,
                        resetRemovedAttachments: true,
                    });
                });
            });

            root.querySelectorAll('[data-forum-report-button]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openReportModal({
                        action: button.dataset.reportAction || '',
                        author: button.dataset.reportAuthor || 'this post',
                        postId: button.dataset.reportPostId || '',
                    });
                });
            });
        };

        const bindTitleButtons = () => {
            root.querySelectorAll('[data-forum-title-button]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openTitleModal();
                });
            });
        };

        const updateReactionButton = (button) => {
            const postId = button.dataset.postId || '';
            const type = button.dataset.type || '';
            const activeClasses = button.dataset.activeClasses || '';
            const label = button.dataset.label || '';
            const currentState = reactionState[postId] || {current: null, counts: {}, tooltips: {}};
            const isActive = currentState.current === type;

            button.className = `${reactionBaseButtonClasses} ${isActive ? activeClasses : reactionIdleClasses}`.trim();
            button.disabled = !!reactionLoading[postId];
            button.title = `${label}: ${currentState.tooltips?.[type] || 'No reactions yet'}`;

            const countElement = button.querySelector('[data-forum-reaction-count]');
            if (countElement) {
                countElement.textContent = String(currentState.counts?.[type] ?? 0);
            }
        };

        const bindReactionButtons = () => root.querySelectorAll('[data-forum-reaction-button]').forEach((button) => {
            updateReactionButton(button);

            button.addEventListener('click', async (event) => {
                event.preventDefault();

                const url = button.dataset.url || '';
                const postId = button.dataset.postId || '';
                const type = button.dataset.type || '';

                if (!url || !postId || !type || reactionLoading[postId]) {
                    return;
                }

                reactionLoading[postId] = true;
                root.querySelectorAll(`[data-forum-reaction-button][data-post-id="${postId}"]`).forEach(updateReactionButton);

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({type}),
                    });

                    if (!response.ok) {
                        throw new Error('Could not update reaction.');
                    }

                    const payload = await response.json();
                    reactionState[postId] = payload.reactions || {};
                    root.querySelectorAll(`[data-forum-reaction-button][data-post-id="${postId}"]`).forEach(updateReactionButton);
                } catch (error) {
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Action failed', error.message || 'Could not update reaction.', 'danger');
                    }
                } finally {
                    reactionLoading[postId] = false;
                    root.querySelectorAll(`[data-forum-reaction-button][data-post-id="${postId}"]`).forEach(updateReactionButton);
                }
            });
        });

        const bindTopicInteractions = () => {
            bindReactionButtons();
            bindComposerButtons();
            bindTitleButtons();
        };

        currentAttachmentsList?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-forum-attachment-remove]');
            if (!removeButton) {
                return;
            }

            event.preventDefault();

            const row = removeButton.closest('[data-forum-attachment-row]');
            if (row) {
                const attachmentId = row.dataset.attachmentId || removeButton.dataset.attachmentId || '';
                if (!attachmentId) {
                    return;
                }

                row.remove();
                appendRemovedAttachmentInput(attachmentId);
                syncCurrentAttachmentsVisibility();
                return;
            }

            const queuedRow = removeButton.closest('[data-forum-queued-attachment-row]');
            const index = Number(queuedRow?.dataset.queuedAttachmentIndex || removeButton.dataset.queuedAttachmentIndex || '-1');
            if (!Number.isInteger(index) || index < 0) {
                return;
            }

            removeQueuedAttachmentAt(index);
        });

        addFilesButton?.addEventListener('click', (event) => {
            event.preventDefault();
            replyAttachmentsInput?.click();
        });

        replyAttachmentsInput?.addEventListener('change', () => {
            if (replyAttachmentsInput.files && replyAttachmentsInput.files.length > 0) {
                addQueuedAttachments(Array.from(replyAttachmentsInput.files));
            }
        });

        root.addEventListener('change', (event) => {
            const checkbox = event.target.closest('[data-forum-notifications-toggle]');
            if (!checkbox) {
                return;
            }

            const form = checkbox.closest('[data-forum-notifications-form]');
            saveNotificationsPreference(form, checkbox);
        });

        root.querySelectorAll('[data-forum-reply-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (replyModalSaving) {
                    return;
                }
                closeReplyModal();
            });
        });

        modal?.addEventListener('click', (event) => {
            if (replyModalSaving) {
                event.preventDefault();
                return;
            }

            if (event.target === modal) {
                closeReplyModal();
            }
        });

        root.querySelectorAll('[data-forum-report-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                closeReportModal();
            });
        });

        reportModal?.addEventListener('click', (event) => {
            if (event.target === reportModal) {
                closeReportModal();
            }
        });

        root.querySelectorAll('[data-forum-title-close]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                closeTitleModal();
            });
        });

        titleModal?.addEventListener('click', (event) => {
            if (event.target === titleModal) {
                closeTitleModal();
            }
        });

        replyForm?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || (!event.metaKey && !event.ctrlKey)) {
                return;
            }

            event.preventDefault();

            if (typeof replyForm.requestSubmit === 'function') {
                replyForm.requestSubmit();
                return;
            }

            replyForm.submit();
        });

        replyForm?.addEventListener('submit', (event) => {
            const hasQueuedFiles = (attachmentQueue.files?.length || 0) > 0;
            if (!hasQueuedFiles) {
                return;
            }

            event.preventDefault();
            clearSubmitErrors();

            syncAttachmentInput();
            const payload = new FormData(replyForm);
            setSavingState(true, 0);
            const xhr = new XMLHttpRequest();

            xhr.open('POST', replyForm.action);
            xhr.responseType = 'json';
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            const csrfToken = config.csrfToken || '';
            if (csrfToken !== '') {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }

            xhr.upload.addEventListener('progress', (progressEvent) => {
                if (!progressEvent.lengthComputable) {
                    return;
                }

                const percent = Math.max(0, Math.min(100, (progressEvent.loaded / progressEvent.total) * 100));
                setSavingState(true, percent);
            });

            xhr.addEventListener('load', () => {
                const response = xhr.response || {};
                setSavingState(false);

                if (xhr.status >= 200 && xhr.status < 300 && response.redirect) {
                    window.location.assign(response.redirect);
                    return;
                }

                if (xhr.status === 422 && response.errors) {
                    renderSubmitErrors(response.errors);
                    return;
                }

                renderSubmitErrors({
                    body: [response.message || 'Unable to save the post.'],
                });
            });

            xhr.addEventListener('error', () => {
                setSavingState(false);
                renderSubmitErrors({
                    body: ['Unable to save the post. Please try again.'],
                });
            });

            xhr.send(payload);
        });

        const refreshTopicSnapshot = async () => {
            if (!snapshotUrl || !topicMetaContainer || !firstPostContainer || !repliesContainer || !paginationContainer || !replyPanelContainer) {
                return;
            }

            if ((modal && !modal.classList.contains('hidden')) || (reportModal && !reportModal.classList.contains('hidden')) || (titleModal && !titleModal.classList.contains('hidden'))) {
                return;
            }

            try {
                const response = await fetch(snapshotUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                topicMetaContainer.innerHTML = payload.topicMetaHtml || '';
                firstPostContainer.innerHTML = payload.firstPostHtml || '';
                repliesContainer.innerHTML = payload.repliesHtml || '';
                paginationContainer.innerHTML = payload.paginationHtml || '';
                replyPanelContainer.innerHTML = payload.replyPanelHtml || '';
                Object.keys(reactionState).forEach((key) => delete reactionState[key]);
                Object.assign(reactionState, payload.reactionState || {});
                bindTopicInteractions();
                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(topicMetaContainer);
                    window.Alpine.initTree(firstPostContainer);
                    window.Alpine.initTree(repliesContainer);
                    window.Alpine.initTree(replyPanelContainer);
                }
                window.dispatchEvent(new CustomEvent('forum-notifications-refresh'));
            } catch (_error) {
            }
        };

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden') && !replyModalSaving) {
                closeReplyModal();
            }

            if (event.key === 'Escape' && reportModal && !reportModal.classList.contains('hidden')) {
                closeReportModal();
            }

            if (event.key === 'Escape' && titleModal && !titleModal.classList.contains('hidden')) {
                closeTitleModal();
            }
        });

        if (config.replyModalOpen) {
            openComposerModal({
                title: modalTitle?.textContent || 'Reply',
                html: config.initialReplyBody || '',
                action: replyForm?.action || '',
                method: replyMethodInput?.value === 'PUT' ? 'PUT' : 'POST',
                submitLabel: replySubmitLabel?.textContent || 'Post Reply',
                mode: replyModeInput?.value || 'reply',
                editPostId: replyEditPostIdInput?.value || '',
                replyPostId: config.initialReplyPostId || '',
                resetRemovedAttachments: false,
            });
        }

        if (config.reportModalOpen) {
            openReportModal({
                action: config.initialReportAction || '',
                author: config.initialReportAuthor || 'this post',
                postId: config.initialReportPostId || '',
            });
        }

        if (config.titleModalOpen) {
            openTitleModal();
        }

        bindTopicInteractions();
        window.dispatchEvent(new CustomEvent('forum-notifications-refresh'));
        if (snapshotUrl) {
            window.setInterval(() => {
                if (document.visibilityState === 'visible') {
                    refreshTopicSnapshot();
                }
            }, 15000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    refreshTopicSnapshot();
                }
            });
        }
    });
</script>
