# FE-012 : Reservation Flow

**Status**: ✅ COMPLETED
**Priority**: P1 (High)
**Story Points**: 4pts
**Date**: 2025-12-04
**Dependencies**: FE-011 ✅ (Parking Search)

---

## 📋 Résumé

Flow complet de réservation d'un parking avec sélection de date/heure et calcul de prix en temps réel.

**Features** :
- Page réservation protégée (user only)
- Date picker avec calendrier
- Time pickers (15min intervals)
- Quick duration buttons (+1h, +2h, +3h, +4h)
- Real-time price calculation
- Form validation (start > now, end > start)
- Integration reservationApi.create()
- Success redirect avec message
- Error handling

---

## 🎯 Composants Créés

### Pages (1 fichier)
1. **Reserve** ([src/pages/Reserve.tsx](../frontend/src/pages/Reserve.tsx))
   - Formulaire réservation
   - Date/time pickers
   - Price calculator
   - Form validation
   - API integration

### shadcn/ui Components (3 fichiers)
2. **Calendar** ([src/components/ui/calendar.tsx](../frontend/src/components/ui/calendar.tsx))
   - react-day-picker wrapper
   - Custom Tailwind styling
   - Past dates disabled

3. **Popover** ([src/components/ui/popover.tsx](../frontend/src/components/ui/popover.tsx))
   - Date picker trigger wrapper
   - Radix UI Popover

4. **Select** ([src/components/ui/select.tsx](../frontend/src/components/ui/select.tsx))
   - Time picker dropdown
   - Radix UI Select
   - Tous les sub-components

---

## 🗺️ Features

### Date Selection
- Calendar component (react-day-picker)
- Popover trigger avec icône
- Past dates disabled
- French locale (date-fns/locale/fr)
- Format: "PPP" (ex: 4 décembre 2025)

### Time Selection
- 2 Selects : start time, end time
- 15-minute intervals (00:00 to 23:45)
- 96 time options total
- Clock icon dans chaque option
- Validation: end > start

### Quick Duration
- 4 buttons: +1h, +2h, +3h, +4h
- Disabled si startTime non sélectionné
- Auto-calcul endTime depuis startTime
- Protection contre midnight overflow

### Price Calculation
- Real-time calculation (useEffect)
- Formula: quarters * (hourlyRate / 4)
- quarters = Math.ceil(hours * 4)
- Display avec 2 décimales + € symbol
- Card preview avec muted background

### Form Validation
- Date required
- Start time required
- End time required
- Start must be in future
- End must be after start
- Error messages en français
- Alert destructive pour erreurs

---

## 📐 Price Calculation Logic

### Formula
```typescript
// Parse datetimes
const startDateTime = parse(date + startTime, format, new Date())
const endDateTime = parse(date + endTime, format, new Date())

// Duration in hours
const durationMs = endDateTime - startDateTime
const durationHours = durationMs / (1000 * 60 * 60)

// Quarters (billing unit)
const quarters = Math.ceil(durationHours * 4)

// Price
const price = quarters * (hourlyRate / 4)
```

### Examples
- 1h00 → 4 quarters → 4 * (10€ / 4) = 10.00€
- 1h15 → 5 quarters → 5 * (10€ / 4) = 12.50€
- 2h30 → 10 quarters → 10 * (10€ / 4) = 25.00€
- 0h45 → 3 quarters → 3 * (10€ / 4) = 7.50€

---

## 🧪 User Flow

1. User clicks "Réserver" sur parking card (FE-011)
2. Navigate to `/parkings/:id/reserve`
3. Protected route check (redirect /login si non auth)
4. Fetch parking details (loading skeleton)
5. Display parking info card (name, address, price, spots)
6. User selects date (calendar popover)
7. User selects start time (select dropdown)
8. User selects end time (or uses quick duration buttons)
9. Price calculated in real-time
10. User clicks "Confirmer la réservation"
11. Form validation
12. POST /api/reservations
13. Success → navigate('/user/dashboard', { state: { message } })
14. Error → display alert

---

## 🎨 UI/UX

### Layout
- Max-width: 2xl (max-w-2xl)
- Centered (mx-auto)
- Responsive spacing
- 2 cards: Parking Info + Reservation Form

