import { useRef, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, MapPin, Phone } from "lucide-react";
import { useCurrentUserQuery } from "~/features/auth/auth.queries";
import { ApiError, apiJson } from "~/lib/api";
import "./settings.css";

type Profile = {
  name: string;
  phone: string;
  address: string;
  logo_url: string | null;
};
const endpoint = "/api/v1/company-profile";

export function meta() {
  return [{ title: "Pengaturan toko | ATK Industry" }];
}

export default function Settings() {
  const account = useCurrentUserQuery().data;
  const client = useQueryClient();
  const key = ["company-profile", account?.id];
  const query = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await apiJson<{ data: Profile | null }>(endpoint)).data,
    enabled: Boolean(account),
    refetchOnWindowFocus: false,
  });
  const mutation = useMutation({
    mutationFn: async (body: FormData) =>
      (await apiJson<{ data: Profile }>(endpoint, { method: "POST", body }))
        .data,
    onSuccess: (profile) => client.setQueryData(key, profile),
  });
  return (
    <div className="store-settings">
      <header className="store-settings-heading">
        <div>
          <p className="store-eyebrow">KONFIGURASI SISTEM</p>
          <h1>Pengaturan toko</h1>
          <p>
            Perbarui identitas resmi toko, kontak layanan, dan logo yang tampil
            pada struk kasir.
          </p>
        </div>
      </header>
      {query.isPending ? (
        <p className="store-status-card" role="status">
          Memuat profil toko…
        </p>
      ) : query.isError ? (
        <div className="store-error-card" role="alert">
          <span>Profil toko gagal dimuat.</span>
          <button
            type="button"
            className="store-secondary"
            onClick={() => query.refetch()}
          >
            Coba lagi
          </button>
        </div>
      ) : (
        <ProfileForm
          profile={query.data}
          editable={Boolean(
            account?.permissions.includes("company_profile.update"),
          )}
          pending={mutation.isPending}
          save={mutation.mutateAsync}
        />
      )}
    </div>
  );
}

function ProfileForm({
  profile,
  editable,
  pending,
  save,
}: {
  profile: Profile | null;
  editable: boolean;
  pending: boolean;
  save: (body: FormData) => Promise<Profile>;
}) {
  const [removeLogo, setRemoveLogo] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const file = useRef<HTMLInputElement>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editable || pending) return;
    const body = new FormData(event.currentTarget);
    body.set("_method", "PUT");
    if (!file.current?.files?.length) body.delete("logo");
    setError("");
    try {
      await save(body);
      if (file.current) file.current.value = "";
      setRemoveLogo(false);
      setDirty(false);
      setMessage("Profil toko berhasil disimpan.");
    } catch (cause) {
      const reasons =
        cause instanceof ApiError && cause.errors
          ? Object.values(cause.errors).flat()
          : [];
      setError(
        reasons.join(" ") ||
          "Profil gagal disimpan. Periksa koneksi dan izin akun, lalu coba lagi.",
      );
    }
  }

  return (
    <>
      <section className="store-preview-block" aria-label="Pratinjau identitas struk">
        <div>
          <p className="store-eyebrow">PRATINJAU IDENTITAS STRUK & LAPORAN</p>
          <h2>{profile?.name || "ATK Industry"}</h2>
          <div className="store-preview-meta">
            <span>
              <Phone size={15} />
              {profile?.phone || "Belum diatur"}
            </span>
            <span>
              <MapPin size={15} />
              {profile?.address || "Alamat belum diatur"}
            </span>
          </div>
        </div>
        <span className="store-preview-badge">
          <Building2 size={16} />
          {editable ? "Akses edit aktif" : "Mode baca"}
        </span>
      </section>

      <form
        onSubmit={submit}
        onChange={() => {
          setDirty(true);
          setMessage("");
          setError("");
        }}
      >
        <fieldset disabled={pending || !editable}>
          <section>
            <div>
              <h2>Identitas toko</h2>
              <p>Nama, telepon, dan alamat wajib diisi untuk header struk.</p>
            </div>
            <div className="store-fields">
              <label>
                Nama toko
                <input
                  name="name"
                  autoComplete="organization"
                  required
                  maxLength={255}
                  defaultValue={profile?.name}
                />
              </label>
              <label>
                Nomor telepon
                <input
                  name="phone"
                  type="tel"
                  autoComplete="tel"
                  required
                  maxLength={50}
                  defaultValue={profile?.phone}
                />
              </label>
              <label>
                Alamat toko
                <textarea
                  name="address"
                  autoComplete="street-address"
                  required
                  rows={3}
                  maxLength={2000}
                  defaultValue={profile?.address}
                />
              </label>
            </div>
          </section>
          <section>
            <div>
              <h2>Logo toko</h2>
              <p>Format JPG, PNG, atau WebP hingga 2 MB.</p>
            </div>
            <div className="store-fields">
              {profile?.logo_url && (
                <img
                  src={profile.logo_url}
                  alt="Logo toko saat ini"
                  width={96}
                  height={96}
                />
              )}
              {editable && (
                <label className="store-file-label">
                  {profile?.logo_url ? "Ganti logo" : "Unggah logo"}
                  <input
                    ref={file}
                    type="file"
                    name="logo"
                    disabled={removeLogo}
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(e) =>
                      e.currentTarget.setCustomValidity(
                        (e.currentTarget.files?.[0]?.size ?? 0) > 2097152
                          ? "Pilih gambar berukuran maksimal 2 MB."
                          : "",
                      )
                    }
                  />
                </label>
              )}
              {editable && profile?.logo_url && (
                <label className="store-check">
                  <input
                    type="checkbox"
                    name="remove_logo"
                    value="1"
                    checked={removeLogo}
                    onChange={(e) => {
                      setRemoveLogo(e.target.checked);
                      if (file.current) {
                        file.current.value = "";
                        file.current.setCustomValidity("");
                      }
                    }}
                  />
                  Hapus logo saat disimpan
                </label>
              )}
              {!editable && !profile?.logo_url && <p>Belum ada logo.</p>}
            </div>
          </section>
        </fieldset>
        <footer>
          {editable ? (
            <button
              className="store-save"
              type="submit"
              disabled={pending || !dirty}
            >
              {pending ? "Menyimpan…" : "Simpan perubahan"}
            </button>
          ) : (
            <p>Akun Anda memiliki akses baca.</p>
          )}
          <span className="store-status-text" role="status">
            {message ||
              (dirty && !pending && !error
                ? "Ada perubahan yang belum disimpan."
                : "Data profil sesuai dengan yang tersimpan.")}
          </span>
          {error && <span role="alert">{error}</span>}
        </footer>
      </form>
    </>
  );
}
