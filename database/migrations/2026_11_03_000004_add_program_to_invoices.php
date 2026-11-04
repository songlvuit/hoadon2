<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Thông tin chương trình / sự kiện mà hồ sơ hoá đơn thuộc về
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('program_name', 255)->nullable()->after('code')->index();
            $table->date('event_date')->nullable()->after('program_name')->index();
            $table->date('event_end_date')->nullable()->after('event_date');
            $table->string('event_location', 500)->nullable()->after('event_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['program_name', 'event_date', 'event_end_date', 'event_location']);
        });
    }
};
