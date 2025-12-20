# FE-001 : JWT & Auth Infrastructure

**Status**: ✅ COMPLETED
**Priority**: P0 (Critical)
**Story Points**: 3pts
**Date**: 2025-12-02

---

## 📋 Résumé

Infrastructure d'authentification complète pour le système de parking :
- Génération d'IDs uniques (UUID v4)
- Hashage sécurisé des passwords (Bcrypt)
- Génération et validation de tokens JWT
- Middleware d'authentification HTTP

---

## 🏗️ Composants Créés

### 1. UUID Generator
**Fichier**: [src/Infrastructure/Service/UuidGenerator.php](../src/Infrastructure/Service/UuidGenerator.php)

Génère des identifiants UUID v4 conformes RFC 4122.
```php
$generator = new UuidGenerator();
$id = $generator->generate(); // "550e8400-e29b-41d4-a716-446655440000"
```

**Interface**: [src/Infrastructure/Service/IdGeneratorInterface.php](../src/Infrastructure/Service/IdGeneratorInterface.php)

### 2. Password Hasher
**Fichier**: [src/Infrastructure/Service/BcryptPasswordHasher.php](../src/Infrastructure/Service/BcryptPasswordHasher.php)

Hash et vérifie les passwords avec Bcrypt (cost=12 par défaut).
```php
$hasher = new BcryptPasswordHasher();
$hash = $hasher->hash('my_password');
$valid = $hasher->verify('my_password', $hash); // true
```

**Interface réutilisée**: [src/UseCase/User/PasswordHasherInterface.php](../src/UseCase/User/PasswordHasherInterface.php)

### 3. JWT Token Generator
**Fichier**: [src/Infrastructure/Service/FirebaseJwtTokenGenerator.php](../src/Infrastructure/Service/FirebaseJwtTokenGenerator.php)

Génère et valide des tokens JWT avec expiration.
```php
$generator = new FirebaseJwtTokenGenerator($secretKey);
$payload = [
    'userId' => 'user-123',
    'email' => 'user@example.com',
    'iat' => time(),
    'exp' => time() + 3600,
];
$token = $generator->generate($payload, 3600);
$decoded = $generator->verify($token);
```

**Interface réutilisée**: [src/UseCase/User/JwtTokenGeneratorInterface.php](../src/UseCase/User/JwtTokenGeneratorInterface.php)

**Méthodes supplémentaires**:
- `decode(string $token)`: Décode sans vérifier la signature
- `extractUserId(string $token)`: Extrait l'userId pour le logging

### 4. Auth Middleware
**Fichier**: [src/Infrastructure/Http/Middleware/JwtAuthMiddleware.php](../src/Infrastructure/Http/Middleware/JwtAuthMiddleware.php)

Authentifie les requêtes HTTP via JWT.
```php
$middleware = new JwtAuthMiddleware($jwtGenerator);
$user = $middleware->authenticate($headers); // ['userId' => '...', 'email' => '...']
```

**Interface**: [src/Infrastructure/Http/Middleware/AuthMiddlewareInterface.php](../src/Infrastructure/Http/Middleware/AuthMiddlewareInterface.php)

---

## 🧪 Tests

**Total**: 50 tests, 78 assertions (tests infrastructure uniquement)
**Total projet**: 156 tests, 456 assertions

### Tests Unitaires
- ✅ `UuidGeneratorTest`: 5 tests
- ✅ `BcryptPasswordHasherTest`: 12 tests
- ✅ `FirebaseJwtTokenGeneratorTest`: 17 tests
- ✅ `JwtAuthMiddlewareTest`: 16 tests

**Couverture**: 100% des composants critiques

### Test d'Intégration
**Fichier**: [test_auth.php](../test_auth.php)

Test manuel vérifiant l'interaction complète de tous les composants.

```bash
php test_auth.php
```

---

## 🔒 Sécurité

### Bcrypt
- Algorithme adaptatif (résiste aux attaques par force brute)
- Salt automatique et aléatoire
- Cost=12 (bon compromis sécurité/performance)
- Configurable via constructeur

### JWT
- Algorithme HS256 (HMAC avec SHA-256)
- Secret key minimum 32 caractères (validation au constructeur)
- Expiration minimum 60 secondes (validation)
- Validation stricte (signature, expiration, format)
- Support des claims personnalisés

### Middleware
- Extraction sécurisée du token (case-insensitive)
- Validation complète avant authentification
- Messages d'erreur génériques (pas de leak d'info)
- Support du format standard "Bearer <token>"

---

## 📦 Dépendances

```json
{
    "firebase/php-jwt": "^6.0"
}
```

**Déjà installé** ✅

