<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ArchiveLpjAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveLpj;
use App\Models\Lpj;
use Illuminate\Http\JsonResponse;

class LpjArchiveController extends Controller
{
    public function store(ArchiveLpj $request, Lpj $lpj, ArchiveLpjAction $archive): JsonResponse
    {
        return response()->json(['data' => $archive->handle($lpj, $request->user())], 201);
    }
}
