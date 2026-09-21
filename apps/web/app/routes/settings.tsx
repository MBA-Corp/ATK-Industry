export function meta() {
  return [
    { title: "Settings | ATK Industry" },
    {
      name: "description",
      content: "Application settings",
    },
  ];
}

export default function Settings() {
  return (
    <section>
      <h1 className="text-3xl font-semibold">Settings</h1>
      <p className="mt-2 text-gray-600">
        Application settings foundation.
      </p>
    </section>
  );
}
