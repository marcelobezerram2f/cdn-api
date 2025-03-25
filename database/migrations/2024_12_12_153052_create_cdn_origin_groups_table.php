<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cdn_origin_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name');
            $table->string('type')->nullable(); // ballance , failover, default
            $table->bigInteger('cdn_tenant_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cdn_origin_groups', function (Blueprint $table) {
            Schema::dropIfExists('cdn_origin_groups');
        });
    }
};
