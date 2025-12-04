import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useAuth } from '@/contexts/AuthContext';
import { parkingApi } from '@/lib/api';
import type { Parking } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Building2, Plus, MapPin, Euro, ParkingCircle, Pencil, Trash2, Loader2, AlertCircle, CheckCircle } from 'lucide-react';

// Validation schema
const parkingSchema = z.object({
  name: z.string().min(3, 'Le nom doit contenir au moins 3 caractères'),
  address: z.string().min(5, 'L\'adresse doit contenir au moins 5 caractères'),
  latitude: z.string().refine(
    (val) => {
      const num = parseFloat(val);
      return !isNaN(num) && num >= -90 && num <= 90;
    },
    'Latitude invalide (entre -90 et 90)'
  ),
  longitude: z.string().refine(
    (val) => {
      const num = parseFloat(val);
      return !isNaN(num) && num >= -180 && num <= 180;
    },
    'Longitude invalide (entre -180 et 180)'
  ),
  hourlyRate: z.string().refine(
    (val) => {
      const num = parseFloat(val);
      return !isNaN(num) && num > 0;
    },
    'Le tarif doit être supérieur à 0'
  ),
  totalSpots: z.string().refine(
    (val) => {
      const num = parseInt(val);
      return !isNaN(num) && num > 0;
    },
    'Le nombre de places doit être supérieur à 0'
  ),
});

type ParkingFormData = z.infer<typeof parkingSchema>;

