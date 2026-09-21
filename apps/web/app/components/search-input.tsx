import type { ComponentProps } from "react";
import { Search } from "lucide-react";

import { Input } from "~/components/ui/input";

type SearchInputProps = ComponentProps<typeof Input>;

export function SearchInput({
  className,
  ...props
}: SearchInputProps) {
  return (
    <div className="relative">
      <Search
        aria-hidden="true"
        className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
      />

      <Input
        type="search"
        className={["pl-8", className].filter(Boolean).join(" ")}
        {...props}
      />
    </div>
  );
}
