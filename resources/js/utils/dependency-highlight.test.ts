import { describe, expect, it } from "vitest";
import { dependencyHighlight } from "./dependency-highlight";

describe("dependencyHighlight", () => {
    const dependencies = [
        { id: "ab", from: "a", to: "b", type: "FS", critical: false },
        { id: "bc", from: "b", to: "c", type: "SS", critical: false },
        { id: "de", from: "d", to: "e", type: "FS", critical: false },
    ] as const;

    it("highlights only the direct precedents and dependents of the hovered task", () => {
        const result = dependencyHighlight(dependencies, "b");
        expect([...result.dependencyIds]).toEqual(["ab", "bc"]);
        expect([...result.taskIds].sort()).toEqual(["a", "b", "c"]);
    });

    it("does not highlight anything without a hovered task", () => {
        const result = dependencyHighlight(dependencies, null);
        expect(result.dependencyIds.size).toBe(0);
        expect(result.taskIds.size).toBe(0);
    });
});
