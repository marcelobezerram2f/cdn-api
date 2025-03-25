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
        Schema::create('cdn_provisioning_steps', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cdn_resource_id')->nullable();
            $table->bigInteger('cdn_tenant_id')->nullable();
            $table->integer('step');
            $table->string('step_description');
            $table->string('status');
            $table->longText('observation')->nullable();
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
        Schema::dropIfExists('cdn_provisioning_steps');
    }
};
