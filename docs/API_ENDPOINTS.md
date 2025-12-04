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

## Parking Endpoints

### GET /api/parkings
List all parkings (or search with GPS coordinates).

**Query Parameters (optional):**
- `latitude` (float): GPS latitude for search
- `longitude` (float): GPS longitude for search
- `maxDistance` (float): Maximum distance in km (default: 5.0)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Parkings retrieved successfully",
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "ownerId": "owner-uuid",
      "name": "Parking Central",
      "address": "123 Rue de la Paix, Paris",
      "location": {
        "latitude": 48.8566,
        "longitude": 2.3522
      },
      "hourlyRate": 3.5,
      "totalSpots": 50,
      "createdAt": "2025-12-02 12:00:00"
    }
  ]
}
```

---

### GET /api/parkings/:id
Get parking details by ID.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Parking retrieved successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "ownerId": "owner-uuid",
    "name": "Parking Central",
    "address": "123 Rue de la Paix, Paris",
    "location": {
      "latitude": 48.8566,
      "longitude": 2.3522
    },
    "hourlyRate": 3.5,
    "totalSpots": 50,
    "createdAt": "2025-12-02 12:00:00"
  }
}
```

**Errors:**
- `404` - Parking not found

---

### POST /api/parkings
Create a new parking (owner only).

**Authentication:** Required (JWT token with type='owner')

**Request Body:**
```json
{
  "name": "Parking Central",
  "address": "123 Rue de la Paix, Paris",
  "latitude": 48.8566,
  "longitude": 2.3522,
  "hourlyRate": 3.5,
  "totalSpots": 50
}
```

**Validation:**
- `name`: required, minimum 3 characters
- `address`: required, minimum 5 characters
- `latitude`: required, between -90 and 90
- `longitude`: required, between -180 and 180
- `hourlyRate`: required, greater than 0
- `totalSpots`: required, greater than 0

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Parking created successfully",
  "data": {
    "parkingId": "550e8400-e29b-41d4-a716-446655440000",
    "ownerId": "owner-uuid",
    "name": "Parking Central",
    "address": "123 Rue de la Paix, Paris",
    "location": {
      "latitude": 48.8566,
      "longitude": 2.3522
    },
    "hourlyRate": 3.5,
    "totalSpots": 50,
    "createdAt": "2025-12-02T12:00:00+00:00"
  }
}
```

**Errors:**
- `401` - Authentication required
- `403` - Not an owner account
- `422` - Validation error

---

### PUT /api/parkings/:id
Update a parking (owner only, must be the parking owner).

**Authentication:** Required (JWT token with type='owner')

**Request Body (all fields optional):**
```json
{
  "name": "Parking Central Updated",
  "address": "New Address",
  "hourlyRate": 4.0,
  "totalSpots": 60
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Parking updated successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "ownerId": "owner-uuid",
    "name": "Parking Central Updated",
    "address": "New Address",
    "location": {
      "latitude": 48.8566,
      "longitude": 2.3522
    },
    "hourlyRate": 4.0,
    "totalSpots": 60,
    "createdAt": "2025-12-02 12:00:00"
  }
}
```

**Errors:**
- `401` - Authentication required
- `403` - Not the parking owner
- `404` - Parking not found
- `422` - Validation error

---

### DELETE /api/parkings/:id
Delete a parking (owner only, must be the parking owner).

**Authentication:** Required (JWT token with type='owner')

**Response (204 No Content)**

**Errors:**
- `401` - Authentication required
- `403` - Not the parking owner
- `404` - Parking not found

---

## Reservation Endpoints

### POST /api/reservations
Create a new reservation (user only).

**Authentication:** Required (JWT token with type='user')

**Request Body:**
```json
{
  "parkingId": "550e8400-e29b-41d4-a716-446655440000",
  "startTime": "2025-12-04T10:00:00",
  "endTime": "2025-12-04T12:00:00"
}
```

**Validation:**
- `parkingId`: required, must exist
- `startTime`: required, ISO 8601 format, must be in the future
- `endTime`: required, ISO 8601 format, must be after startTime
- Duration: minimum 15 minutes, maximum 24 hours
- No conflicts with existing reservations
- Parking must be open during reservation period

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Reservation created successfully",
  "data": {
    "reservationId": "550e8400-e29b-41d4-a716-446655440000",
    "userId": "user-uuid",
    "parkingId": "parking-uuid",
    "startTime": "2025-12-04T10:00:00+00:00",
    "endTime": "2025-12-04T12:00:00+00:00",
    "totalAmount": 7.0,
    "durationMinutes": 120,
    "status": "confirmed"
  }
}
```

