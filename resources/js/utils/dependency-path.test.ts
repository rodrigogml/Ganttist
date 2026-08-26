import { describe, expect, it } from "vitest";
import { dependencyPath } from "./dependency-path";

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
});
