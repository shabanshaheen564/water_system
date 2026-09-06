<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_record_id')->constrained('dataset_records')->cascadeOnDelete();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->geometry('geometry')->nullable();
            $table->string('geometry_type')->nullable();
            $table->integer('srid')->nullable();
            $table->timestamps();

            $table->index('dataset_record_id');
            $table->index('dataset_id');
            $table->index('geometry_type');
        });

        // Create spatial index using raw SQL
        DB::statement('CREATE INDEX IF NOT EXISTS gis_features_geometry_idx ON gis_features USING GIST (geometry)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gis_features_geometry_idx');
        Schema::dropIfExists('gis_features');
    }
};