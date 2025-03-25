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
        Schema::create('cdn_letsencrypt_acme_registries', function (Blueprint $table) {
            $table->id();
            $table->string('cdn_resource_id')->nullable();
            $table->string('username');
            $table->string('password');
            $table->string('fulldomain');
            $table->string('subdomain');
            $table->dateTime('certificate_created')->nullable();
            $table->dateTime('certificate_expires')->nullable();
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
        Schema::dropIfExists('letsencrypt_acme_registries');
    }
};
