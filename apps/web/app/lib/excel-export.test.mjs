import assert from "node:assert/strict";
import test from "node:test";
import ExcelJS from "exceljs";
import { createExcelBuffer } from "./excel-export.ts";
import {
  reportTransactions,
  summarizeReport,
  transactionCost,
  transactionProfit,
  reportExcelData,
} from "../features/reports/report.mock.ts";

test("Excel export contains only a title and plain bordered cells with typed data", async () => {
  const buffer = await createExcelBuffer({
    title: "Uji ekspor",
    sheetName: "Penjualan",
    filename: "test",
    columns: [
      { title: "ID", width: 24 },
      { title: "Tanggal", width: 18, format: "dd mmm yyyy" },
      { title: "Total", width: 24, format: '"Rp" #,##0' },
    ],
    rows: [
      ["000123", new Date("2026-10-01T00:00:00Z"), 1234567],
      ["=1+1", new Date("2026-10-02T00:00:00Z"), 0],
    ],
  });
  const workbook = new ExcelJS.Workbook();
  await workbook.xlsx.load(buffer);
  const sheet = workbook.getWorksheet("Penjualan");
  assert.equal(sheet.getCell("A1").value, "Uji ekspor");
  assert.equal(sheet.getCell("A2").value, null);
  assert.equal(sheet.rowCount, 5);
  assert.equal(sheet.getCell("A4").value, "000123");
  assert.equal(sheet.getCell("A5").value, "=1+1");
  assert.equal(sheet.getCell("A5").type, ExcelJS.ValueType.String);
  assert.equal(
    sheet.getCell("B4").value.toISOString(),
    "2026-10-01T00:00:00.000Z",
  );
  assert.equal(sheet.getCell("C4").value, 1234567);
  assert.equal(sheet.getCell("C5").value, 0);
  assert.equal(sheet.getCell("C4").numFmt, '"Rp" #,##0');
  assert.equal(sheet.getTables().length, 0);
  assert.equal(sheet.autoFilter, undefined);
  assert.equal(sheet.views[0].state, "normal");
  for (let row = 3; row <= 5; row++) {
    for (let column = 1; column <= 3; column++) {
      const cell = sheet.getCell(row, column);
      for (const side of ["top", "bottom", "left", "right"])
        assert.equal(cell.border[side].style, "thin");
      assert.notEqual(cell.fill?.pattern, "solid");
    }
  }
  assert.equal(sheet.getColumn(3).width, 24);
  assert.equal(sheet.pageSetup.printTitlesRow, "3:3");
  assert.equal(sheet.headerFooter?.oddFooter, undefined);
});

test("Report totals use filtered rows and weighted margin, including zero revenue", async () => {
  const base = reportTransactions[0];
  const items = [
    {
      ...base,
      status: "Selesai",
      lines: [{ name: "A", quantity: 1, price: 100, cost: 50 }],
    },
    {
      ...base,
      status: "Selesai",
      lines: [{ name: "B", quantity: 1, price: 900, cost: 810 }],
    },
    {
      ...base,
      status: "Dibatalkan",
      lines: [{ name: "C", quantity: 1, price: 500, cost: 100 }],
    },
  ];
  const data = reportExcelData(items);
  const columns = Array.from({ length: 11 }, (_, index) => ({
    title: `Kolom ${index + 1}`,
    width: 22,
    format: index === 10 ? "0.00%" : "#,##0",
  }));
  const buffer = await createExcelBuffer({
    title: "Laporan",
    sheetName: "Laporan",
    filename: "test",
    columns,
    ...data,
  });
  const workbook = new ExcelJS.Workbook();
  await workbook.xlsx.load(buffer);
  const sheet = workbook.getWorksheet("Laporan");
  assert.equal(sheet.getCell("A7").value, "TOTAL");
  assert.equal(sheet.getCell("G7").result, 1500);
  assert.equal(sheet.getCell("H7").result, 1000);
  assert.equal(sheet.getCell("I7").result, 860);
  assert.equal(sheet.getCell("J7").result, 140);
  assert.equal(sheet.getCell("K7").result, 0.14);
  assert.equal(sheet.getCell("K7").formula, "IF(H7=0,0,J7/H7)");
  assert.equal(sheet.getCell("H7").formula, "SUM(H4:H6)");
  assert.equal(sheet.getCell("K4").numFmt, "0.00%");
  assert.equal(sheet.getCell("K6").result, 0);
  assert.equal(sheet.getCell("K7").font.bold, true);
  assert.equal(sheet.getCell("K7").border.bottom.style, "thin");
  for (const subset of [[], [items[2]]]) {
    const empty = reportExcelData(subset);
    assert.equal(empty.totals[10].result, 0);
  }
  const single = reportExcelData([items[1]]);
  assert.equal(single.totals[7].result, 900);
  assert.equal(single.totals[10].result, 0.1);
});

test("Exported profit reconciles to report totals and excludes cancelled sales", async () => {
  const item = {
    ...reportTransactions[0],
    status: "Selesai",
    lines: [{ name: "Produk uji", quantity: 3, price: 5000, cost: 2000 }],
  };
  assert.equal(transactionCost(item), 6000);
  assert.equal(transactionProfit(item), 9000);
  assert.equal(transactionProfit({ ...item, status: "Dibatalkan" }), 0);
  assert.equal(transactionCost({ ...item, status: "Dibatalkan" }), 0);
  const rows = reportTransactions.map((transaction) => [
    transaction.id,
    transactionCost(transaction),
    transactionProfit(transaction),
  ]);
  const buffer = await createExcelBuffer({
    title: "Laporan",
    sheetName: "Laporan",
    filename: "test",
    columns: [
      { title: "ID", width: 24 },
      { title: "Harga pokok penjualan", width: 26 },
      { title: "Laba kotor", width: 24 },
    ],
    rows,
  });
  const workbook = new ExcelJS.Workbook();
  await workbook.xlsx.load(buffer);
  const sheet = workbook.getWorksheet("Laporan");
  let totalProfit = 0;
  for (let row = 4; row <= sheet.rowCount; row++)
    totalProfit += sheet.getCell(row, 3).value;
  assert.equal(totalProfit, summarizeReport(reportTransactions).profit);
});
