import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MapPin, Euro, ParkingCircle } from 'lucide-react';
import type { Parking } from '@/types';

interface ParkingCardProps {
  parking: Parking;
  distance?: number;
  onViewDetails: (parking: Parking) => void;
}

export const ParkingCard = ({ parking, distance, onViewDetails }: ParkingCardProps) => {
  return (
    <Card className="hover:shadow-lg transition-shadow cursor-pointer" onClick={() => onViewDetails(parking)}>
      <CardHeader>
        <div className="flex items-start justify-between">
          <CardTitle className="text-xl">{parking.name}</CardTitle>
          <Badge variant="secondary">
            <Euro className="h-3 w-3 mr-1" />
            {parking.hourlyRate.toFixed(2)}/h
          </Badge>
        </div>
      </CardHeader>

      <CardContent className="space-y-3">
        <div className="flex items-start gap-2 text-sm text-muted-foreground">
          <MapPin className="h-4 w-4 mt-0.5 flex-shrink-0" />
          <span>{parking.address}</span>
        </div>

        {distance !== undefined && (
          <div className="flex items-center gap-2">
            <Badge variant="outline">
              📍 {distance < 1 ? `${Math.round(distance * 1000)}m` : `${distance.toFixed(1)}km`}
            </Badge>
          </div>
        )}

        <div className="flex items-center gap-2 text-sm">
          <ParkingCircle className="h-4 w-4 text-primary" />
          <span className="font-medium">
            {parking.availableSpots} / {parking.totalSpots} places disponibles
          </span>
        </div>

        {/* Low availability warning */}
        {parking.availableSpots <= 5 && parking.availableSpots > 0 && (
          <Badge variant="destructive" className="text-xs">
            ⚠️ Seulement {parking.availableSpots} places restantes !
          </Badge>
        )}

        {/* Full badge */}
        {parking.availableSpots === 0 && (
          <Badge variant="destructive" className="text-xs">
            🚫 Complet
          </Badge>
        )}
      </CardContent>

      <CardFooter>
        <Button className="w-full" onClick={(e) => {
          e.stopPropagation();
          onViewDetails(parking);
        }}>
          Voir les détails
        </Button>
      </CardFooter>
    </Card>
  );
};
