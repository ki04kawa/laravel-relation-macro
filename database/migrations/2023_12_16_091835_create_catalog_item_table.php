<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalog_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained(table: 'catalogs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained(table: 'items')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_item');
    }
};
