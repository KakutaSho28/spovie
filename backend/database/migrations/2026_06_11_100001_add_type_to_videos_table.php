<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->enum('type', ['youtube', 'upload'])
                ->default('youtube')
                ->after('user_id');
            $table->string('file_path', 500)->nullable()->after('youtube_video_id');
            $table->string('youtube_video_id', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['type', 'file_path']);
            $table->string('youtube_video_id', 20)->nullable(false)->change();
        });
    }
};
