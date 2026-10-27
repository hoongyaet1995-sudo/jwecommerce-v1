<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_production_plans', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignUuid('revision_id')->constrained('marketing_revisions');
            $t->foreignId('prepared_by')->constrained('users');
            $t->uuid('request_id');
            $t->unsignedInteger('project_version');
            $t->string('fingerprint', 64);
            $t->json('snapshot');
            $t->json('manifest');
            $t->string('status', 24);
            // DATETIME avoids MariaDB's legacy first-TIMESTAMP ON UPDATE behavior.
            $t->dateTime('expires_at');
            $t->foreignId('confirmed_by')->nullable()->constrained('users');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'request_id']);
            $t->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_production_plans');
    }
};
