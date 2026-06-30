<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::orderBy('name')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->string('name'),
            'username' => $request->string('username'),
            'email'    => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'is_admin' => $request->boolean('is_admin', false),
            'is_hr'    => $request->boolean('is_hr',    false),
        ]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
