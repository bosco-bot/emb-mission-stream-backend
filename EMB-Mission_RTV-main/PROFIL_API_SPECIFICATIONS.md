# APIs nécessaires pour l'onglet Profil

## 📋 Informations actuellement disponibles

L'application affiche déjà ces informations utilisateur :
- ✅ Nom (`name`)
- ✅ Email (`email`)
- ✅ Avatar (`avatar`)
- ✅ Rôle (`role`)
- ✅ Date de création (`created_at`)

## 🚫 APIs manquantes pour implémenter l'onglet Profil

### 1. **GET /api/auth/me** (Existe déjà)
- **URL** : `https://radio.embmission.com/api/auth/me`
- **Méthode** : GET
- **Protection** : Token Bearer requis
- **Description** : Récupère les informations de l'utilisateur connecté

**Réponse attendue** :
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "avatar": "https://example.com/avatar.jpg",
  "role": "admin",
  "is_active": true,
  "created_at": "2025-01-01T00:00:00.000000Z",
  "updated_at": "2025-01-01T00:00:00.000000Z"
}
```

---

### 2. **PUT /api/profile** (À créer)
- **URL** : `https://tv.embmission.com/api/profile`
- **Méthode** : PUT
- **Protection** : Token Bearer requis
- **Description** : Met à jour les informations du profil utilisateur

**Body** :
```json
{
  "name": "Jean Dupont",
  "email": "jean.dupont@example.com"
}
```

**Réponse succès (200)** :
```json
{
  "success": true,
  "message": "Profil mis à jour avec succès",
  "data": {
    "id": 1,
    "name": "Jean Dupont",
    "email": "jean.dupont@example.com",
    "avatar": "https://example.com/avatar.jpg",
    "role": "admin",
    "is_active": true,
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-02T12:00:00.000000Z"
  }
}
```

**Erreurs possibles** :
- Email déjà utilisé (422) :
```json
{
  "success": false,
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

- Nom invalide (422) :
```json
{
  "success": false,
  "message": "The name field must be at least 2 characters.",
  "errors": {
    "name": ["The name field must be at least 2 characters."]
  }
}
```

---

### 3. **POST /api/profile/change-password** (À créer)
- **URL** : `https://tv.embmission.com/api/profile/change-password`
- **Méthode** : POST
- **Protection** : Token Bearer requis
- **Description** : Change le mot de passe de l'utilisateur connecté

**Body** :
```json
{
  "current_password": "AncienMotDePasse123!",
  "password": "NouveauMotDePasse123!",
  "password_confirmation": "NouveauMotDePasse123!"
}
```

**Réponse succès (200)** :
```json
{
  "success": true,
  "message": "Mot de passe changé avec succès"
}
```

**Erreurs possibles** :
- Mot de passe actuel incorrect (422) :
```json
{
  "success": false,
  "message": "Le mot de passe actuel est incorrect.",
  "errors": {
    "current_password": ["Le mot de passe actuel est incorrect."]
  }
}
```

- Mots de passe ne correspondent pas (422) :
```json
{
  "success": false,
  "message": "The password confirmation does not match.",
  "errors": {
    "password": ["The password confirmation does not match."]
  }
}
```

- Mot de passe trop court (422) :
```json
{
  "success": false,
  "message": "The password must be at least 8 characters.",
  "errors": {
    "password": ["The password must be at least 8 characters."]
  }
}
```

---

### 4. **POST /api/profile/avatar** (À créer - Optionnel)
- **URL** : `https://tv.embmission.com/api/profile/avatar`
- **Méthode** : POST
- **Protection** : Token Bearer requis
- **Content-Type** : `multipart/form-data`
- **Description** : Upload un nouvel avatar

**Body** (FormData) :
```
file: [fichier image]
```

**Réponse succès (200)** :
```json
{
  "success": true,
  "message": "Avatar mis à jour avec succès",
  "data": {
    "avatar_url": "https://tv.embmission.com/storage/avatars/user_1_avatar.jpg"
  }
}
```

**Erreurs possibles** :
- Fichier trop volumineux (422) :
```json
{
  "success": false,
  "message": "The file may not be greater than 2 MB.",
  "errors": {
    "file": ["The file may not be greater than 2 MB."]
  }
}
```

- Format non supporté (422) :
```json
{
  "success": false,
  "message": "The file must be an image.",
  "errors": {
    "file": ["The file must be an image (jpg, png, gif)."]
  }
}
```

---

## 📝 Résumé des APIs à créer

| Endpoint | Méthode | URL | Status |
|----------|---------|-----|--------|
| Get Profile | GET | `/api/auth/me` | ✅ Existe |
| Update Profile | PUT | `/api/profile` | ❌ À créer |
| Change Password | POST | `/api/profile/change-password` | ❌ À créer |
| Upload Avatar | POST | `/api/profile/avatar` | ❌ À créer (optionnel) |

---

## 🎯 Fonctionnalités possibles dans l'onglet Profil

### Avec les APIs disponibles :
- ✅ Afficher les informations utilisateur
- ✅ Afficher le rôle et le statut

### Avec les APIs à créer :
- 📝 Modifier le nom et l'email
- 🔐 Changer le mot de passe
- 🖼️ Upload/Changer l'avatar
- ✅ Affichage de la date de création/compte créé le
- 📊 Statistiques (optionnel : nombre de diffusions, etc.)






