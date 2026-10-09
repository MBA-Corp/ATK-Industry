import { useState } from "react";
import { ArrowDownLeft, ArrowUpRight, ArrowRight } from "lucide-react";
import { Link } from "react-router";
import type { DashboardPeriod } from "./dashboard.mock";
import {
  inventoryStockMovements,
  isMovementInPeriod,
} from "~/features/inventory/inventory.mock";

export function StockMovements({ period }: { period: DashboardPeriod }) {
  const [direction, setDirection] = useState<"in" | "out">("in");
  const periodMovements = inventoryStockMovements.filter((movement) =>
    isMovementInPeriod(movement, period),
  );
  const visibleMovements = periodMovements.filter(
    (movement) => movement.direction === direction,
  );

  return (
    <article className="dashboard-panel movement-panel">
      <div className="panel-heading">
        <div>
          <h2>Barang masuk & keluar</h2>
          <p>Pantau perubahan stok dan sumber pergerakannya</p>
        </div>
        <Link to="/inventory" className="dashboard-text-link">
          Lihat semua <ArrowUpRight size={14} />
        </Link>
      </div>
      <div className="movement-tabs" aria-label="Jenis pergerakan barang">
        {(["in", "out"] as const).map((type) => {
          const Icon = type === "in" ? ArrowDownLeft : ArrowUpRight;
          const count = periodMovements.filter(
            (movement) => movement.direction === type,
          ).length;
          return (
            <button
              key={type}
              type="button"
              className={`movement-tab ${type} ${direction === type ? "selected" : ""}`}
              aria-pressed={direction === type}
              onClick={() => setDirection(type)}
            >
              <span className="movement-tab-label">
                <Icon size={18} />
                Barang {type === "in" ? "masuk" : "keluar"}
              </span>
              <strong>
                {count}
                <small> aktivitas contoh</small>
              </strong>
            </button>
          );
        })}
      </div>
      <div className="movement-list" aria-live="polite">
        {visibleMovements.map((movement) => (
          <div className="movement-row" key={movement.id}>
            <span className={`movement-icon ${movement.direction}`}>
              {movement.direction === "in" ? (
                <ArrowDownLeft size={17} />
              ) : (
                <ArrowUpRight size={17} />
              )}
            </span>
            <div className="movement-product">
              <strong>{movement.product}</strong>
              <span>
                {movement.source} · {movement.reference}
              </span>
              <small>
                {movement.date.slice(8)}{" "}
                {movement.date.slice(5, 7) === "10" ? "Okt" : "Sep"} ·{" "}
                {movement.time}
              </small>
            </div>
            <span className={`movement-quantity ${movement.direction}`}>
              {movement.direction === "in" ? "+" : "−"}
              {movement.quantity}
              <small>{movement.unit}</small>
            </span>
          </div>
        ))}
        {visibleMovements.length === 0 && (
          <p className="movement-empty">
            Belum ada barang {direction === "in" ? "masuk" : "keluar"} pada
            periode ini.
          </p>
        )}
      </div>
      <Link to="/inventory" className="movement-footer">
        Lihat riwayat persediaan <ArrowRight size={14} />
      </Link>
    </article>
  );
}
