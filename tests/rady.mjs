import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import yaml from "yaml";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const contentDir = path.join(root, "content/rady");
const dist = path.join(root, "dist");
const media = JSON.parse(fs.readFileSync(path.join(root, "src/data/media.json"), "utf8"));

let failed = 0;
function check(cond, message) {
  if (cond) {
    console.log(`ok  ${message}`);
    return;
  }
  failed++;
  console.log(`FAIL ${message}`);
}

function words(text) {
  const plain = String(text)
    .replace(/```[\s\S]*?```/g, " ")
    .replace(/!\[[^\]]*\]\([^)]*\)/g, " ")
    .replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
    .replace(/https?:\/\/\S+/g, " ")
    .replace(/^#{1,6}\s+/gm, " ")
    .replace(/[*_`>|]/g, " ");
  return plain.split(/\s+/).filter((word) => /[\p{L}\p{N}]/u.test(word)).length;
}

function decode(value) {
  return value
    .replace(/&nbsp;/g, " ")
    .replace(/&#160;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&#x27;/g, "'");
}

function textOf(html) {
  return decode(html.replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();
}

function jsonLd(html) {
  return [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map((match) => {
    const raw = decode(match[1]);
    return JSON.parse(raw);
  });
}

const namedIds = ["hero", "b2b", "b2c", "stuzkova", "wide", "party", "opening", "tour"]
  .map((key) => media[key]?.id)
  .filter(Boolean);
const imageIds = new Set([...(media.gallery ?? []).map((item) => item.id), ...namedIds]);

const files = fs.readdirSync(contentDir).filter((name) => name.endsWith(".md")).sort();
check(files.length === 7, `v content/rady je 7 článkov (${files.length})`);

const articles = files.map((file) => {
  const raw = fs.readFileSync(path.join(contentDir, file), "utf8");
  const match = raw.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  check(Boolean(match), `${file} má frontmatter`);
  const data = yaml.parse(match[1]);
  const id = file.replace(/\.md$/, "");
  return { id, file, data, body: match[2], raw };
});

const ids = new Set(articles.map((article) => article.id));
const serviceHrefs = ["/svadby/", "/stuzkova/", "/konferencie/", "/catering/"];

for (const article of articles) {
  const { data, body, file, id, raw } = article;
  const count = words([body, ...data.summary, ...data.faq.flatMap((item) => [item.q, item.a])].join("\n"));
  check(count >= 700 && count <= 1100, `${id} má 700–1100 slov (${count})`);
  check(data.seoTitle.length <= 60, `${id} seoTitle ≤ 60 (${data.seoTitle.length})`);
  check(data.description.length >= 40 && data.description.length <= 155, `${id} description 40–155 (${data.description.length})`);
  check(data.title.length >= 8, `${id} má title`);
  check(!/^# /m.test(body), `${id} nemá vlastné H1 v Markdowne`);
  check((body.match(/^## /gm) ?? []).length >= 3, `${id} má aspoň tri otázky H2`);
  check(data.summary.length >= 2 && data.summary.length <= 4, `${id} má blok V skratke`);
  check(data.faq.length >= 3 && data.faq.length <= 5, `${id} má 3–5 FAQ`);
  check(data.author === "Tím Sály Kinekus", `${id} má autora Tím Sály Kinekus`);
  check(/^\d{4}-\d{2}-\d{2}$/.test(data.published) && /^\d{4}-\d{2}-\d{2}$/.test(data.updated), `${id} má dátumy`);
  check(imageIds.has(data.image), `${id} používa fotku ${data.image}`);
  check(data.related.length >= 2 && data.related.every((slug) => ids.has(slug) && slug !== id), `${id} má platné súvisiace rady`);
  check(!raw.includes("€") && !raw.includes("%"), `${id} nemá cenu ani percentá`);
  check(!/ozveme sa do|pracovného dňa/i.test(raw), `${id} nesľubuje lehotu odpovede`);
  if (id === "stuzkova-krok-za-krokom") {
    check(!/pravidl|poškod|dohľad|férov/i.test(raw), "stužková rada nepoužíva zakázané slová");
  }
}

check(serviceHrefs.every((href) => articles.some((article) => article.body.includes(`](${href})`))), "články spolu odkazujú na svadby, stužkovú, firmy a catering");

const featured = articles.filter((article) => article.data.featured).sort((a, b) => a.data.order - b.data.order);
check(featured.length >= 3, "aspoň tri rady sú označené na úvod");

if (!fs.existsSync(path.join(dist, "rady/index.html"))) {
  console.log("preskočené HTML kontroly (najprv npm run build)");
  process.exit(failed ? 1 : 0);
}

const sitemap = fs.readdirSync(dist)
  .filter((name) => name.startsWith("sitemap") && name.endsWith(".xml"))
  .map((name) => fs.readFileSync(path.join(dist, name), "utf8"))
  .join("\n");

function pageFile(href) {
  const clean = href.split("#")[0].split("?")[0];
  if (clean === "" || clean === "/") return path.join(dist, "index.html");
  const rel = clean.replace(/^\//, "").replace(/\/$/, "");
  const asIndex = path.join(dist, rel, "index.html");
  const asFile = path.join(dist, rel);
  if (fs.existsSync(asIndex)) return asIndex;
  if (fs.existsSync(asFile) && fs.statSync(asFile).isFile()) return asFile;
  return null;
}

const home = fs.readFileSync(path.join(dist, "index.html"), "utf8");
const homeLd = jsonLd(home);
const venue = homeLd.find((block) => {
  const type = block["@type"];
  const types = Array.isArray(type) ? type : [type];
  return types.includes("EventVenue") || types.includes("LocalBusiness");
});
check(Boolean(venue), "úvod má JSON-LD EventVenue/LocalBusiness");
check(venue && JSON.stringify(venue).includes("Rosinská cesta 13"), "JSON-LD má adresu");
check(venue && String(venue.telephone ?? "").includes("908761253"), "JSON-LD má telefón");
check(venue && venue.email === "marketing@salazilina.sk", "JSON-LD má e-mail");
check(venue && venue.url === "https://www.salazilina.sk", "JSON-LD má url");
check(!home.includes("aggregateRating"), "úvod nemá aggregateRating");
check(!JSON.stringify(homeLd).includes("priceRange"), "úvodné JSON-LD nemá cenník");
for (const article of featured.slice(0, 3)) {
  check(home.includes(article.data.title), `úvod ukazuje radu ${article.id}`);
}
check(home.includes('href="/rady/"'), "úvod odkazuje na /rady/");
check(home.includes(">Rady<"), "navigácia obsahuje Rady");

const indexHtml = fs.readFileSync(path.join(dist, "rady/index.html"), "utf8");
const indexLd = jsonLd(indexHtml);
check(indexLd.some((block) => block["@type"] === "CollectionPage"), "/rady/ má CollectionPage");
check(indexLd.some((block) => block.mainEntity?.["@type"] === "ItemList"), "/rady/ má ItemList");
check(indexLd.some((block) => block["@type"] === "BreadcrumbList"), "/rady/ má BreadcrumbList");
check((indexHtml.match(/<h1[\s>]/g) ?? []).length === 1, "/rady/ má jeden H1");
check(sitemap.includes("/rady/"), "sitemap obsahuje /rady/");

const llmsPath = fs.existsSync(path.join(dist, "llms.txt"))
  ? path.join(dist, "llms.txt")
  : path.join(dist, "llms.txt/index.html");
check(fs.existsSync(llmsPath), "existuje llms.txt");
const llms = fs.existsSync(llmsPath) ? fs.readFileSync(llmsPath, "utf8") : "";
check(llms.includes("Sála Kinekus") && llms.includes("/rady/"), "llms.txt opisuje web a rady");
check(!sitemap.includes("llms.txt"), "sitemap neobsahuje llms.txt");

for (const article of articles) {
  const htmlPath = path.join(dist, "rady", article.id, "index.html");
  check(fs.existsSync(htmlPath), `HTML ${article.id}`);
  if (!fs.existsSync(htmlPath)) continue;
  const html = fs.readFileSync(htmlPath, "utf8");
  const blocks = jsonLd(html);
  const h1 = [...html.matchAll(/<h1>([\s\S]*?)<\/h1>/g)];
  check(h1.length === 1 && textOf(h1[0][1]) === article.data.title, `${article.id} má jeden H1 s názvom`);
  check(html.includes("V skratke"), `${article.id} má V skratke`);
  check(html.includes("Tím Sály Kinekus"), `${article.id} ukazuje autora`);
  check(html.includes("25. 10. 2026") || html.includes(article.data.published.split("-").reverse().map((part) => String(Number(part))).join(". ").replace(/(\d+)\. (\d+)\. (\d+)/, (_, d, m, y) => `${Number(d)}. ${Number(m)}. ${y}`)), `${article.id} ukazuje dátum`);
  const minutes = html.match(/(\d+) min čítania/);
  check(Boolean(minutes) && Number(minutes[1]) >= 4, `${article.id} má čas čítania`);
  check(html.includes('href="/#termin"') && html.includes("Overiť voľný termín"), `${article.id} má výzvu na termín`);
  check(html.includes('rel="canonical"'), `${article.id} má canonical`);
  check(html.includes('property="og:title"') && html.includes('property="og:description"') && html.includes('property="og:image"'), `${article.id} má Open Graph`);
  check(html.includes('property="og:type" content="article"'), `${article.id} má og:type article`);
  const title = textOf((html.match(/<title>([\s\S]*?)<\/title>/) ?? ["", ""])[1]);
  const description = (html.match(/<meta name="description" content="([^"]*)"/) ?? ["", ""])[1];
  check(title.length > 0 && title.length <= 60, `${article.id} title ≤ 60 (${title.length})`);
  check(decode(description).length <= 155, `${article.id} meta description ≤ 155`);
  const articleLd = blocks.find((block) => block["@type"] === "Article");
  const faqLd = blocks.find((block) => block["@type"] === "FAQPage");
  const crumbs = blocks.find((block) => block["@type"] === "BreadcrumbList");
  check(Boolean(articleLd && faqLd && crumbs), `${article.id} má Article, FAQPage a BreadcrumbList`);
  if (articleLd) {
    check(articleLd.headline === article.data.title, `${article.id} Article.headline`);
    check(articleLd.description === article.data.description, `${article.id} Article.description`);
    check(articleLd.datePublished === article.data.published && articleLd.dateModified === article.data.updated, `${article.id} Article dátumy`);
    check(articleLd.author?.name === "Sála Kinekus" && articleLd.publisher?.name === "Sála Kinekus", `${article.id} Article autor a vydavateľ`);
    check(typeof articleLd.mainEntityOfPage === "string" && articleLd.mainEntityOfPage.endsWith(`/rady/${article.id}/`), `${article.id} Article mainEntityOfPage`);
    check(typeof articleLd.image === "string" && articleLd.image.startsWith("https://"), `${article.id} Article image`);
  }
  const faqHtml = (html.split('class="article-faq"')[1] ?? "").split('class="article-cta"')[0];
  const pairs = [...faqHtml.matchAll(/<h3>([\s\S]*?)<\/h3>\s*<p>([\s\S]*?)<\/p>/g)].map((match) => ({
    q: textOf(match[1]),
    a: textOf(match[2]),
  }));
  const ldPairs = (faqLd?.mainEntity ?? []).map((item) => ({
    q: item.name,
    a: item.acceptedAnswer?.text,
  }));
  check(pairs.length === article.data.faq.length && pairs.length === ldPairs.length, `${article.id} FAQ počet sedí`);
  check(JSON.stringify(pairs) === JSON.stringify(ldPairs) && JSON.stringify(pairs) === JSON.stringify(article.data.faq.map((item) => ({ q: item.q, a: item.a }))), `${article.id} FAQ JSON-LD sedí s textom`);
  check(!html.includes("aggregateRating"), `${article.id} nemá aggregateRating`);
  check(sitemap.includes(`/rady/${article.id}/`), `${article.id} je v sitemape`);
  check(llms.includes(`/rady/${article.id}/`), `${article.id} je v llms.txt`);
  const hrefs = [...html.matchAll(/href="([^"]+)"/g)].map((match) => match[1]);
  for (const href of hrefs) {
    if (!href.startsWith("/") || href.startsWith("//")) continue;
    const fileOnDisk = pageFile(href);
    check(Boolean(fileOnDisk), `${article.id} odkaz ${href} existuje`);
  }
  for (const slug of article.data.related) {
    check(html.includes(`/rady/${slug}/`), `${article.id} odkazuje na súvisiacu radu ${slug}`);
  }
}

const robots = fs.readFileSync(path.join(root, "public/robots.txt"), "utf8");
check(robots.includes("Disallow: /api/") && !/Disallow:\s*\/\s*$/m.test(robots), "robots.txt ostáva s Disallow len pre /api/");
const oslavy = fs.readFileSync(path.join(root, "public/oslavy/index.html"), "utf8");
check(oslavy.includes('name="robots" content="noindex"'), "presmerovanie /oslavy/ ostáva noindex");

function walk(dir, acc = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, acc);
    else if (/\.(html|txt)$/.test(entry.name)) acc.push(full);
  }
  return acc;
}
const banned = ["bezbariér", "nelimituje", "wa.me", "Mená dodávateľov", "zámerne nie sú", "Vlastnú kuchyňu v sále nemáme", "overené cateringové", "Cenu ovplyvňuje"];
const built = walk(dist).map((file) => ({ file, text: fs.readFileSync(file, "utf8") }));
for (const phrase of banned) {
  const hits = built.filter((item) => item.text.includes(phrase)).map((item) => path.relative(dist, item.file));
  check(hits.length === 0, `výstup neobsahuje „${phrase}“${hits.length ? ` (${hits.join(", ")})` : ""}`);
}
const notFound = fs.readFileSync(path.join(dist, "404.html"), "utf8");
check(notFound.includes('name="robots" content="noindex"'), "404 je noindex");
check(!notFound.includes('rel="canonical"'), "404 nemá canonical");
const htaccess = fs.readFileSync(path.join(dist, ".htaccess"), "utf8");
check(htaccess.includes("ErrorDocument 404 /404.html"), ".htaccess má ErrorDocument 404");
check(htaccess.includes('X-Content-Type-Options "nosniff"'), ".htaccess má nosniff");
check(htaccess.includes('Referrer-Policy "strict-origin-when-cross-origin"'), ".htaccess má Referrer-Policy");
check(htaccess.includes('X-Frame-Options "SAMEORIGIN"'), ".htaccess má X-Frame-Options");
check(!/Content-Security-Policy/i.test(htaccess), ".htaccess nemá CSP");
function headingsOk(html) {
  const levels = [...html.matchAll(/<h([1-6])\b/g)].map((match) => Number(match[1]));
  if (levels[0] !== 1) return false;
  return levels.every((level, index) => index === 0 || level <= levels[index - 1] + 1);
}
for (const href of ["/galeria/", "/svadby/", "/konferencie/"]) {
  const file = pageFile(href);
  const html = file ? fs.readFileSync(file, "utf8") : "";
  check(Boolean(file) && headingsOk(html), `${href} má H1 a nadpisy bez preskoku`);
}
check(home.includes("väčšiu kapacitu ľudí.") && !home.includes("Veľká vďaka patrí p."), "citát Ďuricovej končí celou vetou");
check(home.includes('id="field-message"') && home.includes('aria-describedby="error-message"') && home.includes('aria-describedby="error-form"'), "formulár má prepojené chybové hlásenia");

if (failed) process.exit(1);
