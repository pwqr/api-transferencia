<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTransferNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;
    public array $backoff = [5, 15, 30];
    
    public function __construct(
        public int $payeeId,
        public int $transactionId
    ) {}

    public function handle(): void
    {
        $payee = User::findOrFail($this->payeeId);
        $transaction = Transaction::with('payer')->findOrFail($this->transactionId);

        $response = Http::timeout(5)->post('https://util.devi.tools/api/v1/notify', [
            'email' => $payee->email,
            'message' => sprintf(
                'Você recebeu uma transferência de R$ %.2f do usuário %s.',
                (float) $transaction->amount,
                $transaction->payer->name
            ),
        ]);

        if ($response->failed()) {
            Log::warning('Serviço de notificação falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payee_id' => $payee->id,
                'transaction_id' => $transaction->id,
                'attempt' => $this->attempts(),
            ]);

            throw new \RuntimeException('Falha no serviço de notificação.');
        }

        Log::info('Notificação enviada', [
            'payee_id' => $payee->id,
            'transaction_id' => $transaction->id,
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Notificação falhou', [
            'payee_id' => $this->payeeId,
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
