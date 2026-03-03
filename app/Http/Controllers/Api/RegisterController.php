<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use DB;

class RegisterController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => [
                'required', 
                'string', 
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'accept_terms' => ['required', 'accepted'],
        ], [
            // Messages personnalisés en français
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'accept_terms.required' => 'Vous devez accepter les conditions générales.',
            'accept_terms.accepted' => 'Vous devez accepter les conditions générales.',
        ]);

        // Si validation échoue
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Création de l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'is_active' => true,
            ]);

            // Génération du token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            // Mise à jour de la dernière connexion
            $user->update(['last_login_at' => now()]);

            // Réponse de succès
            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les identifiants fournis sont incorrects.'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé.'
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'token' => $token,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Supprimer le token actuel
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse valide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            // Si l'utilisateur existe, générer le token et envoyer l'email
            if ($user) {
                $token = \Illuminate\Support\Str::random(64);
                
                // Supprimer les anciens tokens pour cet email
                \DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                
                // Insérer le nouveau token
                \DB::table('password_reset_tokens')->insert([
                    'email' => $request->email,
                    'token' => \Hash::make($token),
                    'created_at' => now(),
                ]);

                // ICI: Envoyer l'email avec le lien de réinitialisation
                $user->notify(new \App\Notifications\ResetPasswordNotification($token));
                
                Log::info('Token de réinitialisation généré', [
                    'email' => $request->email,
                    'token' => $token
                ]);
            }

            // Réponse identique que l'utilisateur existe ou non (sécurité)
            return response()->json([
                'success' => true,
                'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur forgotPassword: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required', 
                'string', 
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ], [
            'token.required' => 'Le token est obligatoire.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier si l'utilisateur existe
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'We can\'t find a user with that email address.',
                    'errors' => [
                        'email' => ['We can\'t find a user with that email address.']
                    ]
                ], 422);
            }

            // Vérifier le token dans la base de données
            $passwordReset = \DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'This password reset token is invalid or has expired.',
                    'errors' => [
                        'token' => ['This password reset token is invalid or has expired.']
                    ]
                ], 422);
            }

            // Vérifier l'expiration (60 minutes)
            $expired = now()->diffInMinutes($passwordReset->created_at) > 60;
            
            if ($expired) {
                // Supprimer le token expiré
                \DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                    
                return response()->json([
                    'success' => false,
                    'message' => 'This password reset token is invalid or has expired.',
                    'errors' => [
                        'token' => ['This password reset token is invalid or has expired.']
                    ]
                ], 422);
            }

            // Vérifier que le token correspond
            if (!\Hash::check($request->token, $passwordReset->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This password reset token is invalid or has expired.',
                    'errors' => [
                        'token' => ['This password reset token is invalid or has expired.']
                    ]
                ], 422);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password' => \Hash::make($request->password)
            ]);

            // Supprimer le token utilisé
            \DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            Log::info('Mot de passe réinitialisé avec succès', [
                'email' => $request->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été réinitialisé avec succès',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur resetPassword: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
