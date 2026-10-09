import { Button } from "~/components/ui/button";
import { useHealthQuery } from "~/features/health/health.queries";

export function HealthStatus() {
  const healthQuery = useHealthQuery();

  if (healthQuery.isPending) {
    return (
      <p className="text-sm text-muted-foreground">
        Checking backend connection...
      </p>
    );
  }

  if (healthQuery.isError) {
    return (
      <div className="flex flex-wrap items-center gap-3">
        <p className="text-sm text-destructive">
          Backend connection unavailable.
        </p>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => healthQuery.refetch()}
          disabled={healthQuery.isFetching}
        >
          {healthQuery.isFetching ? "Checking..." : "Retry"}
        </Button>
      </div>
    );
  }

  return (
    <p className="text-sm text-muted-foreground">
      Backend connected · HTTP {healthQuery.data.status}
    </p>
  );
}
