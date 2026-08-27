<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contest_images', function (Blueprint $table) {
            $table->id();
            // NOTE: the foreign key to `contests` is added in a later migration
            // (2025_09_02_085859_add_contest_id_foreign_to_contest_images_table)
            // because this migration runs BEFORE create_contests_table below —
            // adding the constraint here fails on MySQL ("Foreign key constraint
            // is incorrectly formed") since the referenced table doesn't exist yet.
            // SQLite silently tolerated this ordering bug, which is how it went
            // unnoticed until the MySQL migration.
            $table->unsignedBigInteger('contest_id');
            $table->string('image_path');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_images');
    }
};
