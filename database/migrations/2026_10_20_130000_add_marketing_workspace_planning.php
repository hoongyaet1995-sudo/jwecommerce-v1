<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_projects', function (Blueprint $t) {
            $t->json('preferences')->nullable();
            $t->json('planning_state')->nullable();
        });
        Schema::create('marketing_planning_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('project_id')->constrained('marketing_projects');
            $t->foreignId('actor_id')->constrained('users');
            $t->uuid('request_id');
            $t->string('kind', 40);
            $t->unsignedInteger('source_version');
            $t->string('payload_hash', 64);
            $t->json('payload');
            $t->timestamps();
            $t->unique(['actor_id', 'request_id']);
            $t->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_planning_events');
        Schema::table('marketing_projects', fn (Blueprint $t) => $t->dropColumn(['preferences', 'planning_state']));
    }
};
