import { defineConfig } from "astro/config";
import sitemap from "@astrojs/sitemap";

const site = process.env.SITE_URL || "https://www.salazilina.sk";
const rawBase = process.env.SITE_BASE || "/";
const base = rawBase === "/" ? "/" : `/${rawBase.replace(/^\/+|\/+$/g, "")}/`;

export default defineConfig({
  site,
  base,
  trailingSlash: "always",
  outDir: process.env.OUT_DIR || "./dist",
  integrations: [
    sitemap({
      filter: (page) => !page.includes("llms.txt"),
    }),
  ],
  build: {
    format: "directory",
  },
});
