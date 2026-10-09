import { DashboardOverview } from "~/features/dashboard/dashboard-overview";

export function meta() {
  return [
    { title: "Dashboard | ATK Industry" },
    {
      name: "description",
      content: "Ringkasan penjualan dan persediaan toko ATK.",
    },
  ];
}

export default function Dashboard() {
  return <DashboardOverview />;
}
