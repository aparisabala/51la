<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->onDelete('cascade');

            // Time slot — format: "00:00", "01:00", "02:00" ... "23:00"
            $table->string('time_slot', 10);   // e.g. "01:00"
            $table->date('report_date');        // which day this data belongs to

            // Cumulative data (white row)
            $table->unsignedBigInteger('ip_51la')->default(0);       // 51la IP
            $table->unsignedBigInteger('total_install')->default(0); // 总安装 / Install
            $table->unsignedBigInteger('total_click')->default(0);   // 总点击 / Click
            $table->decimal('click_ratio', 8, 2)->nullable();        // 点击比 / click ratio
            $table->decimal('ip_click_ratio', 8, 2)->nullable();     // IP点击比
            $table->decimal('conversion_rate', 8, 4)->nullable();    // 转化率 (stored as decimal e.g. 0.5472)

            // Interval data is calculated on-the-fly (current - previous)
            // OR store it directly for performance
            $table->unsignedBigInteger('interval_ip')->default(0);
            $table->unsignedBigInteger('interval_install')->default(0);
            $table->unsignedBigInteger('interval_click')->default(0);
            $table->decimal('interval_click_ratio', 8, 2)->nullable();
            $table->decimal('interval_ip_click_ratio', 8, 2)->nullable();
            $table->decimal('interval_conversion_rate', 8, 4)->nullable();

            $table->timestamps();

            $table->unique(['app_id', 'report_date', 'time_slot']);
            $table->index(['report_date', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_metrics');
    }
};