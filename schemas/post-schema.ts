import { z } from "zod";

export const postSchema = z.object({
  title: z
    .string()
    .trim()
    .min(3, "Title must be at least 3 characters."),

  slug: z
    .string()
    .trim()
    .min(3, "Slug is required."),

  excerpt: z
    .string()
    .trim()
    .optional()
    .or(z.literal("")),

  content: z
    .string()
    .trim()
    .min(1, "Content is required."),

  status: z.enum(["draft", "published"]),

  // ✅ Match Laravel enum
  post_type: z.enum([
    "article",
    "tutorial",
    "review",
    "comparison",
    "news",
  ]),

  allow_comments: z.boolean(),

  category_id: z.number().min(0),
});

export type PostFormValues = z.infer<typeof postSchema>;