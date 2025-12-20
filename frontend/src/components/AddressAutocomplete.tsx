import { useState, useEffect, useRef } from 'react';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { Loader2, MapPin } from 'lucide-react';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

// Fix Leaflet default marker icon
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

interface NominatimResult {
  place_id: number;
  display_name: string;
  lat: string;
  lon: string;
  address: {
    road?: string;
    house_number?: string;
    postcode?: string;
    city?: string;
    country?: string;
  };
}

interface AddressAutocompleteProps {
  value: string;
  onChange: (address: string, lat: number, lon: number) => void;
  placeholder?: string;
  showMap?: boolean;
}

export const AddressAutocomplete = ({
  value,
  onChange,
  placeholder = 'Rechercher une adresse...',
  showMap = false,
}: AddressAutocompleteProps) => {
  const [query, setQuery] = useState(value);
  const [results, setResults] = useState<NominatimResult[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const [selectedLocation, setSelectedLocation] = useState<{ lat: number; lon: number } | null>(
    null
  );
  const debounceTimeout = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => {
    setQuery(value);
  }, [value]);

  const searchAddress = async (searchQuery: string) => {
    if (searchQuery.length < 3) {
      setResults([]);
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(
          searchQuery
        )}&addressdetails=1&limit=5`,
        {
          headers: {
            'Accept-Language': 'fr',
          },
        }
      );
      const data: NominatimResult[] = await response.json();
      setResults(data);
      setShowResults(true);
    } catch (error) {
      console.error('Error searching address:', error);
      setResults([]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newQuery = e.target.value;
    setQuery(newQuery);

    // Debounce search
    if (debounceTimeout.current) {
      clearTimeout(debounceTimeout.current);
    }

    debounceTimeout.current = setTimeout(() => {
      searchAddress(newQuery);
    }, 300);
  };

  const handleSelectResult = (result: NominatimResult) => {
    const lat = parseFloat(result.lat);
    const lon = parseFloat(result.lon);

    setQuery(result.display_name);
    setShowResults(false);
    setSelectedLocation({ lat, lon });
    onChange(result.display_name, lat, lon);
  };

  return (
    <div className="space-y-4">
      <div className="relative">
        <Input
          value={query}
          onChange={handleInputChange}
          placeholder={placeholder}
          onFocus={() => results.length > 0 && setShowResults(true)}
        />
        {isLoading && (
          <Loader2 className="absolute right-3 top-3 h-4 w-4 animate-spin text-muted-foreground" />
        )}

        {showResults && results.length > 0 && (
          <Card className="absolute z-50 w-full mt-1 max-h-[300px] overflow-auto">
            <div className="p-2">
              {results.map((result) => (
                <button
                  key={result.place_id}
                  onClick={() => handleSelectResult(result)}
                  className="w-full text-left px-3 py-2 hover:bg-accent rounded-md transition-colors flex items-start gap-2"
                >
                  <MapPin className="h-4 w-4 mt-0.5 text-muted-foreground flex-shrink-0" />
                  <span className="text-sm">{result.display_name}</span>
                </button>
              ))}
            </div>
          </Card>
        )}
      </div>

      {showMap && selectedLocation && (
        <div className="h-[300px] rounded-lg overflow-hidden border">
          <MapContainer
            center={[selectedLocation.lat, selectedLocation.lon]}
            zoom={15}
            style={{ height: '100%', width: '100%' }}
          >
            <TileLayer
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Marker position={[selectedLocation.lat, selectedLocation.lon]}>
              <Popup>Emplacement sélectionné</Popup>
            </Marker>
          </MapContainer>
        </div>
      )}
    </div>
  );
};
