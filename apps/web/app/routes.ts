import {
  type RouteConfig,
  index,
  layout,
  route,
} from "@react-router/dev/routes";

export default [
  ...(process.env.NODE_ENV === "development"
    ? [route("preview/dashboard", "routes/dashboard-preview.tsx")]
    : []),
  index("routes/home.tsx"),
  route("login", "routes/login.tsx"),

  layout("layouts/app-layout.tsx", [
    route("dashboard", "routes/dashboard.tsx"),
    route("pos", "routes/pos.tsx"),
    route("products", "routes/products.tsx"),
    route("inventory", "routes/inventory.tsx"),
    route("suppliers", "routes/suppliers.tsx"),
    route("purchases", "routes/purchases.tsx"),
    route("reports", "routes/reports.tsx"),
    route("users", "routes/users.tsx"),
    route("settings", "routes/settings.tsx"),
  ]),
] satisfies RouteConfig;
