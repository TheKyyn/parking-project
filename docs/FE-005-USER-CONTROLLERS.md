# FE-005 : User API Controllers

**Status**: ✅ COMPLETED
**Priority**: P0 (Critical)
**Story Points**: 3pts
**Date**: 2025-12-02

---

## 📋 Résumé

Controllers HTTP pour exposer les use cases User via API REST :
- POST /api/users (register)
- POST /api/auth/login (authenticate)
- GET /api/users/profile (get profile - auth required)

---

## 🏗️ Composants Créés

### 1. UserController
**Fichier**: [src/Infrastructure/Http/Controller/UserController.php](../src/Infrastructure/Http/Controller/UserController.php)

Controller avec 3 endpoints :
- `register()` - Création d'utilisateur
- `login()` - Authentification
- `getProfile()` - Récupération profil (auth)

### 2. AuthMiddleware
**Fichier**: [src/Infrastructure/Http/Middleware/AuthMiddleware.php](../src/Infrastructure/Http/Middleware/AuthMiddleware.php)

Middleware wrapper pour le Router qui :
- Extrait et valide le JWT token
- Injecte userId dans les pathParams de la requête
- Retourne 401 si authentication échoue

### 3. Routes Configuration
**Fichier**: [src/Infrastructure/Http/routes.php](../src/Infrastructure/Http/routes.php)

Configuration centralisée :
- Dependency Injection manuelle
- Enregistrement des routes
- Application des middlewares

---

## 🎯 Endpoints Implémentés

### POST /api/users
**Auth**: Non requis

Crée un nouvel utilisateur.

**Validation** :
- Email requis et valide
- Password min 8 caractères
- FirstName min 2 caractères
- LastName min 2 caractères

**Response 201** : User créé avec userId

### POST /api/auth/login
**Auth**: Non requis

Authentifie un utilisateur.

**Response 200** : JWT token + user info

### GET /api/users/profile
**Auth**: Requis (JWT token)

Récupère le profil de l'utilisateur authentifié.

**Response 200** : User profile

---

## 🔒 Sécurité

### Validation
- Validation stricte côté serveur (SimpleValidator)
- Messages d'erreur appropriés (422 validation, 401 auth)

### Authentication
- JWT token requis pour /profile
- Middleware extrait userId du token
- 401 si token absent/invalide

### Error Handling
- Try-catch dans chaque méthode
- Messages d'erreur génériques (ne pas exposer les détails internes)
- Gestion des exceptions métier (UserAlreadyExistsException, InvalidCredentialsException)

---

## 📦 Dependency Injection

Injection manuelle dans `routes.php` :
```php
$userController = new UserController(
    $createUserUseCase,
    $authenticateUserUseCase,
    $userRepository
);
```

**Future** : Conteneur DI (Symfony DependencyInjection, PHP-DI)

---

## 🔧 Corrections Apportées

### 1. MySQLUserRepository
**Ajout de méthodes manquantes** :
- `emailExists()` - Vérifie si un email existe déjà
- `findRecentlyCreated()` - Trouve les derniers utilisateurs créés

### 2. UuidGenerator
**Fix interface** : Changement de l'interface implémentée de `Infrastructure\Service\IdGeneratorInterface` vers `UseCase\User\IdGeneratorInterface` pour correspondre aux dépendances du use case.

### 3. Routes Configuration
**Loading d'environnement** : Chargement manuel du fichier `.env` dans routes.php pour accéder aux variables de configuration (DB, JWT).

---

## 🧪 Tests Manuels

Tous les endpoints testés avec curl :
- ✅ POST /api/users (success)
- ✅ POST /api/users (validation errors)
- ✅ POST /api/users (email already exists)
- ✅ POST /api/auth/login (success)
- ✅ POST /api/auth/login (invalid credentials)
- ✅ GET /api/users/profile (with token)
- ✅ GET /api/users/profile (without token - 401)

Voir [API_ENDPOINTS.md](./API_ENDPOINTS.md) pour détails et exemples.

---

## 📁 Fichiers Créés

**Source** :
- `src/Infrastructure/Http/Controller/UserController.php`
- `src/Infrastructure/Http/Middleware/AuthMiddleware.php`
- `src/Infrastructure/Http/routes.php`

**Documentation** :
- `docs/API_ENDPOINTS.md`
- `docs/FE-005-USER-CONTROLLERS.md`

**Modifiés** :
- `src/Infrastructure/Repository/MySQL/MySQLUserRepository.php` - Ajout emailExists()
- `src/Infrastructure/Service/UuidGenerator.php` - Fix interface
- `public/index.php` - Chargement routes.php

---

## ✅ Checklist de Validation

- [x] UserController créé avec 3 méthodes
- [x] AuthMiddleware créé
- [x] Routes configurées dans routes.php
- [x] Integration dans index.php
- [x] Tests manuels avec curl (tous passent)
- [x] Documentation API créée
- [x] Documentation ticket créée
- [x] emailExists() ajouté au repository
- [x] UuidGenerator interface fixée

---

## 🔗 Tickets Liés

- **Depends on**: FE-001 ✅, FE-002 ✅, FE-003 ✅, FE-004 ✅
- **Blocks**: FE-006 (Parking Controllers)
- **Related**: FE-004 (Owner Use Cases)

---

## 📝 Notes

### Architecture
L'architecture suit le pattern MVC avec séparation claire des responsabilités :
- **Controller** : Gère HTTP, validation, mapping DTO
- **Use Case** : Logique métier pure
- **Repository** : Persistance des données
- **Middleware** : Cross-cutting concerns (auth)

### Améliorations Futures
1. **Conteneur DI** : Remplacer l'injection manuelle par un conteneur (Symfony, PHP-DI)
2. **Tests Unitaires** : Ajouter PHPUnit tests pour les controllers
3. **Rate Limiting** : Limiter les tentatives de login
4. **Logging** : Logger les erreurs et événements importants
5. **Documentation OpenAPI** : Générer documentation Swagger/OpenAPI
6. **Refresh Token** : Implémenter refresh token pour JWT

---

**Complété par**: Claude
**Validé par**: [À remplir]
