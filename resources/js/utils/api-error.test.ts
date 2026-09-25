import { describe, expect, it } from "vitest";
import { apiErrorMessage } from "./api-error";

describe("apiErrorMessage", () => {
    it("translates the recurrence predecessor restriction into an actionable message", () => {
        expect(apiErrorMessage({ message: "RECURRENCE_DEPENDENCY_FORBIDDEN" }, "Falha.")).toContain(
            "não pode ser predecessora",
        );
        expect(apiErrorMessage({ code: "RECURRENCE_DEPENDENCY_FORBIDDEN", message: "technical" }, "Falha.")).toContain(
            "remova ou reorganize",
        );
    });

    it("keeps an API message or the caller fallback for other errors", () => {
        expect(apiErrorMessage({ message: "Regra inválida." }, "Falha.")).toBe("Regra inválida.");
        expect(apiErrorMessage(null, "Falha.")).toBe("Falha.");
    });
});
