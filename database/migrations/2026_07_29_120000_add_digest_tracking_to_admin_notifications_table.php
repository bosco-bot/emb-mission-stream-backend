<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('digest_id')->nullable()->after('type');
            $table->timestamp('cancelled_at')->nullable()->after('read_at');
            $table->index('digest_id');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropIndex(['digest_id']);
            $table->dropColumn(['digest_id', 'cancelled_at']);
        });
    }
};
