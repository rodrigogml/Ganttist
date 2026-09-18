import { afterEach, describe, expect, it, vi } from "vitest";
import { taskTableClient } from "./client";
import { createEmptyTaskTableDocument } from "./univer-adapter";

describe("taskTableClient", () => {
    afterEach(() => vi.unstubAllGlobals());

    it("sends the documented publish payload and returns its table block", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { id: "table-1", document_version: 1 } }), { status: 201 }));
        vi.stubGlobal("fetch", fetchMock);

        const table = await taskTableClient("project-1", "task-1").publish(createEmptyTaskTableDocument());

        expect(fetchMock).toHaveBeenCalledWith("/api/v1/projects/project-1/tasks/task-1/tables", expect.objectContaining({ method: "POST" }));
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toHaveProperty("document");
        expect(table).toMatchObject({ id: "table-1", document_version: 1 });
    });

    it("maps a lock conflict to a typed API error", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ code: "TABLE_LOCKED", message: "Ocupada" }), { status: 409 })));

        await expect(taskTableClient("project-1", "task-1").acquire("table-1")).rejects.toEqual(expect.objectContaining({ status: 409, code: "TABLE_LOCKED", message: "Ocupada" }));
    });
});
