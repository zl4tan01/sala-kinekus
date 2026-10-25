import type { APIRoute } from "astro";
import { adviceHref, allAdvice } from "../lib/advice";
import { site } from "../data/site";

export const GET: APIRoute = async () => {
  const articles = await allAdvice();
  const lines = [
    "# Sála Kinekus",
    "",
    "Spoločenská sála v Žiline na adrese Rosinská cesta 13, 010 08 Žilina.",
    `Telefón: ${site.phone}`,
    `E-mail: ${site.email}`,
    `Web: ${site.url}`,
    "",
    "Kapacita je 50 až 250 osôb. V sále je parket, pódium 12×6 m, balkón a terasa. Parkovanie je pred budovou. Ozvučenie má mixážny pult Yamaha a mikrofóny. Na projekciu je Full HD projektor so svietivosťou 5 400 lumenov a SMART zatemnenie okien. Vlastnú kuchyňu sála neprevádzkuje, cateringovú firmu vie odporučiť.",
    "",
    "Dopyt cez web termín nerezervuje. Deň platí až po potvrdení tímom sály. Na webe nie sú ceny ani lehota odpovede.",
    "",
    "## Stránky",
    "",
    `- [Úvod](${site.url}/)`,
    `- [Svadby](${site.url}/svadby/)`,
    `- [Stužkové](${site.url}/stuzkova/)`,
    `- [Pre firmy](${site.url}/konferencie/)`,
    `- [Catering](${site.url}/catering/)`,
    `- [Galéria](${site.url}/galeria/)`,
    `- [Referencie](${site.url}/referencie/)`,
    `- [Kontakt](${site.url}/kontakt/)`,
    `- [Rady](${site.url}/rady/)`,
    `- [Overiť voľný termín](${site.url}/#termin)`,
    "",
    "## Rady",
    "",
    ...articles.map((article) => `- [${article.data.title}](${new URL(adviceHref(article.id), site.url)}) — ${article.data.description}`),
    "",
  ];
  return new Response(lines.join("\n"), {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
};
