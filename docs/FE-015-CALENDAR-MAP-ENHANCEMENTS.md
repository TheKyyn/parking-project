# FE-015: Calendar & Map Enhancements

**Status**: ✅ COMPLETED
**Priority**: P1 (High)
**Story Points**: 8pts
**Date**: 2025-12-04

---

## 📋 Features

### 1. Better Booking Calendar
- Replaced `react-day-picker` with `react-calendar` for improved UX
- Better styling and integration with shadcn theme
- French locale support
- Min date validation (prevents past dates)
- Weekend highlighting
- Custom CSS for primary/accent colors

### 2. Owner Calendar View
- Monthly/Weekly/Daily calendar views using `react-big-calendar`
- Displays all reservations for owner's parkings
- Filter by parking dropdown
- Color-coded events by status:
  - **Pending**: Orange (#f59e0b)
  - **Confirmed**: Green (#10b981)
  - **Active**: Blue (#3b82f6)
  - **Completed**: Gray (#6b7280)
  - **Cancelled**: Red (#ef4444)
- Click event to open details modal
- Shows user info, parking details, time range, and amount
- French localization

### 3. User Calendar View
- Monthly/Weekly calendar views
- Displays all user's reservations
- Color-coded by status
- Click event for reservation details
- Simpler than owner calendar (no parking filter needed)
- French localization

### 4. OpenStreetMap Autocomplete
- Address search using Nominatim API (free, no API key required)
- Autocomplete with debounced search (300ms)
- Results dropdown with 5 suggestions
- Auto-fills address, latitude, and longitude
- Optional map preview with marker
- Integrated in Owner Create/Edit Parking forms
- Leaflet marker icons fixed via CDN

---

## 📦 Dependencies Added

```json
{
  "react-calendar": "^6.0.0",
  "react-big-calendar": "^1.19.4",
  "react-leaflet": "^5.0.0",
  "leaflet": "^1.9.4",
  "@types/leaflet": "^1.9.21"
}
```

---

## 🎨 Frontend Changes

### New Components Created (3)

1. **[OwnerCalendar.tsx](../frontend/src/components/OwnerCalendar.tsx)** (~240 lines)
   - Full calendar view for owner
   - Filter by parking
   - Event details dialog
   - Status badges

2. **[UserCalendar.tsx](../frontend/src/components/UserCalendar.tsx)** (~160 lines)
   - Simplified calendar for users
   - Monthly/Weekly views
   - Event details dialog

3. **[AddressAutocomplete.tsx](../frontend/src/components/AddressAutocomplete.tsx)** (~160 lines)
   - Nominatim API integration
   - Debounced search
   - Map preview with Leaflet
   - Marker icon fix

### Modified Files (7)

1. **[Reserve.tsx](../frontend/src/pages/Reserve.tsx)**
   - Replaced Calendar component
   - Updated imports (react-calendar)
   - Removed Popover/cn dependencies

2. **[OwnerDashboard.tsx](../frontend/src/pages/OwnerDashboard.tsx)**
   - Integrated OwnerCalendar component
   - Replaced address fields with AddressAutocomplete
   - Hidden lat/lon fields (auto-filled)
   - Map preview in Create/Edit dialogs

3. **[UserDashboard.tsx](../frontend/src/pages/UserDashboard.tsx)**
   - Integrated UserCalendar component
   - Shows below reservations list

4. **[api.ts](../frontend/src/lib/api.ts)**
   - Added `reservationApi.getByOwner()` method

5. **[types/index.ts](../frontend/src/types/index.ts)**
   - Added `user` field to Reservation type

6. **[index.css](../frontend/src/index.css)**
   - Added custom CSS for `react-calendar`
   - Added custom CSS for `react-big-calendar`
   - Integrated with shadcn theme variables

7. **[package.json](../frontend/package.json)**
   - Added 4 new dependencies + 1 devDependency

---

## 🔧 Backend Changes

### New Endpoint

**GET /api/owner/reservations**
- Owner authentication required (OwnerAuthMiddleware)
- Returns all reservations for owner's parkings
- Joins with user and parking data
- Response format:
```json
{
  "success": true,
  "message": "Owner reservations retrieved successfully",
  "data": [
    {
      "id": "...",
      "userId": "...",
      "parkingId": "...",
      "user": { "id": "...", "firstName": "...", "lastName": "...", "email": "..." },
      "parking": { "id": "...", "name": "...", "address": "...", "hourlyRate": 5.50 },
      "startTime": "2025-12-04 10:00:00",
      "endTime": "2025-12-04 12:00:00",
      "totalAmount": 11.00,
      "status": "confirmed",
      "createdAt": "..."
    }
  ]
}
```

### Modified Files (3)

1. **[ReservationController.php](../src/Infrastructure/Http/Controller/ReservationController.php)**
   - Added `ownerIndex()` method
   - Injected `UserRepositoryInterface` in constructor
   - Fetches all parkings for owner
   - Fetches all reservations for those parkings
   - Joins user and parking data

2. **[MySQLReservationRepository.php](../src/Infrastructure/Repository/MySQL/MySQLReservationRepository.php)**
   - Added `findByParkingIds()` method
   - Uses IN clause with placeholders
   - Returns array of reservations ordered by start_time DESC

3. **[routes.php](../src/Infrastructure/Http/routes.php)**
   - Updated ReservationController instantiation (added userRepository)
   - Added route: `GET /api/owner/reservations` with OwnerAuthMiddleware

---

## 🧪 Testing Checklist

### Frontend

- ✅ Better calendar displays in Reserve page
- ✅ Calendar respects min date (no past dates)
- ✅ French locale works correctly
- ✅ Owner calendar shows all reservations
- ✅ Filter by parking works
- ✅ Click event opens modal with correct data
- ✅ Status colors display correctly
- ✅ User calendar displays reservations
- ✅ Switch month/week views works
- ✅ Address autocomplete search works
- ✅ Lat/lon auto-filled correctly
- ✅ Map preview displays marker
- ✅ Create parking with autocomplete works
- ✅ Edit parking with autocomplete works

### Backend

- ✅ `/api/owner/reservations` returns owner's reservations
- ✅ Owner authentication required
- ✅ Data includes user and parking info
- ✅ Empty parkings returns empty array
- ✅ Handles errors gracefully

---

## 📸 Screenshots

(Optional - Add screenshots of the calendars and map here)

---

## 🔄 Migration Notes

- No database migrations required
- All changes are additive (new components, new endpoint)
- Backward compatible with existing code
- react-day-picker still installed (can be removed in future)

---

## 🚀 Deployment

1. Frontend: `npm install` to install new dependencies
2. Backend: No changes needed (PHP doesn't need package installation)
3. Test both user and owner dashboards
4. Test booking calendar in Reserve page
5. Test address autocomplete in Owner dashboard

---

## 📚 Resources

- [react-calendar docs](https://www.npmjs.com/package/react-calendar)
- [react-big-calendar docs](https://www.npmjs.com/package/react-big-calendar)
- [react-leaflet docs](https://react-leaflet.js.org/)
- [Nominatim API docs](https://nominatim.org/release-docs/latest/api/Search/)

---

**Completed by**: Claude Code Assistant
**Date**: 2025-12-04
