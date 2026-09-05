<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->string('data_type'); // string, integer, decimal, boolean, date, datetime, text
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_identifier')->default(false);
            $table->text('default_value')->nullable();
            $table->integer('sort_order')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'name']);
            $table->index(['dataset_id', 'is_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_fields');
    }
};