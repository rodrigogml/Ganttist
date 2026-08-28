// @vitest-environment jsdom
import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import MarkdownContent from "./MarkdownContent.vue";

describe("MarkdownContent", () => {
    it("renders Markdown and removes unsafe HTML", () => {
        const wrapper = mount(MarkdownContent, {
            props: { content: "# Título\n\n**Importante**\n\n<script>alert('xss')</script>" },
        });

        expect(wrapper.find("h1").text()).toBe("Título");
        expect(wrapper.find("strong").text()).toBe("Importante");
        expect(wrapper.html()).not.toContain("<script");
    });
});
