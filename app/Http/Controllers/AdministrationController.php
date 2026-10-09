<?php

namespace App\Http\Controllers;

use App\Actions\BuildAdministrationViewAction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __invoke(Request $request, BuildAdministrationViewAction $buildView): Response
    {
        abort_unless($request->user()->can('admin.manage'), 403);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('administration/index', $buildView->handle($user));
    }
}
