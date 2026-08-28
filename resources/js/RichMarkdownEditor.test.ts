// @vitest-environment jsdom
import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import RichMarkdownEditor from "./RichMarkdownEditor.vue";

describe("RichMarkdownEditor", () => {
    it("starts empty content as normal paragraph text", async () => {
        const wrapper = mount(RichMarkdownEditor, {
            attachTo: document.body,
            props: { modelValue: "", ariaLabel: "Descrição" },
        });

        await new Promise((resolve) => setTimeout(resolve));
        expect(wrapper.find(".rich-markdown-content > p").exists()).toBe(true);
        expect(wrapper.find(".rich-markdown-content > h1").exists()).toBe(false);
        expect(wrapper.get('[aria-label="Texto normal"]').attributes("aria-pressed")).toBe("true");
        wrapper.unmount();
    });

    it("interprets persisted Markdown as formatted editor content", async () => {
        const wrapper = mount(RichMarkdownEditor, {
            attachTo: document.body,
            props: {
                modelValue: "## Planejamento\n\n**Importante** e ++sublinhado++",
                ariaLabel: "Descrição",
            },
        });

        await new Promise((resolve) => setTimeout(resolve));
        expect(wrapper.find("h2").text()).toBe("Planejamento");
        expect(wrapper.find("strong").text()).toBe("Importante");
        expect(wrapper.find(".rich-markdown-content u").text()).toBe("sublinhado");
        wrapper.unmount();
    });
});
