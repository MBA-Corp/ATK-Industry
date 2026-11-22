import type { ReactNode } from "react";

type EmptyStateProps = {
  title: string;
  description?: string;
  action?: ReactNode;
};

export function EmptyState({
  title,
  description,
  action,
}: EmptyStateProps) {
  return (
    <div className="mx-5 my-4 rounded-2xl border border-[#e6e6e6] bg-[#f7f7f5] p-8 text-center text-black">
      <h2 className="text-base font-semibold tracking-tight text-black">{title}</h2>

      {description && (
        <p className="mx-auto mt-2 max-w-md text-sm font-normal text-black">
          {description}
        </p>
      )}

      {action && <div className="mt-4 flex justify-center">{action}</div>}
    </div>
  );
}
