<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_work_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['complaint_id', 'work_order_id']);
            $table->index('work_order_id');
        });

        DB::table('work_orders')
            ->whereNotNull('complaint_id')
            ->orderBy('id')
            ->eachById(function ($workOrder) {
                DB::table('complaint_work_order')->insertOrIgnore([
                    'complaint_id' => $workOrder->complaint_id,
                    'work_order_id' => $workOrder->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_work_order');
    }
};
