<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_contents', function (Blueprint $table) {
            $table->string('template', 60)->nullable();
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image_path')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('archived_at')->nullable();
        });

        Schema::create('website_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->index(['website_content_id', 'created_at'], 'website_revisions_content_created_index');
        });

        Schema::create('website_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('location', ['header', 'footer', 'secondary'])->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['location', 'name']);
        });

        Schema::create('website_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('website_menu_items')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('url', 500)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->string('target', 10)->default('_self');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['website_menu_id', 'parent_id', 'sort_order'], 'website_menu_items_order_index');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 30)->default('public');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 120)->index();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('category', 80)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('website_menu_items');
        Schema::dropIfExists('website_menus');
        Schema::dropIfExists('website_content_revisions');

        Schema::table('website_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'template', 'meta_title', 'meta_description', 'og_image_path', 'archived_at',
            ]);
        });
    }
};
