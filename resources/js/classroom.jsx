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
        chatStoreEndpoint: root.dataset.chatStoreEndpoint || '',
        clientErrorEndpoint: root.dataset.clientErrorEndpoint || '',
        csrfToken: root.dataset.csrfToken || document.head.querySelector('meta[name="csrf-token"]')?.content || '',
    };
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

function getTrackDisplaySource(trackRef) {
    return Number(trackRef?.source ?? Track.Source.Unknown);
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
                    className="group relative flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white shadow-sm ring-1 ring-slate-200 transition hover:scale-[1.03] hover:ring-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-300"
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
                    className="relative flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white shadow-sm ring-1 ring-slate-200"
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
                <span className="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full border border-white bg-slate-950 px-1 text-[9px] font-semibold uppercase leading-none text-white shadow-sm">
                    T
                </span>
            ) : null}

            {showPresenterBadge ? (
                <span className="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 shadow-sm" />
            ) : null}

            {(requestBadgeTitle || requestBadgeClickable) ? (
                requestBadgeClickable ? (
                    <button
                        type="button"
                        onClick={onRequestBadgeClick}
                        title={requestBadgeTitle || displayName}
                        aria-label={requestBadgeTitle || displayName}
                        className="absolute -left-0.5 -bottom-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] text-white shadow-sm transition hover:scale-105 hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-300"
                    >
                        <i className={requestBadgeIconClass || 'fa-solid fa-video'} aria-hidden="true"></i>
                    </button>
                ) : (
                    <span
                        title={requestBadgeTitle || displayName}
                        className="absolute -left-0.5 -bottom-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] text-white shadow-sm"
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

function ParticipantMediaCard({ title, subtitle, trackRef, emptyLabel, headerActions = null, panelId = null }) {
    const hasRenderableTrack = Boolean(trackRef?.publication?.track);

    return (
        <section id={panelId || undefined} className="overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 shadow-lg">
            <div className="flex items-start justify-between gap-3 border-b border-slate-800 bg-slate-900 px-5 py-4 text-white">
                <div className="min-w-0">
                    {subtitle ? <div className="text-xs font-semibold uppercase tracking-[0.2em] text-sky-200">{subtitle}</div> : null}
                    <h2 className={subtitle ? 'mt-1 text-xl font-semibold' : 'text-xl font-semibold'}>{title}</h2>
                </div>
                {headerActions ? <div className="flex flex-wrap items-center justify-end gap-2">{headerActions}</div> : null}
            </div>
            <div className="flex aspect-video items-center justify-center bg-slate-900 p-4">
                {hasRenderableTrack ? (
                    <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-[1.5rem] bg-black">
                        <VideoTrack
                            trackRef={trackRef}
                            className="h-full w-full object-contain"
                        />
                    </div>
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <div className="max-w-md rounded-[1.5rem] border border-white/10 bg-slate-800/90 px-6 py-5 text-center text-slate-200 shadow-lg">
                            <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Waiting</div>
                            <div className="mt-2 text-sm leading-6 text-slate-200">{emptyLabel}</div>
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
                    className="absolute right-0 top-full z-20 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"
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
    tokenInfo,
    chatStoreEndpoint,
    helpRequestStoreEndpoint,
    csrfToken,
    onStateUpdate,
    onPromoteRequest,
    onApproveAndStartRequest,
    onDismissRequest,
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

    return (
        <section className="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">Live chat</div>
                    <h3 className="mt-1 text-base font-semibold text-slate-950">Room conversation</h3>
                </div>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {!state.classSession?.liveChatEnabled
                        ? 'Forum only'
                        : teacherConnected
                            ? 'Enabled'
                            : 'Waiting for teacher'}
                </span>
            </div>

            {chatEnabled ? (
                <>
                    <div className="mt-4 max-h-72 space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        {messages.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                                No live chat messages yet.
                            </div>
                        ) : (
                            messages.map((chatMessage) => {
                                const isSelf = chatMessage?.identity === tokenInfo?.participantIdentity;
                                const sentAt = chatMessage?.createdAt ? new Date(chatMessage.createdAt) : null;
                                const timeLabel = sentAt && ! Number.isNaN(sentAt.getTime())
                                    ? sentAt.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})
                                    : '';

                                return (
                                    <div
                                        key={chatMessage.id}
                                        className={`rounded-2xl border px-3 py-2 ${isSelf ? 'ml-8 border-sky-200 bg-sky-100' : 'mr-8 border-slate-200 bg-white'}`}
                                    >
                                        <div className="flex items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                                            <span>{chatMessage.name || 'Participant'}</span>
                                            <span>{timeLabel}</span>
                                        </div>
                                        <p className="mt-1 text-sm leading-6 text-slate-800">{chatMessage.displayMessage || chatMessage.message}</p>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    <form onSubmit={sendMessage} className="mt-3 space-y-2">
                        <label className="sr-only" htmlFor="classroom-chat-message">
                            Send a message
                        </label>
                        <textarea
                            id="classroom-chat-message"
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            rows={3}
                            placeholder="Type a short message..."
                            className="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                        />
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs leading-5 text-slate-500">
                                Keep the live chat brief. Longer discussion can continue in the forum.
                            </p>
                            <button
                                type="submit"
                                disabled={isSending || draft.trim() === ''}
                                className="rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            >
                                Send
                            </button>
                        </div>
                    </form>
                </>
            ) : (
                <div className="mt-3 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">
                    {state.classSession?.liveChatEnabled
                        ? 'Live chat opens when the teacher joins the room.'
                        : 'Live chat is turned off for this session. Use the forum for longer discussion.'}
                </div>
            )}

            {error ? (
                <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {error}
                </div>
            ) : null}

            {isTeacher && recentRequest && ['done', 'rejected'].includes(recentRequest.status) ? (
                <div className="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <div className="font-semibold">Broadcast update.</div>
                    <div className="mt-1 leading-6">
                        {describeHelpRequestResolution(recentRequest)}
                    </div>
                </div>
            ) : null}

            {requestTarget && isTeacher ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6">
                    <div className="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl">
                        <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Request broadcast</div>
                        <div className="mt-2 text-lg font-semibold text-slate-950">
                            Ask {requestTarget.label} to publish.
                        </div>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
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
                    <div className="w-full max-w-md rounded-[2rem] border border-slate-200 bg-white p-5 shadow-2xl">
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

            <div className="mt-4 flex flex-wrap gap-2">
                {orderedParticipants.length > 0 ? orderedParticipants.map((participant) => {
                    const participantUserId = getParticipantUserId(participant);
                    const request = pendingRequestByUserId.get(participantUserId) || null;
                    const isPresenter = participant.identity === presenterParticipant?.identity;
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
    endpoints,
    onStateUpdate,
    flashMessage,
    setFlashMessage,
}) {
    const room = useRoomContext();
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
    const presenterEmptyLabel = activeRequest
        ? activePresenterParticipant
            ? `Waiting for ${getParticipantUsername(activePresenterParticipant) || activeRequest?.requestedForUsername || 'the participant'} to start publishing.`
            : 'The student left. Teacher is back in control.'
        : 'Waiting for the teacher to join the room.';
    const noActiveStream = !presenterTrack?.publication?.track;
    const [pendingPublishRequest, setPendingPublishRequest] = useState(null);
    const publishAttemptRef = React.useRef('');
    const lastRecentAnnouncementRef = React.useRef('');
    const didMountRef = React.useRef(false);
    const canPublishCamera = canPublishSource(permissions, Track.Source.Camera);
    const canPublishMicrophone = canPublishSource(permissions, Track.Source.Microphone);
    const canPublishScreenShare = canPublishSource(permissions, Track.Source.ScreenShare);
    const canChangeCameraDevice = canPublishCamera || Boolean(cameraTrack);
    const showPublishControls = state.viewer?.role === 'teacher'
        || canPublishCamera
        || canPublishMicrophone
        || canPublishScreenShare;

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

    return (
        <>
            <RoomAudioRenderer />

            <div className="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                <h1 className="text-3xl font-semibold text-slate-950">{state.classSession?.title}</h1>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {state.classSession?.summary || 'A calm, structured LiveKit classroom.'}
                </p>
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_22rem]">
                <div className="space-y-6">
                    <ParticipantMediaCard
                        panelId={presenterPanelId}
                        title={presenterTitle}
                        subtitle={presenterSubtitle}
                        trackRef={presenterTrack}
                        emptyLabel={presenterEmptyLabel}
                        headerActions={
                            state.viewer?.role === 'teacher' ? (
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
                            ) : showPublishControls ? (
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
                                </div>
                            ) : (
                                <div className="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                                    Awaiting teacher approval
                                </div>
                            )
                        }
                    />

                    {flashMessage ? (
                        <div className="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            {flashMessage}
                        </div>
                    ) : null}

                    {noActiveStream ? (
                        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            No one is broadcasting right now. The classroom is live, but the presenter panel is waiting for a camera or screen share.
                        </div>
                    ) : null}
                </div>

                <aside className="space-y-4">
                    <LiveChatPanel
                        state={state}
                        participants={participants}
                        presenterParticipant={presenterParticipant}
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
                </aside>

                <section className="rounded-[2rem] border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">Instructions</div>
                    {state.classSession?.instructionsHtml ? (
                        <div
                            className="prose prose-slate mt-4 max-w-none prose-headings:scroll-m-20 prose-headings:font-semibold prose-p:leading-7 prose-li:leading-7"
                            dangerouslySetInnerHTML={{ __html: state.classSession.instructionsHtml }}
                        />
                    ) : (
                        <p className="mt-3 text-sm leading-6 text-gray-600">
                            Instructions can be added per class session and shown here.
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
                    setState(payload);
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
