<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\VideoResource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = $request->user()
            ->teams()
            ->with(['owner', 'members'])
            ->orderBy('teams.name')
            ->get();

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create([
            'name' => $request->name,
            'owner_id' => $request->user()->id,
            'invite_token' => Str::random(64),
        ]);

        $team->members()->attach($request->user()->id, [
            'role' => TeamMember::ROLE_OWNER,
            'joined_at' => now(),
        ]);

        return (new TeamResource($team->load(['owner', 'members'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Team $team): TeamResource|JsonResponse
    {
        if (! $team->hasMember($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        return new TeamResource($team->load(['owner', 'members']));
    }

    public function invite(Request $request, string $token): TeamResource|JsonResponse
    {
        $team = Team::where('invite_token', $token)->firstOrFail();

        return new TeamResource($team->load(['owner', 'members']));
    }

    public function join(Request $request, Team $team): TeamResource|JsonResponse
    {
        $validated = $request->validate([
            'invite_token' => ['required', 'string'],
        ]);

        if (! hash_equals($team->invite_token, $validated['invite_token'])) {
            return response()->json(['message' => '招待トークンが正しくありません'], 422);
        }

        if (! $team->hasMember($request->user())) {
            $team->members()->attach($request->user()->id, [
                'role' => TeamMember::ROLE_MEMBER,
                'joined_at' => now(),
            ]);
        }

        return new TeamResource($team->load(['owner', 'members']));
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        if (! $team->isOwner($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $team->delete();

        return response()->json(['message' => 'チームを削除しました']);
    }

    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        if (! $team->isOwner($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'オーナー自身は削除できません'], 422);
        }

        $team->members()->detach($user->id);

        return response()->json(['message' => 'メンバーを削除しました']);
    }

    public function leave(Request $request, Team $team): JsonResponse
    {
        if ($team->isOwner($request->user())) {
            return response()->json(['message' => 'オーナーはチームを脱退できません'], 422);
        }

        $team->members()->detach($request->user()->id);

        return response()->json(['message' => 'チームを脱退しました']);
    }

    public function videos(Request $request, Team $team): AnonymousResourceCollection|JsonResponse
    {
        if (! $team->hasMember($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $videos = $team->videos()
            ->with('team')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return VideoResource::collection($videos);
    }
}
