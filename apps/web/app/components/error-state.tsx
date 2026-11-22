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
    <div className="mx-5 my-4 rounded-2xl border border-black bg-[#efd4d4] p-6 text-black">
      <h2 className="text-base font-semibold text-black">{title}</h2>

      <p className="mt-2 text-sm text-black">
        {description}
      </p>

      {onRetry && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="mt-4 min-h-10 rounded-full border-black bg-white px-5 text-black hover:bg-black hover:text-white"
          onClick={onRetry}
          disabled={isRetrying}
        >
          {isRetrying ? "Memuat ulang..." : "Coba lagi"}
        </Button>
      )}
    </div>
  );
}
