// @vitest-environment jsdom
import type { ObjectDirective } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { defaultForm } from "./default-form";

function mountScope(markup = '<button data-default-submit type="button">Salvar</button>') {
    const scope = document.createElement("section");
    scope.innerHTML = markup;
    document.body.append(scope);
    (defaultForm as ObjectDirective<HTMLElement>).mounted?.(scope, {} as never, {} as never, null as never);
    return scope;
}

function shortcut(target: Element, options: KeyboardEventInit = {}) {
    const event = new KeyboardEvent("keydown", { bubbles: true, key: "Enter", ctrlKey: true, ...options });
    if (options.isComposing) Object.defineProperty(event, "isComposing", { value: true });
    target.dispatchEvent(event);
}

describe("defaultForm", () => {
    beforeEach(() => { document.body.innerHTML = ""; });

    it("triggers only its marked default action with Ctrl+Enter", () => {
        const scope = mountScope('<input><button data-default-submit type="button">Salvar</button><button type="button">Cancelar</button>');
        const save = scope.querySelector<HTMLButtonElement>("[data-default-submit]")!;
        const cancel = scope.querySelectorAll<HTMLButtonElement>("button")[1];
        const saveClick = vi.fn(), cancelClick = vi.fn();
        save.addEventListener("click", saveClick);
        cancel.addEventListener("click", cancelClick);
        shortcut(scope.querySelector("input")!);
        expect(saveClick).toHaveBeenCalledOnce();
        expect(cancelClick).not.toHaveBeenCalled();
    });

    it("accepts Command+Enter and ignores disabled or composing input", () => {
        const scope = mountScope('<input><button data-default-submit type="button" disabled>Salvar</button>');
        const input = scope.querySelector("input")!;
        const save = scope.querySelector<HTMLButtonElement>("button")!;
        const click = vi.fn();
        save.addEventListener("click", click);
        shortcut(input, { metaKey: true, ctrlKey: false });
        expect(click).not.toHaveBeenCalled();
        save.disabled = false;
        shortcut(input, { isComposing: true });
        expect(click).not.toHaveBeenCalled();
    });

    it("does not steal the shortcut from an explicitly ignored editor", () => {
        const scope = mountScope('<div data-default-form-shortcut-ignore><input></div><button data-default-submit type="button">Salvar</button>');
        const click = vi.fn();
        scope.querySelector("button")!.addEventListener("click", click);
        shortcut(scope.querySelector("input")!);
        expect(click).not.toHaveBeenCalled();
    });
});
