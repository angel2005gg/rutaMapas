<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Facades\Auth;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
            
            $finduser = Usuario::where('google_uid', $user->id)->first();
            
            if($finduser) {
                return response()->json([
                    'status' => 'success',
                    'user' => $finduser
                ]);
            } else {
                $newUser = Usuario::create([
                    'nombre' => $user->name,
                    'correo' => $user->email,
                    'google_uid'=> $user->id,
                    'foto_perfil' => $user->avatar
                ]);

                return response()->json([
                    'status' => 'success',
                    'user' => $newUser
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
