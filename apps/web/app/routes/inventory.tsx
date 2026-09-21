export function meta() {
  return [
    { title: "Inventory | ATK Industry" },
    {
      name: "description",
      content: "Inventory management",
    },
  ];
}

export default function Inventory() {
  return (
    <section>
      <h1 className="text-3xl font-semibold">Inventory</h1>
      <p className="mt-2 text-gray-600">
        Inventory management foundation.
      </p>
    </section>
  );
}
