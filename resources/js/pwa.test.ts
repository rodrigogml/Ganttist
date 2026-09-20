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
        expect(manifest.icons).toEqual([
            { src: "/brand/icon-192.png", sizes: "192x192", type: "image/png", purpose: "any" },
            { src: "/brand/icon-512.png", sizes: "512x512", type: "image/png", purpose: "any" },
        ]);
        for (const icon of manifest.icons) {
            expect(icon.type).toBe("image/png");
            expect(existsSync(resolve(publicPath, icon.src.slice(1)))).toBe(true);
        }
    });

    it("never transparently caches authenticated API responses", () => {
        const worker = readFileSync(resolve(publicPath, "sw.js"), "utf8");

        expect(worker).not.toContain("cache.put('/api/")
        expect(worker).toContain("'/revisions/'")
        expect(worker).toContain("'/offline.html'")
    });
});
