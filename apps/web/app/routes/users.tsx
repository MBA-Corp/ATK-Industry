import { useRef, useState, type FormEvent } from "react";
import { Navigate } from "react-router";
import { Pencil, Plus, Search, ShieldCheck, Users as UsersIcon, X } from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useCurrentUserQuery } from "~/features/auth/auth.queries";
import { useToast } from "~/components/toast-provider";
import "./users.css";

const roles = ["Owner", "Manager", "Supervisor", "Kasir"] as const;
type Role = (typeof roles)[number];
type Member = { username: string; name: string; role: Role; active: boolean };

const permissionLabels: Record<string, string> = {
  "category.create": "Tambah kategori",
  "category.update": "Edit kategori",
  "unit.create": "Tambah satuan",
  "unit.update": "Edit satuan",
  "product.create": "Tambah produk",
  "product.update": "Edit produk",
  "supplier.create": "Tambah supplier",
  "supplier.update": "Edit supplier",
  "purchase.create": "Buat pembelian",
  "purchase.receive": "Terima barang",
  "inventory.adjust": "Sesuaikan stok",
  "sale.create": "Catat penjualan",
  "sale.cancel": "Batalkan penjualan",
  "company_profile.update": "Edit profil toko",
  "purchase.invoice.view": "Lihat faktur pembelian",
  "purchase.invoice.manage": "Kelola faktur pembelian",
};

const examples: Member[] = [
  { username: "dina", name: "Dina", role: "Kasir", active: true },
  { username: "rizky", name: "Rizky", role: "Supervisor", active: true },
  { username: "ayu", name: "Ayu", role: "Manager", active: false },
];

export function meta() {
  return [{ title: "Pengguna & akses | ATK Industry" }];
}

