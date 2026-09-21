import { HealthStatus } from "~/components/health-status";

export function meta() {
  return [
    { title: "Dashboard | ATK Industry" },
    {
      name: "description",
      content: "Retail Management System dashboard",
    },
  ];
}

export default function Dashboard() {
  return (
    <section className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Dashboard</h1>
        <p className="mt-2 text-muted-foreground">
          Application dashboard foundation.
        </p>
      </div>

      <div className="rounded-lg border bg-card p-4 text-card-foreground">
        <p className="mb-2 text-sm font-medium">
          Backend status
        </p>

        <HealthStatus />
      </div>
    </section>
  );
}
