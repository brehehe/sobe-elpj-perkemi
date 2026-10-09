<?php

namespace App\Http\Controllers;

use App\Actions\SearchWorkspaceAction;
use App\Http\Requests\GlobalSearchRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, SearchWorkspaceAction $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = $request->string('q')->toString();
        $data = $search->handle($user, $query);

        return response()->json([
            'data' => $data,
            'meta' => [
                'query' => $query,
                'total' => count($data),
                'limit_per_type' => SearchWorkspaceAction::LIMIT_PER_TYPE,
            ],
        ]);
    }
}
