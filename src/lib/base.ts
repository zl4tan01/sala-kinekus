/** Root-relative URL with Astro `base` (SITE_BASE). External and hash-only links stay as written. */
export function url(path: string): string {
  if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(path)) return path;
  if (path.startsWith("#")) return path;
  const base = import.meta.env.BASE_URL || "/";
  const prefix = base.endsWith("/") ? base.slice(0, -1) : base;
  const normalized = path.startsWith("/") ? path : `/${path}`;
  if (prefix && (normalized === prefix || normalized.startsWith(`${prefix}/`))) return normalized;
  return `${prefix}${normalized}`;
}

export function absolute(path: string, origin: string | URL): string {
  return new URL(url(path), origin).toString();
}
