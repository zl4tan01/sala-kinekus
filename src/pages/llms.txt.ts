import type { APIRoute } from "astro";
import { adviceHref, allAdvice } from "../lib/advice";
import { site } from "../data/site";
import { absolute } from "../lib/base";

export const GET: APIRoute = async () => {
  const articles = await allAdvice();
  const origin = import.meta.env.SITE || site.url;
  const link = (path: string) => absolute(path, origin);
  const lines = [
    "# Sála Kinekus",
    "",
    "Spoločenská sála v Žiline na adrese Rosinská cesta 13, 010 08 Žilina.",
    `Telefón: ${site.phone}`,
    `E-mail: ${site.email}`,
    `Web: ${origin}`,
    "",
    "Kapacita je 50 až 250 osôb. V sále je parket, pódium 12×6 m, balkón a terasa. Parkovanie je pred budovou. Ozvučenie má mixážny pult Yamaha a mikrofóny. Na projekciu je Full HD projektor so svietivosťou 5 400 lumenov, SMART osvetlenie a možnosť zastrieť okná. Vlastnú kuchyňu neprevádzkujeme, cateringovú firmu vieme odporučiť.",
    "",
    "Dopyt cez web termín nerezervuje. Deň platí až po potvrdení tímom sály. Web neukazuje, ktoré dni sú voľné. Na webe nie sú ceny ani lehota odpovede.",
    "",
    "## Stránky",
    "",
    `- [Úvod](${link("/")})`,
    `- [Svadby](${link("/svadby/")})`,
    `- [Stužkové](${link("/stuzkova/")})`,
    `- [Pre firmy](${link("/konferencie/")})`,
    `- [Catering](${link("/catering/")})`,
    `- [Galéria](${link("/galeria/")})`,
    `- [Referencie](${link("/referencie/")})`,
    `- [Kontakt](${link("/kontakt/")})`,
    `- [Rady](${link("/rady/")})`,
    `- [Poslať nezáväzný dopyt](${link("/#termin")})`,
    "",
    "## Rady",
    "",
    ...articles.map((article) => `- [${article.data.title}](${absolute(adviceHref(article.id), origin)}) — ${article.data.description}`),
    "",
  ];
  return new Response(lines.join("\n"), {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
};
