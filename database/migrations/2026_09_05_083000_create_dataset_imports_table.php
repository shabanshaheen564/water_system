<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('source_format'); // csv, xlsx
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->jsonb('error_summary')->nullable();
            $table->timestamps();

            $table->index('dataset_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_imports');
    }
};