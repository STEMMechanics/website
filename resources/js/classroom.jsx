import './bootstrap';

import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    LiveKitRoom,
    RoomAudioRenderer,
    TrackToggle,
    VideoTrack,
    useMediaDeviceSelect,
    useDataChannel,
    useLocalParticipant,
    useLocalParticipantPermissions,
    useRoomContext,
    useParticipants,
    useTracks,
} from '@livekit/components-react';
import { ConnectionState, Track } from 'livekit-client';

const ROOT_ID = 'classroom-root';
const TRACK_SOURCES = [
    { source: Track.Source.ScreenShare, withPlaceholder: true },
    { source: Track.Source.Camera, withPlaceholder: true },
];

function readInitialState() {
    const root = document.getElementById(ROOT_ID);
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    try {
        return JSON.parse(root.dataset.state || '{}');
    } catch (_error) {
        return null;
    }
}

function readRootConfig() {
    const root = document.getElementById(ROOT_ID);
    if (!(root instanceof HTMLElement)) {
        return null;
    }

    return {
        livekitUrl: root.dataset.livekitUrl || '',
        tokenEndpoint: root.dataset.tokenEndpoint || '',
        helpRequestsEndpoint: root.dataset.helpRequestsEndpoint || '',
        helpRequestStoreEndpoint: root.dataset.helpRequestStoreEndpoint || '',
        helpRequestApprovePattern: root.dataset.helpRequestApprovePattern || '',
        helpRequestRevokePattern: root.dataset.helpRequestRevokePattern || '',
        broadcastStartEndpoint: root.dataset.broadcastStartEndpoint || '',
        broadcastEndEndpoint: root.dataset.broadcastEndEndpoint || '',
        chatStoreEndpoint: root.dataset.chatStoreEndpoint || '',
        clientErrorEndpoint: root.dataset.clientErrorEndpoint || '',
        csrfToken: root.dataset.csrfToken || document.head.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

function mergeClassroomState(currentState, nextState) {
    if (!currentState) {
        return nextState;
    }

    if (!nextState) {
        return currentState;
    }

    const nextKeys = Object.keys(nextState);
    const isPartialClassSessionUpdate = nextKeys.length === 1 && nextState.classSession && !nextState.viewer;

    if (isPartialClassSessionUpdate) {
        return {
            ...currentState,
            classSession: {
                ...currentState.classSession,
                ...nextState.classSession,
            },
        };
    }

    return nextState;
}

function asArray(value) {
    return Array.isArray(value) ? value : [];
}

function getParticipantRole(participant) {
    return String(participant?.attributes?.app_user_role || participant?.attributes?.role || participant?.metadata?.role || '');
}

function getParticipantUserId(participant) {
    return String(participant?.attributes?.app_user_id || participant?.attributes?.user_id || participant?.metadata?.user_id || '');
}

function getParticipantDisplayName(participant) {
    return String(participant?.name || participant?.attributes?.app_user_name || participant?.attributes?.app_user_username || participant?.identity || 'Participant');
}

function getParticipantUsername(participant) {
    return String(participant?.attributes?.app_user_username || participant?.attributes?.username || participant?.metadata?.username || getParticipantDisplayName(participant));
}

function resolveParticipantUserId(participant, state) {
    const directUserId = getParticipantUserId(participant);
    if (directUserId !== '') {
        return directUserId;
    }

    const identity = String(participant?.identity || '');
    const identityMatch = identity.match(/-user-([0-9a-f-]{36})$/i);
    if (identityMatch?.[1]) {
        return identityMatch[1];
    }

    const targetUsername = getParticipantUsername(participant).trim().toLowerCase();
    const targetName = getParticipantDisplayName(participant).trim().toLowerCase();

    const enrolment = asArray(state?.enrolments).find((item) => {
        const enrolmentUsername = String(item?.username || '').trim().toLowerCase();
        const enrolmentName = String(item?.name || '').trim().toLowerCase();

        return (
            (targetUsername !== '' && enrolmentUsername === targetUsername)
            || (targetName !== '' && enrolmentName === targetName)
        );
    });

    return String(enrolment?.userId || enrolment?.user_id || '');
}

function resolveParticipantIdentity(participant) {
    return String(participant?.identity || '');
}

function getParticipantRoleLabel(participant) {
    return getParticipantRole(participant) === 'teacher' ? 'teacher' : 'student';
}

function getParticipantLabel(participant) {
    return `${getParticipantUsername(participant)} (${getParticipantRoleLabel(participant)})`;
}

function getChatMessageUsername(chatMessage) {
    return String(chatMessage?.username || chatMessage?.name || 'Participant');
}

function getChatMessageRole(chatMessage) {
    return String(chatMessage?.role || (chatMessage?.isTeacher ? 'teacher' : 'student'));
}

function getTrackDisplaySource(trackRef) {
    return Number(trackRef?.source ?? Track.Source.Unknown);
}

function parseClassroomDateTime(value) {
    const raw = String(value || '').trim();
    if (raw === '') {
        return null;
    }

    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatClassroomTimeLabel(date, options = {}) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return '';
    }

    const localeOptions = {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        ...(options.includeYear ? {year: 'numeric'} : {}),
    };

    return date.toLocaleString([], localeOptions);
}

function formatClassroomCountdown(targetDate) {
    if (!(targetDate instanceof Date) || Number.isNaN(targetDate.getTime())) {
        return '';
    }

    const now = new Date();
    const diffMs = targetDate.getTime() - now.getTime();
    const diffAbs = Math.abs(diffMs);
    const minutes = Math.round(diffAbs / 60000);
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (diffMs < 0) {
        if (minutes <= 60) {
            return `Started ${minutes <= 1 ? 'just now' : `${minutes}m ago`}`;
        }

        return `Started ${hours}h ${remainingMinutes}m ago`;
    }

    if (diffMs <= 24 * 60 * 60 * 1000) {
        if (minutes < 60) {
            return `Starts in ${minutes <= 1 ? '1 minute' : `${minutes} minutes`}`;
        }

        return `Starts in ${hours}h ${remainingMinutes}m`;
    }

    return formatClassroomTimeLabel(targetDate);
}

function parseClassroomSchedule(schedule) {
    return asArray(schedule)
        .map((entry) => ({
            startsAt: parseClassroomDateTime(entry?.startsAt || entry?.starts_at),
            endsAt: parseClassroomDateTime(entry?.endsAt || entry?.ends_at),
        }))
        .filter((entry) => entry.startsAt || entry.endsAt)
        .sort((left, right) => {
            const leftTime = left.startsAt ? left.startsAt.getTime() : Number.MAX_SAFE_INTEGER;
            const rightTime = right.startsAt ? right.startsAt.getTime() : Number.MAX_SAFE_INTEGER;
            return leftTime - rightTime;
        });
}

function getClassroomIdleState(schedule) {
    const parsedSchedule = parseClassroomSchedule(schedule);
    const now = Date.now();
    const lateStartWindowMs = 60 * 60 * 1000;
    const relevantSession = parsedSchedule.find((entry) => {
        if (!entry.startsAt) {
            return false;
        }

        const startsAt = entry.startsAt.getTime();
        return startsAt >= (now - lateStartWindowMs);
    }) || null;

    if (relevantSession?.startsAt) {
        const diffMs = relevantSession.startsAt.getTime() - now;
        if (diffMs < 0 && Math.abs(diffMs) <= lateStartWindowMs) {
            return {
                mode: 'expanded',
                summary: 'The next live stream begins shortly.',
                icon: 'fa-solid fa-circle-play',
            };
        }

        if (diffMs > 0 && diffMs <= 24 * 60 * 60 * 1000) {
            return {
                mode: 'compact',
                summary: formatClassroomCountdown(relevantSession.startsAt)
                    .replace(/^Starts in\s+/i, 'The next live stream begins in ')
                    .replace(/^Started\s+/i, 'The next live stream began '),
                icon: 'fa-solid fa-calendar-clock',
            };
        }

        if (diffMs > 24 * 60 * 60 * 1000) {
            return {
                mode: 'compact',
                summary: `The next live stream begins at ${formatClassroomTimeLabel(relevantSession.startsAt, {includeYear: true})}`,
                icon: 'fa-solid fa-calendar-clock',
            };
        }
    }

    return {
        mode: 'compact',
        summary: 'No more live streams are scheduled.',
        icon: 'fa-regular fa-calendar-xmark',
    };
}

function pickTrackForParticipant(trackRefs, participantId) {
    const participantTracks = trackRefs.filter((trackRef) => trackRef?.participant?.identity === participantId);
    if (participantTracks.length === 0) {
        return null;
    }

    const screenShare = participantTracks.find((trackRef) => getTrackDisplaySource(trackRef) === Track.Source.ScreenShare);
    if (screenShare) {
        return screenShare;
    }

    return participantTracks.find((trackRef) => getTrackDisplaySource(trackRef) === Track.Source.Camera) || participantTracks[0];
}

function pickTeacherParticipant(participants) {
    return participants.find((participant) => getParticipantRole(participant) === 'teacher') || participants[0] || null;
}

function pickPresenterParticipant(participants, teacherUserId) {
    const teacherId = String(teacherUserId || '');
    if (teacherId !== '') {
        const enrolledTeacher = pickParticipantByUserId(participants, teacherId);
        if (enrolledTeacher) {
            return enrolledTeacher;
        }
    }

    return pickTeacherParticipant(participants);
}

function pickParticipantByUserId(participants, userId) {
    const target = String(userId || '');
    if (target === '') {
        return null;
    }

    return participants.find((participant) => getParticipantUserId(participant) === target) || null;
}

function pickParticipantByIdentity(participants, identity) {
    const target = String(identity || '');
    if (target === '') {
        return null;
    }

    return participants.find((participant) => String(participant?.identity || '') === target) || null;
}

function getParticipantAvatarUrl(participant) {
    return String(participant?.attributes?.app_user_avatar_url || participant?.metadata?.avatar?.url || '');
}

function getParticipantAvatarLetters(participant) {
    const avatarLetters = String(participant?.attributes?.app_user_avatar_letters || participant?.metadata?.avatar?.letters || '').trim();
    if (avatarLetters !== '') {
        return avatarLetters.slice(0, 3).toUpperCase();
    }

    const displayName = getParticipantDisplayName(participant).trim();
    if (displayName === '') {
        return 'P';
    }

    const parts = displayName.split(/\s+/).filter(Boolean);
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
}

function getParticipantAvatarBackgroundColor(participant) {
    return String(participant?.attributes?.app_user_avatar_background_color || participant?.metadata?.avatar?.backgroundColor || '#334155');
}

function getParticipantAvatarIconClass(participant) {
    return String(participant?.attributes?.app_user_avatar_icon_class || participant?.metadata?.avatar?.iconClass || '').trim();
}

function getParticipantAvatarMode(participant) {
    return String(participant?.attributes?.app_user_avatar_mode || participant?.metadata?.avatar?.mode || '');
}

function canPublishSource(permissions, source) {
    if (!permissions) {
        return true;
    }

    if (permissions.canPublish === false) {
        return false;
    }

    const sources = asArray(permissions.canPublishSources);
    if (sources.length === 0) {
        return true;
    }

    return sources.includes(source);
}

function sortParticipantsForChat(participants, presenterIdentity) {
    const presenter = String(presenterIdentity || '');

    return [...participants].sort((left, right) => {
        const leftRole = getParticipantRole(left);
        const rightRole = getParticipantRole(right);

        if (leftRole !== rightRole) {
            if (leftRole === 'teacher') {
                return -1;
            }

            if (rightRole === 'teacher') {
                return 1;
            }
        }

        const leftIsPresenter = presenter !== '' && left.identity === presenter;
        const rightIsPresenter = presenter !== '' && right.identity === presenter;

        if (leftIsPresenter !== rightIsPresenter) {
            return leftIsPresenter ? -1 : 1;
        }

        return getParticipantDisplayName(left).localeCompare(getParticipantDisplayName(right));
    });
}

function helpRequestTargetsCurrentParticipant(helpRequest, participantIdentity) {
    const targetIdentity = String(helpRequest?.targetParticipantIdentity || helpRequest?.target_participant_identity || '');
    return targetIdentity !== '' && String(participantIdentity || '') !== '' && targetIdentity === String(participantIdentity || '');
}

function describeHelpRequestResolution(helpRequest) {
    if (!helpRequest) {
        return '';
    }

    const requestedFor = String(helpRequest.requestedForName || 'The student').trim() || 'The student';
    const reason = String(helpRequest.resolutionReason || '').trim();

    if (helpRequest.status === 'done') {
        return reason !== '' ? `${requestedFor}: ${reason}` : `${requestedFor} stopped sharing.`;
    }

    if (helpRequest.status === 'rejected') {
        return reason !== '' ? `${requestedFor}: ${reason}` : `${requestedFor} rejected the request.`;
    }

    return '';
}

function ParticipantAvatar({
    participant,
    title = null,
    onClick = null,
    showTeacherBadge = false,
    showPresenterBadge = false,
    requestBadgeTitle = null,
    requestBadgeIconClass = null,
    onRequestBadgeClick = null,
}) {
    const avatarUrl = getParticipantAvatarUrl(participant);
    const avatarMode = getParticipantAvatarMode(participant);
    const avatarLetters = getParticipantAvatarLetters(participant);
    const avatarIconClass = getParticipantAvatarIconClass(participant);
    const backgroundColor = getParticipantAvatarBackgroundColor(participant);
    const displayName = getParticipantDisplayName(participant);
    const clickable = typeof onClick === 'function';
    const requestBadgeClickable = typeof onRequestBadgeClick === 'function' && requestBadgeIconClass !== null;

    return (
        <div className="relative inline-flex overflow-visible">
            {clickable ? (
                <button
                    type="button"
                    onClick={onClick}
                    title={title || displayName}
                    className="group relative flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white/70 transition hover:scale-[1.03] focus:outline-none focus:ring-2 focus:ring-sky-300"
                    style={{backgroundColor}}
                >
                    {avatarUrl && avatarMode === 'media' ? (
                        <img src={avatarUrl} alt="" className="h-full w-full object-cover" />
                    ) : avatarIconClass ? (
                        <span className="flex h-full w-full items-center justify-center text-white">
                            <i className={`${avatarIconClass} text-lg`} aria-hidden="true"></i>
                        </span>
                    ) : (
                        <span className="px-1 text-sm font-semibold uppercase tracking-wide text-white">{avatarLetters}</span>
                    )}
                </button>
            ) : (
                <div
                    title={title || displayName}
                    className="relative flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white/70"
                    style={{backgroundColor}}
                >
                    {avatarUrl && avatarMode === 'media' ? (
                        <img src={avatarUrl} alt="" className="h-full w-full object-cover" />
                    ) : avatarIconClass ? (
                        <span className="flex h-full w-full items-center justify-center text-white">
                            <i className={`${avatarIconClass} text-lg`} aria-hidden="true"></i>
                        </span>
                    ) : (
                        <span className="px-1 text-sm font-semibold uppercase tracking-wide text-white">{avatarLetters}</span>
                    )}
                </div>
            )}

            {showTeacherBadge ? (
                <span
                    className="absolute -left-1 -top-1 inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border border-white bg-slate-950 shadow-sm"
                    title="Teacher"
                    aria-label="Teacher"
                >
                    <img src="/toolbox-sm.png" alt="" className="h-full w-full object-contain p-0.5" />
                </span>
            ) : null}

            {showPresenterBadge ? (
                <span
                    className="absolute -right-1 -top-1 h-5 w-5 rounded-full border-2 border-white bg-emerald-500 shadow-sm"
                    title="Presenter"
                    aria-label="Presenter"
                />
            ) : null}

            {(requestBadgeTitle || requestBadgeClickable) ? (
                requestBadgeClickable ? (
                    <button
                        type="button"
                        onClick={onRequestBadgeClick}
                        title={requestBadgeTitle || displayName}
                        aria-label={requestBadgeTitle || displayName}
                        className="absolute -bottom-1 -left-1 inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] text-white shadow-sm transition hover:scale-105 hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-300"
                    >
                        <i className={requestBadgeIconClass || 'fa-solid fa-video'} aria-hidden="true"></i>
                    </button>
                ) : (
                    <span
                        title={requestBadgeTitle || displayName}
                        className="absolute -bottom-1 -left-1 inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] text-white shadow-sm"
                    >
                        <i className={requestBadgeIconClass || 'fa-solid fa-video'} aria-hidden="true"></i>
                    </span>
                )
            ) : null}
        </div>
    );
}

async function requestJson(url, { method = 'POST', body = null, csrfToken = '' } = {}) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {}),
        },
        body: body ? JSON.stringify(body) : null,
    });

    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error((payload && payload.message) || `Request failed with status ${response.status}`);
    }

    return payload;
}