export default function Users() {
  const { data: account } = useCurrentUserQuery();
  const [members, setMembers] = useState(examples);
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("all");
  const [editing, setEditing] = useState<Member | null>(null);
  const [formVersion, setFormVersion] = useState(0);
  const dialog = useRef<HTMLDialogElement>(null);
  const notify = useToast();

  if (!account?.roles.includes("owner"))
    return <Navigate to="/dashboard" replace />;

  const term = search.trim().toLocaleLowerCase("id-ID");
  const visible = members.filter(
    (member) =>
      (roleFilter === "all" || member.role === roleFilter) &&
      [member.name, member.username].some((value) =>
        value.toLocaleLowerCase("id-ID").includes(term),
      ),
  );

  function openForm(member: Member | null) {
    setEditing(member);
    setFormVersion((version) => version + 1);
    dialog.current?.showModal();
  }

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    const username = String(data.get("username")).trim().toLowerCase();
    if (!editing && members.some((member) => member.username === username)) {
      const field = form.elements.namedItem("username") as HTMLInputElement;
      field.setCustomValidity("Nama pengguna sudah dipakai.");
      field.reportValidity();
      return;
    }
    const next: Member = {
      username,
      name: String(data.get("name")).trim(),
      role: data.get("role") as Role,
      active: data.get("status") === "active",
    };
    setMembers((current) =>
      editing
        ? current.map((member) =>
            member.username === username ? next : member,
          )
        : [next, ...current],
    );
    setSearch("");
    setRoleFilter("all");
    dialog.current?.close();
    notify("Pengguna contoh berhasil disimpan.");
  }

  return (
    <div className="access-page">
      <header className="access-heading">
        <div>
          <p className="access-eyebrow">KEAMANAN & PERAN</p>
          <h1>Pengguna &amp; akses</h1>
          <p>Kelola direktori staf operasional toko, penugasan peran, serta cakupan hak akses sistem.</p>
        </div>
        <button className="access-primary" onClick={() => openForm(null)}>
          <Plus size={16} aria-hidden="true" />
          Tambah pengguna contoh
        </button>
      </header>

      <section className="access-account" aria-labelledby="access-session">
        <div className="access-account-top">
          <div>
            <p className="access-eyebrow">SESI AKTIF</p>
            <h2 id="access-session">Akses akun Anda</h2>
            <p>
              <strong>{account.name}</strong> · @{account.username} · Peran:{" "}
              <strong>{account.roles.join(", ").toUpperCase()}</strong>
            </p>
          </div>
          <span className="access-badge">
            <ShieldCheck size={16} />
            {account.permissions.length} izin aktif
          </span>
        </div>
        <details>
          <summary>Lihat daftar izin akun ini ({account.permissions.length})</summary>
          {account.permissions.length ? (
            <ul>
              {account.permissions.map((permission) => (
                <li key={permission}>
                  {permissionLabels[permission] ?? permission}
                </li>
              ))}
            </ul>
          ) : (
            <p>Belum ada izin khusus pada sesi ini.</p>
          )}
        </details>
      </section>

      <section
        className="access-directory"
        aria-labelledby="access-directory-title"
      >
        <header className="access-directory-header">
          <div>
            <h2 id="access-directory-title">Direktori pengguna contoh</h2>
            <p>
              Perubahan berlaku selama halaman ini terbuka. Akun login dan izin
              toko dikelola terpisah.
            </p>
          </div>
          <span className="access-count" role="status">
            {visible.length} pengguna
          </span>
        </header>
        <div className="access-controls">
          <label className="access-search workspace-search">
            <Search size={16} aria-hidden="true" />
            <input
              aria-label="Cari pengguna"
              type="search"
              placeholder="Cari nama atau username…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </label>
          <SelectMenu
            className="access-role-select"
            ariaLabel="Filter peran"
            icon={<UsersIcon size={15} />}
            value={roleFilter}
            options={[
              { value: "all", label: "Semua peran" },
              ...roles.map((role) => ({ value: role, label: role })),
            ]}
            onChange={setRoleFilter}
          />
        </div>
        <div
          className="access-scroll"
          tabIndex={0}
          role="region"
          aria-label="Tabel pengguna contoh"
        >
          <table>
            <thead>
              <tr>
                <th>PENGGUNA</th>
                <th>PERAN</th>
                <th>STATUS</th>
                <th>
                  <span className="sr-only">Tindakan</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {visible.map((member) => (
                <tr key={member.username}>
                  <td>
                    <strong>{member.name}</strong>
                    <small>@{member.username}</small>
                  </td>
                  <td>
                    <span className="access-role-pill">{member.role}</span>
                  </td>
                  <td>
                    <span
                      className="access-status"
                      data-active={member.active}
                    >
                      <i />
                      {member.active ? "Aktif" : "Nonaktif"}
                    </span>
                  </td>
                  <td>
                    <button
                      type="button"
                      className="access-edit-button"
                      aria-label={`Edit ${member.name}`}
                      onClick={() => openForm(member)}
                    >
                      <Pencil size={14} />
                      Edit
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!visible.length && (
          <div className="access-empty">
            <p>Tidak ada pengguna yang cocok.</p>
            <button
              type="button"
              className="access-secondary"
              onClick={() => {
                setSearch("");
                setRoleFilter("all");
              }}
            >
              Hapus filter
            </button>
          </div>
        )}
      </section>

      <dialog
        className="access-dialog"
        ref={dialog}
        aria-labelledby="access-form-title"
      >
        <form key={formVersion} onSubmit={save}>
          <header className="access-dialog-header">
            <div>
              <p>FORMULIR PENGGUNA</p>
              <h2 id="access-form-title">
                {editing ? "Edit pengguna contoh" : "Tambah pengguna contoh"}
              </h2>
            </div>
            <button
              type="button"
              className="access-close-button"
              aria-label="Tutup form"
              onClick={() => dialog.current?.close()}
            >
              <X size={20} />
            </button>
          </header>
          <label>
            Nama lengkap
            <input
              name="name"
              required
              pattern={".*\\S.*"}
              maxLength={100}
              defaultValue={editing?.name}
              placeholder="Contoh: Dina Rahmawati"
            />
          </label>
          <label>
            Nama pengguna (username)
            <input
              name="username"
              required
              pattern="[a-zA-Z0-9._]+"
              title="Gunakan huruf, angka, titik, atau garis bawah."
              maxLength={50}
              readOnly={!!editing}
              defaultValue={editing?.username}
              placeholder="Contoh: dina.kasir"
              onInput={(e) => e.currentTarget.setCustomValidity("")}
            />
          </label>
          <label>
            Peran operasional
            <select name="role" defaultValue={editing?.role ?? "Kasir"}>
              {roles.map((role) => (
                <option key={role}>{role}</option>
              ))}
            </select>
          </label>
          <label>
            Status akun
            <select
              name="status"
              defaultValue={editing?.active === false ? "inactive" : "active"}
            >
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </label>
          <footer>
            <button
              type="button"
              className="access-secondary"
              onClick={() => dialog.current?.close()}
            >
              Batal
            </button>
            <button className="access-primary" type="submit">
              Simpan
            </button>
          </footer>
        </form>
      </dialog>
    </div>
  );
}
