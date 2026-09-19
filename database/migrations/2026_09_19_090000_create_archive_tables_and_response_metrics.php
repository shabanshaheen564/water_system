<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('first_response_at')->nullable()->after('processed_at');
            $table->index('first_response_at');
        });

        Schema::create('archived_complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->unique();
            $table->string('complaint_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->text('processing_notes')->nullable();
            $table->text('solution')->nullable();
            $table->string('status', 30);
            $table->string('priority', 30);
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->unsignedInteger('response_time_minutes')->nullable();
            $table->unsignedInteger('resolution_time_minutes')->nullable();
            $table->unsignedInteger('total_time_minutes')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('archived_at');
            $table->index('resolved_at');
        });

        Schema::create('archived_work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->unique();
            $table->string('work_order_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('status', 30);
            $table->string('priority', 30);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->unsignedInteger('response_time_minutes')->nullable();
            $table->unsignedInteger('execution_time_minutes')->nullable();
            $table->unsignedInteger('total_time_minutes')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('archived_at');
            $table->index('completed_at');
        });

        Schema::create('archived_complaint_work_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archived_complaint_id')->constrained('archived_complaints')->cascadeOnDelete();
            $table->foreignId('archived_work_order_id')->constrained('archived_work_orders')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['archived_complaint_id', 'archived_work_order_id'], 'archived_complaint_work_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_complaint_work_order');
        Schema::dropIfExists('archived_work_orders');
        Schema::dropIfExists('archived_complaints');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['first_response_at']);
            $table->dropColumn('first_response_at');
        });
    }
};
