<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('msc_service_bus_messages', function (Blueprint $table) {

            $table->timestamp('last_attempted_at')->nullable()->index();

        });

    }

    public function down(): void
    {

        Schema::table('msc_service_bus_messages', function (Blueprint $table) {

            $table->dropIndex(['last_attempted_at']);

            $table->dropColumn('last_attempted_at');

        });

    }
};
