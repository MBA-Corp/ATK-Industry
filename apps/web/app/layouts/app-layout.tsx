import { useState } from "react";
import {
  Navigate,
  NavLink,
  Outlet,
  useLocation,
  useNavigate,
  useNavigation,
} from "react-router";

import { LoadingScreen } from "~/components/loading-screen";
import { Button } from "~/components/ui/button";
import {
  useCurrentUserQuery,
  useLogoutMutation,
} from "~/features/auth/auth.queries";

const navigationItems = [
  { label: "Dashboard", to: "/dashboard" },
  { label: "POS", to: "/pos" },
  { label: "Products", to: "/products" },
  { label: "Inventory", to: "/inventory" },
  { label: "Suppliers", to: "/suppliers" },
  { label: "Purchases", to: "/purchases" },
  { label: "Reports", to: "/reports" },
  { label: "Users", to: "/users" },
  { label: "Settings", to: "/settings" },
];

function Navigation({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <nav className="space-y-1">
      {navigationItems.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          onClick={onNavigate}
          className={({ isActive }) =>
            [
              "block rounded-md px-3 py-2 text-sm font-medium",
              isActive
                ? "bg-muted text-foreground"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            ].join(" ")
          }
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  );
}

export default function AppLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  const location = useLocation();
  const navigate = useNavigate();
  const navigation = useNavigation();

  const currentUserQuery = useCurrentUserQuery();
  const logoutMutation = useLogoutMutation();

  const isNavigating = navigation.state !== "idle";

  if (currentUserQuery.isPending) {
    return <LoadingScreen />;
  }

  if (currentUserQuery.isError) {
    return (
      <main className="flex min-h-screen items-center justify-center p-6">
        <div className="max-w-md space-y-4 text-center">
          <h1 className="text-xl font-semibold">Unable to load your session</h1>

          <p className="text-sm text-muted-foreground">
            The application could not communicate with the backend.
          </p>

          <Button type="button" onClick={() => currentUserQuery.refetch()}>
            Retry
          </Button>
        </div>
      </main>
    );
  }

  if (!currentUserQuery.data) {
    return (
      <Navigate
        to="/login"
        replace
        state={{
          from: location.pathname,
        }}
      />
    );
  }

  const user = currentUserQuery.data;

  async function handleLogout() {
    try {
      await logoutMutation.mutateAsync();

      navigate("/login", {
        replace: true,
      });
    } catch {
      // The mutation exposes its error state.
      // UI feedback can be improved later with toast.
    }
  }

  return (
    <div className="min-h-screen bg-background text-foreground">
      {isNavigating && <LoadingScreen />}

      <header className="sticky top-0 z-30 border-b bg-background">
        <div className="flex h-16 items-center gap-4 px-4 md:px-6">
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="md:hidden"
            onClick={() => setIsSidebarOpen(true)}
            aria-label="Open navigation"
          >
            Menu
          </Button>

          <span className="text-lg font-semibold">ATK Industry</span>

          <div className="ml-auto flex items-center gap-3">
            <div className="hidden text-right sm:block">
              <p className="text-sm font-medium">{user.name}</p>

              <p className="text-xs text-muted-foreground">{user.username}</p>
            </div>

            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={logoutMutation.isPending}
              onClick={handleLogout}
            >
              {logoutMutation.isPending ? "Signing out..." : "Logout"}
            </Button>
          </div>
        </div>
      </header>

      <div className="flex min-h-[calc(100vh-4rem)]">
        <aside className="hidden w-64 shrink-0 border-r bg-background p-4 md:block">
          <Navigation />
        </aside>

        <main className="min-w-0 flex-1 p-4 md:p-6">
          <Outlet />
        </main>
      </div>

      {isSidebarOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            className="absolute inset-0 bg-black/40"
            onClick={() => setIsSidebarOpen(false)}
            aria-label="Close navigation"
          />

          <aside className="relative h-full w-72 max-w-[85vw] border-r bg-background p-4 shadow-xl">
            <div className="mb-4 flex items-center justify-between gap-4">
              <span className="font-semibold">ATK Industry</span>

              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setIsSidebarOpen(false)}
              >
                Close
              </Button>
            </div>

            <Navigation onNavigate={() => setIsSidebarOpen(false)} />
          </aside>
        </div>
      )}
    </div>
  );
}
