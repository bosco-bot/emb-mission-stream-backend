<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Models\User;

class Controller_login_register extends Controller
{
    /**
     * Afficher le formulaire de connexion
     */
    public function showLoginForm()
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        
        
        return view('login');
    }
    
    /**
     * Traiter la connexion de l'utilisateur
     */
    public function login(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez entrer une adresse email valide.',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password'));
        }
        
        // Tentative de connexion
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            // Redirection après connexion réussie
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Connexion réussie ! Bienvenue sur EMB-MISSION-RTV.');
        }
        
        // Échec de la connexion
        return redirect()->back()
            ->withErrors(['email' => 'Les identifiants fournis ne correspondent à aucun compte.'])
            ->withInput($request->except('password'));
    }
    
    /**
     * Afficher le formulaire d'inscription
     */
    public function showRegisterForm()
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        
        return view('register');
    }
    
    /**
     * Traiter l'inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'fullname' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => ['required', 'accepted'],
        ], [
            'fullname.required' => 'Le nom complet est obligatoire.',
            'fullname.string' => 'Le nom doit être une chaîne de caractères.',
            'fullname.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'fullname.min' => 'Le nom doit contenir au moins 2 caractères.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez entrer une adresse email valide.',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'terms.required' => 'Vous devez accepter les conditions générales d\'utilisation.',
            'terms.accepted' => 'Vous devez accepter les conditions générales d\'utilisation.',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except(['password', 'password_confirmation']));
        }
        
        try {
            // Création du nouvel utilisateur
            $user = User::create([
                'name' => $request->fullname,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(), // Auto-vérification pour le moment
            ]);
            
            // Connexion automatique après inscription
            Auth::login($user);
            
            // Redirection après inscription réussie
            return redirect()->route('dashboard')
                ->with('success', 'Inscription réussie ! Bienvenue sur EMB-MISSION-RTV.');
                
        } catch (\Exception $e) {
            // En cas d'erreur lors de la création
            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }
    }
    
    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login')
            ->with('success', 'Vous avez été déconnecté avec succès.');
    }
    
    /**
     * Vérifier si l'email existe déjà (AJAX)
     */
    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        
        if (empty($email)) {
            return response()->json(['available' => false, 'message' => 'Email requis']);
        }
        
        $exists = User::where('email', $email)->exists();
        
        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Cette adresse email est déjà utilisée.' : 'Email disponible.'
        ]);
    }
    
    /**
     * Vérifier la force du mot de passe (AJAX)
     */
    public function checkPasswordStrength(Request $request)
    {
        $password = $request->input('password');
        
        if (empty($password)) {
            return response()->json(['strength' => 'empty', 'message' => '']);
        }
        
        $strength = 0;
        $feedback = [];
        
        // Vérifications de base
        if (strlen($password) >= 8) $strength++;
        else $feedback[] = 'Au moins 8 caractères';
        
        if (preg_match('/[a-z]/', $password)) $strength++;
        else $feedback[] = 'Une lettre minuscule';
        
        if (preg_match('/[A-Z]/', $password)) $strength++;
        else $feedback[] = 'Une lettre majuscule';
        
        if (preg_match('/[0-9]/', $password)) $strength++;
        else $feedback[] = 'Un chiffre';
        
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
        else $feedback[] = 'Un caractère spécial';
        
        // Détermination du niveau de force
        if ($strength < 3) {
            $level = 'weak';
            $message = 'Mot de passe faible. Ajoutez : ' . implode(', ', $feedback);
        } elseif ($strength < 5) {
            $level = 'medium';
            $message = 'Mot de passe moyen. Pour le renforcer : ' . implode(', ', $feedback);
        } else {
            $level = 'strong';
            $message = 'Mot de passe fort !';
        }
        
        return response()->json([
            'strength' => $level,
            'message' => $message,
            'score' => $strength
        ]);
    }
    
    /**
     * Redirection après connexion réussie
     */
    protected function redirectTo()
    {
        return route('dashboard');
    }
}
