export function LoadingScreen() {
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm"
      role="status"
      aria-live="polite"
      aria-label="Loading"
    >
      <div className="flex flex-col items-center gap-3">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-gray-950" />

        <p className="text-sm font-medium text-gray-600">
          Loading...
        </p>
      </div>
    </div>
  );
}
