const TZ = "Europe/Prague";

const months = [
  "január", "február", "marec", "apríl", "máj", "jún",
  "júl", "august", "september", "október", "november", "december",
];
const weekdays = ["Po", "Ut", "St", "Št", "Pi", "So", "Ne"];

function pragueToday(): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: TZ,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
}

function addMonths(year: number, month: number, delta: number): { year: number; month: number } {
  const index = year * 12 + (month - 1) + delta;
  return { year: Math.floor(index / 12), month: (index % 12) + 1 };
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

function iso(year: number, month: number, day: number): string {
  return `${year}-${pad(month)}-${pad(day)}`;
}

function formatSk(date: string): string {
  const [y, m, d] = date.split("-");
  return `${Number(d)}. ${Number(m)}. ${y}`;
}

function parseDateToken(part: string): string | null {
  const isoMatch = part.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (isoMatch) return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
  const skMatch = part.match(/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/);
  if (!skMatch) return null;
  return `${skMatch[3]}-${skMatch[2].padStart(2, "0")}-${skMatch[1].padStart(2, "0")}`;
}

function gridColumns(count: number): number {
  if (count <= 1) return 1;
  const options = [3, 2, 4, 5];
  const exact = options.find((cols) => count % cols === 0);
  if (exact) return exact;
  const safe = options.find((cols) => count % cols !== 1);
  return safe ?? 3;
}

function setupNav(): void {
  const toggle = document.querySelector<HTMLButtonElement>("[data-nav-toggle]");
  const panel = document.querySelector<HTMLElement>("#site-nav");
  const label = toggle?.querySelector<HTMLElement>("[data-nav-label]");
  const backdrop = document.querySelector<HTMLElement>("[data-nav-backdrop]");
  if (!toggle || !panel) return;

  const paint = (open: boolean) => {
    const desktop = window.innerWidth >= 980;
    panel.hidden = desktop ? false : !open;
    toggle.setAttribute("aria-expanded", !desktop && open ? "true" : "false");
    if (label) label.textContent = !desktop && open ? "Zavrieť" : "Menu";
    if (backdrop) backdrop.hidden = desktop || !open;
  };

  paint(false);

  toggle.addEventListener("click", () => {
    paint(toggle.getAttribute("aria-expanded") !== "true");
    if (toggle.getAttribute("aria-expanded") === "true") {
      panel.querySelector<HTMLElement>("a")?.focus();
    }
  });
  backdrop?.addEventListener("click", () => {
    paint(false);
    toggle.focus();
  });
  panel.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 980) paint(false);
    });
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
      paint(false);
      toggle.focus();
    }
  });
  window.addEventListener("resize", () => paint(false));
}

function setupHeader(): void {
  const header = document.querySelector<HTMLElement>(".header");
  if (!header) return;
  const paint = () => header.classList.toggle("is-stuck", window.scrollY > 8);
  paint();
  window.addEventListener("scroll", paint, { passive: true });
}

function setupDock(): void {
  const dock = document.querySelector<HTMLElement>(".dock");
  if (!dock) return;
  const targets = [...document.querySelectorAll<HTMLElement>("#termin, .footer")];
  if (targets.length === 0 || !("IntersectionObserver" in window)) return;
  const visible = new Set<Element>();
  const paint = () => dock.classList.toggle("is-hidden", visible.size > 0);
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) visible.add(entry.target);
        else visible.delete(entry.target);
      });
      paint();
    },
    { rootMargin: "0px 0px -72px 0px", threshold: 0.01 },
  );
  targets.forEach((target) => observer.observe(target));
}

type Availability = {
  occupied: string[] | null;
  updated: string | null;
  source?: string;
};

async function loadAvailability(endpoint: string): Promise<Availability> {
  const response = await fetch(endpoint, { headers: { Accept: "application/json" } });
  if (!response.ok) throw new Error("availability");
  const data = (await response.json()) as Availability;
  if (!data || !Array.isArray(data.occupied)) throw new Error("availability");
  return data;
}

