import { useId, useState } from "react";
import { formatRupiah } from "./dashboard.mock";

export function SalesChart({ values, labels }: {
  values: readonly number[];
  labels: readonly string[];
}) {
  const [active, setActive] = useState<number | null>(null);
  const gradientId = useId();
  const ceiling = Math.max(1000000, Math.ceil(Math.max(...values) / 1000000) * 1000000);
  const points = values.map((value, index) => ({
    x: 65 + index * (610 / Math.max(1, values.length - 1)),
    y: 190 - (value / ceiling) * 155,
  }));
  const line = points.map(({ x, y }, index) => `${index ? "L" : "M"} ${x} ${y}`).join(" ");

  return (
    <div className="sales-chart">
      <svg viewBox="0 0 710 235" role="img" aria-label={`Tren penjualan: ${labels.map((label, index) => `${label} ${formatRupiah(values[index])}`).join(", ")}`}>
        <defs>
          <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#4674f5" stopOpacity=".18" />
            <stop offset="100%" stopColor="#4674f5" stopOpacity="0" />
          </linearGradient>
        </defs>
        {[0, 1, 2, 3, 4].map((step) => (
          <g key={step}>
            <line x1="65" x2="675" y1={35 + step * 38.75} y2={35 + step * 38.75} stroke="#eef0f5" strokeDasharray="4 5" />
            <text x="0" y={39 + step * 38.75} fill="#969ba9" fontSize="11">
              {((ceiling * (4 - step)) / 4000000).toLocaleString("id-ID")} jt
            </text>
          </g>
        ))}
        <path d={`${line} L 675 190 L 65 190 Z`} fill={`url(#${gradientId})`} />
        <path d={line} fill="none" stroke="#4272f4" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
        {points.map((point, index) => (
          <g key={labels[index]}>
            <text x={point.x} y="222" textAnchor="middle" fontSize="11" fill="#969ba9">{labels[index]}</text>
            <circle cx={point.x} cy={point.y} r={active === index ? 6 : 4} fill="white" stroke="#4272f4" strokeWidth="2" />
          </g>
        ))}
      </svg>
      <div className="chart-inspect" aria-label="Rincian penjualan per periode">
        {labels.map((label, index) => (
          <button key={label} type="button" onMouseEnter={() => setActive(index)} onMouseLeave={() => setActive(null)} onFocus={() => setActive(index)} onBlur={() => setActive(null)} onClick={() => setActive(index)} aria-label={`${label}: ${formatRupiah(values[index])}`}>
            {label}
          </button>
        ))}
      </div>
      <p className="chart-detail" aria-live="polite">
        {active === null ? "Pilih periode pada grafik untuk melihat penjualan." : `${labels[active]} · ${formatRupiah(values[active])}`}
      </p>
    </div>
  );
}
