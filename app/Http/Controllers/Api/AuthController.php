<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\AuthNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\User;
use App;

class AuthController extends Controller
{
    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    public function signup(Request $request)
    {

        $messages = [
            'name.required'    => 'Enter full name!',
            'email.required' => 'Enter an e-mail address!',
            'email' => 'E-mail address exist!',
            'password.required'    => 'Password is required',
            'password_confirmation' => 'The :password and :password_confirmation must match.'
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
        ], $messages);

        $user = User::where('email', $request->get('email'))->first();

        if ($user) {
            return response()->json([
                'status' => 'exist',
                'message' => 'User already exist. please login',
            ], 409);
        } elseif ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 406);
        } else {            
            // insert new record
            $input = $request->all();
            $input['password'] = Hash::make($input['password']);

            $user = User::create($input);

            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'access_token' => $tokenResult->accessToken,
                'data' => $user
            ]);
        }
    }

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = request(['email', 'password']);
        $credentials['active'] = 1;
        $credentials['deleted_at'] = null;

        if (!Auth::attempt($credentials))
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized'
            ], 401);

        if (!Auth::user()->active)
            return response()->json([
                'status' => 'failed',
                'message' => 'Your account is under review'
            ], 401);

        $user = User::where('id', Auth::user()->id)->first();

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;

        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        return response()->json([
            'status' => 'success',
            'access_token' => $tokenResult->accessToken,
            'message' => 'login successful',
            'data' => $user
        ]);
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        $request->user()->token()->revoke();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        $user = User::where('id', Auth::user()->id)->first();
        return response()->json([
            'status' => 'success',
            'message' => 'user fetched',
            'data' => $user
        ]);
    }
}