function setupBooking(root: HTMLElement): void {
  const calendar = root.querySelector<HTMLElement>("[data-calendar]");
  const form = root.querySelector<HTMLFormElement>("[data-form]");
  const note = root.querySelector<HTMLElement>("[data-cal-note]");
  const error = root.querySelector<HTMLElement>("[data-cal-error]");
  const thanks = root.querySelector<HTMLElement>("[data-thanks]");
  if (!calendar || !form || !thanks) return;

  const datesInput = form.querySelector<HTMLInputElement>("[name=dates]");
  const kindSelect = form.querySelector<HTMLSelectElement>("[name=kind]");
  const selected = new Set<string>();
  const today = pragueToday();
  const [ty, tm] = today.split("-").map(Number);
  const horizon = addMonths(ty, tm, 18);
  let cursor = { year: ty, month: tm };
  let occupied = new Set<string>();

  const presetKind = form.dataset.kind || "";
  if (presetKind && kindSelect) kindSelect.value = presetKind;
  syncSegment();
  kindSelect?.addEventListener("change", syncSegment);

  function syncSegment(): void {
    const hidden = form.querySelector<HTMLInputElement>("input[name=segment]");
    if (!hidden || !kindSelect) return;
    const next = kindSelect.selectedOptions[0]?.dataset.segment || "";
    if (next) hidden.value = next;
  }

  function syncDates(): void {
    const values = [...selected].sort();
    if (datesInput) datesInput.value = values.map(formatSk).join(", ");
    const list = root.querySelector<HTMLElement>("[data-selected]");
    if (!list) return;
    list.replaceChildren();
    values.forEach((date) => {
      const item = document.createElement("li");
      const button = document.createElement("button");
      button.type = "button";
      button.className = "chip";
      button.textContent = `${formatSk(date)} ×`;
      button.setAttribute("aria-label", `Odobrať ${formatSk(date)}`);
      button.addEventListener("click", () => {
        selected.delete(date);
        syncDates();
        render();
      });
      item.append(button);
      list.append(item);
    });
  }

  function render(): void {
    const { year, month } = cursor;
    const first = new Date(Date.UTC(year, month - 1, 1));
    const startPad = (first.getUTCDay() + 6) % 7;
    const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const atStart = year === ty && month === tm;
    const atEnd = year > horizon.year || (year === horizon.year && month >= horizon.month);

    const head = document.createElement("div");
    head.className = "cal-head";
    const prev = document.createElement("button");
    prev.type = "button";
    prev.className = "cal-nav";
    prev.textContent = "←";
    prev.setAttribute("aria-label", "Predchádzajúci mesiac");
    prev.disabled = atStart;
    prev.addEventListener("click", () => {
      cursor = addMonths(year, month, -1);
      render();
    });
    const title = document.createElement("h3");
    title.id = "cal-title";
    title.textContent = `${months[month - 1]} ${year}`;
    const next = document.createElement("button");
    next.type = "button";
    next.className = "cal-nav";
    next.textContent = "→";
    next.setAttribute("aria-label", "Ďalší mesiac");
    next.disabled = atEnd;
    next.addEventListener("click", () => {
      cursor = addMonths(year, month, 1);
      render();
    });
    head.append(prev, title, next);

    const week = document.createElement("div");
    week.className = "cal-week";
    weekdays.forEach((label) => {
      const cell = document.createElement("span");
      cell.textContent = label;
      week.append(cell);
    });

    const grid = document.createElement("div");
    grid.className = "cal-grid";
    grid.setAttribute("role", "group");
    grid.setAttribute("aria-label", `Dni v mesiaci ${months[month - 1]} ${year}`);

    const cells: HTMLButtonElement[] = [];
    for (let i = 0; i < startPad; i += 1) {
      const empty = document.createElement("span");
      grid.append(empty);
    }
    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = iso(year, month, day);
      const button = document.createElement("button");
      button.type = "button";
      button.className = "day";
      button.textContent = String(day);
      button.dataset.date = date;
      let state: "past" | "today" | "occupied" | "free" = "free";
      if (date < today) state = "past";
      else if (date === today) state = "today";
      else if (occupied.has(date)) state = "occupied";
      button.dataset.state = state;
      const stateLabel = state === "past" ? "minulosť" : state === "today" ? "dnes, nedostupné" : state === "occupied" ? "obsadené" : "voľné";
      const label = `${formatSk(date)}, ${stateLabel}`;
      button.setAttribute("aria-label", label);
      button.setAttribute("aria-pressed", selected.has(date) ? "true" : "false");
      if (state !== "free") button.setAttribute("aria-disabled", "true");
      button.addEventListener("click", () => {
        if (button.getAttribute("aria-disabled") === "true") return;
        toggle(date);
      });
      grid.append(button);
      cells.push(button);
    }

    grid.addEventListener("keydown", (event) => {
      const current = document.activeElement;
      if (!(current instanceof HTMLButtonElement) || !current.dataset.date) return;
      const index = cells.indexOf(current);
      const move: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -7,
        ArrowDown: 7,
      };
      const delta = move[event.key];
      if (delta === undefined) return;
      const target = cells[index + delta];
      if (!target) return;
      event.preventDefault();
      target.focus();
    });

    const legend = document.createElement("ul");
    legend.className = "legend";
    legend.innerHTML = `
      <li><i class="free"></i>Voľné</li>
      <li><i class="busy"></i>Obsadené</li>
      <li><i class="picked"></i>Vybrané</li>
    `;

    calendar.replaceChildren(head, week, grid, legend);
    paintNextFree(year, month, daysInMonth);
  }

  function paintNextFree(year: number, month: number, daysInMonth: number): void {
    const slot = root.querySelector<HTMLElement>("[data-next-free]");
    if (!slot) return;
    let freeInMonth = 0;
    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = iso(year, month, day);
      if (date > today && !occupied.has(date)) freeInMonth += 1;
    }
    const next = nextFreeDate();
    if (freeInMonth >= 5 || !next) {
      slot.hidden = true;
      slot.replaceChildren();
      return;
    }
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = `Ďalší voľný termín: ${formatSk(next)}`;
    button.addEventListener("click", () => {
      const [ny, nm] = next.split("-").map(Number);
      cursor = { year: ny, month: nm };
      render();
    });
    slot.replaceChildren(button);
    slot.hidden = false;
  }

  function nextFreeDate(): string | null {
    let year = ty;
    let month = tm;
    const end = horizon;
    while (year < end.year || (year === end.year && month <= end.month)) {
      const days = new Date(Date.UTC(year, month, 0)).getUTCDate();
      for (let day = 1; day <= days; day += 1) {
        const date = iso(year, month, day);
        if (date > today && !occupied.has(date)) return date;
      }
      const step = addMonths(year, month, 1);
      year = step.year;
      month = step.month;
    }
    return null;
  }

  function toggle(date: string): void {
    if (date <= today || occupied.has(date)) return;
    if (selected.has(date)) selected.delete(date);
    else selected.add(date);
    syncDates();
    render();
  }

  const params = new URLSearchParams(window.location.search);
  params.getAll("d").forEach((date) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) selected.add(date);
  });
  if (params.get("odoslane") === "1") {
    form.hidden = true;
    thanks.hidden = false;
  }
  if (params.get("chyba") === "1") {
    const box = form.querySelector<HTMLElement>("[data-form-error]");
    if (box) {
      box.hidden = false;
      box.textContent = "Správu sa nepodarilo odoslať. Napíšte nám na marketing@salazilina.sk.";
    }
  }

  render();
  loadAvailability(calendar.dataset.endpoint || "/api/availability.php")
    .then((data) => {
      occupied = new Set(data.occupied || []);
      [...selected].forEach((date) => {
        if (occupied.has(date) || date <= today) selected.delete(date);
      });
      syncDates();
      render();
      if (note && data.source === "mock") {
        note.hidden = false;
        note.textContent = "Ukážkové dni. Po doplnení kalendára sa tu zobrazí skutočná obsadenosť.";
      }
    })
    .catch(() => {
      if (error) error.hidden = false;
      if (datesInput) datesInput.readOnly = false;
    });

  if (datesInput) {
    datesInput.addEventListener("change", () => {
      selected.clear();
      datesInput.value.split(/[,;]+/).forEach((part) => {
        const date = parseDateToken(part.trim());
        if (date) selected.add(date);
      });
      syncDates();
      render();
    });
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = form.querySelector<HTMLButtonElement>("[type=submit]");
    const box = form.querySelector<HTMLElement>("[data-form-error]");
    form.querySelectorAll<HTMLElement>(".field-error").forEach((node) => {
      node.hidden = true;
      node.textContent = "";
    });
    form.querySelectorAll<HTMLElement>("[aria-invalid]").forEach((node) => node.removeAttribute("aria-invalid"));
    if (box) box.hidden = true;
    if (button) button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json", "X-Requested-With": "fetch" },
      });
      const data = (await response.json()) as { ok?: boolean; errors?: Record<string, string> };
      if (data.ok) {
        form.hidden = true;
        thanks.hidden = false;
        thanks.focus();
        return;
      }
      const errors = data.errors || {};
      Object.entries(errors).forEach(([key, message]) => {
        if (key === "form") {
          if (box) {
            box.hidden = false;
            box.textContent = message;
          }
          return;
        }
        const field = form.querySelector<HTMLElement>(`[name="${key}"]`);
        const slot = form.querySelector<HTMLElement>(`[data-error-for="${key}"]`);
        if (field) field.setAttribute("aria-invalid", "true");
        if (slot) {
          slot.hidden = false;
          slot.textContent = message;
        }
      });
    } catch {
      if (box) {
        box.hidden = false;
        box.textContent = "Správu sa nepodarilo odoslať. Napíšte nám na marketing@salazilina.sk.";
      }
    } finally {
      if (button) button.disabled = false;
    }
  });
}

