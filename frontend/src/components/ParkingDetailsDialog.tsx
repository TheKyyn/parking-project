import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { MapPin, Euro, ParkingCircle, Navigation } from 'lucide-react';
import type { Parking } from '@/types';

interface ParkingDetailsDialogProps {
  parking: Parking | null;
  open: boolean;
  onClose: () => void;
  onReserve?: (parking: Parking) => void;
  distance?: number;
}

export const ParkingDetailsDialog = ({
  parking,
  open,
  onClose,
  onReserve,
  distance
}: ParkingDetailsDialogProps) => {
  if (!parking) return null;

  const openInMaps = () => {
    const url = `https://www.google.com/maps/search/?api=1&query=${parking.location.latitude},${parking.location.longitude}`;
    window.open(url, '_blank');
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="text-2xl">{parking.name}</DialogTitle>
          <DialogDescription>
            Informations détaillées sur ce parking
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Address */}
          <div className="flex items-start gap-3">
            <MapPin className="h-5 w-5 text-primary mt-0.5" />
            <div>
              <p className="font-medium">Adresse</p>
              <p className="text-sm text-muted-foreground">{parking.address}</p>
            </div>
          </div>

          {/* Distance */}
          {distance !== undefined && (
            <div className="flex items-center gap-3">
              <Navigation className="h-5 w-5 text-primary" />
              <div>
                <p className="font-medium">Distance</p>
                <p className="text-sm text-muted-foreground">
                  {distance < 1 ? `${Math.round(distance * 1000)} mètres` : `${distance.toFixed(1)} kilomètres`}
                </p>
              </div>
            </div>
          )}

          {/* Price */}
          <div className="flex items-center gap-3">
            <Euro className="h-5 w-5 text-primary" />
            <div>
              <p className="font-medium">Tarif horaire</p>
              <p className="text-sm text-muted-foreground">
                {parking.hourlyRate.toFixed(2)}€ / heure
              </p>
              <p className="text-xs text-muted-foreground mt-1">
                Facturation par tranches de 15 minutes
              </p>
            </div>
          </div>

          {/* Spots */}
          <div className="flex items-center gap-3">
            <ParkingCircle className="h-5 w-5 text-primary" />
            <div>
              <p className="font-medium">Capacité</p>
              <p className="text-sm text-muted-foreground">
                {parking.totalSpots} places disponibles
              </p>
            </div>
          </div>

          {/* Coordinates */}
          <div className="bg-muted p-3 rounded-lg">
            <p className="text-xs text-muted-foreground mb-1">Coordonnées GPS</p>
            <p className="text-sm font-mono">
              {parking.location.latitude.toFixed(6)}, {parking.location.longitude.toFixed(6)}
            </p>
          </div>
        </div>

        <DialogFooter className="flex gap-2 sm:gap-0">
          <Button variant="outline" onClick={openInMaps} className="w-full sm:w-auto">
            <Navigation className="h-4 w-4 mr-2" />
            Ouvrir dans Maps
          </Button>
          {onReserve && (
            <Button onClick={() => onReserve(parking)} className="w-full sm:w-auto">
              Réserver maintenant
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
