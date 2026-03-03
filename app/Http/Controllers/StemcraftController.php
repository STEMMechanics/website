<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPenalty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StemcraftController extends Controller
{
    public function index(): View
    {
        return view('stemcraft.index');
    }

    public function join(): View
    {
        return view('stemcraft.join');
    }

    public function rules(): View
    {
        return view('stemcraft.rules');
    }

    public function faqs(): View
    {
        return view('stemcraft.faqs');
    }

    public function punishments(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $query = MinecraftPenalty::query()
            ->with('account')
            ->orderByDesc('started_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('username', 'like', '%'.$search.'%')
                    ->orWhere('uuid', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhere('by_username', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        if (in_array($type, MinecraftPenalty::TYPES, true)) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where(function ($builder): void {
                $builder->whereNull('lifted_at')
                    ->where(function ($restrictionBuilder): void {
                        $restrictionBuilder->where('is_permanent', true)
                            ->orWhere('ends_at', '>', now())
                            ->orWhere('type', MinecraftPenalty::TYPE_KICK);
                    });
            });
        } elseif ($status === 'lifted') {
            $query->whereNotNull('lifted_at');
        } elseif ($status === 'expired') {
            $query->whereNull('lifted_at')
                ->where('is_permanent', false)
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now());
        }

        /** @var LengthAwarePaginator $penalties */
        $penalties = $query->paginate(25)->onEachSide(1);

        return view('stemcraft.punishments', [
            'penalties' => $penalties,
            'search' => $search,
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }
}
