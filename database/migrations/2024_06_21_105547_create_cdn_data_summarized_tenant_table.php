<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cdn_data_summarized_tenant', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable();
            $table->string('tenant');
            $table->bigInteger('total_bytes_transmitted');
            $table->date('summary_date');
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
        Schema::dropIfExists('cdn_data_summarized_tenant');
    }
};
