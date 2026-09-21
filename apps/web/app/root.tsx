import { QueryClientProvider } from "@tanstack/react-query";
import { queryClient } from "~/lib/query-client";
import {
  isRouteErrorResponse,
  Link,
  Links,
  Meta,
  Outlet,
  Scripts,
  ScrollRestoration,
} from "react-router";

import type { Route } from "./+types/root";
import "./app.css";

export function Layout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <head>
        <meta charSet="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <Meta />
        <Links />
      </head>
      <body>
        {children}
        <ScrollRestoration />
        <Scripts />
      </body>
    </html>
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Outlet />
    </QueryClientProvider>
  );
}

export function ErrorBoundary({ error }: Route.ErrorBoundaryProps) {
  const isNotFound = isRouteErrorResponse(error) && error.status === 404;

  let message = "Something went wrong.";
  let stack: string | undefined;

  if (isRouteErrorResponse(error) && !isNotFound) {
    message = error.statusText || message;
  } else if (import.meta.env.DEV && error instanceof Error) {
    message = error.message;
    stack = error.stack;
  }

  if (isNotFound) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-background p-6">
        <div className="w-full max-w-md text-center">
          <p className="text-sm font-semibold text-muted-foreground">404</p>

          <h1 className="mt-2 text-3xl font-semibold text-foreground">
            Page not found
          </h1>

          <p className="mt-3 text-muted-foreground">
            The page you requested could not be found.
          </p>

          <Link
            to="/"
            className="mt-6 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
          >
            Back to home
          </Link>
        </div>
      </main>
    );
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-background p-6">
      <div className="w-full max-w-2xl">
        <p className="text-sm font-semibold text-muted-foreground">
          Application error
        </p>

        <h1 className="mt-2 text-3xl font-semibold text-foreground">
          Something went wrong
        </h1>

        <p className="mt-3 text-muted-foreground">{message}</p>

        {stack && (
          <pre className="mt-6 overflow-x-auto rounded-lg bg-foreground p-4 text-sm text-background">
            <code>{stack}</code>
          </pre>
        )}

        <Link
          to="/"
          className="mt-6 inline-flex rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground"
        >
          Back to home
        </Link>
      </div>
    </main>
  );
}
