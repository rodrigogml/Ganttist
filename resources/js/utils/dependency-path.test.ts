import { describe, expect, it } from "vitest";
import { dependencyPath, dependencyStub } from "./dependency-path";

describe("dependencyPath", () => {
    it("uses a forward final segment for an FS dependency on consecutive days", () => {
        expect(
            dependencyPath(
                { x: 42, y: 27 },
                { x: 42, y: 81 },
                { sourcePort: "finish", targetPort: "start", clearance: 8, rowHeight: 54 },
            ),
        ).toBe("M42,27 H50 V65 H34 V81 H42");
    });

    it("uses the short orthogonal route when opposing ports have horizontal space", () => {
        expect(
            dependencyPath(
                { x: 42, y: 27 },
                { x: 126, y: 81 },
                { sourcePort: "finish", targetPort: "start", clearance: 8, rowHeight: 54 },
            ),
        ).toBe("M42,27 H50 H84 V81 H118 H126");
    });

    it("keeps a finish port as the final approach for FF dependencies", () => {
        expect(
            dependencyPath(
                { x: 42, y: 81 },
                { x: 126, y: 27 },
                { sourcePort: "finish", targetPort: "finish", clearance: 8, rowHeight: 54 },
            ),
        ).toBe("M42,81 H50 H92 V27 H134 H126");
    });

    it("terminates a hidden relation at the side of its visible port", () => {
        expect(dependencyStub({ x: 42, y: 27 }, "finish", 24, "outgoing")).toEqual({
            stemPath: "M54,27 H66",
            arrowPath: "M42,27 H54",
            terminal: { x: 66, y: 27 },
        });
        expect(dependencyStub({ x: 42, y: 27 }, "start", 24, "incoming")).toEqual({
            stemPath: "",
            arrowPath: "M18,27 H42",
            terminal: { x: 18, y: 27 },
        });
    });
});
