import { markInputRule } from "@tiptap/core";
import Underline from "@tiptap/extension-underline";

/** Converts ++texto++ into the project's Markdown underline extension. */
export const UnderlineWithMarkdownInput = Underline.extend({
    addInputRules() {
        return [
            markInputRule({
                find: /\+\+([^+\n]+)\+\+$/,
                type: this.type,
            }),
        ];
    },
});
