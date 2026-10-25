import { defineConfig } from "astro/config";
import sitemap from "@astrojs/sitemap";

export default defineConfig({
  site: "https://www.salazilina.sk",
  trailingSlash: "always",
  integrations: [
    sitemap({
      filter: (page) => !page.includes("llms.txt"),
    }),
  ],
  build: {
    format: "directory",
  },
});
