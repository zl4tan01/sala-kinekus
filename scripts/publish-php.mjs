import { cpSync, mkdirSync, rmSync } from "node:fs";
import path from "node:path";

const root = path.resolve(import.meta.dirname, "..");
const target = path.join(root, "dist/api");
rmSync(target, { recursive: true, force: true });
mkdirSync(target, { recursive: true });
cpSync(path.join(root, "api"), target, {
  recursive: true,
  filter: (source) => !source.endsWith(`${path.sep}config.php`) && !source.endsWith(".json"),
});
console.log("PHP endpoint je v dist/api");
