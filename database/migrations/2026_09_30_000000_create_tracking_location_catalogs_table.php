<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_location_catalogs', function (Blueprint $table) {
            $table->id();
            $table->char('un_location_code', 5)->index();
            $table->char('alternative_un_location_code', 5)->nullable()->index();
            $table->string('terminal_code', 6);
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('source_version', 30);
            $table->timestamps();

            $table->unique(
                ['un_location_code', 'terminal_code', 'valid_from'],
                'tracking_location_catalogs_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_location_catalogs');
    }
};
