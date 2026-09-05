<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->foreignId('child_dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->foreignId('parent_field_id')->constrained('dataset_fields')->cascadeOnDelete();
            $table->foreignId('child_field_id')->constrained('dataset_fields')->cascadeOnDelete();
            $table->string('relationship_type'); // one_to_many
            $table->timestamps();

            $table->unique(['parent_dataset_id', 'child_dataset_id', 'parent_field_id', 'child_field_id']);
            $table->index('parent_dataset_id');
            $table->index('child_dataset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_relationships');
    }
};