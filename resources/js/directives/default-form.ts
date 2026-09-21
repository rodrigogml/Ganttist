import type { Directive } from "vue";

const defaultActionSelector = "button[data-default-submit]";
const ignoredShortcutSelector = "[data-default-form-shortcut-ignore]";

function isDefaultFormShortcut(event: KeyboardEvent): boolean {
    return event.key === "Enter" && (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && !event.isComposing;
}

function triggerDefaultAction(scope: HTMLElement, event: KeyboardEvent): void {
    if (event.defaultPrevented || !isDefaultFormShortcut(event) || (event.target instanceof Element && event.target.closest(ignoredShortcutSelector))) return;

    const action = scope.querySelector<HTMLButtonElement>(defaultActionSelector);
    if (!action || action.disabled || action.getAttribute("aria-disabled") === "true") return;

    event.preventDefault();
    event.stopPropagation();
    action.click();
}

type DefaultFormScope = HTMLElement & { __defaultFormShortcut?: (event: KeyboardEvent) => void };

export const defaultForm: Directive<HTMLElement> = {
    mounted(element) {
        const scope = element as DefaultFormScope;
        scope.__defaultFormShortcut = (event) => triggerDefaultAction(scope, event);
        scope.addEventListener("keydown", scope.__defaultFormShortcut, true);
    },
    unmounted(element) {
        const scope = element as DefaultFormScope;
        if (scope.__defaultFormShortcut) scope.removeEventListener("keydown", scope.__defaultFormShortcut, true);
        delete scope.__defaultFormShortcut;
    },
};
