<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_gis_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('gis_feature_id')->constrained('gis_features')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['complaint_id', 'gis_feature_id']);
            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('work_order_gis_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('gis_feature_id')->constrained('gis_features')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['work_order_id', 'gis_feature_id']);
            $table->index(['work_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_gis_feature');
        Schema::dropIfExists('complaint_gis_feature');
    }
};
