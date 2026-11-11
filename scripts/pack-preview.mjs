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

const publish = spawnSync("node", ["scripts/publish-php.mjs"], {
  cwd: root,
  env: { ...process.env, OUT_DIR: "dist-preview" },
  stdio: "inherit",
});
if (publish.status !== 0) process.exit(publish.status ?? 1);

function rewrite(text) {
  return text
    .replace(/(href|src|action)=("|')\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/)/g, `$1=$2${base}`)
    .replace(/url\((["']?)\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/)/g, `url($1${base}`)
    .replace(/url=\/(?!\/|9456E0ulzhlbeu2YNw7AJhI7\/)/g, `url=${base}`);
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

const zip = "/opt/cursor/artifacts/sala-kinekus-9456E0ulzhlbeu2YNw7AJhI7.zip";
spawnSync("zip", ["-r", "-q", zip, slug], { cwd: stage, stdio: "inherit" });
console.log(zip);