**Cost Calculation:**
- Billed in 15-minute increments (quarters)
- Formula: `quarters * (hourlyRate / 4)`
- Example: 2 hours at 3.50€/hour = 8 quarters × 0.875€ = 7.00€

**Errors:**
- `400` - Validation error, conflict, or business rule violation
- `401` - Authentication required
- `403` - Owner trying to create reservation (users only)
- `404` - Parking or user not found

---

### GET /api/reservations
List all reservations for the authenticated user.

**Authentication:** Required (JWT token with type='user')

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Reservations retrieved successfully",
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "userId": "user-uuid",
      "parkingId": "parking-uuid",
      "startTime": "2025-12-04 10:00:00",
      "endTime": "2025-12-04 12:00:00",
      "totalAmount": 7.0,
      "status": "confirmed",
      "createdAt": "2025-12-02 15:00:00"
    }
  ]
}
```

**Errors:**
- `401` - Authentication required
- `403` - Owner trying to access reservations (users only)

---

### GET /api/reservations/:id
Get reservation details (user must own the reservation).

**Authentication:** Required (JWT token with type='user')

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Reservation retrieved successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "userId": "user-uuid",
    "parkingId": "parking-uuid",
    "startTime": "2025-12-04 10:00:00",
    "endTime": "2025-12-04 12:00:00",
    "totalAmount": 7.0,
    "status": "confirmed",
    "createdAt": "2025-12-02 15:00:00"
  }
}
```

**Errors:**
- `401` - Authentication required
- `403` - Not your reservation or owner trying to access
- `404` - Reservation not found

---

### DELETE /api/reservations/:id
Cancel a reservation (only if not yet started).

**Authentication:** Required (JWT token with type='user')

**Business Rules:**
- Can only cancel reservations that haven't started yet (startTime > now)
- Must be the reservation owner
- Cannot cancel already cancelled reservations

**Response (204 No Content)**

**Errors:**
- `400` - Reservation already started or already cancelled
- `401` - Authentication required
- `403` - Not your reservation or owner trying to cancel
- `404` - Reservation not found

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

---

## Session Endpoints

### POST /api/sessions
Start a parking session from an active reservation (user only).

**Authentication:** Required (JWT token with type='user')

**Request Body:**
```json
{
  "parkingId": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Validation:**
- `parkingId`: required, must exist
- User must have an active reservation or subscription for this parking
- No active session must already exist for this user-parking combination
- Parking must be open at entry time

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Session started successfully",
  "data": {
    "sessionId": "uuid",
    "userId": "uuid",
    "parkingId": "uuid",
    "reservationId": "uuid",
    "startTime": "2025-12-04T10:00:00+00:00",
    "authorizedEndTime": "2025-12-04T12:00:00+00:00",
    "status": "active"
  }
}
```

**Errors:**
- `400` - No active reservation/subscription, parking closed, or active session already exists
- `401` - Authentication required
- `404` - Parking or user not found
- `500` - Internal server error

---

### PUT /api/sessions/:id/end
End an active parking session and calculate final cost.

**Authentication:** Required (JWT token with type='user')

