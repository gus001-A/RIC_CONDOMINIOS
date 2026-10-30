<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        Log::info('Intentando resetear password', [
            'email' => $request->email,
            'token' => $request->token,
        ]);

        // ✅ VERIFICAR MANUALMENTE EL TOKEN
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            Log::error('Token no encontrado en BD', ['email' => $request->email]);
            throw ValidationException::withMessages([
                'email' => ['El token de restablecimiento no es válido.'],
            ]);
        }

        Log::info('Token encontrado en BD', [
            'email' => $request->email,
            'token_hash' => $resetRecord->token,
        ]);

        // ✅ VERIFICAR QUE EL TOKEN COINCIDA (usando Hash::check)
        if (!Hash::check($request->token, $resetRecord->token)) {
            Log::error('Token no coincide', [
                'email' => $request->email,
                'provided_token' => $request->token,
                'stored_hash' => $resetRecord->token,
            ]);
            throw ValidationException::withMessages([
                'email' => ['El token de restablecimiento no es válido.'],
            ]);
        }

        // ✅ VERIFICAR EXPIRACIÓN (60 minutos)
        $createdAt = Carbon::parse($resetRecord->created_at);
        if ($createdAt->diffInMinutes(now()) > 60) {
            Log::error('Token expirado', [
                'email' => $request->email,
                'created_at' => $resetRecord->created_at,
                'minutes_ago' => $createdAt->diffInMinutes(now()),
            ]);
            throw ValidationException::withMessages([
                'email' => ['El enlace de restablecimiento ha expirado.'],
            ]);
        }

        // ✅ BUSCAR EL USUARIO
        $user = Usuario::where('email', $request->email)->first();

        if (!$user) {
            Log::error('Usuario no encontrado', ['email' => $request->email]);
            throw ValidationException::withMessages([
                'email' => ['No encontramos un usuario con este correo.'],
            ]);
        }

        // ✅ ACTUALIZAR CONTRASEÑA
        $user->forceFill([
            'password_hash' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // ✅ ELIMINAR EL TOKEN USADO
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // ✅ DISPARAR EVENTO
        event(new PasswordReset($user));

        Log::info('Contraseña actualizada exitosamente', [
            'user_id' => $user->id_usuario,
            'email' => $user->email,
        ]);

        return redirect()->route('login')->with('status', '¡Tu contraseña ha sido restablecida correctamente!');
    }
}