---

## 🔧 Architecture

### Clean Architecture
Les composants respectent la séparation des couches :
- **Use Case Layer**: Interfaces définies (déjà existantes)
- **Infrastructure Layer**: Implémentations concrètes (nouvelles)
- **Tests**: Tests unitaires complets

### Réutilisation des Interfaces
Le code réutilise les interfaces existantes dans `UseCase/User/`:
- `PasswordHasherInterface`
- `JwtTokenGeneratorInterface`
- `IdGeneratorInterface` (multiple, une par domaine)

Nouvelle interface partagée créée :
- `Infrastructure/Service/IdGeneratorInterface` (pour UUID centralisé)

---

## 🚀 Utilisation dans les Use Cases

Le use case `AuthenticateUser` utilise déjà ces interfaces :

```php
class AuthenticateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtTokenGeneratorInterface $tokenGenerator
    ) {}
}
```

Pour injecter les implémentations :
```php
$hasher = new BcryptPasswordHasher();
$jwtGen = new FirebaseJwtTokenGenerator($secretKey);

$authenticateUser = new AuthenticateUser(
    $userRepository,
    $hasher,
    $jwtGen
);
```

---

## 📂 Structure des Fichiers

```
src/
├── Infrastructure/
│   ├── Service/
│   │   ├── IdGeneratorInterface.php
│   │   ├── UuidGenerator.php
│   │   ├── BcryptPasswordHasher.php
│   │   └── FirebaseJwtTokenGenerator.php
│   └── Http/
│       └── Middleware/
│           ├── AuthMiddlewareInterface.php
│           └── JwtAuthMiddleware.php
└── UseCase/
    └── User/
        ├── PasswordHasherInterface.php (existant)
        ├── JwtTokenGeneratorInterface.php (existant)
        └── IdGeneratorInterface.php (existant)

tests/
└── Unit/
    └── Infrastructure/
        ├── Service/
        │   ├── UuidGeneratorTest.php
        │   ├── BcryptPasswordHasherTest.php
        │   └── FirebaseJwtTokenGeneratorTest.php
        └── Http/
            └── Middleware/
                └── JwtAuthMiddlewareTest.php
```

---

## ✅ Checklist de Validation

- [x] UuidGenerator implémenté et testé (5 tests)
- [x] BcryptPasswordHasher implémenté et testé (12 tests)
- [x] FirebaseJwtTokenGenerator implémenté et testé (17 tests)
- [x] JwtAuthMiddleware implémenté et testé (16 tests)
- [x] Tous les tests passent (50 tests infrastructure, 156 total)
- [x] Test d'intégration manuel réussi
- [x] Documentation créée
- [x] Dépendance firebase/php-jwt déjà installée
- [x] Compatible avec use case existant `AuthenticateUser`

---

## 🔗 Tickets Liés

- **Depends on**: Aucun
- **Blocks**: FE-002 (Request/Response Infrastructure)
- **Related**: FE-005 (User API Controllers)
- **Uses**: Use case `AuthenticateUser` déjà implémenté

---

## 📝 Notes Techniques

### Configuration Recommandée
```php
// .env ou config
JWT_SECRET_KEY=your_secret_key_at_least_32_chars_long
JWT_EXPIRATION=3600 // 1 heure
BCRYPT_COST=12 // Augmenter avec le temps
```

### Gestion des Tokens
- Les tokens JWT sont **stateless** (pas de stockage côté serveur)
- Pour révoquer un token, implémenter une **blacklist** séparément
- Le middleware extrait l'userId sans validation complète (pour logging)

### Performance
- Bcrypt cost=12 : ~200ms par hash sur hardware moderne
- Peut être ajusté selon les besoins (4-31)
- Tests d'expiration prennent ~2 secondes (sleep nécessaire)

### Évolutions Futures
- [ ] Refresh tokens (FE-003)
- [ ] Token blacklist (pour révocation)
- [ ] Rate limiting sur l'authentification
- [ ] Support de claims personnalisés avancés

---

## 🎯 Résultats

### Tests
- **Infrastructure**: 50 tests, 78 assertions ✅
- **Projet complet**: 156 tests, 456 assertions ✅
- **Temps d'exécution**: ~4 secondes
- **Couverture**: 100% des composants critiques

### Qualité
- ✅ PSR-12 compliant
- ✅ Type hints stricts (strict_types=1)
- ✅ Documentation complète (PHPDoc)
- ✅ Gestion d'erreurs robuste
- ✅ Tests edge cases (unicode, caractères spéciaux, etc.)

---

**Complété par**: Claude
**Validé par**: À compléter
**Date de complétion**: 2025-12-02
