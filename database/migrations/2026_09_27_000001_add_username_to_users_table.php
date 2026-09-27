<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('name');
        });

        $users = DB::table('users')->select('id', 'email')->orderBy('id')->get();
        foreach ($users as $user) {
            $base = strtolower((string) str($user->email)->before('@'));
            $base = preg_replace('/[^a-z0-9._-]+/', '', $base) ?: 'user';
            $username = substr($base, 0, 90);
            $candidate = $username;
            $counter = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = substr($username, 0, 90 - strlen((string) $counter) - 1) . '_' . $counter++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
