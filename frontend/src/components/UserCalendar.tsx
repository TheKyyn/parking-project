import { useState, useMemo } from 'react';
import { Calendar, dateFnsLocalizer, Views } from 'react-big-calendar';
import { format, parse, startOfWeek, getDay } from 'date-fns';
import { fr } from 'date-fns/locale';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { MapPin, Clock, Euro } from 'lucide-react';
import type { Reservation } from '@/types';

const locales = { 'fr': fr };

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

interface UserCalendarProps {
  reservations: Reservation[];
}

export const UserCalendar = ({ reservations }: UserCalendarProps) => {
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const events: CalendarEvent[] = useMemo(() => {
    return reservations.map((reservation) => ({
      id: reservation.id,
      title: reservation.parking?.name || 'Parking',
      start: new Date(reservation.startTime),
      end: new Date(reservation.endTime),
      resource: reservation,
    }));
  }, [reservations]);

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
      case 'active':
        backgroundColor = '#10b981';
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

  return (
    <Card>
      <CardHeader>
        <CardTitle>Mon calendrier de réservations</CardTitle>
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
            views={[Views.MONTH, Views.WEEK]}
            defaultView={Views.MONTH}
            messages={{
              next: 'Suivant',
              previous: 'Précédent',
              today: "Aujourd'hui",
              month: 'Mois',
              week: 'Semaine',
              day: 'Jour',
              noEventsInRange: 'Aucune réservation',
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
                    <MapPin className="h-4 w-4 text-muted-foreground" />
                    <span>{selectedEvent.resource.parking?.address}</span>
                  </div>

                  <div className="flex items-center gap-2 text-sm">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                    <span>
                      {format(selectedEvent.start, 'PPP HH:mm', { locale: fr })} →{' '}
                      {format(selectedEvent.end, 'HH:mm')}
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
