# FE-011 : Parking Search & List

**Status**: ✅ COMPLETED
**Priority**: P1 (High)
**Story Points**: 5pts
**Date**: 2025-12-04
**Dependencies**: FE-010 ✅ (Authentication)

---

## 📋 Résumé

Page publique de recherche et affichage des parkings avec filtres et géolocalisation.

**Features** :
- Liste parkings (cards responsive)
- Search bar (nom/adresse)
- Filtre GPS (distance) avec geolocation
- Tri par distance
- Détails parking (dialog)
- Empty/Loading states
- Integration API (parkingApi.getAll)

---

## 🎯 Composants Créés

### Pages (1 fichier)
1. **Parkings** ([src/pages/Parkings.tsx](../frontend/src/pages/Parkings.tsx))
   - Liste parkings
   - Search + filters
   - Geolocation
   - States management

### Components (2 fichiers)
2. **ParkingCard** ([src/components/ParkingCard.tsx](../frontend/src/components/ParkingCard.tsx))
   - Card parking
   - Badge prix/distance
   - Button "Voir détails"

3. **ParkingDetailsDialog** ([src/components/ParkingDetailsDialog.tsx](../frontend/src/components/ParkingDetailsDialog.tsx))
   - Dialog détails
   - Infos complètes
   - Boutons Maps + Réserver

### shadcn/ui (3 fichiers)
4. **Badge** ([src/components/ui/badge.tsx](../frontend/src/components/ui/badge.tsx))
5. **Skeleton** ([src/components/ui/skeleton.tsx](../frontend/src/components/ui/skeleton.tsx))
6. **Dialog** ([src/components/ui/dialog.tsx](../frontend/src/components/ui/dialog.tsx))

### Utils (1 fichier)
7. **distance** ([src/lib/distance.ts](../frontend/src/lib/distance.ts))
   - calculateDistance (Haversine)
   - formatDistance

---

## 🗺️ Features

### Search
- Recherche par nom parking
- Recherche par adresse
- Temps réel (onChange)
- Badge recherche active

### Geolocation
- Navigator.geolocation API
- Permission demandée
- Calcul distance (Haversine)
- Tri par distance
- Badge position active

### States
- **Loading** : Skeletons (6 cards)
- **Empty** : Message + icon + clear button
- **Error** : Alert destructive
- **Success** : Grid parkings

### Integration API
- `parkingApi.getAll()` : fetch all parkings
- Error handling graceful
- Loading states

---

## 🎨 UI/UX

### Cards
- Hover shadow effect
- Clickable (full card)
- Badge prix (top-right)
- Badge distance (si geo active)
- Icon MapPin, Euro, ParkingCircle

### Dialog
- Radix UI Dialog
- Modal overlay
- Responsive (sm:max-w-[500px])
- Close button (X)
- Footer actions

### Responsive
- Grid : 3 cols (lg), 2 cols (md), 1 col (mobile)
- Search bar full-width mobile
- Dialog adaptatif

---

## 📐 Calcul Distance

### Formule Haversine
```typescript
R = 6371 km (rayon Terre)
dLat = lat2 - lat1 (en radians)
dLon = lon2 - lon1 (en radians)

a = sin²(dLat/2) + cos(lat1) * cos(lat2) * sin²(dLon/2)
c = 2 * atan2(√a, √(1-a))
distance = R * c
```

Précision : 1 décimale (ex: 2.3 km)

---

## 🧪 Tests Manuels

**Scénarios testés** :
- ✅ Page loading (skeleton)
- ✅ Liste parkings affichée
- ✅ Search fonctionnelle (filter real-time)
- ✅ Geolocation (permission + calcul distance)
- ✅ Tri par distance
- ✅ Details dialog (open/close)
- ✅ Bouton "Ouvrir dans Maps" (Google Maps)
- ✅ Bouton "Réserver" (redirect si connecté)
- ✅ Empty state (aucun parking)
- ✅ Empty state (recherche sans résultats)
- ✅ Responsive (mobile/desktop)

---

## 📂 Fichiers

### Créés (7 fichiers)
- `src/pages/Parkings.tsx`
- `src/components/ParkingCard.tsx`
- `src/components/ParkingDetailsDialog.tsx`
- `src/components/ui/badge.tsx`
- `src/components/ui/skeleton.tsx`
- `src/components/ui/dialog.tsx`
- `src/lib/distance.ts`

### Modifiés (2 fichiers)
- `src/App.tsx` (route /parkings)
- `package.json` (@radix-ui/react-dialog)

**Total** : ~650 lignes

---

## 🔗 Tickets Liés

- **Depends on**: FE-010 ✅ (Auth - reserve button)
- **Blocks**: FE-012 (Reservation Flow)
- **Enables**: Users can search & view parkings

---

## 🎯 Prochains Tickets

### FE-012 : Reservation Flow
- Page `/parkings/:id/reserve`
- Formulaire réservation (date/time)
- Calcul prix
- Intégration reservationApi

---

**Complété par**: Claude
**Validé par**: En attente
