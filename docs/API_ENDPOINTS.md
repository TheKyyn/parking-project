# API Endpoints Documentation

## Base URL
```
http://localhost:8000
```

## Authentication
Most endpoints require a JWT token in the `Authorization` header:
```
Authorization: Bearer <token>
```

---

## User Endpoints

### POST /api/users
Create a new user (register).

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "firstName": "John",
  "lastName": "Doe"
}
```

**Validation:**
- `email`: required, valid email format
- `password`: required, minimum 8 characters
- `firstName`: required, minimum 2 characters
- `lastName`: required, minimum 2 characters

**Response (201 Created):**
```json
{
  "success": true,
  "message": "User created successfully",
  "data": {
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "fullName": "John Doe",
    "createdAt": "2025-12-02T12:00:00+00:00"
  }
}
```

**Errors:**
- `400` - Validation error or email already exists
- `500` - Internal server error

---

### POST /api/auth/login
Authenticate a user and receive a JWT token.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "fullName": "John Doe",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expiresIn": 3600
  }
}
```

**Errors:**
- `401` - Invalid credentials
- `422` - Validation error
- `500` - Internal server error

---

### GET /api/users/profile
Get the authenticated user's profile.

**Authentication:** Required (JWT token)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "fullName": "John Doe",
    "createdAt": "2025-12-02 12:00:00"
  }
}
```

**Errors:**
- `401` - Authentication required or invalid token
- `404` - User not found
- `500` - Internal server error

---

## Error Response Format
All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Error message"]
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

---

## Example Usage

### 1. Register a new user
```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 3. Get profile (with token)
```bash
TOKEN="your-jwt-token-here"

curl -X GET http://localhost:8000/api/users/profile \
  -H "Authorization: Bearer $TOKEN"
```
