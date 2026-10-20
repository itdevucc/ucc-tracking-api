<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('tracking_events', function (Blueprint $table) {

            $table->dateTime('event_date_time')->change();

        });

    }

    public function down(): void
    {

        Schema::table('tracking_events', function (Blueprint $table) {

            $table->timestamp('event_date_time')->change();

        });

    }
};
