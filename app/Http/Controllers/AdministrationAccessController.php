<?php

namespace App\Http\Controllers;

use App\Actions\BuildAdministrationAccessViewAction;
use App\Actions\UpdateRolePermissionsAction;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdministrationAccessController extends Controller
{
    public function index(Request $request, BuildAdministrationAccessViewAction $buildView): Response
    {
        abort_unless($request->user()->can('admin.manage'), 403);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('administration/access', $buildView->handle($actor));
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role, UpdateRolePermissionsAction $updatePermissions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $updatePermissions->handle($role, $actor, $request->validated('permissions'));

        return back()->with('success', 'Permission role diperbarui dan dicatat pada audit trail.');
    }
}
