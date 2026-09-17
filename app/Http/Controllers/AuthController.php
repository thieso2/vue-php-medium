<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'username' => 'required|string|alpha_dash:ascii|min:3|max:40|unique:users', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user, 'csrf' => csrf_token()], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['username' => 'The username or password is incorrect.']);
        }
        $request->session()->regenerate();

        return ['user' => $request->user(), 'csrf' => csrf_token()];
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['csrf' => csrf_token()];
    }
}