async function reportClientError(endpoint, { csrfToken = '', message = '', source = '', stack = '', context = {} } = {}) {
    if (!endpoint) {
        return;
    }

    try {
        await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {}),
            },
            body: JSON.stringify({
                message,
                source,
                stack,
                context,
            }),
            keepalive: true,
        });
    } catch (_error) {
    }
}

function ParticipantMediaCard({ title, subtitle, trackRef, emptyLabel, headerActions = null, panelId = null, compactWhenEmpty = false, embedded = false }) {
    const hasRenderableTrack = Boolean(trackRef?.publication?.track);
    const emptyContainerClasses = compactWhenEmpty ? 'flex min-h-[8rem] w-full items-center justify-center p-4' : 'flex min-h-[18rem] w-full items-center justify-center bg-slate-900 p-4';
    const rootClasses = embedded
        ? 'flex h-full min-h-0 flex-col overflow-hidden bg-slate-950'
        : 'overflow-hidden rounded-lg border border-slate-200 bg-slate-950 shadow-lg';

    return (
        <section id={panelId || undefined} className={rootClasses}>
            <div className="flex items-start justify-between gap-3 border-b border-slate-800 bg-slate-900 px-5 py-4 text-white">
                <div className="min-w-0">
                    {subtitle ? <div className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-200">{subtitle}</div> : null}
                    <h2 className={subtitle ? 'mt-1 text-xl font-semibold' : 'text-xl font-semibold'}>{title}</h2>
                </div>
                {headerActions ? <div className="flex flex-wrap items-center justify-end gap-2">{headerActions}</div> : null}
            </div>
            <div className={hasRenderableTrack ? 'flex flex-1 items-center justify-center bg-slate-900 p-4' : `${emptyContainerClasses} flex-1`}>
                {hasRenderableTrack ? (
                    <div className="flex h-full w-full min-h-[24rem] items-center justify-center overflow-hidden rounded-lg bg-black">
                        <VideoTrack
                            trackRef={trackRef}
                            className="h-full w-full object-contain"
                        />
                    </div>
                ) : (
                    <div className="flex h-full w-full flex-1 items-center justify-center p-4">
                        <div className={`max-w-2xl rounded-lg border px-5 py-4 text-center shadow-lg ${compactWhenEmpty ? 'border-slate-200 bg-white/95 text-slate-800' : 'border-white/10 bg-slate-800/90 text-slate-200'}`}>
                            <div className={`text-sm font-medium leading-6 ${compactWhenEmpty ? 'text-slate-700' : 'text-slate-200'}`}>
                                {emptyLabel}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

function CameraSourceMenu({ track, disabled = false, onDeviceError = null }) {
    const { devices, activeDeviceId, setActiveMediaDevice } = useMediaDeviceSelect({
        kind: 'videoinput',
        track,
        requestPermissions: false,
        onError: onDeviceError,
    });
    const [open, setOpen] = useState(false);
    const menuRef = React.useRef(null);
    const buttonRef = React.useRef(null);

    useEffect(() => {
        if (!open) {
            return () => {};
        }

        const handlePointerDown = (event) => {
            const target = event.target;
            if (!(target instanceof Node)) {
                return;
            }

            if (menuRef.current?.contains(target) || buttonRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
        };

        document.addEventListener('pointerdown', handlePointerDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
        };
    }, [open]);

    const selectDevice = async (deviceId) => {
        try {
            await setActiveMediaDevice(deviceId);
            setOpen(false);
        } catch (error) {
            if (typeof onDeviceError === 'function' && error instanceof Error) {
                onDeviceError(error);
            }
        }
    };

    return (
        <div className="relative">
            <button
                type="button"
                ref={buttonRef}
                onClick={() => setOpen((current) => !current)}
                disabled={disabled}
                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/15 text-white transition hover:bg-white/25 disabled:cursor-not-allowed disabled:opacity-40"
                title="Camera source"
                aria-label="Camera source"
            >
                <i className="fa-solid fa-video" aria-hidden="true"></i>
            </button>
            {open ? (
                <div
                    ref={menuRef}
                    className="absolute right-0 top-full z-20 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-xl"
                >
                    <div className="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Camera source
                    </div>
                    <div className="mt-1 max-h-64 overflow-y-auto">
                        {devices.length > 0 ? devices.map((device) => (
                            <button
                                key={device.deviceId}
                                type="button"
                                onClick={() => void selectDevice(device.deviceId)}
                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition hover:bg-slate-100 ${
                                    device.deviceId === activeDeviceId ? 'bg-sky-50 text-sky-800' : 'text-slate-700'
                                }`}
                            >
                                <span className="truncate">{device.label || 'Camera'}</span>
                                {device.deviceId === activeDeviceId ? (
                                    <i className="fa-solid fa-check text-xs text-sky-600" aria-hidden="true"></i>
                                ) : null}
                            </button>
                        )) : (
                            <div className="px-3 py-2 text-sm text-slate-500">
                                No cameras found.
                            </div>
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function LiveChatPanel({
    state,
    participants,
    presenterParticipant,
    hasPresenterStream = false,
    tokenInfo,
    chatStoreEndpoint,
    helpRequestStoreEndpoint,
    csrfToken,
    onStateUpdate,
    onPromoteRequest,
    onApproveAndStartRequest,
    onDismissRequest,
    embedded = false,
}) {
    const room = useRoomContext();
    const [messages, setMessages] = useState(() => asArray(state.chatMessages));
    const [draft, setDraft] = useState('');
    const [error, setError] = useState('');
    const [requestTarget, setRequestTarget] = useState(null);
    const { message, send, isSending } = useDataChannel('classroom-chat');
    const currentMine = state.helpRequests?.mine
        || [...asArray(state.helpRequests?.pending), ...asArray(state.helpRequests?.active ? [state.helpRequests.active] : [])]
            .find((request) => helpRequestTargetsCurrentParticipant(request, tokenInfo?.participantIdentity))
        || null;
    const orderedParticipants = sortParticipantsForChat(asArray(participants), state.helpRequests?.active?.userId);
    const teacherConnected = orderedParticipants.some((participant) => getParticipantRole(participant) === 'teacher');
    const isTeacher = state.viewer?.role === 'teacher';
    const chatEnabled = Boolean(state.classSession?.liveChatEnabled && teacherConnected);
    const pendingRequestByUserId = new Map(asArray(state.helpRequests?.pending).map((request) => [String(request.userId || ''), request]));
    const recentRequest = state.helpRequests?.recent || null;
    useEffect(() => {
        setMessages((current) => {
            const next = [...current];
            asArray(state.chatMessages).forEach((chatMessage) => {
                if (!next.some((item) => item.id === chatMessage.id)) {
                    next.push(chatMessage);
                }
            });
            return next.slice(-50);
        });
    }, [state.chatMessages]);

    useEffect(() => {
        const payload = message?.payload;
        if (!payload) {
            return;
        }

        try {
            const parsed = JSON.parse(new TextDecoder().decode(payload));
            if (!parsed?.id || !parsed?.message) {
                return;
            }

            setMessages((current) => {
                if (current.some((item) => item.id === parsed.id)) {
                    return current;
                }

                return [...current, parsed].slice(-50);
            });
        } catch (_error) {
        }
    }, [message?.payload]);

    const sendMessage = async (event) => {
        event.preventDefault();
        if (!chatEnabled) {
            setError('Chat is available when the teacher is connected.');
            return;
        }

        const text = draft.trim();
        if (text === '') {
            return;
        }

        setError('');
        try {
            const payload = await requestJson(chatStoreEndpoint, {
                body: {
                    message: text,
                },
                csrfToken,
            });

            onStateUpdate(payload.state || state);

            const chatMessage = payload.chatMessage;
            if (chatMessage?.id) {
                setMessages((current) => {
                    if (current.some((item) => item.id === chatMessage.id)) {
                        return current;
                    }

                    return [...current, chatMessage].slice(-50);
                });

                await send(new TextEncoder().encode(JSON.stringify(chatMessage)), {reliable: true});
            }

            setDraft('');
        } catch (caughtError) {
            setError(caughtError.message || 'Could not send chat message.');
        }
    };

    const sendBroadcastRequest = async (target, type) => {
        const participant = target?.participant || target;
        const targetIdentity = String(participant?.identity || target?.identity || '');
        const targetUsername = String(getParticipantUsername(participant) || target?.username || '');
        const targetDisplayName = String(getParticipantDisplayName(participant) || target?.displayName || '');
        const targetUserId = String(
            target?.userId
            || participant?.attributes?.app_user_id
            || participant?.metadata?.user_id
            || resolveParticipantUserId(participant, state)
            || '',
        );

        try {
            const payload = await requestJson(helpRequestStoreEndpoint, {
                body: {
                    target_user_id: targetUserId,
                    target_participant_identity: targetIdentity,
                    target_username: targetUsername,
                    target_display_name: targetDisplayName,
                    type,
                },
                csrfToken,
            });

            onStateUpdate(payload.state || state);
            setRequestTarget(null);
            setError('');
        } catch (caughtError) {
            setError(caughtError.message || 'Could not send request.');
        }
    };

    const handleRejectRequest = async () => {
        if (!currentMine) {
            return;
        }

        try {
            const payload = await onDismissRequest(currentMine, {
                participantIdentity: tokenInfo?.participantIdentity || '',
            });
            if (payload) {
                onStateUpdate(payload.state || state);
            }
            setError('');
        } catch (caughtError) {
            setError(caughtError.message || 'Could not reject request.');
        }
    };

    const promptTeacher = async (request) => {
        try {
            const payload = await onPromoteRequest(request, {
                participantIdentity: tokenInfo?.participantIdentity || '',
            });
            if (payload) {
                onStateUpdate(payload.state || state);
            }
            if (typeof onApproveAndStartRequest === 'function') {
                onApproveAndStartRequest(request);
            }
        } catch (caughtError) {
            setError(caughtError.message || 'Could not promote presenter.');
        }
    };

    const cancelRequest = async (request) => {
        try {
            await onDismissRequest(request, {
                participantIdentity: tokenInfo?.participantIdentity || '',
            });
        } catch (caughtError) {
            setError(caughtError.message || 'Could not cancel request.');
        }
    };

    const rootClassName = embedded
        ? 'flex h-full min-h-0 flex-col bg-slate-900/95 p-3 text-slate-100'
        : 'flex h-full min-h-0 flex-col rounded-lg border border-slate-800 bg-slate-900/95 p-4 text-slate-100 shadow-sm';

    const messageNameClass = (chatMessage) => (
        getChatMessageRole(chatMessage) === 'teacher'
            ? 'text-sky-300'
            : 'text-slate-300'
    );

    return (
        <section className={rootClassName}>
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">Live chat</div>
                </div>
            </div>

            {chatEnabled ? (
                <div className="mt-2 flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-0 py-0">
                        {messages.length === 0 ? (
                            <div className="flex h-full min-h-[10rem] items-center justify-center p-4 text-sm text-slate-400">
                                No live chat messages yet.
                            </div>
                        ) : (
                            <div className="flex flex-col">
                                {messages.map((chatMessage) => {
                                    const isSelf = chatMessage?.identity === tokenInfo?.participantIdentity;
                                    const sentAt = chatMessage?.createdAt ? new Date(chatMessage.createdAt) : null;
                                    const timeLabel = sentAt && ! Number.isNaN(sentAt.getTime())
                                        ? sentAt.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})
                                        : '';

                                    return (
                                        <div
                                            key={chatMessage.id}
                                            className="w-full py-1"
                                        >
                                            <div className="flex items-center justify-between gap-3 text-xs font-semibold">
                                                <span className={messageNameClass(chatMessage)}>{getChatMessageUsername(chatMessage)}</span>
                                                <span className="text-slate-400">{timeLabel}</span>
                                            </div>
                                            <p className={`mt-0 text-sm leading-6 text-slate-100 ${isSelf ? 'pl-2' : ''}`}>{chatMessage.displayMessage || chatMessage.message}</p>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className="mt-auto space-y-1 pt-0">
                        <form onSubmit={sendMessage} className="flex items-center overflow-hidden rounded-lg border border-slate-700 bg-slate-950">
                            <label className="sr-only" htmlFor="classroom-chat-message">
                                Send a message
                            </label>
                            <input
                                id="classroom-chat-message"
                                value={draft}
                                onChange={(event) => setDraft(event.target.value)}
                                placeholder="Type a message"
                                className="min-w-0 flex-1 border-0 bg-transparent px-4 py-2.5 text-sm text-slate-100 outline-none transition placeholder:text-slate-500 focus:ring-0"
                            />
                            <button
                                type="submit"
                                disabled={isSending || draft.trim() === ''}
                                className="border-l border-slate-700 bg-sky-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-400 disabled:cursor-not-allowed disabled:bg-slate-700"
                            >
                                Send
                            </button>
                        </form>

                        <div className="mt-4 flex flex-wrap items-center justify-start gap-2">
                            {orderedParticipants.length > 0 ? orderedParticipants.map((participant) => {
                                const participantUserId = getParticipantUserId(participant);
                                const request = pendingRequestByUserId.get(participantUserId) || null;
                                const isPresenter = hasPresenterStream && participant.identity === presenterParticipant?.identity;
                                const isStudent = getParticipantRole(participant) === 'student';
                                const requestBadgeIconClass = request?.type === 'screen'
                                    ? 'fa-solid fa-display'
                                    : request?.type === 'camera'
                                        ? 'fa-solid fa-video'
                                        : null;

                                return (
                                    <ParticipantAvatar
                                        key={participant.identity}
                                        participant={participant}
                                        title={getParticipantLabel(participant)}
                                        onClick={isTeacher && isStudent ? () => setRequestTarget({
                                            participant,
                                            userId: resolveParticipantUserId(participant, state),
                                            identity: resolveParticipantIdentity(participant),
                                            label: getParticipantLabel(participant),
                                        }) : null}
                                        showTeacherBadge={getParticipantRole(participant) === 'teacher'}
                                        showPresenterBadge={isPresenter}
                                        requestBadgeTitle={request ? `${request.requestedByName || 'Teacher'} requested ${request.typeLabel.toLowerCase()}` : null}
                                        requestBadgeIconClass={requestBadgeIconClass}
                                        onRequestBadgeClick={isTeacher && request ? () => cancelRequest(request) : null}
                                    />
                                );
                            }) : null}
                        </div>
                    </div>
                </div>
                ) : (
                <div className="mt-2 flex min-h-0 flex-1 items-center rounded-lg border border-dashed border-slate-700 bg-slate-950/70 p-4 text-sm text-slate-400">
                    {state.classSession?.liveChatEnabled
                        ? 'Live chat opens when the teacher joins the room.'
                        : 'Live chat is turned off for this session. Use the forum for longer discussion.'}
                </div>
            )}

            {error ? (
                <div className="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                    {error}
                </div>
            ) : null}

            {isTeacher && recentRequest && ['done', 'rejected'].includes(recentRequest.status) ? (
                <div className="mt-3 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    <div className="font-semibold">Broadcast update.</div>
                    <div className="mt-1 leading-6">
                        {describeHelpRequestResolution(recentRequest)}
                    </div>
                </div>
            ) : null}

            {requestTarget && isTeacher ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6">
                    <div className="w-full max-w-md rounded-lg border border-slate-700 bg-slate-900 p-5 text-slate-100 shadow-2xl">
                        <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">Request broadcast</div>
                        <div className="mt-2 text-lg font-semibold text-slate-50">
                            Ask {requestTarget.label} to publish.
                        </div>
                        <p className="mt-2 text-sm leading-6 text-slate-300">
                            They will see a prompt to accept or reject camera or screen sharing.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => sendBroadcastRequest(requestTarget, 'camera')}
                                className="rounded-full bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-700"
                            >
                                Ask camera
                            </button>
                            <button
                                type="button"
                                onClick={() => sendBroadcastRequest(requestTarget, 'screen')}
                                className="rounded-full bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-800"
                            >
                                Ask screen
                            </button>
                            <button
                                type="button"
                                onClick={() => setRequestTarget(null)}
                                className="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

            {currentMine && currentMine.status === 'pending' ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6">
                    <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-2xl">
                        <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Broadcast request</div>
                        <div className="mt-2 text-lg font-semibold text-slate-950">
                            {currentMine.requestedByName || 'Teacher'} requested your {currentMine.typeLabel.toLowerCase()}.
                        </div>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Approve to open the browser permission prompt and start sharing, or reject to decline.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => void promptTeacher(currentMine)}
                                className="rounded-full bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-700"
                            >
                                Allow {currentMine.typeLabel.toLowerCase()} and start
                            </button>
                            <button
                                type="button"
                                onClick={handleRejectRequest}
                                className="rounded-full border border-rose-300 bg-white px-3 py-1.5 text-sm font-semibold text-rose-700 hover:border-rose-400 hover:bg-rose-50"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}

        </section>
    );
}

function ClassroomRoomContent({
    state,
    tokenInfo,
    csrfToken,
    chatStoreEndpoint,
    clientErrorEndpoint,
    helpRequestStoreEndpoint,
    broadcastStartEndpoint,
    broadcastEndEndpoint,
    endpoints,
    onStateUpdate,
    flashMessage,
    setFlashMessage,
}) {
    const room = useRoomContext();
    const { message: classroomStateMessage, send: sendClassroomState } = useDataChannel('classroom-state');
    const participants = useParticipants();
    const tracks = useTracks(TRACK_SOURCES);
    const {
        cameraTrack,
        isCameraEnabled,
        isScreenShareEnabled,
    } = useLocalParticipant();
    const permissions = useLocalParticipantPermissions();
    const teacherUserId = asArray(state.enrolments).find((enrolment) => enrolment?.isTeacher)?.userId || '';
    const activeRequest = state.helpRequests?.active || null;
    const recentRequest = state.helpRequests?.recent || null;
    const myRequest = state.helpRequests?.mine
        || [...asArray(state.helpRequests?.pending), ...asArray(state.helpRequests?.active ? [state.helpRequests.active] : [])]
            .find((request) => helpRequestTargetsCurrentParticipant(request, tokenInfo?.participantIdentity))
        || null;
    const fallbackTeacherParticipant = pickPresenterParticipant(participants, teacherUserId);
    const activePresenterParticipant = activeRequest
        ? pickParticipantByIdentity(participants, activeRequest.targetParticipantIdentity)
            || pickParticipantByUserId(participants, activeRequest.userId)
            || null
        : null;
    const presenterParticipant = activePresenterParticipant || fallbackTeacherParticipant;
    const presenterTrack = presenterParticipant ? pickTrackForParticipant(tracks, presenterParticipant.identity) : null;
    const presenterPanelId = 'classroom-presenter-panel';

    const presenterTitle = presenterParticipant ? getParticipantLabel(presenterParticipant) : 'Teacher';
    const presenterSubtitle = null;
    const isBroadcastOpen = Boolean(state.classSession?.isLiveBroadcastOpen);
    const [localBroadcastEndedAt, setLocalBroadcastEndedAt] = useState(0);
    const broadcastEndedAt = parseClassroomDateTime(state.classSession?.liveBroadcastEndedAt);
    const endedReferenceAt = broadcastEndedAt || (localBroadcastEndedAt > 0 ? new Date(localBroadcastEndedAt) : null);
    const broadcastCloseAt = endedReferenceAt ? new Date(endedReferenceAt.getTime() + (15 * 60 * 1000)) : null;
    const broadcastRecentlyEnded = Boolean(broadcastCloseAt && broadcastCloseAt.getTime() > Date.now());
    const classroomSchedule = asArray(state.classSession?.broadcastSchedule?.length ? state.classSession.broadcastSchedule : state.workshop?.classroomSessions);
    const presenterIdleState = getClassroomIdleState(classroomSchedule);
    const hasPresenterStream = Boolean(presenterTrack?.publication?.track);
    const broadcastHasActivePresenter = isBroadcastOpen && hasPresenterStream;
    const presenterEmptyLabel = isBroadcastOpen
        ? 'Live stream is open. Waiting for the teacher to join.'
        : broadcastRecentlyEnded && broadcastCloseAt
            ? `Live stream ended. This room closes ${formatClassroomCountdown(broadcastCloseAt).replace(/^Starts in /i, 'in ').replace(/^Started /i, 'in ')}.`
            : presenterIdleState.summary;
    const presenterIdleIcon = isBroadcastOpen
        ? 'fa-solid fa-broadcast-tower'
        : broadcastRecentlyEnded
            ? 'fa-solid fa-circle-stop'
            : (presenterIdleState.icon || 'fa-solid fa-calendar-clock');
    const lastPresenterEndedStorageKey = state?.classSession?.id ? `classroom:last-stream-ended-at:${state.classSession.id}` : '';
    const lastPresenterWasStreamingRef = React.useRef(hasPresenterStream);
    const [lastPresenterEndedAt, setLastPresenterEndedAt] = useState(() => {
        if (typeof window === 'undefined' || lastPresenterEndedStorageKey === '') {
            return 0;
        }

        const storedValue = Number(window.localStorage.getItem(lastPresenterEndedStorageKey) || 0);
        return Number.isFinite(storedValue) && storedValue > 0 ? storedValue : 0;
    });
    const [clockTick, setClockTick] = useState(0);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setClockTick((value) => value + 1);
        }, 60_000);

        return () => {
            window.clearInterval(interval);
        };
    }, []);

    useEffect(() => {
        if (lastPresenterEndedStorageKey === '') {
            lastPresenterWasStreamingRef.current = hasPresenterStream;
            return;
        }

        if (lastPresenterWasStreamingRef.current && !hasPresenterStream) {
            const endedAt = Date.now();
            lastPresenterWasStreamingRef.current = false;
            setLastPresenterEndedAt(endedAt);
            window.localStorage.setItem(lastPresenterEndedStorageKey, String(endedAt));
            return;
        }

        if (hasPresenterStream) {
            lastPresenterWasStreamingRef.current = true;
            setLastPresenterEndedAt(0);
            window.localStorage.removeItem(lastPresenterEndedStorageKey);
            return;
        }

        lastPresenterWasStreamingRef.current = false;
    }, [hasPresenterStream, lastPresenterEndedStorageKey]);

    const recentlyEnded = lastPresenterEndedAt > 0 && (Date.now() - lastPresenterEndedAt) <= (15 * 60 * 1000);
    const presenterShellExpanded = broadcastHasActivePresenter || isBroadcastOpen || recentlyEnded || broadcastRecentlyEnded || presenterIdleState.mode === 'expanded';
    const [pendingPublishRequest, setPendingPublishRequest] = useState(null);
    const publishAttemptRef = React.useRef('');
    const lastRecentAnnouncementRef = React.useRef('');
    const didMountRef = React.useRef(false);
    const canPublishCamera = canPublishSource(permissions, Track.Source.Camera);
    const canPublishMicrophone = canPublishSource(permissions, Track.Source.Microphone);
    const canPublishScreenShare = canPublishSource(permissions, Track.Source.ScreenShare);
    const canChangeCameraDevice = canPublishCamera || Boolean(cameraTrack);

    const startBroadcast = async () => {
        if (!broadcastStartEndpoint) {
            return;
        }

        try {
            const payload = await requestJson(broadcastStartEndpoint, {
                csrfToken,
            });
            setLocalBroadcastEndedAt(0);
            setLastPresenterEndedAt(0);
            const nextState = payload.state || state;
            onStateUpdate(nextState);
            if (payload.state) {
                await sendClassroomState(new TextEncoder().encode(JSON.stringify({
                    type: 'classroom-state',
                    state: {classSession: payload.state.classSession},
                })), {reliable: true});
            }
        } catch (error) {
            setFlashMessage(error.message || 'Could not start livestream.');
        }
    };

    const endBroadcast = async () => {
        if (!broadcastEndEndpoint) {
            return;
        }

        try {
            const endedAt = Date.now();
            const optimisticState = {
                ...state,
                classSession: {
                    ...state.classSession,
                    isLiveBroadcastOpen: false,
                    liveBroadcastEndedAt: new Date(endedAt).toISOString(),
                    liveBroadcastEndedByUserId: String(tokenInfo?.userId || tokenInfo?.participantUserId || tokenInfo?.participantIdentity || state.classSession?.liveBroadcastEndedByUserId || ''),
                },
            };

            setLocalBroadcastEndedAt(endedAt);
            setLastPresenterEndedAt(endedAt);
            onStateUpdate(optimisticState);
            await sendClassroomState(new TextEncoder().encode(JSON.stringify({
                type: 'classroom-state',
                state: {classSession: optimisticState.classSession},
            })), {reliable: true});

            if (activeRequest) {
                await revokeRequest(activeRequest, {
                    participantIdentity: tokenInfo?.participantIdentity || '',
                    resolution_reason: 'Livestream ended.',
                });
            }

            if (room?.localParticipant) {
                const stopTasks = [];
                if (isCameraEnabled) {
                    stopTasks.push(room.localParticipant.setCameraEnabled(false));
                }
                if (isScreenShareEnabled) {
                    stopTasks.push(room.localParticipant.setScreenShareEnabled(false));
                }

                if (stopTasks.length > 0) {
                    await Promise.allSettled(stopTasks);
                }
            }

            const payload = await requestJson(broadcastEndEndpoint, {
                csrfToken,
            });
            const nextState = payload.state || optimisticState;
            onStateUpdate(nextState);
            if (payload.state) {
                await sendClassroomState(new TextEncoder().encode(JSON.stringify({
                    type: 'classroom-state',
                    state: {classSession: payload.state.classSession},
                })), {reliable: true});
            }
        } catch (error) {
            setFlashMessage(error.message || 'Could not end livestream.');
        }
    };

    const clearPendingPublishTracks = () => {
        publishAttemptRef.current = '';
    };

    useEffect(() => {
        if (!myRequest || myRequest.status === 'done' || myRequest.status === 'rejected') {
            setPendingPublishRequest(null);
            clearPendingPublishTracks();
            publishAttemptRef.current = '';
        }
    }, [myRequest?.id, myRequest?.status]);

    useEffect(() => {
        if (state.viewer?.role !== 'teacher' || !recentRequest) {
            return;
        }

        const announcementKey = `${recentRequest.id}:${recentRequest.status}:${String(recentRequest.resolutionReason || '')}`;
        if (!didMountRef.current) {
            didMountRef.current = true;
            lastRecentAnnouncementRef.current = announcementKey;
            return;
        }

        if (lastRecentAnnouncementRef.current === announcementKey) {
            return;
        }

        lastRecentAnnouncementRef.current = announcementKey;

        if (['done', 'rejected'].includes(recentRequest.status)) {
            const message = describeHelpRequestResolution(recentRequest);
            if (message) {
                setFlashMessage(message);
            }
        }
    }, [recentRequest?.id, recentRequest?.status, recentRequest?.resolutionReason, setFlashMessage, state.viewer?.role]);

    useEffect(() => {
        if (!clientErrorEndpoint) {
            return () => {};
        }

        const report = (source, message, stack = '') => {
            void reportClientError(clientErrorEndpoint, {
                csrfToken,
                source,
                message,
                stack,
                context: {
                    class_session_id: state?.classSession?.id || '',
                    class_session_slug: state?.classSession?.slug || '',
                    request_status: myRequest?.status || '',
                    request_type: myRequest?.type || '',
                    participant_identity: tokenInfo?.participantIdentity || '',
                },
            });
        };

        const handleWindowError = (event) => {
            const error = event?.error instanceof Error ? event.error : null;
            report(
                'window.error',
                String(event?.message || error?.message || 'Unknown classroom error'),
                String(error?.stack || ''),
            );
        };

        const handleUnhandledRejection = (event) => {
            const reason = event?.reason;
            if (reason instanceof Error) {
                report('window.unhandledrejection', reason.message, String(reason.stack || ''));
                return;
            }

            if (typeof reason === 'string') {
                report('window.unhandledrejection', reason);
                return;
            }

            report('window.unhandledrejection', 'Unhandled promise rejection', JSON.stringify(reason ?? {}));
        };

        window.addEventListener('error', handleWindowError);
        window.addEventListener('unhandledrejection', handleUnhandledRejection);

        return () => {
            window.removeEventListener('error', handleWindowError);
            window.removeEventListener('unhandledrejection', handleUnhandledRejection);
        };
    }, [clientErrorEndpoint, csrfToken, myRequest?.status, myRequest?.type, state?.classSession?.id, state?.classSession?.slug, tokenInfo?.participantIdentity]);

    useEffect(() => {
        const payload = classroomStateMessage?.payload;
        if (!payload) {
            return;
        }

        try {
            const parsed = JSON.parse(new TextDecoder().decode(payload));
            if (parsed?.type === 'classroom-state' && parsed?.state) {
                onStateUpdate((current) => mergeClassroomState(current, parsed.state));
            }
        } catch (_error) {
        }
    }, [classroomStateMessage?.payload, onStateUpdate]);

    useEffect(() => {
        if (!room?.localParticipant || !pendingPublishRequest || !myRequest) {
            return () => {};
        }

        if (pendingPublishRequest.id !== myRequest.id) {
            return () => {};
        }

        if (room.state !== ConnectionState.Connected) {
            return () => {};
        }

        if (myRequest.status !== 'approved') {
            return () => {};
        }

        const requestKey = `${pendingPublishRequest.id}:${pendingPublishRequest.type}`;
        if (publishAttemptRef.current === requestKey) {
            return () => {};
        }
        publishAttemptRef.current = requestKey;

        const startPublishing = async () => {
            try {
                if (pendingPublishRequest.type === 'camera') {
                    await room.localParticipant.setCameraEnabled(true);
                } else if (pendingPublishRequest.type === 'screen') {
                    await room.localParticipant.setScreenShareEnabled(true, {
                        audio: true,
                        selfBrowserSurface: 'include',
                    });
                } else {
                    throw new Error('Unknown publish type.');
                }
            } catch (error) {
                publishAttemptRef.current = '';
                const message = error.message || 'Could not start publishing.';

                if (pendingPublishRequest.type === 'screen' && /getDisplayMedia|not supported|unsupported/i.test(message)) {
                    try {
                        await revokeRequest(myRequest, {
                            participantIdentity: tokenInfo?.participantIdentity || '',
                            resolution_reason: message,
                        });
                    } catch (_revokeError) {
                    }

                    setPendingPublishRequest(null);
                    clearPendingPublishTracks();
                    setFlashMessage('Screen sharing is not supported on this device.');
                    return;
                }

                await reportClientError(clientErrorEndpoint, {
                    csrfToken,
                    source: pendingPublishRequest.type === 'camera' ? 'classroom.publish.camera' : 'classroom.publish.screen',
                    message,
                    stack: String(error.stack || ''),
                    context: {
                        class_session_id: state?.classSession?.id || '',
                        class_session_slug: state?.classSession?.slug || '',
                        help_request_id: myRequest?.id || '',
                        help_request_status: myRequest?.status || '',
                        participant_identity: tokenInfo?.participantIdentity || '',
                        publish_mode: pendingPublishRequest.type || '',
                    },
                });
                setFlashMessage(message);
            }
        };

        void startPublishing();

        return () => {};
    }, [
        canPublishCamera,
        canPublishScreenShare,
        isCameraEnabled,
        isScreenShareEnabled,
        myRequest?.id,
        myRequest?.status,
        pendingPublishRequest,
        clientErrorEndpoint,
        room?.localParticipant,
        room?.state,
        setFlashMessage,
        csrfToken,
        state?.classSession?.id,
        state?.classSession?.slug,
        tokenInfo?.participantIdentity,
    ]);

    const approveRequest = async (request, context = {}) => {
        try {
            const url = endpoints.approve.replace('__REQUEST__', request.id);
            const payload = await requestJson(url, {
                csrfToken,
                body: context.participantIdentity ? {participant_identity: context.participantIdentity} : null,
            });
            onStateUpdate(payload.state || state);
            setFlashMessage(payload.message || 'Request approved.');
        } catch (error) {
            setFlashMessage(error.message || 'Could not approve request.');
        }
    };

    const revokeRequest = async (request, context = {}) => {
        try {
            const url = endpoints.revoke.replace('__REQUEST__', request.id);
            const payload = await requestJson(url, {
                csrfToken,
                body: context.participantIdentity ? {participant_identity: context.participantIdentity} : null,
            });
            onStateUpdate(payload.state || state);
            setFlashMessage(payload.message || 'Request updated.');
            return payload;
        } catch (error) {
            setFlashMessage(error.message || 'Could not update request.');
            return null;
        }
    };

    const stopMySharing = async () => {
        if (!room?.localParticipant || !myRequest) {
            return;
        }

        try {
            if (myRequest.type === 'camera') {
                await room.localParticipant.setCameraEnabled(false);
            } else if (myRequest.type === 'screen') {
                await room.localParticipant.setScreenShareEnabled(false);
            }
        } catch (error) {
            setFlashMessage(error.message || 'Could not stop sharing.');
        }

        await revokeRequest(myRequest, {
            participantIdentity: tokenInfo?.participantIdentity || '',
        });
        setPendingPublishRequest(null);
        clearPendingPublishTracks();
        publishAttemptRef.current = '';
    };

    const studentSharingActive = myRequest?.status === 'approved'
        && ((myRequest.type === 'camera' && isCameraEnabled) || (myRequest.type === 'screen' && isScreenShareEnabled));
    const studentStartingPublish = pendingPublishRequest
        && myRequest
        && pendingPublishRequest.id === myRequest.id
        && myRequest.status === 'approved'
        && !studentSharingActive;

    const toggleFullscreen = async () => {
        const panel = document.getElementById(presenterPanelId);
        if (!panel) {
            return;
        }

        if (document.fullscreenElement === panel) {
            await document.exitFullscreen();
            return;
        }

        await panel.requestFullscreen();
    };

    const teacherHeaderActions = state.viewer?.canManage ? (
        <div className="flex flex-wrap items-center gap-2">
            <TrackToggle
                source={Track.Source.Microphone}
                showIcon
                disabled={!canPublishMicrophone}
                initialState={false}
                onDeviceError={(error) => setFlashMessage(error.message)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                title="Microphone"
                aria-label="Microphone"
            />
            <TrackToggle
                source={Track.Source.Camera}
                showIcon
                disabled={!canPublishCamera}
                initialState={false}
                onDeviceError={(error) => setFlashMessage(error.message)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                title="Camera"
                aria-label="Camera"
            />
            <CameraSourceMenu
                track={cameraTrack?.track}
                disabled={!canChangeCameraDevice}
                onDeviceError={(error) => setFlashMessage(error.message)}
            />
            <TrackToggle
                source={Track.Source.ScreenShare}
                showIcon
                disabled={!canPublishScreenShare}
                initialState={false}
                captureOptions={{audio: true, selfBrowserSurface: 'include'}}
                onDeviceError={(error) => setFlashMessage(error.message)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                title="Screen share"
                aria-label="Screen share"
            />
            <button
                type="button"
                onClick={() => void toggleFullscreen()}
                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20"
                title="Fullscreen"
                aria-label="Fullscreen"
            >
                <i className="fa-solid fa-expand" aria-hidden="true"></i>
            </button>
            {activeRequest ? (
                <button
                    type="button"
                    onClick={() => revokeRequest(activeRequest)}
                    className="inline-flex h-9 items-center rounded-full border border-white/15 bg-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                    title="Take control"
                    aria-label="Take control"
                >
                    Take control
                </button>
            ) : null}
            {isBroadcastOpen ? (
                <button
                    type="button"
                    onClick={() => void endBroadcast()}
                    className="inline-flex h-9 items-center rounded-full border border-white/15 bg-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                    title="End livestream"
                    aria-label="End livestream"
                >
                    <i className="fa-solid fa-circle-stop mr-2" aria-hidden="true"></i>
                    End livestream
                </button>
            ) : null}
        </div>
    ) : null;

    return (
        <>
            <RoomAudioRenderer />

            <div className="space-y-6">
                {broadcastHasActivePresenter ? (
                    <section className="overflow-hidden rounded-lg border border-slate-800 bg-slate-950 shadow-lg min-h-[36rem]">
                        <div className="grid min-h-[36rem] gap-px xl:grid-cols-[minmax(0,1.6fr)_22rem]">
                            <ParticipantMediaCard
                                embedded
                                panelId={presenterPanelId}
                                title={presenterTitle}
                                subtitle={presenterSubtitle}
                                trackRef={presenterTrack}
                                emptyLabel={presenterIdleState.summary}
                                headerActions={
                                    state.viewer?.canManage ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <TrackToggle
                                                source={Track.Source.Microphone}
                                                showIcon
                                                disabled={!canPublishMicrophone}
                                                initialState={false}
                                                onDeviceError={(error) => setFlashMessage(error.message)}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                title="Microphone"
                                                aria-label="Microphone"
                                            />
                                            <TrackToggle
                                                source={Track.Source.Camera}
                                                showIcon
                                                disabled={!canPublishCamera}
                                                initialState={false}
                                                onDeviceError={(error) => setFlashMessage(error.message)}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                title="Camera"
                                                aria-label="Camera"
                                            />
                                            <CameraSourceMenu
                                                track={cameraTrack?.track}
                                                disabled={!canChangeCameraDevice}
                                                onDeviceError={(error) => setFlashMessage(error.message)}
                                            />
                                            <TrackToggle
                                                source={Track.Source.ScreenShare}
                                                showIcon
                                                disabled={!canPublishScreenShare}
                                                initialState={false}
                                                captureOptions={{audio: true, selfBrowserSurface: 'include'}}
                                                onDeviceError={(error) => setFlashMessage(error.message)}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                title="Screen share"
                                                aria-label="Screen share"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => void toggleFullscreen()}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white transition hover:bg-white/20"
                                                title="Fullscreen"
                                                aria-label="Fullscreen"
                                            >
                                                <i className="fa-solid fa-expand" aria-hidden="true"></i>
                                            </button>
                                            {activeRequest ? (
                                                <button
                                                    type="button"
                                                    onClick={() => revokeRequest(activeRequest)}
                                                    className="inline-flex h-9 items-center rounded-full border border-white/15 bg-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                                                    title="Take control"
                                                    aria-label="Take control"
                                                >
                                                    Take control
                                                </button>
                                            ) : null}
                                            {isBroadcastOpen ? (
                                                <button
                                                    type="button"
                                                    onClick={() => void endBroadcast()}
                                                    className="inline-flex h-9 items-center rounded-full border border-white/15 bg-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                                                    title="End livestream"
                                                    aria-label="End livestream"
                                                >
                                                    <i className="fa-solid fa-circle-stop mr-2" aria-hidden="true"></i>
                                                    End livestream
                                                </button>
                                            ) : null}
                                        </div>
                                    ) : myRequest?.status === 'approved' ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                                                {studentStartingPublish
                                                        ? `Starting ${myRequest.typeLabel.toLowerCase()}...`
                                                        : studentSharingActive
                                                            ? `${myRequest.typeLabel} live`
                                                            : `Ready for ${myRequest.typeLabel.toLowerCase()}`}
                                            </span>
                                            {studentSharingActive ? (
                                                <button
                                                    type="button"
                                                    onClick={() => void stopMySharing()}
                                                    className="inline-flex h-9 items-center rounded-full border border-white/15 bg-white/10 px-3 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                                                    title="Stop sharing"
                                                    aria-label="Stop sharing"
                                                >
                                                    Stop sharing
                                                </button>
                                            ) : null}
                                        </div>
                                    ) : null
                                }
                            />

                            <div className="min-h-0 border-t border-slate-800 xl:border-l xl:border-t-0">
                                <LiveChatPanel
                                    embedded
                                    state={state}
                                    participants={participants}
                                    presenterParticipant={presenterParticipant}
                                    hasPresenterStream={broadcastHasActivePresenter}
                                    tokenInfo={tokenInfo}
                                    chatStoreEndpoint={chatStoreEndpoint}
                                    helpRequestStoreEndpoint={helpRequestStoreEndpoint}
                                    csrfToken={csrfToken}
                                    onStateUpdate={onStateUpdate}
                                    onPromoteRequest={approveRequest}
                                    onApproveAndStartRequest={(request) => {
                                        setPendingPublishRequest({
                                            id: request.id,
                                            type: request.type,
                                        });
                                    }}
                                    onDismissRequest={revokeRequest}
                                />
                            </div>
                        </div>
                    </section>
                ) : isBroadcastOpen ? (
                    <section className="overflow-hidden rounded-lg border border-slate-800 bg-slate-950 shadow-lg min-h-[36rem]">
                        <div className="grid min-h-[36rem] gap-px xl:grid-cols-[minmax(0,1.6fr)_22rem]">
                            <ParticipantMediaCard
                                embedded
                                panelId={presenterPanelId}
                                title={presenterTitle}
                                subtitle={presenterSubtitle}
                                trackRef={presenterTrack}
                                emptyLabel={presenterEmptyLabel}
                                headerActions={teacherHeaderActions}
                            />

                            <div className="min-h-0 border-t border-slate-800 xl:border-l xl:border-t-0">
                                <LiveChatPanel
                                    embedded
                                    state={state}
                                    participants={participants}
                                    presenterParticipant={presenterParticipant}
                                    hasPresenterStream={broadcastHasActivePresenter}
                                    tokenInfo={tokenInfo}
                                    chatStoreEndpoint={chatStoreEndpoint}
                                    helpRequestStoreEndpoint={helpRequestStoreEndpoint}
                                    csrfToken={csrfToken}
                                    onStateUpdate={onStateUpdate}
                                    onPromoteRequest={approveRequest}
                                    onApproveAndStartRequest={(request) => {
                                        setPendingPublishRequest({
                                            id: request.id,
                                            type: request.type,
                                        });
                                    }}
                                    onDismissRequest={revokeRequest}
                                />
                            </div>
                        </div>
                    </section>
                ) : broadcastRecentlyEnded ? (
                    <section className="overflow-hidden rounded-lg border border-slate-800 bg-slate-950 shadow-lg min-h-[36rem]">
                        <div className="grid min-h-[36rem] gap-px xl:grid-cols-[minmax(0,1.6fr)_22rem]">
                            <div className="flex min-h-[36rem] items-center justify-center px-6 py-10 text-slate-100">
                                <div className="max-w-3xl rounded-lg border border-white/10 bg-slate-800/90 px-5 py-4 text-center shadow-lg">
                                    <div className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-200">
                                        Livestream
                                    </div>
                                    <div className="mt-2 flex items-center justify-center gap-3 text-sm font-medium leading-6 text-slate-200">
                                        <i className={`${presenterIdleIcon} text-sky-300`} aria-hidden="true"></i>
                                        <span>{presenterEmptyLabel}</span>
                                    </div>
                                    {state.viewer?.canManage ? (
                                        <div className="mt-4 flex justify-center">
                                            <button
                                                type="button"
                                                onClick={() => void startBroadcast()}
                                                className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-white/20"
                                            >
                                                <i className="fa-solid fa-circle-play" aria-hidden="true"></i>
                                                Start livestream
                                            </button>
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                            <div className="border-t border-slate-800 xl:border-l xl:border-t-0">
                                <LiveChatPanel
                                    embedded
                                    state={state}
                                    participants={participants}
                                    presenterParticipant={presenterParticipant}
                                    hasPresenterStream={broadcastHasActivePresenter}
                                    tokenInfo={tokenInfo}
                                    chatStoreEndpoint={chatStoreEndpoint}
                                    helpRequestStoreEndpoint={helpRequestStoreEndpoint}
                                    csrfToken={csrfToken}
                                    onStateUpdate={onStateUpdate}
                                    onPromoteRequest={approveRequest}
                                    onApproveAndStartRequest={(request) => {
                                        setPendingPublishRequest({
                                            id: request.id,
                                            type: request.type,
                                        });
                                    }}
                                    onDismissRequest={revokeRequest}
                                />
                            </div>
                        </div>
                    </section>
                ) : (
                    <section className={`w-full border border-slate-800 bg-slate-950 text-slate-100 shadow-lg ${presenterShellExpanded ? 'rounded-lg px-6 py-10 min-h-[36rem]' : 'rounded-lg px-1 py-2'}`}>
                        <div className={`flex w-full items-center justify-center ${presenterShellExpanded ? 'min-h-[36rem]' : ''}`}>
                            <div className={`flex w-full items-center justify-center ${presenterShellExpanded ? 'max-w-4xl gap-3 text-center text-2xl font-semibold' : 'gap-3 text-sm font-medium'}`}>
                                <i className={`${presenterIdleIcon} text-sky-300`} aria-hidden="true"></i>
                                <span>{presenterEmptyLabel}</span>
                            </div>
                        </div>
                    </section>
                )}

                {flashMessage ? (
                    <div className="fixed right-4 top-4 z-50 max-w-md rounded-lg border border-sky-400/20 bg-slate-950/95 px-4 py-3 text-sm text-slate-100 shadow-2xl shadow-slate-950/40">
                        <div className="flex items-start gap-3">
                            <i className="fa-solid fa-circle-info mt-0.5 text-sky-300" aria-hidden="true"></i>
                            <div className="min-w-0 leading-6">
                                {flashMessage}
                            </div>
                        </div>
                    </div>
                ) : null}

                <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm editor">
                    <div className="flex items-center justify-between gap-3">
                        <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">Course</div>
                    </div>
                    {state.workshop?.content || state.classSession?.instructionsHtml ? (
                        <div
                            className="content mt-4 max-w-none"
                            dangerouslySetInnerHTML={{ __html: state.workshop?.content || state.classSession.instructionsHtml }}
                        />
                    ) : (
                        <p className="mt-3 text-sm leading-6 text-gray-600">
                            Course notes can be added per workshop or class session and shown here.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

function ClassroomRoom({
    state,
    tokenInfo,
    livekitUrl,
    csrfToken,
    chatStoreEndpoint,
    clientErrorEndpoint,
    helpRequestEndpoints,
    broadcastStartEndpoint,
    broadcastEndEndpoint,
    onStateUpdate,
    flashMessage,
    setFlashMessage,
}) {
    return (
        <LiveKitRoom
            serverUrl={livekitUrl || undefined}
            token={tokenInfo?.accessToken || undefined}
            connect={Boolean(tokenInfo?.accessToken)}
            audio={false}
            video={false}
            screen={false}
            onError={(error) => setFlashMessage(error.message)}
            className="space-y-6"
        >
            <ClassroomRoomContent
                state={state}
                tokenInfo={tokenInfo}
                csrfToken={csrfToken}
                chatStoreEndpoint={chatStoreEndpoint}
                clientErrorEndpoint={clientErrorEndpoint}
                helpRequestStoreEndpoint={helpRequestEndpoints.store}
                broadcastStartEndpoint={broadcastStartEndpoint}
                broadcastEndEndpoint={broadcastEndEndpoint}
                endpoints={helpRequestEndpoints}
                onStateUpdate={onStateUpdate}
                flashMessage={flashMessage}
                setFlashMessage={setFlashMessage}
            />
        </LiveKitRoom>
    );
}

function ClassroomApp() {
    const initialState = readInitialState();
    const rootConfig = readRootConfig();
    const [state, setState] = useState(initialState || null);
    const [tokenInfo, setTokenInfo] = useState(null);
    const [flashMessage, setFlashMessage] = useState('');

    useEffect(() => {
        if (!flashMessage) {
            return () => {};
        }

        const timer = window.setTimeout(() => {
            setFlashMessage('');
        }, 5000);

        return () => {
            window.clearTimeout(timer);
        };
    }, [flashMessage]);

    useEffect(() => {
        if (!state || !rootConfig?.tokenEndpoint) {
            return () => {};
        }

        let cancelled = false;

        const loadToken = async () => {
            try {
                const payload = await requestJson(rootConfig.tokenEndpoint, {
                    body: {
                        class_session_id: state.classSession.id,
                    },
                    csrfToken: rootConfig.csrfToken,
                });

                if (cancelled) {
                    return;
                }

                setTokenInfo(payload);
            } catch (error) {
                if (!cancelled) {
                    setFlashMessage(error.message || 'Unable to connect to LiveKit.');
                }
            }
        };

        void loadToken();

        return () => {
            cancelled = true;
        };
    }, [rootConfig?.tokenEndpoint, rootConfig?.csrfToken, state?.classSession?.id]);

    useEffect(() => {
        if (!state || !rootConfig?.helpRequestsEndpoint) {
            return () => {};
        }

        let cancelled = false;

        const refreshHelpState = async () => {
            try {
                const payload = await requestJson(rootConfig.helpRequestsEndpoint, {
                    method: 'GET',
                    csrfToken: rootConfig.csrfToken,
                });

                if (!cancelled && payload) {
                    setState((current) => mergeClassroomState(current, payload));
                }
            } catch (_error) {
            }
        };

        void refreshHelpState();
        const timer = window.setInterval(() => {
            void refreshHelpState();
        }, 4000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [rootConfig?.helpRequestsEndpoint, rootConfig?.csrfToken, state?.classSession?.id]);

    if (!state) {
        return (
            <div className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <p className="text-sm text-gray-600">Unable to load classroom state.</p>
            </div>
        );
    }

    return (
        <ClassroomRoom
            state={state}
            tokenInfo={tokenInfo}
            livekitUrl={rootConfig.livekitUrl}
            csrfToken={rootConfig.csrfToken}
            chatStoreEndpoint={rootConfig.chatStoreEndpoint}
            clientErrorEndpoint={rootConfig.clientErrorEndpoint}
            broadcastStartEndpoint={rootConfig.broadcastStartEndpoint}
            broadcastEndEndpoint={rootConfig.broadcastEndEndpoint}
            helpRequestEndpoints={{
                store: rootConfig.helpRequestStoreEndpoint,
                approve: rootConfig.helpRequestApprovePattern,
                revoke: rootConfig.helpRequestRevokePattern,
            }}
            onStateUpdate={setState}
            flashMessage={flashMessage}
            setFlashMessage={setFlashMessage}
        />
    );
}

const rootElement = document.getElementById(ROOT_ID);
if (rootElement instanceof HTMLElement) {
    createRoot(rootElement).render(<ClassroomApp />);
}
