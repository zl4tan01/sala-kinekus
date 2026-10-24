import { defineConfig } from "astro/config";
import sitemap from "@astrojs/sitemap";

export default defineConfig({
  site: "https://www.salazilina.sk",
  trailingSlash: "always",
  integrations: [sitemap()],
  build: {
    format: "directory",
  },
});
