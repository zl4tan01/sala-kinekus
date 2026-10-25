import { cpSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";

const root = path.resolve(import.meta.dirname, "..");
const target = path.join(root, "dist/api");
rmSync(target, { recursive: true, force: true });
mkdirSync(target, { recursive: true });
cpSync(path.join(root, "api"), target, {
  recursive: true,
  filter: (source) => !source.endsWith(`${path.sep}config.php`) && !source.endsWith(".json"),
});
const contentDir = path.join(root, "dist/content");
mkdirSync(contentDir, { recursive: true });
cpSync(path.join(root, "content/google-reviews.json"), path.join(contentDir, "google-reviews.json"));
cpSync(path.join(root, "content/pricing.json"), path.join(contentDir, "pricing.json"));
writeFileSync(path.join(contentDir, ".htaccess"), "Require all denied\n");
console.log("PHP endpoint je v dist/api");
