import { useRef, useState, type FormEvent } from "react";
import {
  Building2,
  ChevronLeft,
  ChevronRight,
  MapPin,
  Pencil,
  Plus,
  Search,
  Truck,
  UserCheck,
  Wallet,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import {
  initialSuppliers,
  paymentTerms,
  supplierCategories,
  type Supplier,
} from "~/features/suppliers/supplier.mock";
import "~/features/suppliers/supplier.css";

export function meta() {
  return [
    { title: "Supplier | ATK Industry" },
    {
      name: "description",
      content: "Kelola direktori supplier dan kontak pemasok toko.",
    },
  ];
}

export default function Suppliers() {
  const [suppliers, setSuppliers] = useState(initialSuppliers);
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("all");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Supplier | null>(null);
  const [selected, setSelected] = useState<Supplier | null>(null);
  const setNotice = useToast();
  const [formVersion, setFormVersion] = useState(0);
  const dialog = useRef<HTMLDialogElement>(null);
  const detail = useRef<HTMLDialogElement>(null);
  const filtered = suppliers.filter(
    (item) =>
      (category === "all" || item.category === category) &&
      (status === "all" ||
        (status === "active" ? item.active : !item.active)) &&
      `${item.id} ${item.name} ${item.contact} ${item.city} ${item.email} ${item.phone}`
        .toLocaleLowerCase("id-ID")
        .includes(search.trim().toLocaleLowerCase("id-ID")),
  );
  const pages = Math.max(1, Math.ceil(filtered.length / 6));
  const currentPage = Math.min(page, pages);
  const rows = filtered.slice((currentPage - 1) * 6, currentPage * 6);
  const activeCount = suppliers.filter((item) => item.active).length;

  function openForm(item: Supplier | null) {
    setEditing(item);
    setFormVersion((value) => value + 1);
    detail.current?.close();
    dialog.current?.showModal();
  }

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const value = (key: string) => String(data.get(key) ?? "").trim();
    const next: Supplier = {
      id:
        editing?.id ??
        `SUP-${String(Math.max(0, ...suppliers.map((item) => Number(item.id.slice(4)))) + 1).padStart(3, "0")}`,
      name: value("name"),
      contact: value("contact"),
      email: value("email"),
      phone: value("phone"),
      city: value("city"),
      address: value("address"),
      category: value("category"),
      terms: value("terms"),
      active: value("status") === "active",
      note: value("note"),
    };
    setSuppliers((current) =>
      editing
        ? current.map((item) => (item.id === next.id ? next : item))
        : [next, ...current],
    );
    setSearch("");
    setCategory("all");
    setStatus("all");
    setPage(1);
    setNotice(
      `${next.name} berhasil ${editing ? "diperbarui" : "ditambahkan"}.`,
    );
    dialog.current?.close();
  }

  return (
    <div className="suppliers-page">
      <section className="suppliers-heading">
        <div>
          <p className="suppliers-eyebrow">MITRA & PASOKAN</p>
          <h1>
            Supplier<span>.</span>
          </h1>
          <p>Semua mitra pemasok, terhubung dalam satu tempat.</p>
        </div>
        <button
          className="supplier-button primary"
          onClick={() => openForm(null)}
        >
          <Plus size={16} />
          Tambah supplier
        </button>
      </section>
      <section className="supplier-summary" aria-label="Ringkasan supplier">
        {[
          {
            label: "Total supplier",
            value: suppliers.length,
            caption: "Mitra pemasok terdaftar",
            icon: Building2,
            tone: "blue",
          },
          {
            label: "Supplier aktif",
            value: activeCount,
            caption: "Siap menerima pesanan",
            icon: UserCheck,
            tone: "green",
          },
          {
            label: "Kota pemasok",
            value: new Set(
              suppliers.map((item) => item.city.toLocaleLowerCase("id-ID")),
            ).size,
            caption: "Jangkauan mitra pemasok",
            icon: MapPin,
            tone: "violet",
          },
          {
            label: "Pembayaran tempo",
            value: suppliers.filter((item) => item.terms !== "Tunai").length,
            caption: "Supplier dengan fasilitas tempo",
            icon: Wallet,
            tone: "amber",
          },
        ].map(({ label, value, caption, icon: Icon, tone }) => (
          <article key={label}>
            <span className={`supplier-stat-icon ${tone}`}>
              <Icon size={20} />
            </span>
            <div>
              <span>{label}</span>
              <strong>{value}</strong>
              <small>{caption}</small>
            </div>
          </article>
        ))}
      </section>
      <p className="supplier-demo">
        <span>
          <i />
          Data contoh
        </span>
        Perubahan hanya tersimpan selama halaman ini terbuka.
      </p>
      <section className="supplier-panel" aria-labelledby="supplier-list-title">
        <div className="supplier-panel-heading">
          <div>
            <h2 id="supplier-list-title">Daftar supplier</h2>
            <p>Temukan kontak, kategori pasokan, dan informasi mitra Anda.</p>
          </div>
          <span className="supplier-count">{suppliers.length} supplier</span>
        </div>
        <div className="supplier-toolbar">
          <label className="supplier-search workspace-search">
            <Search size={17} />
            <input
              type="search"
              aria-label="Cari supplier"
              placeholder="Cari nama, kontak, atau kota supplier..."
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
            />
          </label>
          <SelectMenu
            ariaLabel="Filter kategori supplier"
            className="supplier-filter"
            value={category}
            options={[
              { value: "all", label: "Semua kategori" },
              ...supplierCategories.map((item) => ({
                value: item,
                label: item,
              })),
            ]}
            onChange={(value) => {
              setCategory(value);
              setPage(1);
            }}
          />
        </div>
        <div
          className="supplier-tabs"
          role="group"
          aria-label="Status supplier"
        >
          {[
            { value: "all", label: "Semua supplier", count: suppliers.length },
            { value: "active", label: "Aktif", count: activeCount },
            {
              value: "inactive",
              label: "Nonaktif",
              count: suppliers.length - activeCount,
            },
          ].map((item) => (
            <button
              key={item.value}
              aria-pressed={status === item.value}
              className={status === item.value ? "selected" : ""}
              onClick={() => {
                setStatus(item.value);
                setPage(1);
              }}
            >
              {item.label}
              <span>{item.count}</span>
            </button>
          ))}
        </div>
        <div className="supplier-table-wrap">
          <table className="supplier-table">
            <thead>
              <tr>
                <th scope="col">SUPPLIER</th>
                <th scope="col">KONTAK</th>
                <th scope="col">KATEGORI</th>
                <th scope="col">PEMBAYARAN</th>
                <th scope="col">STATUS</th>
                <th scope="col">
                  <span className="sr-only">Tindakan</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((item, index) => (
                <tr key={item.id}>
                  <td>
                    <button
                      className="supplier-name"
                      onClick={() => {
                        setSelected(item);
                        detail.current?.showModal();
                      }}
                    >
                      <span className={`supplier-avatar tone-${index % 3}`}>
                        <Building2 size={19} />
                      </span>
                      <span>
                        <strong>{item.name}</strong>
                        <small>
                          {item.id} · {item.city}
                        </small>
                      </span>
                    </button>
                  </td>
                  <td>
                    <strong>{item.contact}</strong>
                    <small>{item.phone}</small>
                  </td>
                  <td>
                    <span className="supplier-category">{item.category}</span>
                  </td>
                  <td>{item.terms}</td>
                  <td>
                    <span
                      className={`supplier-status ${item.active ? "active" : "inactive"}`}
                    >
                      <i />
                      {item.active ? "Aktif" : "Nonaktif"}
                    </span>
                  </td>
                  <td>
                    <button
                      className="supplier-icon-button"
                      aria-label={`Edit ${item.name}`}
                      onClick={() => openForm(item)}
                    >
                      <Pencil size={14} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!rows.length && (
          <div className="supplier-empty">
            <Search size={28} />
            <h3>Supplier tidak ditemukan</h3>
            <p>Coba kata kunci lain atau atur ulang filter.</p>
            <button
              className="supplier-button secondary"
              onClick={() => {
                setSearch("");
                setStatus("all");
                setCategory("all");
                setPage(1);
              }}
            >
              Reset filter
            </button>
          </div>
        )}
        <footer className="supplier-pagination">
          <span>
            Menampilkan{" "}
            <strong>
              {filtered.length ? (currentPage - 1) * 6 + 1 : 0}–
              {Math.min(currentPage * 6, filtered.length)}
            </strong>{" "}
            dari <strong>{filtered.length}</strong> supplier
          </span>
          <div>
            <button
              aria-label="Halaman sebelumnya"
              disabled={currentPage === 1}
              onClick={() => setPage(currentPage - 1)}
            >
              <ChevronLeft size={15} />
            </button>
            <span>
              Halaman {currentPage} dari {pages}
            </span>
            <button
              aria-label="Halaman berikutnya"
              disabled={currentPage === pages}
              onClick={() => setPage(currentPage + 1)}
            >
              <ChevronRight size={15} />
            </button>
          </div>
        </footer>
      </section>
      <p className="supplier-footnote">
        <Truck size={14} />
        Kelola informasi pemasok untuk memudahkan proses pembelian.
      </p>
      <dialog
        ref={dialog}
        className="supplier-dialog"
        aria-labelledby="supplier-form-title"
      >
        <form key={formVersion} onSubmit={save}>
          <header>
            <div>
              <p>DIREKTORI SUPPLIER</p>
              <h2 id="supplier-form-title">
                {editing ? "Edit supplier" : "Tambah supplier"}
              </h2>
            </div>
            <button
              type="button"
              aria-label="Tutup form"
              onClick={() => dialog.current?.close()}
            >
              <X size={20} />
            </button>
          </header>
          <p className="supplier-dialog-note">
            Lengkapi informasi pemasok. Kolom bertanda * wajib diisi.
          </p>
          <div className="supplier-form-grid">
            <label>
              Nama supplier *
              <input
                name="name"
                defaultValue={editing?.name}
                required
                pattern={".*\\S.*"}
                maxLength={100}
                placeholder="Contoh: PT Sinar Stationery"
              />
            </label>
            <label>
              Nama kontak *
              <input
                name="contact"
                defaultValue={editing?.contact}
                required
                pattern={".*\\S.*"}
                maxLength={80}
                placeholder="Nama penanggung jawab"
              />
            </label>
            <label>
              Nomor telepon *
              <input
                name="phone"
                type="tel"
                defaultValue={editing?.phone}
                required
                minLength={6}
                maxLength={25}
                placeholder="021-555-0001"
              />
            </label>
            <label>
              Email *
              <input
                name="email"
                type="email"
                defaultValue={editing?.email}
                required
                maxLength={120}
                placeholder="supplier@example.com"
              />
            </label>
            <label>
              Kota *
              <input
                name="city"
                defaultValue={editing?.city}
                required
                pattern={".*\\S.*"}
                maxLength={60}
                placeholder="Jakarta"
              />
            </label>
            <label>
              Kategori
              <select
                name="category"
                defaultValue={editing?.category ?? supplierCategories[0]}
              >
                {supplierCategories.map((item) => (
                  <option key={item}>{item}</option>
                ))}
              </select>
            </label>
            <label>
              Pembayaran
              <select name="terms" defaultValue={editing?.terms ?? "Tunai"}>
                {paymentTerms.map((item) => (
                  <option key={item}>{item}</option>
                ))}
              </select>
            </label>
            <label>
              Status
              <select
                name="status"
                defaultValue={editing?.active === false ? "inactive" : "active"}
              >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
            </label>
            <label className="supplier-wide">
              Alamat *
              <textarea
                name="address"
                defaultValue={editing?.address}
                required
                maxLength={250}
                rows={2}
              />
            </label>
            <label className="supplier-wide">
              Catatan
              <textarea
                name="note"
                defaultValue={editing?.note}
                maxLength={500}
                rows={2}
                placeholder="Informasi tambahan untuk tim pembelian"
              />
            </label>
          </div>
          <footer>
            <button
              type="button"
              className="supplier-button secondary"
              onClick={() => dialog.current?.close()}
            >
              Batal
            </button>
            <button type="submit" className="supplier-button primary">
              Simpan supplier
            </button>
          </footer>
        </form>
      </dialog>
      <dialog
        ref={detail}
        className="supplier-dialog"
        aria-labelledby="supplier-detail-title"
      >
        {selected && (
          <>
            <header>
              <div>
                <p>{selected.id}</p>
                <h2 id="supplier-detail-title">{selected.name}</h2>
              </div>
              <button
                aria-label="Tutup detail"
                onClick={() => detail.current?.close()}
              >
                <X size={20} />
              </button>
            </header>
            <span
              className={`supplier-status ${selected.active ? "active" : "inactive"}`}
            >
              <i />
              {selected.active ? "Aktif" : "Nonaktif"}
            </span>
            <dl className="supplier-details">
              {[
                ["Penanggung jawab", selected.contact],
                ["Telepon", selected.phone],
                ["Email", selected.email],
                ["Kota", selected.city],
                ["Kategori", selected.category],
                ["Pembayaran", selected.terms],
                ["Alamat", selected.address],
                ["Catatan", selected.note || "Belum ada catatan."],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <footer>
              <button
                className="supplier-button secondary"
                onClick={() => detail.current?.close()}
              >
                Tutup
              </button>
              <button
                className="supplier-button primary"
                onClick={() => openForm(selected)}
              >
                <Pencil size={14} />
                Edit supplier
              </button>
            </footer>
          </>
        )}
      </dialog>
    </div>
  );
}
