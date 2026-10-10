<?php

namespace Database\Seeders;

use App\Models\Annotation;
use App\Models\Comment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 公開デモ用のデータ: デモユーザー2人、デモチーム、YouTube 動画、アノテーション、コメント。
 *
 * - YouTube 動画のみ（著作権のある動画ファイルはアップロードしない）
 * - 何度実行しても重複しない（デモアカウントのパスワードは毎回デモ用の値に戻る）
 * - イベントのブロードキャストを避けるため、Service ではなくモデルで直接作成する
 */
class DemoSeeder extends Seeder
{
    public const ALICE_EMAIL = 'demo1@spovie.example';
    public const BOB_EMAIL = 'demo2@spovie.example';
    public const TEAM_NAME = 'デモチーム';

    public function run(): void
    {
        $alice = $this->user('アリス（デモ）', self::ALICE_EMAIL);
        $bob = $this->user('ボブ（デモ）', self::BOB_EMAIL);

        $team = Team::firstOrCreate(
            ['name' => self::TEAM_NAME, 'owner_id' => $alice->id],
            ['invite_token' => Str::random(64)],
        );
        $this->member($team, $alice, TeamMember::ROLE_OWNER);
        $this->member($team, $bob, TeamMember::ROLE_MEMBER);

        [$firstId, $secondId] = config('demo.youtube_ids');
        $first = $this->video($alice, $team, 'デモ動画 A（Big Buck Bunny / CC BY）', $firstId);
        $second = $this->video($alice, $team, 'デモ動画 B（Sintel / CC BY）', $secondId);

        $canvas = json_decode((string) file_get_contents(__DIR__ . '/demo-canvas.json'), true, flags: JSON_THROW_ON_ERROR);

        $a1 = $this->annotation($first, 12, 20, $canvas, '手前の動きに注目。ここで一度止めて確認しよう');
        $a2 = $this->annotation($first, 45, 52, ['canvas_width' => 1280, 'canvas_height' => 720, 'objects' => []], '描画なしのメモだけのアノテーション');
        $this->annotation($second, 30, 38, $canvas, 'カメラの切り替わりのタイミング');

        $this->comment($a1, $bob, 'ここ、もう少し早く動けると良さそうです');
        $this->comment($a1, $alice, 'ありがとう！次の練習で確認しよう');
        $this->comment($a2, $bob, '共有リンクで他のメンバーにも見せました');
    }

    private function user(string $name, string $email): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(config('demo.password'))],
        );
    }

    private function member(Team $team, User $user, string $role): void
    {
        TeamMember::firstOrCreate(
            ['team_id' => $team->id, 'user_id' => $user->id],
            ['role' => $role, 'joined_at' => now()],
        );
    }

    private function video(User $owner, Team $team, string $title, string $youtubeId): Video
    {
        return Video::updateOrCreate(
            ['user_id' => $owner->id, 'team_id' => $team->id, 'title' => $title],
            ['type' => Video::TYPE_YOUTUBE, 'youtube_video_id' => $youtubeId, 'file_path' => null],
        );
    }

    /**
     * @param  array<string, mixed>  $canvas
     */
    private function annotation(Video $video, int $start, int $end, array $canvas, string $comment): Annotation
    {
        return Annotation::firstOrCreate(
            ['video_id' => $video->id, 'comment' => $comment],
            ['start_seconds' => $start, 'end_seconds' => $end, 'canvas_data' => $canvas],
        );
    }

    private function comment(Annotation $annotation, User $author, string $body): void
    {
        Comment::firstOrCreate(
            ['annotation_id' => $annotation->id, 'user_id' => $author->id, 'body' => $body],
        );
    }
}
