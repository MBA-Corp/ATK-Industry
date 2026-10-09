import { useState, type ReactNode } from "react";
import { NavLink, Link, useLocation } from "react-router";
import {
  ArrowUpRight,
  Bell,
  Boxes,
  ChartNoAxesCombined,
  ChevronRight,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  ReceiptText,
  Settings2,
  Store,
  Truck,
  Users,
  X,
} from "lucide-react";
import "./dashboard.css";

const menus = [
  { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { label: "Produk", href: "/products", icon: Package },
  { label: "Persediaan", href: "/inventory", icon: Boxes },
  { label: "Pembelian", href: "/purchases", icon: ReceiptText },
  { label: "Supplier", href: "/suppliers", icon: Truck },
  { label: "Laporan", href: "/reports", icon: ChartNoAxesCombined },
];

export function DashboardShell({
  children,
  name,
  role,
  onLogout,
  isLoggingOut,
  logoutError,
}: {
  children: ReactNode;
  name: string;
  role: string;
  onLogout: () => void;
  isLoggingOut: boolean;
  logoutError: boolean;
}) {
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();
  const currentPage =
    menus.find((item) => item.href === location.pathname)?.label ??
    (location.pathname === "/users"
      ? "Pengguna & akses"
      : location.pathname === "/settings"
        ? "Pengaturan toko"
        : "Dashboard");
  const initials = name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("");
  return (
    <div className="dashboard-workspace" lang="id">
      <aside className={`dashboard-sidebar ${menuOpen ? "is-open" : ""}`}>
        <Link to="/dashboard" className="dashboard-brand">
          <span className="brand-mark">
            <ChartNoAxesCombined size={23} />
          </span>
          <span>
            ATK<span className="brand-light">industry</span>
            <small>RETAIL WORKSPACE</small>
          </span>
        </Link>
        <button
          className="dashboard-mobile-close dashboard-icon-button"
          aria-label="Tutup navigasi"
          onClick={() => setMenuOpen(false)}
        >
          <X size={20} />
        </button>
        <div className="dashboard-store">
          <span className="store-icon">
            <Store size={19} />
          </span>
          <span>
            <strong>Toko ATK</strong>
            <small>Ruang operasional toko</small>
          </span>
        </div>
        <p className="nav-caption">WORKSPACE</p>
        <nav aria-label="Navigasi utama" className="dashboard-nav">
          {menus.map(({ label, href, icon: Icon }) => (
            <NavLink
              key={href}
              to={href}
              onClick={() => setMenuOpen(false)}
              className={({ isActive }) => (isActive ? "active" : "")}
            >
              <Icon size={18} />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>
        <p className="nav-caption settings-caption">PENGELOLAAN</p>
        <nav aria-label="Pengelolaan toko" className="dashboard-nav">
          <Link to="/users">
            <Users size={18} />
            Pengguna & akses
          </Link>
          <Link to="/settings">
            <Settings2 size={18} />
            Pengaturan toko
          </Link>
        </nav>
        <div className="dashboard-sidebar-bottom">
          <div className="sidebar-note">
            <Store size={19} />
            <strong>Semua aktivitas, satu tempat.</strong>
            <p>Kelola toko dengan lebih teratur, setiap hari.</p>
            <Link to="/reports">
              Lihat laporan <ArrowUpRight size={15} />
            </Link>
          </div>
          <div className="sidebar-account">
            <span className="dashboard-avatar">{initials}</span>
            <span className="account-name">
              <strong>{name}</strong>
              <small>{role}</small>
            </span>
            <button
              className="dashboard-icon-button"
              onClick={onLogout}
              disabled={isLoggingOut}
              aria-label={isLoggingOut ? "Sedang keluar" : "Keluar dari akun"}
            >
              <LogOut size={17} />
            </button>
          </div>
          {logoutError && (
            <p role="alert" className="logout-error">
              Gagal keluar. Silakan coba lagi.
            </p>
          )}
        </div>
      </aside>
      <div className="dashboard-main">
        <header className="dashboard-topbar">
          <div className="dashboard-breadcrumb">
            <button
              className="dashboard-icon-button dashboard-mobile-toggle"
              aria-label={menuOpen ? "Tutup navigasi" : "Buka navigasi"}
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen(!menuOpen)}
            >
              <Menu size={20} />
            </button>
            <Store size={15} />
            <span>Workspace</span>
            <ChevronRight size={13} />
            <strong>{currentPage}</strong>
          </div>
          <div className="topbar-actions">
            <span className="dashboard-demo">
              <span />
              Data contoh
            </span>
            <a
              href={
                location.pathname === "/products"
                  ? "#product-catalog"
                  : location.pathname === "/inventory"
                    ? "#inventory-low-stock"
                    : location.pathname === "/purchases"
                      ? "#purchase-orders"
                      : location.pathname === "/reports"
                        ? "#report-transactions"
                        : "#stock-alerts"
              }
              className="dashboard-icon-button notification-button"
              aria-label="Lihat informasi"
            >
              <Bell size={19} />
              <i />
            </a>
            <span className="topbar-divider" />
            <span className="dashboard-avatar small">{initials}</span>
          </div>
        </header>
        <main id="main-content" className="dashboard-content">
          {children}
        </main>
        <footer className="dashboard-footer">
          <span>© 2026 ATK Industry</span>
          <span>Dibuat untuk keseharian toko Anda.</span>
        </footer>
      </div>
    </div>
  );
}
