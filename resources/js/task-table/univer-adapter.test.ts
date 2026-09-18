import { describe, expect, it } from "vitest";
import { createEmptyTaskTableDocument } from "./univer-adapter";

describe("createEmptyTaskTableDocument", () => {
    it("creates a native Univer workbook with one 5 by 5 sheet", () => {
        const document = createEmptyTaskTableDocument() as {
            sheetOrder: string[];
            sheets: Record<string, { rowCount: number; columnCount: number }>;
        };

        expect(document.sheetOrder).toHaveLength(1);
        expect(document.sheets[document.sheetOrder[0]]).toMatchObject({ rowCount: 5, columnCount: 5 });
    });
});
