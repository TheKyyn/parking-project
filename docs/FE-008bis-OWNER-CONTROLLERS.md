# FE-008bis : Owner API Controllers

**Status**: ✅ COMPLETED
**Priority**: P1 (High)
**Story Points**: 2pts
**Date**: 2025-12-04

---

## 📋 Résumé

Controllers HTTP pour les endpoints Owner (register, login, profile) :
- POST /api/owners (register)
- POST /api/owners/login (authenticate)
- GET /api/owners/profile (get profile - owner auth)
- PUT /api/owners/profile (update profile - owner auth)

---

## 🏗️ Composants Créés

### 1. OwnerController
**Fichier**: `src/Infrastructure/Http/Controller/OwnerController.php`

Controller avec 4 endpoints pour gérer les owners.

**Pattern**: Copié et adapté depuis UserController (FE-005)

**Méthodes:**
- `register()` - Créer un nouveau owner (public)
- `login()` - Authentifier un owner (public)
- `getProfile()` - Récupérer le profil owner (owner auth required)
- `updateProfile()` - Mettre à jour le profil owner (owner auth required)

---

## 🎯 Endpoints Implémentés

### Routes Publiques
- `POST /api/owners` - Créer un owner
  - Validation: email, password (min 8), firstName (min 2), lastName (min 2)
  - Returns: ownerId, email, firstName, lastName, createdAt
  - Status: 201 Created

- `POST /api/owners/login` - Authentifier un owner
  - Validation: email, password
  - Returns: token (JWT with type='owner'), ownerId, email, firstName, lastName, expiresIn
  - Status: 200 OK

### Routes Owner (Auth Required)
- `GET /api/owners/profile` - Récupérer son profil
  - Middleware: OwnerAuthMiddleware
  - Returns: ownerId, email, firstName, lastName, createdAt
  - Status: 200 OK

- `PUT /api/owners/profile` - Mettre à jour son profil
  - Middleware: OwnerAuthMiddleware
  - Validation: firstName (optional, min 2), lastName (optional, min 2)
  - Returns: ownerId, email, firstName, lastName
  - Status: 200 OK

---

## 🔒 Sécurité

### Authorization Owner
- OwnerAuthMiddleware sur les routes protégées
- Vérifie `type='owner'` dans le JWT
- Retourne 403 si user normal tente d'accéder
- Injecte `_ownerId` dans les pathParams du request

### JWT Token
- Contient `type='owner'` (critical pour authorization)
- Contient `ownerId`, `email`
- Expire en 3600 secondes (1 heure)

---

## 🔗 Use Cases Réutilisés (FE-004)

- ✅ CreateParkingOwner
- ✅ AuthenticateParkingOwner
- ✅ GetParkingOwnerProfile
- ✅ UpdateParkingOwner

Tous les use cases étaient déjà créés dans FE-004. Ce ticket expose simplement ces use cases via HTTP.

---

## 🧪 Tests Manuels

**Tests effectués** :
- ✅ POST /api/owners (register success)
- ✅ POST /api/owners/login (login success + JWT type='owner')
- ✅ GET /api/owners/profile (get profile)
- ✅ PUT /api/owners/profile (update profile)
- ✅ POST /api/parkings avec owner token (BONUS - works!)
- ✅ POST sans auth (401)
- ✅ Email duplicate (400)

---

## 📝 Adaptation Technique

**Défi**: Les use cases retournent `fullName` dans les responses, mais l'API doit retourner `firstName` et `lastName` séparément.

**Solution**: Le controller récupère l'entité ParkingOwner depuis le repository après l'exécution du use case pour extraire firstName et lastName.

```php
// Après use case
$owner = $this->ownerRepository->findById($response->ownerId);

// Retourne firstName et lastName séparés
return JsonResponse::success([
    'firstName' => $owner->getFirstName(),
    'lastName' => $owner->getLastName(),
    // ...
]);
```

---

## ✅ Checklist de Validation

- [x] OwnerController créé avec 4 méthodes
- [x] Use cases FE-004 réutilisés
- [x] OwnerAuthMiddleware utilisé
- [x] Routes configurées dans routes.php
- [x] Owner use cases instanciés avec dépendances
- [x] Index.php mis à jour avec owner endpoints
- [x] Tests manuels validés
- [x] JWT type='owner' vérifié
- [x] Création parking testée avec owner token
- [x] Documentation API mise à jour
- [x] Documentation ticket créée

---

## 🔗 Tickets Liés

- **Depends on**: FE-001 ✅, FE-002 ✅, FE-003 ✅, FE-004 ✅, FE-005 ✅
- **Blocks**: FE-009 (Frontend UI)
- **Enables**: Complete API testing (owners can now create parkings via API)

---

## 🎯 Impact

**Avant FE-008bis** :
- ❌ Owners ne pouvaient pas s'inscrire/login via API
- ❌ Impossible de tester POST /api/parkings complètement
- ❌ Pas de gestion de profil pour les owners

**Après FE-008bis** :
- ✅ Owners s'inscrivent/login via API
- ✅ POST /api/parkings testable avec owner token
- ✅ Owners peuvent gérer leur profil
- ✅ API backend 100% complète

---

## 📂 Fichiers Modifiés/Créés

### Créés
- `src/Infrastructure/Http/Controller/OwnerController.php` - Controller principal (283 lignes)
- `docs/FE-008bis-OWNER-CONTROLLERS.md` - Documentation ticket

### Modifiés
- `src/Infrastructure/Http/routes.php` - Ajout routes et use cases owner
- `public/index.php` - Ajout endpoints owner dans la liste
- `docs/API_ENDPOINTS.md` - Documentation API owner endpoints

---

## 🚀 Prochaines Étapes

1. **Tests unitaires** (optionnel) : Ajouter tests PHPUnit pour OwnerController
2. **Frontend** (FE-009) : Implémenter UI d'inscription/login owner
3. **Monitoring** : Ajouter logs pour les créations de compte owner

---

**Complété par**: Claude
**Validé par**: [À remplir]
