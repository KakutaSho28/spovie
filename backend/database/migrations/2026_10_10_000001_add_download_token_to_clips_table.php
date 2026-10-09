<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->string('download_token', 40)->nullable()->unique()->after('file_path');
        });

        // 既存クリップにもトークンを付与する
        DB::table('clips')->whereNull('download_token')->pluck('id')->each(function (int $id) {
            DB::table('clips')->where('id', $id)->update(['download_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropUnique(['download_token']);
            $table->dropColumn('download_token');
        });
    }
};
