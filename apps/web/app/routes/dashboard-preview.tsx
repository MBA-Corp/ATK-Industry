import { DashboardShell } from "~/features/dashboard/dashboard-shell";
import { DashboardOverview } from "~/features/dashboard/dashboard-overview";

export function meta() {
  return [{ title: "Pratinjau Dashboard | ATK Industry" }];
}

export default function DashboardPreview() {
  return (
    <DashboardShell
      name="Demo Owner"
      role="Pratinjau tampilan"
      onLogout={() => window.location.assign("/login")}
      isLoggingOut={false}
      logoutError={false}
    >
      <DashboardOverview />
    </DashboardShell>
  );
}
