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
  let lastWidth = window.innerWidth;

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
  window.addEventListener("resize", () => {
    const width = window.innerWidth;
    if (width === lastWidth) return;
    lastWidth = width;
    paint(false);
  });
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

function setupBooking(root: HTMLElement): void {
  const form = root.querySelector<HTMLFormElement>("[data-form]");
  const thanks = root.querySelector<HTMLElement>("[data-thanks]");
  if (!form || !thanks) return;

  const kindSelect = form.querySelector<HTMLSelectElement>("[name=kind]");
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

  const params = new URLSearchParams(window.location.search);
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

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = form.querySelector<HTMLButtonElement>("[type=submit]");
    const box = form.querySelector<HTMLElement>("[data-form-error]");
    form.querySelectorAll<HTMLElement>(".field-error").forEach((node) => {
      node.hidden = true;
      node.textContent = "";
    });
    form.querySelectorAll<HTMLElement>("[aria-invalid]").forEach((node) => node.removeAttribute("aria-invalid"));
    form.removeAttribute("aria-invalid");
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
          form.setAttribute("aria-invalid", "true");
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
