# 📺 FONCTIONNALITÉS WEBTV - EMB MISSION

## 🎯 Page d'accueil
**URL**: `/`

### Fonctionnalités disponibles :
- ✅ Page d'accueil avec présentation des services WebTV et WebRadio
- ✅ Affichage de la vision, mission et services
- ✅ Aperçu des plateformes disponibles
- ✅ Formulaire de contact

---

## 🏠 DASHBOARD
**URL**: `/dashboard`

### Fonctionnalités disponibles :
- ✅ **Statistiques en temps réel**
  - Audience live (WebTV + WebRadio)
  - Vues totales sur 30 jours
  - Durée de diffusion sur 30 jours
  - Engagement global
  
- ✅ **Cartes de services**
  - Démarrage rapide WebTV
  - Démarrage rapide WebRadio
  - Bibliothèque média
  - Diffusion live
  
- ✅ **Dernières diffusions**
  - Historique des dernières diffusions
  - Statut en temps réel

---

## 📺 WEBTV - GESTION COMPLÈTE
**URL**: `/webtv`

### Pages disponibles :

#### 1. **Partage & Intégration** 
**URL**: `/webtv`

**Fonctionnalités** :
- ✅ Aperçu du lecteur WebTV en temps réel
- ✅ Code iframe pour intégration sur site externe
- ✅ Lien direct de partage
- ✅ Boutons de partage social (Facebook, Twitter/X, WhatsApp)
- ✅ Code iframe formaté avec attributs de sécurité :
  ```html
  <iframe src="https://tv.embmission.com/watch" width="800" height="450" frameborder="0" allowfullscreen="true" allow="autoplay; encrypted-media; fullscreen"></iframe>
  ```

#### 2. **Lecteur WebTV**
**URL**: `/webtv/player`

**Fonctionnalités** :
- ✅ Lecteur vidéo en direct avec support WebRTC (Ant Media Server)
- ✅ Lecture automatique des vidéos VoD depuis les playlists
- ✅ Détection automatique du mode Live vs VoD
- ✅ Badge "LIVE" ou "VOD" selon le contenu diffusé
- ✅ Informations détaillées de l'événement en cours
- ✅ Statistiques de diffusion (spectateurs, durée)
- ✅ Bouton de partage rapide
- ✅ Mise à jour automatique toutes les 10 secondes
- ✅ Support responsive (mobile, tablette, desktop)

#### 3. **Sources Vidéo**
**URL**: `/webtv/sources`

**Fonctionnalités** :
- ✅ Liste des sources vidéo disponibles
- ✅ Gestion des playlists WebTV
- ✅ Création, modification, suppression de playlists
- ✅ Synchronisation avec Ant Media Server
- ✅ Import de vidéos depuis la bibliothèque
- ✅ Gestion de l'ordre des vidéos

#### 4. **Contrôle WebTV**
**URL**: `/webtv/control`

**Fonctionnalités** :
- ✅ Contrôle de la diffusion en cours
- ✅ Démarrer/Arrêter la diffusion
- ✅ Gestion des playlists actives
- ✅ Gestion des paramètres de streaming
- ✅ Statut de connexion avec Ant Media Server
- ✅ Configuration des paramètres de diffusion

#### 5. **Diffusion Live**
**URL**: `/webtv/diffusionlive`

**Fonctionnalités** :
- ✅ Démarrage de diffusion live
- ✅ Configuration OBS Studio
- ✅ Paramètres de connexion RTMP
- ✅ Aperçu du flux en direct

---

## 📻 WEBRADIO - GESTION COMPLÈTE
**URL**: `/webradio`

### Pages disponibles :

#### 1. **Page principale**
**URL**: `/webradio`

**Fonctionnalités** :
- ✅ Aperçu de la WebRadio
- ✅ Statut de diffusion
- ✅ Informations du flux en cours

#### 2. **Sources Radio**
**URL**: `/webradio/sources`

**Fonctionnalités** :
- ✅ Liste des playlists audio
- ✅ Gestion des sources audio
- ✅ Import depuis la bibliothèque
- ✅ Synchronisation avec AzuraCast

#### 3. **Contrôle WebRadio**
**URL**: `/webradio/control`

**Fonctionnalités** :
- ✅ Contrôle de la diffusion
- ✅ Démarrer/Arrêter la diffusion
- ✅ Gestion des playlists actives
- ✅ Statistiques en temps réel

---

## 📁 GESTION DES CONTENUS
**URL**: `/contents`

### Fonctionnalités disponibles :

- ✅ **Onglet Vidéos**
  - Liste des vidéos uploadées
  - Play en un clic pour voir la vidéo
  - Édition des métadonnées vidéo
  - Suppression de vidéos
  - Filtrage et recherche
  - Pagination
  
- ✅ **Onglet Audios**
  - Liste des fichiers audio uploadés
  - Lecture audio directe dans le navigateur
  - Édition des métadonnées audio
  - Suppression d'audios
  - Filtrage et recherche
  - Pagination

