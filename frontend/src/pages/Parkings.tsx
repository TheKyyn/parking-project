import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ParkingCard } from '@/components/ParkingCard';
import { ParkingDetailsDialog } from '@/components/ParkingDetailsDialog';
import { parkingApi } from '@/lib/api';
import { calculateDistance } from '@/lib/distance';
import { useAuth } from '@/contexts/AuthContext';
import type { Parking } from '@/types';
import { Search, MapPin, Navigation2, AlertCircle } from 'lucide-react';

export const Parkings = () => {
  const [parkings, setParkings] = useState<Parking[]>([]);
  const [filteredParkings, setFilteredParkings] = useState<Parking[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedParking, setSelectedParking] = useState<Parking | null>(null);
  const [userLocation, setUserLocation] = useState<{ latitude: number; longitude: number } | null>(null);
  const [isGettingLocation, setIsGettingLocation] = useState(false);

  const { isAuthenticated } = useAuth();
  const navigate = useNavigate();

  // Fetch parkings on mount
  useEffect(() => {
    fetchParkings();
  }, []);

  // Filter parkings when search query changes
  useEffect(() => {
    filterParkings();
  }, [searchQuery, parkings, userLocation]);

  const fetchParkings = async () => {
    setIsLoading(true);
    setError('');

    try {
      const response = await parkingApi.getAll();

      if (response.success && response.data) {
        setParkings(response.data);
      }
    } catch (err: any) {
      console.error('Error fetching parkings:', err);
      setError('Impossible de charger les parkings. Veuillez réessayer.');
    } finally {
      setIsLoading(false);
    }
  };

  const filterParkings = () => {
    let filtered = [...parkings];

    // Filter by search query
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (p) =>
          p.name.toLowerCase().includes(query) ||
          p.address.toLowerCase().includes(query)
      );
    }

    // Sort by distance if user location is available
    if (userLocation) {
      filtered = filtered.map((parking) => ({
        ...parking,
        distance: calculateDistance(
          userLocation.latitude,
          userLocation.longitude,
          parking.location.latitude,
          parking.location.longitude
        ),
      })).sort((a: any, b: any) => (a.distance || 0) - (b.distance || 0));
    }

    setFilteredParkings(filtered);
  };

  const getUserLocation = () => {
    setIsGettingLocation(true);

    if (!navigator.geolocation) {
      setError('La géolocalisation n\'est pas supportée par votre navigateur.');
      setIsGettingLocation(false);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        setUserLocation({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        });
        setIsGettingLocation(false);
      },
      (error) => {
        console.error('Geolocation error:', error);
        setError('Impossible d\'obtenir votre position. Veuillez autoriser la géolocalisation.');
        setIsGettingLocation(false);
      }
    );
  };

  const handleViewDetails = (parking: Parking) => {
    setSelectedParking(parking);
  };

  const handleReserve = (parking: Parking) => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }

    // Navigate to reservation page (FE-012)
    navigate(`/parkings/${parking.id}/reserve`);
  };

  const getParkingDistance = (parking: Parking | any) => {
    if (!userLocation) return undefined;

    return calculateDistance(
      userLocation.latitude,
      userLocation.longitude,
      parking.location.latitude,
      parking.location.longitude
    );
  };

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-4xl font-bold mb-2">Trouver un parking</h1>
        <p className="text-muted-foreground">
          Recherchez et réservez un parking près de vous
        </p>
      </div>

      {/* Search & Filters */}
      <div className="mb-6 space-y-4">
        <div className="flex gap-2">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Rechercher par nom ou adresse..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>

          <Button
            variant={userLocation ? "default" : "outline"}
            onClick={getUserLocation}
            disabled={isGettingLocation}
          >
            {isGettingLocation ? (
              <>
                <Navigation2 className="mr-2 h-4 w-4 animate-spin" />
                Localisation...
              </>
            ) : (
              <>
                <MapPin className="mr-2 h-4 w-4" />
                {userLocation ? 'Position activée' : 'Me localiser'}
              </>
            )}
          </Button>
        </div>

        {/* Active filters */}
        <div className="flex flex-wrap gap-2">
          {searchQuery && (
            <Badge variant="secondary">
              Recherche: {searchQuery}
              <button
                onClick={() => setSearchQuery('')}
                className="ml-2 hover:text-foreground"
              >
                ×
              </button>
            </Badge>
          )}
          {userLocation && (
            <Badge variant="secondary">
              <MapPin className="h-3 w-3 mr-1" />
              Triés par distance
              <button
                onClick={() => setUserLocation(null)}
                className="ml-2 hover:text-foreground"
              >
                ×
              </button>
            </Badge>
          )}
        </div>
      </div>

      {/* Error State */}
      {error && (
        <Alert variant="destructive" className="mb-6">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Loading State */}
      {isLoading && (
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="space-y-4">
              <Skeleton className="h-48 w-full" />
            </div>
          ))}
        </div>
      )}

      {/* Empty State */}
      {!isLoading && filteredParkings.length === 0 && (
        <div className="text-center py-12">
          <div className="mx-auto w-24 h-24 bg-muted rounded-full flex items-center justify-center mb-4">
            <Search className="h-12 w-12 text-muted-foreground" />
          </div>
          <h3 className="text-xl font-semibold mb-2">Aucun parking trouvé</h3>
          <p className="text-muted-foreground mb-4">
            {searchQuery
              ? 'Essayez de modifier votre recherche'
              : 'Aucun parking disponible pour le moment'}
          </p>
          {searchQuery && (
            <Button variant="outline" onClick={() => setSearchQuery('')}>
              Effacer la recherche
            </Button>
          )}
        </div>
      )}

      {/* Parkings Grid */}
      {!isLoading && filteredParkings.length > 0 && (
        <>
          <div className="mb-4 text-sm text-muted-foreground">
            {filteredParkings.length} parking{filteredParkings.length > 1 ? 's' : ''} trouvé{filteredParkings.length > 1 ? 's' : ''}
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filteredParkings.map((parking: any) => (
              <ParkingCard
                key={parking.id}
                parking={parking}
                distance={parking.distance}
                onViewDetails={handleViewDetails}
              />
            ))}
          </div>
        </>
      )}

      {/* Parking Details Dialog */}
      <ParkingDetailsDialog
        parking={selectedParking}
        open={!!selectedParking}
        onClose={() => setSelectedParking(null)}
        onReserve={isAuthenticated ? handleReserve : undefined}
        distance={selectedParking ? getParkingDistance(selectedParking) : undefined}
      />
    </div>
  );
};
