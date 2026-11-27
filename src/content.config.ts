import { defineCollection } from "astro:content";
import { glob } from "astro/loaders";
import { z } from "astro/zod";

const rady = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./content/rady" }),
  schema: z.object({
    title: z.string().min(8),
    seoTitle: z.string().min(8).max(60),
    description: z.string().min(40).max(155),
    published: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    updated: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    author: z.string().default("Tím Sály Kinekus"),
    image: z.string(),
    order: z.number().int().positive(),
    featured: z.boolean().default(false),
    related: z.array(z.string()).default([]),
    summary: z.array(z.string().min(20)).min(2).max(4),
    faq: z.array(z.object({
      q: z.string().min(8),
      a: z.string().min(20),
    })).min(3).max(5),
  }),
});

export const collections = { rady };
