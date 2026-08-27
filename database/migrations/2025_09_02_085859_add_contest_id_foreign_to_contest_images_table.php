<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the contest_images -> contests foreign key now that the `contests`
     * table (created by 2025_09_02_085858_create_contests_table) exists.
     */
    public function up(): void
    {
        Schema::table('contest_images', function (Blueprint $table) {
            $table->foreign('contest_id')
                ->references('id')->on('contests')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contest_images', function (Blueprint $table) {
            $table->dropForeign(['contest_id']);
        });
    }
};
