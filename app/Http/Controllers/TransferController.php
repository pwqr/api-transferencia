<?php

namespace App\Http\Controllers;

use App\Services\TransferService;
use App\Http\Requests\StoreTransferRequest;
use RuntimeException;
use Throwable;

class TransferController extends Controller
{
    public function store(StoreTransferRequest $request, TransferService $service)
    {
        $data = $request->validated();
        
        try {
            $transaction = $service->transfer(
                (float) $data['value'],
                (int) $data['payer'],
                (int) $data['payee']
            );

            return response()->json([
                'id' => $transaction->id,
                'payer_id' => $transaction->payer_id,
                'payee_id' => $transaction->payee_id,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at,
            ], 201);

        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao processar transferência.',
            ], 500);
        }
    }
}