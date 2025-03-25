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
        Schema::create('cdn_resources', function (Blueprint $table) {
            $table->id();
            $table->string('request_code');
            $table->string('cdn_resource_hostname');
            $table->string('cdn_origin_hostname');
            $table->string('cdn_origin_server_port');
            $table->bigInteger('cdn_ingest_point_id');
            $table->bigInteger('cdn_target_group_id')->nullable();
            $table->bigInteger('cdn_cname_id')->nullable();
            $table->boolean('cname_verify')->default(0);
            $table->boolean('provisioned')->default(0);
            $table->string('cdn_template_id');
            $table->string('storage_id')->nullable();
            $table->bigInteger('cdn_tenant_id');
            $table->integer('attempt_create')->nullable();
            $table->integer('attempt_delete')->nullable();

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
        Schema::dropIfExists('cdn_resources');
    }
};
