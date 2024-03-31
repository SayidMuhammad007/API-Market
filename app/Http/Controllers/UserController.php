<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\Access;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = User::with(['UserAccess.Access', 'branch'])->where('branch_id', $user->branch_id)->where('status', 1);
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where('name', 'like', "%$$searchTerm%");
        }

        // Paginate the results
        $users = $query->paginate(10);
        return response()->json($users);
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
            ], 401);
        }

        // Validate access_id
        $access_ids = [];
        foreach ($request->access_id as $access_id) {
            $access = Access::find($access_id);
            if (!$access) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access_id',
                ], 400);
            }
            $access_ids[] = $access_id;
        }

        // Validate branch_id
        $branch = Branch::find($request->branch_id);
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid branch_id',
            ], 400);
        }

        // Create a new user
        $newUser = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'role' => $request->role,
            'branch_id' => $request->branch_id,
            'password' => Hash::make($request->password),
        ]);

        // Create a UserAccess record for the new user
        foreach ($access_ids as $access_id) {
            $newUser->UserAccess()->create([
                'access_id' => $access_id,
            ]);
        }
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
            ], 400);
        } else {
            $token = $user->createToken('auth_token', ['expires' => now()->addDay()])->plainTextToken;
            return response()->json([
                'success' => true,
            'user' => $user->load('UserAccess.Access:id,name'),
                'token' => $token,
            ], 201);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // If user exists, return error response
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with this ID',
            ], 201);
        }

        // Check if the phone number is already in use by another user
        if ($request->has('phone') && $request->phone !== $user->phone) {
            $existingUser = User::where('phone', $request->phone)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'This phone number is already in use',
                ], 400);
            }
        }
        $accessIds = [];

        // Validate access_id
        if ($request->access_id) {
            foreach ($request->access_id as $accessId) {
                $access = Access::find($accessId);
                if (!$access) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid access_id',
                    ], 400);
                }
                $accessIds[] = $accessId;
            }
        }
        // Validate branch_id
        $branch = Branch::find($request->branch_id);
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid branch_id',
            ], 400);
        }

        // Update user details
        $user->update([
            'name' => $request->has('name') ? $request->name : $user->name,
            'phone' => $request->has('phone') ? $request->phone : $user->phone,
            'role' => $request->has('role') ? $request->role : $user->role,
            'branch_id' => $request->has('branch_id') ? $request->branch_id : $user->branch_id,
            'password' => $request->has('password') ? Hash::make($request->password) : $user->password,
        ]);

        // Sync user access
        if ($accessIds) {
            // Detach existing UserAccess records
            $user->UserAccess()->delete();


            // Attach the new UserAccess records
            foreach ($accessIds as $accessId) {
                $user->UserAccess()->create([
                    'access_id' => $accessId,
                ]);
            }
        }


        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->load(['UserAccess.Access', 'branch']),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->status = 0;
        $user->save();

        $user = auth()->user();
        $query = User::with(['UserAccess.Access', 'branch'])->where('branch_id', $user->branch_id)->where('status', 1);
        $users = $query->paginate(10);
        return response()->json($users);
    }
}
