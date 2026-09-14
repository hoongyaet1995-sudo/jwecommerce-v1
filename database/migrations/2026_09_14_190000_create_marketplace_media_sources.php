<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('marketplace_media_sources',function(Blueprint $t){$t->id();$t->char('source_hash',64)->unique();$t->text('source_url');$t->uuid('asset_id');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('marketplace_media_sources');}
};
