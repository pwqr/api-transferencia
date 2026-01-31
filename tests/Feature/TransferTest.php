<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function realiza_a_transferencia_quando_é_autorizado(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true]
            ], 200),

            'https://util.devi.tools/api/v1/notify' => Http::response([
                'message' => 'sent'
            ], 200),
        ]);

        $payer = User::factory()->create([
            'type' => 'common',
            'balance' => 1000,
        ]);

        $payee = User::factory()->create([
            'type' => 'merchant',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 100.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('transactions', [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'status' => 'success',
        ]);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(900.00, (float) $payer->balance);
        $this->assertEquals(100.00, (float) $payee->balance);

        Http::assertSent(fn ($req) =>
            $req->url() === 'https://util.devi.tools/api/v2/authorize'
            && $req->method() === 'GET'
        );
    }

    #[Test]
    public function bloqueia_a_transferencia_quando_o_pagador_é_o_lojista(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true]
            ], 200),
        ]);

        $payer = User::factory()->create([
            'type' => 'merchant',
            'balance' => 1000,
        ]);

        $payee = User::factory()->create([
            'type' => 'common',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 10.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(422);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(1000.00, (float) $payer->balance);
        $this->assertEquals(0.00, (float) $payee->balance);

        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function faz_rollback_quando_o_serviço_não_autoriza(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => false]
            ], 200),
        ]);

        $payer = User::factory()->create([
            'type' => 'common',
            'balance' => 1000,
        ]);

        $payee = User::factory()->create([
            'type' => 'common',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 100.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(422);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(1000.00, (float) $payer->balance);
        $this->assertEquals(0.00, (float) $payee->balance);

        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function bloqueia_transferencia_quando_saldo_insuficiente(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true]
            ], 200),
        ]);

        $payer = User::factory()->create([
            'type' => 'common',
            'balance' => 50,
        ]);

        $payee = User::factory()->create([
            'type' => 'common',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 100.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(422);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(50.00, (float) $payer->balance);
        $this->assertEquals(0.00, (float) $payee->balance);

        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function bloqueia_transferencia_para_si_mesmo(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true]
            ], 200),
        ]);

        $user = User::factory()->create([
            'type' => 'common',
            'balance' => 1000,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 10.00,
            'payer' => $user->id,
            'payee' => $user->id,
        ]);

        $response->assertStatus(422);

        $user->refresh();
        $this->assertEquals(1000.00, (float) $user->balance);

        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function nao_deve_quebrar_transferencia_se_notificacao_falhar(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true]
            ], 200),

            'https://util.devi.tools/api/v1/notify' => Http::response(null, 500),
        ]);

        $payer = User::factory()->create([
            'type' => 'common',
            'balance' => 1000,
        ]);

        $payee = User::factory()->create([
            'type' => 'merchant',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 100.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(201);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(900.00, (float) $payer->balance);
        $this->assertEquals(100.00, (float) $payee->balance);

        $this->assertDatabaseHas('transactions', [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'status' => 'success',
        ]);
    }

    #[Test]
    public function bloqueia_quando_autorizador_estiver_indisponivel(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response(null, 500),
        ]);

        $payer = User::factory()->create([
            'type' => 'common',
            'balance' => 1000,
        ]);

        $payee = User::factory()->create([
            'type' => 'common',
            'balance' => 0,
        ]);

        $response = $this->postJson('/api/transfer', [
            'value' => 10.00,
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(422);

        $payer->refresh();
        $payee->refresh();

        $this->assertEquals(1000.00, (float) $payer->balance);
        $this->assertEquals(0.00, (float) $payee->balance);

        $this->assertDatabaseCount('transactions', 0);
    }


    #[Test]
    public function retorna_erro_de_validacao_quando_value_nao_for_enviado(): void
    {
        $payer = User::factory()->create(['type' => 'common', 'balance' => 1000]);
        $payee = User::factory()->create(['type' => 'common', 'balance' => 0]);

        $response = $this->postJson('/api/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['value']);
    }

    #[Test]
    public function retorna_erro_de_validacao_quando_payer_ou_payee_nao_existirem(): void
    {
        $usuarioExistente = User::factory()->create(['type' => 'common', 'balance' => 1000]);

        $responsePayer = $this->postJson('/api/transfer', [
            'value' => 10.00,
            'payer' => 999999,
            'payee' => $usuarioExistente->id,
        ]);

        $responsePayer->assertStatus(422);
        $responsePayer->assertJsonValidationErrors(['payer']);

        $responsePayee = $this->postJson('/api/transfer', [
            'value' => 10.00,
            'payer' => $usuarioExistente->id,
            'payee' => 999999,
        ]);

        $responsePayee->assertStatus(422);
        $responsePayee->assertJsonValidationErrors(['payee']);
    }
}
