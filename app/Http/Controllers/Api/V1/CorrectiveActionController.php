<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitCorrectiveActionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCorrectiveAction;
use App\Models\Finding;
use Illuminate\Http\JsonResponse;

class CorrectiveActionController extends Controller
{
    public function store(StoreCorrectiveAction $request, Finding $finding, SubmitCorrectiveActionAction $submit): JsonResponse
    {
        $correctiveAction = $submit->handle($finding, $request->user(), $request->string('action')->toString(), $request->file('evidence'));

        return response()->json(['data' => $correctiveAction], 201);
    }
}
