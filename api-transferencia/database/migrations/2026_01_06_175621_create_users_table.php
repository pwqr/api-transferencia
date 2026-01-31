<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void
    {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('cpf', 14)->unique();
        $table->string('password');
        $table->enum('type', ['common', 'merchant'])->default('common');
        $table->decimal('balance', 15, 2)->default(0);
        $table->timestamps();
    });
    }
        
    public function down(): void
    {
    Schema::dropIfExists('users');
    }
};      
    