export type Supplier = {
  id: string;
  name: string;
  contact: string;
  email: string;
  phone: string;
  city: string;
  address: string;
  category: string;
  active: boolean;
  terms: string;
  note: string;
};

export const supplierCategories = [
  "Alat tulis",
  "Kertas & buku",
  "Perlengkapan kantor",
  "Tinta & toner",
];
export const paymentTerms = ["Tunai", "Tempo 14 hari", "Tempo 30 hari"];
const samples = [
  [
    "PT Sinar Stationery",
    "Andi Pratama",
    "Jakarta",
    "Alat tulis",
    "Tempo 30 hari",
  ],
  [
    "CV Kertas Nusantara",
    "Dewi Lestari",
    "Bandung",
    "Kertas & buku",
    "Tempo 14 hari",
  ],
  ["UD Mitra Alat Tulis", "Budi Santoso", "Surabaya", "Alat tulis", "Tunai"],
  [
    "PT Cipta Office Mandiri",
    "Rina Wulandari",
    "Jakarta",
    "Perlengkapan kantor",
    "Tempo 30 hari",
  ],
  [
    "CV Warna Prima",
    "Fajar Hidayat",
    "Semarang",
    "Tinta & toner",
    "Tempo 14 hari",
  ],
  ["UD Pena Sejahtera", "Siti Rahma", "Yogyakarta", "Alat tulis", "Tunai"],
  [
    "PT Pustaka Kertas Abadi",
    "Dimas Saputra",
    "Surabaya",
    "Kertas & buku",
    "Tempo 30 hari",
  ],
  [
    "CV Sentra Perkantoran",
    "Maya Putri",
    "Bandung",
    "Perlengkapan kantor",
    "Tempo 14 hari",
  ],
  ["PT Tinta Jaya", "Arif Nugroho", "Tangerang", "Tinta & toner", "Tunai"],
];
export const initialSuppliers: Supplier[] = samples.map(
  ([name, contact, city, category, terms], index) => ({
    id: `SUP-${String(index + 1).padStart(3, "0")}`,
    name,
    contact,
    city,
    category,
    terms,
    email: `supplier${index + 1}@example.com`,
    phone: `021-555-${String(index + 1).padStart(4, "0")}`,
    address: `Jl. Niaga No. ${12 + index}, ${city}`,
    active: index !== 5 && index !== 8,
    note:
      index === 0
        ? "Konfirmasi ketersediaan barang sebelum membuat pesanan."
        : "",
  }),
);
