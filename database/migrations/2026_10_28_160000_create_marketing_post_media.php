<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::create('marketing_post_media',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained('users');$t->string('title');$t->string('path');$t->string('sha256',64);$t->unsignedBigInteger('bytes');$t->decimal('seconds',10,3);$t->timestamps();$t->index(['user_id','created_at']);});}public function down():void{Schema::dropIfExists('marketing_post_media');}};
