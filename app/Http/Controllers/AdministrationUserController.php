<?php

namespace App\Http\Controllers;

use App\Actions\BuildAdministrationUsersViewAction;
use App\Actions\CreateAdministrationUserAction;
use App\Actions\UpdateAdministrationUserAction;
use App\Http\Requests\AdministrationUserIndexRequest;
use App\Http\Requests\StoreAdministrationUserRequest;
use App\Http\Requests\UpdateAdministrationUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationUserController extends Controller
{
    public function index(AdministrationUserIndexRequest $request, BuildAdministrationUsersViewAction $buildView): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('administration/users', $buildView->handle($actor, $request->validated()));
    }

    public function create(Request $request, BuildAdministrationUsersViewAction $buildView): Response
    {
        $this->authorize('create', User::class);
        /** @var User $actor */
        $actor = $request->user();
        $view = $buildView->handle($actor, []);

        return Inertia::render('administration/users/create', [
            'scope' => $view['scope'],
            'options' => $view['options'],
        ]);
    }

    public function store(StoreAdministrationUserRequest $request, CreateAdministrationUserAction $createUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $createUser->handle($actor, $request->validated());
        $message = $result['invitation_status'] === Password::ResetLinkSent
            ? 'Pengguna dibuat. Tautan verifikasi dan atur password telah dikirim.'
            : 'Pengguna dibuat, tetapi tautan atur password belum terkirim. Pengguna dapat memakai menu lupa password.';

        return to_route('administration.users.index')->with('success', $message);
    }

    public function show(Request $request, User $managedUser): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureScoped($actor, $managedUser);
        $this->authorize('view', $managedUser);

        return Inertia::render('administration/users/show', [
            'managedUser' => $this->serializeUser($managedUser),
            'canUpdate' => $actor->can('update', $managedUser),
            'canDelete' => $actor->can('delete', $managedUser),
        ]);
    }

    public function edit(Request $request, User $managedUser, BuildAdministrationUsersViewAction $buildView): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureScoped($actor, $managedUser);
        $this->authorize('update', $managedUser);
        $view = $buildView->handle($actor, []);

        return Inertia::render('administration/users/edit', [
            'managedUser' => $this->serializeUser($managedUser),
            'scope' => $view['scope'],
            'options' => $view['options'],
        ]);
    }

    public function update(UpdateAdministrationUserRequest $request, User $managedUser, UpdateAdministrationUserAction $updateUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $updateUser->handle($managedUser, $actor, $request->validated());

        return back()->with('success', 'Profil akses pengguna diperbarui dan dicatat pada audit trail.');
    }

    public function destroy(Request $request, User $managedUser, UpdateAdministrationUserAction $updateUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureScoped($actor, $managedUser);
        $this->authorize('delete', $managedUser);
        $managedUser->loadMissing('roles');
        $updateUser->handle($managedUser, $actor, [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'unit_id' => $managedUser->unit_id,
            'role' => $managedUser->roles->firstOrFail()->name,
            'is_active' => false,
        ]);

        return to_route('administration.users.index')->with('success', 'Akses pengguna dinonaktifkan; riwayat dan relasi audit tetap dipertahankan.');
    }

    private function ensureScoped(User $actor, User $managedUser): void
    {
        if (! $actor->isGlobalAdministrator() && $actor->unit_id !== $managedUser->unit_id) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $managedUser): array
    {
        $managedUser->loadMissing(['unit:id,code,name', 'roles:id,name']);

        return [
            ...$managedUser->only(['id', 'name', 'email', 'is_active', 'email_verified_at', 'last_login_at', 'created_at']),
            'unit' => $managedUser->unit?->only(['id', 'code', 'name']),
            'role' => $managedUser->roles->first()?->name,
        ];
    }
}
