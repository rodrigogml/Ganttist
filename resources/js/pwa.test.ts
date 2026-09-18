import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const publicPath = resolve(process.cwd(), "public");

describe("PWA installation assets", () => {
    it("declares valid install icons and a standalone manifest", () => {
        const manifest = JSON.parse(readFileSync(resolve(publicPath, "manifest.webmanifest"), "utf8"));

        expect(manifest).toMatchObject({
            name: "Ganttist — Clareza para entregar",
            start_url: "/",
            display: "standalone",
            theme_color: "#0b1020",
        });
        expect(manifest.icons).toHaveLength(3);
        for (const icon of manifest.icons) {
            expect(icon.type).toBe("image/png");
            expect(existsSync(resolve(publicPath, icon.src.slice(1)))).toBe(true);
        }
    });

    it("never caches authenticated API responses", () => {
        const worker = readFileSync(resolve(publicPath, "sw.js"), "utf8");

        expect(worker).toContain("url.pathname.startsWith('/api/')");
        expect(worker).toContain("OFFLINE_URL");
    });
});
