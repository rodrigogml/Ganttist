import { describe, expect, it } from "vitest";
import { floatingPosition } from "./floating-position";

const viewport = { width: 360, height: 640 };

describe("floatingPosition", () => {
    it("flips above a trigger when there is no room below", () => {
        expect(floatingPosition(
            { left: 300, right: 340, top: 580, bottom: 620 },
            { width: 220, height: 180 },
            viewport,
        )).toEqual({ left: 132, top: 392 });
    });

    it("keeps a wide menu inside both horizontal viewport edges", () => {
        expect(floatingPosition(
            { left: 336, right: 352, top: 20, bottom: 44 },
            { width: 240, height: 100 },
            viewport,
            { placement: "bottom-end" },
        )).toEqual({ left: 112, top: 52 });
    });

    it("clamps an oversized menu to the viewport safety margin", () => {
        expect(floatingPosition(
            { left: 0, right: 20, top: 0, bottom: 20 },
            { width: 500, height: 900 },
            viewport,
        )).toEqual({ left: 8, top: 8 });
    });
});