function setupGallery(root: HTMLElement): void {
  const buttons = [...root.querySelectorAll<HTMLButtonElement>("[data-filter]")];
  const shots = [...root.querySelectorAll<HTMLElement>("[data-cat]")];
  const dialog = root.querySelector<HTMLDialogElement>("dialog");
  const image = dialog?.querySelector<HTMLImageElement>("img");
  const caption = dialog?.querySelector<HTMLElement>("[data-caption]");

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const filter = button.dataset.filter || "all";
      buttons.forEach((item) => item.setAttribute("aria-pressed", item === button ? "true" : "false"));
      shots.forEach((shot) => {
        shot.hidden = filter !== "all" && shot.dataset.cat !== filter;
      });
    });
  });

  shots.forEach((shot) => {
    shot.addEventListener("click", () => {
      if (!dialog || !image) return;
      image.src = shot.dataset.full || "";
      image.alt = shot.dataset.alt || "";
      if (caption) caption.textContent = shot.dataset.alt || "";
      dialog.showModal();
    });
  });
}

function httpsUrl(value: unknown): string {
  return typeof value === "string" && value.startsWith("https://") ? value : "";
}

type PublicReview = { author?: string; rating?: number; text?: string; url?: string };

function reviewBits(review: PublicReview): { text: string; meta: string } | null {
  const text = typeof review.text === "string" ? review.text.trim() : "";
  const rating = Number(review.rating);
  if (!text || !Number.isInteger(rating) || rating < 4 || rating > 5) return null;
  const author = typeof review.author === "string" ? review.author.trim() : "";
  const meta = [author, `${rating} ★`].filter(Boolean).join(" · ");
  return { text, meta };
}

