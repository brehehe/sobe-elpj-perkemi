<?php

namespace App\Http\Controllers;

use App\Services\BackupHealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackupHealthController extends Controller
{
    public function __invoke(Request $request, BackupHealthService $backupHealth): Response
    {
        abort_unless($request->user()->can('admin.manage'), 403);

        return Inertia::render('administration/backup-health', $backupHealth->snapshot());
    }
}
