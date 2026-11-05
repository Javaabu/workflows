<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function table(string $name): string
    {
        return config("workflows.tables.{$name}", $name);
    }

    public function up(): void
    {
        Schema::create($this->table('workflows'), function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable()->index();
            $table->json('settings')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('published_version_id')->nullable()->index();

            // Translations (javaabu/translatable). Harmless without it.
            $table->json('translations')->nullable();
            $table->string('lang')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($this->table('workflow_versions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($this->table('workflows'))->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->json('definition');
            $table->string('hash', 64)->nullable();
            $table->nullableMorphs('author');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'number']);
        });

        Schema::create($this->table('workflow_instances'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($this->table('workflows'))->cascadeOnDelete();
            $table->foreignId('workflow_version_id')->constrained($this->table('workflow_versions'))->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->nullableMorphs('starter');
            $table->string('current_step')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('outcome')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create($this->table('workflow_tasks'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained($this->table('workflow_instances'))->cascadeOnDelete();
            $table->string('step');
            $table->string('status')->default('open')->index();
            $table->string('transition')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('form_entry_id')->nullable()->index();
            $table->nullableMorphs('completed_by');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create($this->table('workflow_task_assignees'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_task_id')->constrained($this->table('workflow_tasks'))->cascadeOnDelete();
            $table->string('kind');
            $table->nullableMorphs('assignee');
            $table->string('value')->nullable();
            $table->timestamps();

            $table->index(['kind', 'value']);
        });

        Schema::create($this->table('workflow_history'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained($this->table('workflow_instances'))->cascadeOnDelete();
            $table->unsignedBigInteger('workflow_task_id')->nullable()->index();
            $table->string('event');
            $table->string('step')->nullable();
            $table->string('from_step')->nullable();
            $table->string('transition')->nullable();
            $table->string('action')->nullable();
            $table->nullableMorphs('actor');
            $table->text('comment')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['workflow_history', 'workflow_task_assignees', 'workflow_tasks', 'workflow_instances', 'workflow_versions', 'workflows'] as $table) {
            Schema::dropIfExists($this->table($table));
        }
    }
};