- ✅ **Upload de fichiers**
  - Upload drag & drop
  - Upload multiple
  - Suivi de progression en temps réel
  - Support vidéo, audio, images
  - Extraction automatique des métadonnées

- ✅ **Gestion des playlists**
  - Création de playlists WebTV
  - Création de playlists Radio
  - Organisation par ordre
  - Sync automatique avec Ant Media / AzuraCast

---

## 📊 ANALYTICS
**URL**: `/analytics`

### Fonctionnalités disponibles :
- ✅ Statistiques détaillées
- ✅ Analyse de l'audience
- ✅ Historique des diffusions
- ✅ Rapports périodiques

---

## ⚙️ PARAMÈTRES
**URL**: `/settings`

### Fonctionnalités disponibles :

- ✅ **Onglet Profil**
  - Affichage des informations utilisateur
  - Avatar avec initiales
  - Nom, rôle, email
  - Statut du compte
  - Dates de création et modification

- ✅ **Onglet WebTV**
  - Configuration des streams
  - Paramètres de connexion Ant Media Server
  - Gestion des playlists
  - Paramètres de qualité vidéo

- ✅ **Onglet WebRadio**
  - Configuration des streams radio
  - Paramètres de connexion AzuraCast
  - Gestion des playlists audio
  - Paramètres de qualité audio

- ✅ **Onglet Compte**
  - Gestion du mot de passe
  - Paramètres de sécurité
  - Préférences de notification

---

## 🎥 DIFFUSION LIVE
**URL**: `/diffusionlive`

### Fonctionnalités disponibles :
- ✅ Démarrage de diffusion live
- ✅ Configuration OBS Studio
- ✅ Paramètres de connexion RTMP
- ✅ Aperçu du flux en direct
- ✅ Gestion de la diffusion

---

## 👤 PROFIL
**URL**: `/profile`

### Fonctionnalités disponibles :
- ✅ Informations personnelles
- ✅ Avatar utilisateur
- ✅ Statistiques personnelles
- ✅ Historique des actions

---

## 🔐 AUTHENTIFICATION

### Fonctionnalités disponibles :
- ✅ **Connexion**
  - URL: `/auth`
  - Identification sécurisée
  - Gestion de session

- ✅ **Inscription**
  - URL: `/auth/register`
  - Création de compte
  - Validation des données

- ✅ **Mot de passe oublié**
  - URL: `/auth/forget-password`
  - Envoi d'email de réinitialisation
  - Lien sécurisé

- ✅ **Réinitialisation du mot de passe**
  - URL: `/auth/reset-password`
  - Nouveau mot de passe
  - Token sécurisé

---

## 🌐 CARACTÉRISTIQUES TECHNIQUES

### Responsive Design
- ✅ Mobile (écrans < 768px)
- ✅ Tablette (768px - 1024px)
- ✅ Desktop (> 1024px)

### Design System
- ✅ Interface moderne et professionnelle
- ✅ Navigation intuitive
- ✅ Animations fluides
- ✅ Thème cohérent (couleurs EMB Mission)

### Authentification & Sécurité
- ✅ Gestion de session
- ✅ Protection des routes
- ✅ Tokens sécurisés
- ✅ Validation des données

### Intégrations API
- ✅ API WebTV : https://tv.embmission.com/api
- ✅ API Radio : https://radio.embmission.com/api
- ✅ Ant Media Server pour streaming live
- ✅ AzuraCast pour diffusion radio

---

## 🚀 COMMENT TESTER

### Accès
1. URL de l'application : `https://rtv.embmission.com` (à confirmer)
2. Créer un compte via `/auth/register`
3. Se connecter via `/auth`

### Parcours de test recommandé

#### 1. Dashboard
- Visiter `/dashboard`
- Vérifier les statistiques en temps réel
- Cliquer sur "Démarrer WebTV" ou "Démarrer WebRadio"

#### 2. WebTV
- Naviguer vers `/webtv`
- Tester le lecteur sur `/webtv/player`
- Configurer les sources sur `/webtv/sources`
- Tester le contrôle sur `/webtv/control`

#### 3. Gestion des contenus
- Aller sur `/contents`
- Uploader une vidéo ou audio
- Tester la lecture
- Créer une playlist

#### 4. Partages
- Sur `/webtv`, copier le code iframe
- Tester l'intégration sur un site externe
- Tester les boutons de partage social

---

## 📝 NOTES IMPORTANTES

### Fonctionnalités en développement
- ⏳ Éditeur de métadonnées (temporairement désactivé)
- ⏳ Statistiques avancées
- ⏳ Rapports exportables

### Limitations connues
- Stockage actuel : 1h de contenu
- Capacité actuelle : 1000 spectateurs simultanés
- Objectifs : 168h de stockage, 2000+ spectateurs WebTV, 5000+ auditeurs WebRadio

### Support technique
- Documentation API : disponible
- Support client : contact@embmission.com (exemple)

---

**Version**: 1.0.0
**Date**: Janvier 2024
**Développé par**: EMB Mission Team





