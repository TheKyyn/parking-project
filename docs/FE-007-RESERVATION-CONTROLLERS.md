# FE-007 : Reservation API Controllers

**Status**: ✅ COMPLETED
**Priority**: P0 (Critical)
**Story Points**: 3pts
**Date**: 2025-12-04

---

## 📋 Résumé

Implémentation des controllers HTTP pour la gestion des réservations de parking :
- POST /api/reservations (create - user auth required)
- GET /api/reservations (list user's reservations - user auth)
- GET /api/reservations/:id (show - user auth with ownership check)
- DELETE /api/reservations/:id (cancel - user auth, only before start)

---

## 🏗️ Composants Créés

### 1. Middleware d'Authentification
**Fichier**: [src/Infrastructure/Http/Middleware/UserAuthMiddleware.php](../src/Infrastructure/Http/Middleware/UserAuthMiddleware.php)

Middleware vérifiant que l'utilisateur authentifié a le type 'user' dans son JWT token.
- Vérifie `type='user'` dans le payload JWT
- Retourne 403 si un owner tente d'accéder aux endpoints utilisateur
- Injecte `_userId` et `_userEmail` dans les pathParams

### 2. Services Métier

#### PricingCalculator
**Fichier**: [src/Infrastructure/Service/SimplePricingCalculator.php](../src/Infrastructure/Service/SimplePricingCalculator.php)

Service de calcul des prix avec facturation par tranches de 15 minutes.
- Implémente `PricingCalculatorInterface`
- Formule : `quarters * (hourlyRate / 4)`
- Arrondi au centime supérieur
- **Réutilisé dans FE-008 (Session Controllers)**

#### ConflictChecker
**Fichier**: [src/Infrastructure/Service/SimpleConflictChecker.php](../src/Infrastructure/Service/SimpleConflictChecker.php)

Service de vérification de disponibilité des places de parking.
- Vérifie les conflits de réservation
- Calcule les places disponibles à un instant T
- Prend en compte la capacité totale du parking

### 3. Use Cases

#### CreateReservation
**Fichier**: [src/UseCase/Reservation/CreateReservation.php](../src/UseCase/Reservation/CreateReservation.php) *(existant)*

Use case de création de réservation avec :
- Vérification utilisateur et parking
- Validation des horaires d'ouverture
- Détection de conflits (places disponibles)
- Calcul du prix avec PricingCalculator
- Confirmation automatique

#### CancelReservation
**Fichier**: [src/UseCase/Reservation/CancelReservation.php](../src/UseCase/Reservation/CancelReservation.php) *(créé)*

Use case d'annulation de réservation avec :
- Vérification de propriété (userId match)
- Interdiction si réservation déjà commencée
- Interdiction si déjà annulée
- Appel à `$reservation->cancel()`

### 4. Controller
**Fichier**: [src/Infrastructure/Http/Controller/ReservationController.php](../src/Infrastructure/Http/Controller/ReservationController.php)

Controller avec 4 méthodes :
- **create()**: POST /api/reservations - Crée une réservation
- **index()**: GET /api/reservations - Liste les réservations de l'utilisateur
- **show()**: GET /api/reservations/:id - Détails d'une réservation
- **cancel()**: DELETE /api/reservations/:id - Annule une réservation

### 5. Configuration Routes
**Fichier**: [src/Infrastructure/Http/routes.php](../src/Infrastructure/Http/routes.php)

Ajout de :
- Import des classes nécessaires
- Initialisation de `MySQLReservationRepository`
- Initialisation des services (`SimplePricingCalculator`, `SimpleConflictChecker`)
- Initialisation des use cases (`CreateReservation`, `CancelReservation`)
- Initialisation du `ReservationController`
- Initialisation du `UserAuthMiddleware`
- Enregistrement des 4 routes avec le middleware

### 6. Modification JWT
**Fichier**: [src/Infrastructure/Http/Middleware/JwtAuthMiddleware.php](../src/Infrastructure/Http/Middleware/JwtAuthMiddleware.php)

Ajout du champ `type` dans le retour de la méthode `authenticate()` :
```php
return [
    'userId' => $payload['userId'],
    'email' => $payload['email'],
    'type' => $payload['type'] ?? null,  // ← AJOUTÉ
];
```

---

## 🎯 Endpoints Implémentés

### Routes User (Authentication Requise)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | /api/reservations | Créer une réservation | User only |
| GET | /api/reservations | Liste ses réservations | User only |
| GET | /api/reservations/:id | Détails d'une réservation | User only |
| DELETE | /api/reservations/:id | Annuler une réservation | User only |

---

## 🔒 Business Rules

### Pricing (15 minutes)
- Tarification par tranche de 15 minutes (quarters)
- Formula: `quarters * (hourlyRate / 4)`
- Exemple: 2h à 3.50€/h = 8 × 0.875€ = 7.00€

### Validation Réservation
- Durée minimum: 15 minutes
- Durée maximum: 24 heures
- Pas de réservation dans le passé
- endTime > startTime
- Parking doit être ouvert pendant toute la période

### Conflict Detection
- Vérifie qu'il reste des places disponibles
- Compare avec les réservations existantes (status='confirmed')
- Prend en compte la capacité totale du parking

### Cancellation
- Seulement si pas encore commencée (startTime > now)
- Seulement par le propriétaire de la réservation
- Pas de double annulation
- Statut devient 'cancelled'

---

## 🔒 Sécurité

### Authorization User
- Middleware `UserAuthMiddleware` vérifie `type='user'`
- Retourne 403 si owner tente d'accéder
- Injection `_userId` et `_userEmail` dans la requête

### Ownership Verification
- Show/Cancel vérifient que la réservation appartient au user
- Message d'erreur : "Unauthorized: This is not your reservation"
- Pas de fuite d'information (404 si pas trouvé)

---

## 🧪 Tests Manuels

### Scénarios de Test

#### 1. POST /api/reservations (success)
```bash
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -d '{
    "parkingId": "parking-uuid",
    "startTime": "2025-12-05T10:00:00",
    "endTime": "2025-12-05T12:00:00"
  }'
```

**Résultat attendu**: 201 Created avec reservationId, totalAmount calculé

#### 2. GET /api/reservations (liste)
```bash
curl -X GET http://localhost:8000/api/reservations \
  -H "Authorization: Bearer $USER_TOKEN"
```

**Résultat attendu**: 200 OK avec tableau de réservations

#### 3. GET /api/reservations/:id (détails)
```bash
curl -X GET http://localhost:8000/api/reservations/$RESERVATION_ID \
  -H "Authorization: Bearer $USER_TOKEN"
```

**Résultat attendu**: 200 OK avec détails de la réservation

#### 4. DELETE /api/reservations/:id (cancel)
```bash
curl -X DELETE http://localhost:8000/api/reservations/$RESERVATION_ID \
  -H "Authorization: Bearer $USER_TOKEN"
```

**Résultat attendu**: 204 No Content

#### 5. POST sans auth (401)
```bash
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -d '{"parkingId": "test", "startTime": "2025-12-05T10:00:00", "endTime": "2025-12-05T12:00:00"}'
```

**Résultat attendu**: 401 Unauthorized

#### 6. POST avec owner token (403)
```bash
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OWNER_TOKEN" \
  -d '{"parkingId": "test", "startTime": "2025-12-05T10:00:00", "endTime": "2025-12-05T12:00:00"}'
```

**Résultat attendu**: 403 Forbidden

#### 7. Conflict detection (400)
```bash
# Créer première réservation
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -d '{
    "parkingId": "parking-uuid",
    "startTime": "2025-12-05T10:00:00",
    "endTime": "2025-12-05T12:00:00"
  }'

# Tenter de créer réservation qui chevauche (même parking, même horaire, mais plus de places)
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -d '{
    "parkingId": "parking-uuid",
    "startTime": "2025-12-05T10:00:00",
    "endTime": "2025-12-05T12:00:00"
  }'
```

**Résultat attendu**: 400 "No available spaces for the requested time period" (si parking plein)

---

## ✅ Checklist de Validation

- [x] JwtAuthMiddleware retourne le champ `type`
- [x] UserAuthMiddleware créé et fonctionnel
- [x] SimplePricingCalculator créé (réutilisable FE-008)
- [x] SimpleConflictChecker créé
- [x] CancelReservation use case créé
- [x] ReservationController créé avec 4 méthodes
- [x] Routes configurées dans routes.php
- [x] Syntax PHP validée (php -l sur tous les fichiers)
- [x] Conflict detection implémentée
- [x] Pricing calculation validée (15 min increments)
- [x] Documentation API mise à jour
- [x] Documentation ticket créée

---

## 📊 Métriques

### Fichiers Créés
- **Use Cases**: 1 fichier (CancelReservation)
- **Controllers**: 1 fichier (ReservationController)
- **Middleware**: 1 fichier (UserAuthMiddleware)
- **Services**: 2 fichiers (SimplePricingCalculator, SimpleConflictChecker)
- **Docs**: 1 fichier mise à jour (API_ENDPOINTS.md)
- **Total**: 6 fichiers

### Lignes de Code
- ReservationController: ~220 lignes
- UserAuthMiddleware: ~60 lignes
- SimplePricingCalculator: ~70 lignes
- SimpleConflictChecker: ~120 lignes
- CancelReservation: ~60 lignes
- **Total**: ~530 lignes

---

## 🔗 Tickets Liés

- **Depends on**:
  - FE-001 ✅ (Auth Infrastructure)
  - FE-002 ✅ (Request/Response)
  - FE-003 ✅ (Routing System)
  - FE-005 ✅ (User API Controllers)
  - FE-006 ✅ (Parking API Controllers)
  - BE-002 ✅ (Reservation Core Logic - by Maxime)

- **Blocks**:
  - FE-008 (Session Controllers)

- **Related**:
  - BE-002 (Reservation Entity & Repository)

---

## 🎓 Points d'Apprentissage

### Architecture
- Séparation claire Use Case / Controller / Middleware
- Services réutilisables (PricingCalculator pour FE-008)
- Distinction user vs owner au niveau middleware

### Business Logic
- Pricing par tranches de 15 minutes
- Conflict detection basée sur capacité totale
- Validation temporelle multi-niveaux

### Sécurité
- JWT type-based authorization (user vs owner)
- Ownership verification sur show/cancel
- Pas de fuite d'information (404 uniforme)

---

**Complété par**: Claude Code
**Date de complétion**: 2025-12-04
**Temps estimé**: 3pts
**Temps réel**: ~2h