export const OwnerDashboard = () => {
  const { user } = useAuth();
  const [parkings, setParkings] = useState<Parking[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [selectedParking, setSelectedParking] = useState<Parking | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const createForm = useForm<ParkingFormData>({
    resolver: zodResolver(parkingSchema),
    defaultValues: {
      name: '',
      address: '',
      latitude: '',
      longitude: '',
      hourlyRate: '',
      totalSpots: '',
    },
  });

  const editForm = useForm<ParkingFormData>({
    resolver: zodResolver(parkingSchema),
  });

  useEffect(() => {
    fetchParkings();
  }, []);

  useEffect(() => {
    if (selectedParking) {
      editForm.reset({
        name: selectedParking.name,
        address: selectedParking.address,
        latitude: selectedParking.location.latitude.toString(),
        longitude: selectedParking.location.longitude.toString(),
        hourlyRate: selectedParking.hourlyRate.toString(),
        totalSpots: selectedParking.totalSpots.toString(),
      });
    }
  }, [selectedParking, editForm]);

  const fetchParkings = async () => {
    setIsLoading(true);
    setError('');
    try {
      const response = await parkingApi.getAll();
      if (response.success && response.data) {
        // Filter by owner (will be done by backend in production)
        setParkings(response.data);
      }
    } catch (err) {
      console.error('Error fetching parkings:', err);
      setError('Impossible de charger les parkings');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCreate = async (data: ParkingFormData) => {
    setIsSubmitting(true);
    setError('');
    try {
      const response = await parkingApi.create({
        name: data.name,
        address: data.address,
        location: {
          latitude: parseFloat(data.latitude),
          longitude: parseFloat(data.longitude),
        },
        hourlyRate: parseFloat(data.hourlyRate),
        totalSpots: parseInt(data.totalSpots),
      });

      if (response.success) {
        setSuccess('Parking créé avec succès !');
        setIsCreateDialogOpen(false);
        createForm.reset();
        fetchParkings();
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors de la création du parking');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleUpdate = async (data: ParkingFormData) => {
    if (!selectedParking) return;

    setIsSubmitting(true);
    setError('');
    try {
      const response = await parkingApi.update(selectedParking.id, {
        name: data.name,
        address: data.address,
        location: {
          latitude: parseFloat(data.latitude),
          longitude: parseFloat(data.longitude),
        },
        hourlyRate: parseFloat(data.hourlyRate),
        totalSpots: parseInt(data.totalSpots),
      });

      if (response.success) {
        setSuccess('Parking modifié avec succès !');
        setIsEditDialogOpen(false);
        setSelectedParking(null);
        fetchParkings();
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors de la modification du parking');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedParking) return;

    setIsSubmitting(true);
    setError('');
    try {
      const response = await parkingApi.delete(selectedParking.id);

      if (response.success) {
        setSuccess('Parking supprimé avec succès !');
        setIsDeleteDialogOpen(false);
        setSelectedParking(null);
        fetchParkings();
        setTimeout(() => setSuccess(''), 3000);
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors de la suppression du parking');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="max-w-6xl mx-auto">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold mb-2">Dashboard Propriétaire</h1>
            <p className="text-muted-foreground">
              Gérez vos parkings et suivez vos revenus
            </p>
          </div>
          <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Créer un parking
              </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
              <DialogHeader>
                <DialogTitle>Créer un nouveau parking</DialogTitle>
                <DialogDescription>
                  Remplissez les informations du parking
                </DialogDescription>
              </DialogHeader>
              <Form {...createForm}>
                <form onSubmit={createForm.handleSubmit(handleCreate)} className="space-y-4">
                  <FormField
                    control={createForm.control}
                    name="name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Nom du parking</FormLabel>
                        <FormControl>
                          <Input placeholder="Parking Centre-Ville" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={createForm.control}
                    name="address"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Adresse</FormLabel>
                        <FormControl>
                          <Input placeholder="123 Rue de la Paix, 75001 Paris" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <div className="grid grid-cols-2 gap-4">
                    <FormField
                      control={createForm.control}
                      name="latitude"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Latitude</FormLabel>
                          <FormControl>
                            <Input placeholder="48.8566" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={createForm.control}
                      name="longitude"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Longitude</FormLabel>
                          <FormControl>
                            <Input placeholder="2.3522" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <FormField
                      control={createForm.control}
                      name="hourlyRate"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Tarif horaire (€)</FormLabel>
                          <FormControl>
                            <Input type="number" step="0.01" placeholder="3.50" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={createForm.control}
                      name="totalSpots"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Nombre de places</FormLabel>
                          <FormControl>
                            <Input type="number" placeholder="100" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                  <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                      Annuler
                    </Button>
                    <Button type="submit" disabled={isSubmitting}>
                      {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                      Créer
                    </Button>
                  </DialogFooter>
                </form>
              </Form>
            </DialogContent>
          </Dialog>
        </div>

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

        {/* Loading State */}
        {isLoading && (
          <div className="flex items-center justify-center min-h-[400px]">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        )}

        {/* Empty State */}
        {!isLoading && parkings.length === 0 && (
          <Card className="text-center py-12">
            <CardContent>
              <Building2 className="h-16 w-16 mx-auto text-muted-foreground mb-4" />
              <h3 className="text-xl font-semibold mb-2">Aucun parking</h3>
              <p className="text-muted-foreground mb-4">
                Commencez par créer votre premier parking
              </p>
              <Button onClick={() => setIsCreateDialogOpen(true)}>
                <Plus className="h-4 w-4 mr-2" />
                Créer un parking
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Parkings Grid */}
        {!isLoading && parkings.length > 0 && (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {parkings.map((parking) => (
              <Card key={parking.id} className="hover:shadow-lg transition-shadow">
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div>
                      <CardTitle>{parking.name}</CardTitle>
                      <CardDescription className="flex items-center gap-1 mt-2">
                        <MapPin className="h-3 w-3" />
                        {parking.address}
                      </CardDescription>
                    </div>
                    <Badge variant="secondary">
                      <Euro className="h-3 w-3 mr-1" />
                      {parking.hourlyRate.toFixed(2)}€/h
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <ParkingCircle className="h-4 w-4" />
                    <span>{parking.totalSpots} places</span>
                  </div>
                </CardContent>
                <CardFooter className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={() => {
                      setSelectedParking(parking);
                      setIsEditDialogOpen(true);
                    }}
                  >
                    <Pencil className="h-3 w-3 mr-1" />
                    Modifier
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    className="flex-1"
                    onClick={() => {
                      setSelectedParking(parking);
                      setIsDeleteDialogOpen(true);
                    }}
                  >
                    <Trash2 className="h-3 w-3 mr-1" />
                    Supprimer
                  </Button>
                </CardFooter>
              </Card>
            ))}
          </div>
        )}

        {/* Edit Dialog */}
        <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Modifier le parking</DialogTitle>
              <DialogDescription>
                Modifiez les informations du parking
              </DialogDescription>
            </DialogHeader>
            <Form {...editForm}>
              <form onSubmit={editForm.handleSubmit(handleUpdate)} className="space-y-4">
                <FormField
                  control={editForm.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Nom du parking</FormLabel>
                      <FormControl>
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={editForm.control}
                  name="address"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Adresse</FormLabel>
                      <FormControl>
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <div className="grid grid-cols-2 gap-4">
                  <FormField
                    control={editForm.control}
                    name="latitude"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Latitude</FormLabel>
                        <FormControl>
                          <Input {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={editForm.control}
                    name="longitude"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Longitude</FormLabel>
                        <FormControl>
                          <Input {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <FormField
                    control={editForm.control}
                    name="hourlyRate"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Tarif horaire (€)</FormLabel>
                        <FormControl>
                          <Input type="number" step="0.01" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={editForm.control}
                    name="totalSpots"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Nombre de places</FormLabel>
                        <FormControl>
                          <Input type="number" {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                    Annuler
                  </Button>
                  <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    Modifier
                  </Button>
                </DialogFooter>
              </form>
            </Form>
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation Dialog */}
        <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Confirmer la suppression</DialogTitle>
              <DialogDescription>
                Êtes-vous sûr de vouloir supprimer le parking "{selectedParking?.name}" ?
                Cette action est irréversible.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                Annuler
              </Button>
              <Button variant="destructive" onClick={handleDelete} disabled={isSubmitting}>
                {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Supprimer
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
};
