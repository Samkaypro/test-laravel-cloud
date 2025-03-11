<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (!auth()->attempt($credentials)) {
            return response([
                'message' => 'Your credentials are invalid.',
            ], 401);
        }

        try {
            $user = auth()->user();
            $token = $user->createToken('myapptoken')->plainTextToken;
        } catch (Exception $e) {
            return response([
                'message' => 'Failed to create token.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return (new UserResource($user))->additional([
            'token' => $token
        ]);
    }
}
