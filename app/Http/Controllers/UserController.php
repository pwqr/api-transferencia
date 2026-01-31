<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Http\Requests\StoreUserRequest;
use RuntimeException;
use Throwable;

class UserController extends Controller
{
    public function store(StoreUserRequest $request, UserService $service)
    {
        try {
            $user = $service->create($request->validated());

            return response()->json($user, 201);

        } catch (RuntimeException $e) {
            $status = $e->getCode() === 409 ? 409 : 400;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);

        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao criar usuário.',
            ], 500);
        }
    }
}