**Path Parameters:**
- `:id` - Session UUID

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Session ended successfully",
  "data": {
    "sessionId": "uuid",
    "userId": "uuid",
    "parkingId": "uuid",
    "startTime": "2025-12-04T10:00:00+00:00",
    "endTime": "2025-12-04T12:05:00+00:00",
    "durationMinutes": 125,
    "baseAmount": 7.0,
    "overstayPenalty": 0.0,
    "totalAmount": 7.0,
    "wasOverstayed": false,
    "status": "completed"
  }
}
```

**Cost Calculation:**
- Uses 15-minute billing increments (rounds up)
- Formula: `quarters * (hourlyRate / 4)`
- Example: 2h05min at 3.50€/h = 9 quarters * 0.875€ = 7.875€
- Overstay penalty: €20 base + additional time charged

**Ownership:**
- Session must belong to the authenticated user
- Error message: "Unauthorized: This is not your session"

**Errors:**
- `400` - Session not active (already ended or cancelled)
- `401` - Authentication required
- `403` - Not your session (ownership check failed)
- `404` - Session not found
- `500` - Internal server error

---

### GET /api/sessions
List all sessions for the authenticated user.

**Authentication:** Required (JWT token with type='user')

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Sessions retrieved successfully",
  "data": [
    {
      "id": "uuid",
      "userId": "uuid",
      "parkingId": "uuid",
      "reservationId": "uuid",
      "startTime": "2025-12-04 10:00:00",
      "endTime": "2025-12-04 12:00:00",
      "totalAmount": 7.0,
      "status": "completed",
      "createdAt": "2025-12-04 10:00:00"
    }
  ]
}
```

**Status Values:**
- `active` - Session in progress
- `completed` - Session ended normally
- `overstayed` - Session ended with overstay penalty

**Errors:**
- `401` - Authentication required
- `500` - Internal server error

---

### GET /api/sessions/:id
Get session details (user must own the session).

**Authentication:** Required (JWT token with type='user')

**Path Parameters:**
- `:id` - Session UUID

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Session retrieved successfully",
  "data": {
    "id": "uuid",
    "userId": "uuid",
    "parkingId": "uuid",
    "reservationId": "uuid",
    "startTime": "2025-12-04 10:00:00",
    "endTime": "2025-12-04 12:00:00",
    "totalAmount": 7.0,
    "status": "completed",
    "createdAt": "2025-12-04 10:00:00"
  }
}
```

**Ownership Check:**
- Session must belong to the authenticated user
- Error message: "Unauthorized: This is not your session"

**Errors:**
- `401` - Authentication required
- `403` - Not your session (ownership check failed)
- `404` - Session not found
- `500` - Internal server error

---

## Session Lifecycle Flow

```
1. User creates reservation (POST /api/reservations)
   ↓
2. User starts session (POST /api/sessions)
   Status: active
   ↓
3. User parks vehicle
   ↓
4. User ends session (PUT /api/sessions/:id/end)
   Status: completed (or overstayed if late)
   Cost calculated with 15-min increments
```

**Business Rules:**
- Session can only be started during reservation time window
- One active session per user-parking combination
- Overstay detection: compares endTime with reservation endTime
- Overstay penalty: €20 + additional time charged
- Pricing uses SimplePricingCalculator (15-minute increments)
- Same pricing logic as reservations for consistency

---

## cURL Examples - Sessions

### 1. Start a session
```bash
TOKEN="your-jwt-token-here"

curl -X POST http://localhost:8000/api/sessions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "parkingId": "550e8400-e29b-41d4-a716-446655440000"
  }'
```

### 2. End a session
```bash
TOKEN="your-jwt-token-here"
SESSION_ID="session-uuid-here"

curl -X PUT http://localhost:8000/api/sessions/$SESSION_ID/end \
  -H "Authorization: Bearer $TOKEN"
```

### 3. List my sessions
```bash
TOKEN="your-jwt-token-here"

curl -X GET http://localhost:8000/api/sessions \
  -H "Authorization: Bearer $TOKEN"
```

### 4. Get session details
```bash
TOKEN="your-jwt-token-here"
SESSION_ID="session-uuid-here"

curl -X GET http://localhost:8000/api/sessions/$SESSION_ID \
  -H "Authorization: Bearer $TOKEN"
```
