import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { Navigate, useLocation, useNavigate } from "react-router";
import { z } from "zod";

import { Button } from "~/components/ui/button";
import { Input } from "~/components/ui/input";
import {
  useCurrentUserQuery,
  useLoginMutation,
} from "~/features/auth/auth.queries";
import { ApiError } from "~/services/api";

const loginSchema = z.object({
  login: z.string().trim().min(1, "Username or email is required."),
  password: z.string().min(1, "Password is required."),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function meta() {
  return [
    { title: "Login | ATK Industry" },
    {
      name: "description",
      content: "Login to the Retail Management System",
    },
  ];
}

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();

  const currentUserQuery = useCurrentUserQuery();
  const loginMutation = useLoginMutation();

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      login: "",
      password: "",
    },
  });

  const state = location.state as {
    from?: string;
  } | null;

  const redirectTo = state?.from ?? "/dashboard";

  if (currentUserQuery.data) {
    return <Navigate to="/dashboard" replace />;
  }

  async function handleSubmit(values: LoginFormValues) {
    try {
      await loginMutation.mutateAsync(values);

      navigate(redirectTo, {
        replace: true,
      });
    } catch (error) {
      if (error instanceof ApiError && error.status === 422 && error.errors) {
        const loginError = error.errors.login?.[0];
        const passwordError = error.errors.password?.[0];

        if (loginError) {
          form.setError("login", {
            type: "server",
            message: loginError,
          });
        }

        if (passwordError) {
          form.setError("password", {
            type: "server",
            message: passwordError,
          });
        }
      }
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 p-6">
      <div className="w-full max-w-md rounded-xl border bg-background p-6 shadow-sm">
        <div className="mb-6">
          <h1 className="text-2xl font-semibold">Sign in</h1>

          <p className="mt-2 text-sm text-muted-foreground">
            Sign in to the Retail Management System.
          </p>
        </div>

        <form className="space-y-4" onSubmit={form.handleSubmit(handleSubmit)}>
          <div className="space-y-2">
            <label htmlFor="login" className="text-sm font-medium">
              Username or email
            </label>

            <Input
              id="login"
              autoComplete="username"
              disabled={loginMutation.isPending}
              {...form.register("login")}
            />

            {form.formState.errors.login && (
              <p className="text-sm text-destructive">
                {form.formState.errors.login.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <label htmlFor="password" className="text-sm font-medium">
              Password
            </label>

            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              disabled={loginMutation.isPending}
              {...form.register("password")}
            />

            {form.formState.errors.password && (
              <p className="text-sm text-destructive">
                {form.formState.errors.password.message}
              </p>
            )}
          </div>

          {loginMutation.isError &&
            !(
              loginMutation.error instanceof ApiError &&
              loginMutation.error.status === 422
            ) && (
              <div
                role="alert"
                className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
              >
                {loginMutation.error instanceof ApiError
                  ? loginMutation.error.message
                  : "Unable to sign in. Please try again."}
              </div>
            )}

          <Button
            type="submit"
            className="w-full"
            disabled={loginMutation.isPending}
          >
            {loginMutation.isPending ? "Signing in..." : "Sign in"}
          </Button>
        </form>
      </div>
    </main>
  );
}
