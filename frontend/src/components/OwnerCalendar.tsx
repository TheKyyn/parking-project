import { useState, useEffect, useMemo } from 'react';
import { Calendar, dateFnsLocalizer, Views, type View } from 'react-big-calendar';
import { format, parse, startOfWeek, getDay } from 'date-fns';
import { fr } from 'date-fns/locale';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { User, Clock, Euro, MapPin, Loader2 } from 'lucide-react';
import { reservationApi } from '@/lib/api';
import type { Reservation, Parking } from '@/types';

const locales = {
  'fr': fr,
};

const localizer = dateFnsLocalizer({
  format,
  parse,
  startOfWeek,
  getDay,
  locales,
});

interface CalendarEvent {
  id: string;
  title: string;
  start: Date;
  end: Date;
  resource: Reservation;
}

interface OwnerCalendarProps {
  parkings: Parking[];
}

export const OwnerCalendar = ({ parkings }: OwnerCalendarProps) => {
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [selectedParking, setSelectedParking] = useState<string>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [currentView, setCurrentView] = useState<View>(Views.MONTH);
  const [currentDate, setCurrentDate] = useState(new Date());

  useEffect(() => {
    fetchReservations();
  }, []);

  const fetchReservations = async () => {
    setIsLoading(true);
    try {
      const response = await reservationApi.getByOwner();
      if (response.success && response.data) {
        setReservations(response.data);
      }
    } catch (err) {
      console.error('Error fetching reservations:', err);
    } finally {
      setIsLoading(false);
    }
  };

  const filteredReservations = useMemo(() => {
    if (selectedParking === 'all') return reservations;
    return reservations.filter((r) => r.parkingId === selectedParking);
  }, [reservations, selectedParking]);

  const events: CalendarEvent[] = useMemo(() => {
    return filteredReservations.map((reservation) => ({
      id: reservation.id,
      title: `${reservation.user?.firstName || 'User'} - ${reservation.parking?.name || 'Parking'}`,
      start: new Date(reservation.startTime),
      end: new Date(reservation.endTime),
      resource: reservation,
    }));
  }, [filteredReservations]);

  const handleEventClick = (event: CalendarEvent) => {
    setSelectedEvent(event);
    setIsDialogOpen(true);
  };

  const eventStyleGetter = (event: CalendarEvent) => {
    const status = event.resource.status;
    let backgroundColor = '#3174ad';

    switch (status) {
      case 'pending':
        backgroundColor = '#f59e0b';
        break;
      case 'confirmed':
        backgroundColor = '#10b981';
        break;
      case 'active':
        backgroundColor = '#3b82f6';
        break;
      case 'completed':
        backgroundColor = '#6b7280';
        break;
      case 'cancelled':
        backgroundColor = '#ef4444';
        break;
    }

    return {
      style: {
        backgroundColor,
        borderRadius: '4px',
        opacity: 0.9,
        color: 'white',
        border: 'none',
        display: 'block',
      },
    };
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
      pending: 'secondary',
      confirmed: 'default',
      active: 'default',
      completed: 'outline',
      cancelled: 'destructive',
    };

    const labels: Record<string, string> = {
      pending: 'En attente',
      confirmed: 'Confirmée',
      active: 'Active',
      completed: 'Terminée',
      cancelled: 'Annulée',
    };

    return (
      <Badge variant={variants[status] || 'default'}>
        {labels[status] || status}
      </Badge>
    );
  };

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center min-h-[400px]">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>Calendrier des réservations</CardTitle>
          <Select value={selectedParking} onValueChange={setSelectedParking}>
            <SelectTrigger className="w-[250px]">
              <SelectValue placeholder="Tous les parkings" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Tous les parkings</SelectItem>
              {parkings.map((parking) => (
                <SelectItem key={parking.id} value={parking.id}>
                  {parking.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </CardHeader>
      <CardContent>
        <div className="h-[600px]">
          <Calendar
            localizer={localizer}
            events={events}
            startAccessor="start"
            endAccessor="end"
            style={{ height: '100%' }}
            onSelectEvent={handleEventClick}
            eventPropGetter={eventStyleGetter}
            views={[Views.MONTH, Views.WEEK, Views.DAY]}
            view={currentView}
            onView={setCurrentView}
            date={currentDate}
            onNavigate={setCurrentDate}
            messages={{
              next: 'Suivant',
              previous: 'Précédent',
              today: "Aujourd'hui",
              month: 'Mois',
              week: 'Semaine',
              day: 'Jour',
              agenda: 'Agenda',
              date: 'Date',
              time: 'Heure',
              event: 'Réservation',
              noEventsInRange: 'Aucune réservation pour cette période',
            }}
          />
        </div>

        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Détails de la réservation</DialogTitle>
            </DialogHeader>
            {selectedEvent && (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="font-semibold">
                    {selectedEvent.resource.parking?.name}
                  </h3>
                  {getStatusBadge(selectedEvent.resource.status)}
                </div>

                <div className="space-y-2">
                  <div className="flex items-center gap-2 text-sm">
                    <User className="h-4 w-4 text-muted-foreground" />
                    <span>
                      {selectedEvent.resource.user?.firstName}{' '}
                      {selectedEvent.resource.user?.lastName}
                    </span>
                  </div>

                  <div className="flex items-center gap-2 text-sm">
                    <MapPin className="h-4 w-4 text-muted-foreground" />
                    <span>{selectedEvent.resource.parking?.address}</span>
                  </div>

                  <div className="flex items-center gap-2 text-sm">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                    <span>
                      {format(selectedEvent.start, 'PPP HH:mm', { locale: fr })} →{' '}
                      {format(selectedEvent.end, 'PPP HH:mm', { locale: fr })}
                    </span>
                  </div>

                  <div className="flex items-center gap-2 text-sm">
                    <Euro className="h-4 w-4 text-muted-foreground" />
                    <span className="font-semibold">
                      {selectedEvent.resource.totalAmount.toFixed(2)}€
                    </span>
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
};
