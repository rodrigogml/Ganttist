import type { Dependency } from "../types";

export function dependencyHighlight(
    dependencies: readonly Dependency[],
    taskId: string | null,
) {
    const dependencyIds = new Set<string>();
    const taskIds = new Set<string>();
    if (!taskId) return { dependencyIds, taskIds };

    taskIds.add(taskId);
    for (const dependency of dependencies) {
        if (dependency.from !== taskId && dependency.to !== taskId) continue;
        dependencyIds.add(dependency.id);
        taskIds.add(dependency.from);
        taskIds.add(dependency.to);
    }

    return { dependencyIds, taskIds };
}
