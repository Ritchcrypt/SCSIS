<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();

                $table->string('title');
                $table->text('content');

                $table->string('category', 50)->default('general');
                $table->string('priority', 50)->default('normal');
                $table->string('audience', 50)->default('everyone');

                $table->boolean('is_active')->default(true);
                $table->boolean('activate_calamity_mode')->default(false);

                $table->foreignId('posted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('published_at')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'title')) {
                $table->string('title')->after('id');
            }

            if (! Schema::hasColumn('announcements', 'content')) {
                $table->text('content')->after('title');
            }

            if (! Schema::hasColumn('announcements', 'category')) {
                $table->string('category', 50)->default('general')->after('content');
            }

            if (! Schema::hasColumn('announcements', 'priority')) {
                $table->string('priority', 50)->default('normal')->after('category');
            }

            if (! Schema::hasColumn('announcements', 'audience')) {
                $table->string('audience', 50)->default('everyone')->after('priority');
            }

            if (! Schema::hasColumn('announcements', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('audience');
            }

            if (! Schema::hasColumn('announcements', 'activate_calamity_mode')) {
                $table->boolean('activate_calamity_mode')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('announcements', 'posted_by')) {
                $table->foreignId('posted_by')
                    ->nullable()
                    ->after('activate_calamity_mode')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('announcements', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('posted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};