import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const root = path.resolve(import.meta.dirname, "..");
const srcDir = process.env.ORIGINALS || "/tmp/sala-orig";
const outDir = path.join(root, "public/media");

const heroWidths = [640, 1280, 1920];
const galleryWidths = [640, 1280, 1600];

/** @type {{id:string,file:string,alt:string,cat?:string,role?:string}[]} */
const photos = [
  { id: "hero", file: "sala-zilina-titulka.jpg", alt: "Sála Kinekus v Žiline pripravená na slávnostnú akciu", role: "hero" },
  { id: "konferencia", file: "konferencie.jpg", alt: "Konferenčné usporiadanie sály Kinekus", role: "b2b" },
  { id: "svadba-hero", file: "svadobna-sala.jpg", alt: "Svadobné prestieranie v sále Kinekus", role: "b2c" },
  { id: "stuzkova-hero", file: "stuzkova.jpg", alt: "Sála Kinekus vyzdobená na stužkovú", role: "stuzkova" },
  { id: "sala-siroko", file: "sala.jpg", alt: "Pohľad na sálu a pódium", role: "wide" },

  { id: "svadba-01", file: "MG_0021f.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-02", file: "MG_0019f.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-03", file: "MG_0049f.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-04", file: "DSC_0378.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-05", file: "DSC_0384.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-06", file: "DSC_0387.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-07", file: "DSC_0399.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-08", file: "DSC_0404.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-09", file: "DSC_0415.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-10", file: "DSC_0425-2.jpg", alt: "Fotografia zo svadby v sále Kinekus", cat: "svadby" },
  { id: "svadba-11", file: "Svadobná-výzdoba-1.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-12", file: "Svadobná-výzdoba-2.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-13", file: "Svadobná-výzdoba-3.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-14", file: "Svadobná-výzdoba-4.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-15", file: "Svadobná-výzdoba-5.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-16", file: "Svadobná-výzdoba-6.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-17", file: "Svadobná-výzdoba-7.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-18", file: "Svadobná-výzdoba-8.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-19", file: "IMG_20190429_141547e.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-20", file: "IMG_20190506_145809e.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-21", file: "IMG_20190506_152830e.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-22", file: "web-hlavny-stol-tylova-sukna.jpg", alt: "Hlavný svadobný stôl v sále Kinekus", cat: "svadby" },
  { id: "svadba-23", file: "web-smotanovo-ruzova-vyzdoba.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-24", file: "web-smotanovo-ruzova-vyzdoba2.jpg", alt: "Ukážka svadobnej výzdoby v sále Kinekus", cat: "svadby" },
  { id: "svadba-25", file: "web-vyzdoba-okruhlych-stolov-smotanova.jpg", alt: "Výzdoba okrúhlych stolov v sále Kinekus", cat: "svadby" },
  { id: "svadba-26", file: "web-vyzdoba-okruhlych-stolov-smotanova2.jpg", alt: "Výzdoba okrúhlych stolov v sále Kinekus", cat: "svadby" },
  { id: "svadba-27", file: "web-vyzdoba-okruhlych-stolov-smotanova3.jpg", alt: "Výzdoba okrúhlych stolov v sále Kinekus", cat: "svadby" },

  { id: "konf-01", file: "IMG_20190514_141310367.jpg", alt: "Fotografia z konferencie alebo školenia v sále Kinekus", cat: "konferencie" },
  { id: "konf-02", file: "IMG_20190514_141410353.jpg", alt: "Fotografia z konferencie alebo školenia v sále Kinekus", cat: "konferencie" },
  { id: "konf-03", file: "IMG_20190515_083544696.jpg", alt: "Fotografia z konferencie alebo školenia v sále Kinekus", cat: "konferencie" },
  { id: "konf-04", file: "IMG_20190515_083549474.jpg", alt: "Fotografia z konferencie alebo školenia v sále Kinekus", cat: "konferencie" },
  { id: "konf-05", file: "IMG_20190515_083601436.jpg", alt: "Fotografia z konferencie alebo školenia v sále Kinekus", cat: "konferencie" },

  { id: "party-01", file: "003__kinekus-zfp.jpg", alt: "Fotografia z vianočného večierka v sále Kinekus", cat: "vecierok" },
  { id: "party-02", file: "004__kinekus-zfp.jpg", alt: "Fotografia z vianočného večierka v sále Kinekus", cat: "vecierok" },
  { id: "party-03", file: "005__kinekus-zfp.jpg", alt: "Fotografia z vianočného večierka v sále Kinekus", cat: "vecierok" },
  { id: "party-04", file: "001__kinekus-zfp.jpg", alt: "Fotografia z vianočného večierka v sále Kinekus", cat: "vecierok" },
  { id: "party-05", file: "002__kinekus-zfp.jpg", alt: "Fotografia z vianočného večierka v sále Kinekus", cat: "vecierok" },

  { id: "stuz-01", file: "Stuzkova-1.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-02", file: "Stuzkova-2.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-03", file: "Stuzkova-3.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-04", file: "Stuzkova-4.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-05", file: "Stuzkova-5.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-06", file: "Stuzkova-6.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-07", file: "Stuzkova-7.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-08", file: "Stuzkova-8.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-09", file: "Stuzkova-9.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-10", file: "Stuzkova-10.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },
  { id: "stuz-11", file: "Stuzkova-11.jpg", alt: "Fotografia zo stužkovej v sále Kinekus", cat: "stuzkova" },

  { id: "open-01", file: "Kinekus-2.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-02", file: "Kinekus-11.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-03", file: "Kinekus-24.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-04", file: "Kinekus-44.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-05", file: "DSC_0038.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-06", file: "DSC_0039.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-07", file: "DSC_0058.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-08", file: "DSC_0059.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
  { id: "open-09", file: "DSC_0052.jpg", alt: "Fotografia zo slávnostného otvorenia sály", cat: "otvorenie" },
];

const logos = [
  ["inovia.jpg", "Inovia"],
  ["metro.jpg", "metro"],
  ["ipa.jpg", "Ipa"],
  ["nebbia.jpg", "Nebbia"],
  ["plus.jpg", "Profi Plus"],
  ["sevak.jpg", "Sevak"],
  ["reneseco.jpg", "Renesco"],
  ["rbr-beton.jpg", "RBR betón"],
  ["globesy.jpg", "Globesy"],
  ["simplea.jpg", "simplea"],
  ["duroska.jpg", "Pekáreň Ďuroška"],
];

async function renderSet(file, id, widths) {
  const input = path.join(srcDir, file);
  const base = sharp(input, { failOn: "none", unlimited: true }).rotate();
  const meta = await base.metadata();
  const maxW = meta.width || widths[widths.length - 1];
  const use = [...new Set(widths.filter((w) => w < maxW).concat([Math.min(maxW, Math.max(...widths))]))].sort((a, b) => a - b);
  const webp = [];
  const avif = [];
  let width = 0;
  let height = 0;
  for (const w of use) {
    const buf = await sharp(input, { failOn: "none", unlimited: true })
      .rotate()
      .resize({ width: w, withoutEnlargement: true })
      .toBuffer();
    const info = await sharp(buf).metadata();
    const webpName = `${id}-${info.width}.webp`;
    const avifName = `${id}-${info.width}.avif`;
    await sharp(buf).webp({ quality: 74 }).toFile(path.join(outDir, webpName));
    await sharp(buf).avif({ quality: 48, effort: 4 }).toFile(path.join(outDir, avifName));
    webp.push({ w: info.width, src: `/media/${webpName}` });
    avif.push({ w: info.width, src: `/media/${avifName}` });
    width = info.width || width;
    height = info.height || height;
  }
  return { width, height, webp, avif };
}

await mkdir(outDir, { recursive: true });
await mkdir(path.join(outDir, "logos"), { recursive: true });

const manifest = { hero: null, b2b: null, b2c: null, stuzkova: null, wide: null, gallery: [], logos: [] };

for (const photo of photos) {
  const widths = photo.role === "hero" || photo.role === "wide" ? heroWidths : galleryWidths;
  process.stdout.write(`${photo.id}… `);
  const set = await renderSet(photo.file, photo.id, widths);
  const item = { id: photo.id, alt: photo.alt, ...set };
  if (photo.cat) {
    manifest.gallery.push({ ...item, cat: photo.cat });
  }
  if (photo.role === "hero") manifest.hero = item;
  if (photo.role === "b2b") manifest.b2b = item;
  if (photo.role === "b2c") manifest.b2c = item;
  if (photo.role === "stuzkova") manifest.stuzkova = item;
  if (photo.role === "wide") manifest.wide = item;
  if (photo.cat === "otvorenie" && photo.id === "open-03") manifest.opening = item;
  if (photo.cat === "vecierok" && photo.id === "party-04") manifest.party = item;
  process.stdout.write("ok\n");
}

for (const [file, name] of logos) {
  const id = path.basename(file, path.extname(file));
  const buf = await sharp(path.join(srcDir, file), { failOn: "none" })
    .resize({ width: 320, withoutEnlargement: true })
    .webp({ quality: 80 })
    .toBuffer();
  const info = await sharp(buf).metadata();
  const dest = `logos/${id}.webp`;
  await sharp(buf).toFile(path.join(outDir, dest));
  manifest.logos.push({ name, src: `/media/${dest}`, width: info.width, height: info.height });
  process.stdout.write(`logo ${id}\n`);
}

const fav = await sharp(path.join(srcDir, "Favicon-05.jpg"), { failOn: "none" })
  .resize(192, 192, { fit: "cover" })
  .png()
  .toBuffer();
await sharp(fav).resize(32, 32).png().toFile(path.join(root, "public/favicon-32.png"));
await sharp(fav).resize(180, 180).png().toFile(path.join(root, "public/apple-touch-icon.png"));
await sharp(fav).resize(192, 192).png().toFile(path.join(root, "public/favicon-192.png"));

const heroBuf = await sharp(path.join(srcDir, "sala-zilina-titulka.jpg"), { failOn: "none" })
  .rotate()
  .resize(1200, 630, { fit: "cover", position: "attention" })
  .jpeg({ quality: 78, mozjpeg: true })
  .toBuffer();
await sharp(heroBuf).toFile(path.join(root, "public/og.jpg"));

await mkdir(path.join(root, "src/data"), { recursive: true });
await writeFile(path.join(root, "src/data/media.json"), JSON.stringify(manifest));
console.log("manifest photos", manifest.gallery.length, "logos", manifest.logos.length);