function fillQuoteFigure(root: HTMLElement, review: PublicReview): boolean {
  const bits = reviewBits(review);
  if (!bits) return false;
  const quote = root.querySelector("[data-hero-quote-text], [data-form-quote-text]");
  const by = root.querySelector("[data-hero-quote-by], [data-form-quote-by]");
  if (quote) quote.textContent = `„${bits.text}“`;
  if (by) by.textContent = bits.meta;
  root.hidden = false;
  return true;
}

function fillReviewList(list: HTMLElement, reviews: PublicReview[], listing: string): number {
  let shown = 0;
  reviews.forEach((review) => {
    const bits = reviewBits(review);
    if (!bits) return;
    const article = document.createElement("article");
    article.className = "greview";
    const meta = document.createElement("p");
    meta.className = "greview-author";
    meta.textContent = bits.meta;
    article.append(meta);
    const block = document.createElement("blockquote");
    block.textContent = bits.text;
    article.append(block);
    const href = listing || httpsUrl(review.url);
    if (href) {
      const link = document.createElement("a");
      link.href = href;
      link.rel = "noopener noreferrer";
      link.textContent = "Recenzia na Google";
      article.append(link);
    }
    list.append(article);
    shown += 1;
  });
  return shown;
}

function setupReviews(): void {
  const hero = document.querySelector<HTMLElement>("[data-hero-quote]");
  const section = document.querySelector<HTMLElement>("[data-reviews]");
  const formQuotes = [...document.querySelectorAll<HTMLElement>("[data-form-quote]")];
  const sectionBlocks = [...document.querySelectorAll<HTMLElement>("[data-review-section]")];
  if (!hero && !section && formQuotes.length === 0 && sectionBlocks.length === 0) return;

  fetch("/api/reviews.php", { headers: { Accept: "application/json" } })
    .then(async (response) => {
      if (!response.ok) return;
      const data = (await response.json()) as {
        available?: boolean;
        listingUrl?: string;
        writeUrl?: string;
        hero?: PublicReview;
        formQuote?: PublicReview;
        sectionQuotes?: Record<string, PublicReview>;
        themes?: Array<{ text?: string }>;
        reviews?: PublicReview[];
        sections?: Record<string, PublicReview[]>;
      };
      if (data.available !== true) return;

      const listing = httpsUrl(data.listingUrl);
      const write = httpsUrl(data.writeUrl);

      if (hero && data.hero) fillQuoteFigure(hero, data.hero);

      formQuotes.forEach((formQuote) => {
        const key = formQuote.dataset.quoteSection || "";
        const source = key && data.sectionQuotes?.[key] ? data.sectionQuotes[key] : data.formQuote;
        if (source) fillQuoteFigure(formQuote, source);
      });

      const showWrite = (root: ParentNode) => {
        const writeLink = root.querySelector<HTMLAnchorElement>("[data-write-review]");
        if (writeLink && write) writeLink.href = write;
      };

      if (section) {
        const themeList = section.querySelector("[data-review-themes]");
        (data.themes || []).forEach((theme) => {
          const text = typeof theme.text === "string" ? theme.text.trim() : "";
          if (!text || !themeList) return;
          const item = document.createElement("li");
          item.textContent = `„${text}“`;
          themeList.append(item);
        });
        const list = section.querySelector<HTMLElement>("[data-review-list]");
        const shown = list ? fillReviewList(list, data.reviews || [], listing) : 0;
        if (list) list.style.setProperty("--cols", String(gridColumns(shown)));
        if (themeList instanceof HTMLElement) {
          const themes = themeList.children.length;
          themeList.style.setProperty("--cols", String(themes >= 5 ? 5 : gridColumns(themes)));
        }
        if (shown > 0) {
          showWrite(section);
          section.hidden = false;
        }
      }

      sectionBlocks.forEach((block) => {
        const key = block.dataset.reviewSection || "";
        const reviews = key && data.sections?.[key] ? data.sections[key] : [];
        const list = block.querySelector<HTMLElement>("[data-review-list]");
        const shown = list ? fillReviewList(list, reviews, listing) : 0;
        if (list) list.style.setProperty("--cols", String(gridColumns(shown)));
        if (shown > 0) {
          showWrite(block);
          block.hidden = false;
        }
      });
    })
    .catch(() => {});
}

