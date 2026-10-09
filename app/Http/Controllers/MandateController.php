<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditEventAction;
use App\Http\Requests\StoreMandate;
use App\Http\Requests\UpdateMandate;
use App\Models\Mandate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MandateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Mandate::class);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('mandates/index', [
            'mandates' => Mandate::query()
                ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
                ->with(['unit:id,code,name', 'creator:id,name'])
                ->withCount(['plans', 'activities'])
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Mandate $mandate): array => [
                    ...$mandate->only(['id', 'number', 'title', 'description', 'issued_at', 'expires_at', 'status']),
                    'unit' => $mandate->unit?->only(['id', 'code', 'name']),
                    'creator' => $mandate->creator?->only(['id', 'name']),
                    'plans_count' => $mandate->plans_count,
                    'activities_count' => $mandate->activities_count,
                    'can_update' => $user->can('update', $mandate),
                    'can_delete' => $user->can('delete', $mandate)
                        && $mandate->plans_count === 0
                        && $mandate->activities_count === 0,
                ]),
            'canCreate' => $user->can('create', Mandate::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Mandate::class);

        return Inertia::render('mandates/create');
    }

    public function store(StoreMandate $request, RecordAuditEventAction $audit): RedirectResponse
    {
        $mandate = Mandate::create([
            ...$request->validated(),
            'unit_id' => $request->user()->unit_id,
            'created_by' => $request->user()->id,
        ]);
        $audit->handle($mandate, 'mandate.created', $request->user(), toStatus: $mandate->status);

        return to_route('mandates.show', $mandate)->with('success', 'Mandat berhasil dibuat dan siap digunakan pada Planning.');
    }

    public function show(Request $request, Mandate $mandate): Response
    {
        $this->authorize('view', $mandate);
        /** @var User $user */
        $user = $request->user();
        $mandate->load([
            'unit:id,code,name',
            'creator:id,name',
            'plans' => fn ($query) => $query->with('currentVersionRecord')->latest('updated_at'),
            'activities:id,mandate_id,code,title,status',
        ]);

        return Inertia::render('mandates/show', [
            'mandate' => $mandate,
            'canUpdate' => $user->can('update', $mandate),
            'canDelete' => $user->can('delete', $mandate)
                && $mandate->plans()->doesntExist()
                && $mandate->activities()->doesntExist(),
        ]);
    }

    public function edit(Mandate $mandate): Response
    {
        $this->authorize('update', $mandate);

        return Inertia::render('mandates/edit', ['mandate' => $mandate]);
    }

    public function update(UpdateMandate $request, Mandate $mandate, RecordAuditEventAction $audit): RedirectResponse
    {
        $before = $mandate->only(['number', 'title', 'description', 'issued_at', 'expires_at', 'status']);
        $mandate->update($request->validated());
        $audit->handle($mandate, 'mandate.updated', $request->user(), toStatus: $mandate->status, payload: [
            'before' => $before,
            'after' => $mandate->fresh()->only(array_keys($before)),
        ]);

        return to_route('mandates.show', $mandate)->with('success', 'Mandat berhasil diperbarui.');
    }

    public function destroy(Request $request, Mandate $mandate, RecordAuditEventAction $audit): RedirectResponse
    {
        $this->authorize('delete', $mandate);
        if ($mandate->plans()->exists() || $mandate->activities()->exists()) {
            throw ValidationException::withMessages([
                'mandate' => 'Mandat tidak dapat dihapus karena sudah memiliki Planning atau kegiatan.',
            ]);
        }

        $audit->handle($mandate, 'mandate.deleted', $request->user(), fromStatus: $mandate->status, toStatus: 'DELETED');
        $mandate->delete();

        return to_route('mandates.index')->with('success', 'Mandat dipindahkan dari daftar aktif.');
    }
}
