<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->unsignedInteger('map_order')->default(0)->after('is_active');
            $table->boolean('default_visible')->default(true)->after('map_order');
            $table->decimal('map_opacity', 3, 2)->default(1)->after('default_visible');
            $table->string('display_color', 7)->default('#475467')->after('map_opacity');
            $table->index(['is_spatial', 'is_active', 'map_order']);
        });
    }

    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropIndex(['datasets_is_spatial_is_active_map_order_index']);
            $table->dropColumn(['map_order', 'default_visible', 'map_opacity', 'display_color']);
        });
    }
};
