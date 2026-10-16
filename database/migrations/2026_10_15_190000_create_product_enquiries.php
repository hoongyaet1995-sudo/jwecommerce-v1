<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('product_enquiries', function(Blueprint $t){
            $t->uuid('id')->primary(); $t->string('reference',40)->unique();
            $t->char('owner_hash',64); $t->uuid('request_key'); $t->char('payload_hash',64);
            $t->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('product_title'); $t->json('product_snapshot');
            $t->string('name',120); $t->string('email',254); $t->string('phone',40)->nullable(); $t->text('requirements');
            $t->string('status',20)->default('new'); $t->string('outcome',10)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->unique(['owner_hash','request_key']); $t->index(['status','created_at']); $t->index(['assigned_to','created_at']);
        });
        Schema::create('product_enquiry_events',function(Blueprint $t){
            $t->id(); $t->foreignUuid('product_enquiry_id')->constrained('product_enquiries')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type',24); $t->text('content')->nullable(); $t->json('changes')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {Schema::dropIfExists('product_enquiry_events');Schema::dropIfExists('product_enquiries');}
};
