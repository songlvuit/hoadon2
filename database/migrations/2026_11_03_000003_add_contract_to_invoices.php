<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hợp đồng đính kèm hồ sơ hoá đơn (chỉ nội bộ xem)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('contract_number', 100)->nullable()->after('quote_ref')->index();
            $table->date('contract_date')->nullable()->after('contract_number');
            $table->json('contract_files')->nullable()->after('contract_date');
            $table->json('contract_file_names')->nullable()->after('contract_files');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['contract_number', 'contract_date', 'contract_files', 'contract_file_names']);
        });
    }
};
