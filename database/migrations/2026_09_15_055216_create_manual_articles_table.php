<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('manual_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->string('level', 30)->index();
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('estimated_minutes')->default(5);
            $table->json('roles_json')->nullable();
            $table->json('keywords_json')->nullable();
            $table->json('prerequisites_json')->nullable();
            $table->json('steps_json')->nullable();
            $table->json('required_fields_json')->nullable();
            $table->text('expected_result')->nullable();
            $table->json('common_errors_json')->nullable();
            $table->text('blocked_action')->nullable();
            $table->string('escalation_role')->nullable();
            $table->string('module')->nullable()->index();
            $table->string('permission')->nullable()->index();
            $table->string('route_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->string('version', 30)->default('1.0');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_articles');
    }
};
