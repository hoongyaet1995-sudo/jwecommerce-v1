<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_enquiry_intakes', fn (Blueprint $t) => $t->json('asked_fields')->nullable());
    }

    public function down(): void
    {
        Schema::table('support_enquiry_intakes', fn (Blueprint $t) => $t->dropColumn('asked_fields'));
    }
};
