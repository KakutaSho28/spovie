<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\VideoResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TeamResource::collection($this->teams->listFor($request->user()));
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->teams->create($request->user(), $request->name);

        return (new TeamResource($team->load(['owner', 'members'])))->response()->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load(['owner', 'members']));
    }

    public function invite(string $token): TeamResource
    {
        return new TeamResource($this->teams->findByInviteToken($token)->load(['owner', 'members']));
    }

    public function join(Request $request, Team $team): TeamResource
    {
        $validated = $request->validate(['invite_token' => ['required', 'string']]);
        $this->teams->join($team, $request->user(), $validated['invite_token']);

        return new TeamResource($team->load(['owner', 'members']));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('manage', $team);
        $this->teams->delete($team);

        return response()->json(['message' => 'チームを削除しました']);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $this->authorize('manage', $team);
        $this->teams->removeMember($team, $request->user(), $user);

        return response()->json(['message' => 'メンバーを削除しました']);
    }

    public function leave(Request $request, Team $team): JsonResponse
    {
        $this->teams->leave($team, $request->user());

        return response()->json(['message' => 'チームを脱退しました']);
    }

    public function videos(Request $request, Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        return VideoResource::collection(
            $this->teams->paginateVideos($team, $request->integer('per_page', 20)),
        );
    }
}
