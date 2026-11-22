import { useState } from "react";
import { useForm } from "react-hook-form";
import { useToast } from "~/components/toast-provider";
import { zodResolver } from "@hookform/resolvers/zod";
import { Navigate, useNavigate } from "react-router";
import { ArrowRight, Eye, EyeOff, LoaderCircle } from "lucide-react";
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
      <div className="login-container">
        <div className="login-brand">
          <span className="login-brand-mark" aria-hidden="true">
            A
          </span>
          <span className="login-brand-word">
            ATK<span className="login-brand-light">industry</span>
          </span>
        </div>

        <section className="login-box" aria-labelledby="login-heading">
          <header className="login-box-header">
            <span className="login-eyebrow">AUTENTIKASI AKUN</span>
            <h1 id="login-heading">Masuk ke dashboard</h1>
            <p>Masukkan username dan password akun toko Anda.</p>
          </header>

          <form
            className="login-form"
            onSubmit={form.handleSubmit(submit)}
            noValidate
          >
            <div className="login-field">
              <label htmlFor="username">Username</label>
              <div className="login-input-wrap">
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
                    showPassword
                      ? "Sembunyikan password"
                      : "Tampilkan password"
                  }
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
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
                  <LoaderCircle className="login-spinner" size={16} />
                  Sedang masuk...
                </>
              ) : (
                <>
                  Masuk
                  <ArrowRight size={16} />
                </>
              )}
            </button>
          </form>
        </section>

        <footer className="login-footer">
          <span>© {new Date().getFullYear()} ATK INDUSTRY</span>
          <span>·</span>
          <span>SISTEM MANAJEMEN TOKO</span>
        </footer>
      </div>
    </main>
  );
}
