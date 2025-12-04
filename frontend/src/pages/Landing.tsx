import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Search, Clock, DollarSign } from 'lucide-react';

export const Landing = () => {
  return (
    <div>
      {/* Hero Section */}
      <section className="py-20 text-center">
        <div className="container mx-auto px-4">
          <h1 className="text-5xl font-bold mb-6">
            Trouvez votre place de parking en quelques clics
          </h1>
          <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Réservez des places de parking en temps réel dans toute la ville. Simple, rapide et sécurisé.
          </p>
          <div className="flex gap-4 justify-center flex-wrap">
            <Link to="/parkings">
              <Button size="lg">Rechercher un parking</Button>
            </Link>
            <Link to="/register">
              <Button size="lg" variant="outline">
                Créer un compte
              </Button>
            </Link>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-16 bg-gray-50">
        <div className="container mx-auto px-4">
          <h2 className="text-3xl font-bold text-center mb-12">
            Pourquoi choisir ParkingSystem ?
          </h2>
          <div className="grid md:grid-cols-3 gap-8">
            <Card>
              <CardContent className="pt-6 text-center">
                <Search className="h-12 w-12 mx-auto mb-4 text-blue-600" />
                <h3 className="text-xl font-semibold mb-2">Recherche facile</h3>
                <p className="text-gray-600">
                  Trouvez des parkings près de vous avec notre système de recherche par localisation GPS.
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="pt-6 text-center">
                <Clock className="h-12 w-12 mx-auto mb-4 text-blue-600" />
                <h3 className="text-xl font-semibold mb-2">Réservation instantanée</h3>
                <p className="text-gray-600">
                  Réservez votre place en quelques secondes et arrivez l'esprit tranquille.
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="pt-6 text-center">
                <DollarSign className="h-12 w-12 mx-auto mb-4 text-blue-600" />
                <h3 className="text-xl font-semibold mb-2">Prix transparents</h3>
                <p className="text-gray-600">
                  Tarification claire par tranches de 15 minutes. Pas de frais cachés.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-16">
        <div className="container mx-auto px-4">
          <Card className="bg-blue-600 text-white">
            <CardContent className="pt-12 pb-12 text-center">
              <h2 className="text-3xl font-bold mb-4">Prêt à commencer ?</h2>
              <p className="text-lg mb-6 opacity-90">
                Créez votre compte gratuitement et réservez votre première place de parking.
              </p>
              <Link to="/register">
                <Button size="lg" variant="secondary">
                  S'inscrire gratuitement
                </Button>
              </Link>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  );
};
