import type { TaskComment, TaskTable } from "../types";
import type { TaskTableDocument } from "./univer-adapter";
import { apiFetch } from "../lib/api";

export type TaskTableLockCode = "TABLE_LOCKED" | "TABLE_LOCK_LOST" | "TABLE_NOT_FOUND" | "TABLE_DOCUMENT_INVALID" | "TABLE_LOCK_RATE_LIMITED";

export class TaskTableApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
        readonly code: TaskTableLockCode | null,
    ) {
        super(message);
    }
}

export interface TaskTableContext {
    comments: TaskComment[];
    tables: TaskTable[];
}

function csrfHeaders(): Record<string, string> {
    const token = globalThis.document?.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    return token ? { "X-CSRF-TOKEN": token } : {};
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await apiFetch(url, {
        ...init,
        headers: { Accept: "application/json", ...csrfHeaders(), ...init.headers },
    });
    const body = await response.json().catch(() => null);
    if (!response.ok) {
        throw new TaskTableApiError(
            typeof body?.message === "string" ? body.message : "Não foi possível concluir a operação da tabela.",
            response.status,
            typeof body?.code === "string" ? body.code as TaskTableLockCode : null,
        );
    }
    return body?.data as T;
}

export function taskTableClient(projectId: string, taskId: string) {
    const base = `/api/v1/projects/${projectId}/tasks/${taskId}`;
    const json = (method: string, body?: unknown): RequestInit => ({
        method,
        headers: { "Content-Type": "application/json" },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    return {
        context: () => request<TaskTableContext>(`${base}/context`),
        publish: (document: TaskTableDocument) => request<TaskTable>(`${base}/tables`, json("POST", { document })),
        acquire: (tableId: string) => request<{ lock_token: string; expires_at: string }>(`${base}/tables/${tableId}/edit-lock`, json("POST")),
        renew: (tableId: string, lockToken: string) => request<{ lock_token: string; expires_at: string }>(`${base}/tables/${tableId}/edit-lock`, json("PUT", { lock_token: lockToken })),
        release: (tableId: string, lockToken: string) => request<void>(`${base}/tables/${tableId}/edit-lock`, json("DELETE", { lock_token: lockToken })),
        save: (tableId: string, document: TaskTableDocument, lockToken: string) => request<TaskTable>(`${base}/tables/${tableId}`, json("PUT", { document, lock_token: lockToken })),
        remove: (tableId: string, lockToken: string) => request<void>(`${base}/tables/${tableId}`, json("DELETE", { lock_token: lockToken })),
    };
}
