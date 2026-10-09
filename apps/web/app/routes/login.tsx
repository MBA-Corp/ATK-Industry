import { useState } from "react";
import { useForm } from "react-hook-form";
import { useToast } from "~/components/toast-provider";
import { zodResolver } from "@hookform/resolvers/zod";
import { Navigate, useNavigate } from "react-router";
import {
  ArrowRight,
  ChartNoAxesCombined,
  Eye,
  EyeOff,
  LoaderCircle,
  LockKeyhole,
  ShieldCheck,
  UserRound,
} from "lucide-react";
import { z } from "zod";
import {
  useCurrentUserQuery,
  useLoginMutation,
} from "~/features/auth/auth.queries";
import { ApiError } from "~/lib/api";
import "~/features/auth/login.css";

const schema = z.object({
  username: z.string().trim().min(1, "Masukkan username Anda."),
  password: z.string().min(1, "Masukkan password Anda."),
});
type LoginValues = z.infer<typeof schema>;

export function meta() {
  return [{ title: "Masuk | ATK Industry" }];
}

export default function Login() {
  const notify = useToast();
  const navigate = useNavigate();
  const currentUser = useCurrentUserQuery();
  const login = useLoginMutation();
  const [showPassword, setShowPassword] = useState(false);
  const form = useForm<LoginValues>({
    resolver: zodResolver(schema),
    defaultValues: { username: "", password: "" },
  });

  if (currentUser.data) return <Navigate to="/dashboard" replace />;

  async function submit(values: LoginValues) {
    form.clearErrors("root");
    try {
      await login.mutateAsync(values);
      notify("Anda berhasil masuk. Selamat datang kembali!");
      navigate("/dashboard", { replace: true });
    } catch (error) {
      const message =
        error instanceof ApiError && error.status === 422
          ? "Username atau password tidak sesuai. Silakan coba lagi."
          : error instanceof ApiError && error.status === 429
            ? "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."
            : "Belum dapat masuk. Periksa koneksi dan coba kembali.";
      form.setError("root", { message });
    }
  }

  return (
    <main className="login-page" lang="id">
      <div className="login-card">
        <section
          className="login-welcome"
          aria-label="Selamat datang di ATK Industry"
        >
          <div className="login-welcome-content">
            <span className="login-overline">SELAMAT DATANG DI</span>
            <div className="login-brand-icon">
              <ChartNoAxesCombined size={38} strokeWidth={1.8} />
            </div>
            <div className="login-brand">
              ATK<span>industry</span>
            </div>
            <h1>
              Toko terkelola.
              <br />
              Bisnis bertumbuh.
            </h1>
            <p>
              Satu tempat untuk memantau penjualan,
              <br />
              mengelola persediaan, dan menjalankan
              <br />
              keseharian toko Anda.
            </p>
          </div>
          <div className="login-welcome-footer">
            <ShieldCheck size={15} />
            Ruang kerja khusus tim Anda
          </div>
          <svg
            className="login-wave"
            viewBox="0 0 90 600"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path
              fill="white"
              opacity=".18"
              d="M15 0 Q5 60 40 110 T32 220 T43 340 T24 460 T10 600 H90 V0Z"
            />
            <path
              fill="white"
              opacity=".3"
              d="M35 0 Q20 60 55 110 T47 220 T58 340 T39 460 T25 600 H90 V0Z"
            />
            <path
              fill="white"
              d="M55 0 Q40 60 75 110 T67 220 T78 340 T59 460 T45 600 H90 V0Z"
            />
          </svg>
        </section>
        <section className="login-form-panel">
          <div className="login-form-heading">
            <span className="login-form-eyebrow">RETAIL WORKSPACE</span>
            <h2>Masuk ke akun Anda</h2>
            <p>Selamat datang kembali. Mari mulai hari ini.</p>
          </div>
          <form onSubmit={form.handleSubmit(submit)} noValidate>
            <div className="login-field">
              <label htmlFor="username">Username</label>
              <div className="login-input-wrap">
                <UserRound size={17} />
                <input
                  id="username"
                  autoComplete="username"
                  placeholder="Masukkan username"
                  disabled={login.isPending}
                  aria-invalid={Boolean(form.formState.errors.username)}
                  aria-describedby={
                    form.formState.errors.username
                      ? "username-error"
                      : undefined
                  }
                  {...form.register("username")}
                />
              </div>
              {form.formState.errors.username && (
                <p id="username-error" className="login-error">
                  {form.formState.errors.username.message}
                </p>
              )}
            </div>
            <div className="login-field">
              <label htmlFor="password">Password</label>
              <div className="login-input-wrap">
                <LockKeyhole size={17} />
                <input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  placeholder="Masukkan password"
                  disabled={login.isPending}
                  aria-invalid={Boolean(form.formState.errors.password)}
                  aria-describedby={
                    form.formState.errors.password
                      ? "password-error"
                      : undefined
                  }
                  {...form.register("password")}
                />
                <button
                  type="button"
                  className="login-reveal"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={
                    showPassword ? "Sembunyikan password" : "Tampilkan password"
                  }
                >
                  {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                </button>
              </div>
              {form.formState.errors.password && (
                <p id="password-error" className="login-error">
                  {form.formState.errors.password.message}
                </p>
              )}
            </div>
            {form.formState.errors.root && (
              <p className="login-error login-error-banner" role="alert">
                {form.formState.errors.root.message}
              </p>
            )}
            <button
              className="login-submit"
              type="submit"
              disabled={login.isPending}
            >
              {login.isPending ? (
                <>
                  <LoaderCircle className="login-spinner" size={17} />
                  Sedang masuk...
                </>
              ) : (
                <>
                  Masuk ke dashboard
                  <ArrowRight size={17} />
                </>
              )}
            </button>
          </form>
          <p className="login-help">
            Butuh bantuan akses?
            <br />
            <span>Hubungi pengelola toko Anda.</span>
          </p>
          <p className="login-copyright">
            © {new Date().getFullYear()} ATK Industry. All rights reserved.
          </p>
        </section>
      </div>
    </main>
  );
}
