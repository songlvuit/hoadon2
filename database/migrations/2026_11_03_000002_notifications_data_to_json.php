<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Filament truy vấn notifications.data như JSON; trên PostgreSQL cột phải là kiểu json.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text');
        }
    }
};
