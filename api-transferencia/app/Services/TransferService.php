<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class TransferService
{
    public function transfer(float $amount, int $payerId, int $payeeId): Transaction
    {
        return DB::transaction(function () use ($amount, $payerId, $payeeId) {

            $payer = User::lockForUpdate()->findOrFail($payerId);
            $payee = User::lockForUpdate()->findOrFail($payeeId);

            if ($payer->id === $payee->id) {
                throw new \RuntimeException('Não é possível transferir para si mesmo.');
            }

            if ($payer->type === 'merchant') {
                throw new \RuntimeException('Lojistas não podem realizar transferências.');
            }

            if ($payer->balance < $amount) {
                throw new \RuntimeException('Saldo insuficiente.');
            }

            $this->authorizeTransfer();

            $payer->decrement('balance', $amount);
            $payee->increment('balance', $amount);

            $transaction = Transaction::create([
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount'   => $amount,
                'status'   => 'success',
            ]);

            \App\Jobs\SendTransferNotificationJob::dispatch($payee->id, $transaction->id);

            return $transaction;
        });
    }

    

    private function authorizeTransfer(): void
    {
        try {
            $response = Http::timeout(3)
                ->get('https://util.devi.tools/api/v2/authorize');

            if ($response->failed()) {
                throw new \RuntimeException('Serviço de autorização indisponível.');
            }

            if ($response->json('data.authorization') !== true) {
                throw new \RuntimeException('Transferência não autorizada.');
            }

        } catch (\Throwable $e) {
            throw new \RuntimeException('Falha ao autorizar transferência.');
        }
    }


    private function sendNotification(User $payee, Transaction $transaction): void
    {
        try {
            Http::post('https://util.devi.tools/api/v1/notify', [
                'email'   => $payee->email,
                'message' => "Você recebeu R$ {$transaction->amount}",
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao enviar notificação', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
