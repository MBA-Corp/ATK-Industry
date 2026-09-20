export function meta() {
  return [
    { title: "Users | ATK Industry" },
    {
      name: "description",
      content: "User management",
    },
  ];
}

export default function Users() {
  return (
    <section>
      <h1 className="text-3xl font-semibold">Users</h1>
      <p className="mt-2 text-gray-600">
        User management foundation.
      </p>
    </section>
  );
}
