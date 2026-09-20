export function meta() {
  return [
    { title: "ATK Industry" },
    {
      name: "description",
      content: "Retail Management System",
    },
  ];
}

export default function Home() {
  return (
    <main className="min-h-screen p-8">
      <div className="mx-auto max-w-5xl">
        <h1 className="text-3xl font-semibold">ATK Industry</h1>
        <p className="mt-2 text-gray-600 dark:text-gray-400">
          Retail Management System frontend foundation.
        </p>
      </div>
    </main>
  );
}
