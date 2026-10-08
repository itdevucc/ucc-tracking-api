<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_shipments', function (Blueprint $table) {
            $table->string('pod_code', 5)->nullable();
            $table->string('pod_name')->nullable();
            $table->unsignedInteger('expected_container_count')->nullable();
            $table->timestamp('tracking_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_shipments', function (Blueprint $table) {
            $table->dropColumn(['pod_code', 'pod_name', 'expected_container_count', 'tracking_completed_at']);
        });
    }
};
