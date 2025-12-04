import { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { reservationApi } from '@/lib/api';
import type { Reservation } from '@/types';
import { UserCalendar } from '@/components/UserCalendar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Calendar, MapPin, Euro, Clock, CheckCircle, Loader2, AlertCircle, XCircle } from 'lucide-react';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

export const UserDashboard = () => {
  const { user } = useAuth();
  const location = useLocation();
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(location.state?.message || '');

  useEffect(() => {
    fetchReservations();
  }, []);

  useEffect(() => {
    if (success) {
      const timer = setTimeout(() => setSuccess(''), 3000);
      return () => clearTimeout(timer);
    }
  }, [success]);

  const fetchReservations = async () => {
    setIsLoading(true);
    setError('');
    try {
      const response = await reservationApi.getAll();
      if (response.success && response.data) {
        setReservations(response.data);
      }
    } catch (err) {
      console.error('Error fetching reservations:', err);
      setError('Impossible de charger vos réservations');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancelReservation = async (reservationId: string) => {
    if (!confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
      return;
    }

    try {
      const response = await reservationApi.cancel(reservationId);
      if (response.success) {
        setSuccess('Réservation annulée avec succès');
        fetchReservations();
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors de l\'annulation');
    }
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: any; icon: any; label: string }> = {
      pending: { variant: 'secondary', icon: Clock, label: 'En attente' },
      confirmed: { variant: 'default', icon: CheckCircle, label: 'Confirmée' },
      active: { variant: 'default', icon: CheckCircle, label: 'Active' },
      completed: { variant: 'outline', icon: CheckCircle, label: 'Terminée' },
      cancelled: { variant: 'destructive', icon: XCircle, label: 'Annulée' },
    };

    const config = variants[status] || variants.pending;
    const Icon = config.icon;

    return (
      <Badge variant={config.variant}>
        <Icon className="h-3 w-3 mr-1" />
        {config.label}
      </Badge>
    );
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="max-w-4xl mx-auto">
        <h1 className="text-3xl font-bold mb-2">Dashboard Utilisateur</h1>
        <p className="text-muted-foreground mb-8">
          Bienvenue {(user as any)?.firstName} {(user as any)?.lastName}
        </p>

        {/* Success Alert */}
        {success && (
          <Alert variant="default" className="mb-6 border-green-500 bg-green-50">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <AlertDescription className="text-green-800">{success}</AlertDescription>
          </Alert>
        )}

        {/* Error Alert */}
        {error && (
          <Alert variant="destructive" className="mb-6">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <h2 className="text-2xl font-semibold mb-4">Mes Réservations</h2>

        {/* Loading State */}
        {isLoading && (
          <div className="flex items-center justify-center min-h-[300px]">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        )}

        {/* Empty State */}
        {!isLoading && reservations.length === 0 && (
          <Card className="text-center py-12">
            <CardContent>
              <Calendar className="h-16 w-16 mx-auto text-muted-foreground mb-4" />
              <h3 className="text-xl font-semibold mb-2">Aucune réservation</h3>
              <p className="text-muted-foreground mb-4">
                Vous n'avez pas encore de réservation
              </p>
              <Button onClick={() => window.location.href = '/parkings'}>
                Rechercher un parking
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Reservations List */}
        {!isLoading && reservations.length > 0 && (
          <div className="space-y-4">
            {reservations.map((reservation) => (
              <Card key={reservation.id}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div>
                      <CardTitle>{reservation.parking?.name || 'Parking'}</CardTitle>
                      <CardDescription className="flex items-center gap-1 mt-2">
                        <MapPin className="h-3 w-3" />
                        {reservation.parking?.address || 'Adresse inconnue'}
                      </CardDescription>
                    </div>
                    {getStatusBadge(reservation.status)}
                  </div>
                </CardHeader>
                <CardContent className="space-y-2">
                  <div className="flex items-center gap-2 text-sm">
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                    <span>
                      {format(new Date(reservation.startTime), 'PPP', { locale: fr })}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                    <span>
                      {format(new Date(reservation.startTime), 'HH:mm')} -{' '}
                      {format(new Date(reservation.endTime), 'HH:mm')}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <Euro className="h-4 w-4 text-muted-foreground" />
                    <span className="font-semibold">
                      {reservation.totalAmount.toFixed(2)}€
                    </span>
                  </div>
                </CardContent>
                {(reservation.status === 'pending' || reservation.status === 'confirmed') && (
                  <CardFooter>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => handleCancelReservation(reservation.id)}
                    >
                      <XCircle className="h-4 w-4 mr-2" />
                      Annuler la réservation
                    </Button>
                  </CardFooter>
                )}
              </Card>
            ))}
          </div>
        )}

        {/* User Calendar */}
        {!isLoading && reservations.length > 0 && (
          <div className="mt-8">
            <UserCalendar reservations={reservations} />
          </div>
        )}
      </div>
    </div>
  );
};
