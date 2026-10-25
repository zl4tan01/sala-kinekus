import { getCollection, type CollectionEntry } from "astro:content";
import media from "../data/media.json";

export type AdviceEntry = CollectionEntry<"rady">;

type PictureSource = { w: number; src: string };
export type AdviceImage = {
  id: string;
  alt: string;
  width: number;
  height: number;
  webp: PictureSource[];
  avif: PictureSource[];
};

const named = [
  media.hero,
  media.b2b,
  media.b2c,
  media.stuzkova,
  media.wide,
  media.party,
  media.opening,
  media.tour,
].filter(Boolean) as AdviceImage[];

export async function allAdvice(): Promise<AdviceEntry[]> {
  const items = await getCollection("rady");
  return items.sort((a, b) => a.data.order - b.data.order || a.data.title.localeCompare(b.data.title, "sk"));
}

export function featuredAdvice(items: AdviceEntry[], count = 3): AdviceEntry[] {
  const featured = items.filter((item) => item.data.featured);
  const rest = items.filter((item) => !item.data.featured);
  return [...featured, ...rest].slice(0, count);
}

export function adviceHref(id: string): string {
  return `/rady/${id}/`;
}

export function findMedia(id: string): AdviceImage {
  const found = media.gallery.find((item) => item.id === id) ?? named.find((item) => item.id === id);
  if (!found) throw new Error(`Chýba fotografia ${id}`);
  return found;
}

export function formatSkDate(iso: string): string {
  const [year, month, day] = iso.split("-");
  return `${Number(day)}. ${Number(month)}. ${year}`;
}

export function readingMinutes(entry: AdviceEntry): number {
  const extra = [
    ...entry.data.summary,
    ...entry.data.faq.flatMap((item) => [item.q, item.a]),
  ].join(" ");
  const plain = `${entry.body ?? ""} ${extra}`
    .replace(/```[\s\S]*?```/g, " ")
    .replace(/!\[[^\]]*\]\([^)]*\)/g, " ")
    .replace(/\[[^\]]*\]\([^)]*\)/g, " ")
    .replace(/[#>*_`|]/g, " ");
  const words = plain.split(/\s+/).filter((word) => word.length > 1).length;
  return Math.max(1, Math.round(words / 180));
}

export function relatedAdvice(items: AdviceEntry[], entry: AdviceEntry): AdviceEntry[] {
  return entry.data.related.map((id) => {
    const found = items.find((item) => item.id === id);
    if (!found) throw new Error(`Súvisiaci článok „${id}“ chýba v článku ${entry.id}`);
    return found;
  });
}
