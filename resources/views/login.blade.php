<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - EMB-MISSION-RTV</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .left-panel {
            background: #B4E3F9;
            padding: 60px 40px;
            color: white;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100%;
            height: 100%;
        }
        
        .logo-container {
            position: relative;
            z-index: 2;
        }
        
        .logo {
            font-size: 3rem;
            font-weight: 700;
            color: #ff4757;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .dove-icon {
            font-size: 2rem;
            color: white;
            margin-left: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .platform-name {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2c3e50;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }
        
        .platform-description {
            font-size: 1.1rem;
            font-weight: 400;
            line-height: 1.6;
            color: #2c3e50;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.5);
        }
        
        .right-panel {
            padding: 60px 40px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
            height: 100%;
        }
        
        .form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }
        
        .login-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .login-subtitle {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px 20px 15px 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            z-index: 2;
            pointer-events: none;
        }
        
        .input-group {
            position: relative;
        }
        
        .forgot-password {
            color: #2c3e50;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #667eea;
        }
        
        .login-btn {
            background: #ff4757;
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
            background: #ff3742;
        }
        
        .create-account {
            text-align: center;
            margin-top: 30px;
            color: #2c3e50;
        }
        
        .create-account a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .create-account a:hover {
            color: #667eea;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .alert-danger {
            background: #ffe6e6;
            color: #d63031;
        }
        
        .alert-success {
            background: #e6f7e6;
            color: #00b894;
        }
        
        /* Assurer que les colonnes ont la même hauteur */
        .row.g-0 {
            min-height: 600px;
        }
        
        /* Responsive Design */
        @media (max-width: 991.98px) {
            .login-container {
                margin: 15px;
                border-radius: 15px;
            }
            
            .left-panel, .right-panel {
                padding: 30px 25px;
                min-height: auto;
                height: auto;
            }
            
            .row.g-0 {
                min-height: auto;
            }
            
            .logo {
                font-size: 2.2rem;
            }
            
            .platform-name {
                font-size: 1.5rem;
            }
            
            .platform-description {
                font-size: 1rem;
            }
            
            .login-title {
                font-size: 2rem;
            }
            
            .form-container {
                max-width: 100%;
            }
        }
        
        @media (max-width: 767.98px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                margin: 0;
                border-radius: 10px;
            }
            
            .left-panel, .right-panel {
                padding: 25px 20px;
            }
            
            .logo {
                font-size: 2rem;
            }
            
            .platform-name {
                font-size: 1.3rem;
            }
            
            .platform-description {
                font-size: 0.95rem;
            }
            
            .login-title {
                font-size: 1.8rem;
            }
            
            .form-control {
                padding: 12px 15px 12px 45px;
                font-size: 0.95rem;
            }
            
            .login-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .left-panel, .right-panel {
                padding: 20px 15px;
            }
            
            .logo {
                font-size: 1.8rem;
            }
            
            .platform-name {
                font-size: 1.2rem;
            }
            
            .platform-description {
                font-size: 0.9rem;
            }
            
            .login-title {
                font-size: 1.6rem;
            }
            
            .form-control {
                padding: 10px 12px 10px 40px;
                font-size: 0.9rem;
            }
            
            .input-icon {
                left: 12px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">
                <div class="login-container">
                    <div class="row g-0">
                        <!-- Left Panel -->
                        <div class="col-12 col-md-6 col-lg-5">
                            <div class="left-panel">
                                <div class="logo-container">
                                    <img src="{{ asset('assets/images/Margin_ div.png') }}" alt="EMB Mission Logo" style="max-width: 100%; height: auto; margin-bottom: 20px;">
                                    <div class="platform-name">EMB-MISSION-RTV</div>
                                    <div class="platform-description">
                                        Votre plateforme tout-en-un pour la diffusion WebTV & WebRadio.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Panel -->
                        <div class="col-12 col-md-6 col-lg-7">
                            <div class="right-panel">
                                <div class="form-container">
                                    <h1 class="login-title">Connexion</h1>
                                    <p class="login-subtitle">Heureux de vous revoir !</p>
                                    
                                    <!-- Success Messages -->
                                    @if (session('success'))
                                        <div class="alert alert-success">
                                            {{ session('success') }}
                                        </div>
                                    @endif
                                    
                                    <!-- Error Messages -->
                                    @if ($errors->any())
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    
                                    <!-- Login Form -->
                                    <form method="POST" action="{{ route('login.post') }}">
                                        @csrf
                                        
                                        <!-- Email Field -->
                                        <div class="form-group">
                                            <label for="email" class="form-label">Email</label>
                                            <div class="input-group">
                                                <i class="fas fa-envelope input-icon"></i>
                                                <input type="email" 
                                                       class="form-control @error('email') is-invalid @enderror" 
                                                       id="email" 
                                                       name="email" 
                                                       value="{{ old('email') }}" 
                                                       placeholder="votre.email@example.com"
                                                       required 
                                                       autocomplete="email" 
                                                       autofocus>
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <!-- Password Field -->
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label for="password" class="form-label">Mot de passe</label>
                                                <a href="#" class="forgot-password">
                                                    Mot de passe oublié ?
                                                </a>
                                            </div>
                                            <div class="input-group">
                                                <i class="fas fa-lock input-icon"></i>
                                                <input type="password" 
                                                       class="form-control @error('password') is-invalid @enderror" 
                                                       id="password" 
                                                       name="password" 
                                                       placeholder="••••••••"
                                                       required 
                                                       autocomplete="current-password">
                                                @error('password')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <!-- Remember Me -->
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="remember" 
                                                       id="remember"
                                                       {{ old('remember') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="remember">
                                                    Se souvenir de moi
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Login Button -->
                                        <button type="submit" class="login-btn">
                                            Se connecter
                                        </button>
                                    </form>
                                    
                                    <!-- Create Account Link -->
                                    <div class="create-account">
                                        Pas encore de compte ? 
                                        <a href="{{ route('register') }}">Créer un compte</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Add focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>