function activateFrame(frame: HTMLIFrameElement | null): boolean {
  if (!frame || frame.getAttribute("src")) return false;
  const src = frame.dataset.src || "";
  if (!src.startsWith("https://")) return false;
  frame.src = src;
  frame.hidden = false;
  frame.focus();
  return true;
}

function setupTour(root: HTMLElement): void {
  const button = root.querySelector<HTMLButtonElement>("[data-tour-open]");
  const frame = root.querySelector<HTMLIFrameElement>("[data-tour-frame]");
  const poster = root.querySelector<HTMLElement>("[data-tour-poster]");
  button?.addEventListener("click", () => {
    if (!activateFrame(frame)) return;
    if (poster) poster.hidden = true;
  });
}

function setupMap(root: HTMLElement): void {
  const button = root.querySelector<HTMLButtonElement>("[data-map-open]");
  const frame = root.querySelector<HTMLIFrameElement>("[data-map-frame]");
  const poster = root.querySelector<HTMLElement>("[data-map-poster]");
  if (!button) return;
  button.addEventListener("click", () => {
    const src = frame?.dataset.src || "";
    if (!src.startsWith("https://www.google.com/maps?")) return;
    if (!activateFrame(frame)) return;
    if (poster) poster.hidden = true;
    button.hidden = true;
  });
}

function setupConsent(): void {
  const box = document.querySelector<HTMLElement>("[data-consent]");
  if (!box) return;
  try {
    if (localStorage.getItem("sala-consent") === "1") return;
  } catch {
    return;
  }
  box.hidden = false;
  box.querySelector("[data-consent-ok]")?.addEventListener("click", () => {
    try {
      localStorage.setItem("sala-consent", "1");
    } catch {
      /* úložisko môže byť vypnuté */
    }
    box.hidden = true;
  });
}

function setupViewing(): void {
  const line = "Chcem si dohodnúť obhliadku sály.";
  document.querySelectorAll<HTMLAnchorElement>("[data-viewing]").forEach((link) => {
    link.addEventListener("click", () => {
      const field = document.querySelector<HTMLTextAreaElement>('textarea[name="message"]');
      if (!field || field.value.includes(line)) return;
      const current = field.value.trim();
      field.value = current === "" ? line : `${current}\n${line}`;
    });
  });
}

setupNav();
setupHeader();
setupDock();
document.querySelectorAll<HTMLElement>("[data-booking]").forEach(setupBooking);
document.querySelectorAll<HTMLElement>("[data-gallery]").forEach(setupGallery);
setupReviews();
document.querySelectorAll<HTMLElement>("[data-tour]").forEach(setupTour);
document.querySelectorAll<HTMLElement>("[data-map]").forEach(setupMap);
setupConsent();
setupViewing();
