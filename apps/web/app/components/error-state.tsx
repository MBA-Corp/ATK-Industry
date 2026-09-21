import { Button } from "~/components/ui/button";

type ErrorStateProps = {
  title?: string;
  description?: string;
  onRetry?: () => void;
  isRetrying?: boolean;
};

export function ErrorState({
  title = "Something went wrong",
  description = "The data could not be loaded.",
  onRetry,
  isRetrying = false,
}: ErrorStateProps) {
  return (
    <div className="rounded-lg border border-destructive/30 bg-card p-6 text-card-foreground">
      <h2 className="font-semibold">{title}</h2>

      <p className="mt-2 text-sm text-muted-foreground">
        {description}
      </p>

      {onRetry && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="mt-4"
          onClick={onRetry}
          disabled={isRetrying}
        >
          {isRetrying ? "Retrying..." : "Retry"}
        </Button>
      )}
    </div>
  );
}
