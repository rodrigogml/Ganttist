<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import AuthGate from "./AuthGate.vue";
import AccountPanel from "./AccountPanel.vue";
import ProjectMembersPanel from "./ProjectMembersPanel.vue";
import ProjectDashboard from "./ProjectDashboard.vue";
import HierarchyCombobox from "./HierarchyCombobox.vue";
import PersonCombobox from "./PersonCombobox.vue";
import { useAuthStore } from "./stores/auth";
import {
    unblockedTaskStatuses,
    useWorkspaceStore,
    workspaceTaskStatuses,
} from "./stores/workspace";
import type { Collaborator, Dependency, Task, TaskComment } from "./types";
import {
    barWidth,
    civilDate,
    civilDayOffset,
    visualTaskRange,
} from "./utils/timeline";
import { virtualWindow } from "./utils/virtual-window";
import { selectGanttRow } from "./utils/gantt-selection";
import {
    useTimeblockGesture,
    type ConnectGesture,
    type TimeEndpoint,
} from "./composables/useTimeblockGesture";
import {
    dependencyTypeFor,
    resizePreview,
    shiftCivilDate,
} from "./utils/timeblock-gesture";
const store = useWorkspaceStore();
const auth = useAuthStore();
const appearance = ref(false),
    textScale = ref<"compact" | "comfortable" | "large">("comfortable"),
    spacing = ref<"compact" | "comfortable" | "spacious">("comfortable");
const csrfHeaders = (): Record<string, string> => {
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    return token ? { "X-CSRF-TOKEN": token } : {};
};
const fetchWithSessionGuard = globalThis.fetch.bind(globalThis);
const sessionGuardedFetch = async (
    input: RequestInfo | URL,
    init?: RequestInit,
): Promise<Response> => {
    const response = await fetchWithSessionGuard(input, init);
    if ((response.status === 401 || response.status === 419) && auth.user)
        auth.expireSession();
    return response;
};
globalThis.fetch = sessionGuardedFetch;
let initializedUserId: string | null = null;
async function initializeWorkspace() {
    if (!auth.user || initializedUserId === auth.user.id) return;
    initializedUserId = auth.user.id;
    const activeProjectId = localStorage.getItem("ganttist.active-project-id");
    if (activeProjectId) await store.load(activeProjectId);
}
watch(
    () => auth.user?.id,
    () => {
        if (auth.user) initializeWorkspace();
        else initializedUserId = null;
    },
);
watch(
    () => store.workspace?.project.id,
    async (projectId) => {
        if (!projectId) return;
        await nextTick();
        measureGantt();
        const timeline = timelineElement.value;
        if (!timeline) return;
        const todayOffset = px(todayCivil());
        timeline.scrollLeft = Math.max(0, todayOffset - timelineViewport.value / 2);
        scrollLeft.value = timeline.scrollLeft;
    },
);
onMounted(async () => {
    loadColumnPreferences();
    document.addEventListener("pointerdown", closeFloatingMenusOnOutside);
    window.addEventListener("keydown", focusTaskSearchFromShortcut);
    const savedText = localStorage.getItem("ganttist.text-scale"),
        savedSpacing = localStorage.getItem("ganttist.spacing"),
        savedEditorWidth = Number(
            localStorage.getItem("ganttist.task-editor-width"),
        );
    if (
        savedText === "compact" ||
        savedText === "comfortable" ||
        savedText === "large"
    )
        textScale.value = savedText;
    if (
        savedSpacing === "compact" ||
        savedSpacing === "comfortable" ||
        savedSpacing === "spacious"
    )
        spacing.value = savedSpacing;
    editorPinned.value =
        localStorage.getItem("ganttist.task-editor-pinned") === "1";
    if (Number.isFinite(savedEditorWidth) && savedEditorWidth > 0)
        editorWidth.value = clampEditorWidth(savedEditorWidth);
    window.addEventListener("beforeunload", guardUnsavedTask);
    window.addEventListener("resize", handleViewportResize);
    await auth.bootstrap();
    await initializeWorkspace();
    await nextTick();
    measureGantt();
    if (typeof ResizeObserver !== "undefined" && timelineElement.value) {
        resizeObserver = new ResizeObserver(measureGantt);
        resizeObserver.observe(timelineElement.value);
        if (ganttHeadLeft.value) resizeObserver.observe(ganttHeadLeft.value);
    }
});
onUnmounted(() => {
    resizeObserver?.disconnect();
    if (scrollFrame !== null) cancelAnimationFrame(scrollFrame);
    window.removeEventListener("beforeunload", guardUnsavedTask);
    window.removeEventListener("resize", handleViewportResize);
    window.removeEventListener("keydown", focusTaskSearchFromShortcut);
    document.removeEventListener("pointerdown", closeFloatingMenusOnOutside);
    cancelTaskContextLongPress();
    cancelTimeblockGesture();
    stopEditorResize();
    stopTaskColumnResize();
    stopStructureDrag();
    if (globalThis.fetch === sessionGuardedFetch)
        globalThis.fetch = fetchWithSessionGuard;
});
watch([textScale, spacing], () => {
    localStorage.setItem("ganttist.text-scale", textScale.value);
    localStorage.setItem("ganttist.spacing", spacing.value);
});
type ToastKind = "success" | "error" | "info";
type AppNotification = { message: string; kind: ToastKind };
const drawer = ref(false),
    notices = ref(false),
    filters = ref(false),
    account = ref(false),
    calendarPanel = ref(false),
    historyPanel = ref(false),
    projectMenu = ref(false),
    projectLoading = ref(false),
    projects = ref<{ id: string; name: string }[]>([]),
    creationMenu = ref(false),
    deleting = ref(false),
    preserveContinuity = ref(true),
    deletionPreview = ref<{
        incoming: unknown[];
        outgoing: unknown[];
        continuity: unknown[];
    } | null>(null),
    toast = ref(""),
    toastKind = ref<ToastKind>("info"),
    scrollTop = ref(0),
    scrollLeft = ref(0),
    timelineViewport = ref(1000),
    viewportHeight = ref(620),
    timelineElement = ref<HTMLElement | null>(null),
    creationMenuElement = ref<HTMLElement | null>(null),
    creationTrigger = ref<HTMLElement | null>(null),
    ganttHeadLeft = ref<HTMLElement | null>(null),
    ganttCard = ref<HTMLElement | null>(null),
    hoveredTaskId = ref<string | null>(null),
    cursorTaskId = ref<string | null>(null),
    selectionAnchorId = ref<string | null>(null);
function showToast(message: string, kind: ToastKind = "info") {
    toast.value = message;
    toastKind.value = kind;
}
function handleNotification(notification: AppNotification) {
    showToast(notification.message, notification.kind);
    setTimeout(() => {
        if (toast.value === notification.message) toast.value = "";
    }, 4000);
}
let resizeObserver: ResizeObserver | null = null,
    scrollFrame: number | null = null;
const dependencyConfirmation = ref<{ action: "remove"; id: string } | null>(
    null,
);
type RelationDirection = "predecessor" | "dependent";
type RelationType = "FS" | "SS" | "FF" | "SF";
const relationModal = ref<{
        direction: RelationDirection;
        search: string;
        selectedId: string | null;
        type: RelationType | null;
    } | null>(null),
    relationBusy = ref(false);
const collaborators = ref<Collaborator[]>([]),
    taskComments = ref<TaskComment[]>([]),
    editorContextLoading = ref(false),
    commentDraft = ref(""),
    editingCommentId = ref<string | null>(null),
    commentEditDraft = ref(""),
    commentEditBaseline = ref(""),
    newPersonName = ref(""),
    newPersonEmail = ref(""),
    creatingPerson = ref(false);
const {
    active: timeGesture,
    mode: gestureMode,
    begin: beginTimeGesture,
    cancel: cancelGestureState,
    finish: finishGestureState,
} = useTimeblockGesture();
const timelinePlane = ref<HTMLElement | null>(null),
    undoDependencyId = ref<string | null>(null);
const taskDraft = ref<Task | null>(null);
const sectionDraft = ref<Task | null>(null);
const taskDraftBaseline = ref(""),
    closeConfirmation = ref(false),
    pendingTaskToOpen = ref<Task | null>(null),
    editorPinned = ref(false),
    editorWidth = ref(390),
    editorReturnTaskId = ref<string | null>(null);
const editorMinWidth = 390;
let editorResize: { originX: number; originWidth: number } | null = null;
type WorkspaceColumnId =
    "assignee" | "status" | "start" | "finish" | "comments";
const TASK_COLUMN_MIN = 278;
const workspaceColumns: ReadonlyArray<{
    id: WorkspaceColumnId;
    label: string;
    shortLabel: string;
    width: number;
}> = [
    { id: "assignee", label: "Responsável", shortLabel: "RESP.", width: 58 },
    { id: "status", label: "Status", shortLabel: "STATUS", width: 94 },
    { id: "start", label: "Data inicial", shortLabel: "INÍCIO", width: 92 },
    { id: "finish", label: "Data final", shortLabel: "DEADLINE", width: 92 },
    { id: "comments", label: "Comentários", shortLabel: "COMENT.", width: 76 },
];
const columnVisibility = ref<Record<WorkspaceColumnId, boolean>>({
    assignee: true,
    status: true,
    start: false,
    finish: false,
    comments: false,
});
const taskColumnWidth = ref(TASK_COLUMN_MIN),
    viewportWidth = ref(globalThis.innerWidth || 1440),
    columnsMenu = ref(false),
    columnPickerButton = ref<HTMLElement | null>(null),
    columnPickerMenu = ref<HTMLElement | null>(null),
    columnPickerPosition = ref({ top: 0, left: 0 });
const filterButton = ref<HTMLElement | null>(null),
    filterMenu = ref<HTMLElement | null>(null),
    searchInput = ref<HTMLInputElement | null>(null),
    taskContextMenuElement = ref<HTMLElement | null>(null),
    appearanceWrap = ref<HTMLElement | null>(null);
const taskContextMenu = ref<{ task: Task; x: number; y: number } | null>(null),
    taskContextBusy = ref(false),
    editorPriorityMenu = ref(false);
type StructureDrop = {
    targetId: string;
    parentId: string | null;
    beforeId: string | null;
    zone: "before" | "inside" | "after";
    valid: boolean;
    message: string;
};
type StructureDrag = { task: Task; x: number; y: number; drop: StructureDrop | null };
const structureDrag = ref<StructureDrag | null>(null);
const structureMoveBusy = ref(false);
const canMoveStructure = computed(
    () => store.workspace?.project.role !== "reader" && !structureMoveBusy.value,
);
const taskPriorityOptions: ReadonlyArray<{
    priority: 1 | 2 | 3 | 4;
    label: string;
    flag: "p1" | "p2" | "p3" | "p4";
}> = [
    { priority: 4, label: "Prioridade 1", flag: "p1" },
    { priority: 3, label: "Prioridade 2", flag: "p2" },
    { priority: 2, label: "Prioridade 3", flag: "p3" },
    { priority: 1, label: "Prioridade 4", flag: "p4" },
];
let taskContextLongPress: {
    task: Task;
    pointerId: number;
    x: number;
    y: number;
    timer: ReturnType<typeof setTimeout>;
} | null = null;
const allStatusesSelected = computed(
    () => store.statusFilters.length === workspaceTaskStatuses.length,
);
const assigneeFilterOptions = computed(() => {
    const options = new Map<string, string>();
    for (const task of store.workspace?.tasks ?? []) {
        if (task.kind !== "task") continue;
        const id = task.assignee_id ?? "__unassigned__";
        options.set(
            id,
            task.assignee ??
                (id === "__unassigned__"
                    ? "Sem responsável"
                    : "Responsável"),
        );
    }
    return [...options].sort(([, left], [, right]) =>
        left.localeCompare(right, "pt-BR"),
    );
});
const activeFilterFieldCount = computed(
    () =>
        Number(!allStatusesSelected.value) +
        Number(store.assigneeFilters.length > 0) +
        Number(Boolean(store.periodStart || store.periodEnd)),
);
const unblockedStatusesSelected = computed(
    () =>
        unblockedTaskStatuses.filter((status) =>
            store.statusFilters.includes(status),
        ).length,
);
const unblockedStatusesChecked = computed(
    () => unblockedStatusesSelected.value === unblockedTaskStatuses.length,
);
const unblockedStatusesIndeterminate = computed(
    () =>
        unblockedStatusesSelected.value > 0 && !unblockedStatusesChecked.value,
);
const taskColumnMax = computed(() =>
    Math.max(TASK_COLUMN_MIN, Math.floor(viewportWidth.value * 0.25)),
);
const visibleWorkspaceColumns = computed(() =>
    workspaceColumns.filter((column) => columnVisibility.value[column.id]),
);
const taskPaneWidth = computed(
    () =>
        taskColumnWidth.value +
        visibleWorkspaceColumns.value.reduce(
            (total, column) => total + column.width,
            0,
        ),
);
const taskGridTemplate = computed(() =>
    [
        `${taskColumnWidth.value}px`,
        ...visibleWorkspaceColumns.value.map((column) => `${column.width}px`),
    ].join(" "),
);
let taskColumnResize: { originX: number; originWidth: number } | null = null;
watch(
    columnVisibility,
    () => {
        persistColumnPreferences();
        void nextTick(measureGantt);
    },
    { deep: true },
);
const editableTaskSnapshot = (task: Task) =>
    JSON.stringify({
        title: task.title,
        description: task.description ?? "",
        start: task.start,
        finish: task.finish,
        completed: task.completed ?? task.status === "completed",
        effective_completion: task.effective_completion ?? null,
        priority: task.priority ?? 1,
        assignee_id: task.assignee_id ?? null,
        section_id: task.section_id ?? task.parent_id ?? null,
    });
