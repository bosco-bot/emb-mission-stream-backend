<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - EMB-MISSION-RTV</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #B4E3F9;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        
        .form-container {
            padding: 40px;
            background: white;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-left: 15px;
        }
        
        .logo-image {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .register-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            text-align: center;
        }
        
        .register-subtitle {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 30px;
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
            border-radius: 8px;
            padding: 12px 15px 12px 45px;
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
        
        .form-control.is-valid {
            border-color: #28a745;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
        }
        
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #28a745;
        }
        
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            z-index: 2;
            pointer-events: none;
            font-size: 0.9rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .terms-checkbox {
            margin-bottom: 25px;
        }
        
        .terms-text {
            font-size: 0.9rem;
            color: #2c3e50;
            line-height: 1.4;
        }
        
        .terms-link {
            color: #ff4757;
            text-decoration: none;
            font-weight: 600;
        }
        
        .terms-link:hover {
            color: #ff3742;
            text-decoration: underline;
        }
        
        .form-check-input:checked {
            background-color: #ff4757;
            border-color: #ff4757;
        }
        
        .register-btn {
            background: #ff4757;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .register-btn:hover {
            background: #ff3742;
        }
        
        .register-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .login-link a {
            color: #ff4757;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #ff3742;
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
            .register-container {
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
            
            .register-title {
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
            
            .register-container {
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
            
            .register-title {
                font-size: 1.8rem;
            }
            
            .register-subtitle {
                font-size: 1rem;
            }
            
            .form-control {
                padding: 12px 15px 12px 45px;
                font-size: 0.95rem;
            }
            
            .register-btn {
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
            
            .register-title {
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
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="register-container">
                    <div class="form-container">
                        <!-- Logo Section -->
                        <div class="logo-section">
                            <img src="{{ asset('assets/images/logoregister.png') }}" alt="EMB Mission Logo" class="logo-image">
                            <div class="logo-text">EMB-MISSION-RTV</div>
                        </div>
                        
                        <h1 class="register-title">Créer votre compte</h1>
                        <p class="register-subtitle">Rejoignez la plateforme de diffusion de demain.</p>
                                    
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
                                    
                                    <!-- Register Form -->
                                    <form method="POST" action="{{ route('register.post') }}" id="registerForm">
                                        @csrf
                                        
                                        <!-- Full Name Field -->
                                        <div class="form-group">
                                            <label for="fullname" class="form-label">Nom complet</label>
                                            <div class="input-group">
                                                <i class="fas fa-user input-icon"></i>
                                                <input type="text" 
                                                       class="form-control @error('fullname') is-invalid @enderror" 
                                                       id="fullname" 
                                                       name="fullname" 
                                                       value="{{ old('fullname') }}" 
                                                       placeholder="John Doe"
                                                       required 
                                                       autocomplete="name" 
                                                       autofocus>
                                                @error('fullname')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
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
                                                       placeholder="john.doe@email.com"
                                                       required 
                                                       autocomplete="email">
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <!-- Password Field -->
                                        <div class="form-group">
                                            <label for="password" class="form-label">Mot de passe</label>
                                            <div class="input-group">
                                                <i class="fas fa-lock input-icon"></i>
                                                <input type="password" 
                                                       class="form-control @error('password') is-invalid @enderror" 
                                                       id="password" 
                                                       name="password" 
                                                       placeholder="••••••••"
                                                       required 
                                                       autocomplete="new-password">
                                                @error('password')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <div class="password-strength" id="passwordStrength"></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Confirm Password Field -->
                                        <div class="form-group">
                                            <label for="password_confirmation" class="form-label">Confirmation du mot de passe</label>
                                            <div class="input-group">
                                                <i class="fas fa-lock input-icon"></i>
                                                <input type="password" 
                                                       class="form-control @error('password_confirmation') is-invalid @enderror" 
                                                       id="password_confirmation" 
                                                       name="password_confirmation" 
                                                       placeholder="••••••••"
                                                       required 
                                                       autocomplete="new-password">
                                                @error('password_confirmation')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <!-- Terms and Conditions -->
                                        <div class="form-group terms-checkbox">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="terms" 
                                                       id="terms"
                                                       required>
                                                <label class="form-check-label terms-text" for="terms">
                                                    J'accepte les <a href="#" class="terms-link">Conditions Générales d'Utilisation</a>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Register Button -->
                                        <button type="submit" class="register-btn" id="registerBtn">
                                            S'inscrire
                                        </button>
                                    </form>
                                    
                        <!-- Login Link -->
                        <div class="login-link">
                            Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a>
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
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const strengthElement = document.getElementById('passwordStrength');
            
            if (strength < 3) {
                strengthElement.textContent = 'Mot de passe faible';
                strengthElement.className = 'password-strength strength-weak';
            } else if (strength < 5) {
                strengthElement.textContent = 'Mot de passe moyen';
                strengthElement.className = 'password-strength strength-medium';
            } else {
                strengthElement.textContent = 'Mot de passe fort';
                strengthElement.className = 'password-strength strength-strong';
            }
        }
        
        // Password confirmation validation
        function validatePasswordConfirmation() {
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('password_confirmation').value;
            const confirmationField = document.getElementById('password_confirmation');
            
            if (confirmation && password !== confirmation) {
                confirmationField.classList.add('is-invalid');
                confirmationField.classList.remove('is-valid');
                confirmationField.nextElementSibling.textContent = 'Les mots de passe ne correspondent pas';
            } else if (confirmation && password === confirmation) {
                confirmationField.classList.remove('is-invalid');
                confirmationField.classList.add('is-valid');
                confirmationField.nextElementSibling.textContent = 'Les mots de passe correspondent';
            }
        }
        
        // Email validation
        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Form validation
        function validateForm() {
            let isValid = true;
            
            // Full name validation
            const fullname = document.getElementById('fullname');
            if (fullname.value.trim().length < 2) {
                fullname.classList.add('is-invalid');
                fullname.classList.remove('is-valid');
                fullname.nextElementSibling.textContent = 'Le nom doit contenir au moins 2 caractères';
                isValid = false;
            } else {
                fullname.classList.remove('is-invalid');
                fullname.classList.add('is-valid');
            }
            
            // Email validation
            const email = document.getElementById('email');
            if (!validateEmail(email.value)) {
                email.classList.add('is-invalid');
                email.classList.remove('is-valid');
                email.nextElementSibling.textContent = 'Veuillez entrer une adresse email valide';
                isValid = false;
            } else {
                email.classList.remove('is-invalid');
                email.classList.add('is-valid');
            }
            
            // Password validation
            const password = document.getElementById('password');
            if (password.value.length < 8) {
                password.classList.add('is-invalid');
                password.classList.remove('is-valid');
                password.nextElementSibling.textContent = 'Le mot de passe doit contenir au moins 8 caractères';
                isValid = false;
            } else {
                password.classList.remove('is-invalid');
                password.classList.add('is-valid');
            }
            
            // Password confirmation validation
            validatePasswordConfirmation();
            
            // Terms validation
            const terms = document.getElementById('terms');
            if (!terms.checked) {
                isValid = false;
                alert('Veuillez accepter les conditions générales d\'utilisation');
            }
            
            return isValid;
        }
        
        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            validatePasswordConfirmation();
        });
        
        document.getElementById('password_confirmation').addEventListener('input', validatePasswordConfirmation);
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            // La validation côté client est maintenant optionnelle
            // Le formulaire sera soumis normalement à Laravel pour validation côté serveur
        });
        
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
