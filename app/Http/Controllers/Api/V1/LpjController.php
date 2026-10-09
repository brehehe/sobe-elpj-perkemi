<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LpjResource;
use App\Models\Lpj;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LpjController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Lpj::class);
        $user = request()->user();
        $lpjs = Lpj::query()
            ->when(! $user->isGlobalAdministrator(), fn ($query) => $query->whereHas('activity', fn ($activity) => $activity->where('unit_id', $user->unit_id)))
            ->with(['activity:id,unit_id,code,title', 'owner:id,name,email', 'currentVersionRecord.verifications'])
            ->latest('updated_at')
            ->paginate(20);

        return LpjResource::collection($lpjs);
    }

    public function show(Lpj $lpj): LpjResource
    {
        $this->authorize('view', $lpj);

        return new LpjResource($lpj->load(['activity', 'owner', 'currentVersionRecord.verifications', 'currentVersionRecord.findings.correctiveActions', 'currentVersionRecord.approvals', 'currentVersionRecord.verifiedPack', 'currentVersionRecord.archive']));
    }
}