const taskDraftDirty = computed(
    () =>
        Boolean(
            taskDraft.value &&
            taskDraftBaseline.value &&
            editableTaskSnapshot(taskDraft.value) !== taskDraftBaseline.value,
        ) ||
        Boolean(commentDraft.value.trim()) ||
        Boolean(
            editingCommentId.value &&
            commentEditDraft.value !== commentEditBaseline.value,
        ),
);
const isCreatingTask = computed(() => taskDraft.value?.id === "__new-task__");
const activeTask = computed(() => {
    if (sectionDraft.value) return null;
    if (taskDraft.value) return taskDraft.value;
    const id =
        store.selected.length === 1 ? store.selected[0] : cursorTaskId.value;
    const task = store.workspace?.tasks.find((item) => item.id === id);
    return task?.kind === "task" ? task : null;
});
const predecessorDependencies = computed(() =>
    (store.workspace?.dependencies ?? []).filter(
        (dependency) => dependency.to === activeTask.value?.id,
    ),
);
const dependentDependencies = computed(() =>
    (store.workspace?.dependencies ?? []).filter(
        (dependency) => dependency.from === activeTask.value?.id,
    ),
);
const normalizedRelationSearch = computed(
    () =>
        relationModal.value?.search
            .toLocaleLowerCase("pt-BR")
            .normalize("NFD")
            .replace(/\p{Diacritic}/gu, "")
            .trim() ?? "",
);
const relationCandidates = computed(() => {
    const query = normalizedRelationSearch.value;
    if (!query) return [];
    return (store.workspace?.tasks ?? [])
        .filter(
            (task) =>
                task.kind === "task" &&
                task.id !== activeTask.value?.id &&
                task.title
                    .toLocaleLowerCase("pt-BR")
                    .normalize("NFD")
                    .replace(/\p{Diacritic}/gu, "")
                    .includes(query),
        )
        .slice(0, 50);
});
const selectedRelationTask = computed(
    () =>
        store.workspace?.tasks.find(
            (task) => task.id === relationModal.value?.selectedId,
        ) ?? null,
);
const relationModalValidation = computed(() => {
    const modal = relationModal.value,
        current = activeTask.value,
        selected = selectedRelationTask.value;
    if (!modal?.type || !current || !selected) return "";
    const from = modal.direction === "predecessor" ? selected.id : current.id,
        to = modal.direction === "predecessor" ? current.id : selected.id;
    if (isExpandable(selected) && modal.direction === "dependent")
        return "Grupos podem ser somente predecessores.";
    if (
        (store.workspace?.dependencies ?? []).some(
            (item) =>
                item.from === from &&
                item.to === to &&
                item.type === modal.type,
        )
    )
        return "Essa dependência já existe.";
    if (wouldCreateClientCycle(from, to))
        return "Essa dependência criaria um ciclo no grafo.";
    return "";
});
const relationPreviewLabel = computed(() => {
    const type = relationModal.value?.type;
    return type
        ? (
              {
                  FS: "Término da predecessora → início da sucessora",
                  SS: "Início da predecessora → início da sucessora",
                  FF: "Término da predecessora → término da sucessora",
                  SF: "Início da predecessora → término da sucessora",
              } as Record<RelationType, string>
          )[type]
        : "";
});
function relationPreviewPath(type: RelationType) {
    const sourceX = type.startsWith("S") ? 20 : 120,
        targetX = type.endsWith("S") ? 220 : 320,
        mid = (sourceX + targetX) / 2;
    return `M${sourceX} 34 C${mid} 34 ${mid} 34 ${targetX} 34`;
}
function todayCivil() {
    const parts = new Intl.DateTimeFormat("en", {
        timeZone: store.workspace?.calendar?.timezone ?? "America/Sao_Paulo",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).formatToParts(new Date());
    const value = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? "";
    return `${value("year")}-${value("month")}-${value("day")}`;
}
function ensureCompletionDate() {
    if (activeTask.value?.completed && !activeTask.value.effective_completion)
        activeTask.value.effective_completion = todayCivil();
}
const gestureDates = computed(() =>
    timeGesture.value && timeGesture.value.kind !== "connect"
        ? [timeGesture.value.previewStart, timeGesture.value.previewFinish]
        : [],
);
const timelineStart = computed(() => {
    const dates = [
        todayCivil(),
        ...gestureDates.value,
        ...(store.workspace?.tasks ?? []).flatMap((task) =>
            [
                task.considered_start,
                task.considered_deadline,
                task.start,
                task.finish,
            ]
                .map(civilDate)
                .filter((date): date is string => date !== null),
        ),
    ].sort();
    const start = new Date(dates[0] + "T12:00:00");
    start.setDate(start.getDate() - 14);
    return start;
});
const timelineEnd = computed(() => {
    const dates = [
        todayCivil(),
        ...gestureDates.value,
        ...(store.workspace?.tasks ?? []).flatMap((task) =>
            [
                task.considered_start,
                task.considered_deadline,
                task.start,
                task.finish,
            ]
                .map(civilDate)
                .filter((date): date is string => date !== null),
        ),
    ].sort();
    const end = new Date(dates.at(-1)! + "T12:00:00");
    end.setDate(end.getDate() + 15);
    return end;
});
const days = computed(() => {
    const r: Date[] = [];
    for (
        let d = new Date(timelineStart.value);
        d < timelineEnd.value;
        d.setDate(d.getDate() + 1)
    )
        r.push(new Date(d));
    return r;
});
const dayWidth = computed(() =>
    store.zoom === "day" ? 64 : store.zoom === "week" ? 42 : 24,
);
const rowHeight = computed(() =>
    spacing.value === "compact"
        ? 44
        : spacing.value === "comfortable"
          ? 49
          : 54,
);
const px = (date: string | null) =>
    date
        ? civilDayOffset(timelineStart.value, new Date(date + "T12:00:00")) *
          dayWidth.value
        : 0;
const visualDates = (task: Task) =>
    visualTaskRange(
        task.considered_start ?? task.start,
        task.considered_deadline ?? task.finish,
        todayCivil(),
    );
const visualStart = (task: Task) => visualDates(task).start;
const visualFinish = (task: Task) => visualDates(task).finish;
const width = (task: Task) =>
    barWidth(visualDates(task).start, visualDates(task).finish, dayWidth.value);
const canDragTask = (task: Task) =>
    task.kind === "task" &&
    !task.completed &&
    !task.derived &&
    !isExpandable(task);
const canResizeTask = (task: Task) => canDragTask(task);
const canConnectFrom = (task: Task) => task.kind === "task";
const canConnectTo = (task: Task) =>
    task.kind === "task" && !isExpandable(task);
const visibleTasks = computed(() => {
    const all = new Map(store.tasks.map((task) => [task.id, task]));
    return store.tasks.filter((task) => {
        let parentId = task.parent_id;
        while (parentId) {
            if (store.hiddenGroups.has(parentId)) return false;
            parentId = all.get(parentId)?.parent_id;
        }
        return true;
    });
});
const taskIndexes = computed(
    () => new Map(visibleTasks.value.map((task, index) => [task.id, index])),
);
const virtualStart = computed(
    () =>
        virtualWindow(
            visibleTasks.value.length,
            rowHeight.value,
            scrollTop.value,
            viewportHeight.value,
        ).start,
);
const virtualEnd = computed(
    () =>
        virtualWindow(
            visibleTasks.value.length,
            rowHeight.value,
            scrollTop.value,
            viewportHeight.value,
        ).end,
);
const renderedTasks = computed(() =>
    visibleTasks.value.slice(virtualStart.value, virtualEnd.value),
);
const visibleDayRange = computed(() =>
    virtualWindow(
        days.value.length,
        dayWidth.value,
        scrollLeft.value,
        timelineViewport.value,
        8,
    ),
);
const renderedDays = computed(() =>
    days.value
        .slice(visibleDayRange.value.start, visibleDayRange.value.end)
        .map((date, offset) => ({
            date,
            index: visibleDayRange.value.start + offset,
        })),
);
const rowOffset = (id: string) =>
    (taskIndexes.value.get(id) ?? 0) * rowHeight.value;
const hierarchyTasks = computed(() => store.workspace?.tasks ?? []);
const expandableTaskIds = computed(() => {
    const ids = new Set(
        hierarchyTasks.value
            .filter((task) => task.has_children)
            .map((task) => task.id),
    );
    hierarchyTasks.value.forEach((task) => {
        if (task.parent_id) ids.add(task.parent_id);
    });
    return ids;
});
const isExpandable = (task: Task) => expandableTaskIds.value.has(task.id);
function taskPriorityLevel(task: Task): 1 | 2 | 3 | null {
    if (task.kind !== "task" || isExpandable(task)) return null;
    return task.priority === 4
        ? 1
        : task.priority === 3
            ? 2
            : task.priority === 2
              ? 3
              : null;
}
const taskAncestors = computed(() => {
    const byId = new Map(hierarchyTasks.value.map((task) => [task.id, task]));
    const ancestors = new Map<string, string[]>();
    for (const task of hierarchyTasks.value) {
        const lineage: string[] = [];
        const visited = new Set<string>();
        let parentId = task.parent_id ?? null;
        while (parentId && !visited.has(parentId)) {
            visited.add(parentId);
            lineage.unshift(parentId);
            parentId = byId.get(parentId)?.parent_id ?? null;
        }
        ancestors.set(task.id, lineage);
    }
    return ancestors;
});
const ancestorsFor = (task: Task) => taskAncestors.value.get(task.id) ?? [];
const nextSiblingTaskIds = computed(() => {
    const siblings = new Map<string, Task[]>();
    for (const task of hierarchyTasks.value) {
        const parentKey = task.parent_id ?? "__root__";
        const group = siblings.get(parentKey) ?? [];
        group.push(task);
        siblings.set(parentKey, group);
    }
    const ids = new Set<string>();
    for (const group of siblings.values())
        group.slice(0, -1).forEach((task) => ids.add(task.id));
    return ids;
});
const hasNextSibling = (task: Task) => nextSiblingTaskIds.value.has(task.id);
const isCurrentBranch = (task: Task, depth: number) =>
    depth === ancestorsFor(task).length - 1;
function ancestorSlotContinues(task: Task, depth: number) {
    const pathNodeId = ancestorsFor(task)[depth + 1];
    return Boolean(pathNodeId && nextSiblingTaskIds.value.has(pathNodeId));
}
const hoveredAncestorIds = computed(
    () =>
        new Set(
            hoveredTaskId.value
                ? (taskAncestors.value.get(hoveredTaskId.value) ?? [])
                : [],
        ),
);
const isHoveredAncestor = (task: Task) => hoveredAncestorIds.value.has(task.id);
type TreeSegment = "up" | "right" | "down";
const activeTreeRoute = computed(() => {
    const route = new Map<string, Map<number, Set<TreeSegment>>>(),
        targetId = hoveredTaskId.value;
    if (!targetId) return route;
    const path = [...(taskAncestors.value.get(targetId) ?? []), targetId],
        indexById = new Map(
            visibleTasks.value.map((task, index) => [task.id, index]),
        );
    const activate = (
        taskId: string,
        depth: number,
        ...segments: TreeSegment[]
    ) => {
        const byDepth =
                route.get(taskId) ?? new Map<number, Set<TreeSegment>>(),
            active = byDepth.get(depth) ?? new Set<TreeSegment>();
        segments.forEach((segment) => active.add(segment));
        byDepth.set(depth, active);
        route.set(taskId, byDepth);
    };
    for (let position = 1; position < path.length; position++) {
        const parentId = path[position - 1],
            childId = path[position],
            parentIndex = indexById.get(parentId),
            childIndex = indexById.get(childId),
            child = hierarchyTasks.value.find((task) => task.id === childId);
        if (parentIndex === undefined || childIndex === undefined || !child)
            continue;
        const depth = Math.max(0, child.level - 1);
        activate(parentId, depth, "down");
        for (
            let rowIndex = parentIndex + 1;
            rowIndex < childIndex;
            rowIndex++
        ) {
            const row = visibleTasks.value[rowIndex];
            if (row) activate(row.id, depth, "up", "down");
        }
        activate(childId, depth, "up", "right");
    }
    return route;
});
const isTreeSegmentActive = (task: Task, depth: number, segment: TreeSegment) =>
    activeTreeRoute.value.get(task.id)?.get(depth)?.has(segment) ?? false;
function activeBranchPath(task: Task, depth: number) {
    const up = isTreeSegmentActive(task, depth, "up"),
        right = isTreeSegmentActive(task, depth, "right");
    if (up && right) return "M11 0 V44 Q11 50 17 50 H22";
    if (up) return "M11 0 V50";
    return right ? "M11 50 H22" : "";
}
function activeVerticalPath(task: Task, depth: number, currentBranch: boolean) {
    const up = !currentBranch && isTreeSegmentActive(task, depth, "up"),
        down = isTreeSegmentActive(task, depth, "down");
    if (up && down) return "M11 0 V100";
    if (up) return "M11 0 V50";
    return down ? (currentBranch ? "M11 44 V100" : "M11 50 V100") : "";
}
function clampTaskColumnWidth(width: number) {
    return Math.max(
        TASK_COLUMN_MIN,
        Math.min(taskColumnMax.value, Math.round(width)),
    );
}
function loadColumnPreferences() {
    try {
        const storedVisibility = JSON.parse(
            localStorage.getItem("ganttist.workspace-columns") ?? "null",
        ) as Partial<Record<WorkspaceColumnId, boolean>> | null;
        if (storedVisibility)
            workspaceColumns.forEach((column) => {
                if (typeof storedVisibility[column.id] === "boolean")
                    columnVisibility.value[column.id] =
                        storedVisibility[column.id]!;
            });
        const storedWidth = Number(
            localStorage.getItem("ganttist.task-column-width"),
        );
        if (Number.isFinite(storedWidth) && storedWidth > 0)
            taskColumnWidth.value = clampTaskColumnWidth(storedWidth);
    } catch {
        localStorage.removeItem("ganttist.workspace-columns");
        localStorage.removeItem("ganttist.task-column-width");
    }
}
function persistColumnPreferences() {
    localStorage.setItem(
        "ganttist.workspace-columns",
        JSON.stringify(columnVisibility.value),
    );
    localStorage.setItem(
        "ganttist.task-column-width",
        String(taskColumnWidth.value),
    );
}
function toggleColumnsMenu(event: MouseEvent) {
    columnsMenu.value = !columnsMenu.value;
    if (columnsMenu.value) {
        const rect = (
            event.currentTarget as HTMLElement
        ).getBoundingClientRect();
        columnPickerPosition.value = {
            top: Math.round(rect.bottom + 6),
            left: Math.round(rect.left),
        };
        void nextTick(() =>
            columnPickerMenu.value
                ?.querySelector<HTMLElement>("input:not(:disabled)")
                ?.focus(),
        );
    }
}
function closeFloatingMenusOnOutside(event: PointerEvent) {
    const target = event.target as Node;
    if (
        columnsMenu.value &&
        !columnPickerButton.value?.contains(target) &&
        !columnPickerMenu.value?.contains(target)
    )
        columnsMenu.value = false;
    if (
        filters.value &&
        !filterButton.value?.contains(target) &&
        !filterMenu.value?.contains(target)
    )
        filters.value = false;
    if (
        taskContextMenu.value &&
        !taskContextMenuElement.value?.contains(target)
    )
        taskContextMenu.value = null;
    if (appearance.value && !appearanceWrap.value?.contains(target))
        appearance.value = false;
    if (
        creationMenu.value &&
        !creationMenuElement.value?.contains(target) &&
        !creationTrigger.value?.contains(target)
    )
        creationMenu.value = false;
}
function structureDescendsFrom(task: Task, ancestorId: string) {
    return task.id === ancestorId || ancestorsFor(task).includes(ancestorId);
}
function nextSiblingAfter(task: Task): string | null {
    const items = hierarchyTasks.value;
    let index = items.findIndex((item) => item.id === task.id);
    while (++index < items.length) {
        const candidate = items[index];
        if (candidate.level <= task.level) {
            return candidate.parent_id === task.parent_id ? candidate.id : null;
        }
    }
    return null;
}
function rootAncestor(task: Task): Task {
    const rootId = ancestorsFor(task)[0];
    return hierarchyTasks.value.find((item) => item.id === rootId) ?? task;
}
function structureDropClass(task: Task) {
    const drop = structureDrag.value?.drop;
    if (!drop || drop.targetId !== task.id) return "";
    return drop.valid ? `structure-drop-${drop.zone}` : "structure-drop-invalid";
}
function describeStructureDestination(parentId: string | null, beforeId: string | null, zone: StructureDrop["zone"]) {
    const parent = parentId ? hierarchyTasks.value.find((item) => item.id === parentId) : null;
    const before = beforeId ? hierarchyTasks.value.find((item) => item.id === beforeId) : null;
    if (zone === "inside" && parent) return `Dentro de “${parent.title}”`;
    if (before) return `Antes de “${before.title}”`;
    return parent ? `No fim de “${parent.title}”` : "No fim da raiz";
}
function projectStructureDrop(event: PointerEvent): StructureDrop | null {
    const source = structureDrag.value?.task;
    const row = document.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>(".task-row[data-task-id]");
    const targetId = row?.dataset.taskId;
    const target = hierarchyTasks.value.find((item) => item.id === targetId);
    if (!source || !row || !target) return null;
    const rect = row.getBoundingClientRect();
    const ratio = (event.clientY - rect.top) / rect.height;
    const zone: StructureDrop["zone"] = ratio < .25 ? "before" : ratio > .75 ? "after" : "inside";
    if (structureDescendsFrom(target, source.id)) {
        return { targetId: target.id, parentId: null, beforeId: null, zone, valid: false, message: "Não é permitido mover um item para dentro dele mesmo ou de sua descendência." };
    }
    if (zone === "inside") {
        if (target.kind !== "section") {
            return { targetId: target.id, parentId: null, beforeId: null, zone, valid: false, message: "Tarefas não podem conter itens." };
        }
        return { targetId: target.id, parentId: target.id, beforeId: null, zone, valid: true, message: describeStructureDestination(target.id, null, zone) };
    }
    const projectedDepth = Math.max(0, Math.min(target.level, Math.round((event.clientX - rect.left - 76) / 22)));
    const siblingTarget = projectedDepth === target.level
        ? target
        : hierarchyTasks.value.find((item) => item.id === ancestorsFor(target)[projectedDepth]) ?? rootAncestor(target);
    const parentId = projectedDepth === 0 ? null : ancestorsFor(target)[projectedDepth - 1] ?? null;
    const beforeId = zone === "before" ? siblingTarget.id : nextSiblingAfter(siblingTarget);
    return { targetId: target.id, parentId, beforeId, zone, valid: true, message: describeStructureDestination(parentId, beforeId, zone) };
}
function moveStructureDrag(event: PointerEvent) {
    if (!structureDrag.value) return;
    structureDrag.value = { ...structureDrag.value, x: event.clientX, y: event.clientY, drop: projectStructureDrop(event) };
}
async function finishStructureDrag(event: PointerEvent) {
    const drag = structureDrag.value;
    const drop = drag?.drop;
    stopStructureDrag();
    if (!drag || !drop?.valid || structureMoveBusy.value) return;
    const projectId = store.workspace?.project.id;
    if (!projectId) return;
    structureMoveBusy.value = true;
    try {
        const response = await fetch(`/api/v1/projects/${projectId}/structure/move`, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json", ...csrfHeaders() },
            body: JSON.stringify({ itemId: drag.task.id, itemKind: drag.task.kind, parentSectionId: drop.parentId, beforeItemId: drop.beforeId }),
        });
        if (!response.ok) throw new Error(await responseError(response, "Não foi possível reorganizar o item."));
        await store.load();
        showToast("Estrutura reorganizada", "success");
    } catch (error) {
        showToast(error instanceof Error ? error.message : "Não foi possível reorganizar o item.", "error");
    } finally {
        structureMoveBusy.value = false;
        setTimeout(() => (toast.value = ""), 3500);
    }
}
function stopStructureDrag() {
    document.removeEventListener("pointermove", moveStructureDrag);
    document.removeEventListener("pointerup", finishStructureDrag);
    document.removeEventListener("pointercancel", stopStructureDrag);
    structureDrag.value = null;
}
function startStructureDrag(task: Task, event: PointerEvent) {
    if (!canMoveStructure.value || event.button !== 0) return;
    event.preventDefault();
    event.stopPropagation();
    cancelTaskContextLongPress();
    structureDrag.value = { task, x: event.clientX, y: event.clientY, drop: null };
    document.addEventListener("pointermove", moveStructureDrag);
    document.addEventListener("pointerup", finishStructureDrag, { once: true });
    document.addEventListener("pointercancel", stopStructureDrag, { once: true });
}
function openCreationDialog(kind: "task" | "section") {
    creationMenu.value = false;
    if (kind === "task") {
        taskDraft.value = { id: "__new-task__", title: "", description: "", kind: "task", level: 0, parent_id: null, section_id: null, priority: 1, start: null, finish: null, completed: false, progress: 0, status: "opened", critical: false };
        taskDraftBaseline.value = editableTaskSnapshot(taskDraft.value);
        sectionDraft.value = null;
    } else {
        sectionDraft.value = { id: "__new-section__", title: "", kind: "section", level: 0, parent_id: null, start: null, finish: null, progress: 0, status: "opened", critical: false };
        taskDraft.value = null;
    }
    drawer.value = true;
    focusTaskTitle();
}
function focusTaskSearchFromShortcut(event: KeyboardEvent) {
    if (
        event.key.toLocaleLowerCase("pt-BR") !== "k" ||
        (!event.metaKey && !event.ctrlKey) ||
        event.altKey
    )
        return;
    event.preventDefault();
    searchInput.value?.focus();
}
function isRowContextTarget(target: EventTarget | null) {
    return (
        target instanceof HTMLElement &&
        !target.closest(
            "button,input,select,textarea,a,[data-context-menu-owner]",
        )
    );
}
function openTaskContextMenu(task: Task, x: number, y: number) {
    taskContextMenu.value = {
        task,
        x: Math.max(8, Math.min(x, globalThis.innerWidth - 228)),
        y: Math.max(8, Math.min(y, globalThis.innerHeight - 124)),
    };
}
function openTaskContextMenuFromMouse(task: Task, event: MouseEvent) {
    if (!isRowContextTarget(event.target)) return;
    event.preventDefault();
    cancelTaskContextLongPress();
    moveCursorTo(task);
    openTaskContextMenu(task, event.clientX, event.clientY);
}
function beginTaskContextLongPress(task: Task, event: PointerEvent) {
    if (
        event.pointerType !== "touch" ||
        !isRowContextTarget(event.target)
    )
        return;
    cancelTaskContextLongPress();
    taskContextLongPress = {
        task,
        pointerId: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        timer: setTimeout(() => {
            const pending = taskContextLongPress;
            if (!pending) return;
            taskContextLongPress = null;
            moveCursorTo(pending.task);
            openTaskContextMenu(pending.task, pending.x, pending.y);
            navigator.vibrate?.(12);
        }, 550),
    };
}
function moveTaskContextLongPress(event: PointerEvent) {
    const pending = taskContextLongPress;
    if (!pending || pending.pointerId !== event.pointerId) return;
    if (Math.hypot(event.clientX - pending.x, event.clientY - pending.y) > 10)
        cancelTaskContextLongPress();
}
function cancelTaskContextLongPress() {
    if (!taskContextLongPress) return;
    clearTimeout(taskContextLongPress.timer);
    taskContextLongPress = null;
}
async function toggleTaskCompletionFromContext() {
    const menu = taskContextMenu.value,
        projectId = store.workspace?.project.id;
    if (!menu || !projectId || taskContextBusy.value) return;
    const completed = !(
        menu.task.completed ?? menu.task.status === "completed"
    );
    taskContextMenu.value = null;
    taskContextBusy.value = true;
    try {
        const response = await fetch(
            `/api/v1/projects/${projectId}/tasks/${menu.task.id}/completion`,
            {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ completed }),
            },
        );
        if (!response.ok)
            throw new Error(
                await responseError(
                    response,
                    "Não foi possível atualizar a conclusão.",
                ),
            );
        await store.load();
        showToast(
            completed ? "Tarefa concluída" : "Conclusão desfeita",
            "success",
        );
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível atualizar a conclusão da tarefa.",
            "error",
        );
    } finally {
        taskContextBusy.value = false;
        setTimeout(() => (toast.value = ""), 4000);
    }
}
async function setTaskPriorityFromContext(priority: 1 | 2 | 3 | 4) {
    const menu = taskContextMenu.value;
    if (!menu || menu.task.kind !== "task" || taskContextBusy.value) return;
    taskContextMenu.value = null;
    taskContextBusy.value = true;
    try {
        await persistTask({ ...menu.task, priority });
        await store.load();
        showToast("Prioridade atualizada", "success");
    } catch (error) {
        showToast(error instanceof Error ? error.message : "Não foi possível atualizar a prioridade.", "error");
    } finally {
        taskContextBusy.value = false;
        setTimeout(() => (toast.value = ""), 3500);
    }
}
function editSectionFromContext() {
    const menu = taskContextMenu.value;
    if (!menu || menu.task.kind !== "section") return;
    taskContextMenu.value = null;
    sectionDraft.value = { ...menu.task };
    taskDraft.value = null;
    drawer.value = true;
}
async function deleteSectionFromContext() {
    const menu = taskContextMenu.value, projectId = store.workspace?.project.id;
    if (!menu || menu.task.kind !== "section" || !projectId) return;
    if (!confirm(`Excluir a seção “${menu.task.title}” e todo o seu conteúdo?`)) return;
    taskContextMenu.value = null;
    try {
        const response = await fetch(`/api/v1/projects/${projectId}/sections/${menu.task.id}`, { method: "DELETE", headers: { Accept: "application/json", ...csrfHeaders() } });
        if (!response.ok) throw new Error(await responseError(response, "Não foi possível excluir a seção."));
        await store.load();
        showToast("Seção e conteúdo excluídos", "success");
    } catch (error) {
        showToast(error instanceof Error ? error.message : "Não foi possível excluir a seção.", "error");
    } finally {
        setTimeout(() => (toast.value = ""), 4000);
    }
}
function startTaskColumnResize(event: PointerEvent) {
    event.preventDefault();
    taskColumnResize = {
        originX: event.clientX,
        originWidth: taskColumnWidth.value,
    };
    document.body.classList.add("resizing-task-column");
    window.addEventListener("pointermove", moveTaskColumnResize);
    window.addEventListener("pointerup", stopTaskColumnResize, { once: true });
}
function moveTaskColumnResize(event: PointerEvent) {
    if (!taskColumnResize) return;
    taskColumnWidth.value = clampTaskColumnWidth(
        taskColumnResize.originWidth + event.clientX - taskColumnResize.originX,
    );
    measureGantt();
}
function stopTaskColumnResize() {
    window.removeEventListener("pointermove", moveTaskColumnResize);
    window.removeEventListener("pointerup", stopTaskColumnResize);
    document.body.classList.remove("resizing-task-column");
    if (taskColumnResize) persistColumnPreferences();
    taskColumnResize = null;
}
function taskColumnResizeKeydown(event: KeyboardEvent) {
    let width = taskColumnWidth.value;
    if (event.key === "ArrowLeft") width -= 8;
    else if (event.key === "ArrowRight") width += 8;
    else if (event.key === "Home") width = TASK_COLUMN_MIN;
    else if (event.key === "End") width = taskColumnMax.value;
    else return;
    event.preventDefault();
    taskColumnWidth.value = clampTaskColumnWidth(width);
    persistColumnPreferences();
    measureGantt();
}
function formatTaskDate(value: string | null) {
    if (!value) return "—";
    const [year, month, day] = value.slice(0, 10).split("-");
    return `${day}/${month}/${year}`;
}
function measureGantt() {
    const timeline = timelineElement.value;
    if (!timeline) return;
    viewportHeight.value = timeline.clientHeight;
    timelineViewport.value = Math.max(
        1,
        timeline.clientWidth - taskPaneWidth.value,
    );
}
const onTimelineScroll = (event: Event) => {
    const target = event.target as HTMLElement;
    if (scrollFrame !== null) return;
    scrollFrame = requestAnimationFrame(() => {
        scrollFrame = null;
        scrollTop.value = target.scrollTop;
        scrollLeft.value = target.scrollLeft;
        timelineViewport.value = Math.max(
            1,
            target.clientWidth - taskPaneWidth.value,
        );
        viewportHeight.value = target.clientHeight;
    });
};
function scrollTimelineBy(left: number, top = 0) {
    const timeline = timelineElement.value;
    if (!timeline) return;
    if (typeof timeline.scrollBy === "function")
        timeline.scrollBy({ left, top, behavior: "auto" });
    else {
        timeline.scrollLeft += left;
        timeline.scrollTop += top;
    }
}
const hiddenDependencies = computed(() => {
    const visible = new Set(visibleTasks.value.map((task) => task.id));
    return (store.workspace?.dependencies ?? []).filter(
        (dependency) =>
            !visible.has(dependency.from) || !visible.has(dependency.to),
    );
});
const monthSegments = computed(() => {
    const out: { label: string; span: number }[] = [];
    days.value.forEach((d) => {
        const label = d.toLocaleDateString("pt-BR", {
            month: "long",
            year: "numeric",
        });
        const last = out.at(-1);
        last?.label === label ? last.span++ : out.push({ label, span: 1 });
    });
    return out;
});
const pathFor = (dependency: Dependency) => {
    const from = visibleTasks.value.find((task) => task.id === dependency.from),
        to = visibleTasks.value.find((task) => task.id === dependency.to);
    if (!from || !to) return "";
    const sourceEndpoint: TimeEndpoint = dependency.type.startsWith("F")
            ? "finish"
            : "start",
        targetEndpoint: TimeEndpoint = dependency.type.endsWith("F")
            ? "finish"
            : "start",
        source = endpointPoint(from, sourceEndpoint),
        target = endpointPoint(to, targetEndpoint),
        direction = target.x >= source.x ? 1 : -1,
        x1 = source.x + direction * 6,
        x2 = target.x - direction * 5,
        mid = x1 + direction * Math.max(18, Math.abs(x2 - x1) / 2);
    return `M${x1},${source.y} H${mid} V${target.y} H${x2}`;
};
function moveCursorTo(task: Task) {
    cursorTaskId.value = task.id;
}
function selectableTaskIds() {
    return visibleTasks.value
        .filter((item) => item.kind === "task")
        .map((item) => item.id);
}
function selectFromCheckbox(task: Task, event: MouseEvent) {
    if (task.kind !== "task") return;
    const next = selectGanttRow(
        {
            selected: store.selected,
            anchor: selectionAnchorId.value,
            cursor: cursorTaskId.value,
        },
        selectableTaskIds(),
        task.id,
        { additive: true, range: event.shiftKey },
    );
    store.selected = next.selected;
    selectionAnchorId.value = next.anchor;
    cursorTaskId.value = next.cursor;
}
function toggleExpansion(task: Task) {
    if (!isExpandable(task)) return;
    cursorTaskId.value = task.id;
    store.toggleGroup(task.id);
}
function focusTaskTitle() {
    void nextTick(() =>
        document.querySelector<HTMLInputElement>(".drawer-body input")?.focus(),
    );
}
async function loadEditorContext(_taskId: string) {
    editorContextLoading.value = true;
    const projectId = store.workspace?.project.id;
    try {
        collaborators.value = store.workspace?.people ?? [];
        if (!projectId) return;
        const response = await fetch(
            `/api/v1/projects/${projectId}/tasks/${_taskId}/context`,
            { headers: { Accept: "application/json" } },
        );
        if (!response.ok)
            throw new Error(
                await responseError(response, "Não foi possível carregar a tarefa."),
            );
        const data = (await response.json()).data;
        collaborators.value = data.collaborators ?? collaborators.value;
        taskComments.value = data.comments ?? [];
    } catch (error) {
        showToast(
            error instanceof Error ? error.message : "Não foi possível carregar a tarefa.",
            "error",
        );
    } finally {
        editorContextLoading.value = false;
    }
}
async function createPerson() {
    const projectId = store.workspace?.project.id;
    if (!projectId || !newPersonName.value.trim() || creatingPerson.value)
        return;
    creatingPerson.value = true;
    try {
        const response = await fetch(`/api/v1/projects/${projectId}/people`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                name: newPersonName.value.trim(),
                email: newPersonEmail.value.trim() || null,
            }),
        });
        if (!response.ok)
            throw new Error(
                await responseError(
                    response,
                    "Não foi possível cadastrar a pessoa.",
                ),
            );
        newPersonName.value = "";
        newPersonEmail.value = "";
        await store.load();
        collaborators.value = store.workspace?.people ?? [];
        showToast("Responsável cadastrado", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível cadastrar a pessoa.",
            "error",
        );
    } finally {
        creatingPerson.value = false;
        setTimeout(() => (toast.value = ""), 3500);
    }
}
function openTaskImmediately(task: Task) {
    const draft = { ...task };
    taskDraft.value = draft;
    sectionDraft.value = null;
    taskDraftBaseline.value = editableTaskSnapshot(draft);
    editorReturnTaskId.value = task.id;
    pendingTaskToOpen.value = null;
    closeConfirmation.value = false;
    deletionPreview.value = null;
    dependencyConfirmation.value = null;
    commentDraft.value = "";
    editingCommentId.value = null;
    drawer.value = true;
    void loadEditorContext(task.id);
    focusTaskTitle();
}
function openTask(task: Task) {
    if (task.kind !== "task") return;
    if (drawer.value && taskDraft.value?.id === task.id) return;
    if (drawer.value && taskDraftDirty.value) {
        pendingTaskToOpen.value = task;
        closeConfirmation.value = true;
        return;
    }
    openTaskImmediately(task);
}
function openTaskFromDoubleClick(task: Task, event: MouseEvent) {
    if ((event.target as HTMLElement).closest("button,input,select,a")) return;
    openTask(task);
}
function openSelectedTask() {
    if (store.selected.length !== 1) return;
    const task = store.workspace?.tasks.find(
        (item) => item.id === store.selected[0],
    );
    if (task) openTask(task);
}
function finishTaskEditorClose(returnFocus = true) {
    const returnId = editorReturnTaskId.value;
    drawer.value = false;
    taskDraft.value = null;
    sectionDraft.value = null;
    taskDraftBaseline.value = "";
    closeConfirmation.value = false;
    pendingTaskToOpen.value = null;
    deletionPreview.value = null;
    dependencyConfirmation.value = null;
    relationModal.value = null;
    commentDraft.value = "";
    editingCommentId.value = null;
    if (returnFocus && returnId)
        void nextTick(() =>
            Array.from(document.querySelectorAll<HTMLElement>(".task-row"))
                .find((row) => row.dataset.taskId === returnId)
                ?.focus({ preventScroll: true }),
        );
}
function requestTaskEditorClose() {
    pendingTaskToOpen.value = null;
    if (taskDraftDirty.value) closeConfirmation.value = true;
    else finishTaskEditorClose();
}
function continueTaskEditing() {
    pendingTaskToOpen.value = null;
    closeConfirmation.value = false;
    focusTaskTitle();
}
function discardTaskDraft() {
    const pending = pendingTaskToOpen.value;
    finishTaskEditorClose(!pending);
    if (pending) openTaskImmediately(pending);
}
function guardUnsavedTask(event: BeforeUnloadEvent) {
    if (!taskDraftDirty.value) return;
    event.preventDefault();
    event.returnValue = "";
}
function editorMaxWidth() {
    return Math.max(editorMinWidth, Math.floor(window.innerWidth * 0.5));
}
function clampEditorWidth(width: number) {
    return Math.max(
        editorMinWidth,
        Math.min(editorMaxWidth(), Math.round(width)),
    );
}
function persistEditorLayout() {
    localStorage.setItem(
        "ganttist.task-editor-pinned",
        editorPinned.value ? "1" : "0",
    );
    localStorage.setItem(
        "ganttist.task-editor-width",
        String(editorWidth.value),
    );
}
function handleViewportResize() {
    viewportWidth.value = globalThis.innerWidth || viewportWidth.value;
    const editor = clampEditorWidth(editorWidth.value);
    if (editor !== editorWidth.value) {
        editorWidth.value = editor;
        persistEditorLayout();
    }
    const taskWidth = clampTaskColumnWidth(taskColumnWidth.value);
    if (taskWidth !== taskColumnWidth.value) {
        taskColumnWidth.value = taskWidth;
        persistColumnPreferences();
    }
    if (columnsMenu.value && columnPickerButton.value) {
        const rect = columnPickerButton.value.getBoundingClientRect();
        columnPickerPosition.value = {
            top: Math.round(rect.bottom + 6),
            left: Math.round(rect.left),
        };
    }
    measureGantt();
}
function toggleEditorPinned() {
    editorPinned.value = !editorPinned.value;
    editorWidth.value = clampEditorWidth(editorWidth.value);
    persistEditorLayout();
    void nextTick(measureGantt);
}
function startEditorResize(event: PointerEvent) {
    if (!editorPinned.value) return;
    event.preventDefault();
    editorResize = { originX: event.clientX, originWidth: editorWidth.value };
    window.addEventListener("pointermove", moveEditorResize);
    window.addEventListener("pointerup", stopEditorResize, { once: true });
}
function moveEditorResize(event: PointerEvent) {
    if (!editorResize) return;
    editorWidth.value = clampEditorWidth(
        editorResize.originWidth + (editorResize.originX - event.clientX),
    );
    measureGantt();
}
function stopEditorResize() {
    window.removeEventListener("pointermove", moveEditorResize);
    window.removeEventListener("pointerup", stopEditorResize);
    if (editorResize) persistEditorLayout();
    editorResize = null;
}
function resizeEditorFromKeyboard(event: KeyboardEvent) {
    let width = editorWidth.value;
    if (event.key === "ArrowLeft") width += 24;
    else if (event.key === "ArrowRight") width -= 24;
    else if (event.key === "Home") width = editorMinWidth;
    else if (event.key === "End") width = editorMaxWidth();
    else return;
    event.preventDefault();
    editorWidth.value = clampEditorWidth(width);
    persistEditorLayout();
    void nextTick(measureGantt);
}
function focusCursor() {
    const focus = () => {
        const row = Array.from(
            document.querySelectorAll<HTMLElement>(".task-row"),
        ).find((element) => element.dataset.taskId === cursorTaskId.value);
        row?.focus({ preventScroll: true });
        return Boolean(row);
    };
    if (!focus()) void nextTick(focus);
}
function ensureCursorVisible(index: number) {
    const timeline = timelineElement.value;
    if (!timeline) return;
    const top = index * rowHeight.value,
        bottom = top + rowHeight.value;
    if (top < timeline.scrollTop) timeline.scrollTop = top;
    else if (bottom > timeline.scrollTop + timeline.clientHeight)
        timeline.scrollTop = bottom - timeline.clientHeight;
}
function moveCursor(direction: -1 | 1, focusRow: boolean) {
    if (!visibleTasks.value.length) return;
    const currentIndex = visibleTasks.value.findIndex(
        (item) => item.id === cursorTaskId.value,
    );
    const baseIndex =
        currentIndex < 0
            ? Math.floor(scrollTop.value / rowHeight.value)
            : currentIndex;
    const nextIndex = Math.max(
        0,
        Math.min(visibleTasks.value.length - 1, baseIndex + direction),
    );
    cursorTaskId.value = visibleTasks.value[nextIndex]?.id ?? null;
    ensureCursorVisible(nextIndex);
    if (focusRow) void focusCursor();
}
function toggleCursorSelection() {
    const task = visibleTasks.value.find(
        (item) => item.id === cursorTaskId.value,
    );
    if (!task || task.kind !== "task") return;
    const next = selectGanttRow(
        {
            selected: store.selected,
            anchor: selectionAnchorId.value,
            cursor: cursorTaskId.value,
        },
        selectableTaskIds(),
        task.id,
        { additive: true },
    );
    store.selected = next.selected;
    selectionAnchorId.value = next.anchor;
    cursorTaskId.value = next.cursor;
}
function adoptTimelineCursor() {
    const currentIndex = visibleTasks.value.findIndex(
        (item) => item.id === cursorTaskId.value,
    );
    const currentTop = timelineElement.value?.scrollTop ?? scrollTop.value;
    const currentHeight =
        timelineElement.value?.clientHeight || viewportHeight.value;
    scrollTop.value = currentTop;
    viewportHeight.value = currentHeight;
    const firstVisible = Math.max(0, Math.floor(currentTop / rowHeight.value));
    const lastVisible = Math.min(
        visibleTasks.value.length - 1,
        Math.ceil((currentTop + currentHeight) / rowHeight.value) - 1,
    );
    if (currentIndex < firstVisible || currentIndex > lastVisible)
        cursorTaskId.value = visibleTasks.value[firstVisible]?.id ?? null;
}
function ganttKeydown(event: KeyboardEvent) {
    if (event.target !== event.currentTarget) return;
    if (event.shiftKey && event.key === "Tab") {
        event.preventDefault();
        adoptTimelineCursor();
        void focusCursor();
    } else if (event.key === "ArrowUp" || event.key === "ArrowDown") {
        event.preventDefault();
        moveCursor(event.key === "ArrowUp" ? -1 : 1, false);
    } else if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
        event.preventDefault();
        scrollTimelineBy(
            (event.key === "ArrowLeft" ? -1 : 1) * dayWidth.value * 3,
        );
    } else if (event.key === " ") {
        event.preventDefault();
        toggleCursorSelection();
    } else if (event.key === "Enter") {
        event.preventDefault();
        const task = visibleTasks.value.find(
            (item) => item.id === cursorTaskId.value,
        );
        if (task) openTask(task);
    }
}
function rowKeydown(task: Task, event: KeyboardEvent) {
    if (event.target !== event.currentTarget) return;
    if (event.key === "ArrowUp" || event.key === "ArrowDown") {
        event.preventDefault();
        moveCursor(event.key === "ArrowUp" ? -1 : 1, true);
    } else if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
        event.preventDefault();
        scrollTimelineBy(
            (event.key === "ArrowLeft" ? -1 : 1) * dayWidth.value * 3,
        );
    } else if (event.key === " ") {
        event.preventDefault();
        toggleCursorSelection();
    } else if (event.key === "Enter") {
        event.preventDefault();
        openTask(task);
    }
}
function taskTitle(id: string) {
    return store.workspace?.tasks.find((task) => task.id === id)?.title ?? id;
}
function revealDependency(dependency: Dependency) {
    store.revealTask(dependency.from);
    store.revealTask(dependency.to);
    showToast(`Relação ${dependency.type} revelada`, "info");
    setTimeout(() => (toast.value = ""), 3000);
}
async function toggleProjectMenu() {
    projectMenu.value = !projectMenu.value;
    if (!projectMenu.value || projects.value.length) return;
    projectLoading.value = true;
    try {
        const response = await fetch("/api/v1/projects", {
            headers: { Accept: "application/json" },
        });
        if (!response.ok)
            throw new Error("Não foi possível carregar os projetos.");
        projects.value = (await response.json()).data;
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível carregar projetos.",
            "error",
        );
        projectMenu.value = false;
        setTimeout(() => (toast.value = ""), 3500);
    } finally {
        projectLoading.value = false;
    }
}
async function switchProject(project: { id: string; name: string }) {
    try {
        projectMenu.value = false;
        await store.load(project.id);
        showToast(`Projeto ${project.name} selecionado`, "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível trocar o projeto.",
            "error",
        );
    }
    setTimeout(() => (toast.value = ""), 3500);
}
async function responseError(response: Response, fallback: string) {
    try {
        const payload = await response.json();
        return typeof payload?.message === "string"
            ? payload.message
            : fallback;
    } catch {
        return fallback;
    }
}
async function createDependency(
    from: string,
    to: string,
    type: "FS" | "SS" | "FF" | "SF",
) {
    const projectId = store.workspace?.project.id;
    if (!projectId) throw new Error("Projeto não carregado.");
    const response = await fetch(`/api/v1/projects/${projectId}/dependencies`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
        },
        body: JSON.stringify({ from, to, type }),
    });
    if (!response.ok)
        throw new Error(
            await responseError(
                response,
                "Não foi possível criar a dependência.",
            ),
        );
    await store.load();
    return (await response.json()).data as Dependency;
}
async function removeDependency(id: string) {
    const projectId = store.workspace?.project.id;
    if (!projectId) return;
    try {
        const response = await fetch(
            `/api/v1/projects/${projectId}/dependencies/${id}`,
            {
                method: "DELETE",
                headers: { Accept: "application/json", ...csrfHeaders() },
            },
        );
        if (!response.ok)
            throw new Error("Não foi possível remover a dependência.");
        await store.load();
        showToast("Dependência removida", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível remover a dependência.",
            "error",
        );
    }
    setTimeout(() => (toast.value = ""), 4000);
}
function requestRemoveDependency(id: string) {
    dependencyConfirmation.value = { action: "remove", id };
}
async function confirmDependency() {
    const confirmation = dependencyConfirmation.value;
    dependencyConfirmation.value = null;
    if (confirmation) await removeDependency(confirmation.id);
}
function openRelationModal(direction: RelationDirection) {
    relationModal.value = {
        direction,
        search: "",
        selectedId: null,
        type: null,
    };
    void nextTick(() =>
        document.querySelector<HTMLInputElement>(".relation-search")?.focus(),
    );
}
function chooseRelationTask(task: Task) {
    if (!relationModal.value) return;
    relationModal.value.selectedId = task.id;
    relationModal.value.search = task.title;
    relationModal.value.type = null;
}
async function confirmRelationModal() {
    const modal = relationModal.value,
        task = activeTask.value;
    if (
        !modal?.selectedId ||
        !modal.type ||
        !task ||
        relationModalValidation.value
    )
        return;
    const from = modal.direction === "predecessor" ? modal.selectedId : task.id,
        to = modal.direction === "predecessor" ? task.id : modal.selectedId;
    relationBusy.value = true;
    try {
        await createDependency(from, to, modal.type);
        relationModal.value = null;
        showToast("Relação criada", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível criar a relação.",
            "error",
        );
    } finally {
        relationBusy.value = false;
        setTimeout(() => (toast.value = ""), 4000);
    }
}
function installGestureListeners() {
    window.addEventListener("pointermove", gesturePointerMove, {
        passive: false,
    });
    window.addEventListener("pointerup", gesturePointerUp);
    window.addEventListener("pointercancel", cancelTimeblockGesture);
    window.addEventListener("keydown", gestureKeydown, true);
}
function removeGestureListeners() {
    window.removeEventListener("pointermove", gesturePointerMove);
    window.removeEventListener("pointerup", gesturePointerUp);
    window.removeEventListener("pointercancel", cancelTimeblockGesture);
    window.removeEventListener("keydown", gestureKeydown, true);
}
function cancelTimeblockGesture() {
    if (timeGesture.value?.committing) return;
    removeGestureListeners();
    cancelGestureState();
}
function autoScrollGesture(event: PointerEvent, allowVertical = false) {
    const timeline = timelineElement.value;
    if (!timeline) return;
    const rect = timeline.getBoundingClientRect(),
        edge = 38,
        speed = 18;
    if (event.clientX < rect.left + taskPaneWidth.value + edge)
        timeline.scrollLeft = Math.max(0, timeline.scrollLeft - speed);
    else if (event.clientX > rect.right - edge) timeline.scrollLeft += speed;
    if (!allowVertical) return;
    if (event.clientY < rect.top + edge)
        timeline.scrollTop = Math.max(0, timeline.scrollTop - speed);
    else if (event.clientY > rect.bottom - edge) timeline.scrollTop += speed;
}
function updateConnectionPointer(gesture: ConnectGesture, event: PointerEvent) {
    const rect = timelinePlane.value?.getBoundingClientRect();
    if (rect) {
        gesture.pointerX = event.clientX - rect.left;
        gesture.pointerY = event.clientY - rect.top;
    }
    const port = (
        document.elementFromPoint(
            event.clientX,
            event.clientY,
        ) as HTMLElement | null
    )?.closest<HTMLElement>(".dependency-port");
    gesture.targetTaskId = port?.dataset.taskId ?? null;
    gesture.targetEndpoint =
        (port?.dataset.endpoint as TimeEndpoint | undefined) ?? null;
}
function gesturePointerMove(event: PointerEvent) {
    const gesture = timeGesture.value;
    if (!gesture || gesture.committing) return;
    event.preventDefault();
    autoScrollGesture(event, gesture.kind === "connect");
    if (gesture.kind === "move") {
        const delta = Math.round(
            (event.clientX - gesture.originX) / dayWidth.value,
        );
        gesture.moved =
            gesture.moved || Math.abs(event.clientX - gesture.originX) >= 3;
        gesture.previewStart = shiftCivilDate(gesture.start, delta);
        gesture.previewFinish = shiftCivilDate(gesture.finish, delta);
    } else if (gesture.kind === "resize") {
        const preview = resizePreview({
            edge: gesture.edge,
            originX: gesture.originX,
            pointerX: event.clientX,
            dayWidth: dayWidth.value,
            start: gesture.start,
            finish: gesture.finish,
            earliestStart: gesture.earliestStart,
        });
        gesture.moved =
            gesture.moved || Math.abs(event.clientX - gesture.originX) >= 3;
        gesture.previewStart = preview.start;
        gesture.previewFinish = preview.finish;
        gesture.limited = preview.limited;
    } else updateConnectionPointer(gesture, event);
}
function gesturePointerUp() {
    const gesture = timeGesture.value;
    if (!gesture) return;
    if (gesture.kind === "connect") void commitConnectionGesture();
    else void commitDateGesture();
}
function gestureKeydown(event: KeyboardEvent) {
    const gesture = timeGesture.value;
    if (!gesture) return;
    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        cancelTimeblockGesture();
        return;
    }
    if (
        gesture.kind === "resize" &&
        (event.key === "ArrowLeft" || event.key === "ArrowRight")
    ) {
        event.preventDefault();
        event.stopPropagation();
        const delta = event.key === "ArrowLeft" ? -1 : 1;
        if (gesture.edge === "finish") {
            const candidate = shiftCivilDate(gesture.previewFinish, delta);
            gesture.previewFinish =
                candidate < gesture.previewStart
                    ? gesture.previewStart
                    : candidate;
            gesture.limited = candidate < gesture.previewStart;
        } else {
            const candidate = shiftCivilDate(gesture.previewStart, delta),
                minimum = gesture.earliestStart;
            gesture.previewStart =
                minimum && candidate < minimum
                    ? minimum
                    : candidate > gesture.previewFinish
                      ? gesture.previewFinish
                      : candidate;
            gesture.limited = gesture.previewStart !== candidate;
        }
        gesture.moved = true;
    } else if (
        gesture.kind === "resize" &&
        (event.key === "Enter" || event.key === " ")
    ) {
        event.preventDefault();
        event.stopPropagation();
        void commitDateGesture();
    }
}
function startDrag(task: Task, event: PointerEvent) {
    if (!canDragTask(task)) return;
    event.preventDefault();
    const start = visualStart(task),
        finish = visualFinish(task);
    if (
        beginTimeGesture({
            kind: "move",
            taskId: task.id,
            originX: event.clientX,
            start,
            finish,
            previewStart: start,
            previewFinish: finish,
            moved: false,
            committing: false,
        })
    )
        installGestureListeners();
}
function startResize(task: Task, edge: TimeEndpoint, event?: PointerEvent) {
    if (!canResizeTask(task)) return;
    event?.preventDefault();
    event?.stopPropagation();
    const start = visualStart(task),
        finish = visualFinish(task);
    if (
        beginTimeGesture({
            kind: "resize",
            edge,
            taskId: task.id,
            originX: event?.clientX ?? 0,
            start,
            finish,
            earliestStart: task.earliest_start ?? null,
            previewStart: start,
            previewFinish: finish,
            moved: false,
            limited: false,
            committing: false,
        })
    )
        installGestureListeners();
}
function resizeGripKeydown(
    task: Task,
    edge: TimeEndpoint,
    event: KeyboardEvent,
) {
    if (!["Enter", " "].includes(event.key)) return;
    event.preventDefault();
    event.stopPropagation();
    if (timeGesture.value?.kind === "resize") void commitDateGesture();
    else startResize(task, edge);
}
async function commitDateGesture() {
    const gesture = timeGesture.value;
    if (!gesture || gesture.kind === "connect" || gesture.committing) return;
    removeGestureListeners();
    if (!gesture.moved) {
        finishGestureState();
        return;
    }
    gesture.committing = true;
    const task = store.workspace?.tasks.find((item) => item.id === gesture.taskId);
    const projectId = store.workspace?.project.id;
    if (!task || !projectId) return finishGestureState();
    const body = {
        plannedStart: gesture.kind === "resize" && gesture.edge === "finish" ? task.start : gesture.previewStart,
        plannedFinish: gesture.kind === "resize" && gesture.edge === "start" ? task.finish : gesture.previewFinish,
    };
    try {
        const response = await fetch(`/api/v1/projects/${projectId}/tasks/${gesture.taskId}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify(body),
        });
        if (!response.ok)
            throw new Error(
                await responseError(
                    response,
                    "Não foi possível atualizar as datas da tarefa.",
                ),
            );
        await store.load();
        showToast("Planejamento atualizado", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível atualizar as datas da tarefa.",
            "error",
        );
    } finally {
        finishGestureState();
        setTimeout(() => (toast.value = ""), 4000);
    }
}
function endpointPoint(task: Task, endpoint: TimeEndpoint) {
    return {
        x:
            endpoint === "start"
                ? px(visualStart(task))
                : px(visualFinish(task)) + dayWidth.value,
        y: rowOffset(task.id) + rowHeight.value / 2,
    };
}
function wouldCreateClientCycle(from: string, to: string) {
    const outgoing = new Map<string, string[]>();
    for (const dependency of store.workspace?.dependencies ?? []) {
        const targets = outgoing.get(dependency.from) ?? [];
        targets.push(dependency.to);
        outgoing.set(dependency.from, targets);
    }
    const pending = [to],
        seen = new Set<string>();
    while (pending.length) {
        const current = pending.pop()!;
        if (current === from) return true;
        if (seen.has(current)) continue;
        seen.add(current);
        pending.push(...(outgoing.get(current) ?? []));
    }
    return false;
}
function connectionValidation(task: Task, endpoint: TimeEndpoint) {
    const gesture = timeGesture.value;
    if (!gesture || gesture.kind !== "connect")
        return {
            valid: true,
            reason: "",
            type: null as "FS" | "SS" | "FF" | "SF" | null,
        };
    const type = dependencyTypeFor(gesture.endpoint, endpoint);
    if (task.id === gesture.taskId)
        return {
            valid: false,
            reason: "Uma tarefa não pode depender dela mesma.",
            type,
        };
    if (!canConnectTo(task))
        return {
            valid: false,
            reason: "Grupos podem ser somente predecessores.",
            type,
        };
    if (
        (store.workspace?.dependencies ?? []).some(
            (item) =>
                item.from === gesture.taskId &&
                item.to === task.id &&
                item.type === type,
        )
    )
        return { valid: false, reason: "Essa dependência já existe.", type };
    if (wouldCreateClientCycle(gesture.taskId, task.id))
        return {
            valid: false,
            reason: "Essa dependência criaria um ciclo.",
            type,
        };
    return { valid: true, reason: "", type };
}
function startConnection(
    task: Task,
    endpoint: TimeEndpoint,
    event?: PointerEvent,
) {
    if (!canConnectFrom(task)) return;
    event?.preventDefault();
    event?.stopPropagation();
    const point = endpointPoint(task, endpoint);
    if (
        beginTimeGesture({
            kind: "connect",
            taskId: task.id,
            endpoint,
            pointerX: point.x,
            pointerY: point.y,
            targetTaskId: null,
            targetEndpoint: null,
            committing: false,
        })
    )
        installGestureListeners();
}
function dependencyPortKeydown(
    task: Task,
    endpoint: TimeEndpoint,
    event: KeyboardEvent,
) {
    if (!["Enter", " "].includes(event.key)) return;
    event.preventDefault();
    event.stopPropagation();
    const gesture = timeGesture.value;
    if (!gesture) startConnection(task, endpoint);
    else if (gesture.kind === "connect") {
        gesture.targetTaskId = task.id;
        gesture.targetEndpoint = endpoint;
        void commitConnectionGesture();
    }
}
const connectionPreview = computed(() => {
    const gesture = timeGesture.value;
    if (!gesture || gesture.kind !== "connect") return null;
    const source = visibleTasks.value.find(
        (task) => task.id === gesture.taskId,
    );
    if (!source) return null;
    const from = endpointPoint(source, gesture.endpoint),
        target = gesture.targetTaskId
            ? visibleTasks.value.find(
                  (task) => task.id === gesture.targetTaskId,
              )
            : null,
        to =
            target && gesture.targetEndpoint
                ? endpointPoint(target, gesture.targetEndpoint)
                : { x: gesture.pointerX, y: gesture.pointerY };
    const type =
            target && gesture.targetEndpoint
                ? dependencyTypeFor(gesture.endpoint, gesture.targetEndpoint)
                : null,
        mid = from.x + (to.x - from.x) / 2;
    return {
        path: `M${from.x},${from.y} C${mid},${from.y} ${mid},${to.y} ${to.x},${to.y}`,
        x: mid,
        y: (from.y + to.y) / 2,
        type,
    };
});
async function commitConnectionGesture() {
    const gesture = timeGesture.value;
    if (!gesture || gesture.kind !== "connect" || gesture.committing) return;
    removeGestureListeners();
    const target = gesture.targetTaskId
        ? visibleTasks.value.find((task) => task.id === gesture.targetTaskId)
        : null;
    if (!target || !gesture.targetEndpoint) {
        finishGestureState();
        return;
    }
    const validation = connectionValidation(target, gesture.targetEndpoint);
    if (!validation.valid || !validation.type) {
        showToast(
            validation.reason || "Destino inválido para a dependência.",
            "error",
        );
        finishGestureState();
        setTimeout(() => (toast.value = ""), 4000);
        return;
    }
    gesture.committing = true;
    try {
        const dependency = await createDependency(
            gesture.taskId,
            target.id,
            validation.type,
        );
        undoDependencyId.value = dependency.id;
        showToast(
            `Dependência ${validation.type} criada. Desfazer?`,
            "success",
        );
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível criar a dependência.",
            "error",
        );
    } finally {
        finishGestureState();
        setTimeout(() => {
            toast.value = "";
            undoDependencyId.value = null;
        }, 6000);
    }
}
async function undoLastDependency() {
    const id = undoDependencyId.value;
    if (!id) return;
    undoDependencyId.value = null;
    await removeDependency(id);
}
function gesturePreviewFor(task: Task) {
    const gesture = timeGesture.value;
    return gesture && gesture.kind !== "connect" && gesture.taskId === task.id
        ? gesture
        : null;
}
async function persistTask(task: Task) {
    const projectId = store.workspace?.project.id;
    if (!projectId) throw new Error("Projeto não carregado.");
    const response = await fetch(
        `/api/v1/projects/${projectId}/tasks/${task.id}`,
        {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                title: task.title,
                description: task.description ?? "",
                assigneePersonId: task.assignee_id ?? null,
                sectionId: task.section_id ?? task.parent_id ?? null,
                priority: task.priority ?? 1,
                plannedStart: task.start,
                plannedFinish: task.finish,
                actualCompletionDate: task.effective_completion ?? null,
            }),
        },
    );
    if (!response.ok)
        throw new Error(
            await responseError(response, "Não foi possível salvar a tarefa."),
        );
    store.updateTask(task);
}
function collaboratorName(id: string) {
    return (
        collaborators.value.find((collaborator) => collaborator.id === id)
            ?.name ?? "Usuário"
    );
}
function beginCommentEdit(comment: TaskComment) {
    editingCommentId.value = comment.id;
    commentEditDraft.value = comment.content;
    commentEditBaseline.value = comment.content;
}
function cancelCommentEdit() {
    editingCommentId.value = null;
    commentEditDraft.value = "";
    commentEditBaseline.value = "";
}
async function submitComment() {
    const task = activeTask.value,
        content = commentDraft.value.trim();
    const projectId = store.workspace?.project.id;
    if (!task || !content || !projectId) return;
    const response = await fetch(`/api/v1/projects/${projectId}/tasks/${task.id}/comments`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
        },
        body: JSON.stringify({ content, commandId: crypto.randomUUID() }),
    });
    if (!response.ok)
        throw new Error(
            await responseError(
                response,
                "Não foi possível publicar o comentário.",
            ),
        );
    commentDraft.value = "";
    await loadEditorContext(task.id);
}
async function submitCommentEdit() {
    const task = activeTask.value,
        commentId = editingCommentId.value,
        content = commentEditDraft.value.trim();
    const projectId = store.workspace?.project.id;
    if (!task || !commentId || !content || !projectId) return;
    const response = await fetch(
        `/api/v1/projects/${projectId}/tasks/${task.id}/comments/${commentId}`,
        {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify({ content, commandId: crypto.randomUUID() }),
        },
    );
    if (!response.ok)
        throw new Error(
            await responseError(
                response,
                "Não foi possível editar o comentário.",
            ),
        );
    cancelCommentEdit();
    await loadEditorContext(task.id);
}
async function publishComment() {
    try {
        await submitComment();
        showToast("Comentário publicado", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível publicar o comentário.",
            "error",
        );
    } finally {
        setTimeout(() => (toast.value = ""), 4000);
    }
}
async function publishCommentEdit() {
    try {
        await submitCommentEdit();
        showToast("Comentário atualizado", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível editar o comentário.",
            "error",
        );
    } finally {
        setTimeout(() => (toast.value = ""), 4000);
    }
}
async function previewDeletion() {
    if (!activeTask.value || activeTask.value.kind !== "task") return;
    const taskId = activeTask.value.id;
    const dependencies = store.workspace?.dependencies ?? [];
    deletionPreview.value = {
        incoming: dependencies.filter((dependency) => dependency.to === taskId),
        outgoing: dependencies.filter((dependency) => dependency.from === taskId),
        continuity: [],
    };
}
async function deleteTask() {
    const projectId = store.workspace?.project.id;
    if (!activeTask.value || !deletionPreview.value || !projectId) return;
    deleting.value = true;
    try {
        const response = await fetch(
            `/api/v1/projects/${projectId}/tasks/${activeTask.value.id}`,
            {
                method: "DELETE",
                headers: { Accept: "application/json", ...csrfHeaders() },
            },
        );
        if (!response.ok) throw new Error("Não foi possível excluir a tarefa.");
        finishTaskEditorClose();
        await store.load();
        showToast("Tarefa excluída", "success");
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível excluir a tarefa.",
            "error",
        );
    } finally {
        deleting.value = false;
        setTimeout(() => (toast.value = ""), 3500);
    }
}
async function saveTask() {
    if (!activeTask.value) return;
    const task = { ...activeTask.value },
        source = store.workspace?.tasks.find((item) => item.id === task.id),
        pending = pendingTaskToOpen.value;
    try {
        if (isCreatingTask.value) {
            const projectId = store.workspace?.project.id;
            if (!projectId) throw new Error("Projeto não carregado.");
            const response = await fetch(`/api/v1/projects/${projectId}/tasks`, {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json", ...csrfHeaders() },
                body: JSON.stringify({ title: task.title, description: task.description || null, priority: task.priority ?? 1, sectionId: task.section_id ?? null, assigneePersonId: task.assignee_id ?? null, plannedStart: task.start, plannedFinish: task.finish, actualCompletionDate: task.completed ? task.effective_completion ?? todayCivil() : null }),
            });
            if (!response.ok) throw new Error(await responseError(response, "Não foi possível criar a tarefa."));
            await store.load();
            showToast("Tarefa criada", "success");
            finishTaskEditorClose();
            return;
        }
        const changed =
            !source ||
            task.title !== source.title ||
            (task.description ?? "") !== (source.description ?? "") ||
            (task.assignee_id ?? null) !== (source.assignee_id ?? null) ||
            (task.section_id ?? task.parent_id ?? null) !==
                (source.section_id ?? source.parent_id ?? null) ||
            (task.effective_completion ?? null) !==
                (source.effective_completion ?? null) ||
            (task.priority ?? 1) !== (source.priority ?? 1) ||
            task.start !== source.start ||
            task.finish !== source.finish;
        if (changed) await persistTask(task);
        if (
            (task.completed ?? task.status === "completed") !==
            (source?.completed ?? source?.status === "completed")
        ) {
            const projectId = store.workspace?.project.id;
            if (projectId) {
                const response = await fetch(
                    `/api/v1/projects/${projectId}/tasks/${task.id}/completion`,
                    {
                        method: "PATCH",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            ...csrfHeaders(),
                        },
                        body: JSON.stringify({
                            completed: task.completed ?? false,
                            actualCompletionDate: task.completed
                                ? task.effective_completion ?? todayCivil()
                                : null,
                        }),
                    },
                );
                if (!response.ok)
                    throw new Error(
                        await responseError(
                            response,
                            "Não foi possível atualizar a conclusão.",
                        ),
                    );
            }
        }
        await store.load();
        showToast("Alterações salvas", "success");
        finishTaskEditorClose(!pending);
        if (pending) openTaskImmediately(pending);
        setTimeout(() => (toast.value = ""), 4500);
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : "Não foi possível salvar as alterações",
            "error",
        );
        setTimeout(() => (toast.value = ""), 4500);
    }
}
async function saveSection() {
    const section = sectionDraft.value, projectId = store.workspace?.project.id;
    if (!section || !projectId || !section.title.trim()) return;
    try {
        const creating = section.id === "__new-section__";
        const response = await fetch(`/api/v1/projects/${projectId}/sections${creating ? "" : `/${section.id}`}`, {
            method: creating ? "POST" : "PUT",
            headers: { "Content-Type": "application/json", Accept: "application/json", ...csrfHeaders() },
            body: JSON.stringify(creating ? { name: section.title.trim(), parentSectionId: section.parent_id ?? null } : { name: section.title.trim(), parentSectionId: section.parent_id ?? null }),
        });
        if (!response.ok) throw new Error(await responseError(response, "Não foi possível salvar a seção."));
        await store.load();
        showToast(creating ? "Seção criada" : "Seção atualizada", "success");
        finishTaskEditorClose();
    } catch (error) {
        showToast(error instanceof Error ? error.message : "Não foi possível salvar a seção.", "error");
    }
}
function statusLabel(s: string) {
    return (
        {
            completed: "Concluída",
            blocked: "Bloqueada",
            scheduled: "Agendada",
            late: "Atrasada",
            opened: "Aberta",
        } as Record<string, string>
    )[s];
}
</script>

<template>
    <main v-if="auth.loading" class="loading">
        <div class="loader-logo">G</div>
        <p>Verificando seu acesso…</p>
    </main>
    <AuthGate v-else-if="!auth.user" :auth="auth" />
    <ProjectDashboard
        v-else-if="!store.workspace"
        @open="(id) => store.load(id)"
    />
    <div
        v-else
        class="app-shell"
        :class="[
            `text-${textScale}`,
            `space-${spacing}`,
            { 'editor-pinned': drawer && activeTask && editorPinned },
        ]"
        :style="{ '--task-editor-width': editorWidth + 'px' }"
    >
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark"><i></i><i></i><i></i></span
                ><strong>Ganttist</strong>
            </div>
            <div class="project-switcher">
                <span class="eyebrow">PROJETO</span
                ><button
                    :aria-expanded="projectMenu"
                    aria-haspopup="listbox"
                    @click="toggleProjectMenu"
                >
                    <span class="project-dot"></span
                    >{{ store.workspace?.project.name || "Carregando…" }}
                    <span class="chevron">⌄</span>
                </button>
                <div
                    v-if="projectMenu"
                    class="project-menu"
                    role="listbox"
                    aria-label="Projetos"
                >
                    <span v-if="projectLoading">Carregando projetos…</span
                    ><button
                        v-for="project in projects"
                        :key="project.id"
                        role="option"
                        :aria-selected="
                            project.id === store.workspace?.project.id
                        "
                        @click="() => switchProject(project)"
                    >
                        {{ project.name }}
                    </button>
                </div>
            </div>
            <div class="top-actions">
                <div ref="appearanceWrap" class="appearance-wrap">
                    <button
                        class="icon-btn appearance-btn"
                        aria-label="Aparência"
                        title="Aparência"
                        @click="appearance = !appearance"
                    >
                        A<span>a</span>
                    </button>
                    <div v-if="appearance" class="appearance-menu">
                        <b>Aparência</b
                        ><label
                            >Tamanho do texto<select v-model="textScale">
                                <option value="compact">Menor</option>
                                <option value="comfortable">Confortável</option>
                                <option value="large">Maior</option>
                            </select></label
                        ><label
                            >Espaçamento<select v-model="spacing">
                                <option value="compact">Compacto</option>
                                <option value="comfortable">Confortável</option>
                                <option value="spacious">Espaçoso</option>
                            </select></label
                        >
                    </div>
                </div>
                <button
                    class="icon-btn settings-trigger"
                    aria-label="Abrir configurações do projeto"
                    title="Configurações"
                    aria-haspopup="dialog"
                    :aria-expanded="calendarPanel"
                    @click="calendarPanel = true"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z"
                        />
                        <path
                            d="M19.4 13.15c.05-.38.05-.77 0-1.15l1.74-1.35-1.8-3.12-2.04.82a8.2 8.2 0 0 0-1-.58L16 5.6h-3.6l-.3 2.17c-.35.16-.68.35-1 .58l-2.04-.82-1.8 3.12L9 12c-.05.38-.05.77 0 1.15L7.26 14.5l1.8 3.12 2.04-.82c.32.23.65.42 1 .58l.3 2.17H16l.3-2.17c.35-.16.68-.35 1-.58l2.04.82 1.8-3.12-1.74-1.35Z"
                        />
                    </svg>
                </button>
                <button
                    class="avatar"
                    aria-label="Abrir sessões e configurações da conta"
                    :aria-expanded="account"
                    aria-haspopup="dialog"
                    @click="account = true"
                >
                    {{
                        (auth.user.name || auth.user.email)
                            .slice(0, 2)
                            .toUpperCase()
                    }}
                </button>
            </div>
        </header>

        <main v-if="store.loading" class="loading">
            <div class="loader-logo">G</div>
            <p>Organizando seu planejamento…</p>
        </main>
        <main v-else-if="store.error" class="loading">
            <p>{{ store.error }}</p>
            <button class="primary" @click="() => store.load()">
                Tentar novamente
            </button>
        </main>
        <main v-else class="main">
            <section class="commandbar">
                <div class="title-block">
                    <div>
                        <span class="eyebrow">VISÃO DE PLANEJAMENTO</span>
                        <h1>{{ store.workspace?.project.name }}</h1>
                    </div>
                    <span
                        v-if="
                            store.workspace?.project.id ===
                            'demo-product-launch'
                        "
                        class="demo-badge"
                        >AMBIENTE DEMO</span
                    >
                </div>
                <div class="commands">
                    <label class="search"
                        ><span>⌕</span
                        ><input
                            ref="searchInput"
                            v-model="store.search"
                            placeholder="Buscar tarefa…"
                            aria-keyshortcuts="Meta+K Control+K"
                        /><kbd>⌘ K</kbd></label
                    >
                    <div class="segmented">
                        <button
                            :class="{ active: store.zoom === 'day' }"
                            @click="store.zoom = 'day'"
                        >
                            Dia</button
                        ><button
                            :class="{ active: store.zoom === 'week' }"
                            @click="store.zoom = 'week'"
                        >
                            Semana</button
                        ><button
                            :class="{ active: store.zoom === 'month' }"
                            @click="store.zoom = 'month'"
                        >
                            Mês
                        </button>
                    </div>
                    <button
                        ref="filterButton"
                        class="soft-btn filter-trigger"
                        :aria-expanded="filters"
                        aria-haspopup="true"
                        aria-label="Filtros"
                        title="Filtros"
                        @click="filters = !filters"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M3.5 5.5h17l-6.7 7.3v4.8l-3.6 1.9v-6.7L3.5 5.5Z"
                            /></svg
                        ><span v-if="activeFilterFieldCount" class="count">{{
                            activeFilterFieldCount
                        }}</span>
                    </button>
                    <div class="creation-control">
                        <button ref="creationTrigger" class="primary create-item-trigger" :aria-expanded="creationMenu" aria-haspopup="menu" aria-label="Criar item" title="Criar tarefa ou seção" @click="creationMenu = !creationMenu">+</button>
                        <div v-if="creationMenu" ref="creationMenuElement" class="creation-menu" role="menu" aria-label="Criar item"><button role="menuitem" @click="openCreationDialog('task')"><b>＋</b><span><strong>Tarefa</strong><small>Uma atividade do projeto</small></span></button><button role="menuitem" @click="openCreationDialog('section')"><b>▤</b><span><strong>Seção</strong><small>Um agrupamento hierárquico</small></span></button></div>
                    </div>
                </div>
            </section>
            <div v-if="store.stale" class="workspace-state" role="status">
                <span
                    >Exibindo o último estado conhecido; a atualização remota
                    falhou.</span
                ><button
                    class="soft-btn"
                    :disabled="store.refreshing"
                    @click="() => store.load()"
                >
                    {{ store.refreshing ? "Atualizando…" : "Tentar novamente" }}
                </button>
            </div>
            <section class="stats-row">
                <article>
                    <span class="stat-icon violet">◔</span>
                    <div>
                        <small>PROGRESSO GERAL</small
                        ><b>{{ store.workspace?.stats.progress }}%</b>
                    </div>
                    <div class="mini-progress">
                        <i
                            :style="{
                                width: store.workspace?.stats.progress + '%',
                            }"
                        ></i>
                    </div>
                </article>
                <article>
                    <span class="stat-icon completed">✓</span>
                    <div>
                        <small>CONCLUÍDAS</small
                        ><b
                            >{{ store.workspace?.stats.completed }}
                            <em>/ {{ store.workspace?.stats.total }}</em></b
                        >
                    </div>
                    <span class="trend">+2 esta semana</span>
                </article>
                <article>
                    <span class="stat-icon coral">⌁</span>
                    <div>
                        <small>CAMINHO CRÍTICO</small
                        ><b
                            >{{ store.workspace?.stats.critical }}
                            <em>tarefas</em></b
                        >
                    </div>
                    <span class="risk">ATENÇÃO</span>
                </article>
                <article>
                    <span class="stat-icon amber">⊘</span>
                    <div>
                        <small>BLOQUEADAS</small
                        ><b
                            >{{ store.workspace?.stats.blocked ?? 0 }}
                            <em>tarefas</em></b
                        >
                    </div>
                    <button @click="store.setStatusFilters(['blocked'])">
                        Revisar →
                    </button>
                </article>
            </section>

            <div
                v-if="filters"
                ref="filterMenu"
                class="filter-popover"
                role="group"
                aria-label="Filtros de tarefas"
                @keydown.esc="
                    filters = false;
                    filterButton?.focus();
                "
            >
                <header>
                    <b>Filtros</b
                    ><small>Combine critérios para restringir a lista.</small>
                </header>
                <section
                    class="filter-section"
                    aria-labelledby="filter-status-title"
                >
                    <b id="filter-status-title">Status</b
                    ><label class="filter-status-option filter-all"
                        ><input
                            type="checkbox"
                            :checked="allStatusesSelected"
                            @change="
                                store.setStatusFilters(
                                    allStatusesSelected
                                        ? []
                                        : workspaceTaskStatuses,
                                )
                            "
                        /><span>Todos</span></label
                    >
                    <section
                        class="filter-status-group"
                        role="group"
                        aria-labelledby="unblocked-filter-label"
                    >
                        <label
                            id="unblocked-filter-label"
                            class="filter-status-option filter-status-parent"
                            ><input
                                type="checkbox"
                                :checked="unblockedStatusesChecked"
                                :indeterminate="unblockedStatusesIndeterminate"
                                @change="store.toggleUnblockedStatusFilters()"
                            /><span>Desbloqueadas</span></label
                        >
                        <div class="filter-status-children">
                            <label
                                v-for="f in [
                                    ['opened', 'Abertas'],
                                    ['scheduled', 'Agendadas'],
                                    ['late', 'Atrasadas'],
                                ] as const"
                                :key="f[0]"
                                class="filter-status-option"
                                ><input
                                    type="checkbox"
                                    :checked="
                                        store.statusFilters.includes(f[0])
                                    "
                                    @change="store.toggleStatusFilter(f[0])"
                                /><span>{{ f[1] }}</span></label
                            >
                        </div>
                    </section>
                    <label
                        v-for="f in [
                            ['blocked', 'Bloqueadas'],
                            ['completed', 'Concluídas'],
                        ] as const"
                        :key="f[0]"
                        class="filter-status-option"
                        ><input
                            type="checkbox"
                            :checked="store.statusFilters.includes(f[0])"
                            @change="store.toggleStatusFilter(f[0])"
                        /><span>{{ f[1] }}</span></label
                    >
                </section>
                <section
                    class="filter-section"
                    aria-labelledby="filter-assignee-title"
                >
                    <b id="filter-assignee-title">Responsável</b>
                    <p
                        v-if="!assigneeFilterOptions.length"
                        class="filter-empty"
                    >
                        Nenhum responsável disponível.
                    </p>
                    <label
                        v-for="[id, name] in assigneeFilterOptions"
                        :key="id"
                        class="filter-status-option"
                        ><input
                            type="checkbox"
                            :checked="store.assigneeFilters.includes(id)"
                            @change="store.toggleAssigneeFilter(id)"
                        /><span>{{ name }}</span></label
                    >
                </section>
                <section
                    class="filter-section filter-period"
                    aria-labelledby="filter-period-title"
                >
                    <b id="filter-period-title">Período</b
                    ><small
                        >Mostra tarefas cuja faixa no Gantt intersecta este
                        intervalo.</small
                    ><label
                        >De<input
                            v-model="store.periodStart"
                            type="date"
                            aria-label="Período inicial" /></label
                    ><label
                        >Até<input
                            v-model="store.periodEnd"
                            type="date"
                            aria-label="Período final"
                    /></label>
                </section>
            </div>

            <Teleport to="body"
                ><div
                    v-if="columnsMenu"
                    ref="columnPickerMenu"
                    class="column-picker-popover"
                    role="group"
                    aria-label="Colunas visíveis"
                    :style="{
                        top: columnPickerPosition.top + 'px',
                        left: columnPickerPosition.left + 'px',
                    }"
                    @keydown.esc="columnsMenu = false"
                >
                    <header>
                        <b>Colunas visíveis</b
                        ><small
                            >A configuração fica salva neste navegador.</small
                        >
                    </header>
                    <label class="mandatory"
                        ><input type="checkbox" checked disabled /> Tarefa
                        <small>Obrigatória · redimensionável</small></label
                    ><label v-for="column in workspaceColumns" :key="column.id"
                        ><input
                            v-model="columnVisibility[column.id]"
                            type="checkbox"
                        />
                        {{ column.label }}</label
                    >
                </div></Teleport
            >

            <Teleport to="body"
                ><section
                    v-if="taskContextMenu"
                    ref="taskContextMenuElement"
                    class="task-context-menu"
                    role="menu"
                    aria-label="Ações da tarefa"
                    :style="{
                        left: taskContextMenu.x + 'px',
                        top: taskContextMenu.y + 'px',
                    }"
                    @contextmenu.prevent
                >
                    <button
                        type="button"
                        role="menuitem"
                        :disabled="taskContextBusy"
                        @click="toggleTaskCompletionFromContext"
                    >
                        <svg
                            v-if="
                                !(
                                    taskContextMenu.task.completed ??
                                    taskContextMenu.task.status === 'completed'
                                )
                            "
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="8.5"></circle>
                            <path d="m8.3 12 2.4 2.5 5-5.2"></path></svg
                        ><svg v-else viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="8.5"></circle></svg
                        ><span>{{
                            (taskContextMenu.task.completed ??
                            taskContextMenu.task.status === "completed")
                                ? "Desfazer conclusão"
                                : "Concluir tarefa"
                        }}</span>
                    </button>
                    <div v-if="taskContextMenu.task.kind === 'task'" class="task-context-priorities" role="group" aria-label="Definir prioridade"><button v-for="option in taskPriorityOptions" :key="option.priority" type="button" class="task-context-priority" :class="[option.flag, { active: (taskContextMenu.task.priority ?? 1) === option.priority }]" :disabled="taskContextBusy" :aria-label="option.label" :title="option.label" @click="setTaskPriorityFromContext(option.priority)"><svg class="priority-flag-icon" :class="option.flag" viewBox="0 0 18 28" aria-hidden="true"><path class="flag-pole" d="M4 2.5 V25.5"></path><path class="flag-cloth" d="M5 4 H16 L13 8.5 L16 13 H5 Z"></path></svg></button></div>
                    <button
                        v-if="taskContextMenu.task.kind === 'section'"
                        type="button"
                        role="menuitem"
                        @click="editSectionFromContext"
                    >Editar seção</button>
                    <button
                        v-if="taskContextMenu.task.kind === 'section'"
                        type="button"
                        role="menuitem"
                        class="danger-btn"
                        @click="deleteSectionFromContext"
                    >Excluir seção e conteúdo</button>
                    </section
            ></Teleport>

            <section
                ref="ganttCard"
                class="gantt-card"
                :class="{ 'has-selection': store.selected.length > 0 }"
                :style="{
                    gridTemplateColumns: taskPaneWidth + 'px minmax(0,1fr)',
                }"
            >
                <div
                    ref="ganttHeadLeft"
                    class="gantt-head-left"
                    :style="{ gridTemplateColumns: taskGridTemplate }"
                >
                    <div class="column-toolbar">
                        <button
                            ref="columnPickerButton"
                            type="button"
                            class="column-picker-button"
                            :aria-expanded="columnsMenu"
                            aria-haspopup="true"
                            title="Escolher colunas"
                            @click="toggleColumnsMenu"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M4 5h16v14H4V5Zm2 2v10h4V7H6Zm6 0v10h6V7h-6Z"
                                /></svg
                            ><span>Colunas</span>
                        </button>
                    </div>
                    <span class="column-heading task-column-heading"
                        >TAREFA</span
                    ><span
                        v-for="column in visibleWorkspaceColumns"
                        :key="column.id"
                        class="column-heading"
                        >{{ column.shortLabel }}</span
                    ><button
                        type="button"
                        class="task-column-resizer"
                        role="separator"
                        aria-orientation="vertical"
                        aria-label="Redimensionar coluna Tarefa"
                        :aria-valuemin="TASK_COLUMN_MIN"
                        :aria-valuemax="taskColumnMax"
                        :aria-valuenow="taskColumnWidth"
                        :aria-valuetext="`${taskColumnWidth} pixels`"
                        :style="{ left: taskColumnWidth - 5 + 'px' }"
                        @pointerdown="startTaskColumnResize"
                        @keydown="taskColumnResizeKeydown"
                    >
                        <i aria-hidden="true"></i>
                    </button>
                </div>
                <div class="timeline-head">
                    <div
                        class="months"
                        :style="{
                            width: days.length * dayWidth + 'px',
                            transform: `translateX(${-scrollLeft}px)`,
                        }"
                    >
                        <span
                            v-for="m in monthSegments"
                            :style="{ width: m.span * dayWidth + 'px' }"
                            >{{ m.label }}</span
                        >
                    </div>
                    <div class="day-heads">
                        <span
                            v-for="day in renderedDays"
                            :class="{
                                weekend: [0, 6].includes(day.date.getDay()),
                                today:
                                    day.date.toISOString().slice(0, 10) ===
                                    todayCivil(),
                            }"
                            :style="{
                                width: dayWidth + 'px',
                                left: day.index * dayWidth - scrollLeft + 'px',
                            }"
                            ><b>{{
                                day.date
                                    .toLocaleDateString("pt-BR", {
                                        weekday: "short",
                                    })
                                    .slice(0, 3)
                            }}</b
                            >{{ day.date.getDate() }}</span
                        >
                    </div>
                </div>
                <div
                    ref="timelineElement"
                    class="gantt-scroll"
                    role="tree"
                    aria-label="Linhas e cronograma do projeto"
                    tabindex="0"
                    @focus="adoptTimelineCursor"
                    @keydown="ganttKeydown"
                    @scroll="onTimelineScroll"
                >
                    <div
                        class="gantt-body"
                        role="none"
                        :style="{
                            width:
                                taskPaneWidth + days.length * dayWidth + 'px',
                            height: visibleTasks.length * rowHeight + 'px',
                        }"
                    >
                        <div
                            class="task-pane-surface"
                            aria-hidden="true"
                            :style="{
                                width: taskPaneWidth + 'px',
                                height: visibleTasks.length * rowHeight + 'px',
                            }"
                        ></div>
                        <div
                            ref="timelinePlane"
                            class="timeline-plane"
                            role="none"
                            :style="{
                                left: taskPaneWidth + 'px',
                                width: days.length * dayWidth + 'px',
                                height: visibleTasks.length * rowHeight + 'px',
                            }"
                        >
                            <div
                                v-for="day in renderedDays"
                                :key="day.date.toISOString()"
                                class="day-column"
                                :class="{
                                    weekend: [0, 6].includes(day.date.getDay()),
                                }"
                                :style="{
                                    left: day.index * dayWidth + 'px',
                                    width: dayWidth + 'px',
                                }"
                            ></div>
                            <div
                                class="today-line"
                                :style="{ left: px(todayCivil()) + 'px' }"
                            >
                                <span>HOJE</span>
                            </div>
                            <svg
                                class="dependencies"
                                :width="days.length * dayWidth"
                                :height="visibleTasks.length * rowHeight"
                            >
                                <defs>
                                    <marker
                                        id="arrow"
                                        markerWidth="6"
                                        markerHeight="6"
                                        refX="5"
                                        refY="3"
                                        orient="auto"
                                    >
                                        <path d="M0 0 L6 3 L0 6Z" />
                                    </marker>
                                    <marker
                                        id="arrow-critical"
                                        markerWidth="6"
                                        markerHeight="6"
                                        refX="5"
                                        refY="3"
                                        orient="auto"
                                    >
                                        <path d="M0 0 L6 3 L0 6Z" />
                                    </marker>
                                </defs>
                                <path
                                    v-for="dep in store.workspace?.dependencies"
                                    :key="dep.id"
                                    :d="pathFor(dep)"
                                    :class="{ critical: dep.critical }"
                                    marker-end="url(#arrow)"
                                />
                            </svg>
                            <svg
                                v-if="connectionPreview"
                                class="connection-preview"
                                :width="days.length * dayWidth"
                                :height="visibleTasks.length * rowHeight"
                                aria-hidden="true"
                            >
                                <path :d="connectionPreview.path" />
                            </svg>
                            <span
                                v-if="connectionPreview?.type"
                                class="connection-type-badge"
                                :style="{
                                    left: connectionPreview.x + 'px',
                                    top: connectionPreview.y + 'px',
                                }"
                                >{{ connectionPreview.type }}</span
                            >
                        </div>
                        <div
                            v-for="(task, renderedIndex) in renderedTasks"
                            :key="task.id"
                            class="gantt-row"
                            role="none"
                            :style="{
                                top: rowOffset(task.id) + 'px',
                                height: rowHeight + 'px',
                                width:
                                    taskPaneWidth +
                                    days.length * dayWidth +
                                    'px',
                            }"
                            @mouseenter="hoveredTaskId = task.id"
                            @mouseleave="hoveredTaskId = null"
                            @dblclick="openTaskFromDoubleClick(task, $event)"
                        >
                            <div
                                class="task-row"
                                role="treeitem"
                                :data-task-id="task.id"
                                :aria-level="task.level + 1"
                                :aria-expanded="
                                    isExpandable(task)
                                        ? !store.hiddenGroups.has(task.id)
                                        : undefined
                                "
                                :aria-selected="
                                    task.kind === 'task'
                                        ? store.selected.includes(task.id)
                                        : undefined
                                "
                                :tabindex="
                                    cursorTaskId === task.id ||
                                    (!cursorTaskId &&
                                        virtualStart + renderedIndex === 0)
                                        ? 0
                                        : -1
                                "
                                :class="[
                                    {
                                        section: task.kind === 'section',
                                        parent: isExpandable(task),
                                        selected: store.selected.includes(
                                            task.id,
                                        ),
                                        hovered: hoveredTaskId === task.id,
                                        'hierarchy-ancestor':
                                            isHoveredAncestor(task),
                                        cursor: cursorTaskId === task.id,
                                        'calendar-inconsistent':
                                            task.calendar_inconsistent &&
                                            !isExpandable(task),
                                    },
                                    task.status,
                                    structureDropClass(task),
                                    {
                                        'structure-drag-source':
                                            structureDrag?.task.id === task.id,
                                    },
                                ]"
                                :style="{
                                    height: rowHeight + 'px',
                                    width: taskPaneWidth + 'px',
                                    gridTemplateColumns: taskGridTemplate,
                                }"
                                @focus="cursorTaskId = task.id"
                                @click="moveCursorTo(task)"
                                @contextmenu="
                                    openTaskContextMenuFromMouse(task, $event)
                                "
                                @pointerdown="
                                    beginTaskContextLongPress(task, $event)
                                "
                                @pointermove="moveTaskContextLongPress"
                                @pointerup="cancelTaskContextLongPress"
                                @pointercancel="cancelTaskContextLongPress"
                                @keydown="rowKeydown(task, $event)"
                            >
                                <div
                                    class="task-name"
                                    :style="{
                                        '--tree-row-height': rowHeight + 'px',
                                    }"
                                >
                                    <span class="task-selection-slot"
                                        ><input
                                            v-if="task.kind === 'task'"
                                            class="task-check"
                                            type="checkbox"
                                            :checked="
                                                store.selected.includes(task.id)
                                            "
                                            :aria-label="`Selecionar ${task.title}`"
                                            @click.stop="
                                                selectFromCheckbox(task, $event)
                                            "
                                    /></span>
                                    <button
                                        v-if="canMoveStructure"
                                        type="button"
                                        class="structure-drag-handle"
                                        :aria-label="`Reorganizar ${task.title}`"
                                        :title="`Reorganizar ${task.title}`"
                                        @pointerdown.stop="startStructureDrag(task, $event)"
                                    >⠿</button><div
                                        class="task-tree-content"
                                        :class="{
                                            'has-expanded-children':
                                                isExpandable(task) &&
                                                !store.hiddenGroups.has(
                                                    task.id,
                                                ),
                                            'node-down-active':
                                                isTreeSegmentActive(
                                                    task,
                                                    task.level,
                                                    'down',
                                                ),
                                        }"
                                        :style="{ '--tree-depth': task.level }"
                                    >
                                        <span
                                            v-if="task.level"
                                            class="tree-prefix"
                                            aria-hidden="true"
                                            ><svg
                                                v-for="(
                                                    ancestorId, depth
                                                ) in ancestorsFor(task)"
                                                :key="ancestorId"
                                                class="tree-slot"
                                                :class="{
                                                    'current-branch':
                                                        isCurrentBranch(
                                                            task,
                                                            depth,
                                                        ),
                                                    'ancestor-slot':
                                                        !isCurrentBranch(
                                                            task,
                                                            depth,
                                                        ),
                                                }"
                                                width="22"
                                                :height="rowHeight"
                                                viewBox="0 0 22 100"
                                                preserveAspectRatio="none"
                                            >
                                                <path
                                                    v-if="
                                                        isCurrentBranch(
                                                            task,
                                                            depth,
                                                        )
                                                    "
                                                    class="tree-segment-base tree-branch-path"
                                                    d="M11 0 V44 Q11 50 17 50 H22"
                                                ></path>
                                                <path
                                                    v-if="
                                                        isCurrentBranch(
                                                            task,
                                                            depth,
                                                        ) &&
                                                        hasNextSibling(task)
                                                    "
                                                    class="tree-segment-base tree-sibling-continuation"
                                                    d="M11 44 V100"
                                                ></path>
                                                <path
                                                    v-if="
                                                        !isCurrentBranch(
                                                            task,
                                                            depth,
                                                        ) &&
                                                        ancestorSlotContinues(
                                                            task,
                                                            depth,
                                                        )
                                                    "
                                                    class="tree-segment-base tree-sibling-continuation"
                                                    d="M11 0 V100"
                                                ></path>
                                                <path
                                                    v-if="
                                                        isCurrentBranch(
                                                            task,
                                                            depth,
                                                        ) &&
                                                        activeBranchPath(
                                                            task,
                                                            depth,
                                                        )
                                                    "
                                                    class="tree-segment-active"
                                                    :d="
                                                        activeBranchPath(
                                                            task,
                                                            depth,
                                                        )
                                                    "
                                                ></path>
                                                <path
                                                    v-if="
                                                        activeVerticalPath(
                                                            task,
                                                            depth,
                                                            isCurrentBranch(
                                                                task,
                                                                depth,
                                                            ),
                                                        )
                                                    "
                                                    class="tree-segment-active"
                                                    :d="
                                                        activeVerticalPath(
                                                            task,
                                                            depth,
                                                            isCurrentBranch(
                                                                task,
                                                                depth,
                                                            ),
                                                        )
                                                    "
                                                ></path></svg></span
                                        ><button
                                            v-if="isExpandable(task)"
                                            type="button"
                                            class="gantt-tree-toggle"
                                            :title="`${store.hiddenGroups.has(task.id) ? 'Expandir' : 'Recolher'} subitens`"
                                            :aria-label="`${store.hiddenGroups.has(task.id) ? 'Expandir' : 'Recolher'} ${task.title}`"
                                            :aria-expanded="
                                                !store.hiddenGroups.has(task.id)
                                            "
                                            @pointerdown.stop
                                            @click.stop.prevent="
                                                toggleExpansion(task)
                                            "
                                        >
                                            <span
                                                class="gantt-tree-toggle-chevron"
                                                :class="{
                                                    expanded:
                                                        !store.hiddenGroups.has(
                                                            task.id,
                                                        ),
                                                }"
                                                aria-hidden="true"
                                                >›</span
                                            ></button
                                        ><span
                                            v-else
                                            class="task-terminal-slot"
                                            :class="{
                                                'has-priority':
                                                    taskPriorityLevel(task),
                                            }"
                                            ><svg
                                                class="gantt-tree-toggle-spacer"
                                                :class="{
                                                    connected: task.level > 0,
                                                    active:
                                                        task.level > 0 &&
                                                        isTreeSegmentActive(
                                                            task,
                                                            task.level - 1,
                                                            'right',
                                                        ),
                                                }"
                                                width="22"
                                                :height="rowHeight"
                                                viewBox="0 0 22 100"
                                                preserveAspectRatio="none"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    v-if="task.level > 0"
                                                    :d="
                                                        taskPriorityLevel(task)
                                                            ? 'M0 50 H6'
                                                            : 'M0 50 H22'
                                                    "
                                                ></path></svg
                                            ><svg
                                                v-if="taskPriorityLevel(task)"
                                                class="task-priority-flag"
                                                :class="`p${taskPriorityLevel(task)}`"
                                                viewBox="0 0 18 28"
                                                role="img"
                                                :aria-label="`Prioridade P${taskPriorityLevel(task)}`"
                                            >
                                                <title>
                                                    Prioridade P{{
                                                        taskPriorityLevel(task)
                                                    }}
                                                </title>
                                                <path
                                                    class="flag-pole"
                                                    d="M4 2.5 V25.5"
                                                ></path>
                                                <path
                                                    class="flag-cloth"
                                                    d="M5 4 H16 L13 8.5 L16 13 H5 Z"
                                                ></path></svg
                                        ></span>
                                        <div class="task-title">
                                            <div class="task-title-line">
                                                <b>{{ task.title }}</b>
                                            </div>
                                            <small
                                                v-if="
                                                    task.kind === 'task' &&
                                                    !isExpandable(task) &&
                                                    task.description
                                                "
                                                class="task-description"
                                                :title="task.description"
                                                >{{ task.description }}</small
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="columnVisibility.assignee"
                                    class="task-assignee task-meta-cell"
                                >
                                    <template
                                        v-if="
                                            task.kind === 'task' &&
                                            !isExpandable(task)
                                        "
                                        ><span
                                            v-if="task.assignee"
                                            class="mini-avatar"
                                            :title="task.assignee"
                                            >{{ task.assignee }}</span
                                        ><span v-else>—</span></template
                                    >
                                </div>
                                <div
                                    v-if="columnVisibility.status"
                                    class="task-status task-meta-cell"
                                >
                                    <template
                                        v-if="
                                            task.kind === 'task' &&
                                            !isExpandable(task)
                                        "
                                        ><span
                                            v-if="task.calendar_inconsistent"
                                            class="calendar-warning"
                                            title="A data cai em dia não útil; simule para corrigir"
                                            >Calendário</span
                                        ><span v-else class="status-label"
                                            ><i></i
                                            >{{
                                                statusLabel(task.status)
                                            }}</span
                                        ></template
                                    >
                                </div>
                                <div
                                    v-if="columnVisibility.start"
                                    class="task-date task-meta-cell"
                                >
                                    <template v-if="task.kind === 'task'"
                                        ><span
                                            :title="
                                                task.start ??
                                                'Sem data inicial explícita'
                                            "
                                            >{{
                                                formatTaskDate(task.start)
                                            }}</span
                                        ></template
                                    >
                                </div>
                                <div
                                    v-if="columnVisibility.finish"
                                    class="task-date task-meta-cell"
                                >
                                    <template v-if="task.kind === 'task'"
                                        ><span
                                            :title="
                                                task.finish ??
                                                'Sem deadline explícita'
                                            "
                                            >{{
                                                formatTaskDate(task.finish)
                                            }}</span
                                        ></template
                                    >
                                </div>
                                <div
                                    v-if="columnVisibility.comments"
                                    class="task-comments task-meta-cell"
                                >
                                    <template v-if="task.kind === 'task'"
                                        ><span
                                            :aria-label="`${task.comment_count ?? 0} comentário(s)`"
                                            :title="`${task.comment_count ?? 0} comentário(s)`"
                                            >▱
                                            {{ task.comment_count ?? 0 }}</span
                                        ></template
                                    >
                                </div>
                            </div>
                            <div
                                class="bar-lane"
                                :class="{
                                    selected: store.selected.includes(task.id),
                                    hovered: hoveredTaskId === task.id,
                                    'hierarchy-ancestor':
                                        isHoveredAncestor(task),
                                    cursor: cursorTaskId === task.id,
                                }"
                                :style="{
                                    left: taskPaneWidth + 'px',
                                    height: rowHeight + 'px',
                                }"
                                @click="moveCursorTo(task)"
                            >
                                <div
                                    v-if="gesturePreviewFor(task)"
                                    class="task-bar drag-ghost"
                                    :class="[
                                        `gesture-${gesturePreviewFor(task)?.kind}`,
                                        {
                                            committing:
                                                gesturePreviewFor(task)
                                                    ?.committing,
                                            provisional: !task.start,
                                        },
                                    ]"
                                    :style="{
                                        left:
                                            px(
                                                gesturePreviewFor(task)!
                                                    .previewStart,
                                            ) + 'px',
                                        width:
                                            barWidth(
                                                gesturePreviewFor(task)!
                                                    .previewStart,
                                                gesturePreviewFor(task)!
                                                    .previewFinish,
                                                dayWidth,
                                            ) + 'px',
                                    }"
                                    :aria-label="`${gesturePreviewFor(task)?.kind === 'move' ? 'Movendo' : 'Redimensionando'} ${task.title}: ${gesturePreviewFor(task)?.previewStart} até ${gesturePreviewFor(task)?.previewFinish}`"
                                ></div>
                                <div
                                    v-else
                                    class="task-bar"
                                    :class="[
                                        task.kind,
                                        task.status,
                                        {
                                            critical:
                                                task.critical &&
                                                !isExpandable(task),
                                            summary: isExpandable(task),
                                            parent: task.has_children,
                                            provisional:
                                                task.kind === 'task' &&
                                                !isExpandable(task) &&
                                                !civilDate(task.start),
                                            'calendar-inconsistent':
                                                task.calendar_inconsistent &&
                                                !isExpandable(task),
                                            'controls-visible':
                                                cursorTaskId === task.id ||
                                                gestureMode === 'connect',
                                        },
                                    ]"
                                    :style="{
                                        left: px(visualStart(task)) + 'px',
                                        width: width(task) + 'px',
                                    }"
                                    :aria-label="`${task.title}: ${civilDate(task.start) ?? 'sem data, exibida provisoriamente em hoje'}`"
                                    :title="task.title"
                                    @click.stop="moveCursorTo(task)"
                                    @pointerdown.stop="
                                        moveCursorTo(task);
                                        startDrag(task, $event);
                                    "
                                >
                                    <template v-if="isExpandable(task)"
                                        ><i class="group-line"></i
                                        ><i class="group-left"></i
                                        ><i class="group-right"></i
                                    ></template>
                                    <template v-else
                                        ><i
                                            class="progress-fill"
                                            :style="{
                                                width: task.progress + '%',
                                            }"
                                        ></i
                                    ></template>
                                    <template v-if="canResizeTask(task)"
                                        ><button
                                            type="button"
                                            class="timeblock-grip start"
                                            title="Ajustar data inicial"
                                            :aria-label="`Ajustar início de ${task.title}`"
                                            @pointerdown.stop="
                                                startResize(
                                                    task,
                                                    'start',
                                                    $event,
                                                )
                                            "
                                            @keydown="
                                                resizeGripKeydown(
                                                    task,
                                                    'start',
                                                    $event,
                                                )
                                            "
                                        ></button
                                        ><button
                                            type="button"
                                            class="timeblock-grip finish"
                                            title="Ajustar deadline"
                                            :aria-label="`Ajustar deadline de ${task.title}`"
                                            @pointerdown.stop="
                                                startResize(
                                                    task,
                                                    'finish',
                                                    $event,
                                                )
                                            "
                                            @keydown="
                                                resizeGripKeydown(
                                                    task,
                                                    'finish',
                                                    $event,
                                                )
                                            "
                                        ></button
                                    ></template>
                                    <template v-if="canConnectFrom(task)"
                                        ><button
                                            type="button"
                                            class="dependency-port start"
                                            :class="{
                                                visible:
                                                    gestureMode === 'connect',
                                                'drop-target':
                                                    timeGesture?.kind ===
                                                        'connect' &&
                                                    timeGesture.targetTaskId ===
                                                        task.id &&
                                                    timeGesture.targetEndpoint ===
                                                        'start',
                                                invalid:
                                                    gestureMode === 'connect' &&
                                                    !connectionValidation(
                                                        task,
                                                        'start',
                                                    ).valid,
                                            }"
                                            :data-task-id="task.id"
                                            data-endpoint="start"
                                            :aria-label="`Conector de início de ${task.title}`"
                                            :aria-disabled="
                                                gestureMode === 'connect' &&
                                                !connectionValidation(
                                                    task,
                                                    'start',
                                                ).valid
                                            "
                                            :title="
                                                gestureMode === 'connect'
                                                    ? connectionValidation(
                                                          task,
                                                          'start',
                                                      ).reason ||
                                                      'Conectar ao início'
                                                    : 'Criar relação a partir do início'
                                            "
                                            @pointerdown.stop="
                                                startConnection(
                                                    task,
                                                    'start',
                                                    $event,
                                                )
                                            "
                                            @keydown="
                                                dependencyPortKeydown(
                                                    task,
                                                    'start',
                                                    $event,
                                                )
                                            "
                                        ></button
                                        ><button
                                            type="button"
                                            class="dependency-port finish"
                                            :class="{
                                                visible:
                                                    gestureMode === 'connect',
                                                'drop-target':
                                                    timeGesture?.kind ===
                                                        'connect' &&
                                                    timeGesture.targetTaskId ===
                                                        task.id &&
                                                    timeGesture.targetEndpoint ===
                                                        'finish',
                                                invalid:
                                                    gestureMode === 'connect' &&
                                                    !connectionValidation(
                                                        task,
                                                        'finish',
                                                    ).valid,
                                            }"
                                            :data-task-id="task.id"
                                            data-endpoint="finish"
                                            :aria-label="`Conector de fim de ${task.title}`"
                                            :aria-disabled="
                                                gestureMode === 'connect' &&
                                                !connectionValidation(
                                                    task,
                                                    'finish',
                                                ).valid
                                            "
                                            :title="
                                                gestureMode === 'connect'
                                                    ? connectionValidation(
                                                          task,
                                                          'finish',
                                                      ).reason ||
                                                      'Conectar ao fim'
                                                    : 'Criar relação a partir do fim'
                                            "
                                            @pointerdown.stop="
                                                startConnection(
                                                    task,
                                                    'finish',
                                                    $event,
                                                )
                                            "
                                            @keydown="
                                                dependencyPortKeydown(
                                                    task,
                                                    'finish',
                                                    $event,
                                                )
                                            "
                                        ></button
                                    ></template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="store.selected.length"
                    class="selection-bar"
                    role="region"
                    aria-label="Comandos da seleção"
                >
                    <b>{{ store.selected.length }} tarefa(s) selecionada(s)</b
                    ><span aria-live="polite"
                        >Use os checkboxes; Shift seleciona um intervalo.</span
                    >
                    <div>
                        <button
                            class="soft-btn"
                            :disabled="store.selected.length !== 1"
                            @click="openSelectedTask"
                        >
                            Editar</button
                        ><button class="soft-btn" @click="store.selected = []">
                            Limpar seleção
                        </button>
                    </div>
                </div>
                <div class="gantt-footer">
                    <span><i class="legend opened"></i> Aberta</span
                    ><span><i class="legend blocked"></i> Bloqueada</span
                    ><span><i class="legend scheduled"></i> Agendada</span
                    ><span><i class="legend late"></i> Atrasada</span
                    ><span><i class="legend completed"></i> Concluída</span
                    ><span
                        ><i class="legend critical"></i> Caminho crítico</span
                    >
                    <div class="footer-right">
                        Fuso: América/São Paulo · Datas consideradas na projeção
                        <button>?</button>
                    </div>
                </div>
            </section>
        </main>

        <section
            v-if="hiddenDependencies.length"
            class="hidden-dependencies"
            aria-live="polite"
        >
            <div>
                <b>Relações ocultas pelos filtros ou recolhimento</b>
                <p>
                    Elas continuam ativas no planejamento e no cálculo do
                    caminho crítico.
                </p>
            </div>
            <ul>
                <li
                    v-for="dependency in hiddenDependencies"
                    :key="dependency.id"
                >
                    <span :class="{ critical: dependency.critical }"
                        >{{ taskTitle(dependency.from) }} →
                        {{ taskTitle(dependency.to) }} ({{
                            dependency.type
                        }})</span
                    ><button @click="revealDependency(dependency)">
                        Revelar
                    </button>
                </li>
            </ul>
        </section>
        <aside
            class="drawer"
            :class="{ open: drawer && (activeTask || sectionDraft), pinned: editorPinned }"
            role="dialog"
            aria-modal="false"
            aria-labelledby="task-editor-title"
        >
            <div
                v-if="editorPinned && drawer"
                class="drawer-resizer"
                role="separator"
                aria-label="Redimensionar editor de tarefa"
                aria-orientation="vertical"
                :aria-valuemin="editorMinWidth"
                :aria-valuemax="editorMaxWidth()"
                :aria-valuenow="editorWidth"
                tabindex="0"
                @pointerdown="startEditorResize"
                @keydown="resizeEditorFromKeyboard"
            ></div>
            <template v-if="activeTask">
                <header>
                    <div>
                        <span class="eyebrow">{{ isCreatingTask ? 'NOVA TAREFA' : 'DETALHES DA TAREFA' }}</span>
                        <h2 id="task-editor-title">{{ isCreatingTask ? 'Adicionar tarefa' : activeTask.title }}</h2>
                    </div>
                    <div class="drawer-header-actions">
                        <button
                            class="drawer-pin"
                            :class="{ active: editorPinned }"
                            :aria-pressed="editorPinned"
                            :aria-label="
                                editorPinned
                                    ? 'Soltar editor do layout'
                                    : 'Fixar editor no layout'
                            "
                            :title="
                                editorPinned ? 'Soltar editor' : 'Fixar editor'
                            "
                            @click="toggleEditorPinned"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M8 3h8l-1 6 3 3v2h-5v7l-1 1-1-1v-7H6v-2l3-3-1-6Z"
                                ></path>
                            </svg>
                        </button>
                        <button
                            class="drawer-close"
                            aria-label="Fechar editor"
                            title="Fechar"
                            @click="requestTaskEditorClose"
                        >
                            ×
                        </button>
                    </div>
                </header>
                <div class="drawer-body">
                    <div class="task-title-field"><label>Título<input v-model="activeTask.title" /></label><div class="editor-priority-wrap"><button type="button" class="editor-priority-button" :class="taskPriorityOptions.find((option) => option.priority === (activeTask.priority ?? 1))?.flag" aria-label="Definir prioridade" title="Definir prioridade" @click="editorPriorityMenu = !editorPriorityMenu"><svg class="priority-flag-icon" :class="taskPriorityOptions.find((option) => option.priority === (activeTask.priority ?? 1))?.flag" viewBox="0 0 18 28" aria-hidden="true"><path class="flag-pole" d="M4 2.5 V25.5"></path><path class="flag-cloth" d="M5 4 H16 L13 8.5 L16 13 H5 Z"></path></svg></button><div v-if="editorPriorityMenu" class="editor-priority-menu"><button v-for="option in taskPriorityOptions" :key="option.priority" type="button" :class="[option.flag, { active: (activeTask.priority ?? 1) === option.priority }]" :aria-label="option.label" :title="option.label" @click="activeTask.priority = option.priority; editorPriorityMenu = false"><svg class="priority-flag-icon" :class="option.flag" viewBox="0 0 18 28" aria-hidden="true"><path class="flag-pole" d="M4 2.5 V25.5"></path><path class="flag-cloth" d="M5 4 H16 L13 8.5 L16 13 H5 Z"></path></svg></button></div></div></div>
                    <label
                        >Descrição<textarea
                            v-model="activeTask.description"
                            rows="4"
                            placeholder="Descrição da tarefa"
                        ></textarea>
                    </label>
                    <label>Posição na hierarquia<HierarchyCombobox v-model="activeTask.section_id" :items="store.workspace?.tasks ?? []" /></label>
                    <div class="form-grid" style="grid-template-columns: 1fr">
                        <label
                            >Responsável<PersonCombobox v-model="activeTask.assignee_id" :people="collaborators" /></label
                        >
                    </div>
                    <div class="form-grid">
                        <label
                            >Data inicial planejada<input
                                v-model="activeTask.start"
                                type="date" /></label
                        ><label
                            >Data final planejada<input
                                v-model="activeTask.finish"
                                type="date"
                        /></label>
                    </div>
                    <div class="form-grid"><label class="completion-toggle"><input v-model="activeTask.completed" type="checkbox" @change="ensureCompletionDate" />Concluída</label><label v-if="activeTask.completed">Data efetiva de conclusão<input v-model="activeTask.effective_completion" type="date" required /></label></div>
                    <section
                        class="projection-summary"
                        aria-label="Projeção calculada"
                    >
                        <div>
                            <small>STATUS CALCULADO</small
                            ><b>{{ statusLabel(activeTask.status) }}</b>
                        </div>
                        <div>
                            <small>INÍCIO CONSIDERADO</small
                            ><b>{{
                                activeTask.considered_start || todayCivil()
                            }}</b>
                        </div>
                        <div>
                            <small>DEADLINE CONSIDERADA</small
                            ><b>{{
                                activeTask.considered_deadline ||
                                activeTask.considered_start ||
                                todayCivil()
                            }}</b>
                        </div>
                        <div v-if="activeTask.unlock_date">
                            <small>DATA DE DESBLOQUEIO</small
                            ><b>{{ activeTask.unlock_date }}</b>
                        </div>
                        <p>
                            Status calculado a partir do planejamento, das dependências e da conclusão.
                        </p>
                    </section>
                    <section
                        v-if="!isCreatingTask"
                        class="comments-box"
                        aria-labelledby="comments-title"
                    >
                        <header>
                            <div>
                                <b id="comments-title">Comentários</b
                                ><small
                                    >{{
                                        taskComments.length
                                    }}
                                    comentário(s)</small
                                >
                            </div>
                        </header>
                        <p v-if="editorContextLoading" class="dependency-empty">
                            Carregando comentários…
                        </p>
                        <p
                            v-else-if="!taskComments.length"
                            class="dependency-empty"
                        >
                            Nenhum comentário nesta tarefa.
                        </p>
                        <article
                            v-for="comment in taskComments"
                            :key="comment.id"
                            class="task-comment"
                        >
                            <header>
                                <b>{{ collaboratorName(comment.author_id) }}</b
                                ><time v-if="comment.posted_at">{{
                                    new Date(comment.posted_at).toLocaleString(
                                        "pt-BR",
                                    )
                                }}</time>
                            </header>
                            <template v-if="editingCommentId === comment.id">
                                <textarea
                                    v-model="commentEditDraft"
                                    rows="3"
                                ></textarea>
                                <div class="comment-actions">
                                    <button
                                        class="soft-btn"
                                        @click="cancelCommentEdit"
                                    >
                                        Cancelar</button
                                    ><button
                                        class="primary"
                                        :disabled="!commentEditDraft.trim()"
                                        @click="publishCommentEdit"
                                    >
                                        Salvar
                                    </button>
                                </div></template
                            ><template v-else
                                ><p>{{ comment.content }}</p>
                                <button
                                    v-if="comment.editable"
                                    class="comment-edit"
                                    @click="beginCommentEdit(comment)"
                                >
                                    Editar
                                </button></template
                            >
                        </article>
                        <label
                            >Novo comentário<textarea
                                v-model="commentDraft"
                                rows="3"
                                placeholder="Escreva um comentário"
                            ></textarea></label
                        ><button
                            class="soft-btn comment-submit"
                            :disabled="!commentDraft.trim()"
                            @click="publishComment"
                        >
                            Publicar comentário
                        </button>
                    </section>
                    <section
                        v-if="!isCreatingTask"
                        class="dependency-box"
                        aria-labelledby="task-relations-title"
                    >
                        <header class="dependency-box-header">
                            <span aria-hidden="true">⌁</span>
                            <div>
                                <b id="task-relations-title">Relações</b
                                ><small
                                    >{{
                                        predecessorDependencies.length
                                    }}
                                    predecessora(s) ·
                                    {{
                                        dependentDependencies.length
                                    }}
                                    dependente(s)</small
                                >
                            </div>
                        </header>
                        <section
                            class="dependency-direction"
                            aria-labelledby="predecessor-relations-title"
                        >
                            <header>
                                <b id="predecessor-relations-title"
                                    >Depende de</b
                                ><span>{{
                                    predecessorDependencies.length
                                }}</span
                                ><button
                                    type="button"
                                    class="dependency-add"
                                    aria-label="Adicionar predecessora"
                                    title="Adicionar predecessora"
                                    :disabled="isExpandable(activeTask)"
                                    @click="openRelationModal('predecessor')"
                                >
                                    +
                                </button>
                            </header>
                            <p
                                v-if="!predecessorDependencies.length"
                                class="dependency-empty"
                            >
                                Nenhuma predecessora.
                            </p>
                            <ul v-else>
                                <li
                                    v-for="dependency in predecessorDependencies"
                                    :key="dependency.id"
                                    class="dependency-item"
                                >
                                    <span class="dependency-type"
                                        >[{{ dependency.type }}]</span
                                    ><span
                                        class="dependency-task-title"
                                        :title="taskTitle(dependency.from)"
                                        >{{ taskTitle(dependency.from) }}</span
                                    ><button
                                        type="button"
                                        class="dependency-delete"
                                        :aria-label="`Remover dependência ${dependency.type} de ${taskTitle(dependency.from)}`"
                                        :title="`Remover relação com ${taskTitle(dependency.from)}`"
                                        @click="
                                            requestRemoveDependency(
                                                dependency.id,
                                            )
                                        "
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-.7 11H7.7L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"
                                            />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                        </section>
                        <section
                            class="dependency-direction"
                            aria-labelledby="dependent-relations-title"
                        >
                            <header>
                                <b id="dependent-relations-title">Dependentes</b
                                ><span>{{ dependentDependencies.length }}</span
                                ><button
                                    type="button"
                                    class="dependency-add"
                                    aria-label="Adicionar dependente"
                                    title="Adicionar dependente"
                                    @click="openRelationModal('dependent')"
                                >
                                    +
                                </button>
                            </header>
                            <p
                                v-if="!dependentDependencies.length"
                                class="dependency-empty"
                            >
                                Nenhuma tarefa dependente.
                            </p>
                            <ul v-else>
                                <li
                                    v-for="dependency in dependentDependencies"
                                    :key="dependency.id"
                                    class="dependency-item"
                                >
                                    <span class="dependency-type"
                                        >[{{ dependency.type }}]</span
                                    ><span
                                        class="dependency-task-title"
                                        :title="taskTitle(dependency.to)"
                                        >{{ taskTitle(dependency.to) }}</span
                                    ><button
                                        type="button"
                                        class="dependency-delete"
                                        :aria-label="`Remover dependente ${dependency.type} ${taskTitle(dependency.to)}`"
                                        :title="`Remover relação com ${taskTitle(dependency.to)}`"
                                        @click="
                                            requestRemoveDependency(
                                                dependency.id,
                                            )
                                        "
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-.7 11H7.7L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"
                                            />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                        </section>
                        <section
                            v-if="dependencyConfirmation"
                            class="dependency-confirm"
                            role="alert"
                        >
                            <b>Remover dependência?</b>
                            <p>A relação será removida do planejamento.</p>
                            <div>
                                <button
                                    class="soft-btn"
                                    @click="dependencyConfirmation = null"
                                >
                                    Cancelar</button
                                ><button
                                    class="primary"
                                    @click="confirmDependency"
                                >
                                    Confirmar
                                </button>
                            </div>
                        </section>
                    </section>
                    <section
                        v-if="deletionPreview"
                        class="delete-preview"
                        role="alert"
                    >
                        <b>Excluir esta tarefa?</b>
                        <p>
                            {{ deletionPreview.incoming.length }} entrada(s),
                            {{ deletionPreview.outgoing.length }} saída(s) e
                            {{ deletionPreview.continuity.length }} ligação(ões)
                            de continuidade possíveis.
                        </p>
                        <label
                            ><input
                                v-model="preserveContinuity"
                                type="checkbox"
                            />
                            Preservar continuidade (FS)</label
                        >
                        <div>
                            <button
                                class="soft-btn"
                                @click="deletionPreview = null"
                            >
                                Cancelar</button
                            ><button
                                class="danger-btn"
                                :disabled="deleting"
                                @click="deleteTask"
                            >
                                {{
                                    deleting
                                        ? "Excluindo…"
                                        : "Confirmar exclusão"
                                }}
                            </button>
                        </div>
                    </section>
                </div>
                <section
                    v-if="closeConfirmation"
                    class="unsaved-confirm"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="unsaved-title"
                    aria-describedby="unsaved-description"
                >
                    <b id="unsaved-title">Alterações não salvas</b>
                    <p id="unsaved-description">
                        {{
                            pendingTaskToOpen
                                ? "Salve ou descarte o rascunho antes de editar outra tarefa."
                                : "Salve ou descarte o rascunho antes de fechar o editor."
                        }}
                    </p>
                    <div>
                        <button
                            class="soft-btn continue-editing"
                            @click="continueTaskEditing"
                        >
                            Continuar editando</button
                        ><button
                            class="danger-btn discard-changes"
                            @click="discardTaskDraft"
                        >
                            Descartar alterações</button
                        ><button
                            class="primary save-before-close"
                            :disabled="deleting"
                            @click="saveTask"
                        >
                            Salvar alterações
                        </button>
                    </div>
                </section>
                <form class="create-task" @submit.prevent="createPerson">
                    <input
                        v-model="newPersonName"
                        aria-label="Nome do responsável"
                        placeholder="Cadastrar responsável"
                    /><input
                        v-model="newPersonEmail"
                        aria-label="E-mail do responsável"
                        type="email"
                        placeholder="E-mail (opcional)"
                    /><button
                        class="soft-btn"
                        :disabled="creatingPerson || !newPersonName.trim()"
                    >
                        {{
                            creatingPerson ? "Cadastrando…" : "Adicionar pessoa"
                        }}
                    </button>
                </form>
                <footer>
                    <button
                        v-if="!isCreatingTask && activeTask.kind === 'task' && !deletionPreview"
                        class="danger-btn"
                        @click="previewDeletion"
                    >
                        Excluir</button
                    ><button
                        class="soft-btn drawer-cancel"
                        @click="requestTaskEditorClose"
                    >
                        Cancelar</button
                    ><button
                        class="primary"
                        :disabled="deleting"
                        @click="saveTask"
                    >
                        {{ isCreatingTask ? 'Criar tarefa' : 'Salvar alterações' }}
                    </button>
                </footer>
            </template>
            <template v-else-if="sectionDraft">
                <header><div><span class="eyebrow">{{ sectionDraft.id === '__new-section__' ? 'NOVA SEÇÃO' : 'EDITAR SEÇÃO' }}</span><h2 id="task-editor-title">{{ sectionDraft.id === '__new-section__' ? 'Adicionar seção' : sectionDraft.title }}</h2></div><div class="drawer-header-actions"><button class="drawer-close" aria-label="Fechar editor" @click="finishTaskEditorClose">×</button></div></header>
                <div class="drawer-body"><div class="source-line"><span class="todoist-mark">▤</span><div><b>Estrutura do projeto</b><small>Seções organizam tarefas e outras seções.</small></div></div><label>Nome da seção<input v-model="sectionDraft.title" autofocus placeholder="Ex.: Planejamento"></label><label>Seção-pai<HierarchyCombobox v-model="sectionDraft.parent_id" :items="store.workspace?.tasks ?? []" :exclude-id="sectionDraft.id" /></label></div>
                <footer><button class="soft-btn drawer-cancel" @click="finishTaskEditorClose">Cancelar</button><button class="primary" @click="saveSection">{{ sectionDraft.id === '__new-section__' ? 'Criar seção' : 'Salvar alterações' }}</button></footer>
            </template>
        </aside>
        <div
            v-if="relationModal"
            class="relation-modal-scrim"
            @keydown.esc="relationModal = null"
        >
            <section
                class="relation-modal"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="'relation-modal-title'"
            >
                <header>
                    <div>
                        <span class="eyebrow">NOVA RELAÇÃO</span>
                        <h2 id="relation-modal-title">
                            {{
                                relationModal.direction === "predecessor"
                                    ? "Adicionar predecessora"
                                    : "Adicionar dependente"
                            }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        aria-label="Fechar criação de relação"
                        @click="relationModal = null"
                    >
                        ×
                    </button>
                </header>
                <div class="relation-modal-body">
                    <figure
                        v-if="selectedRelationTask && relationModal.type"
                        class="relation-preview"
                    >
                        <div class="preview-block predecessor">
                            {{
                                relationModal.direction === "predecessor"
                                    ? selectedRelationTask.title
                                    : activeTask?.title
                            }}
                        </div>
                        <svg
                            viewBox="0 0 340 68"
                            role="img"
                            :aria-label="relationPreviewLabel"
                        >
                            <defs>
                                <marker
                                    id="relation-preview-arrow"
                                    markerWidth="7"
                                    markerHeight="7"
                                    refX="6"
                                    refY="3.5"
                                    orient="auto"
                                >
                                    <path d="M0 0 L7 3.5 L0 7Z" />
                                </marker>
                            </defs>
                            <path
                                :d="relationPreviewPath(relationModal.type)"
                                marker-end="url(#relation-preview-arrow)"
                            />
                        </svg>
                        <div class="preview-block successor">
                            {{
                                relationModal.direction === "predecessor"
                                    ? activeTask?.title
                                    : selectedRelationTask.title
                            }}
                        </div>
                        <figcaption>
                            <b>{{ relationModal.type }}</b> —
                            {{ relationPreviewLabel }}
                        </figcaption>
                    </figure>
                    <label
                        >Buscar tarefa<input
                            class="relation-search"
                            role="combobox"
                            aria-controls="relation-results"
                            :aria-expanded="relationCandidates.length > 0"
                            v-model="relationModal.search"
                            placeholder="Digite parte do título…"
                            @input="
                                relationModal.selectedId = null;
                                relationModal.type = null;
                            "
                    /></label>
                    <ul
                        v-if="
                            relationCandidates.length &&
                            !relationModal.selectedId
                        "
                        id="relation-results"
                        class="relation-results"
                        role="listbox"
                    >
                        <li
                            v-for="task in relationCandidates"
                            :key="task.id"
                            role="option"
                            :aria-selected="false"
                            @click="chooseRelationTask(task)"
                        >
                            <b>{{ task.title }}</b
                            ><small>{{
                                task.kind === "task" ? "Tarefa" : "Seção"
                            }}</small>
                        </li>
                    </ul>
                    <p
                        v-else-if="
                            normalizedRelationSearch &&
                            !relationModal.selectedId
                        "
                        class="relation-empty"
                    >
                        Nenhuma tarefa encontrada.
                    </p>
                    <div v-if="selectedRelationTask" class="relation-selected">
                        <span>Selecionada</span
                        ><b>{{ selectedRelationTask.title }}</b>
                    </div>
                    <fieldset v-if="selectedRelationTask">
                        <legend>Tipo de relacionamento</legend>
                        <button
                            v-for="type in ['FS', 'SS', 'FF', 'SF'] as const"
                            :key="type"
                            type="button"
                            :class="{ active: relationModal.type === type }"
                            :aria-pressed="relationModal.type === type"
                            @click="relationModal.type = type"
                        >
                            {{ type }}
                        </button>
                    </fieldset>
                    <p
                        v-if="relationModalValidation"
                        class="relation-validation"
                        role="alert"
                    >
                        {{ relationModalValidation }}
                    </p>
                </div>
                <footer>
                    <button class="soft-btn" @click="relationModal = null">
                        Cancelar</button
                    ><button
                        class="primary"
                        :disabled="
                            !relationModal.selectedId ||
                            !relationModal.type ||
                            relationBusy ||
                            Boolean(relationModalValidation)
                        "
                        @click="confirmRelationModal"
                    >
                        {{ relationBusy ? "Criando…" : "Criar relação" }}
                    </button>
                </footer>
            </section>
        </div>
        <div
            v-if="toast"
            class="toast"
            :class="toastKind"
            :role="toastKind === 'error' ? 'alert' : 'status'"
            :aria-live="toastKind === 'error' ? 'assertive' : 'polite'"
        >
            <span>{{
                toastKind === "success"
                    ? "✓"
                    : toastKind === "error"
                      ? "!"
                      : "i"
            }}</span
            >{{ toast
            }}<button
                v-if="undoDependencyId"
                type="button"
                @click="undoLastDependency"
            >
                Desfazer
            </button>
        </div>
    </div>
    <Teleport to="body">
        <div
            v-if="structureDrag"
            class="structure-drag-overlay"
            :class="{ invalid: structureDrag.drop && !structureDrag.drop.valid }"
            :style="{ left: structureDrag.x + 14 + 'px', top: structureDrag.y + 14 + 'px' }"
            aria-hidden="true"
        >
            <b>{{ structureDrag.task.kind === 'section' ? 'Seção' : 'Tarefa' }}</b>
            <span>{{ structureDrag.task.title }}</span>
            <small>{{ structureDrag.drop?.message ?? 'Escolha uma posição na estrutura' }}</small>
        </div>
    </Teleport>
    <AccountPanel
        :open="account"
        @close="account = false"
        @deleted="
            account = false;
            auth.logout();
        "
    />
    <ProjectMembersPanel
        v-if="calendarPanel && store.workspace"
        :project-id="store.workspace.project.id"
        :role="store.workspace.project.role ?? 'reader'"
        @close="calendarPanel = false"
        @people-changed="store.load()"
        @deleted="calendarPanel = false; store.clearWorkspace()"
    />
</template>
