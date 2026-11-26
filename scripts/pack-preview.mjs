import { cpSync, mkdirSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";

const root = path.resolve(import.meta.dirname, "..");
const slug = "9456E0ulzhlbeu2YNw7AJhI7";
const base = `/${slug}/`;
const out = path.join(root, "dist-preview");

const build = spawnSync("npx", ["astro", "build"], {
  cwd: root,
  env: {
    ...process.env,
    SITE_BASE: base,
    SITE_URL: "https://sala.kelvio.sk",
    SITE_NOINDEX: "1",
    OUT_DIR: "dist-preview",
  },
  stdio: "inherit",
});
if (build.status !== 0) process.exit(build.status ?? 1);

rmSync(path.join(out, "api"), { recursive: true, force: true });
rmSync(path.join(out, "content"), { recursive: true, force: true });

function rewrite(text) {
  return text
    .replace(/(href|src|action)=("|')\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/|api\/)/g, `$1=$2${base}`)
    .replace(/url\((["']?)\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/|api\/)/g, `url($1${base}`)
    .replace(/url=\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/|api\/)/g, `url=${base}`);
}

function walk(dir) {
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) {
      walk(full);
      continue;
    }
    if (!/\.(html|css|js|xml|txt)$/.test(name)) continue;
    const next = rewrite(readFileSync(full, "utf8"));
    writeFileSync(full, next);
  }
}
walk(out);

const oslavy = path.join(out, "oslavy/index.html");
writeFileSync(
  oslavy,
  readFileSync(oslavy, "utf8").replace(
    'href="https://www.salazilina.sk/"',
    `href="https://sala.kelvio.sk${base}"`,
  ),
);

const htaccess = path.join(out, ".htaccess");
let rules = readFileSync(htaccess, "utf8");
rules = rules.replace("ErrorDocument 404 /404.html", `ErrorDocument 404 ${base}404.html`);
rules = rules.replace("RewriteRule ^oslavy/?$ / [R=301,L]", `RewriteRule ^oslavy/?$ ${base} [R=301,L]`);
if (!rules.includes("X-Robots-Tag")) {
  rules = rules.replace(
    'Header always set X-Frame-Options "SAMEORIGIN"',
    'Header always set X-Frame-Options "SAMEORIGIN"\n    Header always set X-Robots-Tag "noindex"',
  );
}
writeFileSync(htaccess, rules);

const stage = path.join(root, ".preview-pack");
rmSync(stage, { recursive: true, force: true });
mkdirSync(path.join(stage, slug), { recursive: true });
cpSync(out, path.join(stage, slug), { recursive: true });

const zip = process.env.PREVIEW_ZIP || "/opt/cursor/artifacts/sala-preview.zip";
rmSync(zip, { force: true });
spawnSync("zip", ["-r", "-q", zip, slug], { cwd: stage, stdio: "inherit" });
console.log(zip);

if (process.env.PACK_ROOT === "0") process.exit(0);

const kelvioDir = path.join(root, "dist-kelvio");
const kelvio = spawnSync("npx", ["astro", "build"], {
  cwd: root,
  env: {
    ...process.env,
    SITE_BASE: "/",
    SITE_URL: "https://sala.kelvio.sk",
    SITE_NOINDEX: "1",
    OUT_DIR: "dist-kelvio",
  },
  stdio: "inherit",
});
if (kelvio.status !== 0) process.exit(kelvio.status ?? 1);
const kelvioPublish = spawnSync("node", ["scripts/publish-php.mjs"], {
  cwd: root,
  env: { ...process.env, OUT_DIR: "dist-kelvio" },
  stdio: "inherit",
});
if (kelvioPublish.status !== 0) process.exit(kelvioPublish.status ?? 1);

const kelvioAccess = path.join(kelvioDir, ".htaccess");
let kelvioRules = readFileSync(kelvioAccess, "utf8");
if (!kelvioRules.includes("X-Robots-Tag")) {
  kelvioRules = kelvioRules.replace(
    'Header always set X-Frame-Options "SAMEORIGIN"',
    'Header always set X-Frame-Options "SAMEORIGIN"\n    Header always set X-Robots-Tag "noindex"',
  );
}
writeFileSync(kelvioAccess, kelvioRules);
const kelvioOslavy = path.join(kelvioDir, "oslavy/index.html");
writeFileSync(
  kelvioOslavy,
  readFileSync(kelvioOslavy, "utf8").replace(
    'href="https://www.salazilina.sk/"',
    'href="https://sala.kelvio.sk/"',
  ),
);

const rootZip = "/opt/cursor/artifacts/sala-root.zip";
rmSync(rootZip, { force: true });
spawnSync("zip", ["-r", "-q", rootZip, "."], { cwd: kelvioDir, stdio: "inherit" });
console.log(rootZip);
