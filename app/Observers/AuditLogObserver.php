<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogObserver
{
    /**
     * Fields to exclude from audit diffs.
     *
     * @var string[]
     */
    private array $excludedFields = [
        'password',
        'remember_token',
        'tfa_secret',
        'square_webhook_payload',
        'created_at',
        'updated_at',
    ];

    public function created(Model $model): void
    {
        $newValues = $this->filterValues($model->getAttributes());
        if ($newValues === []) {
            return;
        }

        $this->record($model, 'created', null, $newValues);
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        $dirty = $this->filterValues($dirty);
        if ($dirty === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];
        foreach (array_keys($dirty) as $key) {
            $oldValues[$key] = $model->getOriginal($key);
            $newValues[$key] = $model->getAttribute($key);
        }

        $this->record($model, 'updated', $this->filterValues($oldValues), $this->filterValues($newValues));
    }

    public function deleted(Model $model): void
    {
        $oldValues = $this->filterValues($model->getOriginal());
        if ($oldValues === []) {
            return;
        }

        $this->record($model, 'deleted', $oldValues, null);
    }

    private function record(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        $request = app()->bound('request') ? request() : null;
        $actorUserId = auth()->id();
        if ($request && $request->user()?->id) {
            $actorUserId = $request->user()->id;
        }

        $normalizedActorUserId = null;
        if ($actorUserId !== null && $actorUserId !== '') {
            $normalizedActorUserId = (string) $actorUserId;
        }

        AuditLog::query()->create([
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'actor_user_id' => $normalizedActorUserId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterValues(array $values): array
    {
        $filtered = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $this->excludedFields, true)) {
                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
