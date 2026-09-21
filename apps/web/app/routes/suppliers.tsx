export function meta() {
  return [
    { title: "Suppliers | ATK Industry" },
    {
      name: "description",
      content: "Supplier management",
    },
  ];
}

export default function Suppliers() {
  return (
    <section>
      <h1 className="text-3xl font-semibold">Suppliers</h1>
      <p className="mt-2 text-gray-600">
        Supplier management foundation.
      </p>
    </section>
  );
}
