import { useRef, useState, type ReactNode } from "react";
import { NavLink, Link, useLocation } from "react-router";
import {
  ArrowDown,
  Boxes,
  ChartNoAxesCombined,
  ChevronDown,
  ChevronRight,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  Settings2,
  Truck,
  Users,
  X,
} from "lucide-react";
import "./dashboard.css";

type DropdownKey = "inventory" | "procurement" | "reports" | null;

function resolveDropdownForPath(path: string): DropdownKey {
  if (path === "/inventory" || path === "/inventory/movements") {
    return "inventory";
  }
  if (path === "/purchases" || path === "/suppliers") {
    return "procurement";
  }
  if (path === "/reports") {
    return "reports";
  }
  return null;
}

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
  const location = useLocation();
  const path = location.pathname.replace(/\/$/, "");
  const viewParam = new URLSearchParams(location.search).get("view");

  const [menuOpen, setMenuOpen] = useState(false);
  // Single active dropdown state: opening one forces all other dropdowns to close
  const [dropdownState, setDropdownState] = useState<{
    forPath: string;
    open: DropdownKey;
  }>(() => ({
    forPath: path,
    open: resolveDropdownForPath(path),
  }));
  const openDropdown =
    dropdownState.forPath === path
      ? dropdownState.open
      : resolveDropdownForPath(path);

  function setOpenDropdown(next: DropdownKey) {
    setDropdownState({ forPath: path, open: next });
  }

  const toggleRef = useRef<HTMLButtonElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);

  function closeMenu() {
    setMenuOpen(false);
    toggleRef.current?.focus();
  }

  function toggleDropdown(target: Exclude<DropdownKey, null>) {
    setOpenDropdown(openDropdown === target ? null : target);
  }

  const isInventoryActive =
    path === "/inventory" || path === "/inventory/movements";
  const isMovementsView =
    path === "/inventory/movements" ||
    (path === "/inventory" && viewParam === "movements");
  const isStockConditionView = path === "/inventory" && !isMovementsView;

  const isProcurementActive = path === "/purchases" || path === "/suppliers";
  const isReportsActive = path === "/reports";
  const isReportsOverview = isReportsActive && !viewParam;
  const isReportsDaily = isReportsActive && viewParam === "daily";
  const isReportsTransactions = isReportsActive && viewParam === "transactions";
  const isReportsRefunds = isReportsActive && viewParam === "refunds";

  const inventoryOpen = openDropdown === "inventory";
  const procurementOpen = openDropdown === "procurement";
  const reportsOpen = openDropdown === "reports";

  const currentPage =
    path === "/dashboard" || path === "/preview/dashboard"
      ? "Dashboard"
      : path === "/products"
        ? "Produk"
        : isMovementsView
          ? "Riwayat pergerakan stok"
          : isStockConditionView
            ? "Kondisi stok"
            : path === "/purchases"
              ? "Pembelian"
              : path === "/suppliers"
                ? "Supplier"
                : isReportsDaily
                  ? "Daftar barang terjual harian"
                  : isReportsTransactions
                    ? "Rincian transaksi"
                    : isReportsRefunds
                      ? "Refund produk pembeli"
                      : isReportsOverview
                        ? "Laporan penjualan"
                        : path === "/users"
                          ? "Pengguna & akses"
                          : path === "/settings"
                            ? "Pengaturan toko"
                            : "Dashboard";

  const sectionLink =
    path === "/dashboard" || path === "/preview/dashboard"
      ? { href: "#stock-alerts", label: "Stok menipis" }
      : path === "/products"
        ? { href: "#product-catalog", label: "Daftar produk" }
        : isMovementsView
          ? { href: "#inventory-history", label: "Tabel riwayat stok" }
          : isStockConditionView
            ? { href: "#inventory-low-stock", label: "Tabel kondisi stok" }
            : path === "/purchases"
              ? { href: "#purchase-orders", label: "Daftar pembelian" }
              : path === "/suppliers"
                ? { href: "#supplier-list-title", label: "Daftar supplier" }
                : isReportsDaily
                  ? { href: "#daily-sold-items", label: "Tabel terjual harian" }
                  : isReportsTransactions
                    ? { href: "#report-transactions", label: "Tabel transaksi" }
                    : isReportsRefunds
                      ? { href: "#report-refunds", label: "Tabel refund pembeli" }
                      : isReportsOverview
                        ? { href: "#report-summary-section", label: "Ringkasan laporan" }
                        : path === "/users"
                          ? { href: "#access-directory-title", label: "Direktori pengguna" }
                          : undefined;

  const initials = name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("");

  return (
    <div className="dashboard-workspace" lang="id">
      {menuOpen && (
        <div
          className="dashboard-sidebar-backdrop"
          aria-hidden="true"
          onClick={closeMenu}
        />
      )}
      <aside
        id="owner-navigation"
        onKeyDown={(event) => {
          if (menuOpen && event.key === "Escape") closeMenu();
        }}
        className={`dashboard-sidebar ${menuOpen ? "is-open" : ""}`}
      >
        <div className="dashboard-sidebar-header">
          <Link
            to="/dashboard"
            onClick={() => {
              setMenuOpen(false);
              setOpenDropdown(null);
            }}
            className="dashboard-brand"
          >
            <span className="brand-mark">
              <ChartNoAxesCombined size={19} />
            </span>
            <span>
              ATK<span className="brand-light">industry</span>
            </span>
          </Link>
          <button
            type="button"
            className="dashboard-mobile-close dashboard-icon-button"
            aria-label="Tutup navigasi"
            ref={closeRef}
            onClick={closeMenu}
          >
            <X size={18} />
          </button>
        </div>

        <p className="nav-caption">Operasional</p>
        <nav aria-label="Navigasi utama" className="dashboard-nav">
          <NavLink
            to="/dashboard"
            onClick={() => {
              setMenuOpen(false);
              setOpenDropdown(null);
            }}
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <LayoutDashboard size={16} />
            <span>Dashboard</span>
          </NavLink>

          <NavLink
            to="/products"
            onClick={() => {
              setMenuOpen(false);
              setOpenDropdown(null);
            }}
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <Package size={16} />
            <span>Produk</span>
          </NavLink>

          {/* Dropdown Persediaan: Kondisi stok & Riwayat pergerakan stok */}
          <div className={`nav-group ${inventoryOpen ? "is-expanded" : ""}`}>
            <button
              type="button"
              className={`nav-group-trigger ${isInventoryActive ? "active" : ""}`}
              aria-expanded={inventoryOpen}
              onClick={() => toggleDropdown("inventory")}
            >
              <Boxes size={16} />
              <span>Persediaan</span>
              <ChevronDown
                size={14}
                className={`nav-group-chevron ${inventoryOpen ? "is-open" : ""}`}
              />
            </button>
            {inventoryOpen && (
              <div
                className="nav-submenu"
                role="group"
                aria-label="Submenu Persediaan"
              >
                <Link
                  to="/inventory"
                  onClick={() => setMenuOpen(false)}
                  className={isStockConditionView ? "sub-active" : ""}
                >
                  Kondisi stok
                </Link>
                <Link
                  to="/inventory?view=movements"
                  onClick={() => setMenuOpen(false)}
                  className={isMovementsView ? "sub-active" : ""}
                >
                  Riwayat pergerakan stok
                </Link>
              </div>
            )}
          </div>

          {/* Dropdown Pengadaan & Mitra: Pembelian & Supplier */}
          <div className={`nav-group ${procurementOpen ? "is-expanded" : ""}`}>
            <button
              type="button"
              className={`nav-group-trigger ${isProcurementActive ? "active" : ""}`}
              aria-expanded={procurementOpen}
              onClick={() => toggleDropdown("procurement")}
            >
              <Truck size={16} />
              <span>Pengadaan &amp; Mitra</span>
              <ChevronDown
                size={14}
                className={`nav-group-chevron ${procurementOpen ? "is-open" : ""}`}
              />
            </button>
            {procurementOpen && (
              <div
                className="nav-submenu"
                role="group"
                aria-label="Submenu Pengadaan & Mitra"
              >
                <Link
                  to="/purchases"
                  onClick={() => setMenuOpen(false)}
                  className={path === "/purchases" ? "sub-active" : ""}
                >
                  Pembelian
                </Link>
                <Link
                  to="/suppliers"
                  onClick={() => setMenuOpen(false)}
                  className={path === "/suppliers" ? "sub-active" : ""}
                >
                  Supplier
                </Link>
              </div>
            )}
          </div>

          {/* Dropdown Laporan: Laporan penjualan, Daftar barang terjual harian, Rincian transaksi */}
          <div className={`nav-group ${reportsOpen ? "is-expanded" : ""}`}>
            <button
              type="button"
              className={`nav-group-trigger ${isReportsActive ? "active" : ""}`}
              aria-expanded={reportsOpen}
              onClick={() => toggleDropdown("reports")}
            >
              <ChartNoAxesCombined size={16} />
              <span>Laporan</span>
              <ChevronDown
                size={14}
                className={`nav-group-chevron ${reportsOpen ? "is-open" : ""}`}
              />
            </button>
            {reportsOpen && (
              <div
                className="nav-submenu"
                role="group"
                aria-label="Submenu Laporan"
              >
                <Link
                  to="/reports"
                  onClick={() => setMenuOpen(false)}
                  className={isReportsOverview ? "sub-active" : ""}
                >
                  Laporan penjualan
                </Link>
                <Link
                  to="/reports?view=daily"
                  onClick={() => setMenuOpen(false)}
                  className={isReportsDaily ? "sub-active" : ""}
                >
                  Daftar barang terjual harian
                </Link>
                <Link
                  to="/reports?view=transactions"
                  onClick={() => setMenuOpen(false)}
                  className={isReportsTransactions ? "sub-active" : ""}
                >
                  Rincian transaksi
                </Link>
                <Link
                  to="/reports?view=refunds"
                  onClick={() => setMenuOpen(false)}
                  className={isReportsRefunds ? "sub-active" : ""}
                >
                  Refund produk pembeli
                </Link>
              </div>
            )}
          </div>
        </nav>

        <p className="nav-caption settings-caption">Pengelolaan</p>
        <nav aria-label="Pengelolaan toko" className="dashboard-nav">
          <NavLink
            to="/users"
            onClick={() => {
              setMenuOpen(false);
              setOpenDropdown(null);
            }}
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <Users size={16} />
            <span>Pengguna &amp; akses</span>
          </NavLink>
          <NavLink
            to="/settings"
            onClick={() => {
              setMenuOpen(false);
              setOpenDropdown(null);
            }}
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <Settings2 size={16} />
            <span>Pengaturan toko</span>
          </NavLink>
        </nav>

        <div className="dashboard-sidebar-bottom">
          <div className="sidebar-account">
            <span className="dashboard-avatar">{initials}</span>
            <span className="account-name">
              <strong>{name}</strong>
              <small>{role}</small>
            </span>
            <button
              type="button"
              className="dashboard-icon-button"
              onClick={onLogout}
              disabled={isLoggingOut}
              aria-label={isLoggingOut ? "Sedang keluar" : "Keluar dari akun"}
            >
              <LogOut size={16} />
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
              type="button"
              className="dashboard-icon-button dashboard-mobile-toggle"
              aria-label={menuOpen ? "Tutup navigasi" : "Buka navigasi"}
              ref={toggleRef}
              aria-controls="owner-navigation"
              aria-expanded={menuOpen}
              onClick={() => {
                setMenuOpen(!menuOpen);
                if (!menuOpen)
                  requestAnimationFrame(() => closeRef.current?.focus());
              }}
            >
              <Menu size={18} />
            </button>
            <nav className="owner-trail" aria-label="Lokasi halaman">
              <Link to="/dashboard">Toko ATK</Link>
              <ChevronRight size={13} aria-hidden="true" />
              <strong aria-current="page">{currentPage}</strong>
            </nav>
          </div>
          <div className="topbar-actions">
            <span
              className={`dashboard-demo ${path === "/settings" ? "is-live" : ""}`}
            >
              <i aria-hidden="true" />
              {path === "/settings" ? "Profil toko aktif" : "Data contoh"}
            </span>
            {sectionLink && (
              <a href={sectionLink.href} className="owner-section-link">
                {sectionLink.label}
                <ArrowDown size={14} aria-hidden="true" />
              </a>
            )}
          </div>
        </header>
        <main id="main-content" className="dashboard-content">
          {children}
        </main>
        <footer className="dashboard-footer">
          <span>© 2026 ATK INDUSTRY · DASHBOARD OWNER</span>
          <span>SISTEM OPERASIONAL TOKO</span>
        </footer>
      </div>
    </div>
  );
}
