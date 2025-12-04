import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { format, parse, addHours, isAfter, isBefore } from 'date-fns';
import Calendar from 'react-calendar';
import 'react-calendar/dist/Calendar.css';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { parkingApi, reservationApi } from '@/lib/api';
import type { Parking } from '@/types';
import { Clock, Euro, MapPin, AlertCircle, ParkingCircle } from 'lucide-react';

// Generate time options (00:00 to 23:45 with 15min intervals)
const generateTimeOptions = (): string[] => {
  const options: string[] = [];
  for (let hour = 0; hour < 24; hour++) {
    for (let minute = 0; minute < 60; minute += 15) {
      const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
      options.push(time);
    }
  }
  return options;
};

const TIME_OPTIONS = generateTimeOptions();

export const Reserve = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  // State
  const [parking, setParking] = useState<Parking | null>(null);
  const [isLoadingParking, setIsLoadingParking] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [date, setDate] = useState<Date | undefined>(undefined);
  const [startTime, setStartTime] = useState<string>('');
  const [endTime, setEndTime] = useState<string>('');
  const [error, setError] = useState('');
  const [estimatedPrice, setEstimatedPrice] = useState(0);

  // Fetch parking details
  useEffect(() => {
    if (id) {
      fetchParking(id);
    }
  }, [id]);

  // Calculate price when date/times change
  useEffect(() => {
    if (date && startTime && endTime && parking) {
      calculatePrice();
    }
  }, [date, startTime, endTime, parking]);

  const fetchParking = async (parkingId: string) => {
    setIsLoadingParking(true);
    setError('');

    try {
      const response = await parkingApi.getById(parkingId);

      if (response.success && response.data) {
        setParking(response.data);
      } else {
        setError('Parking introuvable');
      }
    } catch (err: any) {
      console.error('Error fetching parking:', err);
      setError('Impossible de charger les informations du parking');
    } finally {
      setIsLoadingParking(false);
    }
  };

  const calculatePrice = () => {
    if (!date || !startTime || !endTime || !parking) return;

    try {
      // Parse start and end datetimes
      const startDateTime = parse(
        `${format(date, 'yyyy-MM-dd')} ${startTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      );
      const endDateTime = parse(
        `${format(date, 'yyyy-MM-dd')} ${endTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      );

      // Calculate duration in hours
      const durationMs = endDateTime.getTime() - startDateTime.getTime();
      const durationHours = durationMs / (1000 * 60 * 60);

      // Calculate quarters (round up)
      const quarters = Math.ceil(durationHours * 4);

      // Calculate price (quarters * hourlyRate / 4)
      const price = quarters * (parking.hourlyRate / 4);

      setEstimatedPrice(price);
    } catch (err) {
      console.error('Error calculating price:', err);
      setEstimatedPrice(0);
    }
  };

  const setQuickDuration = (hours: number) => {
    if (!startTime) {
      setError('Veuillez d\'abord sélectionner une heure de début');
      return;
    }

    try {
      // Parse start time
      const startDateTime = parse(startTime, 'HH:mm', new Date());

      // Add hours
      const endDateTime = addHours(startDateTime, hours);

      // Format end time
      const newEndTime = format(endDateTime, 'HH:mm');

      // Check if end time is valid (not crossing midnight)
      if (isBefore(endDateTime, startDateTime)) {
        setError('La durée dépasse minuit. Veuillez sélectionner manuellement l\'heure de fin.');
        return;
      }

      setEndTime(newEndTime);
      setError('');
    } catch (err) {
      console.error('Error setting quick duration:', err);
      setError('Erreur lors du calcul de la durée');
    }
  };

  const validateForm = (): boolean => {
    // Check all fields
    if (!date) {
      setError('Veuillez sélectionner une date');
      return false;
    }

    if (!startTime) {
      setError('Veuillez sélectionner une heure de début');
      return false;
    }

    if (!endTime) {
      setError('Veuillez sélectionner une heure de fin');
      return false;
    }

    try {
      // Parse datetimes
      const now = new Date();
      const startDateTime = parse(
        `${format(date, 'yyyy-MM-dd')} ${startTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      );
      const endDateTime = parse(
        `${format(date, 'yyyy-MM-dd')} ${endTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      );

      // Validate: start must be in future
      if (isBefore(startDateTime, now)) {
        setError('L\'heure de début doit être dans le futur');
        return false;
      }

      // Validate: end must be after start
      if (!isAfter(endDateTime, startDateTime)) {
        setError('L\'heure de fin doit être après l\'heure de début');
        return false;
      }

      setError('');
      return true;
    } catch (err) {
      console.error('Validation error:', err);
      setError('Erreur de validation des dates');
      return false;
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm() || !parking || !id) return;

    setIsSubmitting(true);
    setError('');

    try {
      // Build ISO datetimes
      const startDateTime = parse(
        `${format(date!, 'yyyy-MM-dd')} ${startTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      ).toISOString();

      const endDateTime = parse(
        `${format(date!, 'yyyy-MM-dd')} ${endTime}`,
        'yyyy-MM-dd HH:mm',
        new Date()
      ).toISOString();

      // Create reservation
      const response = await reservationApi.create({
        parkingId: id,
        startTime: startDateTime,
        endTime: endDateTime,
      });

      if (response.success) {
        // Success - redirect to dashboard with message
        navigate('/user/dashboard', {
          state: {
            message: `Réservation confirmée pour ${parking.name}`,
          },
        });
      } else {
        setError(response.message || 'Erreur lors de la réservation');
      }
    } catch (err: any) {
      console.error('Error creating reservation:', err);
      setError(
        err.response?.data?.message ||
        'Impossible de créer la réservation. Veuillez réessayer.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  // Loading state
  if (isLoadingParking) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto space-y-6">
          <div className="h-8 bg-muted animate-pulse rounded" />
          <div className="h-48 bg-muted animate-pulse rounded" />
          <div className="h-96 bg-muted animate-pulse rounded" />
        </div>
      </div>
    );
  }

  // Error state (parking not found)
  if (!parking) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto">
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {error || 'Parking introuvable'}
            </AlertDescription>
          </Alert>
          <div className="mt-6">
            <Button onClick={() => navigate('/parkings')}>
              Retour aux parkings
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="max-w-2xl mx-auto space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-4xl font-bold mb-2">Réserver un parking</h1>
          <p className="text-muted-foreground">
            Sélectionnez la date et l'heure de votre réservation
          </p>
        </div>

        {/* Parking Info Card */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ParkingCircle className="h-5 w-5" />
              {parking.name}
            </CardTitle>
            <CardDescription className="flex items-center gap-2">
              <MapPin className="h-4 w-4" />
              {parking.address}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-6 text-sm">
              <div className="flex items-center gap-2">
                <Euro className="h-4 w-4 text-muted-foreground" />
                <span className="font-medium">{parking.hourlyRate.toFixed(2)}€/h</span>
              </div>
              <div className="flex items-center gap-2">
                <ParkingCircle className="h-4 w-4 text-muted-foreground" />
                <span>{parking.totalSpots} places</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Reservation Form */}
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Détails de la réservation</CardTitle>
              <CardDescription>
                Choisissez la date et l'heure de début et de fin
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Error Alert */}
              {error && (
                <Alert variant="destructive">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              {/* Date Picker */}
              <div className="space-y-2">
                <Label>Date</Label>
                <div className="border rounded-lg overflow-hidden">
                  <Calendar
                    onChange={(value) => setDate(value as Date)}
                    value={date}
                    minDate={new Date()}
                    locale="fr-FR"
                    className="w-full"
                  />
                </div>
              </div>

              {/* Time Pickers */}
              <div className="grid md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="start-time">Heure de début</Label>
                  <Select value={startTime} onValueChange={setStartTime}>
                    <SelectTrigger id="start-time">
                      <SelectValue placeholder="Sélectionner" />
                    </SelectTrigger>
                    <SelectContent>
                      {TIME_OPTIONS.map((time) => (
                        <SelectItem key={time} value={time}>
                          <div className="flex items-center gap-2">
                            <Clock className="h-3 w-3" />
                            {time}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="end-time">Heure de fin</Label>
                  <Select value={endTime} onValueChange={setEndTime}>
                    <SelectTrigger id="end-time">
                      <SelectValue placeholder="Sélectionner" />
                    </SelectTrigger>
                    <SelectContent>
                      {TIME_OPTIONS.map((time) => (
                        <SelectItem key={time} value={time}>
                          <div className="flex items-center gap-2">
                            <Clock className="h-3 w-3" />
                            {time}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              {/* Quick Duration Buttons */}
              <div className="space-y-2">
                <Label>Durée rapide</Label>
                <div className="grid grid-cols-4 gap-2">
                  {[1, 2, 3, 4].map((hours) => (
                    <Button
                      key={hours}
                      type="button"
                      variant="outline"
                      onClick={() => setQuickDuration(hours)}
                      disabled={!startTime}
                    >
                      +{hours}h
                    </Button>
                  ))}
                </div>
              </div>

              {/* Price Preview */}
              {estimatedPrice > 0 && (
                <Card className="bg-muted/50">
                  <CardContent className="pt-6">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium">Prix estimé</span>
                      <span className="text-2xl font-bold flex items-center gap-1">
                        {estimatedPrice.toFixed(2)}
                        <Euro className="h-5 w-5" />
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-2">
                      Facturation par tranches de 15 minutes
                    </p>
                  </CardContent>
                </Card>
              )}
            </CardContent>

            <CardFooter className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/parkings')}
                className="flex-1"
              >
                Annuler
              </Button>
              <Button
                type="submit"
                disabled={isSubmitting || !date || !startTime || !endTime}
                className="flex-1"
              >
                {isSubmitting ? 'Réservation...' : 'Confirmer la réservation'}
              </Button>
            </CardFooter>
          </Card>
        </form>
      </div>
    </div>
  );
};
