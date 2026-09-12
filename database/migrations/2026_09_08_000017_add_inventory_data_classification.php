<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLES=['inventory_warehouses','product_variants','orders','marketplace_orders','marketplace_stores'];

    public function up():void
    {
        foreach(self::TABLES as $name){
            Schema::table($name,function(Blueprint $table){
                // Unknown is intentionally not equivalent to operational or test eligibility.
                $table->string('data_classification',16)->default('unclassified')->index();
                $table->string('classification_reason',255)->nullable();
                $table->unsignedBigInteger('classified_by')->nullable();
                $table->timestamp('classified_at')->nullable();
            });
        }
    }

    public function down():void
    {
        foreach(self::TABLES as $name){
            Schema::table($name,function(Blueprint $table){
                $table->dropIndex(['data_classification']);
                $table->dropColumn(['data_classification','classification_reason','classified_by','classified_at']);
            });
        }
    }
};
