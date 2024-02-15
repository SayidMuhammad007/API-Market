<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\Access;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(User::with('userAccess.access')->paginate(20));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        // Check if the user already exists
        $user = User::where('phone', $request->phone)->first();

        // If user exists, return error response
        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already in use',
            ], 201);
        }

        // Validate access_id
        $access = Access::find($request->access_id);
        if (!$access) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid access_id',
            ], 400);
        }

        // Create a new user
        $newUser = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Create a UserAccess record for the new user
        $newUser->userAccess()->create([
            'access_id' => $request->access_id,
        ]);

        // Generate an authentication token for the new user
        $token = $newUser->createToken('auth_token')->plainTextToken;

        // Return success response with token
        return response()->json([
            'success' => true,
            'token' => $token,
        ], 201);
    }



    /**
     * Display the specified resource.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('phone', $request->login)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials',
            ], 401);
        } else {
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
            ], 201);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
