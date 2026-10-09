export type ExcelColumn = { title: string; width: number; format?: string };
export type ExcelValue =
  string | number | Date | { formula: string; result: number };
export type ExcelExport = {
  title: string;
  sheetName: string;
  filename: string;
  columns: ExcelColumn[];
  rows: ExcelValue[][];
  totals?: ExcelValue[];
};

export async function createExcelBuffer(options: ExcelExport) {
  const { default: ExcelJS } = await import("exceljs");
  const workbook = new ExcelJS.Workbook();
  workbook.creator = "ATK Industry";
  workbook.calcProperties.fullCalcOnLoad = true;
  const sheet = workbook.addWorksheet(options.sheetName, {
    views: [{ state: "normal", showGridLines: true }],
    pageSetup: {
      paperSize: 9,
      orientation: "landscape",
      fitToPage: true,
      fitToWidth: 1,
      fitToHeight: 0,
      printTitlesRow: "3:3",
    },
  });
  sheet.mergeCells(1, 1, 1, options.columns.length);
  sheet.getCell("A1").value = options.title;
  sheet.getCell("A1").font = {
    name: "Calibri",
    size: 18,
    bold: true,
    color: { argb: "FF000000" },
  };
  sheet.getRow(1).height = 32;
  sheet.getRow(2).height = 10;
  sheet.getRow(3).values = options.columns.map((column) => column.title);
  options.rows.forEach((row, index) => {
    sheet.getRow(index + 4).values = row;
  });
  const lastRow = options.rows.length + 3 + (options.totals ? 1 : 0);
  if (options.totals) sheet.getRow(lastRow).values = options.totals;
  for (let row = 3; row <= lastRow; row++) {
    for (let col = 1; col <= options.columns.length; col++) {
      sheet.getCell(row, col).border = {
        top: { style: "thin", color: { argb: "FF000000" } },
        bottom: { style: "thin", color: { argb: "FF000000" } },
        left: { style: "thin", color: { argb: "FF000000" } },
        right: { style: "thin", color: { argb: "FF000000" } },
      };
    }
  }
  options.columns.forEach((column, index) => {
    sheet.getColumn(index + 1).width = column.width;
    for (let row = 4; row <= lastRow; row++) {
      const cell = sheet.getCell(row, index + 1);
      cell.font = {
        name: "Calibri",
        size: 11,
        bold: !!options.totals && row === lastRow,
      };
      cell.alignment = {
        vertical: "middle",
        wrapText: true,
        horizontal:
          typeof cell.value === "number" ||
          cell.type === ExcelJS.ValueType.Formula
            ? "right"
            : "left",
      };
      if (column.format) cell.numFmt = column.format;
      sheet.getRow(row).height = 32;
    }
  });
  sheet.getRow(3).height = 30;
  sheet.getRow(3).eachCell((cell) => {
    cell.font = {
      name: "Calibri",
      size: 11,
      bold: true,
      color: { argb: "FF000000" },
    };
    cell.alignment = { vertical: "middle", wrapText: true };
  });
  sheet.pageSetup.printArea = `A1:${sheet.getColumn(options.columns.length).letter}${lastRow}`;
  return workbook.xlsx.writeBuffer();
}

export async function downloadExcel(options: ExcelExport) {
  const buffer = await createExcelBuffer(options);
  const url = URL.createObjectURL(
    new Blob([new Uint8Array(buffer)], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    }),
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = `${options.filename}.xlsx`;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
