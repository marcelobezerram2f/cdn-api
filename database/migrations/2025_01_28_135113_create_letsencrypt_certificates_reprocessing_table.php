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
        Schema::create('letsencrypt_certificates_reprocessing', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cdn_resource_id')->nullable();
            $table->string('status');
            $table->string('domain');
            $table->longText('csr');
            $table->longText('private_key');
            $table->string('url');
            $table->longText('payload');
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
        Schema::dropIfExists('letsencrypt_certificates_reprocessing');
    }
};
