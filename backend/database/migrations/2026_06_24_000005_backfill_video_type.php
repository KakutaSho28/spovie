<?php

use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('videos')
            ->whereNull('type')
            ->whereNotNull('youtube_video_id')
            ->update(['type' => Video::TYPE_YOUTUBE]);
    }

    public function down(): void
    {
        //
    }
};
