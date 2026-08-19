import React from "react";
import { ServiceCatalog } from "@/components/ServiceCatalog";
import { useAuth } from "@/context/AuthContext";

export default function Catalog() {
  const { user } = useAuth();
  return (
    <div data-testid="catalog-page" className="mx-auto max-w-7xl px-6 py-14">
      <h1 className="text-4xl font-extrabold sm:text-5xl">Harga Layanan</h1>
      <p className="mt-4 max-w-2xl text-sm text-muted-foreground md:text-lg">
        Harga sudah termasuk markup dan biaya layanan. Pilih negara, cari layanan, lalu pesan nomor dari dasbor.
      </p>
      <div className="mt-10">
        <ServiceCatalog canBuy={!!user} />
      </div>
    </div>
  );
}
