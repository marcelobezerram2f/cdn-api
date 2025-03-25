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
        Schema::create('cdn_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant');
            $table->string('api_key')->nullable();
            $table->integer('cdn_target_group_id')->nullable();
            $table->bigInteger('cdn_client_id');
            $table->integer('attempt_create')->nullable();
            $table->integer('attempt_delete')->nullable();
            $table->boolean('queued')->default(0);
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
        Schema::dropIfExists('cdn_tenant');
    }
};