### Parking Info Card
- Icon: ParkingCircle (header)
- Title: Parking name
- Description: Address avec MapPin icon
- Content: Price + Total spots

### Reservation Form Card
- Header: Title + Description
- Error alert (if error)
- Date picker (Popover + Calendar)
- Time pickers grid (2 cols md)
- Quick duration buttons grid (4 cols)
- Price preview card (muted bg)
- Footer: Cancel + Submit buttons

### States
- **Loading**: Skeleton placeholders
- **Error parking not found**: Alert + Back button
- **Form error**: Destructive alert
- **Submitting**: Button disabled + "Réservation..." text
- **Success**: Redirect (handled by navigate)

---

## 📂 Fichiers

### Créés (4 fichiers)
- `src/pages/Reserve.tsx` (~450 lignes)
- `src/components/ui/calendar.tsx` (~68 lignes)
- `src/components/ui/popover.tsx` (~32 lignes)
- `src/components/ui/select.tsx` (~165 lignes)

### Modifiés (2 fichiers)
- `src/App.tsx` (route /parkings/:id/reserve)
- `package.json` (dependencies: react-day-picker, @radix-ui/react-popover, @radix-ui/react-select)

**Total nouvelles lignes** : ~715 lignes

---

## 🔌 API Integration

### parkingApi.getById(id)
- GET /api/parkings/:id
- Returns: Parking object
- Used: Fetch parking details on mount

### reservationApi.create(data)
- POST /api/reservations
- Body:
  ```json
  {
    "parkingId": "string",
    "startTime": "ISO8601",
    "endTime": "ISO8601"
  }
  ```
- Returns: Reservation object
- Used: Create reservation on form submit

---

## 📦 Dependencies

### Installed (3 packages)
- `react-day-picker` (^9.11.3) - Calendar component
- `@radix-ui/react-popover` (^1.1.15) - Date picker trigger
- `@radix-ui/react-select` (^2.2.6) - Time picker dropdown

### Already installed
- `date-fns` (^4.1.0) - Date manipulation ✅
- `lucide-react` (^0.555.0) - Icons ✅

---

## 🧪 Tests Manuels

**Scénarios testés** :
- ✅ Page loading (skeleton)
- ✅ Parking details fetched et affichés
- ✅ Date picker opens et allows selection
- ✅ Past dates disabled
- ✅ Time selects show 96 options (00:00 - 23:45)
- ✅ Quick duration buttons calculate end time
- ✅ Price updates in real-time
- ✅ Validation: date required
- ✅ Validation: times required
- ✅ Validation: start must be future
- ✅ Validation: end must be after start
- ✅ Form submission creates reservation
- ✅ Success redirects to /user/dashboard
- ✅ Error shows alert message
- ✅ Cancel button returns to /parkings
- ✅ Protected route (user only)
- ✅ Responsive design (mobile/desktop)

---

## 🔗 Tickets Liés

- **Depends on**: FE-011 ✅ (Parking Search - navigate from parkings page)
- **Blocks**: FE-013 (User Dashboard - list reservations)
- **Enables**: Users can reserve parkings with date/time

---

## 🎯 Prochains Tickets

### FE-013 : User Dashboard
- List user reservations
- Display reservation details
- Cancel reservation action
- Active/Completed/Cancelled states
- Upcoming reservations highlighted

---

## 📝 Notes Techniques

### date-fns Functions Used
- `format()` - Format dates for display
- `parse()` - Parse date strings to Date objects
- `addHours()` - Quick duration calculation
- `isAfter()` - Validate end > start
- `isBefore()` - Validate start > now, disable past dates
- `startOfDay()` - Disable dates before today
- `fr` locale - French date formatting

### Time Generation
```typescript
const generateTimeOptions = (): string[] => {
  const options: string[] = [];
  for (let hour = 0; hour < 24; hour++) {
    for (let minute = 0; minute < 60; minute += 15) {
      const time = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
      options.push(time);
    }
  }
  return options;
};
```

### useEffect Dependencies
- Fetch parking: `[id]`
- Calculate price: `[date, startTime, endTime, parking]`

---

**Complété par**: Claude
**Validé par**: En attente
