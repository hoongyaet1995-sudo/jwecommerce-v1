<?php

use App\Services\Admin\CommerceStatusRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_status_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('description', 500)->default('');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->unique(['scope', 'code']);
        });
        foreach (CommerceStatusRegistry::DEFAULTS as $scope => $statuses) {
            foreach ($statuses as $i => $status) {
                DB::table('commerce_status_definitions')->insert(['scope' => $scope, 'code' => $status[0], 'name' => $status[1], 'description' => $status[2], 'sort_order' => ($i + 1) * 10, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_status_definitions');
    }
};
