<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkshopReportService
{
    /**
     * @return Builder<Workshop>
     */
    public function queryFor(User $user, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $locationId = trim((string) ($filters['location'] ?? ''));
        $photoFilter = (string) ($filters['photos'] ?? 'all');
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $workshopId = trim((string) ($filters['workshop_id'] ?? ''));

        return Workshop::query()
            ->where('requested_by_user_id', (string) $user->id)
            ->when($workshopId !== '', fn (Builder $query): Builder => $query->whereKey($workshopId))
            ->when($locationId !== '', fn (Builder $query): Builder => $query->where('location_id', $locationId))
            ->when($dateFrom !== '', fn (Builder $query): Builder => $query->whereDate('starts_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query): Builder => $query->whereDate('starts_at', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like): void {
                    $searchQuery
                        ->where('workshops.title', 'like', $like)
                        ->orWhereHas('location', fn (Builder $locationQuery): Builder => $locationQuery
                            ->where('name', 'like', $like)
                            ->orWhere('address', 'like', $like)
                            ->orWhere('suburb', 'like', $like)
                            ->orWhere('state', 'like', $like)
                            ->orWhere('postcode', 'like', $like))
                        ->orWhereHas('hostedFor', fn (Builder $organisationQuery): Builder => $organisationQuery->where('name', 'like', $like));
                });
            })
            ->when($photoFilter === 'with', fn (Builder $query): Builder => $query->whereHas(
                'photos',
                fn ($photoQuery) => $this->applyPhotoAccessScope($photoQuery, $user),
            ))
            ->when($photoFilter === 'without', fn (Builder $query): Builder => $query->whereDoesntHave(
                'photos',
                fn ($photoQuery) => $this->applyPhotoAccessScope($photoQuery, $user),
            ))
            ->with([
                'location',
                'photos' => fn ($query) => $this->applyPhotoAccessScope($query, $user),
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    public function canAccess(User $user, Workshop $workshop): bool
    {
        return (string) $workshop->requested_by_user_id === (string) $user->id;
    }

    public function decorate(Workshop $workshop, User $user): Workshop
    {
        $photos = $workshop->relationLoaded('photos')
            ? $workshop->photos
            : $this->photosFor($workshop, $user);

        $workshop->setAttribute('report_attendance', $this->attendanceCount($workshop));
        $workshop->setAttribute('report_schedule', $this->schedule($workshop));
        $workshop->setAttribute('report_location_name', $workshop->getLocationName());
        $workshop->setAttribute('report_location_address', $this->locationAddress($workshop));
        $workshop->setAttribute('report_photos', $photos
            ->map(fn (Media $media): array => $this->photoPayload($workshop, $media))
            ->values()
            ->all());

        return $workshop;
    }

    public function locationAddress(Workshop $workshop): ?string
    {
        if ($workshop->isOnlineWorkshop() || $workshop->isStemcraftWorkshop() || ! $workshop->location) {
            return null;
        }

        $location = $workshop->location;
        $stateAndPostcode = trim(implode(' ', array_filter([
            trim((string) $location->state),
            trim((string) $location->postcode),
        ])));
        $parts = array_filter([
            trim((string) $location->address),
            trim((string) $location->suburb),
            $stateAndPostcode,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return Collection<int, Media>
     */
    public function photosFor(Workshop $workshop, User $user): Collection
    {
        return $workshop->photos()
            ->tap(fn ($query) => $this->applyPhotoAccessScope($query, $user))
            ->orderByDesc('photographed_at')
            ->orderByDesc('media.created_at')
            ->get();
    }

    /**
     * @return Collection<int, Location>
     */
    public function locationOptionsFor(User $user): Collection
    {
        return Location::query()
            ->whereHas('workshops', fn (Builder $query): Builder => $query->where('requested_by_user_id', (string) $user->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function photoIsAccessible(User $user, Workshop $workshop, Media $media): bool
    {
        if (! $this->canAccess($user, $workshop)) {
            return false;
        }

        return $workshop->photos()
            ->where('media.name', (string) $media->getKey())
            ->tap(fn ($query) => $this->applyPhotoAccessScope($query, $user))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function photoPayload(Workshop $workshop, Media $media): array
    {
        $title = trim((string) $media->title);
        if ($title === '') {
            $title = pathinfo((string) $media->name, PATHINFO_FILENAME);
        }

        $routeParameters = [
            'workshop' => $workshop->getRouteKey(),
            'media' => $media->getRouteKey(),
        ];

        return [
            'title' => $title,
            'caption' => trim((string) ($media->caption ?? '')),
            'alt' => $title !== '' ? $title : $workshop->title,
            'is_video' => str_starts_with(strtolower((string) $media->mime_type), 'video/'),
            'thumbnail_url' => route('account.workshop-report.photo', [...$routeParameters, 'variant' => 'thumbnail']),
            'url' => route('account.workshop-report.photo', [...$routeParameters, 'variant' => 'md']),
            'download_url' => route('account.workshop-report.photo.download', $routeParameters),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function schedule(Workshop $workshop): array
    {
        if ($workshop->isCourse()) {
            return $workshop->courseScheduleDisplayLines() ?: ['Date TBC'];
        }

        if (! $workshop->starts_at) {
            return ['Date TBC'];
        }

        if (! $workshop->ends_at || ! $workshop->starts_at->isSameDay($workshop->ends_at)) {
            return [
                $workshop->starts_at->format('D j M Y g:ia')
                .' – '.($workshop->ends_at?->format('D j M Y g:ia') ?? 'time TBC'),
            ];
        }

        return [
            $workshop->starts_at->format('D j M Y g:ia')
            .' – '.$workshop->ends_at->format('g:ia'),
        ];
    }

    public function attendanceCount(Workshop $workshop): ?int
    {
        $dropInCount = (int) WorkshopAttendance::query()
            ->where('workshop_id', $workshop->id)
            ->whereNull('ticket_id')
            ->count();

        if ($workshop->isCourse()) {
            $ticketAttendanceCount = (int) DB::table('workshop_session_attendance')
                ->join('tickets', 'tickets.id', '=', 'workshop_session_attendance.ticket_id')
                ->where('workshop_session_attendance.workshop_id', $workshop->id)
                ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
                ->distinct('workshop_session_attendance.ticket_id')
                ->count('workshop_session_attendance.ticket_id');
        } else {
            $ticketAttendanceCount = (int) $workshop->tickets()
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->whereNotNull('attended_at')
                ->count();
        }

        $attendanceCount = $ticketAttendanceCount + $dropInCount;

        if ($attendanceCount > 0 || $workshop->attendance_no_attendees_confirmed_at !== null) {
            return $attendanceCount;
        }

        return null;
    }

    /**
     * Apply the same ordinary-user media access boundary used by the media area:
     * public, unpassworded media is visible, as is the user's own unpassworded
     * media. Protected/private media remains out of the report unless the
     * requested user already owns it.
     */
    public function applyPhotoAccessScope(Relation|Builder $query, User $user): Relation|Builder
    {
        $query->where(function (Builder $mimeQuery): void {
            $mimeQuery
                ->where('media.mime_type', 'like', 'image/%')
                ->orWhere('media.mime_type', 'like', 'video/%');
        });

        if ($user->isAdmin()) {
            return $query;
        }

        return $query
            ->whereNull('media.password')
            ->where(function (Builder $accessQuery) use ($user): void {
                $accessQuery->where(function (Builder $publicQuery): void {
                    $publicQuery
                        ->where(function (Builder $visibilityQuery): void {
                            $visibilityQuery
                                ->whereNull('media.visibility')
                                ->orWhere('media.visibility', 'public');
                        })
                        ->whereNotExists(function ($privateLinkQuery): void {
                            $privateLinkQuery
                                ->selectRaw('1')
                                ->from('mediables as private_media_links')
                                ->whereColumn('private_media_links.media_name', 'media.name')
                                ->where('private_media_links.collection', 'private');
                        });
                })->orWhere('media.user_id', (string) $user->id);
            });
    }
}
