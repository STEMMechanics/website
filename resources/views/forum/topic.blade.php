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
    $reportModalOpen = $errors->has('reason');
    $reportAction = trim((string) old('report_action', ''));
    $reportPostId = trim((string) old('report_post_id', ''));
    $reportAuthor = trim((string) old('report_author', ''));

    $topicPageConfig = [
        'reactionState' => $reactionState,
        'csrfToken' => csrf_token(),
        'replyModalOpen' => $errors->has('body'),
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
    ];
@endphp

<script>
    window.forumTopicPageConfig = @json($topicPageConfig);
</script>

<x-layout>
    <x-mast backRoute="forum.category.show" :backRouteParams="[$category->slug]" backTitle="{{ $category->name }}">{{ $topic->title }}</x-mast>

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
                <form method="POST" action="{{ $modalAction }}" class="min-h-0 flex-1 overflow-y-auto" data-default-action="{{ route('forum.post.store', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort]) }}" data-default-method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="forum-reply-modal-method" value="{{ $modalMethod === 'PUT' ? 'PUT' : '' }}">
                    <input type="hidden" name="modal_mode" id="forum-reply-modal-mode" value="{{ $modalMode }}">
                    <input type="hidden" name="edit_post_id" id="forum-reply-edit-post-id" value="{{ $editingPost?->id }}">
                    <input type="hidden" name="reply_to_post_id" id="forum-reply-to-post-id" value="{{ $modalReplyPostId }}">
                    <x-ui.editor name="body" :value="old('body', $replyPrefillBody)" label="" :allowHeadings="false" class="forum-reply-editor !border-0 mt-0" />
                    <div class="flex shrink-0 justify-end gap-3 px-6 py-5 border-t border-gray-300">
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
        const modalTitle = document.getElementById('forum-reply-modal-title');
        const modalDescription = document.getElementById('forum-reply-modal-description');
        const replyForm = modal?.querySelector('form') || null;
        const reportForm = document.getElementById('forum-report-form');
        const replyMethodInput = document.getElementById('forum-reply-modal-method');
        const replyModeInput = document.getElementById('forum-reply-modal-mode');
        const replyEditPostIdInput = document.getElementById('forum-reply-edit-post-id');
        const replyToPostIdInput = document.getElementById('forum-reply-to-post-id');
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

        const setReplyEditorContent = (html = '') => {
            window.dispatchEvent(new CustomEvent('sm-editor-set-content', {
                detail: {
                    name: 'body',
                    html,
                    focusEnd: true,
                },
            }));
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
        } = {}) => {
            if (!modal || !modalTitle) {
                return;
            }

            modalTitle.textContent = title;
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

        const saveNotificationsPreference = async (form, checkbox) => {
            if (!form || !checkbox || form.dataset.loading === 'true') {
                return;
            }

            form.dataset.loading = 'true';
            checkbox.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new FormData(form),
                });

                if (!response.ok) {
                    checkbox.checked = !checkbox.checked;
                    return;
                }

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
                    openComposerModal({
                        title: button.dataset.editTitle || 'Edit Reply',
                        html: button.dataset.editBody || '',
                        action: button.dataset.editAction || replyForm?.action || '',
                        method: button.dataset.editMethod || 'PUT',
                        submitLabel: button.dataset.submitLabel || 'Save Changes',
                        mode: 'edit',
                        editPostId: button.dataset.editPostId || '',
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
        };

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
                closeReplyModal();
            });
        });

        modal?.addEventListener('click', (event) => {
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

        const refreshTopicSnapshot = async () => {
            if (!snapshotUrl || !topicMetaContainer || !firstPostContainer || !repliesContainer || !paginationContainer || !replyPanelContainer) {
                return;
            }

            if ((modal && !modal.classList.contains('hidden')) || (reportModal && !reportModal.classList.contains('hidden'))) {
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
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeReplyModal();
            }

            if (event.key === 'Escape' && reportModal && !reportModal.classList.contains('hidden')) {
                closeReportModal();
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
            });
        }

        if (config.reportModalOpen) {
            openReportModal({
                action: config.initialReportAction || '',
                author: config.initialReportAuthor || 'this post',
                postId: config.initialReportPostId || '',
            });
        }

        bindTopicInteractions();
        window.dispatchEvent(new CustomEvent('forum-notifications-refresh'));
        if (snapshotUrl) {
            topicPollHandle = window.setInterval(() => {
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
