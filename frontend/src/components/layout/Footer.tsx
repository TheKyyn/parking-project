export const Footer = () => {
  return (
    <footer className="bg-gray-900 text-gray-300 mt-auto">
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <p className="mb-4">&copy; 2025 ParkingSystem. Tous droits réservés.</p>
          <div className="flex justify-center gap-6 text-sm">
            <a href="#" className="hover:text-white transition">
              À propos
            </a>
            <a href="#" className="hover:text-white transition">
              Conditions d'utilisation
            </a>
            <a href="#" className="hover:text-white transition">
              Contact
            </a>
          </div>
        </div>
      </div>
    </footer>
  );
};
