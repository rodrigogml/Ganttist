const recurrenceErrorMessages: Record<string, string> = {
    RECURRENCE_DEPENDENCY_FORBIDDEN:
        "Esta tarefa desbloqueia outra tarefa. Na versão atual, uma tarefa recorrente não pode ser predecessora; remova ou reorganize essa dependência antes de ativar a recorrência.",
};

export function apiErrorMessage(payload: unknown, fallback: string): string {
    if (!payload || typeof payload !== "object") return fallback;

    const { code, message } = payload as { code?: unknown; message?: unknown };
    const identifier = typeof code === "string" ? code : typeof message === "string" ? message : null;

    if (identifier && recurrenceErrorMessages[identifier]) {
        return recurrenceErrorMessages[identifier];
    }

    return typeof message === "string" ? message : fallback;
}
